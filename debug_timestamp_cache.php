<?php
/**
 * Debug Timestamp and Cache Issues
 * 
 * This script helps debug the timestamp calculation discrepancy
 * and identifies potential caching issues.
 */

echo "<!DOCTYPE html>";
echo "<html><head><title>Debug Timestamp & Cache Issues</title>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} .warning{color:orange;}</style>";
echo "</head><body>";
echo "<h1>🔍 Debug Timestamp & Cache Issues</h1>";

// Current timestamp analysis
$currentTime = time();
$currentCalculation = floor($currentTime) % 1000;

echo "<h2>📊 Current Timestamp Analysis</h2>";
echo "<p><strong>Current Time:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<p><strong>Current Unix Timestamp:</strong> $currentTime</p>";
echo "<p><strong>Current JS Calculation:</strong> Math.floor($currentTime) % 1000 = <strong>$currentCalculation</strong></p>";

// Analysis of draw 521 creation time
$drawCreatedTime = "2025-05-23 22:08:41";
$drawTimestamp = strtotime($drawCreatedTime);
$drawCalculation = floor($drawTimestamp) % 1000;

echo "<h2>🎯 Draw 521 Analysis</h2>";
echo "<p><strong>Draw Created At:</strong> $drawCreatedTime</p>";
echo "<p><strong>Draw Timestamp:</strong> $drawTimestamp</p>";
echo "<p><strong>Expected Calculation:</strong> Math.floor($drawTimestamp) % 1000 = <strong>$drawCalculation</strong></p>";
echo "<p><strong>Actual Draw Number:</strong> 521</p>";

if ($drawCalculation == 521) {
    echo "<p class='success'>✅ Timestamp calculation matches draw number 521</p>";
} else {
    echo "<p class='warning'>⚠️ <strong>DISCREPANCY DETECTED!</strong></p>";
    echo "<p>Expected: $drawCalculation, Actual: 521</p>";
    echo "<p>Difference: " . abs($drawCalculation - 521) . "</p>";
    
    // Check if 521 could come from a different timestamp
    $targetTimestamp = null;
    for ($i = $drawTimestamp - 3600; $i <= $drawTimestamp + 3600; $i++) {
        if (floor($i) % 1000 == 521) {
            $targetTimestamp = $i;
            break;
        }
    }
    
    if ($targetTimestamp) {
        echo "<p class='info'>📅 Draw number 521 would be generated at timestamp: $targetTimestamp</p>";
        echo "<p class='info'>📅 That corresponds to: " . date('Y-m-d H:i:s', $targetTimestamp) . "</p>";
        echo "<p class='info'>📅 Time difference: " . ($targetTimestamp - $drawTimestamp) . " seconds</p>";
    }
}

echo "<h2>🧠 Possible Caching Sources</h2>";
echo "<ul>";
echo "<li><strong>Browser localStorage:</strong> May store old draw numbers</li>";
echo "<li><strong>Browser sessionStorage:</strong> May store session-specific data</li>";
echo "<li><strong>Browser HTTP cache:</strong> May cache API responses</li>";
echo "<li><strong>JavaScript variables:</strong> May retain old values across page interactions</li>";
echo "<li><strong>DOM elements:</strong> May display cached values</li>";
echo "<li><strong>Server-side caching:</strong> PHP opcache or database query cache</li>";
echo "</ul>";

echo "<h2>🛠️ Cache Prevention Strategy</h2>";
echo "<ol>";
echo "<li><strong>HTTP Headers:</strong> Add no-cache headers to prevent browser caching</li>";
echo "<li><strong>Cache-busting URLs:</strong> Add timestamps to all resource requests</li>";
echo "<li><strong>Storage clearing:</strong> Clear localStorage/sessionStorage on page load</li>";
echo "<li><strong>Fresh API calls:</strong> Force fresh data retrieval with cache-busting parameters</li>";
echo "<li><strong>Debug logging:</strong> Track where draw numbers come from</li>";
echo "</ol>";

// Check current browser storage (this will be shown in JavaScript)
echo "<h2>🔍 Browser Storage Check</h2>";
echo "<div id='storage-info'></div>";

echo "<script>";
echo "document.addEventListener('DOMContentLoaded', function() {";
echo "  const storageDiv = document.getElementById('storage-info');";
echo "  let html = '<h3>LocalStorage Contents:</h3><ul>';";
echo "  ";
echo "  if (typeof(Storage) !== 'undefined') {";
echo "    for (let i = 0; i < localStorage.length; i++) {";
echo "      const key = localStorage.key(i);";
echo "      const value = localStorage.getItem(key);";
echo "      html += '<li><strong>' + key + ':</strong> ' + value + '</li>';";
echo "    }";
echo "    html += '</ul><h3>SessionStorage Contents:</h3><ul>';";
echo "    ";
echo "    for (let i = 0; i < sessionStorage.length; i++) {";
echo "      const key = sessionStorage.key(i);";
echo "      const value = sessionStorage.getItem(key);";
echo "      html += '<li><strong>' + key + ':</strong> ' + value + '</li>';";
echo "    }";
echo "    html += '</ul>';";
echo "  } else {";
echo "    html += '<li>Storage not supported</li></ul>';";
echo "  }";
echo "  ";
echo "  storageDiv.innerHTML = html;";
echo "});";
echo "</script>";

echo "<p><a href='complete_system_reset.php'>← Complete System Reset</a> | <a href='tvdisplay/index.html'>TV Display →</a></p>";
echo "</body></html>";
?>
