<?php
/**
 * Get Detailed Draw Results
 * This script retrieves detailed draw results from the draw_results table
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers for CORS and JSON response
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

// Process parameters
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
$stats_only = isset($_GET['stats_only']) && $_GET['stats_only'] === 'true';
$recent_only = isset($_GET['recent_only']) && $_GET['recent_only'] === 'true';
$frequencies_only = isset($_GET['frequencies_only']) && $_GET['frequencies_only'] === 'true';
$draw_id = isset($_GET['draw_id']) ? intval($_GET['draw_id']) : null;
$draw_number = isset($_GET['draw_number']) ? intval($_GET['draw_number']) : null;
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : null;
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : null;

try {
    // Connect to the database
    $conn = getDatabaseConnection();

    // If specific draw_id or draw_number is requested, get that specific draw
    if ($draw_id || $draw_number) {
        $result = getSpecificDraw($conn, $draw_id, $draw_number);
        echo json_encode([
            'success' => true,
            'draw' => $result
        ]);
        exit;
    }

    // Handle specific requests
    $response = ['success' => true];

    // Get statistics if requested or no specific filter
    if ($stats_only || (!$recent_only && !$frequencies_only)) {
        $response['statistics'] = getStatistics($conn, $limit, $from_date, $to_date);
    }

    // Get recent draws if requested or no specific filter
    if ($recent_only || (!$stats_only && !$frequencies_only)) {
        $response['draws'] = getRecentDraws($conn, $limit, $from_date, $to_date);
    }

    // Get number frequencies if requested or no specific filter
    if ($frequencies_only || (!$stats_only && !$recent_only)) {
        $response['frequencies'] = getNumberFrequencies($conn, $limit, $from_date, $to_date);
    }

    // Return the response
    echo json_encode($response);

    // Close the connection
    $conn->close();

} catch (Exception $e) {
    // Return error response for any exceptions
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

/**
 * Get a database connection
 * @return mysqli Database connection
 */
function getDatabaseConnection() {
    // Database connection parameters
    $host = "localhost";
    $username = "root"; // Default XAMPP username
    $password = ""; // Default XAMPP password is empty
    $database = "roulette";

    // Create connection
    $conn = new mysqli($host, $username, $password, $database);

    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    return $conn;
}

/**
 * Get a specific draw by ID or draw number
 * @param mysqli $conn Database connection
 * @param int|null $draw_id Draw ID
 * @param int|null $draw_number Draw number
 * @return array|null The draw data or null if not found
 */
function getSpecificDraw($conn, $draw_id, $draw_number) {
    $sql = "";
    $param = null;

    if ($draw_id) {
        $sql = "SELECT * FROM draw_results WHERE draw_id = ?";
        $param = $draw_id;
    } else if ($draw_number) {
        $sql = "SELECT * FROM draw_results WHERE draw_number = ? ORDER BY draw_completed_at DESC LIMIT 1";
        $param = $draw_number;
    } else {
        return null;
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $param);
    $stmt->execute();
    $result = $stmt->get_result();
    $draw = $result->fetch_assoc();
    $stmt->close();

    return $draw;
}

/**
 * Get statistics based on draw results
 * @param mysqli $conn Database connection
 * @param int $limit Maximum number of draws to analyze
 * @param string|null $from_date Optional from date filter
 * @param string|null $to_date Optional to date filter
 * @return array Statistics data
 */
function getStatistics($conn, $limit, $from_date = null, $to_date = null) {
    try {
        // Create a simplified statistics array in case the database query fails
        $stats = [
            'total_draws' => 0,
            'red_count' => 0,
            'black_count' => 0,
            'green_count' => 0,
            'odd_count' => 0,
            'even_count' => 0,
            'high_count' => 0,
            'low_count' => 0
        ];

        // Get the most recent draws to calculate basic statistics
        $draws = getRecentDraws($conn, $limit, $from_date, $to_date);

        if (empty($draws)) {
            return $stats;
        }

        // Calculate statistics from the draws
        $stats['total_draws'] = count($draws);

        foreach ($draws as $draw) {
            // Count by color
            if ($draw['winning_color'] === 'red') {
                $stats['red_count']++;
            } else if ($draw['winning_color'] === 'black') {
                $stats['black_count']++;
            } else if ($draw['winning_color'] === 'green') {
                $stats['green_count']++;
            }

            // Skip zero for odd/even and high/low
            if ($draw['winning_number'] === 0) {
                continue;
            }

            // Count by odd/even
            if ($draw['winning_number'] % 2 === 1) {
                $stats['odd_count']++;
            } else {
                $stats['even_count']++;
            }

            // Count by high/low
            if ($draw['winning_number'] > 18) {
                $stats['high_count']++;
            } else {
                $stats['low_count']++;
            }
        }

        // Calculate percentages
        $nonZeroDraws = $stats['total_draws'] - $stats['green_count'];

        if ($stats['total_draws'] > 0) {
            $stats['red_percentage'] = round(($stats['red_count'] / $stats['total_draws']) * 100, 2);
            $stats['black_percentage'] = round(($stats['black_count'] / $stats['total_draws']) * 100, 2);
            $stats['green_percentage'] = round(($stats['green_count'] / $stats['total_draws']) * 100, 2);
        }

        if ($nonZeroDraws > 0) {
            $stats['odd_percentage'] = round(($stats['odd_count'] / $nonZeroDraws) * 100, 2);
            $stats['even_percentage'] = round(($stats['even_count'] / $nonZeroDraws) * 100, 2);
            $stats['high_percentage'] = round(($stats['high_count'] / $nonZeroDraws) * 100, 2);
            $stats['low_percentage'] = round(($stats['low_count'] / $nonZeroDraws) * 100, 2);
        }

        return $stats;
    } catch (Exception $e) {
        // Return basic statistics object in case of error
        return [
            'total_draws' => 0,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Get recent draws
 * @param mysqli $conn Database connection
 * @param int $limit Maximum number of draws to retrieve
 * @param string|null $from_date Optional from date filter
 * @param string|null $to_date Optional to date filter
 * @return array Recent draws data
 */
function getRecentDraws($conn, $limit, $from_date = null, $to_date = null) {
    try {
        $where_clause = "";
        $params = [];
        $types = "";

        // Build where clause if date filters are provided
        if ($from_date || $to_date) {
            if ($from_date) {
                $where_clause .= " WHERE draw_completed_at >= ?";
                $params[] = $from_date;
                $types .= "s";
            }

            if ($to_date) {
                $where_clause .= $from_date ? " AND" : " WHERE";
                $where_clause .= " draw_completed_at <= ?";
                $params[] = $to_date;
                $types .= "s";
            }
        }

        // Build the SQL query
        $sql = "
            SELECT *
            FROM draw_results
            " . $where_clause . "
            ORDER BY draw_completed_at DESC
            LIMIT ?
        ";

        // Add limit parameter
        $params[] = $limit;
        $types .= "i";

        // Prepare and execute the query
        $stmt = $conn->prepare($sql);

        // Bind parameters if any
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $draws = [];
        while ($row = $result->fetch_assoc()) {
            $draws[] = $row;
        }

        $stmt->close();

        return $draws;
    } catch (Exception $e) {
        // Return empty array in case of error
        return [];
    }
}

/**
 * Get number frequencies
 * @param mysqli $conn Database connection
 * @param int $limit Maximum number of draws to analyze
 * @param string|null $from_date Optional from date filter
 * @param string|null $to_date Optional to date filter
 * @return array Number frequencies data
 */
function getNumberFrequencies($conn, $limit, $from_date = null, $to_date = null) {
    try {
        // Get recent draws
        $draws = getRecentDraws($conn, $limit, $from_date, $to_date);

        // Initialize frequencies array with all numbers 0-36
        $frequencies = [];
        for ($i = 0; $i <= 36; $i++) {
            $frequencies[$i] = [
                'number' => $i,
                'frequency' => 0,
                'percentage' => 0
            ];
        }

        // Count frequencies
        $total_draws = count($draws);
        foreach ($draws as $draw) {
            $number = intval($draw['winning_number']);
            if (isset($frequencies[$number])) {
                $frequencies[$number]['frequency']++;
            }
        }

        // Calculate percentages and prepare response format
        $result = [];
        if ($total_draws > 0) {
            foreach ($frequencies as $number => $data) {
                $data['percentage'] = round(($data['frequency'] / $total_draws) * 100, 2);
                $result[] = $data;
            }
        }

        // Sort by frequency (highest first)
        usort($result, function($a, $b) {
            return $b['frequency'] - $a['frequency'];
        });

        return $result;
    } catch (Exception $e) {
        // Return empty array in case of error
        return [];
    }
}
?>