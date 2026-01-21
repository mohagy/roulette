<?php
/**
 * Check and Fix Draw Numbers Script
 * 
 * This script checks the database for draw number mismatches and fixes them
 */

require_once 'php/db_connect.php';

header('Content-Type: text/plain');

echo "=== Draw Number Diagnostic and Fix Tool ===\n\n";

try {
    // Get current draw number based on server time
    date_default_timezone_set('America/Guyana');
    $now = new DateTime('now', new DateTimeZone('America/Guyana'));
    $currentHour = (int)$now->format('H');
    $currentMinute = (int)$now->format('i');
    $totalMinutesSinceMidnight = ($currentHour * 60) + $currentMinute;
    $drawIndex = floor($totalMinutesSinceMidnight / 3);
    $serverTimeBasedDrawNumber = $drawIndex + 1;
    
    echo "Server Time: " . $now->format('Y-m-d H:i:s') . " (Georgetown)\n";
    echo "Expected Current Draw: #{$serverTimeBasedDrawNumber}\n\n";
    
    // Get last 10 draws from detailed_draw_results
    echo "=== Last 10 Draws in Database ===\n";
    $stmt = $pdo->prepare("
        SELECT 
            draw_number,
            winning_number,
            timestamp
        FROM detailed_draw_results
        WHERE winning_number IS NOT NULL
        ORDER BY draw_number DESC
        LIMIT 10
    ");
    $stmt->execute();
    $draws = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($draws)) {
        echo "No draws found in database.\n";
    } else {
        foreach ($draws as $draw) {
            echo "Draw #{$draw['draw_number']} → Number: {$draw['winning_number']} ({$draw['timestamp']})\n";
        }
    }
    
    echo "\n=== Analytics Data ===\n";
    // Check roulette_analytics
    $stmt = $pdo->prepare("SELECT current_draw_number, all_spins FROM roulette_analytics WHERE id = 1 LIMIT 1");
    $stmt->execute();
    $analytics = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($analytics) {
        echo "Stored current_draw_number: #{$analytics['current_draw_number']}\n";
        $allSpins = json_decode($analytics['all_spins'] ?? '[]', true);
        if (is_array($allSpins) && !empty($allSpins)) {
            echo "Last 8 numbers in all_spins: " . implode(', ', array_slice($allSpins, 0, 8)) . "\n";
        } else {
            echo "all_spins is empty or invalid\n";
        }
    }
    
    // Calculate expected draw numbers for last 8 spins
    echo "\n=== Expected Draw Numbers for Last 8 Spins ===\n";
    if (!empty($draws)) {
        $expectedBaseDraw = $serverTimeBasedDrawNumber - 1; // Last completed draw
        for ($i = 0; $i < min(8, count($draws)); $i++) {
            $expectedDraw = $expectedBaseDraw - $i;
            $dbDraw = $draws[$i]['draw_number'];
            $match = ($expectedDraw == $dbDraw) ? "✓" : "✗ MISMATCH";
            echo "Position {$i}: Expected #{$expectedDraw}, Database #{$dbDraw} {$match}\n";
        }
    }
    
    echo "\n=== Checking next_draw_winning_number Table ===\n";
    // Check forced numbers for current and next draws
    for ($i = $serverTimeBasedDrawNumber; $i <= $serverTimeBasedDrawNumber + 2; $i++) {
        $stmt = $pdo->prepare("SELECT draw_number, winning_number FROM next_draw_winning_number WHERE draw_number = ? LIMIT 1");
        $stmt->execute([$i]);
        $forced = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($forced) {
            echo "Draw #{$i}: Forced number = {$forced['winning_number']}\n";
        } else {
            echo "Draw #{$i}: No forced number\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
