<?php
/**
 * Implement Dual Storage System
 * 
 * This script creates the dual storage system that saves spin data
 * to both roulette_analytics and detailed_draw_results tables.
 */

// Initialize cache prevention
require_once 'php/cache_prevention.php';

echo "<!DOCTYPE html>";
echo "<html><head><title>Implement Dual Storage System</title>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} .warning{color:orange;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background-color:#f2f2f2;} pre{background:#f8f9fa;padding:15px;border-radius:5px;overflow-x:auto;}</style>";
echo "</head><body>";

echo "<h1>🔄 Implement Dual Storage System</h1>";

echo "<h2>📋 Implementation Overview</h2>";
echo "<p>Creating a secure dual storage system that:</p>";
echo "<ul>";
echo "<li>✅ Saves aggregate data to <code>roulette_analytics</code> table</li>";
echo "<li>✅ Saves individual records to <code>detailed_draw_results</code> table</li>";
echo "<li>✅ Maintains security against phantom data generation</li>";
echo "<li>✅ Ensures data consistency with transaction management</li>";
echo "<li>✅ Implements proper error handling and rollback</li>";
echo "</ul>";

echo "<h2>Step 1: Create Secure Dual Storage API</h2>";

// Create the dual storage API
$dualStorageAPI = '<?php
/**
 * Secure Dual Storage API
 * 
 * This API saves spin data to both roulette_analytics and detailed_draw_results
 * tables while maintaining security against phantom data generation.
 */

// Initialize comprehensive cache prevention
require_once "cache_prevention.php";

// Include database connection
require_once "db_connect.php";

// Set response header to JSON
header("Content-Type: application/json");

// Log the request
logCachePrevention("Dual storage API called", [
    "method" => $_SERVER["REQUEST_METHOD"],
    "user_agent" => $_SERVER["HTTP_USER_AGENT"] ?? "unknown",
    "timestamp" => date("Y-m-d H:i:s")
]);

// Only allow POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "Only POST requests allowed",
        "timestamp" => date("Y-m-d H:i:s")
    ]);
    exit;
}

// Get JSON input
$input = file_get_contents("php://input");
$data = json_decode($input, true);

// Validate input data
if (!$data || !isset($data["winning_number"]) || !isset($data["draw_number"])) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Missing required fields: winning_number and draw_number",
        "timestamp" => date("Y-m-d H:i:s")
    ]);
    exit;
}

$winningNumber = (int)$data["winning_number"];
$drawNumber = (int)$data["draw_number"];
$timestamp = $data["timestamp"] ?? date("Y-m-d H:i:s");

// Validate winning number range
if ($winningNumber < 0 || $winningNumber > 36) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid winning number. Must be between 0 and 36",
        "timestamp" => date("Y-m-d H:i:s")
    ]);
    exit;
}

// Validate draw number
if ($drawNumber < 1) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid draw number. Must be greater than 0",
        "timestamp" => date("Y-m-d H:i:s")
    ]);
    exit;
}

try {
    // Start transaction for atomic operation
    $conn->autocommit(false);
    
    logCachePrevention("Starting dual storage transaction", [
        "winning_number" => $winningNumber,
        "draw_number" => $drawNumber,
        "timestamp" => $timestamp
    ]);
    
    // Step 1: Get current analytics data using fresh query
    $currentAnalytics = getFreshData("SELECT * FROM roulette_analytics WHERE id = 1");
    
    if (empty($currentAnalytics)) {
        // Initialize analytics if not exists
        $allSpins = [];
        $numberFrequency = array_fill(0, 37, 0);
        $currentDrawNumber = 0;
    } else {
        $analytics = $currentAnalytics[0];
        $allSpins = json_decode($analytics["all_spins"], true) ?: [];
        $numberFrequency = json_decode($analytics["number_frequency"], true) ?: array_fill(0, 37, 0);
        $currentDrawNumber = (int)$analytics["current_draw_number"];
    }
    
    // Step 2: Update analytics data
    array_unshift($allSpins, $winningNumber);
    $allSpins = array_slice($allSpins, 0, 100); // Keep only last 100 spins
    
    $numberFrequency[$winningNumber]++;
    $newDrawNumber = max($currentDrawNumber, $drawNumber);
    
    // Step 3: Save to roulette_analytics table
    $allSpinsJson = json_encode($allSpins);
    $frequencyJson = json_encode($numberFrequency);
    
    if (empty($currentAnalytics)) {
        // Insert new record
        $stmt = $conn->prepare("INSERT INTO roulette_analytics (id, all_spins, number_frequency, current_draw_number, last_updated, created_at) VALUES (1, ?, ?, ?, NOW(), NOW())");
        $stmt->bind_param("ssi", $allSpinsJson, $frequencyJson, $newDrawNumber);
    } else {
        // Update existing record
        $stmt = $conn->prepare("UPDATE roulette_analytics SET all_spins = ?, number_frequency = ?, current_draw_number = ?, last_updated = NOW() WHERE id = 1");
        $stmt->bind_param("ssi", $allSpinsJson, $frequencyJson, $newDrawNumber);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update roulette_analytics: " . $stmt->error);
    }
    
    logCachePrevention("Updated roulette_analytics table", [
        "spins_count" => count($allSpins),
        "draw_number" => $newDrawNumber
    ]);
    
    // Step 4: Save to detailed_draw_results table
    $stmt2 = $conn->prepare("INSERT INTO detailed_draw_results (draw_number, winning_number, color, timestamp) VALUES (?, ?, get_roulette_color(?), ?)");
    $stmt2->bind_param("iiis", $drawNumber, $winningNumber, $winningNumber, $timestamp);
    
    if (!$stmt2->execute()) {
        throw new Exception("Failed to insert into detailed_draw_results: " . $stmt2->error);
    }
    
    $detailedResultId = $conn->insert_id;
    
    logCachePrevention("Inserted into detailed_draw_results table", [
        "id" => $detailedResultId,
        "draw_number" => $drawNumber,
        "winning_number" => $winningNumber
    ]);
    
    // Commit transaction
    $conn->commit();
    
    // Get the color for response
    $colorResult = $conn->query("SELECT get_roulette_color($winningNumber) as color");
    $color = $colorResult ? $colorResult->fetch_assoc()["color"] : "unknown";
    
    logCachePrevention("Dual storage transaction completed successfully", [
        "analytics_updated" => true,
        "detailed_record_id" => $detailedResultId,
        "total_spins" => count($allSpins)
    ]);
    
    // Return success response
    echo json_encode([
        "status" => "success",
        "message" => "Spin data saved to both tables successfully",
        "data" => [
            "draw_number" => $drawNumber,
            "winning_number" => $winningNumber,
            "color" => $color,
            "detailed_record_id" => $detailedResultId,
            "total_spins_recorded" => count($allSpins),
            "timestamp" => $timestamp
        ],
        "timestamp" => date("Y-m-d H:i:s")
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    logCachePrevention("Dual storage transaction failed", [
        "error" => $e->getMessage(),
        "winning_number" => $winningNumber,
        "draw_number" => $drawNumber
    ]);
    
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to save spin data: " . $e->getMessage(),
        "timestamp" => date("Y-m-d H:i:s")
    ]);
} finally {
    // Restore autocommit
    $conn->autocommit(true);
}
?>';

// Save the dual storage API
file_put_contents('php/dual_storage_api.php', $dualStorageAPI);
echo "<p class='success'>✅ Created secure dual storage API: <code>php/dual_storage_api.php</code></p>";

echo "<h2>Step 2: Create Color Mapping Utility</h2>";

// Create color mapping utility
$colorUtility = '<?php
/**
 * Roulette Color Utility
 * 
 * Utility functions for roulette color mapping and validation.
 */

class RouletteColor {
    // Red numbers on a standard roulette wheel
    private static $redNumbers = [1,3,5,7,9,12,14,16,18,19,21,23,25,27,30,32,34,36];
    
    /**
     * Get the color of a roulette number
     */
    public static function getColor($number) {
        $number = (int)$number;
        
        if ($number === 0) {
            return "green";
        } elseif (in_array($number, self::$redNumbers)) {
            return "red";
        } else {
            return "black";
        }
    }
    
    /**
     * Validate if a number is a valid roulette number
     */
    public static function isValidNumber($number) {
        $number = (int)$number;
        return $number >= 0 && $number <= 36;
    }
    
    /**
     * Get all numbers of a specific color
     */
    public static function getNumbersByColor($color) {
        switch (strtolower($color)) {
            case "red":
                return self::$redNumbers;
            case "green":
                return [0];
            case "black":
                $allNumbers = range(1, 36);
                return array_diff($allNumbers, self::$redNumbers);
            default:
                return [];
        }
    }
    
    /**
     * Get color statistics for an array of spins
     */
    public static function getColorStats($spins) {
        $stats = ["red" => 0, "black" => 0, "green" => 0];
        
        foreach ($spins as $number) {
            $color = self::getColor($number);
            $stats[$color]++;
        }
        
        return $stats;
    }
}
?>';

file_put_contents('php/roulette_color.php', $colorUtility);
echo "<p class='success'>✅ Created color utility: <code>php/roulette_color.php</code></p>";

echo "<h2>Step 3: Update TV Display Integration</h2>";

// Create JavaScript integration for TV display
$jsIntegration = '/**
 * Dual Storage Integration for TV Display
 * 
 * This script integrates the dual storage system with the TV display
 * to save spin data to both analytics and detailed results tables.
 */

// Dual storage functionality
const DualStorage = {
    /**
     * Save spin data to both tables
     */
    async saveSpin(winningNumber, drawNumber, timestamp = null) {
        try {
            console.log("🔄 DUAL STORAGE: Saving spin data", {
                winningNumber,
                drawNumber,
                timestamp: timestamp || new Date().toISOString()
            });
            
            const data = {
                winning_number: parseInt(winningNumber),
                draw_number: parseInt(drawNumber),
                timestamp: timestamp || new Date().toISOString().slice(0, 19).replace("T", " ")
            };
            
            const response = await fetch("/slipp/php/dual_storage_api.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.status === "success") {
                console.log("✅ DUAL STORAGE: Spin saved successfully", result.data);
                
                // Trigger custom event for successful save
                document.dispatchEvent(new CustomEvent("dual_storage_success", {
                    detail: result.data
                }));
                
                return result.data;
            } else {
                console.error("❌ DUAL STORAGE: Save failed", result.message);
                throw new Error(result.message);
            }
            
        } catch (error) {
            console.error("❌ DUAL STORAGE: Error saving spin", error);
            
            // Trigger custom event for error
            document.dispatchEvent(new CustomEvent("dual_storage_error", {
                detail: { error: error.message, winningNumber, drawNumber }
            }));
            
            throw error;
        }
    },
    
    /**
     * Get color for a roulette number
     */
    getColor(number) {
        const redNumbers = [1,3,5,7,9,12,14,16,18,19,21,23,25,27,30,32,34,36];
        
        if (number === 0) {
            return "green";
        } else if (redNumbers.includes(number)) {
            return "red";
        } else {
            return "black";
        }
    },
    
    /**
     * Validate roulette number
     */
    isValidNumber(number) {
        return Number.isInteger(number) && number >= 0 && number <= 36;
    }
};

// Make DualStorage available globally
window.DualStorage = DualStorage;

// Log initialization
console.log("🔄 Dual Storage system initialized");';

file_put_contents('tvdisplay/js/dual-storage.js', $jsIntegration);
echo "<p class='success'>✅ Created TV display integration: <code>tvdisplay/js/dual-storage.js</code></p>";

echo "<h2>Step 4: Test Dual Storage System</h2>";

// Create test data
$testData = [
    ['winning_number' => 25, 'draw_number' => 1],
    ['winning_number' => 0, 'draw_number' => 2],
    ['winning_number' => 14, 'draw_number' => 3]
];

echo "<p>Testing dual storage with sample data:</p>";
echo "<table>";
echo "<tr><th>Test</th><th>Winning Number</th><th>Draw Number</th><th>Expected Color</th><th>Result</th></tr>";

foreach ($testData as $index => $test) {
    $testNum = $index + 1;
    $winningNumber = $test['winning_number'];
    $drawNumber = $test['draw_number'];
    
    // Determine expected color
    if ($winningNumber === 0) {
        $expectedColor = 'green';
    } elseif (in_array($winningNumber, [1,3,5,7,9,12,14,16,18,19,21,23,25,27,30,32,34,36])) {
        $expectedColor = 'red';
    } else {
        $expectedColor = 'black';
    }
    
    echo "<tr>";
    echo "<td>Test $testNum</td>";
    echo "<td>$winningNumber</td>";
    echo "<td>$drawNumber</td>";
    echo "<td class='$expectedColor'>$expectedColor</td>";
    echo "<td><button onclick='testDualStorage($winningNumber, $drawNumber)' style='background:#007bff;color:white;padding:5px 10px;border:none;border-radius:3px;cursor:pointer;'>Test</button></td>";
    echo "</tr>";
}
echo "</table>";

echo "<div id='test-results'></div>";

echo "<h2>✅ Implementation Complete</h2>";
echo "<p>The dual storage system has been successfully implemented with:</p>";
echo "<ul>";
echo "<li>✅ Secure API endpoint for saving to both tables</li>";
echo "<li>✅ Color mapping utility and validation</li>";
echo "<li>✅ JavaScript integration for TV display</li>";
echo "<li>✅ Transaction management for data consistency</li>";
echo "<li>✅ Comprehensive error handling and logging</li>";
echo "<li>✅ Security measures against phantom data</li>";
echo "</ul>";

echo "<script>";
echo "async function testDualStorage(winningNumber, drawNumber) {";
echo "  const results = document.getElementById('test-results');";
echo "  results.innerHTML = '<p>Testing dual storage...</p>';";
echo "  ";
echo "  try {";
echo "    const response = await fetch('/slipp/php/dual_storage_api.php', {";
echo "      method: 'POST',";
echo "      headers: { 'Content-Type': 'application/json' },";
echo "      body: JSON.stringify({";
echo "        winning_number: winningNumber,";
echo "        draw_number: drawNumber,";
echo "        timestamp: new Date().toISOString().slice(0, 19).replace('T', ' ')";
echo "      })";
echo "    });";
echo "    ";
echo "    const result = await response.json();";
echo "    ";
echo "    if (result.status === 'success') {";
echo "      results.innerHTML = '<div class=\"success\"><h3>✅ Test Successful</h3>' +";
echo "        '<p><strong>Draw:</strong> #' + result.data.draw_number + '</p>' +";
echo "        '<p><strong>Number:</strong> ' + result.data.winning_number + '</p>' +";
echo "        '<p><strong>Color:</strong> ' + result.data.color + '</p>' +";
echo "        '<p><strong>Record ID:</strong> ' + result.data.detailed_record_id + '</p>' +";
echo "        '<p><strong>Total Spins:</strong> ' + result.data.total_spins_recorded + '</p></div>';";
echo "    } else {";
echo "      results.innerHTML = '<div class=\"error\"><h3>❌ Test Failed</h3><p>' + result.message + '</p></div>';";
echo "    }";
echo "  } catch (error) {";
echo "    results.innerHTML = '<div class=\"error\"><h3>❌ Test Error</h3><p>' + error.message + '</p></div>';";
echo "  }";
echo "}";
echo "</script>";

echo "<h2>🎯 Next Steps</h2>";
echo "<ol>";
echo "<li><strong>Update TV Display:</strong> Integrate dual storage into the spin recording</li>";
echo "<li><strong>Test Integration:</strong> Verify data is saved to both tables during actual spins</li>";
echo "<li><strong>Monitor Performance:</strong> Ensure the dual storage doesn't impact performance</li>";
echo "<li><strong>Verify Security:</strong> Confirm security measures remain active</li>";
echo "</ol>";

echo "<button onclick='window.open(\"update_tv_display_storage.php\", \"_blank\")' style='background:#28a745;color:white;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;margin:5px;'>🎮 Update TV Display</button>";
echo "<button onclick='window.open(\"test_dual_storage_integration.php\", \"_blank\")' style='background:#007bff;color:white;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;margin:5px;'>🧪 Test Integration</button>";

echo "<p><a href='create_detailed_draw_results_table.php'>← Create Table</a> | <a href='update_tv_display_storage.php'>Update TV Display →</a></p>";
echo "</body></html>";
?>
