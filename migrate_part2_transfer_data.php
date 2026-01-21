<?php
/**
 * Roulette Analytics Migration - Part 2: Transfer Data
 *
 * This script migrates data from the old single-row roulette_analytics table
 * to the new multi-row tables.
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection if not already included
if (!isset($conn) || !($conn instanceof mysqli)) {
    require_once 'php/db_connect.php';
    $part2_conn = $conn;
} else {
    $part2_conn = $conn;
}

// Use the logMessage function from the parent script
if (!function_exists('logMessage')) {
    function logMessage($message) {
        echo date('[Y-m-d H:i:s] ') . $message . PHP_EOL;
    }
}

// Start migration
logMessage("Starting Part 2: Migrating data from old table to new tables...");

try {
    // Begin transaction
    $part2_conn->begin_transaction();

    // Check if the old table exists
    $tableCheckQuery = "SHOW TABLES LIKE 'roulette_analytics'";
    $tableCheckResult = $part2_conn->query($tableCheckQuery);

    if ($tableCheckResult->num_rows == 0) {
        throw new Exception("The roulette_analytics table does not exist. Nothing to migrate.");
    }

    // Fetch data from the old table
    $query = "SELECT * FROM roulette_analytics WHERE id = 1";
    $result = $part2_conn->query($query);

    if ($result && $result->num_rows > 0) {
        $oldData = $result->fetch_assoc();
        logMessage("Found existing analytics data in the old table.");

        // Extract data from the old format
        $allSpins = json_decode($oldData['all_spins'] ?? '[]', true);
        $numberFrequency = json_decode($oldData['number_frequency'] ?? '{}', true);
        $currentDrawNumber = $oldData['current_draw_number'] ?? 1;

        logMessage("Current draw number from old table: " . $currentDrawNumber);
        logMessage("Found " . count($allSpins) . " historical spins in the old table.");

        // Initialize the game state
        $stateQuery = "INSERT INTO roulette_game_state (current_draw_number, next_draw_number, draw_interval_seconds)
                       VALUES (?, ?, 180)
                       ON DUPLICATE KEY UPDATE
                       current_draw_number = VALUES(current_draw_number),
                       next_draw_number = VALUES(next_draw_number)";
        $stateStmt = $part2_conn->prepare($stateQuery);
        $nextDrawNumber = $currentDrawNumber + 1;
        $stateStmt->bind_param("ii", $currentDrawNumber, $nextDrawNumber);
        $stateStmt->execute();
        logMessage("Game state initialized with current draw number: $currentDrawNumber, next draw number: $nextDrawNumber");

        // Process all spins history
        if (!empty($allSpins)) {
            // Prepare the insert statement for draws
            $drawQuery = "INSERT INTO roulette_draws
                         (draw_number, winning_number, winning_color, draw_time)
                         VALUES (?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE
                         winning_number = VALUES(winning_number),
                         winning_color = VALUES(winning_color)";
            $drawStmt = $part2_conn->prepare($drawQuery);

            // Map of numbers to colors
            $numberColors = [];
            for ($i = 1; $i <= 36; $i++) {
                if (in_array($i, [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36])) {
                    $numberColors[$i] = 'red';
                } else {
                    $numberColors[$i] = 'black';
                }
            }
            $numberColors[0] = 'green';

            // Process each spin
            $drawNumber = 1;
            foreach ($allSpins as $spin) {
                // Extract the winning number
                $winningNumber = intval($spin);
                $color = $numberColors[$winningNumber] ?? 'green';

                // Create a timestamp (we don't have the actual time, so we'll use a placeholder)
                $drawTime = date('Y-m-d H:i:s', time() - (($currentDrawNumber - $drawNumber) * 180));

                // Insert the draw
                $drawStmt->bind_param("iiss", $drawNumber, $winningNumber, $color, $drawTime);
                $drawStmt->execute();

                // Update number frequency
                $updateNumberQuery = "UPDATE roulette_number_stats
                                     SET frequency = frequency + 1,
                                         last_hit_draw_number = ?,
                                         last_hit_time = ?
                                     WHERE number = ?";
                $updateNumberStmt = $part2_conn->prepare($updateNumberQuery);
                $updateNumberStmt->bind_param("isi", $drawNumber, $drawTime, $winningNumber);
                $updateNumberStmt->execute();

                // Update color frequency
                $updateColorQuery = "UPDATE roulette_color_stats
                                    SET frequency = frequency + 1,
                                        last_hit_draw_number = ?,
                                        last_hit_time = ?
                                    WHERE color = ?";
                $updateColorStmt = $part2_conn->prepare($updateColorQuery);
                $updateColorStmt->bind_param("iss", $drawNumber, $drawTime, $color);
                $updateColorStmt->execute();

                $drawNumber++;
            }

            logMessage("Migrated " . count($allSpins) . " historical spins to the new tables.");
        } else {
            logMessage("No historical spins found to migrate.");
        }

        // Process number frequency if the all_spins data was empty or incomplete
        if (!empty($numberFrequency)) {
            foreach ($numberFrequency as $number => $frequency) {
                if ($frequency > 0) {
                    $updateQuery = "UPDATE roulette_number_stats
                                   SET frequency = ?
                                   WHERE number = ?";
                    $updateStmt = $part2_conn->prepare($updateQuery);
                    $updateStmt->bind_param("ii", $frequency, $number);
                    $updateStmt->execute();
                }
            }
            logMessage("Updated number frequencies from existing data.");
        }

        // Check if there's a detailed_draw_results table to migrate from
        $tableCheckQuery = "SHOW TABLES LIKE 'detailed_draw_results'";
        $tableCheckResult = $part2_conn->query($tableCheckQuery);

        if ($tableCheckResult->num_rows > 0) {
            logMessage("Found detailed_draw_results table. Migrating additional data...");

            // Get the latest draws from detailed_draw_results
            $detailedDrawsQuery = "SELECT * FROM detailed_draw_results ORDER BY draw_number";
            $detailedDrawsResult = $part2_conn->query($detailedDrawsQuery);

            if ($detailedDrawsResult && $detailedDrawsResult->num_rows > 0) {
                $migratedCount = 0;

                while ($row = $detailedDrawsResult->fetch_assoc()) {
                    $drawNumber = (int)$row['draw_number'];
                    $winningNumber = (int)$row['winning_number'];
                    $winningColor = $row['winning_color'];
                    $drawTime = $row['draw_time'] ?? date('Y-m-d H:i:s');

                    // Insert or update in roulette_draws
                    $updateDrawQuery = "INSERT INTO roulette_draws
                                      (draw_number, winning_number, winning_color, draw_time)
                                      VALUES (?, ?, ?, ?)
                                      ON DUPLICATE KEY UPDATE
                                      winning_number = VALUES(winning_number),
                                      winning_color = VALUES(winning_color),
                                      draw_time = VALUES(draw_time)";
                    $updateDrawStmt = $part2_conn->prepare($updateDrawQuery);
                    $updateDrawStmt->bind_param("iiss", $drawNumber, $winningNumber, $winningColor, $drawTime);
                    $updateDrawStmt->execute();

                    $migratedCount++;
                }

                logMessage("Migrated $migratedCount draws from detailed_draw_results table.");
            }
        }

        // Commit the transaction
        $part2_conn->commit();
        logMessage("Part 2 completed successfully! Data migrated to new tables.");

    } else {
        logMessage("No data found in the old roulette_analytics table. Nothing to migrate.");
        $part2_conn->commit();
    }

} catch (Exception $e) {
    // Rollback the transaction on error
    $part2_conn->rollback();
    logMessage("Error during Part 2: " . $e->getMessage());
}

// Don't close the connection if it was passed from the parent script
if (!isset($conn) || !($conn instanceof mysqli)) {
    $part2_conn->close();
}
?>
