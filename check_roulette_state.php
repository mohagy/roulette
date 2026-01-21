<?php
/**
 * Check Roulette State
 * 
 * This script checks the current draw numbers in the roulette_state table and displays them.
 */

// Include database connection
require_once 'php/db_connect.php';

// Query to get the current draw numbers from roulette_state
$stateQuery = "SELECT * FROM roulette_state ORDER BY id LIMIT 1";
$stateResult = $conn->query($stateQuery);

$stateData = null;
if ($stateResult && $stateResult->num_rows > 0) {
    $stateData = $stateResult->fetch_assoc();
}

// Output the result
header('Content-Type: application/json');
echo json_encode([
    'stateData' => $stateData,
    'lastDraw' => $stateData ? $stateData['last_draw'] : null,
    'nextDraw' => $stateData ? $stateData['next_draw'] : null,
    'currentDrawNumber' => $stateData ? $stateData['current_draw_number'] : null,
    'countdownTime' => $stateData ? $stateData['countdown_time'] : null,
    'endTime' => $stateData ? $stateData['end_time'] : null,
    'manualMode' => $stateData ? $stateData['manual_mode'] : null,
    'nextDrawWinningNumber' => $stateData ? $stateData['next_draw_winning_number'] : null
]);
