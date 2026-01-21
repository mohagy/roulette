<?php
/**
 * Fix Analytics Draw Numbers
 * Recalculates draw numbers in analytics_history based on draw_time
 * to match server-time-based calculation (1-480 per day)
 */

require_once 'db_connect.php';

header('Content-Type: text/plain');

echo "=== Fixing Analytics Draw Numbers ===\n\n";

try {
    date_default_timezone_set('America/Guyana');
    
    // Get all draws from analytics_history
    $stmt = $pdo->query("
        SELECT id, draw_number, draw_time, winning_number
        FROM analytics_history
        ORDER BY draw_time ASC
    ");
    
    $draws = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = count($draws);
    
    echo "Found {$total} draws to process\n\n";
    
    $pdo->beginTransaction();
    
    $updated = 0;
    $skipped = 0;
    $errors = 0;
    
    foreach ($draws as $draw) {
        try {
            $drawTime = $draw['draw_time'];
            $oldDrawNumber = $draw['draw_number'];
            
            // Parse the draw_time
            $dt = new DateTime($drawTime, new DateTimeZone('America/Guyana'));
            $hour = (int)$dt->format('H');
            $minute = (int)$dt->format('i');
            
            // Calculate server-time-based draw number
            $totalMinutesSinceMidnight = ($hour * 60) + $minute;
            $drawIndex = floor($totalMinutesSinceMidnight / 3);
            $newDrawNumber = $drawIndex + 1;
            
            // Cap at 480
            if ($newDrawNumber > 480) {
                $newDrawNumber = 480;
            }
            
            // Only update if different
            if ($oldDrawNumber != $newDrawNumber) {
                // Check if new draw number already exists
                $checkStmt = $pdo->prepare("
                    SELECT id FROM analytics_history 
                    WHERE draw_number = ? AND id != ?
                    LIMIT 1
                ");
                $checkStmt->execute([$newDrawNumber, $draw['id']]);
                $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing) {
                    // Draw number conflict - keep the one with the most recent timestamp
                    $conflictStmt = $pdo->prepare("
                        SELECT id, draw_time FROM analytics_history 
                        WHERE id = ?
                    ");
                    $conflictStmt->execute([$existing['id']]);
                    $conflictDraw = $conflictStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($conflictDraw && $drawTime > $conflictDraw['draw_time']) {
                        // Current draw is newer, delete the old one
                        $deleteStmt = $pdo->prepare("DELETE FROM analytics_history WHERE id = ?");
                        $deleteStmt->execute([$existing['id']]);
                        echo "  Deleted conflicting draw #{$newDrawNumber} (older: {$conflictDraw['draw_time']})\n";
                    } else {
                        // Keep the existing one, skip this update
                        echo "  Skipped draw ID {$draw['id']}: Draw #{$newDrawNumber} already exists (conflict)\n";
                        $skipped++;
                        continue;
                    }
                }
                
                // Update the draw number
                $updateStmt = $pdo->prepare("
                    UPDATE analytics_history 
                    SET draw_number = ? 
                    WHERE id = ?
                ");
                $updateStmt->execute([$newDrawNumber, $draw['id']]);
                
                echo "  Updated draw ID {$draw['id']}: #{$oldDrawNumber} → #{$newDrawNumber} (Time: {$drawTime})\n";
                $updated++;
            } else {
                $skipped++;
            }
        } catch (Exception $e) {
            echo "  Error processing draw ID {$draw['id']}: " . $e->getMessage() . "\n";
            $errors++;
        }
    }
    
    $pdo->commit();
    
    echo "\n=== Summary ===\n";
    echo "Total draws processed: {$total}\n";
    echo "Updated: {$updated}\n";
    echo "Skipped (already correct): {$skipped}\n";
    echo "Errors: {$errors}\n";
    
    // Verify final state
    echo "\n=== Verification ===\n";
    $verifyStmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            MIN(draw_number) as min_draw,
            MAX(draw_number) as max_draw,
            COUNT(DISTINCT draw_number) as unique_draws
        FROM analytics_history
    ");
    $verify = $verifyStmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Total records: {$verify['total']}\n";
    echo "Draw number range: {$verify['min_draw']} - {$verify['max_draw']}\n";
    echo "Unique draw numbers: {$verify['unique_draws']}\n";
    
    if ($verify['max_draw'] <= 480) {
        echo "✓ All draw numbers are within valid range (1-480)\n";
    } else {
        echo "⚠ Warning: Some draw numbers exceed 480\n";
    }
    
} catch (PDOException $e) {
    $pdo->rollBack();
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

