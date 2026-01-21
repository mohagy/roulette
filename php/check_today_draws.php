<?php
require_once 'db_connect.php';

date_default_timezone_set('America/Guyana');
$now = new DateTime('now', new DateTimeZone('America/Guyana'));
$today = $now->format('Y-m-d');

echo "=== Today's Draws ({$today}) ===\n\n";

$stmt = $pdo->prepare("
    SELECT COUNT(*) as count, 
           MIN(draw_number) as min_draw, 
           MAX(draw_number) as max_draw
    FROM analytics_history
    WHERE DATE(draw_time) = ?
");
$stmt->execute([$today]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Total draws today: {$result['count']}\n";
echo "Draw range: {$result['min_draw']} - {$result['max_draw']}\n\n";

// Get current draw number
$currentHour = (int)$now->format('H');
$currentMinute = (int)$now->format('i');
$totalMinutes = ($currentHour * 60) + $currentMinute;
$currentDraw = floor($totalMinutes / 3) + 1;

echo "Current server time: " . $now->format('H:i:s') . "\n";
echo "Current draw number: #{$currentDraw}\n\n";

// Show recent draws
$stmt2 = $pdo->prepare("
    SELECT draw_number, winning_number, winning_color, draw_time
    FROM analytics_history
    WHERE DATE(draw_time) = ?
    ORDER BY draw_number DESC
    LIMIT 10
");
$stmt2->execute([$today]);
$draws = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo "Recent draws today:\n";
foreach ($draws as $draw) {
    echo "  Draw #{$draw['draw_number']} at {$draw['draw_time']} - {$draw['winning_number']} ({$draw['winning_color']})\n";
}

