<?php
require_once '../php/db_connect.php';

echo "<h1>Fix Draw Synchronization</h1>";

// 1. Get max draw from detailed_draw_results
$query = "SELECT MAX(draw_number) as max_draw FROM detailed_draw_results";
$result = $conn->query($query);
$row = $result->fetch_assoc();
$maxCompletedDraw = (int)$row['max_draw'];
echo "<p>Max completed draw: $maxCompletedDraw</p>";

$nextDraw = $maxCompletedDraw + 1;
echo "<p>Next draw should be: $nextDraw</p>";

// 2. Update roulette_analytics
$query = "UPDATE roulette_analytics SET current_draw_number = ? WHERE id = 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $nextDraw);
if ($stmt->execute()) {
    echo "<p style='color:green'>Updated roulette_analytics.current_draw_number to $nextDraw</p>";
} else {
    echo "<p style='color:red'>Failed to update roulette_analytics: " . $conn->error . "</p>";
}

// 3. Verify
$query = "SELECT current_draw_number FROM roulette_analytics WHERE id = 1";
$result = $conn->query($query);
$row = $result->fetch_assoc();
echo "<p>New roulette_analytics.current_draw_number: " . $row['current_draw_number'] . "</p>";

?>
