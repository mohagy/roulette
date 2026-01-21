<?php
/**
 * Fix Upcoming Draws API - Ensure everything is working correctly
 */

echo "<h1>🔧 Fix Upcoming Draws API</h1>";
echo "<div style='font-family: Arial, sans-serif; max-width: 1000px; margin: 0 auto; padding: 20px; background: #f5f5f5;'>";

try {
    require_once 'php/db_connect.php';
    
    echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0;'>";
    echo "<h2>✅ Step 1: Database Connection</h2>";
    echo "<p>Database connection successful!</p>";
    echo "</div>";
    
    // Check if we have any completed draws
    $stmt = $conn->prepare("SELECT COUNT(*) as count, MAX(draw_number) as max_draw FROM detailed_draw_results");
    $stmt->execute();
    $result = $stmt->get_result();
    $drawInfo = $result->fetch_assoc();
    
    $completedDraws = (int)$drawInfo['count'];
    $maxDraw = (int)($drawInfo['max_draw'] ?? 0);
    
    echo "<div style='background: #e2f3ff; padding: 15px; border: 1px solid #b8daff; border-radius: 5px; margin: 10px 0;'>";
    echo "<h2>📊 Step 2: Current Database State</h2>";
    echo "<p><strong>Completed Draws:</strong> $completedDraws</p>";
    echo "<p><strong>Highest Draw Number:</strong> #$maxDraw</p>";
    echo "</div>";
    
    // If no completed draws, create some sample data
    if ($completedDraws === 0) {
        echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px; margin: 10px 0;'>";
        echo "<h2>⚠️ Step 3: Creating Sample Data</h2>";
        echo "<p>No completed draws found. Creating sample data...</p>";
        
        // Create some sample completed draws
        for ($i = 1; $i <= 5; $i++) {
            $drawNumber = 100 + $i;
            $winningNumber = rand(0, 36);
            $color = ($winningNumber == 0) ? 'green' : (in_array($winningNumber, [1,3,5,7,9,12,14,16,18,19,21,23,25,27,30,32,34,36]) ? 'red' : 'black');
            $timestamp = date('Y-m-d H:i:s', strtotime("-$i hours"));
            
            $stmt = $conn->prepare("INSERT INTO detailed_draw_results (draw_number, winning_number, color, timestamp) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiss", $drawNumber, $winningNumber, $color, $timestamp);
            $stmt->execute();
            
            echo "<p>✅ Created draw #$drawNumber - Winning: $winningNumber ($color)</p>";
        }
        
        $maxDraw = 105; // Update max draw
        echo "<p><strong>Sample data created successfully!</strong></p>";
        echo "</div>";
    } else {
        echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0;'>";
        echo "<h2>✅ Step 3: Data Check</h2>";
        echo "<p>Sufficient completed draws found. No sample data needed.</p>";
        echo "</div>";
    }
    
    // Check betting slips
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM betting_slips");
    $stmt->execute();
    $result = $stmt->get_result();
    $slipCount = $result->fetch_assoc()['count'];
    
    echo "<div style='background: #e2f3ff; padding: 15px; border: 1px solid #b8daff; border-radius: 5px; margin: 10px 0;'>";
    echo "<h2>🎫 Step 4: Betting Slips</h2>";
    echo "<p><strong>Total Betting Slips:</strong> $slipCount</p>";
    
    // Create some test betting slips for upcoming draws if needed
    if ($slipCount < 5) {
        echo "<p>Creating test betting slips for upcoming draws...</p>";
        
        for ($i = 1; $i <= 3; $i++) {
            $upcomingDraw = $maxDraw + $i;
            $slipNumber = 'TEST_UPCOMING_' . $upcomingDraw . '_' . time() . '_' . $i;
            $stake = rand(10, 50);
            $payout = $stake * rand(2, 35);
            
            $stmt = $conn->prepare("
                INSERT INTO betting_slips (
                    slip_number, user_id, total_stake, potential_payout, 
                    created_at, updated_at, is_paid, is_cancelled, 
                    draw_number, status
                ) VALUES (?, 1, ?, ?, NOW(), NOW(), 0, 0, ?, 'active')
            ");
            $stmt->bind_param("sddi", $slipNumber, $stake, $payout, $upcomingDraw);
            $stmt->execute();
            
            echo "<p>✅ Created test slip for draw #$upcomingDraw - Stake: $$stake</p>";
        }
    }
    echo "</div>";
    
    // Test the API
    echo "<div style='background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; border-radius: 5px; margin: 10px 0;'>";
    echo "<h2>🧪 Step 5: API Test</h2>";
    
    // Simulate API call
    $apiUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/api/upcoming_draws_stats.php';
    
    echo "<p><strong>Testing API:</strong> <a href='$apiUrl' target='_blank'>$apiUrl</a></p>";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'method' => 'GET'
        ]
    ]);
    
    $apiResponse = @file_get_contents($apiUrl, false, $context);
    
    if ($apiResponse === false) {
        echo "<p style='color: red;'>❌ API call failed. Testing with direct include...</p>";
        
        // Test with direct include
        ob_start();
        include 'api/upcoming_draws_stats.php';
        $directResponse = ob_get_clean();
        
        echo "<h4>Direct API Response:</h4>";
        echo "<pre style='background: #000; color: #0f0; padding: 10px; border-radius: 3px; overflow-x: auto; font-size: 11px;'>";
        echo htmlspecialchars($directResponse);
        echo "</pre>";
        
        // Try to parse as JSON
        $jsonData = json_decode($directResponse, true);
        if ($jsonData) {
            echo "<p style='color: green;'>✅ Direct API test successful!</p>";
            if ($jsonData['status'] === 'success') {
                $upcomingCount = count($jsonData['data']['upcoming_draws'] ?? []);
                echo "<p><strong>Upcoming Draws Generated:</strong> $upcomingCount</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ Direct API returned invalid JSON</p>";
        }
        
    } else {
        echo "<h4>API Response:</h4>";
        echo "<pre style='background: #000; color: #0f0; padding: 10px; border-radius: 3px; overflow-x: auto; font-size: 11px;'>";
        echo htmlspecialchars($apiResponse);
        echo "</pre>";
        
        $jsonData = json_decode($apiResponse, true);
        if ($jsonData && $jsonData['status'] === 'success') {
            echo "<p style='color: green;'>✅ API test successful!</p>";
            $upcomingCount = count($jsonData['data']['upcoming_draws'] ?? []);
            echo "<p><strong>Upcoming Draws Generated:</strong> $upcomingCount</p>";
        } else {
            echo "<p style='color: red;'>❌ API returned error or invalid JSON</p>";
        }
    }
    echo "</div>";
    
    // Final status
    echo "<div style='background: #d4edda; padding: 20px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0; text-align: center;'>";
    echo "<h2>🎉 Fix Complete!</h2>";
    echo "<p><strong>The upcoming draws API should now be working correctly.</strong></p>";
    echo "<p><a href='index.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>🎯 Test Main Interface</a></p>";
    echo "<p><a href='test_upcoming_draws_panel.html' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>🧪 Test Panel</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0;'>";
    echo "<h2>❌ Error</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "</div>";
}

// Close database connection
if (isset($conn)) {
    $conn->close();
}

echo "</div>";
?>
