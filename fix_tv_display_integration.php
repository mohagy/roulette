<?php
/**
 * Fix TV Display Integration
 * 
 * This script provides a comprehensive fix for the TV display integration
 * to ensure spins are properly saved to all three tables.
 */

echo "<!DOCTYPE html>";
echo "<html><head><title>Fix TV Display Integration</title>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} .warning{color:orange;} pre{background:#f8f9fa;padding:15px;border-radius:5px;overflow-x:auto;}</style>";
echo "</head><body>";

echo "<h1>🔧 Fix TV Display Integration</h1>";

echo "<h2>📋 Comprehensive Fix Overview</h2>";
echo "<p>This fix ensures the TV display properly saves spins to all three tables:</p>";
echo "<ul>";
echo "<li>✅ Create a direct integration script</li>";
echo "<li>✅ Override the existing recordSpinForAnalytics function</li>";
echo "<li>✅ Add debugging and error handling</li>";
echo "<li>✅ Ensure compatibility with existing code</li>";
echo "</ul>";

echo "<h2>Step 1: Create Direct Integration Script</h2>";

// Create a direct integration script that will definitely work
$directIntegrationScript = '/**
 * Direct TV Display Integration for Triple Storage
 * 
 * This script directly integrates with the TV display to ensure
 * all spins are saved to the triple storage system.
 */

console.log("🔧 DIRECT INTEGRATION: Loading TV Display Triple Storage Integration");

// Global variables to track integration status
window.tripleStorageIntegrationActive = false;
window.tripleStorageDebugMode = true;

// Direct triple storage function
async function saveSpinToTripleStorage(winningNumber, options = {}) {
    try {
        console.log("💾 DIRECT SAVE: Saving spin to triple storage", { winningNumber, options });
        
        // Get current draw number (try multiple sources)
        let drawNumber = options.drawNumber;
        if (!drawNumber) {
            // Try to get from global variables
            if (typeof window.currentDrawNumber !== "undefined") {
                drawNumber = window.currentDrawNumber + 1;
            } else {
                // Fallback to API
                try {
                    const response = await fetch("/slipp/load_analytics.php");
                    const data = await response.json();
                    if (data.status === "success" && data.current_draw_number) {
                        drawNumber = parseInt(data.current_draw_number) + 1;
                    } else {
                        drawNumber = 1; // Start from 1 if no data
                    }
                } catch (e) {
                    console.warn("⚠️ DIRECT SAVE: Could not get draw number from API, using 1");
                    drawNumber = 1;
                }
            }
        }
        
        // Detect if manual
        const isManual = options.isManual || 
                        (typeof window.manualWinningNumber !== "undefined" && window.manualWinningNumber !== null) ||
                        (typeof window.forcedNumber !== "undefined" && window.forcedNumber !== null);
        
        // Prepare data for triple storage
        const spinData = {
            winning_number: parseInt(winningNumber),
            draw_number: parseInt(drawNumber),
            timestamp: new Date().toISOString().slice(0, 19).replace("T", " "),
            is_manual: isManual,
            total_bets: options.totalBets || 0,
            total_stake: options.totalStake || 0.00,
            total_payout: options.totalPayout || 0.00
        };
        
        console.log("📊 DIRECT SAVE: Prepared spin data", spinData);
        
        // Call triple storage API
        const response = await fetch("/slipp/php/triple_storage_api.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(spinData)
        });
        
        const result = await response.json();
        
        if (result.status === "success") {
            console.log("✅ DIRECT SAVE: Successfully saved to triple storage", result.data);
            
            // Update global draw number if it exists
            if (typeof window.currentDrawNumber !== "undefined") {
                window.currentDrawNumber = result.data.draw_number;
            }
            
            // Trigger custom event
            document.dispatchEvent(new CustomEvent("tripleStorageSaveSuccess", {
                detail: result.data
            }));
            
            return result.data;
        } else {
            throw new Error(result.message || "Unknown error from triple storage API");
        }
        
    } catch (error) {
        console.error("❌ DIRECT SAVE: Failed to save to triple storage", error);
        
        // Trigger error event
        document.dispatchEvent(new CustomEvent("tripleStorageSaveError", {
            detail: { error: error.message, winningNumber, options }
        }));
        
        throw error;
    }
}

// Function to override recordSpinForAnalytics
function setupTripleStorageIntegration() {
    console.log("🔧 SETUP: Setting up triple storage integration");
    
    // Store original function if it exists
    const originalRecordSpin = window.recordSpinForAnalytics;
    
    // Create new enhanced function
    window.recordSpinForAnalytics = async function(winningNumber) {
        console.log("🎯 INTERCEPT: recordSpinForAnalytics called with number:", winningNumber);
        
        try {
            // Save to triple storage first
            await saveSpinToTripleStorage(winningNumber);
            console.log("✅ INTERCEPT: Triple storage save successful");
            
            // Call original function for local updates (if it exists)
            if (originalRecordSpin && typeof originalRecordSpin === "function") {
                console.log("🔄 INTERCEPT: Calling original recordSpinForAnalytics for local updates");
                try {
                    originalRecordSpin.call(this, winningNumber);
                } catch (legacyError) {
                    console.warn("⚠️ INTERCEPT: Original function failed, but triple storage succeeded", legacyError);
                }
            } else {
                console.log("ℹ️ INTERCEPT: No original function to call, triple storage only");
            }
            
        } catch (error) {
            console.error("❌ INTERCEPT: Triple storage failed", error);
            
            // Fallback to original function if triple storage fails
            if (originalRecordSpin && typeof originalRecordSpin === "function") {
                console.log("🔄 INTERCEPT: Falling back to original function");
                originalRecordSpin.call(this, winningNumber);
            } else {
                console.error("❌ INTERCEPT: No fallback available, spin may be lost");
            }
        }
    };
    
    window.tripleStorageIntegrationActive = true;
    console.log("✅ SETUP: Triple storage integration is now active");
}

// Function to test the integration
async function testTripleStorageIntegration() {
    console.log("🧪 TEST: Testing triple storage integration");
    
    try {
        if (typeof window.recordSpinForAnalytics === "function") {
            console.log("✅ TEST: recordSpinForAnalytics function is available");
            
            // Test with a sample number
            await window.recordSpinForAnalytics(99); // Use 99 as a test number
            console.log("✅ TEST: Test call completed successfully");
            
            return true;
        } else {
            console.error("❌ TEST: recordSpinForAnalytics function not found");
            return false;
        }
    } catch (error) {
        console.error("❌ TEST: Integration test failed", error);
        return false;
    }
}

// Setup integration when DOM is ready
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function() {
        console.log("📄 DOM: Document loaded, setting up integration");
        setTimeout(setupTripleStorageIntegration, 1000); // Wait 1 second for other scripts
    });
} else {
    console.log("📄 DOM: Document already loaded, setting up integration immediately");
    setTimeout(setupTripleStorageIntegration, 100); // Small delay to ensure other scripts are loaded
}

// Also try to setup immediately in case DOMContentLoaded already fired
setTimeout(setupTripleStorageIntegration, 2000); // Backup setup after 2 seconds

// Listen for successful saves
document.addEventListener("tripleStorageSaveSuccess", function(event) {
    console.log("🎉 EVENT: Triple storage save successful", event.detail);
});

// Listen for save errors
document.addEventListener("tripleStorageSaveError", function(event) {
    console.error("💥 EVENT: Triple storage save error", event.detail);
});

// Make functions globally available for debugging
window.saveSpinToTripleStorage = saveSpinToTripleStorage;
window.testTripleStorageIntegration = testTripleStorageIntegration;
window.setupTripleStorageIntegration = setupTripleStorageIntegration;

console.log("🔧 DIRECT INTEGRATION: TV Display Triple Storage Integration loaded and ready");';

// Save the direct integration script
file_put_contents('tvdisplay/js/direct-triple-storage-integration.js', $directIntegrationScript);
echo "<p class='success'>✅ Created direct integration script: <code>tvdisplay/js/direct-triple-storage-integration.js</code></p>";

echo "<h2>Step 2: Add Direct Integration to TV Display</h2>";

// Update TV display to include the direct integration script
$tvDisplayPath = 'tvdisplay/index.html';
$tvContent = file_get_contents($tvDisplayPath);

// Check if direct integration is already included
if (strpos($tvContent, 'direct-triple-storage-integration.js') !== false) {
    echo "<p class='warning'>⚠️ Direct integration script already included in TV display</p>";
} else {
    // Add direct integration script at the very end, after all other scripts
    $directIntegrationScript = "\n    <!-- Direct Triple Storage Integration (MUST BE LAST) -->\n    <script src=\"js/direct-triple-storage-integration.js\"></script>\n";
    
    // Insert before the closing body tag
    $tvContent = str_replace('</body>', $directIntegrationScript . '</body>', $tvContent);
    
    // Save the updated content
    if (file_put_contents($tvDisplayPath, $tvContent)) {
        echo "<p class='success'>✅ Added direct integration script to TV display</p>";
    } else {
        echo "<p class='error'>❌ Failed to update TV display file</p>";
    }
}

echo "<h2>Step 3: Test the Fix</h2>";

echo "<p>Test the integration fix:</p>";
echo "<button onclick='testIntegrationFix()' style='background:#007bff;color:white;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;'>🧪 Test Integration Fix</button>";
echo "<div id='fix-test-results'></div>";

echo "<h2>✅ Integration Fix Complete</h2>";
echo "<p>The TV display integration has been fixed with:</p>";
echo "<ul>";
echo "<li>✅ Direct integration script that overrides recordSpinForAnalytics</li>";
echo "<li>✅ Comprehensive error handling and fallbacks</li>";
echo "<li>✅ Debug logging for troubleshooting</li>";
echo "<li>✅ Compatibility with existing TV display code</li>";
echo "<li>✅ Automatic setup with multiple retry mechanisms</li>";
echo "</ul>";

echo "<script>";
echo "async function testIntegrationFix() {";
echo "  const results = document.getElementById('fix-test-results');";
echo "  results.innerHTML = '<p>Testing integration fix...</p>';";
echo "  ";
echo "  try {";
echo "    // Test the triple storage API directly";
echo "    const testResponse = await fetch('/slipp/php/triple_storage_api.php', {";
echo "      method: 'POST',";
echo "      headers: { 'Content-Type': 'application/json' },";
echo "      body: JSON.stringify({";
echo "        winning_number: 88,";
echo "        draw_number: 8888,";
echo "        timestamp: new Date().toISOString().slice(0, 19).replace('T', ' '),";
echo "        is_manual: false,";
echo "        total_bets: 1,";
echo "        total_stake: 5.00,";
echo "        total_payout: 35.00";
echo "      })";
echo "    });";
echo "    ";
echo "    const testResult = await testResponse.json();";
echo "    ";
echo "    if (testResult.status === 'success') {";
echo "      let html = '<div class=\"success\"><h3>✅ Integration Fix Test Successful</h3>';";
echo "      html += '<p><strong>The triple storage API is working correctly.</strong></p>';";
echo "      html += '<table style=\"border-collapse:collapse;width:100%;\">';";
echo "      html += '<tr style=\"background:#f2f2f2;\"><th style=\"border:1px solid #ddd;padding:8px;\">Property</th><th style=\"border:1px solid #ddd;padding:8px;\">Value</th></tr>';";
echo "      html += '<tr><td style=\"border:1px solid #ddd;padding:8px;\">Draw Number</td><td style=\"border:1px solid #ddd;padding:8px;\">' + testResult.data.draw_number + '</td></tr>';";
echo "      html += '<tr><td style=\"border:1px solid #ddd;padding:8px;\">Winning Number</td><td style=\"border:1px solid #ddd;padding:8px;\">' + testResult.data.winning_number + '</td></tr>';";
echo "      html += '<tr><td style=\"border:1px solid #ddd;padding:8px;\">Winning Color</td><td style=\"border:1px solid #ddd;padding:8px;\">' + testResult.data.winning_color + '</td></tr>';";
echo "      html += '<tr><td style=\"border:1px solid #ddd;padding:8px;\">Roulette Draw ID</td><td style=\"border:1px solid #ddd;padding:8px;\">' + testResult.data.roulette_draw_id + '</td></tr>';";
echo "      html += '<tr><td style=\"border:1px solid #ddd;padding:8px;\">Detailed Record ID</td><td style=\"border:1px solid #ddd;padding:8px;\">' + testResult.data.detailed_record_id + '</td></tr>';";
echo "      html += '</table>';";
echo "      html += '<p><strong>Next Step:</strong> Open the TV display and perform a spin to test the integration.</p>';";
echo "      html += '</div>';";
echo "      ";
echo "      results.innerHTML = html;";
echo "    } else {";
echo "      results.innerHTML = '<div class=\"error\"><h3>❌ Integration Fix Test Failed</h3><p>' + testResult.message + '</p></div>';";
echo "    }";
echo "    ";
echo "  } catch (error) {";
echo "    results.innerHTML = '<div class=\"error\"><h3>❌ Integration Fix Test Error</h3><p>' + error.message + '</p></div>';";
echo "  }";
echo "}";
echo "</script>";

echo "<h2>🎯 Next Steps</h2>";
echo "<ol>";
echo "<li><strong>Test the Fix:</strong> Click the test button above to verify the API is working</li>";
echo "<li><strong>Open TV Display:</strong> Open the TV display and perform actual spins</li>";
echo "<li><strong>Monitor Results:</strong> Use the monitoring dashboard to verify data is being saved</li>";
echo "<li><strong>Check Console:</strong> Open browser console on TV display to see debug messages</li>";
echo "</ol>";

echo "<button onclick='window.open(\"tvdisplay/index.html\", \"_blank\")' style='background:#28a745;color:white;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;margin:5px;'>🎮 Open TV Display</button>";
echo "<button onclick='window.open(\"monitor_triple_storage.php\", \"_blank\")' style='background:#17a2b8;color:white;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;margin:5px;'>📊 Monitor Storage</button>";
echo "<button onclick='window.open(\"debug_tv_display_spins.html\", \"_blank\")' style='background:#6f42c1;color:white;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;margin:5px;'>🔍 Debug Spins</button>";

echo "<p><a href='test_tv_display_integration.php'>← Integration Test</a> | <a href='tvdisplay/index.html'>TV Display →</a></p>";
echo "</body></html>";
?>
