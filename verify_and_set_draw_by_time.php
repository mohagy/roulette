<?php
/**
 * Verify and Set Draw Number Based on Current Time
 * 
 * This script checks the current time and calculates what the draw number should be,
 * then verifies if the database has the correct draw number. If not, it sets it.
 * 
 * Draws occur every 3 minutes (180 seconds), starting from draw #1 at midnight.
 */

// Include database connection
require_once 'db_config.php';

// Set timezone (adjust if needed)
date_default_timezone_set('America/Guyana');

// Set headers
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Verify and Set Draw Number by Time</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #667eea;
            margin-bottom: 20px;
        }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .success-box {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .warning-box {
            background: #fff3e0;
            border-left: 4px solid #ff9800;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .error-box {
            background: #ffebee;
            border-left: 4px solid #f44336;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px 0 0;
        }
        .button:hover {
            background: #5568d3;
        }
        .button-danger {
            background: #f44336;
        }
        .button-danger:hover {
            background: #d32f2f;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f5f5f5;
            font-weight: bold;
        }
        .highlight {
            background: #fff9c4;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🕐 Verify and Set Draw Number by Time</h1>

<?php
try {
    // Get current time
    $now = new DateTime();
    $currentTime = $now->getTimestamp();
    
    // Get midnight of today
    $midnight = new DateTime('today 00:00:00');
    $midnightTimestamp = $midnight->getTimestamp();
    
    // Calculate seconds since midnight
    $secondsSinceMidnight = $currentTime - $midnightTimestamp;
    
    // Calculate expected draw number
    // Draws occur every 3 minutes (180 seconds), starting from draw #1
    // Draw #1 starts at 00:00:00, Draw #2 at 00:03:00, etc.
    $drawInterval = 180; // 3 minutes in seconds
    $expectedDrawNumber = floor($secondsSinceMidnight / $drawInterval) + 1;
    
    // Get current draw number from database
    $currentDrawNumber = null;
    $nextDrawNumber = null;
    
    // Try to get from roulette_analytics first
    $stmt = $pdo->prepare("SELECT current_draw_number FROM roulette_analytics WHERE id = 1 LIMIT 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $currentDrawNumber = intval($result['current_draw_number']);
    }
    
    // Also check roulette_game_state
    $stmt = $pdo->prepare("SELECT current_draw_number, next_draw_number FROM roulette_game_state WHERE id = 1 LIMIT 1");
    $stmt->execute();
    $gameState = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($gameState) {
        if ($currentDrawNumber === null) {
            $currentDrawNumber = intval($gameState['current_draw_number']);
        }
        $nextDrawNumber = intval($gameState['next_draw_number']);
    }
    
    // Calculate next expected draw number
    $expectedNextDrawNumber = $expectedDrawNumber + 1;
    
    // Calculate time until next draw
    $nextDrawTime = $midnightTimestamp + ($expectedDrawNumber * $drawInterval);
    $secondsUntilNextDraw = $nextDrawTime - $currentTime;
    $minutesUntilNextDraw = floor($secondsUntilNextDraw / 60);
    $secondsRemaining = $secondsUntilNextDraw % 60;
    
    // Display current information
    echo '<div class="info-box">';
    echo '<strong>Current Time:</strong> ' . $now->format('Y-m-d H:i:s') . '<br>';
    echo '<strong>Seconds Since Midnight:</strong> ' . number_format($secondsSinceMidnight) . ' seconds<br>';
    echo '<strong>Expected Draw Number:</strong> <span class="highlight">#' . $expectedDrawNumber . '</span><br>';
    echo '<strong>Expected Next Draw:</strong> #' . $expectedNextDrawNumber . '<br>';
    echo '<strong>Time Until Next Draw:</strong> ' . $minutesUntilNextDraw . 'm ' . $secondsRemaining . 's';
    echo '</div>';
    
    // Display database information
    echo '<table>';
    echo '<tr><th>Source</th><th>Current Draw</th><th>Next Draw</th><th>Status</th></tr>';
    
    $analyticsStatus = 'N/A';
    if ($currentDrawNumber !== null) {
        if ($currentDrawNumber == $expectedDrawNumber) {
            $analyticsStatus = '<span style="color: green;">✓ Correct</span>';
        } else {
            $analyticsStatus = '<span style="color: red;">✗ Incorrect (Expected: #' . $expectedDrawNumber . ')</span>';
        }
        echo '<tr>';
        echo '<td>roulette_analytics</td>';
        echo '<td>#' . $currentDrawNumber . '</td>';
        echo '<td>N/A</td>';
        echo '<td>' . $analyticsStatus . '</td>';
        echo '</tr>';
    } else {
        echo '<tr><td>roulette_analytics</td><td colspan="3" style="color: orange;">Not found</td></tr>';
    }
    
    if ($gameState) {
        $gameStateStatus = 'N/A';
        if ($gameState['current_draw_number'] == $expectedDrawNumber && 
            ($nextDrawNumber === null || $nextDrawNumber == $expectedNextDrawNumber)) {
            $gameStateStatus = '<span style="color: green;">✓ Correct</span>';
        } else {
            $gameStateStatus = '<span style="color: red;">✗ Incorrect</span>';
        }
        echo '<tr>';
        echo '<td>roulette_game_state</td>';
        echo '<td>#' . $gameState['current_draw_number'] . '</td>';
        echo '<td>#' . ($nextDrawNumber ?? 'N/A') . '</td>';
        echo '<td>' . $gameStateStatus . '</td>';
        echo '</tr>';
    } else {
        echo '<tr><td>roulette_game_state</td><td colspan="3" style="color: orange;">Not found</td></tr>';
    }
    
    echo '</table>';
    
    // Check if correction is needed
    $needsCorrection = false;
    $correctionActions = [];
    
    if ($currentDrawNumber !== null && $currentDrawNumber != $expectedDrawNumber) {
        $needsCorrection = true;
        $correctionActions[] = "Update roulette_analytics.current_draw_number from #{$currentDrawNumber} to #{$expectedDrawNumber}";
    }
    
    if ($gameState) {
        if ($gameState['current_draw_number'] != $expectedDrawNumber) {
            $needsCorrection = true;
            $correctionActions[] = "Update roulette_game_state.current_draw_number from #{$gameState['current_draw_number']} to #{$expectedDrawNumber}";
        }
        if ($nextDrawNumber !== null && $nextDrawNumber != $expectedNextDrawNumber) {
            $needsCorrection = true;
            $correctionActions[] = "Update roulette_game_state.next_draw_number from #{$nextDrawNumber} to #{$expectedNextDrawNumber}";
        }
    }
    
    // Handle correction if requested
    if (isset($_GET['correct']) && $_GET['correct'] == 'yes') {
        echo '<div class="warning-box">';
        echo '<strong>⚠️ Applying Corrections...</strong><br>';
        
        $correctionsApplied = [];
        
        // Update roulette_analytics
        if ($currentDrawNumber !== null && $currentDrawNumber != $expectedDrawNumber) {
            try {
                $stmt = $pdo->prepare("UPDATE roulette_analytics SET current_draw_number = ? WHERE id = 1");
                $stmt->execute([$expectedDrawNumber]);
                $correctionsApplied[] = "✓ Updated roulette_analytics.current_draw_number to #{$expectedDrawNumber}";
            } catch (PDOException $e) {
                $correctionsApplied[] = "✗ Failed to update roulette_analytics: " . $e->getMessage();
            }
        }
        
        // Update roulette_game_state
        if ($gameState) {
            try {
                $stmt = $pdo->prepare("UPDATE roulette_game_state SET current_draw_number = ?, next_draw_number = ? WHERE id = 1");
                $stmt->execute([$expectedDrawNumber, $expectedNextDrawNumber]);
                $correctionsApplied[] = "✓ Updated roulette_game_state to current_draw_number = #{$expectedDrawNumber}, next_draw_number = #{$expectedNextDrawNumber}";
            } catch (PDOException $e) {
                $correctionsApplied[] = "✗ Failed to update roulette_game_state: " . $e->getMessage();
            }
        }
        
        echo implode('<br>', $correctionsApplied);
        echo '</div>';
        
        // Instructions to refresh TV display
        echo '<div class="info-box" style="margin-top: 20px;">';
        echo '<strong>📺 TV Display Refresh Required</strong><br><br>';
        echo 'After updating the draw number, you need to refresh the TV display to see the changes:<br><br>';
        echo '<strong>Option 1:</strong> Open the TV display in a new tab and hard refresh (Ctrl+F5 or Ctrl+Shift+R)<br>';
        echo '<strong>Option 2:</strong> Click the button below to open TV display with cache-busting<br>';
        echo '<strong>Option 3:</strong> Clear browser localStorage on the TV display page (F12 → Console → localStorage.clear())<br><br>';
        echo '<a href="tvdisplay/index.html?refresh=' . time() . '" target="_blank" class="button" style="background: #4caf50;">Open TV Display (New Tab)</a>';
        echo '</div>';
        
        // Refresh page to show updated values
        echo '<script>setTimeout(function(){ window.location.href = window.location.pathname; }, 2000);</script>';
        echo '<p><a href="' . $_SERVER['PHP_SELF'] . '" class="button">Refresh This Page</a></p>';
        
    } else if ($needsCorrection) {
        // Show correction needed message
        echo '<div class="warning-box">';
        echo '<strong>⚠️ Draw Number Correction Needed</strong><br><br>';
        echo 'The current draw number does not match the expected draw number based on the current time.<br><br>';
        echo '<strong>Required Corrections:</strong><ul>';
        foreach ($correctionActions as $action) {
            echo '<li>' . $action . '</li>';
        }
        echo '</ul>';
        echo '<a href="?correct=yes" class="button button-danger">Apply Corrections</a>';
        echo '<a href="' . $_SERVER['PHP_SELF'] . '" class="button">Cancel</a>';
        echo '</div>';
    } else {
        // All good!
        echo '<div class="success-box">';
        echo '<strong>✅ Draw Number is Correct!</strong><br>';
        echo 'The current draw number matches the expected draw number based on the current time.';
        echo '</div>';
        
        // Add option to force refresh TV display anyway
        echo '<div class="info-box" style="margin-top: 20px;">';
        echo '<strong>💡 Tip:</strong> If the TV display is not showing the correct draw number, try refreshing it:<br><br>';
        echo '<a href="tvdisplay/index.html?refresh=' . time() . '" target="_blank" class="button" style="background: #2196f3;">Refresh TV Display</a>';
        echo '</div>';
    }
    
    echo '<div style="margin-top: 30px;">';
    echo '<a href="application_maintenance_checklist.html" class="button">Back to Checklist</a>';
    echo '<a href="check_current_draw.php" class="button">Check Current Draw</a>';
    echo '<a href="tvdisplay/index.html?refresh=' . time() . '" target="_blank" class="button" style="background: #ff9800;">Open TV Display</a>';
    echo '<a href="' . $_SERVER['PHP_SELF'] . '" class="button">Refresh</a>';
    echo '</div>';
    
} catch (PDOException $e) {
    echo '<div class="error-box">';
    echo '<strong>❌ Database Error:</strong><br>';
    echo $e->getMessage();
    echo '</div>';
} catch (Exception $e) {
    echo '<div class="error-box">';
    echo '<strong>❌ Error:</strong><br>';
    echo $e->getMessage();
    echo '</div>';
}
?>

    </div>
</body>
</html>
