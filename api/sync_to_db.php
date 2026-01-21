<?php
require_once '../includes/db_connection.php';

// Get the last draw from detailed_draw_results
$result = $conn->query("SELECT MAX(draw_number) as max_draw FROM detailed_draw_results");
$row = $result->fetch_assoc();
$currentDraw = (int)$row['max_draw'];

echo "Current draw from detailed_draw_results: $currentDraw\n";

// Update roulette_analytics
$sql = "UPDATE roulette_analytics SET current_draw_number = ? WHERE id = 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $currentDraw);
if ($stmt->execute()) {
    echo "✅ Updated roulette_analytics.current_draw_number to $currentDraw\n";
} else {
    echo "❌ Error updating roulette_analytics: " . $conn->error . "\n";
}
$stmt->close();

// Calculate countdown (current time in 3-min interval)
date_default_timezone_set('America/La_Paz');
$now = new DateTime();
$currentHour = (int)$now->format('H');
$currentMinute = (int)$now->format('i');
$currentSecond = (int)$now->format('s');
$totalMinutes = ($currentHour * 60) + $currentMinute;
$minutesInInterval = $totalMinutes % 3;
$secondsInInterval = ($minutesInInterval * 60) + $currentSecond;
$countdown = (3 * 60) - $secondsInInterval;

echo "Countdown: $countdown seconds\n";

// Update roulette_state with latest row
$nextDraw = $currentDraw + 1;
$res = $conn->query("SELECT id FROM roulette_state ORDER BY created_at DESC LIMIT 1");
if ($res->num_rows > 0) {
    $stateRow = $res->fetch_assoc();
    $stateId = $stateRow['id'];
    
    $sql = "UPDATE roulette_state SET 
        draw_number = ?,
        next_draw_number = ?,
        countdown_time = ?,
        updated_at = NOW()
        WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiii", $currentDraw, $nextDraw, $countdown, $stateId);
    
    if ($stmt->execute()) {
        echo "✅ Updated roulette_state (id=$stateId)\n";
    } else {
        echo "❌ Error updating roulette_state: " . $conn->error . "\n";
    }
    $stmt->close();
}

echo "\n✅ Sync complete!\n";
$conn->close();
?>
