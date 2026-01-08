<?php
/**
 * Roulette Analytics Handler
 *
 * This file provides functions for managing roulette game analytics
 * using the new multi-row database structure.
 */

// Include database connection
require_once 'db_connect.php';

/**
 * Get the current game state
 *
 * @return array Game state information
 */
function getGameState() {
    global $conn;

    // First try to get state from roulette_state table (primary source)
    $stateQuery = "SELECT * FROM roulette_state ORDER BY id LIMIT 1";
    $stateResult = $conn->query($stateQuery);

    if ($stateResult && $stateResult->num_rows > 0) {
        $stateRow = $stateResult->fetch_assoc();

        // Extract the draw numbers from the last_draw and next_draw fields
        $lastDrawNumber = (int)str_replace('#', '', $stateRow['last_draw']);
        $nextDrawNumber = (int)str_replace('#', '', $stateRow['next_draw']);

        error_log("Found draw numbers in roulette_state table: Last=$lastDrawNumber, Next=$nextDrawNumber");

        return [
            'id' => $stateRow['id'],
            'current_draw_number' => $lastDrawNumber,
            'next_draw_number' => $nextDrawNumber,
            'next_draw_time' => null,
            'is_auto_draw' => $stateRow['manual_mode'] ? 0 : 1,
            'draw_interval_seconds' => $stateRow['countdown_time'] ?? 180,
            'winning_number' => $stateRow['winning_number'],
            'next_draw_winning_number' => $stateRow['next_draw_winning_number']
        ];
    }

    // If not found in roulette_state, try roulette_game_state
    $query = "SELECT * FROM roulette_game_state ORDER BY id LIMIT 1";
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }

    // If not found, try to get from roulette_analytics table
    $analyticsQuery = "SELECT current_draw_number FROM roulette_analytics ORDER BY id DESC LIMIT 1";
    $analyticsResult = $conn->query($analyticsQuery);

    if ($analyticsResult && $analyticsResult->num_rows > 0) {
        $row = $analyticsResult->fetch_assoc();
        $currentDrawNumber = (int)$row['current_draw_number'];
        $nextDrawNumber = $currentDrawNumber + 1;

        error_log("Found draw number in analytics table: Current=$currentDrawNumber, Next=$nextDrawNumber");

        return [
            'id' => 1,
            'current_draw_number' => $currentDrawNumber,
            'next_draw_number' => $nextDrawNumber,
            'next_draw_time' => null,
            'is_auto_draw' => 1,
            'draw_interval_seconds' => 180
        ];
    }

    // If no state exists in any table, use safe default values
    error_log("Warning: No draw state found in any table, using default values (current=0, next=1)");
    return [
        'id' => 1,
        'current_draw_number' => 0,
        'next_draw_number' => 1,
        'next_draw_time' => null,
        'is_auto_draw' => 1,
        'draw_interval_seconds' => 180
    ];
}

/**
 * Record a new draw result
 *
 * @param int $drawNumber The draw number
 * @param int $winningNumber The winning number (0-36)
 * @param array $betStats Optional betting statistics
 * @return bool Success status
 */
function recordDrawResult($drawNumber, $winningNumber, $betStats = []) {
    global $conn;

    try {
        // Begin transaction
        $conn->begin_transaction();

        // Determine the color of the winning number
        $color = 'green'; // Default for 0
        if ($winningNumber > 0) {
            if (in_array($winningNumber, [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36])) {
                $color = 'red';
            } else {
                $color = 'black';
            }
        }

        // Insert the draw record
        $drawQuery = "INSERT INTO roulette_draws
                     (draw_number, winning_number, winning_color, total_bets, total_stake, total_payout)
                     VALUES (?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                     winning_number = VALUES(winning_number),
                     winning_color = VALUES(winning_color),
                     total_bets = VALUES(total_bets),
                     total_stake = VALUES(total_stake),
                     total_payout = VALUES(total_payout)";

        $drawStmt = $conn->prepare($drawQuery);
        $totalBets = $betStats['total_bets'] ?? 0;
        $totalStake = $betStats['total_stake'] ?? 0;
        $totalPayout = $betStats['total_payout'] ?? 0;

        $drawStmt->bind_param("iisidi", $drawNumber, $winningNumber, $color, $totalBets, $totalStake, $totalPayout);
        $drawStmt->execute();

        // Update number frequency
        $updateNumberQuery = "UPDATE roulette_number_stats
                             SET frequency = frequency + 1,
                                 last_hit_draw_number = ?,
                                 last_hit_time = CURRENT_TIMESTAMP
                             WHERE number = ?";
        $updateNumberStmt = $conn->prepare($updateNumberQuery);
        $updateNumberStmt->bind_param("ii", $drawNumber, $winningNumber);
        $updateNumberStmt->execute();

        // Update color frequency
        $updateColorQuery = "UPDATE roulette_color_stats
                            SET frequency = frequency + 1,
                                last_hit_draw_number = ?,
                                last_hit_time = CURRENT_TIMESTAMP
                            WHERE color = ?";
        $updateColorStmt = $conn->prepare($updateColorQuery);
        $updateColorStmt->bind_param("is", $drawNumber, $color);
        $updateColorStmt->execute();

        // Update game state to next draw
        $nextDrawNumber = $drawNumber + 1;
        $updateStateQuery = "UPDATE roulette_game_state
                            SET current_draw_number = ?,
                                next_draw_number = ?,
                                next_draw_time = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL draw_interval_seconds SECOND)";
        $updateStateStmt = $conn->prepare($updateStateQuery);
        $updateStateStmt->bind_param("ii", $drawNumber, $nextDrawNumber);
        $updateStateStmt->execute();

        // Commit the transaction
        $conn->commit();
        return true;

    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        error_log("Error recording draw result: " . $e->getMessage());
        return false;
    }
}

/**
 * Get recent draw history
 *
 * @param int $limit Number of recent draws to retrieve
 * @return array Recent draw history
 */
function getRecentDraws($limit = 10) {
    global $conn;

    $query = "SELECT * FROM roulette_draws ORDER BY draw_number DESC LIMIT ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $limit);
    $stmt->execute();

    $result = $stmt->get_result();
    $draws = [];

    while ($row = $result->fetch_assoc()) {
        $draws[] = $row;
    }

    return $draws;
}

/**
 * Get number frequency statistics
 *
 * @return array Number frequency data
 */
function getNumberFrequency() {
    global $conn;

    $query = "SELECT * FROM roulette_number_stats ORDER BY number";
    $result = $conn->query($query);

    $frequency = [];

    while ($row = $result->fetch_assoc()) {
        $frequency[$row['number']] = [
            'frequency' => $row['frequency'],
            'last_hit' => $row['last_hit_draw_number'],
            'last_hit_time' => $row['last_hit_time']
        ];
    }

    return $frequency;
}

/**
 * Get color frequency statistics
 *
 * @return array Color frequency data
 */
function getColorFrequency() {
    global $conn;

    $query = "SELECT * FROM roulette_color_stats";
    $result = $conn->query($query);

    $frequency = [];

    while ($row = $result->fetch_assoc()) {
        $frequency[$row['color']] = [
            'frequency' => $row['frequency'],
            'last_hit' => $row['last_hit_draw_number'],
            'last_hit_time' => $row['last_hit_time']
        ];
    }

    return $frequency;
}

/**
 * Get upcoming draw information
 *
 * @param int $count Number of upcoming draws to retrieve
 * @return array Upcoming draw information
 */
function getUpcomingDraws($count = 10) {
    global $conn;

    $state = getGameState();
    $currentDrawNumber = $state['current_draw_number'];

    $draws = [];
    for ($i = 0; $i < $count; $i++) {
        $drawNumber = $currentDrawNumber + $i;
        $drawTime = date('Y-m-d H:i:s', strtotime("+" . ($i * $state['draw_interval_seconds']) . " seconds"));

        $draws[] = [
            'draw_number' => $drawNumber,
            'draw_time' => $drawTime
        ];
    }

    return $draws;
}

/**
 * Get analytics data for the TV display
 *
 * @return array Analytics data for display
 */
function getTVDisplayData() {
    // Log the function call for debugging
    error_log("getTVDisplayData() called at " . date('Y-m-d H:i:s'));

    $gameState = getGameState();
    $recentDraws = getRecentDraws(5);
    $upcomingDraws = getUpcomingDraws(10);

    // Format the data for the TV display
    $lastDrawNumber = null;
    $lastWinningNumber = null;
    $lastWinningColor = null;

    if (!empty($recentDraws)) {
        $lastDraw = $recentDraws[0];
        $lastDrawNumber = $lastDraw['draw_number'];
        $lastWinningNumber = $lastDraw['winning_number'];
        $lastWinningColor = $lastDraw['winning_color'];
    }

    $currentDrawNumber = $gameState['current_draw_number'];
    $nextDrawNumber = $gameState['next_draw_number'];

    // Log the draw numbers for debugging
    error_log("Draw numbers: Last=$lastDrawNumber, Current=$currentDrawNumber, Next=$nextDrawNumber");

    // Calculate countdown time
    $countdownTime = 180; // Default 3 minutes
    if (!empty($gameState['next_draw_time'])) {
        $nextDrawTime = strtotime($gameState['next_draw_time']);
        $currentTime = time();
        $countdownTime = max(0, $nextDrawTime - $currentTime);
    }

    // Format upcoming draws for display
    $formattedUpcomingDraws = [];
    $upcomingDrawTimes = [];

    foreach ($upcomingDraws as $draw) {
        $formattedUpcomingDraws[] = $draw['draw_number'];
        $upcomingDrawTimes[] = date('H:i:s', strtotime($draw['draw_time']));
    }

    // If no upcoming draws, add at least the next draw
    if (empty($formattedUpcomingDraws) && $nextDrawNumber) {
        $formattedUpcomingDraws[] = $nextDrawNumber;
        $upcomingDrawTimes[] = date('H:i:s', time() + $countdownTime);
    }

    // Log the formatted data
    error_log("Formatted upcoming draws: " . json_encode($formattedUpcomingDraws));

    $result = [
        'lastDrawNumber' => $lastDrawNumber,
        'currentDrawNumber' => $currentDrawNumber,
        'nextDrawNumber' => $nextDrawNumber,
        'lastWinningNumber' => $lastWinningNumber,
        'lastWinningColor' => $lastWinningColor,
        'countdownTime' => $countdownTime,
        'upcomingDraws' => $formattedUpcomingDraws,
        'upcomingDrawTimes' => $upcomingDrawTimes
    ];

    // Add a timestamp to prevent caching
    $result['timestamp'] = time();

    return $result;
}
