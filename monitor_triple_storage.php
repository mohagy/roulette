<?php
/**
 * Monitor Triple Storage System
 * 
 * Real-time monitoring dashboard for the triple storage system
 * showing data in roulette_analytics, detailed_draw_results, and roulette_draws tables.
 */

// Initialize cache prevention
require_once 'php/cache_prevention.php';

// Include database connection
require_once 'php/db_connect.php';

echo "<!DOCTYPE html>";
echo "<html><head><title>Monitor Triple Storage System</title>";
echo "<style>";
echo "body{font-family:Arial,sans-serif;margin:20px;background:#f5f5f5;}";
echo ".dashboard{display:grid;grid-template-columns:1fr 1fr 1fr;gap:15px;}";
echo ".panel{background:white;padding:15px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1);}";
echo ".success{color:green;} .error{color:red;} .info{color:blue;} .warning{color:orange;}";
echo ".metric{text-align:center;padding:10px;background:#f8f9fa;border-radius:4px;margin:5px 0;}";
echo ".metric-value{font-size:1.5em;font-weight:bold;color:#007bff;}";
echo ".metric-label{color:#6c757d;margin-top:5px;font-size:0.9em;}";
echo "table{border-collapse:collapse;width:100%;font-size:0.9em;} th,td{border:1px solid #ddd;padding:6px;text-align:left;} th{background-color:#f2f2f2;}";
echo ".sync-status{padding:8px;border-radius:4px;margin:8px 0;font-size:0.9em;}";
echo ".sync-good{background:#d4edda;border:1px solid #c3e6cb;}";
echo ".sync-warning{background:#fff3cd;border:1px solid #ffeaa7;}";
echo ".sync-error{background:#f8d7da;border:1px solid #f5c6cb;}";
echo ".red{color:#dc3545;} .black{color:#343a40;} .green{color:#28a745;}";
echo "</style>";
echo "<meta http-equiv='refresh' content='15'>";  // Auto-refresh every 15 seconds
echo "</head><body>";

echo "<h1>📊 Monitor Triple Storage System</h1>";
echo "<p class='info'>🔄 Auto-refreshing every 15 seconds | Last update: " . date('Y-m-d H:i:s') . "</p>";

try {
    // Get data from all three tables
    $analyticsData = getFreshData("SELECT * FROM roulette_analytics WHERE id = 1");
    $detailedData = getFreshData("SELECT * FROM detailed_draw_results ORDER BY id DESC LIMIT 15");
    $rouletteDrawsData = getFreshData("SELECT * FROM roulette_draws ORDER BY id DESC LIMIT 15");
    
    // Get counts
    $detailedCount = getFreshData("SELECT COUNT(*) as total_records FROM detailed_draw_results");
    $rouletteDrawsCount = getFreshData("SELECT COUNT(*) as total_records FROM roulette_draws");
    
    echo "<div class='dashboard'>";
    
    // Panel 1 - Analytics Table
    echo "<div class='panel'>";
    echo "<h3>📈 Analytics Table</h3>";
    
    if (!empty($analyticsData)) {
        $analytics = $analyticsData[0];
        $allSpins = json_decode($analytics['all_spins'], true) ?: [];
        $currentDraw = (int)$analytics['current_draw_number'];
        
        echo "<div class='metric'>";
        echo "<div class='metric-value'>" . count($allSpins) . "</div>";
        echo "<div class='metric-label'>Spins in Analytics</div>";
        echo "</div>";
        
        echo "<div class='metric'>";
        echo "<div class='metric-value'>$currentDraw</div>";
        echo "<div class='metric-label'>Current Draw Number</div>";
        echo "</div>";
        
        if (!empty($allSpins)) {
            echo "<p><strong>Recent 5:</strong> " . implode(', ', array_slice($allSpins, 0, 5)) . "</p>";
        }
        
        echo "<p><small>Updated: " . htmlspecialchars($analytics['last_updated']) . "</small></p>";
        
    } else {
        echo "<div class='sync-warning'><p>⚠️ No analytics data</p></div>";
    }
    
    echo "</div>";
    
    // Panel 2 - Detailed Results Table
    echo "<div class='panel'>";
    echo "<h3>📊 Detailed Results</h3>";
    
    if (!empty($detailedCount)) {
        $totalDetailed = $detailedCount[0]['total_records'];
        
        echo "<div class='metric'>";
        echo "<div class='metric-value'>$totalDetailed</div>";
        echo "<div class='metric-label'>Total Records</div>";
        echo "</div>";
        
        if (!empty($detailedData)) {
            $latestDetailed = $detailedData[0];
            
            echo "<div class='metric'>";
            echo "<div class='metric-value'>" . htmlspecialchars($latestDetailed['draw_number']) . "</div>";
            echo "<div class='metric-label'>Latest Draw</div>";
            echo "</div>";
            
            echo "<table>";
            echo "<tr><th>Draw</th><th>Number</th><th>Color</th></tr>";
            
            foreach (array_slice($detailedData, 0, 5) as $row) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['draw_number']) . "</td>";
                echo "<td>" . htmlspecialchars($row['winning_number']) . "</td>";
                echo "<td class='" . strtolower($row['color']) . "'>" . htmlspecialchars($row['color']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
    } else {
        echo "<div class='sync-warning'><p>⚠️ No detailed records</p></div>";
    }
    
    echo "</div>";
    
    // Panel 3 - Roulette Draws Table
    echo "<div class='panel'>";
    echo "<h3>🎯 Roulette Draws</h3>";
    
    if (!empty($rouletteDrawsCount)) {
        $totalRouletteDraws = $rouletteDrawsCount[0]['total_records'];
        
        echo "<div class='metric'>";
        echo "<div class='metric-value'>$totalRouletteDraws</div>";
        echo "<div class='metric-label'>Total Draws</div>";
        echo "</div>";
        
        if (!empty($rouletteDrawsData)) {
            $latestRouletteDraw = $rouletteDrawsData[0];
            
            echo "<div class='metric'>";
            echo "<div class='metric-value'>" . htmlspecialchars($latestRouletteDraw['draw_number']) . "</div>";
            echo "<div class='metric-label'>Latest Draw</div>";
            echo "</div>";
            
            echo "<table>";
            echo "<tr><th>Draw</th><th>Number</th><th>Manual</th><th>Bets</th></tr>";
            
            foreach (array_slice($rouletteDrawsData, 0, 5) as $row) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['draw_number']) . "</td>";
                echo "<td class='" . strtolower($row['winning_color']) . "'>" . htmlspecialchars($row['winning_number']) . "</td>";
                echo "<td>" . ($row['is_manual'] ? '✋ Yes' : '🤖 No') . "</td>";
                echo "<td>" . htmlspecialchars($row['total_bets']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
    } else {
        echo "<div class='sync-warning'><p>⚠️ No roulette draws</p></div>";
    }
    
    echo "</div>";
    
    echo "</div>"; // End dashboard
    
    // Synchronization Analysis
    echo "<div class='panel' style='margin-top:20px;'>";
    echo "<h2>🔄 Triple Storage Synchronization Analysis</h2>";
    
    if (!empty($analyticsData) && !empty($detailedData) && !empty($rouletteDrawsData)) {
        $analytics = $analyticsData[0];
        $allSpins = json_decode($analytics['all_spins'], true) ?: [];
        $analyticsSpinCount = count($allSpins);
        $detailedRecordCount = $detailedCount[0]['total_records'];
        $rouletteDrawsRecordCount = $rouletteDrawsCount[0]['total_records'];
        
        $currentDrawAnalytics = (int)$analytics['current_draw_number'];
        $latestDrawDetailed = (int)$detailedData[0]['draw_number'];
        $latestDrawRoulette = (int)$rouletteDrawsData[0]['draw_number'];
        
        // Check synchronization
        $syncStatus = 'good';
        $syncMessages = [];
        
        // Check record counts
        if (abs($detailedRecordCount - $rouletteDrawsRecordCount) > 2) {
            $syncStatus = 'warning';
            $syncMessages[] = "Record count mismatch: Detailed has $detailedRecordCount, Roulette Draws has $rouletteDrawsRecordCount";
        }
        
        // Check draw numbers
        if (abs($latestDrawDetailed - $latestDrawRoulette) > 1) {
            $syncStatus = 'warning';
            $syncMessages[] = "Draw number mismatch: Detailed shows $latestDrawDetailed, Roulette Draws shows $latestDrawRoulette";
        }
        
        if (abs($currentDrawAnalytics - $latestDrawRoulette) > 1) {
            $syncStatus = 'warning';
            $syncMessages[] = "Analytics draw mismatch: Analytics shows $currentDrawAnalytics, Roulette Draws shows $latestDrawRoulette";
        }
        
        if (empty($syncMessages)) {
            $syncMessages[] = "All tables are properly synchronized";
        }
        
        echo "<div class='sync-$syncStatus'>";
        foreach ($syncMessages as $message) {
            echo "<p>" . ($syncStatus === 'good' ? '✅' : '⚠️') . " $message</p>";
        }
        echo "</div>";
        
        echo "<table>";
        echo "<tr><th>Metric</th><th>Analytics</th><th>Detailed Results</th><th>Roulette Draws</th><th>Status</th></tr>";
        echo "<tr><td>Record Count</td><td>$analyticsSpinCount</td><td>$detailedRecordCount</td><td>$rouletteDrawsRecordCount</td><td>" . (abs($detailedRecordCount - $rouletteDrawsRecordCount) <= 2 ? '✅ Good' : '⚠️ Mismatch') . "</td></tr>";
        echo "<tr><td>Latest Draw</td><td>$currentDrawAnalytics</td><td>$latestDrawDetailed</td><td>$latestDrawRoulette</td><td>" . (abs($latestDrawDetailed - $latestDrawRoulette) <= 1 ? '✅ Good' : '⚠️ Mismatch') . "</td></tr>";
        echo "</table>";
        
    } else {
        echo "<div class='sync-error'>";
        echo "<h3>❌ Cannot Check Synchronization</h3>";
        echo "<p>One or more tables are empty.</p>";
        echo "</div>";
    }
    
    echo "</div>";
    
    // Manual vs Automatic Analysis
    if (!empty($rouletteDrawsData)) {
        echo "<div class='panel' style='margin-top:20px;'>";
        echo "<h2>🎯 Manual vs Automatic Spins</h2>";
        
        $manualCount = 0;
        $automaticCount = 0;
        
        foreach ($rouletteDrawsData as $draw) {
            if ($draw['is_manual']) {
                $manualCount++;
            } else {
                $automaticCount++;
            }
        }
        
        $totalSpins = $manualCount + $automaticCount;
        
        echo "<table>";
        echo "<tr><th>Type</th><th>Count</th><th>Percentage</th></tr>";
        echo "<tr><td>🤖 Automatic</td><td>$automaticCount</td><td>" . ($totalSpins > 0 ? round(($automaticCount / $totalSpins) * 100, 1) : 0) . "%</td></tr>";
        echo "<tr><td>✋ Manual</td><td>$manualCount</td><td>" . ($totalSpins > 0 ? round(($manualCount / $totalSpins) * 100, 1) : 0) . "%</td></tr>";
        echo "</table>";
        
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='panel'>";
    echo "<h2 class='error'>❌ Monitoring Error</h2>";
    echo "<p class='error'>Error monitoring triple storage: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "<div style='text-align:center;margin:20px 0;'>";
echo "<button onclick='window.location.reload()' style='background:#007bff;color:white;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;margin:5px;'>🔄 Manual Refresh</button>";
echo "<button onclick='window.open(\"tvdisplay/index.html\", \"_blank\")' style='background:#28a745;color:white;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;margin:5px;'>🎮 Open TV Display</button>";
echo "<button onclick='window.open(\"test_triple_storage_complete.php\", \"_blank\")' style='background:#6f42c1;color:white;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;margin:5px;'>🧪 Run Tests</button>";
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
echo "console.log('📊 Triple Storage Monitor loaded at', new Date().toISOString());";
echo "</script>";

echo "<p style='text-align:center;color:#6c757d;margin-top:40px;'>";
echo "<a href='update_tv_display_triple_storage.php'>← Update TV Display</a> | ";
echo "<a href='tvdisplay/index.html'>TV Display</a> | ";
echo "<a href='test_triple_storage_complete.php'>Complete Test →</a>";
echo "</p>";

echo "</body></html>";
?>
