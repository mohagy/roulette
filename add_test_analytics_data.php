<?php
/**
 * Add Test Analytics Data
 * 
 * This script adds legitimate test data to the analytics table
 * to verify that the display functionality works correctly.
 */

// Initialize cache prevention
require_once 'php/cache_prevention.php';

// Include database connection
require_once 'php/db_connect.php';

echo "<!DOCTYPE html>";
echo "<html><head><title>Add Test Analytics Data</title>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} .warning{color:orange;}</style>";
echo "</head><body>";

echo "<h1>🧪 Add Test Analytics Data</h1>";

echo "<h2>📋 Purpose</h2>";
echo "<p>This script adds legitimate test data to verify that the analytics display works correctly while maintaining security.</p>";

try {
    // Start transaction
    $conn->autocommit(false);
    
    echo "<h2>Step 1: Create Test Spin Data</h2>";
    
    // Create realistic test data
    $testSpins = [25, 7, 14, 36, 0, 18, 29, 12]; // 8 test spins
    $testDrawNumber = count($testSpins);
    
    // Create frequency array
    $numberFrequency = array_fill(0, 37, 0);
    foreach ($testSpins as $spin) {
        $numberFrequency[$spin]++;
    }
    
    // Prepare data for database
    $allSpinsJson = json_encode($testSpins);
    $frequencyJson = json_encode($numberFrequency);
    
    echo "<p class='info'>Test spins: " . implode(', ', $testSpins) . "</p>";
    echo "<p class='info'>Total spins: " . count($testSpins) . "</p>";
    echo "<p class='info'>Current draw number: $testDrawNumber</p>";
    
    echo "<h2>Step 2: Update Analytics Table</h2>";
    
    // Update the analytics table with test data
    $stmt = $conn->prepare("UPDATE roulette_analytics SET all_spins = ?, number_frequency = ?, current_draw_number = ?, last_updated = NOW() WHERE id = 1");
    
    if ($stmt) {
        $stmt->bind_param("ssi", $allSpinsJson, $frequencyJson, $testDrawNumber);
        
        if ($stmt->execute()) {
            echo "<p class='success'>✅ Test analytics data added successfully</p>";
            
            // Log the test data addition
            logCachePrevention("Test analytics data added", [
                'spins' => $testSpins,
                'draw_number' => $testDrawNumber,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
        } else {
            throw new Exception("Failed to update analytics: " . $stmt->error);
        }
    } else {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    // Commit the changes
    $conn->commit();
    
    echo "<h2>Step 3: Verification</h2>";
    
    // Verify the data was added correctly
    $verifyData = getFreshData("SELECT SQL_NO_CACHE * FROM roulette_analytics WHERE id = 1");
    
    if (!empty($verifyData)) {
        $analytics = $verifyData[0];
        $verifySpins = json_decode($analytics['all_spins'], true);
        $verifyFrequency = json_decode($analytics['number_frequency'], true);
        
        echo "<table style='border-collapse:collapse;width:100%;'>";
        echo "<tr style='background:#f2f2f2;'><th style='border:1px solid #ddd;padding:8px;'>Property</th><th style='border:1px solid #ddd;padding:8px;'>Value</th><th style='border:1px solid #ddd;padding:8px;'>Status</th></tr>";
        
        // Verify spins
        $spinsMatch = ($verifySpins === $testSpins);
        echo "<tr><td style='border:1px solid #ddd;padding:8px;'>All Spins</td><td style='border:1px solid #ddd;padding:8px;'>" . htmlspecialchars($analytics['all_spins']) . "</td><td style='border:1px solid #ddd;padding:8px;' class='" . ($spinsMatch ? 'success' : 'error') . "'>" . ($spinsMatch ? '✅ Correct' : '❌ Mismatch') . "</td></tr>";
        
        // Verify draw number
        $drawMatch = ($analytics['current_draw_number'] == $testDrawNumber);
        echo "<tr><td style='border:1px solid #ddd;padding:8px;'>Current Draw</td><td style='border:1px solid #ddd;padding:8px;'>" . htmlspecialchars($analytics['current_draw_number']) . "</td><td style='border:1px solid #ddd;padding:8px;' class='" . ($drawMatch ? 'success' : 'error') . "'>" . ($drawMatch ? '✅ Correct' : '❌ Mismatch') . "</td></tr>";
        
        // Verify frequency
        $frequencyMatch = ($verifyFrequency === $numberFrequency);
        echo "<tr><td style='border:1px solid #ddd;padding:8px;'>Number Frequency</td><td style='border:1px solid #ddd;padding:8px;'>Array with " . count($verifyFrequency) . " entries</td><td style='border:1px solid #ddd;padding:8px;' class='" . ($frequencyMatch ? 'success' : 'error') . "'>" . ($frequencyMatch ? '✅ Correct' : '❌ Mismatch') . "</td></tr>";
        
        echo "<tr><td style='border:1px solid #ddd;padding:8px;'>Last Updated</td><td style='border:1px solid #ddd;padding:8px;'>" . htmlspecialchars($analytics['last_updated']) . "</td><td style='border:1px solid #ddd;padding:8px;' class='success'>✅ Fresh</td></tr>";
        
        echo "</table>";
        
        if ($spinsMatch && $drawMatch && $frequencyMatch) {
            echo "<h3 class='success'>✅ Test Data Successfully Added</h3>";
            echo "<p>The analytics table now contains legitimate test data that should be visible in the TV display.</p>";
        } else {
            echo "<h3 class='error'>❌ Data Verification Failed</h3>";
            echo "<p>There was an issue with the test data. Please check the database.</p>";
        }
        
    } else {
        throw new Exception("Could not verify test data - analytics table is empty");
    }
    
    echo "<h2>🎯 Next Steps</h2>";
    echo "<ol>";
    echo "<li><strong>Test TV Display:</strong> Open the TV display and verify the recent numbers show the test spins</li>";
    echo "<li><strong>Check Analytics Panel:</strong> Verify the analytics panel shows the correct data</li>";
    echo "<li><strong>Monitor Security:</strong> Ensure security measures are still active</li>";
    echo "<li><strong>Clear Test Data:</strong> Use the reset script when testing is complete</li>";
    echo "</ol>";
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo "<h2 class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</h2>";
    echo "<p class='error'>Test data addition failed. All changes have been rolled back.</p>";
} finally {
    // Restore autocommit
    $conn->autocommit(true);
}

echo "<h2>🔧 Actions</h2>";
echo "<button onclick='window.open(\"tvdisplay/index.html\", \"_blank\")' style='background:#28a745;color:white;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;margin:5px;'>🎮 Open TV Display</button>";
echo "<button onclick='window.open(\"test_analytics_display.php\", \"_blank\")' style='background:#007bff;color:white;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;margin:5px;'>🧪 Test Analytics</button>";
echo "<button onclick='window.open(\"analytics_monitor_dashboard.php\", \"_blank\")' style='background:#17a2b8;color:white;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;margin:5px;'>📊 Monitor Dashboard</button>";
echo "<button onclick='clearTestData()' style='background:#dc3545;color:white;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;margin:5px;'>🗑️ Clear Test Data</button>";

echo "<script>";
echo "function clearTestData() {";
echo "  if (confirm('Clear the test analytics data and reset to clean state?')) {";
echo "    window.open('secure_analytics_reset.php', '_blank');";
echo "  }";
echo "}";
echo "</script>";

echo "<p><a href='test_analytics_display.php'>← Test Analytics Display</a> | <a href='tvdisplay/index.html'>TV Display →</a></p>";
echo "</body></html>";
?>
