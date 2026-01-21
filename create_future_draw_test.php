<?php
/**
 * Create a test betting slip for a future draw to test validation
 */

echo "<h1>Create Future Draw Test Slip</h1>";
echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto;'>";

try {
    require_once 'php/db_connect.php';

    // Get current draw number
    $stmt = $conn->prepare("SELECT current_draw_number FROM roulette_analytics WHERE id = 1");
    $stmt->execute();
    $result = $stmt->get_result();

    $currentDraw = 100; // Default
    if ($result->num_rows > 0) {
        $analytics = $result->fetch_assoc();
        $currentDraw = $analytics['current_draw_number'];
    }

    $futureDraw = $currentDraw + 5; // Create a slip for 5 draws in the future

    echo "<p><strong>Current Draw:</strong> #$currentDraw</p>";
    echo "<p><strong>Creating test slip for future draw:</strong> #$futureDraw</p>";

    // Create a test betting slip for the future draw with unique number
    $slipNumber = 'FUTURE_TEST_' . time() . '_' . rand(1000, 9999);

    // Check if slip number already exists and generate a new one if needed
    $checkStmt = $conn->prepare("SELECT slip_id FROM betting_slips WHERE slip_number = ?");
    $checkStmt->bind_param("s", $slipNumber);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    while ($checkResult->num_rows > 0) {
        $slipNumber = 'FUTURE_TEST_' . time() . '_' . rand(1000, 9999);
        $checkStmt->bind_param("s", $slipNumber);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
    }
    $checkStmt->close();

    // Insert the betting slip
    $stmt = $conn->prepare("
        INSERT INTO betting_slips (
            slip_number, user_id, total_stake, potential_payout,
            created_at, updated_at, is_paid, is_cancelled,
            draw_number, status
        ) VALUES (?, 1, 10.00, 350.00, NOW(), NOW(), 0, 0, ?, 'active')
    ");
    $stmt->bind_param("si", $slipNumber, $futureDraw);

    if ($stmt->execute()) {
        $slipId = $conn->insert_id;
        echo "<p style='color: green;'><strong>✓ Test slip created successfully!</strong></p>";
        echo "<p><strong>Slip Number:</strong> $slipNumber</p>";
        echo "<p><strong>Slip ID:</strong> $slipId</p>";
        echo "<p><strong>Draw Number:</strong> #$futureDraw</p>";

        // Check the actual structure of slip_details table first
        $tableCheck = $conn->query("DESCRIBE slip_details");
        $columns = [];
        if ($tableCheck) {
            while ($row = $tableCheck->fetch_assoc()) {
                $columns[] = $row['Field'];
            }
        }

        echo "<p><strong>Available columns in slip_details:</strong> " . implode(', ', $columns) . "</p>";

        // The slip_details table is just a linking table (slip_id, bet_id)
        // We need to create a bet first, then link it to the slip

        // Step 1: Create a bet in the bets table
        $stmt2 = $conn->prepare("
            INSERT INTO bets (
                user_id, bet_type, bet_description,
                bet_amount, multiplier, potential_return, created_at
            ) VALUES (1, 'straight', 'Straight Up on 7', 10.00, 35.00, 350.00, NOW())
        ");

        if ($stmt2->execute()) {
            $betId = $conn->insert_id;
            echo "<p><strong>✓ Test bet created with ID: $betId</strong></p>";

            // Step 2: Link the bet to the slip via slip_details
            $stmt3 = $conn->prepare("
                INSERT INTO slip_details (slip_id, bet_id) VALUES (?, ?)
            ");
            $stmt3->bind_param("ii", $slipId, $betId);

            if ($stmt3->execute()) {
                echo "<p><strong>✓ Test bet linked to slip</strong></p>";
            } else {
                echo "<p style='color: red;'><strong>✗ Failed to link bet to slip:</strong> " . $conn->error . "</p>";
            }
            $stmt3->close();

        } else {
            echo "<p style='color: red;'><strong>✗ Failed to create test bet:</strong> " . $conn->error . "</p>";
        }
        $stmt2->close();

        // Now test the cashout validation
        echo "<h2>Testing Cashout Validation</h2>";
        echo "<button onclick='testFutureSlipCashout(\"$slipNumber\", $futureDraw)' style='padding: 10px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer;'>Test Future Draw Cashout (Should Fail)</button>";

        echo "<div id='test-result' style='margin-top: 20px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; display: none;'></div>";

        echo '<script>
        async function testFutureSlipCashout(slipNumber, drawNumber) {
            const resultDiv = document.getElementById("test-result");
            resultDiv.style.display = "block";
            resultDiv.innerHTML = "Testing future draw cashout...";

            try {
                const formData = new FormData();
                formData.append("action", "verify_cashout");
                formData.append("slip_number", slipNumber);

                const response = await fetch("/slipp/php/cashout_api.php", {
                    method: "POST",
                    body: formData
                });

                const text = await response.text();
                console.log("Response:", text);

                try {
                    const json = JSON.parse(text);

                    let resultHtml = `<h3>Testing Future Draw Slip: ${slipNumber} (Draw #${drawNumber})</h3>`;

                    if (json.status === "success") {
                        resultHtml += `<p style="color: red;"><strong>✗ UNEXPECTED SUCCESS</strong></p>`;
                        resultHtml += `<p><em>This should have failed because it\'s a future draw!</em></p>`;
                        resultHtml += `<p>Draw: #${json.draw_number}</p>`;
                        resultHtml += `<p>Winning Number: ${json.winning_number}</p>`;
                    } else {
                        resultHtml += `<p style="color: green;"><strong>✓ CORRECTLY FAILED</strong></p>`;
                        resultHtml += `<p><strong>Error Message:</strong> ${json.message}</p>`;

                        if (json.message.includes("has not occurred yet") || json.message.includes("not been completed yet")) {
                            resultHtml += `<p style="color: green;"><em>✓ Perfect! Future draw validation is working correctly</em></p>`;
                        } else {
                            resultHtml += `<p style="color: orange;"><em>Different error (may still be correct)</em></p>`;
                        }
                    }

                    resultDiv.innerHTML = resultHtml;

                } catch (parseError) {
                    resultDiv.innerHTML = `
                        <h3>Testing Future Draw Slip: ${slipNumber} (Draw #${drawNumber})</h3>
                        <p style="color: red;"><strong>✗ PARSE ERROR</strong></p>
                        <p>Invalid JSON response:</p>
                        <pre style="background: white; padding: 10px; border: 1px solid #ccc;">${text}</pre>
                    `;
                }

            } catch (error) {
                resultDiv.innerHTML = `
                    <h3>Testing Future Draw Slip: ${slipNumber} (Draw #${drawNumber})</h3>
                    <p style="color: red;"><strong>✗ NETWORK ERROR</strong></p>
                    <p>${error.message}</p>
                `;
            }
        }
        </script>';

    } else {
        echo "<p style='color: red;'><strong>✗ Failed to create test slip</strong></p>";
        echo "<p>Error: " . $conn->error . "</p>";
    }

    $stmt->close();

} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";

echo "<h2>Summary</h2>";
echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px;'>";
echo "<h3>What This Test Does:</h3>";
echo "<ul>";
echo "<li>Creates a betting slip for a future draw (#$futureDraw)</li>";
echo "<li>Tests the cashout validation to ensure it properly rejects future draws</li>";
echo "<li>Verifies that the error message is appropriate and informative</li>";
echo "</ul>";

echo "<h3>Expected Result:</h3>";
echo "<ul>";
echo "<li style='color: green;'><strong>✓ Should FAIL</strong> with message: 'This draw (#$futureDraw) has not occurred yet'</li>";
echo "<li style='color: green;'><strong>✓ Should mention current completed draw</strong></li>";
echo "<li style='color: green;'><strong>✓ Should instruct user to wait</strong></li>";
echo "</ul>";
echo "</div>";

echo "</div>";
?>
