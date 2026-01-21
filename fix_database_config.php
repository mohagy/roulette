<?php
/**
 * Database Configuration Fix Script
 * This script ensures all database connections are properly configured to use the 'roulette' database
 */

echo "<h1>Database Configuration Fix</h1>";
echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto;'>";

// Step 1: Check current database status
echo "<h2>Step 1: Database Status Check</h2>";

try {
    $conn = new mysqli("localhost", "root", "", "roulette");

    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    echo "<p style='color: green;'>✓ Successfully connected to 'roulette' database</p>";

    // Check if required tables exist
    $required_tables = ['betting_slips', 'users', 'slip_details', 'bets', 'roulette_analytics'];
    $missing_tables = [];

    foreach ($required_tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            echo "<p style='color: green;'>✓ Table '$table' exists</p>";
        } else {
            echo "<p style='color: red;'>✗ Table '$table' missing</p>";
            $missing_tables[] = $table;
        }
    }

    if (empty($missing_tables)) {
        echo "<p style='color: green;'><strong>All required tables are present!</strong></p>";
    } else {
        echo "<p style='color: orange;'><strong>Missing tables: " . implode(', ', $missing_tables) . "</strong></p>";
    }

    $conn->close();

} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Database connection error: " . $e->getMessage() . "</p>";
}

// Step 2: Test cashout API
echo "<h2>Step 2: Cashout API Test</h2>";

// Create a simple test request
$test_url = 'http://localhost/slipp/php/cashout_api.php';
$test_data = array(
    'action' => 'verify_cashout',
    'slip_number' => 'NONEXISTENT123'
);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $test_url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($test_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    echo "<p style='color: red;'>✗ cURL Error: " . $curl_error . "</p>";
} else {
    echo "<p>HTTP Response Code: " . $http_code . "</p>";

    if ($http_code == 200) {
        $json_response = json_decode($response, true);
        if ($json_response) {
            echo "<p style='color: green;'>✓ Cashout API is responding with valid JSON</p>";
            echo "<p>Response: " . $json_response['message'] . "</p>";
        } else {
            echo "<p style='color: red;'>✗ API returned invalid JSON</p>";
            echo "<p>Raw response: " . htmlspecialchars(substr($response, 0, 200)) . "...</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ API returned HTTP error code: " . $http_code . "</p>";
    }
}

// Step 3: Create sample data for testing
echo "<h2>Step 3: Create Sample Data (Optional)</h2>";

if (isset($_POST['create_sample'])) {
    try {
        $conn = new mysqli("localhost", "root", "", "roulette");

        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }

        // Create sample user if none exists
        $userResult = $conn->query("SELECT user_id FROM users LIMIT 1");
        if ($userResult->num_rows == 0) {
            $conn->query("INSERT INTO users (username, password, role) VALUES ('testuser', 'test123', 'player')");
            echo "<p style='color: green;'>✓ Created sample user</p>";
        }

        // Get user ID
        $userResult = $conn->query("SELECT user_id FROM users LIMIT 1");
        $user = $userResult->fetch_assoc();
        $userId = $user['user_id'];

        // Create sample betting slip
        $slipNumber = 'TEST' . time();
        $stmt = $conn->prepare("INSERT INTO betting_slips (slip_number, user_id, total_stake, potential_payout, draw_number) VALUES (?, ?, 10.00, 20.00, 1)");
        $stmt->bind_param("si", $slipNumber, $userId);
        $stmt->execute();
        $slipId = $conn->insert_id;
        $stmt->close();

        echo "<p style='color: green;'>✓ Created sample betting slip: <strong>$slipNumber</strong></p>";

        // Create sample bet
        $conn->query("INSERT INTO bets (user_id, bet_type, bet_description, bet_amount, multiplier, potential_return) VALUES ($userId, 'straight', 'Straight Up on 7', 10.00, 35.00, 350.00)");
        $betId = $conn->insert_id;

        // Link bet to slip
        $conn->query("INSERT INTO slip_details (slip_id, bet_id) VALUES ($slipId, $betId)");

        echo "<p style='color: green;'>✓ Created sample bet and linked to slip</p>";
        echo "<p><strong>Test slip number: $slipNumber</strong></p>";

        $conn->close();

    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Error creating sample data: " . $e->getMessage() . "</p>";
    }
}

echo '<form method="post">
    <button type="submit" name="create_sample" value="1" style="background: #2ecc71; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">Create Sample Data for Testing</button>
</form>';

// Step 4: Instructions
echo "<h2>Step 4: Next Steps</h2>";
echo "<ul>";
echo "<li>If all tests above passed, the cashout functionality should be working</li>";
echo "<li>If you created sample data, you can test the cashout with the generated slip number</li>";
echo "<li>Open the main cashier interface at <a href='index.php' target='_blank'>index.php</a> and try the cashout function</li>";
echo "<li>Check the browser console for any JavaScript errors</li>";
echo "</ul>";

echo "</div>";
?>
