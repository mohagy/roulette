<?php
/**
 * Check for automatic forced numbers that should be cleaned up
 */

require_once 'db_connect.php';

date_default_timezone_set('America/Guyana');
$now = new DateTime('now', new DateTimeZone('America/Guyana'));
$h = (int)$now->format('H');
$m = (int)$now->format('i');
$total = ($h * 60) + $m;
$currentDraw = floor($total / 3) + 1;

echo "=== Checking Automatic Forced Numbers ===\n\n";
echo "Current draw: #{$currentDraw}\n\n";

// Check for automatic forced numbers
$stmt = $pdo->prepare("
    SELECT draw_number, winning_number, source, reason, created_at
    FROM next_draw_winning_number
    WHERE source = 'automatic'
    ORDER BY draw_number ASC
");
$stmt->execute();
$automatic = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Automatic forced numbers found: " . count($automatic) . "\n\n";

if (count($automatic) > 0) {
    echo "Automatic forced numbers:\n";
    foreach ($automatic as $auto) {
        $status = $auto['draw_number'] < $currentDraw ? 'PAST' : ($auto['draw_number'] == $currentDraw ? 'CURRENT' : 'FUTURE');
        echo "  Draw #{$auto['draw_number']}: {$auto['winning_number']} - {$auto['reason']} - {$status}\n";
    }
    
    echo "\n⚠️ These automatic forced numbers are overriding preset schedule.\n";
    echo "They should be deleted so preset schedule numbers are used instead.\n";
} else {
    echo "✓ No automatic forced numbers found.\n";
}

// Check for manual forced numbers
$stmt2 = $pdo->prepare("
    SELECT draw_number, winning_number, source, reason
    FROM next_draw_winning_number
    WHERE source = 'manual'
    ORDER BY draw_number ASC
");
$stmt2->execute();
$manual = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo "\nManual forced numbers found: " . count($manual) . "\n";
if (count($manual) > 0) {
    foreach ($manual as $man) {
        echo "  Draw #{$man['draw_number']}: {$man['winning_number']} - {$man['reason']}\n";
    }
}

