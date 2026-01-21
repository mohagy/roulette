<?php
/**
 * Roulette Analytics Migration - Part 1: Create New Tables
 *
 * This script creates the new database tables for the multi-row analytics structure.
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection if not already included
if (!isset($conn) || !($conn instanceof mysqli)) {
    require_once 'php/db_connect.php';
    $part1_conn = $conn;
} else {
    $part1_conn = $conn;
}

// Use the logMessage function from the parent script
if (!function_exists('logMessage')) {
    function logMessage($message) {
        echo date('[Y-m-d H:i:s] ') . $message . PHP_EOL;
    }
}

// Start migration
logMessage("Starting Part 1: Creating new database tables...");

try {
    // Begin transaction
    $part1_conn->begin_transaction();

    // Create roulette_draws table
    $createDrawsTable = "
    CREATE TABLE IF NOT EXISTS `roulette_draws` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `draw_number` int(11) NOT NULL COMMENT 'Sequential draw number',
      `winning_number` int(11) NOT NULL COMMENT 'The winning roulette number (0-36)',
      `winning_color` varchar(10) NOT NULL COMMENT 'Color of the winning number (red, black, green)',
      `draw_time` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When the draw occurred',
      `is_manual` tinyint(1) DEFAULT 0 COMMENT 'Whether the winning number was manually set',
      `total_bets` int(11) DEFAULT 0 COMMENT 'Total number of bets placed on this draw',
      `total_stake` decimal(10,2) DEFAULT 0.00 COMMENT 'Total amount staked on this draw',
      `total_payout` decimal(10,2) DEFAULT 0.00 COMMENT 'Total amount paid out on this draw',
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_draw_number` (`draw_number`),
      KEY `idx_winning_number` (`winning_number`),
      KEY `idx_winning_color` (`winning_color`),
      KEY `idx_draw_time` (`draw_time`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    if ($part1_conn->query($createDrawsTable)) {
        logMessage("Created roulette_draws table successfully.");
    } else {
        throw new Exception("Error creating roulette_draws table: " . $part1_conn->error);
    }

    // Create roulette_number_stats table
    $createNumberStatsTable = "
    CREATE TABLE IF NOT EXISTS `roulette_number_stats` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `number` int(11) NOT NULL COMMENT 'Roulette number (0-36)',
      `frequency` int(11) NOT NULL DEFAULT 0 COMMENT 'How many times this number has appeared',
      `last_hit_draw_number` int(11) DEFAULT NULL COMMENT 'Last draw number when this number hit',
      `last_hit_time` timestamp NULL DEFAULT NULL COMMENT 'When this number last hit',
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_number` (`number`),
      KEY `idx_frequency` (`frequency`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    if ($part1_conn->query($createNumberStatsTable)) {
        logMessage("Created roulette_number_stats table successfully.");
    } else {
        throw new Exception("Error creating roulette_number_stats table: " . $part1_conn->error);
    }

    // Create roulette_game_state table
    $createGameStateTable = "
    CREATE TABLE IF NOT EXISTS `roulette_game_state` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `current_draw_number` int(11) NOT NULL DEFAULT 1 COMMENT 'Current draw number',
      `next_draw_number` int(11) NOT NULL DEFAULT 2 COMMENT 'Next draw number',
      `next_draw_time` timestamp NULL DEFAULT NULL COMMENT 'Scheduled time for next draw',
      `is_auto_draw` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Whether draws are automatic',
      `draw_interval_seconds` int(11) NOT NULL DEFAULT 180 COMMENT 'Seconds between draws',
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    if ($part1_conn->query($createGameStateTable)) {
        logMessage("Created roulette_game_state table successfully.");
    } else {
        throw new Exception("Error creating roulette_game_state table: " . $part1_conn->error);
    }

    // Create roulette_color_stats table
    $createColorStatsTable = "
    CREATE TABLE IF NOT EXISTS `roulette_color_stats` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `color` varchar(10) NOT NULL COMMENT 'Color (red, black, green)',
      `frequency` int(11) NOT NULL DEFAULT 0 COMMENT 'How many times this color has appeared',
      `last_hit_draw_number` int(11) DEFAULT NULL COMMENT 'Last draw number when this color hit',
      `last_hit_time` timestamp NULL DEFAULT NULL COMMENT 'When this color last hit',
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_color` (`color`),
      KEY `idx_frequency` (`frequency`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    if ($part1_conn->query($createColorStatsTable)) {
        logMessage("Created roulette_color_stats table successfully.");
    } else {
        throw new Exception("Error creating roulette_color_stats table: " . $part1_conn->error);
    }

    // Create next_draw_winning_number table
    $createNextDrawTable = "
    CREATE TABLE IF NOT EXISTS `next_draw_winning_number` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `draw_number` int(11) NOT NULL,
      `winning_number` int(11) NOT NULL,
      `winning_color` varchar(10) NOT NULL,
      `is_manual` tinyint(1) NOT NULL DEFAULT 1,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_draw_number` (`draw_number`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    if ($part1_conn->query($createNextDrawTable)) {
        logMessage("Created next_draw_winning_number table successfully.");
    } else {
        throw new Exception("Error creating next_draw_winning_number table: " . $part1_conn->error);
    }

    // Initialize the number statistics table with all possible numbers
    $initNumberStats = "
    INSERT INTO `roulette_number_stats` (`number`, `frequency`)
    SELECT n, 0 FROM (
      SELECT 0 AS n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION
      SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION
      SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15 UNION SELECT 16 UNION SELECT 17 UNION
      SELECT 18 UNION SELECT 19 UNION SELECT 20 UNION SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION
      SELECT 24 UNION SELECT 25 UNION SELECT 26 UNION SELECT 27 UNION SELECT 28 UNION SELECT 29 UNION
      SELECT 30 UNION SELECT 31 UNION SELECT 32 UNION SELECT 33 UNION SELECT 34 UNION SELECT 35 UNION
      SELECT 36
    ) AS numbers
    ON DUPLICATE KEY UPDATE frequency = frequency;
    ";

    if ($part1_conn->query($initNumberStats)) {
        logMessage("Initialized roulette_number_stats with all possible numbers.");
    } else {
        throw new Exception("Error initializing roulette_number_stats: " . $part1_conn->error);
    }

    // Initialize the color statistics table
    $initColorStats = "
    INSERT INTO `roulette_color_stats` (`color`, `frequency`)
    VALUES ('red', 0), ('black', 0), ('green', 0)
    ON DUPLICATE KEY UPDATE frequency = frequency;
    ";

    if ($part1_conn->query($initColorStats)) {
        logMessage("Initialized roulette_color_stats with all colors.");
    } else {
        throw new Exception("Error initializing roulette_color_stats: " . $part1_conn->error);
    }

    // Commit the transaction
    $part1_conn->commit();
    logMessage("Part 1 completed successfully! All tables created.");

} catch (Exception $e) {
    // Rollback the transaction on error
    $part1_conn->rollback();
    logMessage("Error during Part 1: " . $e->getMessage());
}

// Don't close the connection if it was passed from the parent script
if (!isset($conn) || !($conn instanceof mysqli)) {
    $part1_conn->close();
}
?>
