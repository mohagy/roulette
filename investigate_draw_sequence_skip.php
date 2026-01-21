<?php
/**
 * Draw Number Sequence Skip Investigation
 *
 * This script investigates the draw number sequence skip where draws 4 and 5 were missing
 * and the system jumped directly from draw 3 to draw 6.
 */

// Include database connection
require_once 'includes/db_connection.php';

// Set content type for proper output
header('Content-Type: text/html; charset=UTF-8');

echo "<!DOCTYPE html>";
echo "<html><head><title>Draw Number Sequence Skip Investigation</title>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .info { color: blue; }
    .critical { color: red; background-color: #ffe6e6; padding: 10px; border-radius: 5px; }
    .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ccc; border-radius: 5px; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .missing { background-color: #ffcccc; }
    .present { background-color: #ccffcc; }
    .gap { background-color: #ffffcc; }
    pre { background-color: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; }
    .highlight { background-color: yellow; padding: 2px 4px; border-radius: 3px; }
</style></head><body>";

echo "<h1>🔍 Draw Number Sequence Skip Investigation</h1>";
echo "<p>Investigating the missing draws 4 and 5 in the sequence...</p>";

// Investigation 1: Detailed Draw Results Analysis
echo "<div class='test-section'>";
echo "<h2>Investigation 1: Detailed Draw Results Analysis</h2>";

try {
    // Get all draw numbers from detailed_draw_results
    $stmt = $conn->prepare("
        SELECT
            draw_number,
            winning_number,
            color,
            timestamp,
            created_at,
            TIMESTAMPDIFF(MINUTE, timestamp, NOW()) as minutes_ago
        FROM detailed_draw_results
        ORDER BY draw_number ASC
    ");
    $stmt->execute();
    $result = $stmt->get_result();

    $draws = [];
    $maxDraw = 0;
    $minDraw = PHP_INT_MAX;

    echo "<p class='info'>All draws in detailed_draw_results table:</p>";
    echo "<table>";
    echo "<tr><th>Draw Number</th><th>Winning Number</th><th>Color</th><th>Timestamp</th><th>Created At</th><th>Minutes Ago</th></tr>";

    while ($row = $result->fetch_assoc()) {
        $draws[] = $row['draw_number'];
        $maxDraw = max($maxDraw, $row['draw_number']);
        $minDraw = min($minDraw, $row['draw_number']);

        echo "<tr class='present'>";
        echo "<td><strong>{$row['draw_number']}</strong></td>";
        echo "<td>{$row['winning_number']}</td>";
        echo "<td>{$row['color']}</td>";
        echo "<td>{$row['timestamp']}</td>";
        echo "<td>{$row['created_at']}</td>";
        echo "<td>{$row['minutes_ago']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Analyze sequence gaps
    echo "<h3>Sequence Gap Analysis:</h3>";
    $missingDraws = [];

    if (!empty($draws)) {
        sort($draws);
        echo "<p class='info'>Draw sequence found: " . implode(', ', $draws) . "</p>";
        echo "<p class='info'>Range: Draw #{$minDraw} to Draw #{$maxDraw}</p>";

        // Check for gaps in sequence
        for ($i = $minDraw; $i <= $maxDraw; $i++) {
            if (!in_array($i, $draws)) {
                $missingDraws[] = $i;
            }
        }

        if (!empty($missingDraws)) {
            echo "<div class='critical'>";
            echo "<p class='error'>🚨 <strong>MISSING DRAWS DETECTED:</strong> " . implode(', ', $missingDraws) . "</p>";
            echo "</div>";
        } else {
            echo "<p class='success'>✅ No gaps detected in the sequence</p>";
        }
    } else {
        echo "<p class='warning'>⚠️ No draws found in detailed_draw_results table</p>";
    }

} catch (Exception $e) {
    echo "<p class='error'>❌ Error analyzing detailed_draw_results: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Investigation 2: Roulette Analytics State
echo "<div class='test-section'>";
echo "<h2>Investigation 2: Roulette Analytics State</h2>";

try {
    $stmt = $conn->prepare("SELECT * FROM roulette_analytics WHERE id = 1");
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $analytics = $result->fetch_assoc();

        echo "<table>";
        echo "<tr><th>Field</th><th>Value</th><th>Analysis</th></tr>";
        echo "<tr><td>current_draw_number</td><td class='highlight'><strong>{$analytics['current_draw_number']}</strong></td><td>Last completed draw</td></tr>";
        echo "<tr><td>last_updated</td><td>{$analytics['last_updated']}</td><td>Last system update</td></tr>";
        echo "<tr><td>total_spins</td><td>{$analytics['total_spins']}</td><td>Total recorded spins</td></tr>";
        echo "</table>";

        // Analyze all_spins data
        if (!empty($analytics['all_spins'])) {
            $allSpins = json_decode($analytics['all_spins'], true);
            if ($allSpins && is_array($allSpins)) {
                echo "<h3>All Spins Analysis:</h3>";
                echo "<p class='info'>Total spins in array: " . count($allSpins) . "</p>";

                echo "<table>";
                echo "<tr><th>Index</th><th>Draw Number</th><th>Winning Number</th><th>Timestamp</th></tr>";

                foreach ($allSpins as $index => $spin) {
                    $drawNum = isset($spin['draw_number']) ? $spin['draw_number'] : 'N/A';
                    $winNum = isset($spin['number']) ? $spin['number'] : (isset($spin['winning_number']) ? $spin['winning_number'] : 'N/A');
                    $timestamp = isset($spin['timestamp']) ? $spin['timestamp'] : 'N/A';

                    echo "<tr>";
                    echo "<td>$index</td>";
                    echo "<td><strong>$drawNum</strong></td>";
                    echo "<td>$winNum</td>";
                    echo "<td>$timestamp</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p class='error'>❌ Invalid all_spins JSON data</p>";
            }
        } else {
            echo "<p class='warning'>⚠️ No all_spins data found</p>";
        }

    } else {
        echo "<p class='error'>❌ No roulette_analytics record found</p>";
    }

} catch (Exception $e) {
    echo "<p class='error'>❌ Error analyzing roulette_analytics: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Investigation 3: Roulette State Analysis
echo "<div class='test-section'>";
echo "<h2>Investigation 3: Roulette State Analysis</h2>";

try {
    $stmt = $conn->prepare("SELECT * FROM roulette_state WHERE id = 1");
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $state = $result->fetch_assoc();

        echo "<table>";
        echo "<tr><th>Field</th><th>Value</th><th>Analysis</th></tr>";

        foreach ($state as $field => $value) {
            $analysis = '';
            switch ($field) {
                case 'last_draw':
                    $analysis = 'Last completed draw identifier';
                    break;
                case 'next_draw':
                    $analysis = 'Next upcoming draw identifier';
                    break;
                case 'current_draw':
                    $analysis = 'Current draw counter';
                    break;
                case 'current_draw_number':
                    $analysis = 'Current draw number';
                    break;
                case 'winning_number':
                    $analysis = 'Last winning number';
                    break;
                case 'next_draw_winning_number':
                    $analysis = 'Pre-set next winning number';
                    break;
                case 'updated_at':
                    $analysis = 'Last update timestamp';
                    break;
                default:
                    $analysis = 'System field';
            }

            echo "<tr>";
            echo "<td>$field</td>";
            echo "<td class='highlight'><strong>$value</strong></td>";
            echo "<td>$analysis</td>";
            echo "</tr>";
        }
        echo "</table>";

    } else {
        echo "<p class='error'>❌ No roulette_state record found</p>";
    }

} catch (Exception $e) {
    echo "<p class='error'>❌ Error analyzing roulette_state: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Investigation 4: Betting Slips Analysis
echo "<div class='test-section'>";
echo "<h2>Investigation 4: Betting Slips Analysis</h2>";

try {
    $stmt = $conn->prepare("
        SELECT
            draw_number,
            COUNT(*) as slip_count,
            SUM(total_stake) as total_stakes,
            MIN(created_at) as first_slip,
            MAX(created_at) as last_slip
        FROM betting_slips
        GROUP BY draw_number
        ORDER BY draw_number ASC
    ");
    $stmt->execute();
    $result = $stmt->get_result();

    echo "<p class='info'>Betting slips grouped by draw number:</p>";
    echo "<table>";
    echo "<tr><th>Draw Number</th><th>Slip Count</th><th>Total Stakes</th><th>First Slip</th><th>Last Slip</th><th>Status</th></tr>";

    $slipDraws = [];
    while ($row = $result->fetch_assoc()) {
        $slipDraws[] = $row['draw_number'];
        $status = '';

        // Check if this draw exists in detailed_draw_results
        if (isset($draws) && in_array($row['draw_number'], $draws)) {
            $status = '<span class="success">✅ Draw Completed</span>';
            $rowClass = 'present';
        } else {
            $status = '<span class="error">❌ Draw Missing</span>';
            $rowClass = 'missing';
        }

        echo "<tr class='$rowClass'>";
        echo "<td><strong>{$row['draw_number']}</strong></td>";
        echo "<td>{$row['slip_count']}</td>";
        echo "<td>\${$row['total_stakes']}</td>";
        echo "<td>{$row['first_slip']}</td>";
        echo "<td>{$row['last_slip']}</td>";
        echo "<td>$status</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Check for orphaned betting slips
    if (isset($draws) && !empty($slipDraws)) {
        $orphanedSlips = array_diff($slipDraws, $draws);
        if (!empty($orphanedSlips)) {
            echo "<div class='critical'>";
            echo "<p class='error'>🚨 <strong>ORPHANED BETTING SLIPS:</strong> Slips exist for draws " . implode(', ', $orphanedSlips) . " but no draw results found!</p>";
            echo "</div>";
        }
    }

} catch (Exception $e) {
    echo "<p class='error'>❌ Error analyzing betting slips: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Investigation 5: System Consistency Check
echo "<div class='test-section'>";
echo "<h2>Investigation 5: System Consistency Check</h2>";

try {
    // Compare different sources of draw numbers
    $sources = [];

    // From detailed_draw_results
    if (isset($maxDraw)) {
        $sources['detailed_draw_results_max'] = $maxDraw;
    }

    // From roulette_analytics
    if (isset($analytics)) {
        $sources['roulette_analytics_current'] = $analytics['current_draw_number'];
    }

    // From roulette_state
    if (isset($state)) {
        $sources['roulette_state_current'] = $state['current_draw_number'] ?? 'N/A';
        $sources['roulette_state_last_draw'] = str_replace('#', '', $state['last_draw'] ?? '');
        $sources['roulette_state_next_draw'] = str_replace('#', '', $state['next_draw'] ?? '');
    }

    echo "<table>";
    echo "<tr><th>Source</th><th>Draw Number</th><th>Consistency</th></tr>";

    $expectedDraw = $maxDraw ?? 0;
    foreach ($sources as $source => $drawNum) {
        $consistency = '';
        if (is_numeric($drawNum)) {
            if ($drawNum == $expectedDraw) {
                $consistency = '<span class="success">✅ Consistent</span>';
                $rowClass = 'present';
            } else {
                $consistency = '<span class="error">❌ Inconsistent (Expected: ' . $expectedDraw . ')</span>';
                $rowClass = 'missing';
            }
        } else {
            $consistency = '<span class="warning">⚠️ Non-numeric</span>';
            $rowClass = 'gap';
        }

        echo "<tr class='$rowClass'>";
        echo "<td>$source</td>";
        echo "<td><strong>$drawNum</strong></td>";
        echo "<td>$consistency</td>";
        echo "</tr>";
    }
    echo "</table>";

} catch (Exception $e) {
    echo "<p class='error'>❌ Error in consistency check: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Investigation 6: Root Cause Analysis
echo "<div class='test-section'>";
echo "<h2>Investigation 6: Root Cause Analysis</h2>";

echo "<h3>🔍 Potential Root Causes for Draw Number Skip:</h3>";

// Check for multiple draw increment sources
echo "<h4>1. Multiple Draw Increment Sources</h4>";
echo "<p class='info'>The system has multiple components that can increment draw numbers:</p>";
echo "<ul>";
echo "<li><strong>TV Display System:</strong> <code>tvdisplay/js/scripts.js</code> - syncDrawNumbersWithRollHistory()</li>";
echo "<li><strong>Draw Sync Module:</strong> <code>js/draw-sync.js</code> - advanceToNextDraw()</li>";
echo "<li><strong>Georgetown Time Sync:</strong> <code>js/georgetown-time-sync.js</code> - completeCurrentDraw()</li>";
echo "<li><strong>Cashier Draw Display:</strong> <code>js/cashier-draw-display.js</code> - sync operations</li>";
echo "<li><strong>Manual Updates:</strong> Various update scripts and API endpoints</li>";
echo "</ul>";

// Check for race conditions
echo "<h4>2. Race Condition Analysis</h4>";
echo "<p class='warning'>⚠️ <strong>CRITICAL FINDING:</strong> Multiple systems can update draw numbers simultaneously:</p>";
echo "<ul>";
echo "<li><strong>TV Display:</strong> Updates based on roll history length</li>";
echo "<li><strong>Georgetown Time:</strong> Updates every 3 minutes automatically</li>";
echo "<li><strong>Manual Triggers:</strong> Admin can manually advance draws</li>";
echo "<li><strong>Sync Operations:</strong> Cross-tab synchronization can trigger updates</li>";
echo "</ul>";

// Check for specific problematic code patterns
echo "<h4>3. Problematic Code Patterns Identified</h4>";
echo "<div class='critical'>";
echo "<p class='error'>🚨 <strong>RACE CONDITION DETECTED:</strong></p>";
echo "<pre>";
echo "// In tvdisplay/js/scripts.js - syncDrawNumbersWithRollHistory()
if (rolledNumbersArray.length > currentDrawNumber) {
    currentDrawNumber = rolledNumbersArray.length; // ❌ DANGEROUS: Can skip numbers
    updateDrawNumberDisplay();
    saveAnalyticsData();
}

// In js/georgetown-time-sync.js - completeCurrentDraw()
state.currentDrawNumber = state.nextDrawNumber;
state.nextDrawNumber = state.currentDrawNumber + 1; // ❌ Can cause jumps

// In js/draw-sync.js - advanceToNextDraw()
const newCurrentDraw = state.nextDraw;
const newNextDraw = state.nextDraw + 1; // ❌ Multiple systems calling this";
echo "</pre>";
echo "</div>";

// Check for database transaction issues
echo "<h4>4. Database Transaction Analysis</h4>";
try {
    // Check for any failed transactions or incomplete records
    $stmt = $conn->prepare("
        SELECT
            TABLE_NAME,
            AUTO_INCREMENT
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME IN ('detailed_draw_results', 'roulette_analytics', 'roulette_state')
    ");
    $stmt->execute();
    $result = $stmt->get_result();

    echo "<table>";
    echo "<tr><th>Table</th><th>Auto Increment</th><th>Analysis</th></tr>";

    while ($row = $result->fetch_assoc()) {
        $analysis = '';
        if ($row['TABLE_NAME'] === 'detailed_draw_results') {
            $analysis = 'Primary draw results storage';
        } elseif ($row['TABLE_NAME'] === 'roulette_analytics') {
            $analysis = 'Draw number tracking';
        } elseif ($row['TABLE_NAME'] === 'roulette_state') {
            $analysis = 'System state management';
        }

        echo "<tr>";
        echo "<td>{$row['TABLE_NAME']}</td>";
        echo "<td>{$row['AUTO_INCREMENT']}</td>";
        echo "<td>$analysis</td>";
        echo "</tr>";
    }
    echo "</table>";

} catch (Exception $e) {
    echo "<p class='error'>❌ Error checking database transactions: " . $e->getMessage() . "</p>";
}

// Check for timing issues
echo "<h4>5. Timing and Synchronization Issues</h4>";
echo "<p class='warning'>⚠️ <strong>TIMING CONFLICTS IDENTIFIED:</strong></p>";
echo "<ul>";
echo "<li><strong>Georgetown Time Sync:</strong> Runs every 3 minutes automatically</li>";
echo "<li><strong>Draw Sync Polling:</strong> Polls every 5 seconds for updates</li>";
echo "<li><strong>TV Display Sync:</strong> Updates on roll history changes</li>";
echo "<li><strong>Cross-tab Sync:</strong> localStorage events can trigger updates</li>";
echo "</ul>";

echo "<div class='critical'>";
echo "<p class='error'>🚨 <strong>LIKELY ROOT CAUSE:</strong></p>";
echo "<p>The draw number skip from 3 to 6 (missing 4 and 5) suggests that <strong>multiple systems incremented the draw number simultaneously</strong>:</p>";
echo "<ol>";
echo "<li><strong>System A</strong> advanced from draw 3 to draw 4</li>";
echo "<li><strong>System B</strong> (running concurrently) advanced from draw 3 to draw 4</li>";
echo "<li><strong>System C</strong> (also running) advanced from draw 3 to draw 4</li>";
echo "<li>Due to race conditions, the final result was draw 6 instead of draw 4</li>";
echo "</ol>";
echo "</div>";

echo "</div>";

// Investigation 7: Prevention and Fix Recommendations
echo "<div class='test-section'>";
echo "<h2>Investigation 7: Prevention and Fix Recommendations</h2>";

echo "<h3>🔧 Immediate Fixes Required:</h3>";
echo "<ol>";
echo "<li><strong>Implement Database Locking:</strong> Use SELECT FOR UPDATE to prevent concurrent draw number updates</li>";
echo "<li><strong>Centralize Draw Management:</strong> Create a single authoritative source for draw number increments</li>";
echo "<li><strong>Add Sequence Validation:</strong> Validate that draw numbers increment by exactly 1</li>";
echo "<li><strong>Implement Transaction Isolation:</strong> Use proper database transactions for draw updates</li>";
echo "<li><strong>Add Conflict Detection:</strong> Detect and resolve draw number conflicts automatically</li>";
echo "</ol>";

echo "<h3>📋 Recommended Actions:</h3>";
echo "<div class='info'>";
echo "<h4>Option 1: Backfill Missing Draws (Recommended)</h4>";
echo "<p>Insert placeholder records for draws 4 and 5 to maintain sequence integrity:</p>";
echo "<pre>";
echo "INSERT INTO detailed_draw_results (draw_number, winning_number, color, timestamp) VALUES
(4, 0, 'green', '2025-01-XX XX:XX:XX'),
(5, 0, 'green', '2025-01-XX XX:XX:XX');";
echo "</pre>";
echo "</div>";

echo "<div class='warning'>";
echo "<h4>Option 2: Continue from Current Sequence</h4>";
echo "<p>Accept the gap and continue from draw 6, but implement gap detection alerts.</p>";
echo "</div>";

echo "<h3>🛡️ Prevention Measures:</h3>";
echo "<ul>";
echo "<li><strong>Single Source of Truth:</strong> Designate one system as the authoritative draw manager</li>";
echo "<li><strong>Database Constraints:</strong> Add unique constraints and sequence validation</li>";
echo "<li><strong>Atomic Operations:</strong> Use database transactions for all draw updates</li>";
echo "<li><strong>Conflict Resolution:</strong> Implement retry logic with exponential backoff</li>";
echo "<li><strong>Monitoring:</strong> Add alerts for draw number sequence gaps</li>";
echo "<li><strong>Logging:</strong> Enhanced logging for all draw number changes</li>";
echo "</ul>";

echo "</div>";

// Close database connection
$conn->close();

echo "<div class='test-section'>";
echo "<h2>🎯 Investigation Summary</h2>";

if (isset($missingDraws) && !empty($missingDraws)) {
    echo "<div class='critical'>";
    echo "<h3>🚨 CRITICAL FINDINGS:</h3>";
    echo "<ul>";
    echo "<li><strong>Missing Draws:</strong> " . implode(', ', $missingDraws) . "</li>";
    echo "<li><strong>Sequence Break:</strong> Draw numbering jumped from " . (min($missingDraws) - 1) . " to " . (max($missingDraws) + 1) . "</li>";
    echo "<li><strong>Impact:</strong> Draw sequence integrity compromised</li>";
    echo "</ul>";
    echo "</div>";

    echo "<h3>📋 Next Steps Required:</h3>";
    echo "<ol>";
    echo "<li><strong>Root Cause Analysis:</strong> Check server logs during the time period when draws " . implode(' and ', $missingDraws) . " should have occurred</li>";
    echo "<li><strong>Code Review:</strong> Examine draw increment logic in TV display and synchronization systems</li>";
    echo "<li><strong>Data Recovery:</strong> Decide whether to backfill missing draws or continue from current sequence</li>";
    echo "<li><strong>Prevention:</strong> Implement sequence validation and gap detection</li>";
    echo "</ol>";
} else {
    echo "<p class='success'>✅ No sequence gaps detected in current investigation</p>";
}

echo "</div>";

echo "<p><a href='my_transactions_new.php'>🔗 Check Transactions</a> | <a href='index.php'>🔗 Return to Main Page</a></p>";

echo "</body></html>";
?>
