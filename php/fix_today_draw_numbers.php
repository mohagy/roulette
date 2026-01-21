<?php
/**
 * Fix Today's Draw Numbers
 * Recalculates draw numbers for today's draws based on their actual draw_time
 */

require_once 'db_connect.php';

date_default_timezone_set('America/Guyana');
$now = new DateTime('now', new DateTimeZone('America/Guyana'));
$today = $now->format('Y-m-d');

echo "=== Fixing Today's Draw Numbers ({$today}) ===\n\n";

try {
    // Get all draws from today
    $stmt = $pdo->prepare("
        SELECT id, draw_number, draw_time, winning_number, winning_color
        FROM analytics_history
        WHERE DATE(draw_time) = ?
        ORDER BY draw_time ASC
    ");
    $stmt->execute([$today]);
    $draws = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($draws) . " draws to fix\n\n";
    
    $pdo->beginTransaction();
    
    $updated = 0;
    $errors = 0;
    
    foreach ($draws as $draw) {
        try {
            $drawTime = $draw['draw_time'];
            $oldDrawNumber = $draw['draw_number'];
            
            // Parse the draw_time
            $dt = new DateTime($drawTime, new DateTimeZone('America/Guyana'));
            $hour = (int)$dt->format('H');
            $minute = (int)$dt->format('i');
            
            // Calculate correct server-time-based draw number
            $totalMinutesSinceMidnight = ($hour * 60) + $minute;
            $drawIndex = floor($totalMinutesSinceMidnight / 3);
            $newDrawNumber = $drawIndex + 1;
            
            // Cap at 480
            if ($newDrawNumber > 480) {
                $newDrawNumber = 480;
            }
            
            // Only update if different
            if ($oldDrawNumber != $newDrawNumber) {
                // Check if new draw number already exists for today
                $checkStmt = $pdo->prepare("
                    SELECT id, draw_time FROM analytics_history 
                    WHERE DATE(draw_time) = ? AND draw_number = ? AND id != ?
                    LIMIT 1
                ");
                $checkStmt->execute([$today, $newDrawNumber, $draw['id']]);
                $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing) {
                    // Keep the one with the most recent timestamp
                    if ($drawTime > $existing['draw_time']) {
                        // Current draw is newer, delete the old one
                        $deleteStmt = $pdo->prepare("DELETE FROM analytics_history WHERE id = ?");
                        $deleteStmt->execute([$existing['id']]);
                        echo "  Deleted conflicting draw #{$newDrawNumber} (older: {$existing['draw_time']})\n";
                    } else {
                        // Keep existing, delete current
                        $deleteStmt = $pdo->prepare("DELETE FROM analytics_history WHERE id = ?");
                        $deleteStmt->execute([$draw['id']]);
                        echo "  Deleted duplicate draw ID {$draw['id']} (newer exists: {$existing['draw_time']})\n";
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
                
                echo "  Updated: Draw #{$oldDrawNumber} → #{$newDrawNumber} at {$drawTime} ({$draw['winning_number']})\n";
                $updated++;
            }
        } catch (Exception $e) {
            echo "  Error processing draw ID {$draw['id']}: " . $e->getMessage() . "\n";
            $errors++;
        }
    }
    
    $pdo->commit();
    
    echo "\n=== Summary ===\n";
    echo "Updated: {$updated}\n";
    echo "Errors: {$errors}\n";
    
    // Verify
    echo "\n=== Verification ===\n";
    $verifyStmt = $pdo->prepare("
        SELECT draw_number, winning_number, draw_time
        FROM analytics_history
        WHERE DATE(draw_time) = ?
        ORDER BY draw_number DESC
        LIMIT 10
    ");
    $verifyStmt->execute([$today]);
    $verify = $verifyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Recent draws today:\n";
    foreach ($verify as $v) {
        $dt = new DateTime($v['draw_time'], new DateTimeZone('America/Guyana'));
        $h = (int)$dt->format('H');
        $m = (int)$dt->format('i');
        $calc = floor((($h * 60) + $m) / 3) + 1;
        $match = ($v['draw_number'] == $calc) ? '✓' : '✗';
        echo "  {$match} Draw #{$v['draw_number']} at {$v['draw_time']} (Calc: #{$calc}) - {$v['winning_number']}\n";
    }
    
} catch (PDOException $e) {
    $pdo->rollBack();
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

