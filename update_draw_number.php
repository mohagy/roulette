<?php
/**
 * Update Draw Number
 * 
 * This script updates the current draw number in the database to ensure
 * that the system is displaying the correct draw numbers.
 */

// Include database connection
require_once 'php/db_connect.php';

// Set the current draw number to 12 and next draw to 13
$currentDrawNumber = 12;
$nextDrawNumber = 13;

// Update the roulette_analytics table
$analyticsQuery = "UPDATE roulette_analytics SET current_draw_number = ? WHERE id = 1";
$analyticsStmt = $conn->prepare($analyticsQuery);
$analyticsStmt->bind_param("i", $currentDrawNumber);
$analyticsResult = $analyticsStmt->execute();

// Update the roulette_game_state table
$gameStateQuery = "UPDATE roulette_game_state SET current_draw_number = ?, next_draw_number = ? WHERE id = 1";
$gameStateStmt = $conn->prepare($gameStateQuery);
$gameStateStmt->bind_param("ii", $currentDrawNumber, $nextDrawNumber);
$gameStateResult = $gameStateStmt->execute();

// Check if the tables exist, if not create them
if (!$analyticsResult) {
    // Create the roulette_analytics table
    $createAnalyticsQuery = "CREATE TABLE IF NOT EXISTS roulette_analytics (
        id INT PRIMARY KEY,
        current_draw_number INT NOT NULL DEFAULT 12
    )";
    $conn->query($createAnalyticsQuery);
    
    // Insert the default record
    $insertAnalyticsQuery = "INSERT INTO roulette_analytics (id, current_draw_number) VALUES (1, ?)
                           ON DUPLICATE KEY UPDATE current_draw_number = ?";
    $insertAnalyticsStmt = $conn->prepare($insertAnalyticsQuery);
    $insertAnalyticsStmt->bind_param("ii", $currentDrawNumber, $currentDrawNumber);
    $insertAnalyticsStmt->execute();
}

if (!$gameStateResult) {
    // Create the roulette_game_state table
    $createGameStateQuery = "CREATE TABLE IF NOT EXISTS roulette_game_state (
        id INT PRIMARY KEY AUTO_INCREMENT,
        current_draw_number INT NOT NULL DEFAULT 12,
        next_draw_number INT NOT NULL DEFAULT 13,
        next_draw_time DATETIME,
        is_auto_draw TINYINT(1) NOT NULL DEFAULT 1,
        draw_interval_seconds INT NOT NULL DEFAULT 180
    )";
    $conn->query($createGameStateQuery);
    
    // Insert the default record
    $insertGameStateQuery = "INSERT INTO roulette_game_state 
                           (current_draw_number, next_draw_number, next_draw_time, is_auto_draw, draw_interval_seconds) 
                           VALUES (?, ?, NOW() + INTERVAL 180 SECOND, 1, 180)";
    $insertGameStateStmt = $conn->prepare($insertGameStateQuery);
    $insertGameStateStmt->bind_param("ii", $currentDrawNumber, $nextDrawNumber);
    $insertGameStateStmt->execute();
}

// Output the result
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Draw numbers updated successfully',
    'currentDrawNumber' => $currentDrawNumber,
    'nextDrawNumber' => $nextDrawNumber
]);
