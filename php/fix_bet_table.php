<?php
// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Updating bets table structure\n";
echo "==========================\n\n";

// Include database connection
require_once 'db_connect.php';

try {
    // Check if bet_numbers exists
    $result = $conn->query("SHOW COLUMNS FROM bets LIKE 'bet_numbers'");
    $hasBetNumbers = $result->num_rows > 0;
    
    // Check if bet_description exists
    $result = $conn->query("SHOW COLUMNS FROM bets LIKE 'bet_description'");
    $hasBetDescription = $result->num_rows > 0;
    
    // Begin transaction
    $conn->begin_transaction();
    
    if ($hasBetNumbers && !$hasBetDescription) {
        echo "Renaming column 'bet_numbers' to 'bet_description'...\n";
        $result = $conn->query("ALTER TABLE bets CHANGE COLUMN bet_numbers bet_description VARCHAR(100) NOT NULL");
        
        if (!$result) {
            throw new Exception("Failed to rename column: " . $conn->error);
        }
        
        echo "Column renamed successfully.\n";
    } elseif ($hasBetDescription) {
        echo "Column 'bet_description' already exists.\n";
    } else {
        echo "Adding 'bet_description' column...\n";
        $result = $conn->query("ALTER TABLE bets ADD COLUMN bet_description VARCHAR(100) NOT NULL AFTER bet_type");
        
        if (!$result) {
            throw new Exception("Failed to add column: " . $conn->error);
        }
        
        echo "Column added successfully.\n";
    }
    
    // Check if multiplier column exists
    $result = $conn->query("SHOW COLUMNS FROM bets LIKE 'multiplier'");
    $hasMultiplier = $result->num_rows > 0;
    
    if (!$hasMultiplier) {
        echo "Adding 'multiplier' column...\n";
        $result = $conn->query("ALTER TABLE bets ADD COLUMN multiplier DECIMAL(10,2) DEFAULT 0 AFTER bet_amount");
        
        if (!$result) {
            throw new Exception("Failed to add column: " . $conn->error);
        }
        
        echo "Column added successfully.\n";
    } else {
        echo "Column 'multiplier' already exists.\n";
    }
    
    // Check if potential_return column exists
    $result = $conn->query("SHOW COLUMNS FROM bets LIKE 'potential_return'");
    $hasPotentialReturn = $result->num_rows > 0;
    
    if (!$hasPotentialReturn) {
        echo "Adding 'potential_return' column...\n";
        $result = $conn->query("ALTER TABLE bets ADD COLUMN potential_return DECIMAL(10,2) DEFAULT 0 AFTER multiplier");
        
        if (!$result) {
            throw new Exception("Failed to add column: " . $conn->error);
        }
        
        echo "Column added successfully.\n";
    } else {
        echo "Column 'potential_return' already exists.\n";
    }
    
    // Commit transaction
    $conn->commit();
    
    echo "\nTable structure update completed successfully.\n";
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
    }
    
    echo "Error: " . $e->getMessage() . "\n";
}
?> 