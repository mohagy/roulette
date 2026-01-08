<?php
/**
 * Analytics Monitor Dashboard
 * 
 * Real-time monitoring dashboard for the roulette_analytics table
 * to detect and prevent phantom data generation.
 */

// Initialize comprehensive cache prevention
require_once 'php/cache_prevention.php';

// Include database connection
require_once 'php/db_connect.php';

// Set content type to HTML for better display
header('Content-Type: text/html; charset=UTF-8');

echo "<!DOCTYPE html>";
echo "<html><head><title>Analytics Monitor Dashboard</title>";
echo "<style>";
echo "body{font-family:Arial,sans-serif;margin:20px;background:#f5f5f5;}";
echo ".dashboard{display:grid;grid-template-columns:1fr 1fr;gap:20px;}";
echo ".panel{background:white;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1);}";
echo ".success{color:green;} .error{color:red;} .info{color:blue;} .warning{color:orange;}";
echo ".status-good{background:#d4edda;border:1px solid #c3e6cb;padding:10px;border-radius:4px;}";
echo ".status-bad{background:#f8d7da;border:1px solid #f5c6cb;padding:10px;border-radius:4px;}";
echo ".status-warning{background:#fff3cd;border:1px solid #ffeaa7;padding:10px;border-radius:4px;}";
echo "table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background-color:#f2f2f2;}";
echo ".metric{text-align:center;padding:20px;background:#f8f9fa;border-radius:4px;margin:10px 0;}";
echo ".metric-value{font-size:2em;font-weight:bold;color:#007bff;}";
echo ".metric-label{color:#6c757d;margin-top:5px;}";
echo ".log-entry{font-family:monospace;font-size:12px;padding:5px;margin:2px 0;border-left:3px solid #007bff;background:#f8f9fa;}";
echo "</style>";
echo "<meta http-equiv='refresh' content='5'>";  // Auto-refresh every 5 seconds
echo "</head><body>";

echo "<h1>📊 Analytics Monitor Dashboard</h1>";
echo "<p class='info'>🔄 Auto-refreshing every 5 seconds | Last update: " . date('Y-m-d H:i:s') . "</p>";

try {
    // Get current analytics data with fresh query
    $analyticsData = getFreshData("SELECT SQL_NO_CACHE * FROM roulette_analytics WHERE id = 1");
    
    echo "<div class='dashboard'>";
    
    // Left Panel - Current Status
    echo "<div class='panel'>";
    echo "<h2>🎯 Current Analytics Status</h2>";
    
    if (!empty($analyticsData)) {
        $analytics = $analyticsData[0];
        
        // Parse data
        $allSpins = json_decode($analytics['all_spins'], true) ?: [];
        $numberFrequency = json_decode($analytics['number_frequency'], true) ?: [];
        $currentDraw = (int)$analytics['current_draw_number'];
        $lastUpdated = $analytics['last_updated'];
        
        // Determine status
        $isClean = (empty($allSpins) && $currentDraw === 0);
        $statusClass = $isClean ? 'status-good' : 'status-bad';
        $statusText = $isClean ? '✅ CLEAN - No phantom data' : '❌ PHANTOM DATA DETECTED';
        
        echo "<div class='$statusClass'>";
        echo "<h3>$statusText</h3>";
        echo "</div>";
        
        // Metrics
        echo "<div class='metric'>";
        echo "<div class='metric-value'>$currentDraw</div>";
        echo "<div class='metric-label'>Current Draw Number</div>";
        echo "</div>";
        
        echo "<div class='metric'>";
        echo "<div class='metric-value'>" . count($allSpins) . "</div>";
        echo "<div class='metric-label'>Total Spins Recorded</div>";
        echo "</div>";
        
        // Detailed data
        echo "<h3>📋 Detailed Data</h3>";
        echo "<table>";
        echo "<tr><th>Property</th><th>Value</th><th>Status</th></tr>";
        
        // All spins
        $spinsDisplay = empty($allSpins) ? '[]' : '[' . implode(',', array_slice($allSpins, 0, 10)) . (count($allSpins) > 10 ? '...' : '') . ']';
        $spinsStatus = empty($allSpins) ? 'success' : 'error';
        echo "<tr><td>All Spins</td><td>" . htmlspecialchars($spinsDisplay) . "</td><td class='$spinsStatus'>" . (empty($allSpins) ? '✅ Empty' : '❌ Has Data') . "</td></tr>";
        
        // Current draw
        $drawStatus = ($currentDraw === 0) ? 'success' : 'error';
        echo "<tr><td>Current Draw</td><td>$currentDraw</td><td class='$drawStatus'>" . ($currentDraw === 0 ? '✅ Reset' : '❌ Non-zero') . "</td></tr>";
        
        // Last updated
        $timeDiff = time() - strtotime($lastUpdated);
        $timeStatus = ($timeDiff > 300) ? 'success' : 'warning'; // Good if not updated in last 5 minutes
        echo "<tr><td>Last Updated</td><td>" . htmlspecialchars($lastUpdated) . "</td><td class='$timeStatus'>" . ($timeDiff > 300 ? '✅ Stable' : '⚠️ Recent') . "</td></tr>";
        
        echo "</table>";
        
        // If phantom data detected, show details
        if (!$isClean) {
            echo "<div class='status-bad'>";
            echo "<h3>🚨 PHANTOM DATA ALERT</h3>";
            echo "<p><strong>Detected unauthorized data:</strong></p>";
            echo "<ul>";
            if (!empty($allSpins)) {
                echo "<li>Spins: " . htmlspecialchars(json_encode($allSpins)) . "</li>";
            }
            if ($currentDraw > 0) {
                echo "<li>Draw number: $currentDraw</li>";
            }
            echo "</ul>";
            echo "<button onclick='resetAnalytics()' style='background:#dc3545;color:white;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;'>🔄 Reset Analytics</button>";
            echo "</div>";
        }
        
    } else {
        echo "<div class='status-warning'>";
        echo "<h3>⚠️ NO ANALYTICS DATA</h3>";
        echo "<p>The analytics table is empty. This might indicate a problem.</p>";
        echo "<button onclick='initializeAnalytics()' style='background:#28a745;color:white;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;'>🔧 Initialize Analytics</button>";
        echo "</div>";
    }
    
    echo "</div>";
    
    // Right Panel - Monitoring Logs
    echo "<div class='panel'>";
    echo "<h2>📝 Monitoring Logs</h2>";
    
    // Check for protection logs
    $protectionLogFile = 'logs/analytics_protection.log';
    if (file_exists($protectionLogFile)) {
        echo "<h3>🛡️ Protection Log (Last 10 entries)</h3>";
        $logLines = file($protectionLogFile);
        $recentLines = array_slice($logLines, -10);
        
        foreach ($recentLines as $line) {
            echo "<div class='log-entry'>" . htmlspecialchars(trim($line)) . "</div>";
        }
    } else {
        echo "<p class='info'>ℹ️ No protection log found</p>";
    }
    
    // Check for cache prevention logs
    $cacheLogFile = 'logs/cache_prevention.log';
    if (file_exists($cacheLogFile)) {
        echo "<h3>🚫 Cache Prevention Log (Last 5 entries)</h3>";
        $logLines = file($cacheLogFile);
        $recentLines = array_slice($logLines, -5);
        
        foreach ($recentLines as $line) {
            echo "<div class='log-entry'>" . htmlspecialchars(trim($line)) . "</div>";
        }
    } else {
        echo "<p class='info'>ℹ️ No cache prevention log found</p>";
    }
    
    // System status checks
    echo "<h3>🔧 System Status</h3>";
    echo "<table>";
    echo "<tr><th>Component</th><th>Status</th></tr>";
    
    // Check if analytics blocker exists
    $blockerExists = file_exists('js/analytics_blocker.js');
    echo "<tr><td>Analytics Blocker</td><td class='" . ($blockerExists ? 'success' : 'error') . "'>" . ($blockerExists ? '✅ Active' : '❌ Missing') . "</td></tr>";
    
    // Check if protection script exists
    $protectionExists = file_exists('php/analytics_protection.php');
    echo "<tr><td>Server Protection</td><td class='" . ($protectionExists ? 'success' : 'error') . "'>" . ($protectionExists ? '✅ Active' : '❌ Missing') . "</td></tr>";
    
    // Check if cache prevention is active
    $cachePreventionExists = file_exists('php/cache_prevention.php');
    echo "<tr><td>Cache Prevention</td><td class='" . ($cachePreventionExists ? 'success' : 'error') . "'>" . ($cachePreventionExists ? '✅ Active' : '❌ Missing') . "</td></tr>";
    
    echo "</table>";
    
    echo "</div>";
    
    echo "</div>"; // End dashboard
    
    // Action buttons
    echo "<div style='text-align:center;margin:20px 0;'>";
    echo "<button onclick='window.location.reload()' style='background:#007bff;color:white;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;margin:5px;'>🔄 Manual Refresh</button>";
    echo "<button onclick='window.open(\"secure_analytics_reset.php\", \"_blank\")' style='background:#dc3545;color:white;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;margin:5px;'>🔧 Secure Reset</button>";
    echo "<button onclick='window.open(\"block_phantom_analytics.php\", \"_blank\")' style='background:#6f42c1;color:white;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;margin:5px;'>🚫 Block Phantoms</button>";
    echo "<button onclick='toggleAutoRefresh()' id='autoRefreshBtn' style='background:#28a745;color:white;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;margin:5px;'>⏸️ Pause Auto-Refresh</button>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='status-bad'>";
    echo "<h2>❌ Error</h2>";
    echo "<p>Error monitoring analytics: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "<script>";
echo "let autoRefreshEnabled = true;";
echo "";
echo "function resetAnalytics() {";
echo "  if (confirm('Are you sure you want to reset the analytics table? This will clear all data.')) {";
echo "    window.open('secure_analytics_reset.php', '_blank');";
echo "  }";
echo "}";
echo "";
echo "function initializeAnalytics() {";
echo "  if (confirm('Initialize analytics table with clean data?')) {";
echo "    window.open('secure_analytics_reset.php', '_blank');";
echo "  }";
echo "}";
echo "";
echo "function toggleAutoRefresh() {";
echo "  const btn = document.getElementById('autoRefreshBtn');";
echo "  if (autoRefreshEnabled) {";
echo "    // Disable auto-refresh by removing meta refresh";
echo "    const metaRefresh = document.querySelector('meta[http-equiv=\"refresh\"]');";
echo "    if (metaRefresh) metaRefresh.remove();";
echo "    btn.textContent = '▶️ Resume Auto-Refresh';";
echo "    btn.style.background = '#ffc107';";
echo "    autoRefreshEnabled = false;";
echo "  } else {";
echo "    // Re-enable auto-refresh";
echo "    window.location.reload();";
echo "  }";
echo "}";
echo "";
echo "// Log monitoring activity";
echo "console.log('📊 Analytics Monitor Dashboard loaded at', new Date().toISOString());";
echo "</script>";

echo "<p style='text-align:center;color:#6c757d;margin-top:40px;'>";
echo "<a href='security_verification.php'>← Security Verification</a> | ";
echo "<a href='complete_system_reset.php'>Complete Reset</a> | ";
echo "<a href='tvdisplay/index.html'>TV Display →</a>";
echo "</p>";

echo "</body></html>";
?>
