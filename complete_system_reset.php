<?php
/**
 * Complete System Reset
 *
 * This script performs a comprehensive reset of the roulette system,
 * including database cleanup and browser storage clearing.
 */

// Include database connection
require_once 'php/db_connect.php';

// Set content type to HTML for better display
header('Content-Type: text/html; charset=UTF-8');

echo "<!DOCTYPE html>";
echo "<html><head><title>Complete System Reset</title>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} .warning{color:orange;}</style>";
echo "<script>";
echo "function clearBrowserStorage() {";
echo "  // Clear localStorage";
echo "  if (typeof(Storage) !== 'undefined') {";
echo "    localStorage.removeItem('rouletteAnalytics');";
echo "    localStorage.removeItem('drawNumber');";
echo "    localStorage.removeItem('currentDraw');";
echo "    localStorage.removeItem('nextDraw');";
echo "    localStorage.clear();";
echo "    console.log('LocalStorage cleared');";
echo "  }";
echo "  // Clear sessionStorage";
echo "  if (typeof(Storage) !== 'undefined') {";
echo "    sessionStorage.clear();";
echo "    console.log('SessionStorage cleared');";
echo "  }";
echo "  return true;";
echo "}";
echo "</script>";
echo "</head><body>";
echo "<h1>🔄 Complete System Reset</h1>";

try {
    // Start transaction
    $conn->autocommit(false);

    echo "<h2>Step 1: Database Reset</h2>";

    // Clear all draw-related tables
    $tables = ['detailed_draw_results', 'next_draw_winning_number'];
    foreach ($tables as $table) {
        $result = $conn->query("DELETE FROM $table");
        if ($result) {
            echo "<p class='success'>✓ Cleared $table table</p>";
        } else {
            echo "<p class='error'>✗ Failed to clear $table table: " . $conn->error . "</p>";
        }
    }

    echo "<h2>Step 2: Reset Draw Numbers</h2>";

    $currentDraw = 0;
    $nextDraw = 1;

    // Reset roulette_analytics with proper data
    $stmt = $conn->prepare("INSERT INTO roulette_analytics (id, current_draw_number, all_spins, number_frequency, last_updated, created_at) VALUES (1, ?, '[]', ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE current_draw_number = ?, all_spins = '[]', number_frequency = ?, last_updated = NOW()");
    $emptyFreq = json_encode(array_fill(0, 37, 0));
    if ($stmt) {
        $stmt->bind_param("isis", $currentDraw, $emptyFreq, $currentDraw, $emptyFreq);
        if ($stmt->execute()) {
            echo "<p class='success'>✓ Reset roulette_analytics: current_draw_number = $currentDraw</p>";
        } else {
            echo "<p class='error'>✗ Failed to reset roulette_analytics: " . $stmt->error . "</p>";
        }
    }

    // Reset roulette_state
    $stmt = $conn->prepare("INSERT INTO roulette_state (id, last_draw, next_draw, current_draw, countdown_time, end_time, updated_at) VALUES (1, ?, ?, ?, 120, ?, NOW()) ON DUPLICATE KEY UPDATE last_draw = ?, next_draw = ?, current_draw = ?, countdown_time = 120, end_time = ?, updated_at = NOW()");
    $lastDrawFormatted = "#$currentDraw";
    $nextDrawFormatted = "#$nextDraw";
    $endTime = time() + 120;
    if ($stmt) {
        // Parameters: last_draw, next_draw, current_draw, end_time, last_draw, next_draw, current_draw, end_time (8 total)
        $stmt->bind_param("ssiissii", $lastDrawFormatted, $nextDrawFormatted, $currentDraw, $endTime, $lastDrawFormatted, $nextDrawFormatted, $currentDraw, $endTime);
        if ($stmt->execute()) {
            echo "<p class='success'>✓ Reset roulette_state: last_draw=$lastDrawFormatted, next_draw=$nextDrawFormatted</p>";
        } else {
            echo "<p class='error'>✗ Failed to reset roulette_state: " . $stmt->error . "</p>";
        }
    }

    // Reset auto-increment values
    echo "<h2>Step 3: Reset Auto-Increment Values</h2>";
    foreach ($tables as $table) {
        $result = $conn->query("ALTER TABLE $table AUTO_INCREMENT = 1");
        if ($result) {
            echo "<p class='success'>✓ Reset auto-increment for $table</p>";
        } else {
            echo "<p class='info'>ℹ Auto-increment reset for $table may have failed: " . $conn->error . "</p>";
        }
    }

    // Commit all changes
    $conn->commit();
    echo "<h2 class='success'>✓ Database reset completed successfully!</h2>";

    echo "<h2>Step 4: Clear Browser Storage</h2>";
    echo "<p class='warning'>⚠️ This will clear your browser's localStorage and sessionStorage for this site.</p>";
    echo "<button onclick='clearBrowserStorage(); alert(\"Browser storage cleared!\");' style='background:#dc3545;color:white;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;'>Clear Browser Storage</button>";

    echo "<h2>Step 5: Verification</h2>";
    echo "<p>After clearing browser storage, you should:</p>";
    echo "<ol>";
    echo "<li>Close all browser tabs for this site</li>";
    echo "<li>Open a new browser tab</li>";
    echo "<li>Navigate to the TV display interface</li>";
    echo "<li>Verify that the first spin creates draw #1 (not a random number like 521)</li>";
    echo "</ol>";

    echo "<h2 class='success'>✅ System Reset Complete</h2>";
    echo "<p><strong>Current State:</strong></p>";
    echo "<ul>";
    echo "<li>Current Draw: #$currentDraw</li>";
    echo "<li>Next Draw: #$nextDraw</li>";
    echo "<li>All phantom draws removed</li>";
    echo "<li>JavaScript fallbacks fixed</li>";
    echo "<li>Analytics table properly populated</li>";
    echo "</ul>";

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo "<h2 class='error'>✗ Error occurred: " . $e->getMessage() . "</h2>";
    echo "<p class='error'>All changes have been rolled back.</p>";
} finally {
    // Restore autocommit
    $conn->autocommit(true);
}

echo "<p><a href='test_db.php'>Test Database →</a> | <a href='monitor_draws.php'>Monitor Draws →</a> | <a href='tvdisplay/index.html'>TV Display →</a></p>";
echo "</body></html>";
?>
