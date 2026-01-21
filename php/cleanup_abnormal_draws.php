<?php
/**
 * Cleanup Abnormal Draw Numbers (> 480)
 * Removes all draws with draw_number > 480 from relevant tables
 */

require_once 'db_connect.php';

header('Content-Type: text/plain');

echo "=== Cleaning Up Abnormal Draw Numbers (> 480) ===\n\n";

try {
    $pdo->beginTransaction();
    
    // 1. Delete from analytics_history
    echo "1. Cleaning analytics_history...\n";
    $stmt = $pdo->prepare("DELETE FROM analytics_history WHERE draw_number > 480");
    $stmt->execute();
    $deleted1 = $stmt->rowCount();
    echo "   ✓ Deleted {$deleted1} records from analytics_history\n\n";
    
    // 2. Delete from detailed_draw_results
    echo "2. Cleaning detailed_draw_results...\n";
    $stmt = $pdo->prepare("DELETE FROM detailed_draw_results WHERE draw_number > 480");
    $stmt->execute();
    $deleted2 = $stmt->rowCount();
    echo "   ✓ Deleted {$deleted2} records from detailed_draw_results\n\n";
    
    // 3. Delete from next_draw_winning_number
    echo "3. Cleaning next_draw_winning_number...\n";
    $stmt = $pdo->prepare("DELETE FROM next_draw_winning_number WHERE draw_number > 480");
    $stmt->execute();
    $deleted3 = $stmt->rowCount();
    echo "   ✓ Deleted {$deleted3} records from next_draw_winning_number\n\n";
    
    // 4. Check if there are any other tables with draw_number > 480
    echo "4. Checking other tables...\n";
    
    // Check roulette_state
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM roulette_state LIKE 'draw_number'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM roulette_state WHERE draw_number > 480");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result['count'] > 0) {
                $stmt = $pdo->prepare("UPDATE roulette_state SET draw_number = 480 WHERE draw_number > 480");
                $stmt->execute();
                echo "   ✓ Updated roulette_state (capped at 480)\n";
            }
        }
    } catch (PDOException $e) {
        // Table might not have draw_number column, skip
    }
    
    // Check roulette_analytics
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM roulette_analytics LIKE 'current_draw_number'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM roulette_analytics WHERE current_draw_number > 480");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result['count'] > 0) {
                // Calculate current draw based on server time
                date_default_timezone_set('America/Guyana');
                $now = new DateTime('now', new DateTimeZone('America/Guyana'));
                $currentHour = (int)$now->format('H');
                $currentMinute = (int)$now->format('i');
                $totalMinutesSinceMidnight = ($currentHour * 60) + $currentMinute;
                $currentDrawNumber = floor($totalMinutesSinceMidnight / 3) + 1;
                
                // Cap at 480
                if ($currentDrawNumber > 480) {
                    $currentDrawNumber = 480;
                }
                
                $stmt = $pdo->prepare("UPDATE roulette_analytics SET current_draw_number = ? WHERE current_draw_number > 480");
                $stmt->execute([$currentDrawNumber]);
                echo "   ✓ Updated roulette_analytics (set to current server-time draw: {$currentDrawNumber})\n";
            }
        }
    } catch (PDOException $e) {
        // Table might not exist or have different structure, skip
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo "\n=== Cleanup Complete ===\n";
    echo "Total deleted:\n";
    echo "  - analytics_history: {$deleted1} records\n";
    echo "  - detailed_draw_results: {$deleted2} records\n";
    echo "  - next_draw_winning_number: {$deleted3} records\n";
    echo "\n✓ All abnormal draws (draw_number > 480) have been removed.\n";
    
    // Verify cleanup
    echo "\n=== Verification ===\n";
    $stmt = $pdo->query("SELECT MAX(draw_number) as max_draw FROM analytics_history");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Max draw number in analytics_history: " . ($result['max_draw'] ?? 'N/A') . "\n";
    
    $stmt = $pdo->query("SELECT MAX(draw_number) as max_draw FROM detailed_draw_results");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Max draw number in detailed_draw_results: " . ($result['max_draw'] ?? 'N/A') . "\n";
    
} catch (PDOException $e) {
    $pdo->rollBack();
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Transaction rolled back.\n";
    exit(1);
}

