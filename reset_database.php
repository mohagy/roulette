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

// Note: We don't use transactions here because TRUNCATE TABLE auto-commits in MySQL
// Each operation is independent and will commit immediately
try {

    // Reset roulette_state table
    echo "<h2>Resetting Game State</h2>";
    try {
        // Check if table exists
        $tableExists = $pdo->query("SHOW TABLES LIKE 'roulette_state'")->rowCount() > 0;
        
        if (!$tableExists) {
            echo "<p class='warning'>⚠ roulette_state table does not exist. Creating it...</p>";
            // Create basic table structure
            $pdo->exec("CREATE TABLE IF NOT EXISTS roulette_state (
                id INT AUTO_INCREMENT PRIMARY KEY,
                last_draw VARCHAR(10) DEFAULT NULL,
                next_draw VARCHAR(10) DEFAULT NULL,
                countdown_time INT DEFAULT 120,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )");
            echo "<p class='success'>✓ roulette_state table created</p>";
        }
        
        // Get the actual table structure
        $columns = [];
        $result = $pdo->query("SHOW COLUMNS FROM roulette_state");
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $row['Field'];
        }

        // Delete all records
        $pdo->exec("DELETE FROM roulette_state");

        // Truncate the table to reset auto-increment
        $pdo->exec("TRUNCATE TABLE roulette_state");

        // Reset auto-increment value
        $pdo->exec("ALTER TABLE roulette_state AUTO_INCREMENT = 1");

        // Build INSERT statement based on available columns
        $insertColumns = [];
        $insertValues = [];
        
        // Check for different column structures and build appropriate INSERT
        if (in_array('draw_number', $columns) && in_array('state_type', $columns)) {
            // Normalized structure with state_type and draw_number
            $insertColumns[] = 'state_type';
            $insertValues[] = 'initial_reset';
            
            $insertColumns[] = 'draw_number';
            $insertValues[] = 1;
            
            if (in_array('next_draw_number', $columns)) {
                $insertColumns[] = 'next_draw_number';
                $insertValues[] = 2;
            }
            
            if (in_array('countdown_time', $columns)) {
                $insertColumns[] = 'countdown_time';
                $insertValues[] = 120;
            }
            
            if (in_array('end_time', $columns)) {
                $insertColumns[] = 'end_time';
                $insertValues[] = time() + 120;
            }
            
            if (in_array('manual_mode', $columns)) {
                $insertColumns[] = 'manual_mode';
                $insertValues[] = 0;
            }
        } else if (in_array('current_draw_number', $columns)) {
            // New structure with current_draw_number
            $insertColumns[] = 'current_draw_number';
            $insertValues[] = 1;
            
            if (in_array('next_draw_number', $columns)) {
                $insertColumns[] = 'next_draw_number';
                $insertValues[] = 2;
            }
            
            if (in_array('countdown_time', $columns)) {
                $insertColumns[] = 'countdown_time';
                $insertValues[] = 120;
            }
            
            if (in_array('end_time', $columns)) {
                $insertColumns[] = 'end_time';
                $insertValues[] = time() + 120;
            }
            
            if (in_array('last_draw', $columns)) {
                $insertColumns[] = 'last_draw';
                $insertValues[] = '#0';
            }
            
            if (in_array('next_draw', $columns)) {
                $insertColumns[] = 'next_draw';
                $insertValues[] = '#1';
            }
            
            if (in_array('roll_history', $columns)) {
                $insertColumns[] = 'roll_history';
                $insertValues[] = '';
            }
            
            if (in_array('roll_colors', $columns)) {
                $insertColumns[] = 'roll_colors';
                $insertValues[] = '';
            }
            
            if (in_array('manual_mode', $columns)) {
                $insertColumns[] = 'manual_mode';
                $insertValues[] = 0;
            }
            
            if (in_array('state_type', $columns)) {
                $insertColumns[] = 'state_type';
                $insertValues[] = 'initial_reset';
            }
        } else if (in_array('last_draw', $columns) && in_array('next_draw', $columns)) {
            // Old structure with last_draw and next_draw
            $insertColumns[] = 'last_draw';
            $insertValues[] = '#0';
            
            $insertColumns[] = 'next_draw';
            $insertValues[] = '#1';
            
            if (in_array('countdown_time', $columns)) {
                $insertColumns[] = 'countdown_time';
                $insertValues[] = 120;
            }
            
            if (in_array('roll_history', $columns)) {
                $insertColumns[] = 'roll_history';
                $insertValues[] = '';
            }
            
            if (in_array('roll_colors', $columns)) {
                $insertColumns[] = 'roll_colors';
                $insertValues[] = '';
            }
        } else {
            // Minimal structure - just insert with id
            $insertColumns[] = 'id';
            $insertValues[] = 1;
        }

        // Build and execute INSERT statement
        $columnsStr = implode(', ', $insertColumns);
        $valuesStr = implode(', ', array_map(function($val) {
            return is_string($val) ? "'" . addslashes($val) . "'" : $val;
        }, $insertValues));
        
        $insertSql = "INSERT INTO roulette_state ($columnsStr) VALUES ($valuesStr)";
        $pdo->exec($insertSql);

        // Verify the table has data
        $result = $pdo->query("SELECT * FROM roulette_state WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        if (!$result) {
            throw new Exception("Failed to initialize roulette_state table");
        }

        echo "<p class='success'>✓ Game state reset successfully</p>";
        echo "<p class='info'>Table structure detected: " . count($columns) . " columns</p>";
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Error resetting game state: " . $e->getMessage() . "</p>";
        // Continue with other resets even if this one fails
    }

    // Reset roulette_analytics table
    echo "<h2>Resetting Analytics Data</h2>";
    try {
        // Check if table exists
        $tableExists = $pdo->query("SHOW TABLES LIKE 'roulette_analytics'")->rowCount() > 0;
        
        if ($tableExists) {
            // First delete all records
            $pdo->exec("DELETE FROM roulette_analytics");

            // Then truncate the table to reset auto-increment
            $pdo->exec("TRUNCATE TABLE roulette_analytics");

            // Reset auto-increment value
            $pdo->exec("ALTER TABLE roulette_analytics AUTO_INCREMENT = 1");

            // Get columns to build appropriate INSERT
            $analyticsColumns = [];
            $result = $pdo->query("SHOW COLUMNS FROM roulette_analytics");
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $analyticsColumns[] = $row['Field'];
            }

            // Build INSERT based on available columns
            $insertCols = [];
            $insertVals = [];
            
            if (in_array('current_draw_number', $analyticsColumns)) {
                $insertCols[] = 'current_draw_number';
                $insertVals[] = 1;
            }
            
            if (in_array('all_spins', $analyticsColumns)) {
                $insertCols[] = 'all_spins';
                $insertVals[] = '[]';
            }
            
            if (in_array('number_frequency', $analyticsColumns)) {
                $insertCols[] = 'number_frequency';
                $insertVals[] = '{}';
            }
            
            if (in_array('id', $analyticsColumns)) {
                $insertCols[] = 'id';
                $insertVals[] = 1;
            }

            if (!empty($insertCols)) {
                $colsStr = implode(', ', $insertCols);
                $valsStr = implode(', ', array_map(function($val) {
                    return is_string($val) ? "'" . addslashes($val) . "'" : $val;
                }, $insertVals));
                
                $pdo->exec("INSERT INTO roulette_analytics ($colsStr) VALUES ($valsStr)");
            }

            // Verify the table has data
            $result = $pdo->query("SELECT * FROM roulette_analytics WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
            if (!$result) {
                throw new Exception("Failed to initialize roulette_analytics table");
            }

            echo "<p class='success'>✓ Analytics data reset successfully</p>";
        } else {
            echo "<p class='info'>ℹ roulette_analytics table does not exist, skipping</p>";
        }
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Error resetting analytics data: " . $e->getMessage() . "</p>";
        // Continue with other resets even if this one fails
    }

    // Reset detailed_draw_results table
    echo "<h2>Resetting Draw Results</h2>";
    try {
        // Check if table exists
        $tableExists = $pdo->query("SHOW TABLES LIKE 'detailed_draw_results'")->rowCount() > 0;
        
        if ($tableExists) {
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
        } else {
            echo "<p class='info'>ℹ detailed_draw_results table does not exist, skipping</p>";
        }
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Error resetting draw results: " . $e->getMessage() . "</p>";
        // Continue with other resets even if this one fails
    }

    // Reset draw_history table (check for both possible table names)
    echo "<h2>Resetting Draw History</h2>";
    try {
        // Check which draw history table exists
        $drawHistoryExists = $pdo->query("SHOW TABLES LIKE 'draw_history'")->rowCount() > 0;
        $rouletteDrawHistoryExists = $pdo->query("SHOW TABLES LIKE 'roulette_draw_history'")->rowCount() > 0;
        
        if ($drawHistoryExists) {
            $pdo->exec("TRUNCATE TABLE draw_history");
            $pdo->exec("ALTER TABLE draw_history AUTO_INCREMENT = 1");
            echo "<p class='success'>✓ Draw history (draw_history) reset successfully</p>";
        } else if ($rouletteDrawHistoryExists) {
            $pdo->exec("TRUNCATE TABLE roulette_draw_history");
            $pdo->exec("ALTER TABLE roulette_draw_history AUTO_INCREMENT = 1");
            echo "<p class='success'>✓ Draw history (roulette_draw_history) reset successfully</p>";
        } else {
            echo "<p class='info'>ℹ Draw history table does not exist, skipping</p>";
        }
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Error resetting draw history: " . $e->getMessage() . "</p>";
        // Continue with other resets even if this one fails
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
        // Continue with other resets even if this one fails
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
        // Continue even if this one fails
    }

    echo "<h2 class='success'>Database Reset Complete!</h2>";
    echo "<p>All roulette game data has been reset to initial values.</p>";
    echo "<p class='info'>ℹ Note: TRUNCATE operations auto-commit in MySQL (this is normal).</p>";
    echo "<p><a href='test_db.php' style='background-color: #4CAF50; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px;'>View Database Status</a> &nbsp;
            <a href='tvdisplay/index.html' style='background-color: #2196F3; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px;'>Launch Roulette Game</a></p>";

} catch (Exception $e) {
    // Catch any unexpected errors
    echo "<p class='error'>Unexpected error: " . $e->getMessage() . "</p>";
    echo "<p>Please try running the <a href='setup_database.php'>setup_database.php</a> script instead to create missing tables.</p>";
}

// Close HTML
echo "</body></html>";
?>