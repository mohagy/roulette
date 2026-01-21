<?php
/**
 * Monitor Draws
 * 
 * This script monitors the database for new draws and displays them in real-time
 * to help debug the phantom draw issue.
 */

// Include database connection
require_once 'php/db_connect.php';

// Set content type to HTML for better display
header('Content-Type: text/html; charset=UTF-8');

echo "<!DOCTYPE html>";
echo "<html><head><title>Draw Monitor</title>";
echo "<style>";
echo "body{font-family:Arial,sans-serif;margin:20px;background:#f5f5f5;}";
echo ".container{max-width:1200px;margin:0 auto;background:white;padding:20px;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,0.1);}";
echo "table{width:100%;border-collapse:collapse;margin:10px 0;}";
echo "th,td{padding:8px;text-align:left;border-bottom:1px solid #ddd;}";
echo "th{background-color:#f2f2f2;font-weight:bold;}";
echo ".new-entry{background-color:#e8f5e8;}";
echo ".phantom{background-color:#ffe8e8;}";
echo ".status{padding:10px;margin:10px 0;border-radius:4px;}";
echo ".success{background-color:#d4edda;color:#155724;border:1px solid #c3e6cb;}";
echo ".warning{background-color:#fff3cd;color:#856404;border:1px solid #ffeaa7;}";
echo ".error{background-color:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}";
echo ".refresh-btn{background:#007bff;color:white;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;margin:10px 0;}";
echo ".refresh-btn:hover{background:#0056b3;}";
echo "</style>";
echo "<script>";
echo "function autoRefresh() { setTimeout(function(){ location.reload(); }, 5000); }";
echo "window.onload = autoRefresh;";
echo "</script>";
echo "</head><body>";

echo "<div class='container'>";
echo "<h1>🎰 Roulette Draw Monitor</h1>";
echo "<p>This page automatically refreshes every 5 seconds to monitor for phantom draws.</p>";

try {
    // Get current timestamp for comparison
    $currentTime = date('Y-m-d H:i:s');
    echo "<div class='status success'>Last updated: $currentTime</div>";
    
    // Check current state
    echo "<h2>📊 Current System State</h2>";
    
    // Get roulette_state
    $stateQuery = "SELECT * FROM roulette_state ORDER BY id DESC LIMIT 1";
    $stateResult = $conn->query($stateQuery);
    
    if ($stateResult && $stateResult->num_rows > 0) {
        $state = $stateResult->fetch_assoc();
        echo "<table>";
        echo "<tr><th>Property</th><th>Value</th></tr>";
        echo "<tr><td>Last Draw</td><td>" . htmlspecialchars($state['last_draw'] ?? '') . "</td></tr>";
        echo "<tr><td>Next Draw</td><td>" . htmlspecialchars($state['next_draw'] ?? '') . "</td></tr>";
        echo "<tr><td>Current Draw</td><td>" . htmlspecialchars($state['current_draw'] ?? '') . "</td></tr>";
        echo "<tr><td>Updated At</td><td>" . htmlspecialchars($state['updated_at'] ?? '') . "</td></tr>";
        echo "</table>";
    } else {
        echo "<div class='status warning'>No roulette_state data found</div>";
    }
    
    // Get roulette_analytics
    $analyticsQuery = "SELECT current_draw_number FROM roulette_analytics WHERE id = 1";
    $analyticsResult = $conn->query($analyticsQuery);
    
    if ($analyticsResult && $analyticsResult->num_rows > 0) {
        $analytics = $analyticsResult->fetch_assoc();
        echo "<p><strong>Analytics Current Draw:</strong> " . htmlspecialchars($analytics['current_draw_number']) . "</p>";
    }
    
    // Monitor detailed_draw_results
    echo "<h2>🎯 Recent Draw Results</h2>";
    $drawsQuery = "SELECT * FROM detailed_draw_results ORDER BY id DESC LIMIT 10";
    $drawsResult = $conn->query($drawsQuery);
    
    if ($drawsResult && $drawsResult->num_rows > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Draw ID</th><th>Draw Number</th><th>Winning Number</th><th>Color</th><th>Created At</th><th>Status</th></tr>";
        
        $lastDrawNumber = null;
        while ($draw = $drawsResult->fetch_assoc()) {
            $isPhantom = false;
            $status = "Normal";
            
            // Check for potential phantom draws
            if ($lastDrawNumber !== null && ($draw['draw_number'] - $lastDrawNumber) > 1) {
                $isPhantom = true;
                $status = "⚠️ Gap detected";
            }
            
            $rowClass = $isPhantom ? "phantom" : "";
            
            echo "<tr class='$rowClass'>";
            echo "<td>" . htmlspecialchars($draw['id']) . "</td>";
            echo "<td>" . htmlspecialchars($draw['draw_id'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($draw['draw_number']) . "</td>";
            echo "<td>" . htmlspecialchars($draw['winning_number']) . "</td>";
            echo "<td>" . htmlspecialchars($draw['winning_color'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($draw['created_at'] ?? '') . "</td>";
            echo "<td>$status</td>";
            echo "</tr>";
            
            $lastDrawNumber = $draw['draw_number'];
        }
        echo "</table>";
    } else {
        echo "<div class='status success'>✓ No draw results found - clean state</div>";
    }
    
    // Monitor next_draw_winning_number
    echo "<h2>🔮 Upcoming Draw Numbers</h2>";
    $nextDrawQuery = "SELECT * FROM next_draw_winning_number ORDER BY draw_number DESC LIMIT 10";
    $nextDrawResult = $conn->query($nextDrawQuery);
    
    if ($nextDrawResult && $nextDrawResult->num_rows > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Draw Number</th><th>Winning Number</th><th>Source</th><th>Reason</th><th>Created At</th></tr>";
        
        while ($nextDraw = $nextDrawResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($nextDraw['id']) . "</td>";
            echo "<td>" . htmlspecialchars($nextDraw['draw_number']) . "</td>";
            echo "<td>" . htmlspecialchars($nextDraw['winning_number']) . "</td>";
            echo "<td>" . htmlspecialchars($nextDraw['source'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($nextDraw['reason'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($nextDraw['created_at'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='status success'>✓ No upcoming draw numbers set - clean state</div>";
    }
    
    // Check for recent log entries
    $logFile = __DIR__ . '/logs/auto_selection.log';
    if (file_exists($logFile)) {
        echo "<h2>📝 Recent Auto Selection Log</h2>";
        $logLines = file($logFile);
        $recentLines = array_slice($logLines, -10); // Last 10 lines
        
        echo "<div style='background:#f8f9fa;padding:10px;border-radius:4px;font-family:monospace;font-size:12px;'>";
        foreach ($recentLines as $line) {
            echo htmlspecialchars($line) . "<br>";
        }
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='status error'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<button class='refresh-btn' onclick='location.reload()'>🔄 Refresh Now</button>";
echo "<p><a href='test_db.php'>← Database Test</a> | <a href='tvdisplay/index.html'>TV Display →</a> | <a href='fix_draw_numbers.php'>Fix Draw Numbers →</a></p>";
echo "</div>";
echo "</body></html>";
?>
