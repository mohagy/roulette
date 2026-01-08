<?php
/**
 * Roulette Analytics Data Migration Script
 * 
 * This script migrates data from the old single-row roulette_analytics table
 * to the new normalized tables for better historical tracking.
 */

// Include database connection
require_once 'db_connect.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to log messages
function logMessage($message) {
    echo date('[Y-m-d H:i:s] ') . $message . PHP_EOL;
}

// Start migration
logMessage("Starting roulette analytics data migration...");

try {
    // Begin transaction
    $conn->begin_transaction();
    
    // Fetch data from the old table
    $query = "SELECT * FROM roulette_analytics WHERE id = 1";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $oldData = $result->fetch_assoc();
        logMessage("Found existing analytics data.");
        
        // Extract data from the old format
        $allSpins = json_decode($oldData['all_spins'] ?? '[]', true);
        $numberFrequency = json_decode($oldData['number_frequency'] ?? '{}', true);
        $currentDrawNumber = $oldData['current_draw_number'] ?? 1;
        
        // Initialize the game state
        $stateQuery = "INSERT INTO roulette_game_state (current_draw_number, next_draw_number) 
                       VALUES (?, ?) 
                       ON DUPLICATE KEY UPDATE 
                       current_draw_number = VALUES(current_draw_number), 
                       next_draw_number = VALUES(next_draw_number)";
        $stateStmt = $conn->prepare($stateQuery);
        $nextDrawNumber = $currentDrawNumber + 1;
        $stateStmt->bind_param("ii", $currentDrawNumber, $nextDrawNumber);
        $stateStmt->execute();
        logMessage("Game state initialized with current draw number: $currentDrawNumber");
        
        // Process all spins history
        if (!empty($allSpins)) {
            // Prepare the insert statement for draws
            $drawQuery = "INSERT INTO roulette_draws 
                         (draw_number, winning_number, winning_color, draw_time) 
                         VALUES (?, ?, ?, ?) 
                         ON DUPLICATE KEY UPDATE 
                         winning_number = VALUES(winning_number), 
                         winning_color = VALUES(winning_color)";
            $drawStmt = $conn->prepare($drawQuery);
            
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
                $updateNumberStmt = $conn->prepare($updateNumberQuery);
                $updateNumberStmt->bind_param("isi", $drawNumber, $drawTime, $winningNumber);
                $updateNumberStmt->execute();
                
                // Update color frequency
                $updateColorQuery = "UPDATE roulette_color_stats 
                                    SET frequency = frequency + 1, 
                                        last_hit_draw_number = ?, 
                                        last_hit_time = ? 
                                    WHERE color = ?";
                $updateColorStmt = $conn->prepare($updateColorQuery);
                $updateColorStmt->bind_param("iss", $drawNumber, $drawTime, $color);
                $updateColorStmt->execute();
                
                $drawNumber++;
            }
            
            logMessage("Migrated " . count($allSpins) . " historical spins.");
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
                    $updateStmt = $conn->prepare($updateQuery);
                    $updateStmt->bind_param("ii", $frequency, $number);
                    $updateStmt->execute();
                }
            }
            logMessage("Updated number frequencies from existing data.");
        }
        
        // Commit the transaction
        $conn->commit();
        logMessage("Migration completed successfully!");
        
    } else {
        logMessage("No data found in the old roulette_analytics table.");
        $conn->commit();
    }
    
} catch (Exception $e) {
    // Rollback the transaction on error
    $conn->rollback();
    logMessage("Error during migration: " . $e->getMessage());
}

// Close the connection
$conn->close();
