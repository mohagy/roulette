<?php
/**
 * Check Latest Draw
 */

require_once 'php/db_connect.php';

header('Content-Type: text/plain');

echo "=== Latest Draw Check ===\n\n";

try {
    // Check what the most recent draw is in database
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
    
    echo "=== Last 10 Draws by Draw Number (DESC) ===\n";
    foreach ($draws as $draw) {
        echo "Draw #{$draw['draw_number']} → Number: {$draw['winning_number']} ({$draw['timestamp']})\n";
    }
    
    // Also check by timestamp
    echo "\n=== Last 10 Draws by Timestamp (DESC) ===\n";
    $stmt = $pdo->prepare("
        SELECT 
            draw_number,
            winning_number,
            timestamp
        FROM detailed_draw_results
        WHERE winning_number IS NOT NULL
        ORDER BY timestamp DESC
        LIMIT 10
    ");
    $stmt->execute();
    $drawsByTime = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($drawsByTime as $draw) {
        echo "Draw #{$draw['draw_number']} → Number: {$draw['winning_number']} ({$draw['timestamp']})\n";
    }
    
    // Check what get_recent_draws.php would return
    echo "\n=== Simulating get_recent_draws.php Response ===\n";
    $stmt = $pdo->prepare("
        SELECT 
            draw_number,
            winning_number,
            winning_color,
            timestamp
        FROM detailed_draw_results
        WHERE winning_number IS NOT NULL
        ORDER BY draw_number DESC
        LIMIT 8
    ");
    $stmt->execute();
    $apiDraws = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($apiDraws as $draw) {
        echo "Draw #{$draw['draw_number']} → Number: {$draw['winning_number']} ({$draw['timestamp']})\n";
    }
    
    // Check current server time draw
    date_default_timezone_set('America/Guyana');
    $now = new DateTime('now', new DateTimeZone('America/Guyana'));
    $currentHour = (int)$now->format('H');
    $currentMinute = (int)$now->format('i');
    $totalMinutesSinceMidnight = ($currentHour * 60) + $currentMinute;
    $drawIndex = floor($totalMinutesSinceMidnight / 3);
    $serverTimeBasedDrawNumber = $drawIndex + 1;
    
    echo "\nServer Time: " . $now->format('Y-m-d H:i:s') . "\n";
    echo "Expected Current Draw: #{$serverTimeBasedDrawNumber}\n";
    echo "Previous Draw (just played): #" . ($serverTimeBasedDrawNumber - 1) . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>




