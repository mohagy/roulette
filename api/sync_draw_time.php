<?php
require_once '../includes/db_connection.php';

// Set timezone
date_default_timezone_set('America/La_Paz');
$now = new DateTime();
$currentHour = (int)$now->format('H');
$currentMinute = (int)$now->format('i');
$currentSecond = (int)$now->format('s');

// Calculate draw number (3 minute intervals)
$totalMinutes = ($currentHour * 60) + $currentMinute;
$completedIntervals = floor($totalMinutes / 3);
$currentDrawNumber = $completedIntervals + 1;

// Calculate remaining seconds in current interval
$minutesInInterval = $totalMinutes % 3;
$secondsInInterval = ($minutesInInterval * 60) + $currentSecond;
$totalSecondsInInterval = 3 * 60;
$remainingSeconds = $totalSecondsInInterval - $secondsInInterval;

echo "Time: " . $now->format('H:i:s') . "\n";
echo "Calculated Draw: $currentDrawNumber\n";
echo "Countdown: $remainingSeconds seconds\n";

// Update roulette_analytics
$sql1 = "UPDATE roulette_analytics SET current_draw_number = $currentDrawNumber WHERE id = 1";
if ($conn->query($sql1) === TRUE) {
    echo "Updated roulette_analytics.current_draw_number\n";
} else {
    echo "Error updating roulette_analytics: " . $conn->error . "\n";
}

// Update roulette_state
// We need to update draw_number, next_draw_number, and countdown_time
$nextDrawNumber = $currentDrawNumber + 1;
$sql2 = "UPDATE roulette_state SET 
    draw_number = $currentDrawNumber, 
    next_draw_number = $nextDrawNumber,
    countdown_time = $remainingSeconds,
    updated_at = NOW()
    WHERE id = (SELECT id FROM (SELECT id FROM roulette_state ORDER BY created_at DESC LIMIT 1) AS t)";

// Note: The nested subquery is needed for MySQL UPDATE with LIMIT/ORDER BY in some versions, 
// or just update the specific row if we knew the ID. 
// Let's assume there's only one relevant row or we want to update the latest.
// Actually, draw_info.php uses:
// "SELECT ... FROM roulette_state ORDER BY created_at DESC LIMIT 1"
// So we should update that one.

// Let's find the ID first to be safe
$res = $conn->query("SELECT id FROM roulette_state ORDER BY created_at DESC LIMIT 1");
if ($res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $stateId = $row['id'];
    
    $sql2 = "UPDATE roulette_state SET 
        draw_number = $currentDrawNumber, 
        next_draw_number = $nextDrawNumber,
        countdown_time = $remainingSeconds,
        updated_at = NOW()
        WHERE id = $stateId";
        
    if ($conn->query($sql2) === TRUE) {
        echo "Updated roulette_state (id=$stateId)\n";
    } else {
        echo "Error updating roulette_state: " . $conn->error . "\n";
    }
} else {
    echo "No roulette_state row found to update.\n";
    // Optional: Insert one if missing?
}

$conn->close();
?>
