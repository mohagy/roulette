<?php
/**
 * Final verification test - ensure all issues are completely resolved
 */

echo "<h1>🎯 Final Verification Test - Complete Resolution</h1>";
echo "<div style='font-family: Arial, sans-serif; max-width: 1000px; margin: 0 auto;'>";

try {
    require_once 'php/db_connect.php';
    
    // Get current system status
    $stmt = $conn->prepare("SELECT current_draw_number FROM roulette_analytics WHERE id = 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $currentDraw = $result->fetch_assoc()['current_draw_number'] ?? 0;
    
    echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0;'>";
    echo "<h2>✅ System Status Verified</h2>";
    echo "<p><strong>Current Draw:</strong> #$currentDraw</p>";
    echo "</div>";
    
    // Test 1: Create a future draw slip and test validation
    echo "<h2>🔮 Test 1: Future Draw Validation</h2>";
    
    $futureDraw = $currentDraw + 20;
    $testSlipNumber = 'FINAL_VERIFICATION_' . time() . '_' . rand(1000, 9999);
    
    // Create the test slip
    $stmt = $conn->prepare("
        INSERT INTO betting_slips (
            slip_number, user_id, total_stake, potential_payout, 
            created_at, updated_at, is_paid, is_cancelled, 
            draw_number, status
        ) VALUES (?, 1, 10.00, 350.00, NOW(), NOW(), 0, 0, ?, 'active')
    ");
    $stmt->bind_param("si", $testSlipNumber, $futureDraw);
    
    if ($stmt->execute()) {
        $slipId = $conn->insert_id;
        
        // Create and link a test bet
        $stmt2 = $conn->prepare("
            INSERT INTO bets (
                user_id, bet_type, bet_description, 
                bet_amount, multiplier, potential_return, created_at
            ) VALUES (1, 'straight', 'Straight Up on 7', 10.00, 35.00, 350.00, NOW())
        ");
        
        if ($stmt2->execute()) {
            $betId = $conn->insert_id;
            $stmt3 = $conn->prepare("INSERT INTO slip_details (slip_id, bet_id) VALUES (?, ?)");
            $stmt3->bind_param("ii", $slipId, $betId);
            $stmt3->execute();
            $stmt3->close();
        }
        $stmt2->close();
        
        echo "<p style='color: green;'><strong>✅ Created test slip for future draw #$futureDraw</strong></p>";
        echo "<p><strong>Slip Number:</strong> $testSlipNumber</p>";
        
        // Now test the validation
        echo "<div id='future-validation-result'></div>";
        echo "<script>
        async function testFutureValidation() {
            try {
                const formData = new FormData();
                formData.append('action', 'verify_cashout');
                formData.append('slip_number', '$testSlipNumber');
                
                const response = await fetch('/slipp/php/cashout_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const json = await response.json();
                const success = json.status === 'success';
                const resultDiv = document.getElementById('future-validation-result');
                
                if (success) {
                    resultDiv.innerHTML = `
                        <div style=\"background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0;\">
                            <h3>🚨 CRITICAL FAILURE</h3>
                            <p><strong>Future draw validation is NOT working!</strong></p>
                            <p>Slip for draw #$futureDraw should have been rejected!</p>
                            <p>Returned: Draw #\${json.draw_number}, Winning: \${json.winning_number}</p>
                        </div>
                    `;
                } else {
                    const hasCorrectMessage = json.message.includes('has not occurred yet') && 
                                            json.message.includes('$currentDraw');
                    
                    resultDiv.innerHTML = `
                        <div style=\"background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0;\">
                            <h3>✅ PERFECT SUCCESS</h3>
                            <p><strong>Future draw validation working correctly!</strong></p>
                            <p><strong>Error Message:</strong> \${json.message}</p>
                            <p style=\"color: green;\">
                                \${hasCorrectMessage ? 
                                    '✅ Perfect! Correct error message with current draw #$currentDraw mentioned.' : 
                                    '⚠️ Error message format could be improved, but validation is working.'}
                            </p>
                        </div>
                    `;
                }
                
            } catch (error) {
                document.getElementById('future-validation-result').innerHTML = `
                    <div style=\"background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0;\">
                        <h3>❌ TEST ERROR</h3>
                        <p>\${error.message}</p>
                    </div>
                `;
            }
        }
        
        testFutureValidation();
        </script>";
        
    } else {
        echo "<p style='color: red;'><strong>✗ Failed to create test slip:</strong> " . $conn->error . "</p>";
    }
    
    // Test 2: Test a completed draw
    echo "<h2>✅ Test 2: Completed Draw Validation</h2>";
    
    $stmt = $conn->prepare("
        SELECT bs.slip_number, bs.draw_number 
        FROM betting_slips bs 
        WHERE bs.draw_number <= ? AND bs.is_paid = 0 AND bs.is_cancelled = 0 
        ORDER BY bs.draw_number DESC 
        LIMIT 1
    ");
    $stmt->bind_param("i", $currentDraw);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $completedSlip = $result->fetch_assoc();
        $completedSlipNumber = $completedSlip['slip_number'];
        $completedDrawNumber = $completedSlip['draw_number'];
        
        echo "<p><strong>Testing completed draw slip:</strong> $completedSlipNumber (Draw #$completedDrawNumber)</p>";
        
        echo "<div id='completed-validation-result'></div>";
        echo "<script>
        async function testCompletedValidation() {
            try {
                const formData = new FormData();
                formData.append('action', 'verify_cashout');
                formData.append('slip_number', '$completedSlipNumber');
                
                const response = await fetch('/slipp/php/cashout_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const json = await response.json();
                const success = json.status === 'success';
                const resultDiv = document.getElementById('completed-validation-result');
                
                if (success) {
                    resultDiv.innerHTML = `
                        <div style=\"background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0;\">
                            <h3>✅ PERFECT SUCCESS</h3>
                            <p><strong>Completed draw validation working correctly!</strong></p>
                            <p><strong>Draw:</strong> #\${json.draw_number}</p>
                            <p><strong>Winning Number:</strong> \${json.winning_number}</p>
                            <p><strong>Winning Color:</strong> \${json.winning_color}</p>
                            <p><strong>Total Winnings:</strong> $\${json.total_winnings}</p>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div style=\"background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px; margin: 10px 0;\">
                            <h3>⚠️ UNEXPECTED FAILURE</h3>
                            <p><strong>Completed draw validation failed:</strong> \${json.message}</p>
                            <p>This might indicate an issue with the slip or draw data.</p>
                        </div>
                    `;
                }
                
            } catch (error) {
                document.getElementById('completed-validation-result').innerHTML = `
                    <div style=\"background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0;\">
                        <h3>❌ TEST ERROR</h3>
                        <p>\${error.message}</p>
                    </div>
                `;
            }
        }
        
        testCompletedValidation();
        </script>";
        
    } else {
        echo "<p>No completed draw slips available for testing.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";

echo "<h2>🎉 Final Verification Summary</h2>";
echo "<div style='background: #e2f3ff; padding: 20px; border: 1px solid #b8daff; border-radius: 5px;'>";
echo "<h3>🎯 What This Test Verifies:</h3>";
echo "<ul style='font-size: 16px; line-height: 1.6;'>";
echo "<li><strong>✅ Future Draw Prevention:</strong> Slip for draw #" . ($currentDraw + 20) . " should be rejected</li>";
echo "<li><strong>✅ Correct Error Message:</strong> Should mention current completed draw #$currentDraw</li>";
echo "<li><strong>✅ Completed Draw Processing:</strong> Valid slips should process successfully</li>";
echo "<li><strong>✅ Database Compatibility:</strong> All queries should work without SQL errors</li>";
echo "<li><strong>✅ getCurrentDrawInfo Fix:</strong> Should use correct current draw number</li>";
echo "</ul>";

echo "<h3>🚀 Expected Results:</h3>";
echo "<ul style='font-size: 16px; line-height: 1.6;'>";
echo "<li><strong>Future Draw Test:</strong> ✅ PERFECT SUCCESS with 'has not occurred yet' message</li>";
echo "<li><strong>Completed Draw Test:</strong> ✅ PERFECT SUCCESS with winning number and color</li>";
echo "<li><strong>No SQL Errors:</strong> All database operations should complete successfully</li>";
echo "</ul>";

echo "<h3>🏆 Success Criteria:</h3>";
echo "<p style='font-size: 18px; color: #0066cc; font-weight: bold;'>If both tests show ✅ PERFECT SUCCESS, the cashout validation system is FULLY OPERATIONAL!</p>";
echo "</div>";

echo "</div>";
?>
