<?php
/**
 * Cleanup Automatic Forced Numbers
 * Removes automatic forced numbers so preset schedule is used by default
 * Keeps manual forced numbers (those should override preset)
 */

require_once 'db_connect.php';

date_default_timezone_set('America/Guyana');
$now = new DateTime('now', new DateTimeZone('America/Guyana'));
$h = (int)$now->format('H');
$m = (int)$now->format('i');
$total = ($h * 60) + $m;
$currentDraw = floor($total / 3) + 1;

echo "=== Cleaning Up Automatic Forced Numbers ===\n\n";
echo "Current draw: #{$currentDraw}\n\n";

try {
    $pdo->beginTransaction();
    
    // Count automatic forced numbers
    $countStmt = $pdo->query("SELECT COUNT(*) as count FROM next_draw_winning_number WHERE source = 'automatic'");
    $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "Found {$count} automatic forced numbers\n\n";
    
    // Delete all automatic forced numbers
    // Keep manual forced numbers (source='manual') as those are intentional overrides
    $deleteStmt = $pdo->prepare("DELETE FROM next_draw_winning_number WHERE source = 'automatic'");
    $deleteStmt->execute();
    $deleted = $deleteStmt->rowCount();
    
    // Also clean up past forced numbers (both automatic and manual for past draws)
    $cleanupStmt = $pdo->prepare("DELETE FROM next_draw_winning_number WHERE draw_number < ?");
    $cleanupStmt->execute([$currentDraw]);
    $cleanedPast = $cleanupStmt->rowCount();
    
    $pdo->commit();
    
    echo "✓ Deleted {$deleted} automatic forced numbers\n";
    echo "✓ Cleaned up {$cleanedPast} past forced numbers (draws < #{$currentDraw})\n\n";
    
    echo "=== Result ===\n";
    echo "The system will now use preset schedule numbers by default.\n";
    echo "Only manually set forced numbers will override preset schedule.\n";
    
    // Verify
    $verifyStmt = $pdo->query("SELECT COUNT(*) as count FROM next_draw_winning_number WHERE source = 'automatic'");
    $remaining = $verifyStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($remaining == 0) {
        echo "\n✓ Verification: No automatic forced numbers remaining\n";
    } else {
        echo "\n⚠ Warning: {$remaining} automatic forced numbers still remain\n";
    }
    
    // Show remaining manual forced numbers
    $manualStmt = $pdo->query("SELECT COUNT(*) as count FROM next_draw_winning_number WHERE source = 'manual'");
    $manualCount = $manualStmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Manual forced numbers remaining: {$manualCount}\n";
    
} catch (PDOException $e) {
    $pdo->rollBack();
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

