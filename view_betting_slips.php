<?php
/**
 * View available betting slips for testing
 */

echo "<h1>Available Betting Slips for Testing</h1>";
echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto;'>";

try {
    require_once 'php/db_connect.php';
    
    // Get recent betting slips
    $stmt = $conn->prepare("
        SELECT bs.*, u.username,
               (SELECT COUNT(*) FROM slip_details sd WHERE sd.slip_id = bs.slip_id) as bet_count
        FROM betting_slips bs
        JOIN users u ON bs.user_id = u.user_id
        ORDER BY bs.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "<h2>Recent Betting Slips (Last 10)</h2>";
        echo "<table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>";
        echo "<tr style='background: #f5f5f5; border: 1px solid #ddd;'>";
        echo "<th style='padding: 10px; border: 1px solid #ddd;'>Slip Number</th>";
        echo "<th style='padding: 10px; border: 1px solid #ddd;'>Draw #</th>";
        echo "<th style='padding: 10px; border: 1px solid #ddd;'>User</th>";
        echo "<th style='padding: 10px; border: 1px solid #ddd;'>Stake</th>";
        echo "<th style='padding: 10px; border: 1px solid #ddd;'>Potential</th>";
        echo "<th style='padding: 10px; border: 1px solid #ddd;'>Bets</th>";
        echo "<th style='padding: 10px; border: 1px solid #ddd;'>Status</th>";
        echo "<th style='padding: 10px; border: 1px solid #ddd;'>Created</th>";
        echo "<th style='padding: 10px; border: 1px solid #ddd;'>Action</th>";
        echo "</tr>";
        
        while ($slip = $result->fetch_assoc()) {
            $status = 'Active';
            $statusColor = 'green';
            
            if ($slip['is_paid'] == 1) {
                $status = 'Paid';
                $statusColor = 'blue';
            } elseif ($slip['is_cancelled'] == 1) {
                $status = 'Cancelled';
                $statusColor = 'red';
            }
            
            echo "<tr style='border: 1px solid #ddd;'>";
            echo "<td style='padding: 8px; border: 1px solid #ddd; font-weight: bold;'>" . htmlspecialchars($slip['slip_number']) . "</td>";
            echo "<td style='padding: 8px; border: 1px solid #ddd;'>#" . $slip['draw_number'] . "</td>";
            echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($slip['username']) . "</td>";
            echo "<td style='padding: 8px; border: 1px solid #ddd;'>$" . number_format($slip['total_stake'], 2) . "</td>";
            echo "<td style='padding: 8px; border: 1px solid #ddd;'>$" . number_format($slip['potential_payout'], 2) . "</td>";
            echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . $slip['bet_count'] . "</td>";
            echo "<td style='padding: 8px; border: 1px solid #ddd; color: $statusColor;'>$status</td>";
            echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . date('M j, Y H:i', strtotime($slip['created_at'])) . "</td>";
            echo "<td style='padding: 8px; border: 1px solid #ddd;'>";
            echo "<button onclick='testSlip(\"" . $slip['slip_number'] . "\")' style='padding: 5px 10px; background: #007cba; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 12px;'>Test</button>";
            echo "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>No betting slips found in the database.</p>";
    }
    
    $stmt->close();
    
    // Get current draw information
    echo "<h2>Current Draw Information</h2>";
    
    $drawStmt = $conn->prepare("
        SELECT current_draw_number, all_spins
        FROM roulette_analytics
        WHERE id = 1
    ");
    $drawStmt->execute();
    $drawResult = $drawStmt->get_result();
    
    if ($drawResult->num_rows > 0) {
        $drawData = $drawResult->fetch_assoc();
        echo "<p><strong>Current Draw Number:</strong> #" . $drawData['current_draw_number'] . "</p>";
        
        $allSpins = json_decode($drawData['all_spins'], true);
        if ($allSpins && count($allSpins) > 0) {
            echo "<p><strong>Recent Results:</strong> ";
            $recentSpins = array_slice($allSpins, 0, 5);
            echo implode(', ', $recentSpins);
            echo "</p>";
        }
    }
    
    $drawStmt->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "</div>";

// JavaScript for testing
echo '<script>
async function testSlip(slipNumber) {
    console.log("Testing slip:", slipNumber);
    
    try {
        const formData = new FormData();
        formData.append("action", "verify_cashout");
        formData.append("slip_number", slipNumber);
        
        const response = await fetch("/slipp/php/cashout_api.php", {
            method: "POST",
            body: formData
        });
        
        const text = await response.text();
        console.log("Response for", slipNumber, ":", text);
        
        try {
            const json = JSON.parse(text);
            if (json.status === "success") {
                alert("✓ Slip " + slipNumber + " verified successfully!\\n" +
                      "Draw: #" + json.draw_number + "\\n" +
                      "Winning Number: " + json.winning_number + " (" + json.winning_color + ")\\n" +
                      "Total Winnings: $" + json.total_winnings);
            } else {
                alert("✗ Error: " + json.message);
            }
        } catch (parseError) {
            alert("✗ Invalid response: " + text);
        }
        
    } catch (error) {
        alert("✗ Network error: " + error.message);
    }
}
</script>';
?>
