<?php
/**
 * Monitor Dual Storage System
 * 
 * Real-time monitoring dashboard for the dual storage system
 * showing data in both roulette_analytics and detailed_draw_results tables.
 */

// Initialize cache prevention
require_once 'php/cache_prevention.php';

// Include database connection
require_once 'php/db_connect.php';

echo "<!DOCTYPE html>";
echo "<html><head><title>Monitor Dual Storage System</title>";
echo "<style>";
echo "body{font-family:Arial,sans-serif;margin:20px;background:#f5f5f5;}";
echo ".dashboard{display:grid;grid-template-columns:1fr 1fr;gap:20px;}";
echo ".panel{background:white;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1);}";
echo ".success{color:green;} .error{color:red;} .info{color:blue;} .warning{color:orange;}";
echo ".metric{text-align:center;padding:15px;background:#f8f9fa;border-radius:4px;margin:10px 0;}";
echo ".metric-value{font-size:1.8em;font-weight:bold;color:#007bff;}";
echo ".metric-label{color:#6c757d;margin-top:5px;}";
echo "table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background-color:#f2f2f2;}";
echo ".sync-status{padding:10px;border-radius:4px;margin:10px 0;}";
echo ".sync-good{background:#d4edda;border:1px solid #c3e6cb;}";
echo ".sync-warning{background:#fff3cd;border:1px solid #ffeaa7;}";
echo ".sync-error{background:#f8d7da;border:1px solid #f5c6cb;}";
echo "</style>";
echo "<meta http-equiv='refresh' content='10'>";  // Auto-refresh every 10 seconds
echo "</head><body>";

echo "<h1>📊 Monitor Dual Storage System</h1>";
echo "<p class='info'>🔄 Auto-refreshing every 10 seconds | Last update: " . date('Y-m-d H:i:s') . "</p>";

try {
    // Get data from both tables
    $analyticsData = getFreshData("SELECT * FROM roulette_analytics WHERE id = 1");
    $detailedData = getFreshData("SELECT * FROM detailed_draw_results ORDER BY id DESC LIMIT 20");
    $detailedCount = getFreshData("SELECT COUNT(*) as total_records FROM detailed_draw_results");
    
    echo "<div class='dashboard'>";
    
    // Left Panel - Analytics Table Status
    echo "<div class='panel'>";
    echo "<h2>📈 Analytics Table Status</h2>";
    
    if (!empty($analyticsData)) {
        $analytics = $analyticsData[0];
        $allSpins = json_decode($analytics['all_spins'], true) ?: [];
        $numberFrequency = json_decode($analytics['number_frequency'], true) ?: [];
        $currentDraw = (int)$analytics['current_draw_number'];
        
        echo "<div class='metric'>";
        echo "<div class='metric-value'>" . count($allSpins) . "</div>";
        echo "<div class='metric-label'>Total Spins in Analytics</div>";
        echo "</div>";
        
        echo "<div class='metric'>";
        echo "<div class='metric-value'>$currentDraw</div>";
        echo "<div class='metric-label'>Current Draw Number</div>";
        echo "</div>";
        
        echo "<h3>📋 Analytics Details</h3>";
        echo "<table>";
        echo "<tr><th>Property</th><th>Value</th></tr>";
        echo "<tr><td>Last Updated</td><td>" . htmlspecialchars($analytics['last_updated']) . "</td></tr>";
        echo "<tr><td>Created At</td><td>" . htmlspecialchars($analytics['created_at']) . "</td></tr>";
        echo "<tr><td>Spins Array Length</td><td>" . count($allSpins) . "</td></tr>";
        echo "<tr><td>Frequency Entries</td><td>" . count($numberFrequency) . "</td></tr>";
        echo "</table>";
        
        if (!empty($allSpins)) {
            echo "<h3>🎯 Recent Spins (Last 10)</h3>";
            echo "<p>" . implode(', ', array_slice($allSpins, 0, 10)) . "</p>";
        }
        
    } else {
        echo "<div class='sync-warning'>";
        echo "<h3>⚠️ No Analytics Data</h3>";
        echo "<p>The analytics table is empty or not initialized.</p>";
        echo "</div>";
    }
    
    echo "</div>";
    
    // Right Panel - Detailed Results Status
    echo "<div class='panel'>";
    echo "<h2>📊 Detailed Results Table Status</h2>";
    
    if (!empty($detailedCount)) {
        $totalRecords = $detailedCount[0]['total_records'];
        
        echo "<div class='metric'>";
        echo "<div class='metric-value'>$totalRecords</div>";
        echo "<div class='metric-label'>Total Detailed Records</div>";
        echo "</div>";
        
        if (!empty($detailedData)) {
            $latestRecord = $detailedData[0];
            
            echo "<div class='metric'>";
            echo "<div class='metric-value'>" . htmlspecialchars($latestRecord['draw_number']) . "</div>";
            echo "<div class='metric-label'>Latest Draw Number</div>";
            echo "</div>";
            
            echo "<h3>📋 Recent Records (Last 10)</h3>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Draw #</th><th>Number</th><th>Color</th><th>Timestamp</th></tr>";
            
            foreach (array_slice($detailedData, 0, 10) as $row) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                echo "<td>" . htmlspecialchars($row['draw_number']) . "</td>";
                echo "<td>" . htmlspecialchars($row['winning_number']) . "</td>";
                echo "<td class='" . strtolower($row['color']) . "'>" . htmlspecialchars($row['color']) . "</td>";
                echo "<td>" . htmlspecialchars($row['timestamp']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
    } else {
        echo "<div class='sync-warning'>";
        echo "<h3>⚠️ No Detailed Records</h3>";
        echo "<p>The detailed results table is empty.</p>";
        echo "</div>";
    }
    
    echo "</div>";
    
    echo "</div>"; // End dashboard
    
    // Synchronization Status
    echo "<div class='panel'>";
    echo "<h2>🔄 Synchronization Status</h2>";
    
    if (!empty($analyticsData) && !empty($detailedData)) {
        $analytics = $analyticsData[0];
        $allSpins = json_decode($analytics['all_spins'], true) ?: [];
        $analyticsSpinCount = count($allSpins);
        $detailedRecordCount = $detailedCount[0]['total_records'];
        $currentDrawAnalytics = (int)$analytics['current_draw_number'];
        $latestDrawDetailed = (int)$detailedData[0]['draw_number'];
        
        // Check synchronization
        $syncStatus = 'good';
        $syncMessage = '✅ Tables are synchronized';
        
        // Check if record counts are reasonable
        if (abs($analyticsSpinCount - $detailedRecordCount) > 5) {
            $syncStatus = 'warning';
            $syncMessage = "⚠️ Record count mismatch: Analytics has $analyticsSpinCount spins, Detailed has $detailedRecordCount records";
        }
        
        // Check if draw numbers are consistent
        if (abs($currentDrawAnalytics - $latestDrawDetailed) > 1) {
            $syncStatus = 'warning';
            $syncMessage = "⚠️ Draw number mismatch: Analytics shows $currentDrawAnalytics, Detailed shows $latestDrawDetailed";
        }
        
        echo "<div class='sync-$syncStatus'>";
        echo "<h3>$syncMessage</h3>";
        echo "</div>";
        
        echo "<table>";
        echo "<tr><th>Metric</th><th>Analytics Table</th><th>Detailed Table</th><th>Status</th></tr>";
        echo "<tr><td>Record Count</td><td>$analyticsSpinCount</td><td>$detailedRecordCount</td><td>" . (abs($analyticsSpinCount - $detailedRecordCount) <= 5 ? '✅ Good' : '⚠️ Mismatch') . "</td></tr>";
        echo "<tr><td>Latest Draw</td><td>$currentDrawAnalytics</td><td>$latestDrawDetailed</td><td>" . (abs($currentDrawAnalytics - $latestDrawDetailed) <= 1 ? '✅ Good' : '⚠️ Mismatch') . "</td></tr>";
        echo "<tr><td>Last Updated</td><td>" . htmlspecialchars($analytics['last_updated']) . "</td><td>" . htmlspecialchars($detailedData[0]['timestamp']) . "</td><td>ℹ️ Info</td></tr>";
        echo "</table>";
        
    } else {
        echo "<div class='sync-error'>";
        echo "<h3>❌ Cannot Check Synchronization</h3>";
        echo "<p>One or both tables are empty.</p>";
        echo "</div>";
    }
    
    echo "</div>";
    
    // Color Distribution Analysis
    if (!empty($detailedData)) {
        echo "<div class='panel'>";
        echo "<h2>🎨 Color Distribution Analysis</h2>";
        
        $colorCounts = ['red' => 0, 'black' => 0, 'green' => 0];
        foreach ($detailedData as $record) {
            $color = strtolower($record['color']);
            if (isset($colorCounts[$color])) {
                $colorCounts[$color]++;
            }
        }
        
        $totalSpins = array_sum($colorCounts);
        
        echo "<table>";
        echo "<tr><th>Color</th><th>Count</th><th>Percentage</th></tr>";
        
        foreach ($colorCounts as $color => $count) {
            $percentage = $totalSpins > 0 ? round(($count / $totalSpins) * 100, 1) : 0;
            echo "<tr>";
            echo "<td class='$color'>" . ucfirst($color) . "</td>";
            echo "<td>$count</td>";
            echo "<td>$percentage%</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "</div>";
    }
    
    // System Health
    echo "<div class='panel'>";
    echo "<h2>🏥 System Health</h2>";
    
    $healthChecks = [
        'Dual Storage API' => file_exists('php/dual_storage_api.php'),
        'Color Utility' => file_exists('php/roulette_color.php'),
        'Cache Prevention' => file_exists('php/cache_prevention.php'),
        'Analytics Blocker' => file_exists('js/analytics_blocker.js'),
        'TV Display Integration' => file_exists('tvdisplay/js/dual-storage.js')
    ];
    
    echo "<table>";
    echo "<tr><th>Component</th><th>Status</th></tr>";
    
    $allHealthy = true;
    foreach ($healthChecks as $component => $status) {
        $statusText = $status ? '✅ Active' : '❌ Missing';
        $statusClass = $status ? 'success' : 'error';
        
        if (!$status) $allHealthy = false;
        
        echo "<tr><td>$component</td><td class='$statusClass'>$statusText</td></tr>";
    }
    echo "</table>";
    
    if ($allHealthy) {
        echo "<p class='success'>✅ All system components are healthy</p>";
    } else {
        echo "<p class='error'>❌ Some system components are missing</p>";
    }
    
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='panel'>";
    echo "<h2 class='error'>❌ Monitoring Error</h2>";
    echo "<p class='error'>Error monitoring dual storage: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "<div style='text-align:center;margin:20px 0;'>";
echo "<button onclick='window.location.reload()' style='background:#007bff;color:white;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;margin:5px;'>🔄 Manual Refresh</button>";
echo "<button onclick='window.open(\"tvdisplay/index.html\", \"_blank\")' style='background:#28a745;color:white;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;margin:5px;'>🎮 Open TV Display</button>";
echo "<button onclick='window.open(\"test_dual_storage_integration.php\", \"_blank\")' style='background:#6f42c1;color:white;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;margin:5px;'>🧪 Run Tests</button>";
echo "<button onclick='toggleAutoRefresh()' id='autoRefreshBtn' style='background:#ffc107;color:black;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;margin:5px;'>⏸️ Pause Auto-Refresh</button>";
echo "</div>";

echo "<script>";
echo "let autoRefreshEnabled = true;";
echo "";
echo "function toggleAutoRefresh() {";
echo "  const btn = document.getElementById('autoRefreshBtn');";
echo "  if (autoRefreshEnabled) {";
echo "    const metaRefresh = document.querySelector('meta[http-equiv=\"refresh\"]');";
echo "    if (metaRefresh) metaRefresh.remove();";
echo "    btn.textContent = '▶️ Resume Auto-Refresh';";
echo "    btn.style.background = '#28a745';";
echo "    btn.style.color = 'white';";
echo "    autoRefreshEnabled = false;";
echo "  } else {";
echo "    window.location.reload();";
echo "  }";
echo "}";
echo "";
echo "console.log('📊 Dual Storage Monitor loaded at', new Date().toISOString());";
echo "</script>";

echo "<p style='text-align:center;color:#6c757d;margin-top:40px;'>";
echo "<a href='test_dual_storage_integration.php'>← Test Integration</a> | ";
echo "<a href='tvdisplay/index.html'>TV Display</a> | ";
echo "<a href='analytics_monitor_dashboard.php'>Analytics Monitor →</a>";
echo "</p>";

echo "</body></html>";
?>
