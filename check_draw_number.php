<?php
/**
 * Check Draw Number
 * 
 * This script checks the current draw number in the database and displays it.
 */

// Include database connection
require_once 'php/db_connect.php';

// Query to get the current draw number from roulette_analytics
$analyticsQuery = "SELECT current_draw_number FROM roulette_analytics WHERE id = 1";
$analyticsResult = $conn->query($analyticsQuery);

$analyticsDrawNumber = null;
if ($analyticsResult && $analyticsResult->num_rows > 0) {
    $row = $analyticsResult->fetch_assoc();
    $analyticsDrawNumber = $row['current_draw_number'];
}

// Query to get the current draw number from roulette_game_state
$gameStateQuery = "SELECT current_draw_number, next_draw_number FROM roulette_game_state ORDER BY id LIMIT 1";
$gameStateResult = $conn->query($gameStateQuery);

$gameStateCurrentDraw = null;
$gameStateNextDraw = null;
if ($gameStateResult && $gameStateResult->num_rows > 0) {
    $row = $gameStateResult->fetch_assoc();
    $gameStateCurrentDraw = $row['current_draw_number'];
    $gameStateNextDraw = $row['next_draw_number'];
}

// Output the result
header('Content-Type: application/json');
echo json_encode([
    'analyticsDrawNumber' => $analyticsDrawNumber,
    'gameStateCurrentDraw' => $gameStateCurrentDraw,
    'gameStateNextDraw' => $gameStateNextDraw
]);
