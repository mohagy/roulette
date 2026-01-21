<?php
/**
 * Sync Draw Number to Time
 * Forces the draw number to match the expected draw number based on current time
 */

header('Content-Type: application/json');
require_once '../includes/db_connection.php';
require_once '../includes/helper_functions.php';

date_default_timezone_set('America/La_Paz');

$response = [
    'status' => 'error',
    'message' => 'An error occurred',
    'data' => [],
    'timestamp' => time()
];

try {
    $now = new DateTime('now', new DateTimeZone('America/La_Paz'));
    $currentDate = $now->format('Y-m-d');
    $currentTime = $now->format('H:i:s');
    $currentHour = (int)$now->format('H');
    $currentMinute = (int)$now->format('i');

    // Calculate expected draw number
    $totalMinutes = ($currentHour * 60) + $currentMinute;
    $completedIntervals = floor($totalMinutes / 3);
    $expectedDrawNumber = $completedIntervals + 1;

    // Get current draw number from database
    $stmt = $conn->prepare("SELECT current_draw_number, last_reset_date FROM roulette_analytics WHERE id = 1 LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $oldDrawNumber = 0;
    $lastResetDate = null;
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $oldDrawNumber = (int)$row['current_draw_number'];
        $lastResetDate = $row['last_reset_date'];
    }
    $stmt->close();
    
    // Check if reset is needed (new day)
    $needsReset = (!$lastResetDate || $lastResetDate < $currentDate);
    
    if ($needsReset) {
        // Reset to 1 for new day
        $expectedDrawNumber = 1;
    }
    
    // Update draw number
    $stmt = $conn->prepare("
        UPDATE roulette_analytics
        SET current_draw_number = ?,
            last_reset_date = ?,
            last_updated = NOW()
        WHERE id = 1
    ");
    $stmt->bind_param("is", $expectedDrawNumber, $currentDate);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update draw number: " . $stmt->error);
    }
    $stmt->close();
    
    // Calculate next draw time
    $nextDrawMinute = (floor($currentMinute / 3) + 1) * 3;
    $nextDrawHour = $currentHour;
    if ($nextDrawMinute >= 60) {
        $nextDrawHour = $currentHour + 1;
        $nextDrawMinute = $nextDrawMinute % 60;
    }
    $nextDrawTime = sprintf('%02d:%02d:00', $nextDrawHour, $nextDrawMinute);

    $response = [
        'status' => 'success',
        'message' => 'Draw number synced to current time',
        'data' => [
            'current_time' => $currentTime,
            'current_date' => $currentDate,
            'timezone' => 'America/La_Paz (UTC-04:00)',
            'old_draw_number' => $oldDrawNumber,
            'new_draw_number' => $expectedDrawNumber,
            'was_reset' => $needsReset,
            'calculation' => [
                'total_minutes_since_midnight' => $totalMinutes,
                'completed_intervals' => $completedIntervals,
                'formula' => "floor($totalMinutes / 3) + 1 = $expectedDrawNumber"
            ],
            'next_draw_time' => $nextDrawTime
        ],
        'timestamp' => time()
    ];
    
    error_log("✅ Draw number synced: $oldDrawNumber → $expectedDrawNumber (reset: " . ($needsReset ? 'yes' : 'no') . ")");

} catch (Exception $e) {
    error_log("❌ Error syncing draw number to time: " . $e->getMessage());
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>


