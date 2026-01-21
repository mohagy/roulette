<?php
require_once 'db_connect.php';

echo "=== Checking Draw #291 ===\n\n";

$stmt = $pdo->prepare("
    SELECT draw_number, winning_number, source, reason, created_at, updated_at
    FROM next_draw_winning_number
    WHERE draw_number = 291
    ORDER BY updated_at DESC
    LIMIT 1
");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result) {
    echo "Current record:\n";
    echo "  Draw #{$result['draw_number']}: Number {$result['winning_number']}\n";
    echo "  Source: {$result['source']}\n";
    echo "  Reason: {$result['reason']}\n";
    echo "  Updated: {$result['updated_at']}\n\n";
    
    if ($result['source'] !== 'manual') {
        echo "❌ PROBLEM: Source is '{$result['source']}' instead of 'manual'\n";
    }
} else {
    echo "No record found for draw #291\n";
}

