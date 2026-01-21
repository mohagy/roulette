<?php
/**
 * Debug the getCurrentDrawInfo function to see why it's returning 0
 */

echo "<h1>🔍 Debug Current Draw Info Function</h1>";
echo "<div style='font-family: Arial, sans-serif; max-width: 1000px; margin: 0 auto;'>";

try {
    require_once 'php/db_connect.php';
    
    echo "<h2>1. Check roulette_state table</h2>";
    $stmt = $conn->prepare("SELECT last_draw, next_draw FROM roulette_state WHERE id = 1");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $state = $result->fetch_assoc();
        echo "<p><strong>Raw data from roulette_state:</strong></p>";
        echo "<ul>";
        echo "<li>last_draw: '" . $state['last_draw'] . "'</li>";
        echo "<li>next_draw: '" . $state['next_draw'] . "'</li>";
        echo "</ul>";
        
        $currentDraw = (int)str_replace('#', '', $state['last_draw']);
        $nextDraw = (int)str_replace('#', '', $state['next_draw']);
        
        echo "<p><strong>After processing:</strong></p>";
        echo "<ul>";
        echo "<li>current_draw: $currentDraw</li>";
        echo "<li>next_draw: $nextDraw</li>";
        echo "</ul>";
        
        if ($currentDraw == 0) {
            echo "<p style='color: red;'><strong>⚠️ ISSUE FOUND:</strong> last_draw is converting to 0!</p>";
            echo "<p>This explains why the validation is showing 'Current completed draw is #0'</p>";
        }
    } else {
        echo "<p>No data found in roulette_state table</p>";
    }
    
    echo "<h2>2. Check roulette_analytics table</h2>";
    $stmt = $conn->prepare("SELECT current_draw_number FROM roulette_analytics WHERE id = 1");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $analytics = $result->fetch_assoc();
        echo "<p><strong>Raw data from roulette_analytics:</strong></p>";
        echo "<ul>";
        echo "<li>current_draw_number: " . $analytics['current_draw_number'] . "</li>";
        echo "</ul>";
        
        if ($analytics['current_draw_number'] == 114) {
            echo "<p style='color: green;'><strong>✅ CORRECT:</strong> roulette_analytics has the right current draw number!</p>";
        }
    } else {
        echo "<p>No data found in roulette_analytics table</p>";
    }
    
    echo "<h2>3. Check detailed_draw_results table</h2>";
    $stmt = $conn->prepare("SELECT MAX(draw_number) as max_draw FROM detailed_draw_results");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $detailed = $result->fetch_assoc();
        echo "<p><strong>Raw data from detailed_draw_results:</strong></p>";
        echo "<ul>";
        echo "<li>max_draw: " . $detailed['max_draw'] . "</li>";
        echo "</ul>";
        
        if ($detailed['max_draw'] == 114) {
            echo "<p style='color: green;'><strong>✅ CORRECT:</strong> detailed_draw_results has the right max draw number!</p>";
        }
    } else {
        echo "<p>No data found in detailed_draw_results table</p>";
    }
    
    echo "<h2>4. Test the getCurrentDrawInfo function</h2>";
    
    // Copy the function logic here for testing
    function testGetCurrentDrawInfo($conn) {
        $info = [
            'current_draw' => 0,
            'next_draw' => 1,
            'source' => 'fallback'
        ];

        try {
            echo "<h3>Step 1: Try roulette_state table</h3>";
            $stateStmt = $conn->prepare("SELECT last_draw, next_draw FROM roulette_state WHERE id = 1");
            if ($stateStmt) {
                $stateStmt->execute();
                $stateResult = $stateStmt->get_result();

                if ($stateResult->num_rows > 0) {
                    $state = $stateResult->fetch_assoc();
                    $info['current_draw'] = (int)str_replace('#', '', $state['last_draw']);
                    $info['next_draw'] = (int)str_replace('#', '', $state['next_draw']);
                    $info['source'] = 'roulette_state';
                    echo "<p style='color: orange;'>Using roulette_state: current_draw = " . $info['current_draw'] . "</p>";
                    $stateStmt->close();
                    return $info;
                }
                $stateStmt->close();
            }

            echo "<h3>Step 2: Try roulette_analytics table</h3>";
            $analyticsStmt = $conn->prepare("SELECT current_draw_number FROM roulette_analytics WHERE id = 1");
            if ($analyticsStmt) {
                $analyticsStmt->execute();
                $analyticsResult = $analyticsStmt->get_result();

                if ($analyticsResult->num_rows > 0) {
                    $analytics = $analyticsResult->fetch_assoc();
                    $info['current_draw'] = (int)$analytics['current_draw_number'];
                    $info['next_draw'] = $info['current_draw'] + 1;
                    $info['source'] = 'roulette_analytics';
                    echo "<p style='color: green;'>Using roulette_analytics: current_draw = " . $info['current_draw'] . "</p>";
                    $analyticsStmt->close();
                    return $info;
                }
                $analyticsStmt->close();
            }

            echo "<h3>Step 3: Try detailed_draw_results table</h3>";
            $detailedStmt = $conn->prepare("SELECT MAX(draw_number) as max_draw FROM detailed_draw_results");
            if ($detailedStmt) {
                $detailedStmt->execute();
                $detailedResult = $detailedStmt->get_result();

                if ($detailedResult->num_rows > 0) {
                    $detailed = $detailedResult->fetch_assoc();
                    if ($detailed['max_draw']) {
                        $info['current_draw'] = (int)$detailed['max_draw'];
                        $info['next_draw'] = $info['current_draw'] + 1;
                        $info['source'] = 'detailed_draw_results';
                        echo "<p style='color: blue;'>Using detailed_draw_results: current_draw = " . $info['current_draw'] . "</p>";
                    }
                }
                $detailedStmt->close();
            }

        } catch (Exception $e) {
            echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
        }

        return $info;
    }
    
    $testResult = testGetCurrentDrawInfo($conn);
    
    echo "<h3>Final Result:</h3>";
    echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>";
    print_r($testResult);
    echo "</pre>";
    
    if ($testResult['current_draw'] == 0) {
        echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0;'>";
        echo "<h3>🚨 PROBLEM IDENTIFIED</h3>";
        echo "<p>The getCurrentDrawInfo() function is returning 0 because:</p>";
        echo "<ul>";
        echo "<li>roulette_state table has invalid data (last_draw converts to 0)</li>";
        echo "<li>The function prioritizes roulette_state over roulette_analytics</li>";
        echo "<li>We need to fix the priority order or fix the roulette_state data</li>";
        echo "</ul>";
        echo "</div>";
    } else {
        echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0;'>";
        echo "<h3>✅ FUNCTION WORKING</h3>";
        echo "<p>getCurrentDrawInfo() is returning the correct current draw: " . $testResult['current_draw'] . "</p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "</div>";
?>
