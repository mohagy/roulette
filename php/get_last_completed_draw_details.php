<?php
/**
 * Get Last Completed Draw Details API
 * Returns the winning number and winning slips count for the last completed draw
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database connection
$host = "localhost";
$username = "root";
$password = "";
$database = "roulette";

try {
    $conn = new mysqli($host, $username, $password, $database);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    // Get the last completed draw details
    $query = "
        SELECT
            ddr.draw_number,
            ddr.winning_number,
            ddr.timestamp,
            COUNT(DISTINCT bs.slip_id) as total_slips,
            COUNT(DISTINCT CASE WHEN bs.status = 'cashed_out' THEN bs.slip_id END) as winning_slips,
            COUNT(DISTINCT CASE WHEN bs.status = 'active' THEN bs.slip_id END) as active_slips
        FROM detailed_draw_results ddr
        LEFT JOIN betting_slips bs ON ddr.draw_number = bs.draw_number
        WHERE ddr.draw_number = (
            SELECT MAX(draw_number)
            FROM detailed_draw_results
            WHERE winning_number IS NOT NULL
        )
        GROUP BY ddr.draw_number, ddr.winning_number, ddr.timestamp
        ORDER BY ddr.draw_number DESC
        LIMIT 1
    ";

    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Get additional details about the winning number
        $winningNumber = $row['winning_number'];
        $color = 'green'; // Default for 0

        if ($winningNumber != 0) {
            // Determine color based on roulette wheel
            $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
            $color = in_array($winningNumber, $redNumbers) ? 'red' : 'black';
        }

        // Calculate win percentage
        $totalSlips = (int)$row['total_slips'];
        $winningSlips = (int)$row['winning_slips'];
        $activeSlips = (int)$row['active_slips'];
        $winPercentage = $totalSlips > 0 ? round(($winningSlips / $totalSlips) * 100, 1) : 0;

        // Format timestamp
        $timestamp = new DateTime($row['timestamp']);
        $timeAgo = $timestamp->diff(new DateTime())->format('%h hours %i minutes ago');

        $response = [
            'status' => 'success',
            'data' => [
                'draw_number' => (int)$row['draw_number'],
                'winning_number' => (int)$winningNumber,
                'winning_number_color' => $color,
                'total_slips' => $totalSlips,
                'winning_slips' => $winningSlips,
                'active_slips' => $activeSlips,
                'losing_slips' => $totalSlips - $winningSlips,
                'win_percentage' => $winPercentage,
                'timestamp' => $row['timestamp'],
                'time_ago' => $timeAgo,
                'formatted_time' => $timestamp->format('g:i A')
            ]
        ];

    } else {
        // No completed draws found, return default values
        $response = [
            'status' => 'success',
            'data' => [
                'draw_number' => null,
                'winning_number' => null,
                'winning_number_color' => null,
                'total_slips' => 0,
                'winning_slips' => 0,
                'losing_slips' => 0,
                'win_percentage' => 0,
                'timestamp' => null,
                'time_ago' => 'No completed draws',
                'formatted_time' => 'N/A'
            ]
        ];
    }

} catch (Exception $e) {
    $response = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'data' => [
            'draw_number' => null,
            'winning_number' => null,
            'winning_number_color' => null,
            'total_slips' => 0,
            'winning_slips' => 0,
            'losing_slips' => 0,
            'win_percentage' => 0,
            'timestamp' => null,
            'time_ago' => 'Error loading data',
            'formatted_time' => 'N/A'
        ]
    ];
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

echo json_encode($response);
?>
