<?php
/**
 * Investigate Draw 521
 * 
 * This script investigates the mysterious draw 521 that appeared after the database reset.
 */

// Include database connection
require_once 'php/db_connect.php';

// Set content type to HTML for better display
header('Content-Type: text/html; charset=UTF-8');

echo "<!DOCTYPE html>";
echo "<html><head><title>Investigate Draw 521</title>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background-color:#f2f2f2;}</style>";
echo "</head><body>";
echo "<h1>🔍 Investigation: Draw 521 Mystery</h1>";

try {
    echo "<h2>Current Database State</h2>";
    
    // Check detailed_draw_results table
    echo "<h3>📊 Detailed Draw Results</h3>";
    $query = "SELECT * FROM detailed_draw_results ORDER BY id DESC LIMIT 10";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Draw ID</th><th>Draw Number</th><th>Winning Number</th><th>Color</th><th>Created At</th><th>Notes</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            $highlight = ($row['draw_number'] == 521) ? 'style="background-color:#ffcccc;"' : '';
            echo "<tr $highlight>";
            echo "<td>" . htmlspecialchars($row['id'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['draw_id'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['draw_number'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['winning_number'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['winning_color'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['created_at'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['notes'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='info'>No entries found in detailed_draw_results table.</p>";
    }
    
    // Check next_draw_winning_number table
    echo "<h3>🔮 Next Draw Winning Numbers</h3>";
    $query = "SELECT * FROM next_draw_winning_number ORDER BY id DESC LIMIT 10";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Draw Number</th><th>Winning Number</th><th>Source</th><th>Reason</th><th>Created At</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            $highlight = ($row['draw_number'] == 521 || $row['draw_number'] == 522) ? 'style="background-color:#ffcccc;"' : '';
            echo "<tr $highlight>";
            echo "<td>" . htmlspecialchars($row['id'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['draw_number'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['winning_number'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['source'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['reason'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['created_at'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='info'>No entries found in next_draw_winning_number table.</p>";
    }
    
    // Check roulette_analytics table
    echo "<h3>📈 Roulette Analytics</h3>";
    $query = "SELECT * FROM roulette_analytics WHERE id = 1";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $analytics = $result->fetch_assoc();
        echo "<table>";
        echo "<tr><th>Property</th><th>Value</th></tr>";
        echo "<tr><td>Current Draw Number</td><td>" . htmlspecialchars($analytics['current_draw_number'] ?? 'NULL') . "</td></tr>";
        echo "<tr><td>Last Updated</td><td>" . htmlspecialchars($analytics['last_updated'] ?? 'NULL') . "</td></tr>";
        echo "<tr><td>Created At</td><td>" . htmlspecialchars($analytics['created_at'] ?? 'NULL') . "</td></tr>";
        echo "</table>";
    } else {
        echo "<p class='error'>No analytics data found!</p>";
    }
    
    // Check roulette_state table
    echo "<h3>🎮 Roulette State</h3>";
    $query = "SELECT * FROM roulette_state WHERE id = 1";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $state = $result->fetch_assoc();
        echo "<table>";
        echo "<tr><th>Property</th><th>Value</th></tr>";
        echo "<tr><td>Last Draw</td><td>" . htmlspecialchars($state['last_draw'] ?? 'NULL') . "</td></tr>";
        echo "<tr><td>Next Draw</td><td>" . htmlspecialchars($state['next_draw'] ?? 'NULL') . "</td></tr>";
        echo "<tr><td>Current Draw</td><td>" . htmlspecialchars($state['current_draw'] ?? 'NULL') . "</td></tr>";
        echo "<tr><td>Updated At</td><td>" . htmlspecialchars($state['updated_at'] ?? 'NULL') . "</td></tr>";
        echo "</table>";
    } else {
        echo "<p class='error'>No state data found!</p>";
    }
    
    // Check for any processes that might be creating draws
    echo "<h2>🔍 Investigation Analysis</h2>";
    
    echo "<h3>Possible Causes:</h3>";
    echo "<ol>";
    echo "<li><strong>Background Process:</strong> A background script or cron job might be running that creates draws automatically.</li>";
    echo "<li><strong>Page Load Trigger:</strong> Loading certain pages might trigger draw creation through JavaScript or PHP.</li>";
    echo "<li><strong>Database Trigger:</strong> A database trigger might be creating draws when certain conditions are met.</li>";
    echo "<li><strong>Old Session Data:</strong> Cached session data or browser storage might be triggering old draw numbers.</li>";
    echo "<li><strong>API Calls:</strong> External API calls or AJAX requests might be creating draws.</li>";
    echo "</ol>";
    
    echo "<h3>Next Steps:</h3>";
    echo "<ol>";
    echo "<li>Check if any background processes are running (cron jobs, scheduled tasks)</li>";
    echo "<li>Monitor network requests when loading pages to see what APIs are being called</li>";
    echo "<li>Check browser developer tools for any JavaScript errors or automatic requests</li>";
    echo "<li>Look for any database triggers that might be creating draws</li>";
    echo "<li>Clear browser cache and session storage</li>";
    echo "</ol>";
    
    // Check for database triggers
    echo "<h3>🔧 Database Triggers Check</h3>";
    $query = "SHOW TRIGGERS";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        echo "<table>";
        echo "<tr><th>Trigger</th><th>Event</th><th>Table</th><th>Statement</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['Trigger'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['Event'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['Table'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['Statement'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='success'>✓ No database triggers found</p>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<p><a href='test_db.php'>← Back to Database Test</a> | <a href='monitor_draws.php'>Monitor Draws →</a></p>";
echo "</body></html>";
?>
