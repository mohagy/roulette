<?php
/**
 * Migration script to normalize the roulette_state table
 * 
 * This script will:
 * 1. Create a backup of the current roulette_state table
 * 2. Create the new normalized table structure
 * 3. Migrate data from the old structure to the new one
 */

// Set headers for CLI output
header('Content-Type: text/plain');

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include database configuration
require_once 'db_config.php';

echo "Starting migration of roulette_state table to normalized structure...\n";

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Step 1: Create a backup of the current roulette_state table
    echo "Creating backup of current roulette_state table...\n";
    
    // Check if the backup table already exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'roulette_state_backup'");
    $backupExists = $stmt->rowCount() > 0;
    
    if ($backupExists) {
        echo "Backup table already exists. Dropping it to create a fresh backup...\n";
        $pdo->exec("DROP TABLE IF EXISTS roulette_state_backup");
    }
    
    // Create the backup table
    $pdo->exec("CREATE TABLE roulette_state_backup AS SELECT * FROM roulette_state");
    echo "Backup created successfully.\n";
    
    // Step 2: Create the new normalized table structure
    echo "Creating new normalized table structure...\n";
    
    // Drop the existing table
    $pdo->exec("DROP TABLE IF EXISTS roulette_state");
    
    // Create the new normalized table
    $pdo->exec("
        CREATE TABLE roulette_state (
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
        )
    ");
    
    // Create indexes for better performance
    $pdo->exec("CREATE INDEX idx_roulette_state_draw_number ON roulette_state(draw_number)");
    $pdo->exec("CREATE INDEX idx_roulette_state_state_type ON roulette_state(state_type)");
    $pdo->exec("CREATE INDEX idx_roulette_state_created_at ON roulette_state(created_at)");
    
    echo "New table structure created successfully.\n";
    
    // Create the draw history table if it doesn't exist
    $stmt = $pdo->query("SHOW TABLES LIKE 'roulette_draw_history'");
    $drawHistoryExists = $stmt->rowCount() > 0;
    
    if (!$drawHistoryExists) {
        echo "Creating roulette_draw_history table...\n";
        
        $pdo->exec("
            CREATE TABLE roulette_draw_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                draw_number INT NOT NULL COMMENT 'The draw number',
                winning_number INT NOT NULL COMMENT 'The winning number (0-36)',
                winning_color VARCHAR(10) NOT NULL COMMENT 'Color of the winning number (red, black, green)',
                draw_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When the draw occurred',
                is_manual TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether this was a manual draw',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY (draw_number)
            )
        ");
        
        echo "Draw history table created successfully.\n";
    }
    
    // Step 3: Migrate data from the old structure to the new one
    echo "Migrating data from backup to new structure...\n";
    
    // Get the most recent record from the backup
    $stmt = $pdo->query("SELECT * FROM roulette_state_backup ORDER BY id DESC LIMIT 1");
    $latestState = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($latestState) {
        echo "Found latest state record (ID: {$latestState['id']}).\n";
        
        // Extract draw numbers from formatted strings
        $currentDrawNumber = intval(str_replace('#', '', $latestState['last_draw'] ?? '#0'));
        $nextDrawNumber = intval(str_replace('#', '', $latestState['next_draw'] ?? '#1'));
        
        // Insert the latest state into the new structure
        $stmt = $pdo->prepare("
            INSERT INTO roulette_state (
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
                'migration',
                :draw_number,
                :next_draw_number,
                :countdown_time,
                :end_time,
                :winning_number,
                :next_winning_number,
                :manual_mode,
                :additional_data
            )
        ");
        
        $additionalData = json_encode([
            'roll_history' => $latestState['roll_history'] ?? '',
            'roll_colors' => $latestState['roll_colors'] ?? '',
            'last_draw_formatted' => $latestState['last_draw'] ?? '#0',
            'next_draw_formatted' => $latestState['next_draw'] ?? '#1',
            'migration_source' => 'latest_state',
            'original_id' => $latestState['id']
        ]);
        
        $stmt->execute([
            ':draw_number' => $currentDrawNumber,
            ':next_draw_number' => $nextDrawNumber,
            ':countdown_time' => $latestState['countdown_time'] ?? 180,
            ':end_time' => $latestState['end_time'] ?? null,
            ':winning_number' => $latestState['winning_number'] ?? null,
            ':next_winning_number' => $latestState['next_draw_winning_number'] ?? null,
            ':manual_mode' => $latestState['manual_mode'] ?? 0,
            ':additional_data' => $additionalData
        ]);
        
        echo "Latest state migrated successfully.\n";
        
        // Extract historical draw data from roll_history and roll_colors
        if (!empty($latestState['roll_history']) && !empty($latestState['roll_colors'])) {
            echo "Extracting historical draw data...\n";
            
            $rollHistory = explode(',', $latestState['roll_history']);
            $rollColors = explode(',', $latestState['roll_colors']);
            
            // Make sure both arrays have the same length
            $count = min(count($rollHistory), count($rollColors));
            
            if ($count > 0) {
                echo "Found {$count} historical draws to migrate.\n";
                
                for ($i = 0; $i < $count; $i++) {
                    $number = intval($rollHistory[$i]);
                    $color = $rollColors[$i];
                    
                    // Calculate the draw number (going backward from current)
                    $drawNum = $currentDrawNumber - $i - 1;
                    if ($drawNum <= 0) continue; // Skip invalid draw numbers
                    
                    // Insert into draw history
                    $stmt = $pdo->prepare("
                        INSERT IGNORE INTO roulette_draw_history (
                            draw_number,
                            winning_number,
                            winning_color,
                            draw_time,
                            is_manual
                        ) VALUES (
                            :draw_number,
                            :winning_number,
                            :winning_color,
                            DATE_SUB(NOW(), INTERVAL :minutes MINUTE),
                            0
                        )
                    ");
                    
                    $stmt->execute([
                        ':draw_number' => $drawNum,
                        ':winning_number' => $number,
                        ':winning_color' => $color,
                        ':minutes' => ($i + 1) * 3 // Assuming 3-minute intervals
                    ]);
                }
                
                echo "Historical draw data migrated successfully.\n";
            } else {
                echo "No valid historical draw data found.\n";
            }
        }
    } else {
        echo "No existing state records found. Starting with a clean state.\n";
        
        // Insert an initial state record
        $stmt = $pdo->prepare("
            INSERT INTO roulette_state (
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
            )
        ");
        
        $initialEndTime = (time() * 1000) + (180 * 1000);
        
        $stmt->execute([
            ':end_time' => $initialEndTime,
            ':additional_data' => json_encode([
                'roll_history' => '',
                'roll_colors' => '',
                'last_draw_formatted' => '#0',
                'next_draw_formatted' => '#1',
                'migration_source' => 'new_setup'
            ])
        ]);
        
        echo "Initial state record created.\n";
    }
    
    // Commit the transaction
    $pdo->commit();
    
    echo "Migration completed successfully!\n";
    
} catch (PDOException $e) {
    // Rollback the transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo "Error during migration: " . $e->getMessage() . "\n";
    echo "Rolling back changes...\n";
    
    // If the migration failed, restore from backup
    try {
        echo "Attempting to restore from backup...\n";
        
        // Check if the backup table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'roulette_state_backup'");
        $backupExists = $stmt->rowCount() > 0;
        
        if ($backupExists) {
            // Drop the new table if it exists
            $pdo->exec("DROP TABLE IF EXISTS roulette_state");
            
            // Restore from backup
            $pdo->exec("CREATE TABLE roulette_state AS SELECT * FROM roulette_state_backup");
            
            echo "Restored from backup successfully.\n";
        } else {
            echo "No backup table found. Unable to restore.\n";
        }
    } catch (PDOException $restoreError) {
        echo "Error during restore: " . $restoreError->getMessage() . "\n";
    }
}

echo "Migration process completed.\n";
?>
