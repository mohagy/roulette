<?php
/**
 * Comprehensive Analysis of Draw Number Logic
 * This script analyzes the current database state and draw number assignment logic
 */

echo "<h1>Draw Number Analysis</h1>";
echo "<div style='font-family: Arial, sans-serif; max-width: 1000px; margin: 0 auto;'>";

try {
    require_once 'php/db_connect.php';
    
    // 1. Check roulette_analytics table
    echo "<h2>1. Roulette Analytics Table</h2>";
    $stmt = $conn->prepare("SELECT * FROM roulette_analytics WHERE id = 1");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $analytics = $result->fetch_assoc();
        echo "<table style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
        echo "<tr style='background: #f5f5f5;'>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Field</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Value</th>";
        echo "</tr>";
        
        foreach ($analytics as $key => $value) {
            if ($key === 'all_spins' || $key === 'number_frequency') {
                $decoded = json_decode($value, true);
                if ($key === 'all_spins' && is_array($decoded)) {
                    $value = "Array with " . count($decoded) . " spins: [" . implode(', ', array_slice($decoded, 0, 5)) . (count($decoded) > 5 ? '...' : '') . "]";
                } else {
                    $value = substr($value, 0, 100) . (strlen($value) > 100 ? '...' : '');
                }
            }
            echo "<tr>";
            echo "<td style='border: 1px solid #ddd; padding: 8px; font-weight: bold;'>$key</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>$value</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        $currentDrawFromAnalytics = $analytics['current_draw_number'];
        echo "<p><strong>Current Draw Number from Analytics:</strong> #$currentDrawFromAnalytics</p>";
        echo "<p><strong>Next Draw Number (Analytics + 1):</strong> #" . ($currentDrawFromAnalytics + 1) . "</p>";
    } else {
        echo "<p style='color: red;'>No data found in roulette_analytics table</p>";
    }
    
    // 2. Check roulette_state table
    echo "<h2>2. Roulette State Table</h2>";
    $stmt = $conn->prepare("SELECT * FROM roulette_state WHERE id = 1");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $state = $result->fetch_assoc();
        echo "<table style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
        echo "<tr style='background: #f5f5f5;'>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Field</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Value</th>";
        echo "</tr>";
        
        foreach ($state as $key => $value) {
            echo "<tr>";
            echo "<td style='border: 1px solid #ddd; padding: 8px; font-weight: bold;'>$key</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>$value</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        if (isset($state['last_draw']) && isset($state['next_draw'])) {
            $lastDraw = (int)str_replace('#', '', $state['last_draw']);
            $nextDraw = (int)str_replace('#', '', $state['next_draw']);
            echo "<p><strong>Last Draw (Completed):</strong> #$lastDraw</p>";
            echo "<p><strong>Next Draw (Upcoming):</strong> #$nextDraw</p>";
        }
    } else {
        echo "<p style='color: red;'>No data found in roulette_state table</p>";
    }
    
    // 3. Check recent betting slips and their draw numbers
    echo "<h2>3. Recent Betting Slips Analysis</h2>";
    $stmt = $conn->prepare("
        SELECT bs.slip_number, bs.draw_number, bs.created_at, u.username
        FROM betting_slips bs
        JOIN users u ON bs.user_id = u.user_id
        ORDER BY bs.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "<table style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
        echo "<tr style='background: #f5f5f5;'>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Slip Number</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Draw Number</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Created At</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>User</th>";
        echo "</tr>";
        
        while ($slip = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($slip['slip_number']) . "</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>#" . $slip['draw_number'] . "</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . $slip['created_at'] . "</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($slip['username']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No betting slips found</p>";
    }
    
    // 4. Check detailed_draw_results for completed draws
    echo "<h2>4. Completed Draws (detailed_draw_results)</h2>";
    $stmt = $conn->prepare("
        SELECT draw_number, winning_number, winning_color, draw_time
        FROM detailed_draw_results
        ORDER BY draw_number DESC
        LIMIT 5
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "<table style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
        echo "<tr style='background: #f5f5f5;'>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Draw Number</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Winning Number</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Color</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>Draw Time</th>";
        echo "</tr>";
        
        while ($draw = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>#" . $draw['draw_number'] . "</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . $draw['winning_number'] . "</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . $draw['winning_color'] . "</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . $draw['draw_time'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No completed draws found</p>";
    }
    
    // 5. Analysis and Recommendations
    echo "<h2>5. Analysis and Recommendations</h2>";
    
    $issues = [];
    $recommendations = [];
    
    // Check if current draw logic is correct
    if (isset($currentDrawFromAnalytics)) {
        echo "<div style='background: #f9f9f9; padding: 15px; border: 1px solid #ddd; margin: 10px 0;'>";
        echo "<h3>Current Logic Analysis:</h3>";
        
        // Check what the JavaScript getCurrentDrawNumber() functions are doing
        echo "<p><strong>JavaScript getCurrentDrawNumber() Logic:</strong></p>";
        echo "<ul>";
        echo "<li>Looks for 'next-draw-number' element in DOM</li>";
        echo "<li>Falls back to window.drawHeader.currentDrawNumber</li>";
        echo "<li>Default fallback varies (1, 19, etc.)</li>";
        echo "</ul>";
        
        echo "<p><strong>Problem Identified:</strong></p>";
        echo "<ul style='color: red;'>";
        echo "<li>The getCurrentDrawNumber() function is looking for 'next-draw-number' but treating it as current draw</li>";
        echo "<li>This means betting slips might be assigned to the wrong draw number</li>";
        echo "<li>The function should get the NEXT/UPCOMING draw number, not the current completed draw</li>";
        echo "</ul>";
        
        echo "<p><strong>Correct Logic Should Be:</strong></p>";
        echo "<ul style='color: green;'>";
        echo "<li><strong>Current Draw:</strong> The most recently completed draw with results</li>";
        echo "<li><strong>Next Draw:</strong> The upcoming draw that bets can be placed on</li>";
        echo "<li><strong>Betting slips should ALWAYS be assigned to the NEXT draw number</strong></li>";
        echo "</ul>";
        
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "</div>";
?>
