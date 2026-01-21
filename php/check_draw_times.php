<?php
/**
 * Check Draw Times Around 12:43 PM
 */

require_once 'db_connect.php';

date_default_timezone_set('America/Guyana');

echo "=== Checking Draw Times Around 12:43 PM ===\n\n";

// Calculate what draw number should be at 12:43 PM
$targetHour = 12;
$targetMinute = 43;
$totalMinutes = ($targetHour * 60) + $targetMinute;
$expectedDraw = floor($totalMinutes / 3) + 1;

echo "Expected draw number at 12:43 PM: #{$expectedDraw}\n";
echo "Draw time range: 12:42 - 12:45\n\n";

// Check draws around this time
$stmt = $pdo->prepare("
    SELECT draw_number, winning_number, winning_color, draw_time, source
    FROM analytics_history
    WHERE draw_time >= '2026-01-17 12:30:00' AND draw_time <= '2026-01-17 13:00:00'
    ORDER BY draw_time ASC
");
$stmt->execute();
$draws = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Draws in analytics_history around 12:43 PM:\n";
foreach ($draws as $draw) {
    $dt = new DateTime($draw['draw_time'], new DateTimeZone('America/Guyana'));
    $hour = (int)$dt->format('H');
    $minute = (int)$dt->format('i');
    $totalMins = ($hour * 60) + $minute;
    $calcDraw = floor($totalMins / 3) + 1;
    
    $match = ($draw['draw_number'] == $calcDraw) ? '✓' : '✗';
    echo "  {$match} Draw #{$draw['draw_number']} at {$draw['draw_time']} (Calc: #{$calcDraw}) - {$draw['winning_number']} ({$draw['winning_color']})\n";
}

echo "\n=== Checking for Draw #{$expectedDraw} ===\n";
$stmt2 = $pdo->prepare("
    SELECT draw_number, winning_number, winning_color, draw_time, source
    FROM analytics_history
    WHERE draw_number = ?
    ORDER BY draw_time DESC
    LIMIT 5
");
$stmt2->execute([$expectedDraw]);
$draws2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if (count($draws2) > 0) {
    echo "Found " . count($draws2) . " draw(s) with number #{$expectedDraw}:\n";
    foreach ($draws2 as $draw) {
        echo "  Draw #{$draw['draw_number']} at {$draw['draw_time']} - {$draw['winning_number']} ({$draw['winning_color']}) - Source: {$draw['source']}\n";
    }
} else {
    echo "No draw found with number #{$expectedDraw}\n";
}

// Check what the current server time draw should be
$now = new DateTime('now', new DateTimeZone('America/Guyana'));
$currentHour = (int)$now->format('H');
$currentMinute = (int)$now->format('i');
$currentTotal = ($currentHour * 60) + $currentMinute;
$currentDraw = floor($currentTotal / 3) + 1;

echo "\n=== Current Server Time ===\n";
echo "Server time: " . $now->format('Y-m-d H:i:s') . "\n";
echo "Current draw number: #{$currentDraw}\n";

