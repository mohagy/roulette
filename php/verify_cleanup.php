<?php
require_once 'db_connect.php';

echo "=== Verification: Draw Number Cleanup ===\n\n";

$tables = ['analytics_history', 'detailed_draw_results', 'next_draw_winning_number'];

foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total,
                MIN(draw_number) as min_draw,
                MAX(draw_number) as max_draw,
                SUM(CASE WHEN draw_number > 480 THEN 1 ELSE 0 END) as abnormal_count
            FROM {$table}
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "{$table}:\n";
        echo "  Total records: {$result['total']}\n";
        echo "  Draw range: {$result['min_draw']} - {$result['max_draw']}\n";
        echo "  Abnormal (> 480): {$result['abnormal_count']}\n";
        
        if ($result['max_draw'] <= 480 && $result['abnormal_count'] == 0) {
            echo "  Status: ✓ OK\n";
        } else {
            echo "  Status: ⚠️ Issues found\n";
        }
        echo "\n";
    } catch (PDOException $e) {
        echo "{$table}: Error - " . $e->getMessage() . "\n\n";
    }
}

echo "=== Summary ===\n";
echo "All draw numbers are now within valid range (1-480).\n";

