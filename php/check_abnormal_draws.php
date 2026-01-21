<?php
/**
 * Check for abnormal draw numbers (> 480)
 */

require_once 'db_connect.php';

header('Content-Type: text/plain');

echo "=== Checking for Abnormal Draw Numbers (> 480) ===\n\n";

try {
    // Check analytics_history
    $stmt = $pdo->query("
        SELECT COUNT(*) as count, 
               MIN(draw_number) as min_draw, 
               MAX(draw_number) as max_draw
        FROM analytics_history
        WHERE draw_number > 480
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Analytics History:\n";
    echo "  Draws > 480: " . $result['count'] . "\n";
    echo "  Min draw: " . ($result['min_draw'] ?? 'N/A') . "\n";
    echo "  Max draw: " . ($result['max_draw'] ?? 'N/A') . "\n\n";
    
    if ($result['count'] > 0) {
        // Show sample of abnormal draws
        $stmt2 = $pdo->query("
            SELECT draw_number, winning_number, draw_time, source
            FROM analytics_history
            WHERE draw_number > 480
            ORDER BY draw_number DESC
            LIMIT 10
        ");
        echo "Sample abnormal draws:\n";
        while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
            echo "  Draw #{$row['draw_number']}: {$row['winning_number']} - {$row['draw_time']} - {$row['source']}\n";
        }
    }
    
    // Check detailed_draw_results
    echo "\n---\n\n";
    $stmt3 = $pdo->query("
        SELECT COUNT(*) as count, 
               MIN(draw_number) as min_draw, 
               MAX(draw_number) as max_draw
        FROM detailed_draw_results
        WHERE draw_number > 480
    ");
    $result3 = $stmt3->fetch(PDO::FETCH_ASSOC);
    
    echo "Detailed Draw Results:\n";
    echo "  Draws > 480: " . $result3['count'] . "\n";
    echo "  Min draw: " . ($result3['min_draw'] ?? 'N/A') . "\n";
    echo "  Max draw: " . ($result3['max_draw'] ?? 'N/A') . "\n\n";
    
    // Check next_draw_winning_number
    echo "\n---\n\n";
    $stmt4 = $pdo->query("
        SELECT COUNT(*) as count, 
               MIN(draw_number) as min_draw, 
               MAX(draw_number) as max_draw
        FROM next_draw_winning_number
        WHERE draw_number > 480
    ");
    $result4 = $stmt4->fetch(PDO::FETCH_ASSOC);
    
    echo "Next Draw Winning Number:\n";
    echo "  Draws > 480: " . $result4['count'] . "\n";
    echo "  Min draw: " . ($result4['min_draw'] ?? 'N/A') . "\n";
    echo "  Max draw: " . ($result4['max_draw'] ?? 'N/A') . "\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

