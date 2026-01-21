<?php
/**
 * Fix Draw Numbers Script
 * 
 * This script fixes incorrect draw numbers in the database by recalculating them
 * based on the actual timestamps and server time
 */

require_once 'php/db_connect.php';

header('Content-Type: text/plain');

echo "=== Draw Number Fix Tool ===\n\n";

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
    
    // Get last 50 draws from detailed_draw_results ordered by timestamp DESC (most recent first)
    echo "=== Checking Recent Draws by Timestamp ===\n";
    $stmt = $pdo->prepare("
        SELECT 
            id,
            draw_number,
            winning_number,
            timestamp
        FROM detailed_draw_results
        WHERE winning_number IS NOT NULL
        AND timestamp >= DATE_SUB(NOW(), INTERVAL 2 DAY)
        ORDER BY timestamp DESC
        LIMIT 50
    ");
    $stmt->execute();
    $draws = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($draws)) {
        echo "No draws found in database.\n";
        exit;
    }
    
    echo "Found " . count($draws) . " recent draws\n\n";
    
    // Calculate correct draw numbers based on timestamps
    $updates = [];
    
    foreach ($draws as $index => $draw) {
        // Parse timestamp
        $drawTime = new DateTime($draw['timestamp'], new DateTimeZone('America/Guyana'));
        $drawHour = (int)$drawTime->format('H');
        $drawMinute = (int)$drawTime->format('i');
        $drawTotalMinutes = ($drawHour * 60) + $drawMinute;
        $drawDrawIndex = floor($drawTotalMinutes / 3);
        $correctDrawNumber = $drawDrawIndex + 1;
        
        $currentDrawNumber = intval($draw['draw_number']);
        
        if ($currentDrawNumber != $correctDrawNumber) {
            $updates[] = [
                'id' => $draw['id'],
                'old_draw_number' => $currentDrawNumber,
                'new_draw_number' => $correctDrawNumber,
                'winning_number' => $draw['winning_number'],
                'timestamp' => $draw['timestamp']
            ];
            
            echo "✗ Draw ID {$draw['id']}: Draw #{$currentDrawNumber} → Should be #{$correctDrawNumber} (Time: {$draw['timestamp']})\n";
        } else {
            echo "✓ Draw ID {$draw['id']}: Draw #{$currentDrawNumber} is correct (Time: {$draw['timestamp']})\n";
        }
    }
    
    if (empty($updates)) {
        echo "\n✅ All draw numbers are correct!\n";
        exit;
    }
    
    echo "\n=== Found " . count($updates) . " draws with incorrect draw numbers ===\n";
    echo "Do you want to fix them? (This will update the database)\n";
    echo "Run with --fix flag to automatically fix: php fix_draw_numbers.php --fix\n\n";
    
    // If --fix flag is provided, fix the draws
    if (isset($argv[1]) && $argv[1] === '--fix') {
        echo "🔧 Fixing draw numbers...\n\n";
        
        $pdo->beginTransaction();
        
        try {
            $updateStmt = $pdo->prepare("
                UPDATE detailed_draw_results 
                SET draw_number = ? 
                WHERE id = ?
            ");
            
            foreach ($updates as $update) {
                $updateStmt->execute([$update['new_draw_number'], $update['id']]);
                echo "✓ Fixed Draw ID {$update['id']}: #{$update['old_draw_number']} → #{$update['new_draw_number']}\n";
            }
            
            $pdo->commit();
            echo "\n✅ Successfully fixed " . count($updates) . " draw numbers!\n";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "\n❌ Error fixing draw numbers: " . $e->getMessage() . "\n";
        }
    } else {
        echo "⚠️  Run with --fix flag to apply fixes: php fix_draw_numbers.php --fix\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>