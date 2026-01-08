<?php
/**
 * Setup Draw Tables
 * 
 * This script creates the necessary database tables for the draw history.
 */

// Include database connection
require_once 'php/db_connect.php';

// Set headers
header('Content-Type: text/html');

// Function to check if a table exists
function tableExists($pdo, $table) {
    try {
        $result = $pdo->query("SHOW TABLES LIKE '{$table}'");
        return $result->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Function to create a table if it doesn't exist
function createTableIfNotExists($pdo, $table, $sql) {
    if (!tableExists($pdo, $table)) {
        try {
            $pdo->exec($sql);
            echo "<p>Table '{$table}' created successfully.</p>";
        } catch (PDOException $e) {
            echo "<p>Error creating table '{$table}': " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p>Table '{$table}' already exists.</p>";
    }
}

// Start HTML output
echo "<!DOCTYPE html>
<html>
<head>
    <title>Setup Draw Tables</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
        h1 {
            color: #333;
        }
        .success {
            color: green;
        }
        .error {
            color: red;
        }
        pre {
            background-color: #f5f5f5;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <h1>Setup Draw Tables</h1>";

try {
    // Create detailed_draw_results table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS `detailed_draw_results` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `draw_id` varchar(20) NOT NULL COMMENT 'Unique identifier for this draw',
        `draw_number` int(11) NOT NULL COMMENT 'Sequential draw number',
        `winning_number` int(11) NOT NULL COMMENT 'The winning roulette number',
        `winning_color` varchar(10) NOT NULL COMMENT 'Color of the winning number (red, black, green)',
        `game_session_id` varchar(50) DEFAULT NULL COMMENT 'Session identifier for grouping draws',
        `dealer_id` varchar(50) DEFAULT NULL COMMENT 'Dealer identifier or name',
        `table_id` varchar(50) DEFAULT NULL COMMENT 'Table identifier or name',
        `total_bets` int(11) DEFAULT 0 COMMENT 'Number of bets placed on this draw',
        `total_bet_amount` decimal(10,2) DEFAULT 0.00 COMMENT 'Total amount bet on this draw',
        `total_payout` decimal(10,2) DEFAULT 0.00 COMMENT 'Total amount paid out on this draw',
        `player_count` int(11) DEFAULT 0 COMMENT 'Number of players betting on this draw',
        `notes` text DEFAULT NULL COMMENT 'Additional information about this draw',
        `draw_time` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When this draw occurred',
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    createTableIfNotExists($pdo, 'detailed_draw_results', $sql);
    
    // Create game_history table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS `game_history` (
        `game_id` int(11) NOT NULL AUTO_INCREMENT,
        `winning_number` int(11) NOT NULL,
        `winning_color` varchar(10) NOT NULL,
        `draw_id` varchar(20) NOT NULL,
        `played_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`game_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    createTableIfNotExists($pdo, 'game_history', $sql);
    
    // Create next_draw_winning_number table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS `next_draw_winning_number` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `draw_number` int(11) NOT NULL,
        `winning_number` int(11) NOT NULL,
        `source` varchar(50) DEFAULT 'manual',
        `reason` varchar(255) DEFAULT 'Set by administrator',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_draw` (`draw_number`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    createTableIfNotExists($pdo, 'next_draw_winning_number', $sql);
    
    // Create roulette_state table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS `roulette_state` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `roll_history` text DEFAULT NULL,
        `roll_colors` text DEFAULT NULL,
        `last_draw` varchar(10) DEFAULT NULL,
        `next_draw` varchar(10) DEFAULT NULL,
        `countdown_time` int(11) DEFAULT 120,
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    createTableIfNotExists($pdo, 'roulette_state', $sql);
    
    // Create roulette_analytics table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS `roulette_analytics` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `all_spins` text NOT NULL COMMENT 'JSON array of all recorded spins (newest first)',
        `number_frequency` text NOT NULL COMMENT 'JSON object with count of each number',
        `current_draw_number` int(11) NOT NULL DEFAULT 0 COMMENT 'Current draw number counter',
        `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    createTableIfNotExists($pdo, 'roulette_analytics', $sql);
    
    // Create roulette_settings table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS `roulette_settings` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `setting_name` varchar(50) NOT NULL,
        `setting_value` text NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        `automatic_mode` tinyint(1) DEFAULT 1,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    createTableIfNotExists($pdo, 'roulette_settings', $sql);
    
    // Insert default data into roulette_state if empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM roulette_state");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        $stmt = $pdo->prepare("INSERT INTO roulette_state (roll_history, roll_colors, last_draw, next_draw, countdown_time) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['0,0,0,0,0', 'green,green,green,green,green', '#1', '#2', 120]);
        echo "<p>Default data inserted into roulette_state table.</p>";
    }
    
    // Insert default data into roulette_analytics if empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM roulette_analytics");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        $stmt = $pdo->prepare("INSERT INTO roulette_analytics (all_spins, number_frequency, current_draw_number) VALUES (?, ?, ?)");
        $stmt->execute(['[0,0,0,0,0]', json_encode(array_fill(0, 37, 0)), 1]);
        echo "<p>Default data inserted into roulette_analytics table.</p>";
    }
    
    echo "<p class='success'>Setup completed successfully!</p>";
    echo "<p>You can now <a href='tvdisplay/index.html'>go to the TV display</a> or <a href='admin/bet_distribution.php'>go to the admin panel</a>.</p>";
    
} catch (PDOException $e) {
    echo "<p class='error'>Database error: " . $e->getMessage() . "</p>";
}

// End HTML output
echo "</body></html>";
