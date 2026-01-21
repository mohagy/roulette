<?php
/**
 * Investigate Draw #127 Issue - Critical Bug Analysis
 */

echo "<h1>🚨 Critical Issue Investigation - Draw #127</h1>";
echo "<div style='font-family: Arial, sans-serif; max-width: 1200px; margin: 0 auto;'>";

try {
    require_once 'php/db_connect.php';
    
    echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0;'>";
    echo "<h2>🚨 CRITICAL BUG DETECTED</h2>";
    echo "<p><strong>Issue:</strong> System incorrectly processing draw #127 as completed</p>";
    echo "<p><strong>Expected:</strong> Should reject as future draw</p>";
    echo "<p><strong>Actual:</strong> Returns winning number 27</p>";
    echo "</div>";
    
    // 1. Check detailed_draw_results for draw #127
    echo "<h2>1. Check detailed_draw_results Table</h2>";
    $stmt = $conn->prepare("SELECT * FROM detailed_draw_results WHERE draw_number = 127");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "<div style='background: #f8d7da; padding: 10px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
        echo "<p style='color: red;'><strong>❌ PROBLEM FOUND:</strong> Draw #127 EXISTS in detailed_draw_results!</p>";
        $draw127 = $result->fetch_assoc();
        echo "<pre style='background: white; padding: 10px; border: 1px solid #ccc;'>";
        print_r($draw127);
        echo "</pre>";
        echo "</div>";
    } else {
        echo "<div style='background: #d4edda; padding: 10px; border: 1px solid #c3e6cb; border-radius: 5px;'>";
        echo "<p style='color: green;'><strong>✓ CORRECT:</strong> Draw #127 does NOT exist in detailed_draw_results</p>";
        echo "</div>";
    }
    
    // 2. Check current draw status
    echo "<h2>2. Check Current Draw Status</h2>";
    
    // From roulette_analytics
    $stmt = $conn->prepare("SELECT current_draw_number FROM roulette_analytics WHERE id = 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $analyticsCurrentDraw = $result->fetch_assoc()['current_draw_number'] ?? 0;
    
    // From detailed_draw_results (max)
    $stmt = $conn->prepare("SELECT MAX(draw_number) as max_draw FROM detailed_draw_results");
    $stmt->execute();
    $result = $stmt->get_result();
    $maxCompletedDraw = $result->fetch_assoc()['max_draw'] ?? 0;
    
    echo "<table style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
    echo "<tr style='background: #f5f5f5;'>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Data Source</th>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Current Draw</th>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>Status</th>";
    echo "</tr>";
    echo "<tr>";
    echo "<td style='border: 1px solid #ddd; padding: 8px;'>roulette_analytics</td>";
    echo "<td style='border: 1px solid #ddd; padding: 8px;'>#$analyticsCurrentDraw</td>";
    echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . ($analyticsCurrentDraw >= 127 ? "❌ >= 127" : "✓ < 127") . "</td>";
    echo "</tr>";
    echo "<tr>";
    echo "<td style='border: 1px solid #ddd; padding: 8px;'>detailed_draw_results (MAX)</td>";
    echo "<td style='border: 1px solid #ddd; padding: 8px;'>#$maxCompletedDraw</td>";
    echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . ($maxCompletedDraw >= 127 ? "❌ >= 127" : "✓ < 127") . "</td>";
    echo "</tr>";
    echo "</table>";
    
    // 3. Test the getCurrentDrawInfo function
    echo "<h2>3. Test getCurrentDrawInfo() Function</h2>";
    
    // Include the function from cashout_api.php
    function testGetCurrentDrawInfo($conn) {
        $info = [
            'current_draw' => 0,
            'next_draw' => 1,
            'source' => 'fallback'
        ];

        try {
            // Try roulette_analytics table first (most reliable for current draw)
            $analyticsStmt = $conn->prepare("SELECT current_draw_number FROM roulette_analytics WHERE id = 1");
            if ($analyticsStmt) {
                $analyticsStmt->execute();
                $analyticsResult = $analyticsStmt->get_result();

                if ($analyticsResult->num_rows > 0) {
                    $analytics = $analyticsResult->fetch_assoc();
                    $currentDrawNumber = (int)$analytics['current_draw_number'];
                    if ($currentDrawNumber > 0) {
                        $info['current_draw'] = $currentDrawNumber;
                        $info['next_draw'] = $info['current_draw'] + 1;
                        $info['source'] = 'roulette_analytics';
                        $analyticsStmt->close();
                        return $info;
                    }
                }
                $analyticsStmt->close();
            }

            // Try detailed_draw_results table as second priority
            $detailedStmt = $conn->prepare("SELECT MAX(draw_number) as max_draw FROM detailed_draw_results");
            if ($detailedStmt) {
                $detailedStmt->execute();
                $detailedResult = $detailedStmt->get_result();

                if ($detailedResult->num_rows > 0) {
                    $detailed = $detailedResult->fetch_assoc();
                    if ($detailed['max_draw'] && $detailed['max_draw'] > 0) {
                        $info['current_draw'] = (int)$detailed['max_draw'];
                        $info['next_draw'] = $info['current_draw'] + 1;
                        $info['source'] = 'detailed_draw_results';
                        $detailedStmt->close();
                        return $info;
                    }
                }
                $detailedStmt->close();
            }

        } catch (Exception $e) {
            // Use fallback values
        }

        return $info;
    }
    
    $currentDrawInfo = testGetCurrentDrawInfo($conn);
    
    echo "<div style='background: #e2f3ff; padding: 10px; border: 1px solid #b8daff; border-radius: 5px;'>";
    echo "<p><strong>getCurrentDrawInfo() Result:</strong></p>";
    echo "<pre style='background: white; padding: 10px; border: 1px solid #ccc;'>";
    print_r($currentDrawInfo);
    echo "</pre>";
    
    if ($currentDrawInfo['current_draw'] >= 127) {
        echo "<p style='color: red;'><strong>❌ PROBLEM:</strong> getCurrentDrawInfo() returns current_draw >= 127</p>";
    } else {
        echo "<p style='color: green;'><strong>✓ CORRECT:</strong> getCurrentDrawInfo() returns current_draw < 127</p>";
    }
    echo "</div>";
    
    // 4. Test the validateDrawCompletion function for draw #127
    echo "<h2>4. Test validateDrawCompletion() for Draw #127</h2>";
    
    // Simulate the validation logic
    function testValidateDrawCompletion($conn, $draw_number) {
        $result = [
            'is_completed' => false,
            'winning_number' => null,
            'winning_color' => null,
            'error_message' => '',
            'debug_info' => []
        ];

        try {
            // Method 1: Check if draw exists in detailed_draw_results
            $result['debug_info'][] = "Checking detailed_draw_results for draw #$draw_number";
            
            $historyStmt = $conn->prepare("SELECT winning_number, color, timestamp FROM detailed_draw_results WHERE draw_number = ? LIMIT 1");
            $historyStmt->bind_param("i", $draw_number);
            $historyStmt->execute();
            $historyResult = $historyStmt->get_result();

            if ($historyResult->num_rows > 0) {
                $drawHistory = $historyResult->fetch_assoc();
                $result['is_completed'] = true;
                $result['winning_number'] = $drawHistory['winning_number'];
                $result['winning_color'] = $drawHistory['color'] ?? 'calculated';
                $result['debug_info'][] = "FOUND in detailed_draw_results: " . json_encode($drawHistory);
                $historyStmt->close();
                return $result;
            }
            $historyStmt->close();
            $result['debug_info'][] = "NOT FOUND in detailed_draw_results";

            // Method 2: Check current draw status
            $current_draw_info = testGetCurrentDrawInfo($conn);
            $result['debug_info'][] = "Current draw info: " . json_encode($current_draw_info);

            if ($draw_number > $current_draw_info['current_draw']) {
                $result['error_message'] = "This draw (#$draw_number) has not occurred yet. Current completed draw is #" . $current_draw_info['current_draw'] . ". Please wait for the draw to be completed before attempting to cash out.";
                $result['debug_info'][] = "Draw is in the future - should be rejected";
                return $result;
            }

            // If we reach here, it's an old draw but no results found
            $result['error_message'] = "No results found for draw #$draw_number.";
            $result['debug_info'][] = "Old draw but no results found";

        } catch (Exception $e) {
            $result['error_message'] = "Error validating draw completion: " . $e->getMessage();
            $result['debug_info'][] = "Exception: " . $e->getMessage();
        }

        return $result;
    }
    
    $validation127 = testValidateDrawCompletion($conn, 127);
    
    echo "<div style='background: #fff3cd; padding: 10px; border: 1px solid #ffeaa7; border-radius: 5px;'>";
    echo "<p><strong>validateDrawCompletion(127) Result:</strong></p>";
    echo "<pre style='background: white; padding: 10px; border: 1px solid #ccc;'>";
    print_r($validation127);
    echo "</pre>";
    
    if ($validation127['is_completed']) {
        echo "<p style='color: red;'><strong>❌ CRITICAL BUG:</strong> Function incorrectly returns is_completed = true for draw #127!</p>";
    } else {
        echo "<p style='color: green;'><strong>✓ CORRECT:</strong> Function correctly rejects draw #127</p>";
    }
    echo "</div>";
    
    // 5. Test the actual API call
    echo "<h2>5. Test Actual API Call</h2>";
    echo "<p>Testing the actual cashout API with slip COMPREHENSIVE_TEST_1...</p>";
    
    echo "<div id='api-test-result'></div>";
    
    echo "<script>
    async function testActualAPI() {
        try {
            const formData = new FormData();
            formData.append('action', 'verify_cashout');
            formData.append('slip_number', 'COMPREHENSIVE_TEST_1');
            
            const response = await fetch('/slipp/php/cashout_api.php', {
                method: 'POST',
                body: formData
            });
            
            const text = await response.text();
            console.log('Raw API response:', text);
            
            try {
                const json = JSON.parse(text);
                const resultDiv = document.getElementById('api-test-result');
                
                let html = '<div style=\"background: #e2f3ff; padding: 10px; border: 1px solid #b8daff; border-radius: 5px;\">';
                html += '<p><strong>API Response:</strong></p>';
                html += '<pre style=\"background: white; padding: 10px; border: 1px solid #ccc;\">' + JSON.stringify(json, null, 2) + '</pre>';
                
                if (json.status === 'success') {
                    html += '<p style=\"color: red;\"><strong>❌ CRITICAL BUG CONFIRMED:</strong> API incorrectly returns success for draw #127!</p>';
                    html += '<p><strong>Winning Number:</strong> ' + json.winning_number + '</p>';
                    html += '<p><strong>This should NOT happen!</strong></p>';
                } else {
                    html += '<p style=\"color: green;\"><strong>✓ CORRECT:</strong> API correctly rejects draw #127</p>';
                    html += '<p><strong>Error Message:</strong> ' + json.message + '</p>';
                }
                
                html += '</div>';
                resultDiv.innerHTML = html;
                
            } catch (parseError) {
                document.getElementById('api-test-result').innerHTML = 
                    '<div style=\"background: #f8d7da; padding: 10px; border: 1px solid #f5c6cb; border-radius: 5px;\">' +
                    '<p style=\"color: red;\"><strong>Parse Error:</strong> ' + parseError.message + '</p>' +
                    '<pre style=\"background: white; padding: 10px; border: 1px solid #ccc;\">' + text + '</pre>' +
                    '</div>';
            }
            
        } catch (error) {
            document.getElementById('api-test-result').innerHTML = 
                '<div style=\"background: #f8d7da; padding: 10px; border: 1px solid #f5c6cb; border-radius: 5px;\">' +
                '<p style=\"color: red;\"><strong>Network Error:</strong> ' + error.message + '</p>' +
                '</div>';
        }
    }
    
    testActualAPI();
    </script>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";

echo "<h2>🔍 Investigation Summary</h2>";
echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
echo "<h3>Critical Bug Analysis:</h3>";
echo "<p>The system is incorrectly processing draw #127 as completed when it should be rejecting it as a future draw.</p>";
echo "<h3>Investigation Points:</h3>";
echo "<ul>";
echo "<li>✓ Check if draw #127 exists in detailed_draw_results</li>";
echo "<li>✓ Verify current draw status from multiple sources</li>";
echo "<li>✓ Test getCurrentDrawInfo() function</li>";
echo "<li>✓ Test validateDrawCompletion() function</li>";
echo "<li>✓ Test actual API call</li>";
echo "</ul>";
echo "<h3>Expected Findings:</h3>";
echo "<ul>";
echo "<li>Draw #127 should NOT exist in detailed_draw_results</li>";
echo "<li>Current draw should be < 127</li>";
echo "<li>validateDrawCompletion(127) should return is_completed = false</li>";
echo "<li>API should reject with 'has not occurred yet' error</li>";
echo "</ul>";
echo "</div>";

echo "</div>";
?>
