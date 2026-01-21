<?php
/**
 * Clean up test slips that don't have proper bets linked
 */

echo "<h1>🧹 Clean Up Test Slips</h1>";
echo "<div style='font-family: Arial, sans-serif; max-width: 1000px; margin: 0 auto;'>";

try {
    require_once 'php/db_connect.php';
    
    echo "<h2>1. Find Test Slips Without Bets</h2>";
    
    // Find test slips that don't have any bets linked
    $stmt = $conn->prepare("
        SELECT bs.slip_id, bs.slip_number, bs.draw_number, bs.created_at,
               COUNT(sd.bet_id) as bet_count
        FROM betting_slips bs
        LEFT JOIN slip_details sd ON bs.slip_id = sd.slip_id
        WHERE bs.slip_number LIKE 'FUTURE_TEST_%' OR bs.slip_number LIKE 'COMPREHENSIVE_TEST_%'
        GROUP BY bs.slip_id, bs.slip_number, bs.draw_number, bs.created_at
        ORDER BY bs.created_at DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $slipsWithoutBets = [];
    $slipsWithBets = [];
    
    if ($result->num_rows > 0) {
        echo "<table style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
        echo "<tr style='background: #f5f5f5;'>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Slip ID</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Slip Number</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Draw #</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Bet Count</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Status</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Action</th>";
        echo "</tr>";
        
        while ($slip = $result->fetch_assoc()) {
            $betCount = $slip['bet_count'];
            $status = $betCount > 0 ? 'Has Bets' : 'No Bets';
            $statusColor = $betCount > 0 ? 'green' : 'red';
            
            if ($betCount == 0) {
                $slipsWithoutBets[] = $slip;
            } else {
                $slipsWithBets[] = $slip;
            }
            
            echo "<tr>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . $slip['slip_id'] . "</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . $slip['slip_number'] . "</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>#" . $slip['draw_number'] . "</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>$betCount</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px; color: $statusColor;'>$status</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>";
            if ($betCount == 0) {
                echo "<button onclick='fixSlip(" . $slip['slip_id'] . ", \"" . $slip['slip_number'] . "\")' style='padding: 5px 10px; background: #007cba; color: white; border: none; border-radius: 3px; cursor: pointer;'>Fix</button> ";
                echo "<button onclick='deleteSlip(" . $slip['slip_id'] . ", \"" . $slip['slip_number'] . "\")' style='padding: 5px 10px; background: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer;'>Delete</button>";
            } else {
                echo "✓ OK";
            }
            echo "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<p><strong>Summary:</strong></p>";
        echo "<ul>";
        echo "<li><strong>Slips with bets:</strong> " . count($slipsWithBets) . "</li>";
        echo "<li><strong>Slips without bets:</strong> " . count($slipsWithoutBets) . "</li>";
        echo "</ul>";
        
    } else {
        echo "<p>No test slips found.</p>";
    }
    
    echo "<h2>2. Bulk Actions</h2>";
    echo "<button onclick='fixAllSlips()' style='padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; margin: 5px;'>Fix All Slips Without Bets</button>";
    echo "<button onclick='deleteAllBrokenSlips()' style='padding: 10px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer; margin: 5px;'>Delete All Slips Without Bets</button>";
    
    echo "<div id='action-results' style='margin-top: 20px;'></div>";
    
    echo "<script>
    async function fixSlip(slipId, slipNumber) {
        try {
            const response = await fetch('cleanup_test_slips_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'fix_slip', slip_id: slipId })
            });
            
            const result = await response.json();
            if (result.success) {
                alert('Slip ' + slipNumber + ' fixed successfully!');
                location.reload();
            } else {
                alert('Failed to fix slip: ' + result.message);
            }
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }
    
    async function deleteSlip(slipId, slipNumber) {
        if (confirm('Are you sure you want to delete slip ' + slipNumber + '?')) {
            try {
                const response = await fetch('cleanup_test_slips_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete_slip', slip_id: slipId })
                });
                
                const result = await response.json();
                if (result.success) {
                    alert('Slip ' + slipNumber + ' deleted successfully!');
                    location.reload();
                } else {
                    alert('Failed to delete slip: ' + result.message);
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
    }
    
    async function fixAllSlips() {
        if (confirm('Fix all slips without bets?')) {
            try {
                const response = await fetch('cleanup_test_slips_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'fix_all_slips' })
                });
                
                const result = await response.json();
                alert(result.message);
                location.reload();
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
    }
    
    async function deleteAllBrokenSlips() {
        if (confirm('Delete all slips without bets? This cannot be undone!')) {
            try {
                const response = await fetch('cleanup_test_slips_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete_all_broken_slips' })
                });
                
                const result = await response.json();
                alert(result.message);
                location.reload();
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
    }
    </script>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "</div>";
?>
