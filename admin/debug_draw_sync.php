<?php
require_once '../php/db_connect.php';

echo "<h1>Draw Synchronization Debug</h1>";

// Check roulette_analytics
$query = "SELECT current_draw_number FROM roulette_analytics WHERE id = 1";
$result = $conn->query($query);
$row = $result->fetch_assoc();
$analyticsDraw = $row['current_draw_number'];
echo "<p><strong>roulette_analytics.current_draw_number:</strong> $analyticsDraw</p>";

// Check detailed_draw_results
$query = "SELECT MAX(draw_number) as max_draw FROM detailed_draw_results";
$result = $conn->query($query);
$row = $result->fetch_assoc();
$maxCompletedDraw = $row['max_draw'];
echo "<p><strong>detailed_draw_results.MAX(draw_number):</strong> $maxCompletedDraw</p>";

// Count total bets
$query = "SELECT COUNT(*) as count FROM betting_slips";
$result = $conn->query($query);
$row = $result->fetch_assoc();
echo "Total bets in database: " . $row['count'] . "\n";

if ($row['count'] > 0) {
    $query = "SELECT slip_id, draw_number, total_stake, timestamp FROM betting_slips ORDER BY slip_id DESC LIMIT 10";
    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()) {
        echo "Slip ID: " . $row['slip_id'] . " | Draw: " . $row['draw_number'] . " | Stake: " . $row['total_stake'] . " | Time: " . $row['timestamp'] . "\n";
    }
}
?>
