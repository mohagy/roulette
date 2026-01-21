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

    // ⚠️ CRITICAL: Get draw_number parameter if provided (server-time-based draw number)
    // Otherwise, get the last completed draw from today
    $drawNumberParam = isset($_GET['draw_number']) ? intval($_GET['draw_number']) : null;
    
    // Use server time to filter by today's draws only
    date_default_timezone_set('America/Guyana');
    $today = date('Y-m-d');
    
    // Check if analytics_history table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'analytics_history'");
    $hasAnalyticsHistory = $tableCheck && $tableCheck->num_rows > 0;
    
    // Try to get from analytics_history first (has server-time-based draw numbers)
    // Otherwise fall back to detailed_draw_results
    $result = null;
    $row = null;
    
    if ($drawNumberParam && $hasAnalyticsHistory) {
        // First try: Query analytics_history by specific draw number
        $query = "
            SELECT
                ah.draw_number,
                ah.winning_number,
                ah.draw_time as timestamp,
                COUNT(DISTINCT bs.slip_id) as total_slips,
                COUNT(DISTINCT CASE WHEN bs.status = 'cashed_out' THEN bs.slip_id END) as winning_slips,
                COUNT(DISTINCT CASE WHEN bs.status = 'active' THEN bs.slip_id END) as active_slips
            FROM analytics_history ah
            LEFT JOIN betting_slips bs ON ah.draw_number = bs.draw_number
            WHERE ah.draw_number = ? AND DATE(ah.draw_time) = ?
            GROUP BY ah.draw_number, ah.winning_number, ah.draw_time
            LIMIT 1
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('is', $drawNumberParam, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
    }
    
    // If no result from analytics_history, try detailed_draw_results as fallback
    if (!$row && $drawNumberParam) {
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
            WHERE ddr.draw_number = ?
            GROUP BY ddr.draw_number, ddr.winning_number, ddr.timestamp
            LIMIT 1
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $drawNumberParam);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
    }
    
    // If still no result and no draw_number param, get the last completed draw from today
    if (!$row && !$drawNumberParam && $hasAnalyticsHistory) {
        $query = "
            SELECT
                ah.draw_number,
                ah.winning_number,
                ah.draw_time as timestamp,
                COUNT(DISTINCT bs.slip_id) as total_slips,
                COUNT(DISTINCT CASE WHEN bs.status = 'cashed_out' THEN bs.slip_id END) as winning_slips,
                COUNT(DISTINCT CASE WHEN bs.status = 'active' THEN bs.slip_id END) as active_slips
            FROM analytics_history ah
            LEFT JOIN betting_slips bs ON ah.draw_number = bs.draw_number
            WHERE DATE(ah.draw_time) = ? AND ah.winning_number IS NOT NULL
            GROUP BY ah.draw_number, ah.winning_number, ah.draw_time
            ORDER BY ah.draw_number DESC
            LIMIT 1
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
    }
    
    // Final fallback: Get MAX draw from detailed_draw_results
    if (!$row) {
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
                SELECT MAX(draw_number) FROM detailed_draw_results WHERE winning_number IS NOT NULL
            )
            GROUP BY ddr.draw_number, ddr.winning_number, ddr.timestamp
            LIMIT 1
        ";
        
        $result = $conn->query($query);
        $row = $result ? $result->fetch_assoc() : null;
    }

    // $row is already fetched above
    if ($row) {

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
