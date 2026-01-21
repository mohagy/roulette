<?php
// Database connection parameters
$servername = "localhost";
$username = "root";  // Default XAMPP username
$password = "";      // Default XAMPP password (empty)
$dbname = "roulette";  // Using the roulette database

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Starting commission_summary fix...\n";

// Start transaction
$conn->begin_transaction();

try {
    // Find records with NULL user_id
    $result = $conn->query("SELECT * FROM commission_summary WHERE user_id IS NULL OR user_id = 0");
    $nullRecords = $result->num_rows;
    
    echo "Found $nullRecords records with NULL or 0 user_id.\n";
    
    if ($nullRecords > 0) {
        while ($row = $result->fetch_assoc()) {
            $date = $row['date_created'];
            $summaryId = $row['summary_id'];
            $totalBets = $row['total_bets'];
            $totalCommission = $row['total_commission'];
            
            echo "Processing summary_id $summaryId for date $date...\n";
            
            // Since we can't update due to unique constraint, we'll merge this data with existing records
            // First, find all existing records for this date
            $existingStmt = $conn->prepare("
                SELECT * FROM commission_summary 
                WHERE date_created = ? 
                AND user_id IS NOT NULL 
                AND user_id > 0
            ");
            $existingStmt->bind_param("s", $date);
            $existingStmt->execute();
            $existingResult = $existingStmt->get_result();
            
            if ($existingResult->num_rows > 0) {
                // Get the first record to merge with
                $existingRow = $existingResult->fetch_assoc();
                $existingId = $existingRow['summary_id'];
                $existingUserId = $existingRow['user_id'];
                $newTotalBets = $existingRow['total_bets'] + $totalBets;
                $newTotalCommission = $existingRow['total_commission'] + $totalCommission;
                
                echo "Merging data with existing record (summary_id $existingId, user_id $existingUserId)...\n";
                
                // Update the existing record with merged totals
                $updateStmt = $conn->prepare("
                    UPDATE commission_summary 
                    SET total_bets = ?, total_commission = ? 
                    WHERE summary_id = ?
                ");
                $updateStmt->bind_param("ddi", $newTotalBets, $newTotalCommission, $existingId);
                
                if ($updateStmt->execute()) {
                    echo "Successfully merged data into summary_id $existingId.\n";
                    
                    // Now delete the NULL user_id record
                    $deleteStmt = $conn->prepare("DELETE FROM commission_summary WHERE summary_id = ?");
                    $deleteStmt->bind_param("i", $summaryId);
                    
                    if ($deleteStmt->execute()) {
                        echo "Successfully deleted summary_id $summaryId with NULL user_id.\n";
                    } else {
                        echo "Error deleting summary_id $summaryId: " . $deleteStmt->error . "\n";
                    }
                } else {
                    echo "Error updating summary_id $existingId: " . $updateStmt->error . "\n";
                }
            } else {
                echo "No existing records found for date $date. Creating new record with user_id 1...\n";
                
                // Create a new record with user_id 1
                $insertStmt = $conn->prepare("
                    INSERT INTO commission_summary 
                    (user_id, date_created, total_bets, total_commission) 
                    VALUES (1, ?, ?, ?)
                ");
                $insertStmt->bind_param("sdd", $date, $totalBets, $totalCommission);
                
                if ($insertStmt->execute()) {
                    echo "Successfully created new record with user_id 1.\n";
                    
                    // Now delete the NULL user_id record
                    $deleteStmt = $conn->prepare("DELETE FROM commission_summary WHERE summary_id = ?");
                    $deleteStmt->bind_param("i", $summaryId);
                    
                    if ($deleteStmt->execute()) {
                        echo "Successfully deleted summary_id $summaryId with NULL user_id.\n";
                    } else {
                        echo "Error deleting summary_id $summaryId: " . $deleteStmt->error . "\n";
                    }
                } else {
                    echo "Error creating new record: " . $insertStmt->error . "\n";
                }
            }
        }
    }
    
    // Add NOT NULL constraint to user_id column if not already added
    echo "\nAdding NOT NULL constraint to user_id column...\n";
    
    if ($conn->query("ALTER TABLE commission_summary MODIFY user_id INT(11) NOT NULL")) {
        echo "Successfully added NOT NULL constraint to user_id column.\n";
    } else {
        echo "Error adding NOT NULL constraint: " . $conn->error . "\n";
    }
    
    // Commit transaction
    $conn->commit();
    echo "\nCommission summary fix completed successfully.\n";
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo "Error: " . $e->getMessage() . "\n";
}

// Close connection
$conn->close();
?>
