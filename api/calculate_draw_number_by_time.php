<?php
/**
 * Calculate Draw Number by Time
 * This endpoint calculates the expected draw number based on the current time
 * and compares it with the actual draw number in the database
 */

header('Content-Type: application/json');
require_once '../includes/db_connection.php';
require_once '../includes/helper_functions.php';

date_default_timezone_set('America/La_Paz');

$inputTime = isset($_GET['time']) ? $_GET['time'] : null;
$inputDate = isset($_GET['date']) ? $_GET['date'] : null;

$response = [
    'status' => 'error',
    'message' => 'An error occurred',
    'data' => [],
    'timestamp' => time()
];

try {
    // Get current time or use provided time
    if ($inputTime && $inputDate) {
        $now = new DateTime("$inputDate $inputTime", new DateTimeZone('America/La_Paz'));
    } else {
        $now = new DateTime('now', new DateTimeZone('America/La_Paz'));
    }

    $currentDate = $now->format('Y-m-d');
    $currentTime = $now->format('H:i:s');
    $currentHour = (int)$now->format('H');
    $currentMinute = (int)$now->format('i');
    $currentSecond = (int)$now->format('s');

    // Calculate expected draw number
    // Draws occur every 3 minutes starting from midnight
    // Draw #1 at 00:00:00, Draw #2 at 00:03:00, Draw #3 at 00:06:00, etc.
    $totalMinutes = ($currentHour * 60) + $currentMinute;
    $completedIntervals = floor($totalMinutes / 3);
    $expectedDrawNumber = $completedIntervals + 1;

    // Calculate next draw time
    $nextDrawMinute = (floor($currentMinute / 3) + 1) * 3;
    $nextDrawHour = $currentHour;
    if ($nextDrawMinute >= 60) {
        $nextDrawHour = $currentHour + 1;
        $nextDrawMinute = $nextDrawMinute % 60;
    }
    if ($nextDrawHour >= 24) {
        $nextDrawHour = 0;
    }
    
    $nextDrawTime = sprintf('%02d:%02d:00', $nextDrawHour, $nextDrawMinute);
    
    // Calculate seconds until next draw
    $totalSecondsSinceMidnight = ($currentHour * 3600) + ($currentMinute * 60) + $currentSecond;
    $nextDrawSecondsSinceMidnight = ($nextDrawHour * 3600) + ($nextDrawMinute * 60);
    
    if ($nextDrawSecondsSinceMidnight <= $totalSecondsSinceMidnight) {
        // Next draw is tomorrow
        $nextDrawSecondsSinceMidnight += 86400; // Add 24 hours
    }
    
    $secondsUntilNextDraw = $nextDrawSecondsSinceMidnight - $totalSecondsSinceMidnight;

    // Get actual draw number from database
    $actualDrawNumber = 0;
    $lastResetDate = null;
    $needsReset = false;

    try {
        $stmt = $conn->prepare("SELECT current_draw_number, last_reset_date FROM roulette_analytics WHERE id = 1 LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $actualDrawNumber = (int)$row['current_draw_number'];
            $lastResetDate = $row['last_reset_date'];
            
            // Check if reset is needed
            $needsReset = (!$lastResetDate || $lastResetDate < $currentDate);
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error fetching actual draw number: " . $e->getMessage());
    }

    // Check if draw numbers match
    $drawNumberMatch = ($actualDrawNumber === $expectedDrawNumber);
    $warning = '';
    
    if (!$drawNumberMatch) {
        $warning = "Draw number mismatch! Expected: $expectedDrawNumber, Actual: $actualDrawNumber";
    }

    // Get count of today's draws from detailed_draw_results
    $todayDrawsCount = 0;
    try {
        // Check which timestamp column exists
        $checkColumn = $conn->query("SHOW COLUMNS FROM detailed_draw_results LIKE 'draw_time'");
        $timestampCol = ($checkColumn && $checkColumn->num_rows > 0) ? 'draw_time' : 'timestamp';
        
        $stmt = $conn->prepare("SELECT COUNT(*) as today_draws FROM detailed_draw_results WHERE DATE($timestampCol) = ?");
        $stmt->bind_param("s", $currentDate);
        $stmt->execute();
        $todayDrawsResult = $stmt->get_result()->fetch_assoc();
        $todayDrawsCount = (int)($todayDrawsResult['today_draws'] ?? 0);
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error counting today's draws: " . $e->getMessage());
    }

    $response = [
        'status' => 'success',
        'data' => [
            'current_time' => $currentTime,
            'current_date' => $currentDate,
            'timezone' => 'America/La_Paz (UTC-04:00)',
            'expected_draw_number' => $expectedDrawNumber,
            'actual_draw_number' => $actualDrawNumber,
            'draw_number_match' => $drawNumberMatch,
            'next_draw_time' => $nextDrawTime,
            'seconds_until_next_draw' => $secondsUntilNextDraw,
            'minutes_until_next_draw' => round($secondsUntilNextDraw / 60, 1),
            'last_reset_date' => $lastResetDate,
            'needs_reset' => $needsReset,
            'today_draws_count' => $todayDrawsCount,
            'calculation' => [
                'total_minutes_since_midnight' => $totalMinutes,
                'completed_3_minute_intervals' => $completedIntervals,
                'formula' => "floor($totalMinutes / 3) + 1 = $expectedDrawNumber"
            ]
        ],
        'timestamp' => time()
    ];

    if (!empty($warning)) {
        $response['warning'] = $warning;
    }

} catch (Exception $e) {
    error_log("Error calculating draw number by time: " . $e->getMessage());
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>


