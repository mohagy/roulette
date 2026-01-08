<?php
/**
 * Update Betting Slips Table for Reprint Functionality
 * 
 * This script adds the necessary fields to the betting_slips table
 * to support the reprint slip functionality.
 */

// Include database connection
require_once 'db_connect.php';

// Create logs directory if it doesn't exist
if (!file_exists('../logs')) {
    mkdir('../logs', 0777, true);
}

// Function to log messages
function log_message($message, $level = 'INFO') {
    $log_file = '../logs/database_updates.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$level] $message\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
    
    // Also output to console if this is run from command line
    if (php_sapi_name() === 'cli') {
        echo $log_entry;
    }
}

// Function to check if a column exists in a table
function column_exists($conn, $table, $column) {
    $sql = "SHOW COLUMNS FROM `$table` LIKE '$column'";
    $result = $conn->query($sql);
    return $result && $result->num_rows > 0;
}

// Start the update process
log_message("Starting database update for reprint functionality");

try {
    // Check if the betting_slips table exists
    $result = $conn->query("SHOW TABLES LIKE 'betting_slips'");
    if ($result->num_rows === 0) {
        throw new Exception("betting_slips table does not exist");
    }
    
    log_message("betting_slips table exists, checking for required columns");
    
    // Begin transaction
    $conn->begin_transaction();
    
    // Add reprinted_from column if it doesn't exist
    if (!column_exists($conn, 'betting_slips', 'reprinted_from')) {
        log_message("Adding reprinted_from column to betting_slips table");
        $sql = "ALTER TABLE `betting_slips` 
                ADD COLUMN `reprinted_from` INT NULL DEFAULT NULL 
                COMMENT 'Reference to the original slip_id if this is a reprint'";
        
        if ($conn->query($sql)) {
            log_message("reprinted_from column added successfully");
        } else {
            throw new Exception("Failed to add reprinted_from column: " . $conn->error);
        }
    } else {
        log_message("reprinted_from column already exists");
    }
    
    // Add is_reprint column if it doesn't exist
    if (!column_exists($conn, 'betting_slips', 'is_reprint')) {
        log_message("Adding is_reprint column to betting_slips table");
        $sql = "ALTER TABLE `betting_slips` 
                ADD COLUMN `is_reprint` TINYINT(1) NOT NULL DEFAULT 0 
                COMMENT 'Whether this slip is a reprint'";
        
        if ($conn->query($sql)) {
            log_message("is_reprint column added successfully");
        } else {
            throw new Exception("Failed to add is_reprint column: " . $conn->error);
        }
    } else {
        log_message("is_reprint column already exists");
    }
    
    // Add reprint_count column if it doesn't exist
    if (!column_exists($conn, 'betting_slips', 'reprint_count')) {
        log_message("Adding reprint_count column to betting_slips table");
        $sql = "ALTER TABLE `betting_slips` 
                ADD COLUMN `reprint_count` INT NOT NULL DEFAULT 0 
                COMMENT 'Number of times this slip has been reprinted'";
        
        if ($conn->query($sql)) {
            log_message("reprint_count column added successfully");
        } else {
            throw new Exception("Failed to add reprint_count column: " . $conn->error);
        }
    } else {
        log_message("reprint_count column already exists");
    }
    
    // Add index on reprinted_from if it doesn't exist
    $result = $conn->query("SHOW INDEX FROM `betting_slips` WHERE Key_name = 'idx_betting_slips_reprinted_from'");
    if ($result->num_rows === 0) {
        log_message("Adding index on reprinted_from column");
        $sql = "ALTER TABLE `betting_slips` 
                ADD INDEX `idx_betting_slips_reprinted_from` (`reprinted_from`)";
        
        if ($conn->query($sql)) {
            log_message("Index on reprinted_from added successfully");
        } else {
            throw new Exception("Failed to add index on reprinted_from: " . $conn->error);
        }
    } else {
        log_message("Index on reprinted_from already exists");
    }
    
    // Commit the transaction
    $conn->commit();
    log_message("Database update completed successfully");
    
    echo json_encode([
        'success' => true,
        'message' => 'Database updated successfully for reprint functionality'
    ]);
    
} catch (Exception $e) {
    // Rollback the transaction on error
    if ($conn->inTransaction()) {
        $conn->rollback();
    }
    
    log_message("Error updating database: " . $e->getMessage(), 'ERROR');
    
    echo json_encode([
        'success' => false,
        'message' => 'Error updating database: ' . $e->getMessage()
    ]);
}
