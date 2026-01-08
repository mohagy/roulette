<?php
// Enable more detailed error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Output HTML header for better readability in browser
echo "<!DOCTYPE html>
<html>
<head>
    <title>Reset Roulette Database</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        h1 { color: #333; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Reset Roulette Database</h1>";

// Include database configuration
try {
    require_once 'db_config.php';
    echo "<p class='success'>Database connection successful</p>";
} catch (Exception $e) {
    echo "<p class='error'>Database connection failed: " . $e->getMessage() . "</p>";
    echo "</body></html>";
    exit;
}

// Check if confirmation is provided
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
    echo "<p class='warning'>Warning: This will reset all roulette game data including:</p>";
    echo "<ul>
        <li>Game state (roll history, draw numbers, etc.)</li>
        <li>Analytics data (hot/cold numbers, statistics)</li>
        <li>Detailed draw results</li>
    </ul>";
    echo "<p>Are you sure you want to continue?</p>";
    echo "<p><a href='reset_database.php?confirm=yes' style='background-color: #f44336; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px;'>Yes, reset all data</a> &nbsp;
            <a href='test_db.php' style='background-color: #4CAF50; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px;'>No, go back</a></p>";
    echo "</body></html>";
    exit;
}

// Flag to track if transaction is active
$transactionActive = false;

try {
    // Begin transaction
    $pdo->beginTransaction();
    $transactionActive = true;

    // Reset roulette_state table
    echo "<h2>Resetting Game State</h2>";
    try {
        // First delete all records
        $pdo->exec("DELETE FROM roulette_state");

        // Then truncate the table to reset auto-increment
        $pdo->exec("TRUNCATE TABLE roulette_state");

        // Reset auto-increment value
        $pdo->exec("ALTER TABLE roulette_state AUTO_INCREMENT = 1");

        // Insert initial record with draw number set to 1
        $pdo->exec("INSERT INTO roulette_state (roll_history, roll_colors, last_draw, next_draw, countdown_time, end_time, current_draw_number)
                   VALUES ('', '', '#0', '#1', 120, '" . (time() + 120) . "', 1)");

        // Verify the table has the correct data
        $result = $pdo->query("SELECT current_draw_number FROM roulette_state WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        if (!$result || $result['current_draw_number'] != 1) {
            throw new Exception("Failed to initialize roulette_state table with correct draw number");
        }

        echo "<p class='success'>✓ Game state reset successfully</p>";
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Error resetting game state: " . $e->getMessage() . "</p>";
        if ($transactionActive) {
            $pdo->rollBack();
            $transactionActive = false;
        }
        throw $e;
    }

    // Reset roulette_analytics table
    echo "<h2>Resetting Analytics Data</h2>";
    try {
        // First delete all records
        $pdo->exec("DELETE FROM roulette_analytics");

        // Then truncate the table to reset auto-increment
        $pdo->exec("TRUNCATE TABLE roulette_analytics");

        // Reset auto-increment value
        $pdo->exec("ALTER TABLE roulette_analytics AUTO_INCREMENT = 1");

        // Insert initial record with draw number set to 1
        $pdo->exec("INSERT INTO roulette_analytics (id, all_spins, number_frequency, current_draw_number)
                   VALUES (1, '[]', '{}', 1)");

        // Verify the table has the correct data
        $result = $pdo->query("SELECT current_draw_number FROM roulette_analytics WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        if (!$result || $result['current_draw_number'] != 1) {
            throw new Exception("Failed to initialize roulette_analytics table with correct draw number");
        }

        echo "<p class='success'>✓ Analytics data reset successfully</p>";
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Error resetting analytics data: " . $e->getMessage() . "</p>";
        if ($transactionActive) {
            $pdo->rollBack();
            $transactionActive = false;
        }
        throw $e;
    }

    // Reset detailed_draw_results table
    echo "<h2>Resetting Draw Results</h2>";
    try {
        // First delete all records to ensure a clean state
        $pdo->exec("DELETE FROM detailed_draw_results");

        // Then truncate the table to reset auto-increment
        $pdo->exec("TRUNCATE TABLE detailed_draw_results");

        // Reset auto-increment value to ensure draw numbers start from 1
        $pdo->exec("ALTER TABLE detailed_draw_results AUTO_INCREMENT = 1");

        // Verify the table is empty
        $count = $pdo->query("SELECT COUNT(*) FROM detailed_draw_results")->fetchColumn();
        if ($count > 0) {
            throw new Exception("Failed to clear detailed_draw_results table. Records remaining: $count");
        }

        echo "<p class='success'>✓ Draw results reset successfully</p>";
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Error resetting draw results: " . $e->getMessage() . "</p>";
        if ($transactionActive) {
            $pdo->rollBack();
            $transactionActive = false;
        }
        throw $e;
    }

    // Reset draw_history table
    echo "<h2>Resetting Draw History</h2>";
    try {
        $pdo->exec("TRUNCATE TABLE draw_history");
        // Reset auto-increment value
        $pdo->exec("ALTER TABLE draw_history AUTO_INCREMENT = 1");
        echo "<p class='success'>✓ Draw history reset successfully</p>";
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Error resetting draw history: " . $e->getMessage() . "</p>";
        if ($transactionActive) {
            $pdo->rollBack();
            $transactionActive = false;
        }
        throw $e;
    }

    // Reset roulette_game_state table
    echo "<h2>Resetting Game State Table</h2>";
    try {
        // Check if the table exists
        $tableExists = $pdo->query("SHOW TABLES LIKE 'roulette_game_state'")->rowCount() > 0;

        if ($tableExists) {
            // First delete all records
            $pdo->exec("DELETE FROM roulette_game_state");

            // Then truncate the table to reset auto-increment
            $pdo->exec("TRUNCATE TABLE roulette_game_state");

            // Reset auto-increment value
            $pdo->exec("ALTER TABLE roulette_game_state AUTO_INCREMENT = 1");

            // Insert initial record with draw numbers set to 0 and 1
            $pdo->exec("INSERT INTO roulette_game_state
                       (id, current_draw_number, next_draw_number, next_draw_time, is_auto_draw, draw_interval_seconds)
                       VALUES (1, 0, 1, NOW() + INTERVAL 3 MINUTE, 1, 180)");

            // Verify the table has the correct data
            $result = $pdo->query("SELECT current_draw_number, next_draw_number FROM roulette_game_state WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
            if (!$result || $result['current_draw_number'] != 0 || $result['next_draw_number'] != 1) {
                throw new Exception("Failed to initialize roulette_game_state table with correct draw numbers");
            }

            echo "<p class='success'>✓ Game state table reset successfully</p>";
        } else {
            echo "<p class='info'>Game state table does not exist, skipping</p>";
        }
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Error resetting game state table: " . $e->getMessage() . "</p>";
        if ($transactionActive) {
            $pdo->rollBack();
            $transactionActive = false;
        }
        throw $e;
    }

    // Reset next_draw_winning_number table
    echo "<h2>Resetting Next Draw Winning Number Table</h2>";
    try {
        // Check if the table exists
        $tableExists = $pdo->query("SHOW TABLES LIKE 'next_draw_winning_number'")->rowCount() > 0;

        if ($tableExists) {
            // First delete all records
            $pdo->exec("DELETE FROM next_draw_winning_number");

            // Then truncate the table to reset auto-increment
            $pdo->exec("TRUNCATE TABLE next_draw_winning_number");

            // Reset auto-increment value
            $pdo->exec("ALTER TABLE next_draw_winning_number AUTO_INCREMENT = 1");

            echo "<p class='success'>✓ Next draw winning number table reset successfully</p>";
        } else {
            echo "<p class='info'>Next draw winning number table does not exist, skipping</p>";
        }
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Error resetting next draw winning number table: " . $e->getMessage() . "</p>";
        if ($transactionActive) {
            $pdo->rollBack();
            $transactionActive = false;
        }
        throw $e;
    }

    // Commit transaction if still active
    if ($transactionActive) {
        $pdo->commit();
        $transactionActive = false;
    }

    echo "<h2 class='success'>Database Reset Complete!</h2>";
    echo "<p>All roulette game data has been reset to initial values.</p>";
    echo "<p><a href='test_db.php' style='background-color: #4CAF50; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px;'>View Database Status</a> &nbsp;
            <a href='tvdisplay/index.html' style='background-color: #2196F3; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px;'>Launch Roulette Game</a></p>";

} catch (PDOException $e) {
    // Roll back transaction on error if still active
    if ($transactionActive) {
        try {
            $pdo->rollBack();
        } catch (PDOException $rollbackException) {
            echo "<p class='error'>Additional error during rollback: " . $rollbackException->getMessage() . "</p>";
        }
    }
    echo "<p class='error'>Reset failed: " . $e->getMessage() . "</p>";
    echo "<p>Please try running the <a href='setup_database.php'>setup_database.php</a> script instead to create missing tables.</p>";
}

// Close HTML
echo "</body></html>";
?>