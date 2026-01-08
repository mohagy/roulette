<?php
/**
 * Save Detailed Draw Result
 * This script saves the roulette draw results with detailed statistics
 * to the draw_results table.
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers for CORS and JSON response
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed']);
    exit;
}

// Get the JSON data from the request body
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// If JSON parsing failed, try to get data from $_POST
if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
    $data = $_POST;
}

// Check if the required data is present
if (!isset($data['winningNumber'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Missing winningNumber']);
    exit;
}

// Set defaults for missing data
$winning_number = intval($data['winningNumber']);
$winning_color = isset($data['winningColor']) ? $data['winningColor'] : determineColor($winning_number);
$draw_number = isset($data['draw_number']) ? intval($data['draw_number']) : getCurrentDrawNumber();
$total_bets = isset($data['totalBets']) ? intval($data['totalBets']) : 0;
$total_bet_amount = isset($data['total_bet_amount']) ? floatval($data['total_bet_amount']) : 0.00;
$total_payout = isset($data['totalPayout']) ? floatval($data['totalPayout']) : 0.00;
$session_id = isset($data['gameSessionId']) ? $data['gameSessionId'] : generateSessionId();
$tv_display_id = isset($data['tableId']) ? $data['tableId'] : null;

try {
    // Connect to the database
    $conn = getDatabaseConnection();

    // Use the stored procedure to save the draw result
    $stmt = $conn->prepare("CALL save_draw_result(?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisiddss",
        $draw_number,
        $winning_number,
        $winning_color,
        $total_bets,
        $total_bet_amount,
        $total_payout,
        $session_id,
        $tv_display_id
    );

    // Execute the statement
    if ($stmt->execute()) {
        // Get the result of the stored procedure
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $draw_id = $row['draw_id'];

        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Draw result saved successfully',
            'draw_id' => $draw_id,
            'draw_number' => $draw_number,
            'winning_number' => $winning_number,
            'winning_color' => $winning_color
        ]);
    } else {
        // Return error response
        http_response_code(500); // Internal Server Error
        echo json_encode([
            'success' => false,
            'message' => 'Failed to save draw result: ' . $stmt->error
        ]);
    }

    // Close the statement and connection
    $stmt->close();
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
 * Determine the color based on the number
 * @param int $number The roulette number
 * @return string The color (red, black, or green)
 */
function determineColor($number) {
    // 0 is green
    if ($number === 0) {
        return 'green';
    }

    // Define red numbers
    $red_numbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];

    // Check if the number is in the red numbers array
    if (in_array($number, $red_numbers)) {
        return 'red';
    } else {
        return 'black';
    }
}

/**
 * Get the current draw number (highest draw_number + 1)
 * @return int The next draw number
 */
function getCurrentDrawNumber() {
    try {
        $conn = getDatabaseConnection();

        // First check roulette_state table for the current draw number
        $stateCheckResult = $conn->query("SHOW TABLES LIKE 'roulette_state'");
        $stateTableExists = $stateCheckResult->num_rows > 0;

        if ($stateTableExists) {
            $stmt = $conn->prepare("SELECT current_draw_number FROM roulette_state WHERE id = 1");
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $currentDrawNumber = intval($row['current_draw_number']);

                // If current_draw_number is valid, use it
                if ($currentDrawNumber > 0) {
                    $stmt->close();
                    $conn->close();
                    return $currentDrawNumber;
                }
            }
        }

        // Next check roulette_analytics table
        $analyticsCheckResult = $conn->query("SHOW TABLES LIKE 'roulette_analytics'");
        $analyticsTableExists = $analyticsCheckResult->num_rows > 0;

        if ($analyticsTableExists) {
            $stmt = $conn->prepare("SELECT current_draw_number FROM roulette_analytics WHERE id = 1");
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $currentDrawNumber = intval($row['current_draw_number']);

                // If current_draw_number is valid, use it
                if ($currentDrawNumber > 0) {
                    $stmt->close();
                    $conn->close();
                    return $currentDrawNumber;
                }
            }
        }

        // Next check roulette_game_state table
        $gameStateCheckResult = $conn->query("SHOW TABLES LIKE 'roulette_game_state'");
        $gameStateTableExists = $gameStateCheckResult->num_rows > 0;

        if ($gameStateTableExists) {
            $stmt = $conn->prepare("SELECT next_draw_number FROM roulette_game_state WHERE id = 1");
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $nextDrawNumber = intval($row['next_draw_number']);

                // If next_draw_number is valid, use it
                if ($nextDrawNumber > 0) {
                    $stmt->close();
                    $conn->close();
                    return $nextDrawNumber;
                }
            }
        }

        // If we couldn't get the draw number from state tables, check draw_results
        $tableCheckResult = $conn->query("SHOW TABLES LIKE 'draw_results'");
        $tableExists = $tableCheckResult->num_rows > 0;

        if ($tableExists) {
            // Get the count of records in the draw_results table
            $countStmt = $conn->prepare("SELECT COUNT(*) AS record_count FROM draw_results");
            $countStmt->execute();
            $countResult = $countStmt->get_result();
            $countRow = $countResult->fetch_assoc();
            $countStmt->close();

            // If the table is empty, start with 1
            if ($countRow['record_count'] == 0) {
                $conn->close();
                return 1;
            }

            // Get the highest draw_number from the draw_results table
            $stmt = $conn->prepare("SELECT MAX(draw_number) AS max_draw_number FROM draw_results");
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();

            $stmt->close();

            // If no draw_number exists yet, start with 1
            if ($row['max_draw_number'] === null) {
                $conn->close();
                return 1;
            }

            // Return the next draw number
            $conn->close();
            return intval($row['max_draw_number']) + 1;
        }

        // If the table doesn't exist, try detailed_draw_results
        $tableCheckResult = $conn->query("SHOW TABLES LIKE 'detailed_draw_results'");
        $tableExists = $tableCheckResult->num_rows > 0;

        if ($tableExists) {
            // Get the count of records in the detailed_draw_results table
            $countStmt = $conn->prepare("SELECT COUNT(*) AS record_count FROM detailed_draw_results");
            $countStmt->execute();
            $countResult = $countStmt->get_result();
            $countRow = $countResult->fetch_assoc();
            $countStmt->close();

            // If the table is empty, start with 1
            if ($countRow['record_count'] == 0) {
                $conn->close();
                return 1;
            }

            // Get the highest draw_number from the detailed_draw_results table
            $stmt = $conn->prepare("SELECT MAX(draw_number) AS max_draw_number FROM detailed_draw_results");
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();

            $stmt->close();
            $conn->close();

            // If no draw_number exists yet, start with 1
            if ($row['max_draw_number'] === null) {
                return 1;
            }

            // Return the next draw number
            return intval($row['max_draw_number']) + 1;
        }

        // If neither table exists, start with 1
        $conn->close();
        return 1;

    } catch (Exception $e) {
        // If an error occurs, start with draw number 1
        return 1;
    }
}

/**
 * Generate a session ID if none is provided
 * @return string A session ID
 */
function generateSessionId() {
    // Use existing PHP session if available
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    return session_id();
}
?>