<?php
header('Content-Type: application/json');

// Enable more detailed error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include database configuration
require_once 'db_config.php';

// Log the request
error_log("load_state.php called with method: " . $_SERVER['REQUEST_METHOD']);

try {
    // Check if the normalized table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'roulette_state'");
    $tableExists = $tableCheck->rowCount() > 0;

    // If the table doesn't exist, run the migration script
    if (!$tableExists) {
        error_log("roulette_state table not found, creating normalized structure");

        // Create the normalized table structure
        $pdo->exec("CREATE TABLE IF NOT EXISTS roulette_state (
            id INT AUTO_INCREMENT PRIMARY KEY,
            state_type VARCHAR(50) NOT NULL COMMENT 'Type of state change (draw_result, timer_update, mode_change, etc.)',
            draw_number INT NOT NULL COMMENT 'Current draw number at time of state change',
            next_draw_number INT NOT NULL COMMENT 'Next draw number at time of state change',
            countdown_time INT DEFAULT 180 COMMENT 'Countdown timer in seconds',
            end_time VARCHAR(20) DEFAULT NULL COMMENT 'End time in milliseconds timestamp',
            winning_number INT DEFAULT NULL COMMENT 'Winning number for current draw (if state_type is draw_result)',
            next_winning_number INT DEFAULT NULL COMMENT 'Winning number for next draw (if manually set)',
            manual_mode TINYINT(1) DEFAULT 0 COMMENT 'Whether manual mode is enabled',
            additional_data JSON DEFAULT NULL COMMENT 'Any additional data specific to this state change',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");

        // Create the draw history table
        $pdo->exec("CREATE TABLE IF NOT EXISTS roulette_draw_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            draw_number INT NOT NULL COMMENT 'The draw number',
            winning_number INT NOT NULL COMMENT 'The winning number (0-36)',
            winning_color VARCHAR(10) NOT NULL COMMENT 'Color of the winning number (red, black, green)',
            draw_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When the draw occurred',
            is_manual TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether this was a manual draw',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY (draw_number)
        )");
    }

    // Check if any records exist
    $checkStmt = $pdo->query("SELECT COUNT(*) FROM roulette_state");
    $exists = $checkStmt->fetchColumn();

    if (!$exists) {
        // Calculate initial end time based on current time plus 3 minutes
        $initialEndTime = (time() * 1000) + (180 * 1000);

        // Insert initial record if no records exist
        $insertSql = "INSERT INTO roulette_state (
                        state_type,
                        draw_number,
                        next_draw_number,
                        countdown_time,
                        end_time,
                        manual_mode,
                        additional_data
                      ) VALUES (
                        'initial_setup',
                        0,
                        1,
                        180,
                        :end_time,
                        0,
                        :additional_data
                      )";
        $stmt = $pdo->prepare($insertSql);
        $stmt->execute([
            ':end_time' => $initialEndTime,
            ':additional_data' => json_encode([
                'roll_history' => '',
                'roll_colors' => '',
                'last_draw_formatted' => '#0',
                'next_draw_formatted' => '#1'
            ])
        ]);
        error_log("Initial record created with end_time: $initialEndTime");
    }

    // Get the most recent game state from the database
    $stmt = $pdo->query("SELECT * FROM roulette_state ORDER BY id DESC LIMIT 1");
    $result = $stmt->fetch();

    if ($result) {
        error_log("Data loaded from database: " . json_encode($result));

        // Extract data from the normalized structure
        $additionalData = json_decode($result['additional_data'] ?? '{}', true);

        // Handle empty or NULL values with defaults
        $rollHistory = $additionalData['roll_history'] ?? '';
        $rollColors = $additionalData['roll_colors'] ?? '';
        $lastDraw = $additionalData['last_draw_formatted'] ?? ('#' . $result['draw_number']);
        $nextDraw = $additionalData['next_draw_formatted'] ?? ('#' . $result['next_draw_number']);
        $countdownTime = isset($result['countdown_time']) ? intval($result['countdown_time']) : 180;
        $manualMode = isset($result['manual_mode']) ? (bool)$result['manual_mode'] : false;
        $winningNumber = isset($result['winning_number']) ? $result['winning_number'] : null;
        $nextWinningNumber = isset($result['next_winning_number']) ? $result['next_winning_number'] : null;

        // Handle end_time - if not set or expired, calculate a new one based on real-time
        $currentTime = time() * 1000;
        $endTime = !empty($result['end_time']) ? $result['end_time'] : null;

        // Check if end_time is valid and in the future
        if (empty($endTime) || intval($endTime) <= $currentTime) {
            // Calculate a new end time based on the next 3-minute interval
            $now = new DateTime();
            $currentMinutes = (int)$now->format('i'); // Minutes (0-59)
            $currentSeconds = (int)$now->format('s'); // Seconds (0-59)
            $minutesUntilNextDraw = 3 - ($currentMinutes % 3);
            $secondsUntilNextDraw = ($minutesUntilNextDraw * 60) - $currentSeconds;

            // If we're exactly at a 3-minute mark, set for the next one
            if ($secondsUntilNextDraw === 0 || $secondsUntilNextDraw === 180) {
                $secondsUntilNextDraw = 180;
            }

            $endTime = $currentTime + ($secondsUntilNextDraw * 1000);
            $countdownTime = $secondsUntilNextDraw;

            // Insert a new record with the updated values
            $insertStmt = $pdo->prepare("INSERT INTO roulette_state (
                                            state_type,
                                            draw_number,
                                            next_draw_number,
                                            countdown_time,
                                            end_time,
                                            winning_number,
                                            next_winning_number,
                                            manual_mode,
                                            additional_data
                                        ) VALUES (
                                            'timer_update',
                                            :draw_number,
                                            :next_draw_number,
                                            :countdown_time,
                                            :end_time,
                                            :winning_number,
                                            :next_winning_number,
                                            :manual_mode,
                                            :additional_data
                                        )");

            // Extract draw numbers from formatted strings
            $currentDrawNumber = intval(str_replace('#', '', $lastDraw));
            $nextDrawNumber = intval(str_replace('#', '', $nextDraw));

            $insertStmt->execute([
                ':draw_number' => $currentDrawNumber,
                ':next_draw_number' => $nextDrawNumber,
                ':countdown_time' => $countdownTime,
                ':end_time' => $endTime,
                ':winning_number' => $winningNumber,
                ':next_winning_number' => $nextWinningNumber,
                ':manual_mode' => $manualMode ? 1 : 0,
                ':additional_data' => json_encode([
                    'roll_history' => $rollHistory,
                    'roll_colors' => $rollColors,
                    'last_draw_formatted' => $lastDraw,
                    'next_draw_formatted' => $nextDraw
                ])
            ]);

            error_log("Updated end_time to: $endTime and countdown_time to: $countdownTime");

            // Get the updated record
            $stmt = $pdo->query("SELECT * FROM roulette_state ORDER BY id DESC LIMIT 1");
            $result = $stmt->fetch();

            // Re-extract data from the updated record
            $additionalData = json_decode($result['additional_data'] ?? '{}', true);
            $rollHistory = $additionalData['roll_history'] ?? '';
            $rollColors = $additionalData['roll_colors'] ?? '';
            $lastDraw = $additionalData['last_draw_formatted'] ?? ('#' . $result['draw_number']);
            $nextDraw = $additionalData['next_draw_formatted'] ?? ('#' . $result['next_draw_number']);
        }

        // Return the game state as JSON
        echo json_encode([
            'status' => 'success',
            'id' => $result['id'],
            'roll_history' => $rollHistory,
            'roll_colors' => $rollColors,
            'last_draw' => $lastDraw,
            'next_draw' => $nextDraw,
            'countdown_time' => $countdownTime,
            'end_time' => $endTime,
            'updated_at' => $result['updated_at'],
            'state_type' => $result['state_type'],
            'draw_number' => $result['draw_number'],
            'next_draw_number' => $result['next_draw_number'],
            'winning_number' => $result['winning_number'],
            'next_winning_number' => $result['next_winning_number'],
            'manual_mode' => $result['manual_mode']
        ]);
    } else {
        error_log("No data found in database, returning default values");

        // No data found, return default values with real-time based countdown
        // Calculate a new end time based on the next 3-minute interval
        $now = new DateTime();
        $currentMinutes = (int)$now->format('i'); // Minutes (0-59)
        $currentSeconds = (int)$now->format('s'); // Seconds (0-59)
        $minutesUntilNextDraw = 3 - ($currentMinutes % 3);
        $secondsUntilNextDraw = ($minutesUntilNextDraw * 60) - $currentSeconds;

        // If we're exactly at a 3-minute mark, set for the next one
        if ($secondsUntilNextDraw === 0 || $secondsUntilNextDraw === 180) {
            $secondsUntilNextDraw = 180;
        }

        $currentTime = time() * 1000;
        $endTime = $currentTime + ($secondsUntilNextDraw * 1000);

        // Create a default record in the database
        $insertSql = "INSERT INTO roulette_state (
                        state_type,
                        draw_number,
                        next_draw_number,
                        countdown_time,
                        end_time,
                        manual_mode,
                        additional_data
                      ) VALUES (
                        'initial_setup',
                        0,
                        1,
                        :countdown_time,
                        :end_time,
                        0,
                        :additional_data
                      )";
        $stmt = $pdo->prepare($insertSql);
        $stmt->execute([
            ':countdown_time' => $secondsUntilNextDraw,
            ':end_time' => $endTime,
            ':additional_data' => json_encode([
                'roll_history' => '',
                'roll_colors' => '',
                'last_draw_formatted' => '#0',
                'next_draw_formatted' => '#1'
            ])
        ]);

        $newId = $pdo->lastInsertId();

        echo json_encode([
            'status' => 'warning',
            'message' => 'No game state found, created default',
            'id' => $newId,
            'roll_history' => '',
            'roll_colors' => '',
            'last_draw' => '#0',
            'next_draw' => '#1',
            'countdown_time' => $secondsUntilNextDraw,
            'end_time' => $endTime,
            'state_type' => 'initial_setup',
            'draw_number' => 0,
            'next_draw_number' => 1,
            'winning_number' => null,
            'next_winning_number' => null,
            'manual_mode' => false
        ]);
    }
} catch (PDOException $e) {
    error_log("Database error in load_state.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>