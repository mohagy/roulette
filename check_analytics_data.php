<?php
/**
 * Check roulette_analytics data to find the source of the bug
 */

echo "<h1>🔍 Check Analytics Data - Bug Source Investigation</h1>";
echo "<div style='font-family: Arial, sans-serif; max-width: 1000px; margin: 0 auto;'>";

try {
    require_once 'php/db_connect.php';
    
    echo "<h2>1. Roulette Analytics Table Data</h2>";
    
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
            if ($key === 'all_spins') {
                $spins = json_decode($value, true);
                $spinCount = is_array($spins) ? count($spins) : 0;
                echo "<tr>";
                echo "<td style='border: 1px solid #ddd; padding: 8px;'>$key</td>";
                echo "<td style='border: 1px solid #ddd; padding: 8px;'>Array with $spinCount spins</td>";
                echo "</tr>";
            } else {
                echo "<tr>";
                echo "<td style='border: 1px solid #ddd; padding: 8px;'>$key</td>";
                echo "<td style='border: 1px solid #ddd; padding: 8px;'>$value</td>";
                echo "</tr>";
            }
        }
        echo "</table>";
        
        $currentDrawNumber = (int)$analytics['current_draw_number'];
        $allSpins = json_decode($analytics['all_spins'], true);
        
        echo "<h2>2. Critical Analysis</h2>";
        
        if ($currentDrawNumber >= 127) {
            echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0;'>";
            echo "<h3>🚨 BUG FOUND!</h3>";
            echo "<p><strong>current_draw_number = $currentDrawNumber</strong></p>";
            echo "<p>Since $currentDrawNumber >= 127, the system thinks draw #127 is an 'old draw' and tries to get results from all_spins array!</p>";
            echo "</div>";
        } else {
            echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0;'>";
            echo "<p><strong>current_draw_number = $currentDrawNumber</strong></p>";
            echo "<p>This should correctly reject draw #127 as future.</p>";
            echo "</div>";
        }
        
        echo "<h2>3. All Spins Array Analysis</h2>";
        
        if (is_array($allSpins) && count($allSpins) > 0) {
            echo "<p><strong>All Spins Array has " . count($allSpins) . " entries</strong></p>";
            
            // Calculate what index would be used for draw #127
            $spin_index_127 = $currentDrawNumber - 127 - 1;
            
            echo "<p><strong>For draw #127:</strong></p>";
            echo "<ul>";
            echo "<li>current_draw_number = $currentDrawNumber</li>";
            echo "<li>requested_draw = 127</li>";
            echo "<li>spin_index = $currentDrawNumber - 127 - 1 = $spin_index_127</li>";
            echo "</ul>";
            
            if ($spin_index_127 >= 0 && $spin_index_127 < count($allSpins)) {
                $fabricated_number = $allSpins[$spin_index_127];
                echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0;'>";
                echo "<h3>🚨 CRITICAL BUG CONFIRMED!</h3>";
                echo "<p><strong>The system is returning fabricated winning number: $fabricated_number</strong></p>";
                echo "<p>This number comes from all_spins[$spin_index_127] = $fabricated_number</p>";
                echo "<p>This is NOT a real draw result - it's fabricated data!</p>";
                echo "</div>";
                
                // Show some context around this index
                echo "<h3>All Spins Array Context (around index $spin_index_127):</h3>";
                echo "<table style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
                echo "<tr style='background: #f5f5f5;'>";
                echo "<th style='border: 1px solid #ddd; padding: 8px;'>Index</th>";
                echo "<th style='border: 1px solid #ddd; padding: 8px;'>Value</th>";
                echo "<th style='border: 1px solid #ddd; padding: 8px;'>Represents Draw</th>";
                echo "</tr>";
                
                for ($i = max(0, $spin_index_127 - 2); $i <= min(count($allSpins) - 1, $spin_index_127 + 2); $i++) {
                    $draw_for_index = $currentDrawNumber - $i - 1;
                    $highlight = ($i == $spin_index_127) ? "background: #f8d7da;" : "";
                    echo "<tr style='$highlight'>";
                    echo "<td style='border: 1px solid #ddd; padding: 8px;'>$i</td>";
                    echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . $allSpins[$i] . "</td>";
                    echo "<td style='border: 1px solid #ddd; padding: 8px;'>#$draw_for_index</td>";
                    echo "</tr>";
                }
                echo "</table>";
                
            } else {
                echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0;'>";
                echo "<p><strong>Index $spin_index_127 is out of bounds for all_spins array</strong></p>";
                echo "<p>This should result in 'No results found' error.</p>";
                echo "</div>";
            }
            
        } else {
            echo "<p>All spins array is empty or invalid.</p>";
        }
        
    } else {
        echo "<p>No data found in roulette_analytics table.</p>";
    }
    
    echo "<h2>4. Detailed Draw Results Check</h2>";
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count, MAX(draw_number) as max_draw FROM detailed_draw_results");
    $stmt->execute();
    $result = $stmt->get_result();
    $detailedInfo = $result->fetch_assoc();
    
    echo "<p><strong>Detailed Draw Results:</strong></p>";
    echo "<ul>";
    echo "<li>Total completed draws: " . $detailedInfo['count'] . "</li>";
    echo "<li>Highest completed draw: #" . ($detailedInfo['max_draw'] ?: 'None') . "</li>";
    echo "</ul>";
    
    if ($detailedInfo['max_draw'] < 127) {
        echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0;'>";
        echo "<p><strong>✓ CORRECT:</strong> Draw #127 does NOT exist in detailed_draw_results</p>";
        echo "<p>The highest completed draw is #" . $detailedInfo['max_draw'] . "</p>";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0;'>";
        echo "<p><strong>❌ PROBLEM:</strong> Draw #127 or higher exists in detailed_draw_results</p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";

echo "<h2>🔧 Bug Analysis Summary</h2>";
echo "<div style='background: #f8d7da; padding: 20px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
echo "<h3>The Bug:</h3>";
echo "<ol>";
echo "<li><strong>roulette_analytics.current_draw_number</strong> is set to a value >= 127</li>";
echo "<li><strong>validateDrawCompletion(127)</strong> sees that 127 < current_draw_number</li>";
echo "<li><strong>System thinks draw #127 is an 'old draw'</strong> that should have results</li>";
echo "<li><strong>System looks in all_spins array</strong> and finds fabricated data</li>";
echo "<li><strong>Returns fabricated winning number</strong> instead of rejecting as future draw</li>";
echo "</ol>";

echo "<h3>The Fix:</h3>";
echo "<ol>";
echo "<li><strong>Prioritize detailed_draw_results</strong> as the ONLY authoritative source</li>";
echo "<li><strong>Remove all_spins fallback logic</strong> for draw validation</li>";
echo "<li><strong>Only allow cashouts</strong> for draws that exist in detailed_draw_results</li>";
echo "<li><strong>Reject all other draws</strong> as 'not occurred yet' or 'no results available'</li>";
echo "</ol>";
echo "</div>";

echo "</div>";
?>
