<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include database configuration
require_once 'db_config.php';

// Output HTML header for better readability in browser
echo "<!DOCTYPE html>
<html>
<head>
    <title>Roulette Database Setup</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        h1 { color: #333; }
        .success { color: green; }
        .error { color: red; }
        code { background: #f5f5f5; padding: 2px 5px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>Roulette Database Setup</h1>";

try {
    // Create roulette_state table if it doesn't exist
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS roulette_state (
        id INT AUTO_INCREMENT PRIMARY KEY,
        roll_history TEXT NOT NULL COMMENT 'Comma-separated list of rolled numbers',
        roll_colors TEXT NOT NULL COMMENT 'Comma-separated list of roll colors',
        last_draw VARCHAR(10) NOT NULL DEFAULT '#0' COMMENT 'Last draw number',
        next_draw VARCHAR(10) NOT NULL DEFAULT '#1' COMMENT 'Next draw number',
        countdown_time INT NOT NULL DEFAULT 120 COMMENT 'Countdown timer in seconds',
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
    ");
    echo "<p class='success'>✓ roulette_state table created or already exists</p>";
    
    // Insert initial record in roulette_state if it doesn't exist
    $checkState = $pdo->query("SELECT COUNT(*) FROM roulette_state WHERE id = 1");
    if ($checkState->fetchColumn() == 0) {
        $pdo->exec("
        INSERT INTO roulette_state (id, roll_history, roll_colors, last_draw, next_draw, countdown_time) 
        VALUES (1, '', '', '#0', '#1', 120)
        ");
        echo "<p class='success'>✓ Initial roulette_state record created</p>";
    } else {
        echo "<p class='success'>✓ roulette_state record already exists</p>";
    }
    
    // Create roulette_analytics table if it doesn't exist
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS roulette_analytics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        all_spins TEXT NOT NULL COMMENT 'JSON array of all recorded spins (newest first)',
        number_frequency TEXT NOT NULL COMMENT 'JSON object with count of each number',
        current_draw_number INT NOT NULL DEFAULT 0 COMMENT 'Current draw number counter',
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
    ");
    echo "<p class='success'>✓ roulette_analytics table created or already exists</p>";
    
    // Insert initial record in roulette_analytics if it doesn't exist
    $checkAnalytics = $pdo->query("SELECT COUNT(*) FROM roulette_analytics WHERE id = 1");
    if ($checkAnalytics->fetchColumn() == 0) {
        $pdo->exec("
        INSERT INTO roulette_analytics (id, all_spins, number_frequency, current_draw_number) 
        VALUES (1, '[]', '{}', 0)
        ");
        echo "<p class='success'>✓ Initial roulette_analytics record created</p>";
    } else {
        echo "<p class='success'>✓ roulette_analytics record already exists</p>";
    }
    
    // Create detailed_draw_results table for future expansion
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS detailed_draw_results (
        id INT AUTO_INCREMENT PRIMARY KEY,
        draw_id VARCHAR(20) NOT NULL COMMENT 'Unique identifier for this draw',
        draw_number INT NOT NULL COMMENT 'Sequential draw number',
        winning_number INT NOT NULL COMMENT 'The winning roulette number',
        winning_color VARCHAR(10) NOT NULL COMMENT 'Color of the winning number (red, black, green)',
        game_session_id VARCHAR(50) COMMENT 'Session identifier for grouping draws',
        dealer_id VARCHAR(50) COMMENT 'Dealer identifier or name',
        table_id VARCHAR(50) COMMENT 'Table identifier or name',
        total_bets INT DEFAULT 0 COMMENT 'Number of bets placed on this draw',
        total_bet_amount DECIMAL(10,2) DEFAULT 0 COMMENT 'Total amount bet on this draw',
        total_payout DECIMAL(10,2) DEFAULT 0 COMMENT 'Total amount paid out on this draw',
        player_count INT DEFAULT 0 COMMENT 'Number of players betting on this draw',
        notes TEXT COMMENT 'Additional information about this draw',
        draw_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When this draw occurred'
    )
    ");
    echo "<p class='success'>✓ detailed_draw_results table created or already exists</p>";
    
    echo "<h2 class='success'>Database setup completed successfully!</h2>";
    echo "<p>Your roulette game database is now ready to use. All required tables have been created.</p>";
    
    // Show links to test/verify
    echo "<h3>Next Steps:</h3>";
    echo "<ul>
        <li><a href='test_db.php' target='_blank'>Test Database Connection</a></li>
        <li><a href='tvdisplay/index.html' target='_blank'>Launch Roulette Game</a></li>
    </ul>";

} catch (PDOException $e) {
    echo "<h2 class='error'>Database Error</h2>";
    echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database configuration in <code>db_config.php</code>.</p>";
}

// Close HTML
echo "</body></html>";
?> 