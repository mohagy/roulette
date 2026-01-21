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
        // Get the most recent user_id from commission table for the same date
        while ($row = $result->fetch_assoc()) {
            $date = $row['date_created'];
            $summaryId = $row['summary_id'];
            
            echo "Processing summary_id $summaryId for date $date...\n";
            
            // Find a user_id from commission records for this date
            $userStmt = $conn->prepare("
                SELECT DISTINCT user_id 
                FROM commission 
                WHERE date_created = ? 
                AND user_id IS NOT NULL 
                AND user_id > 0
                LIMIT 1
            ");
            $userStmt->bind_param("s", $date);
            $userStmt->execute();
            $userResult = $userStmt->get_result();
            
            if ($userResult->num_rows > 0) {
                $userRow = $userResult->fetch_assoc();
                $userId = $userRow['user_id'];
                
                echo "Found user_id $userId for date $date. Updating summary record...\n";
                
                // Update the commission_summary record
                $updateStmt = $conn->prepare("
                    UPDATE commission_summary 
                    SET user_id = ? 
                    WHERE summary_id = ?
                ");
                $updateStmt->bind_param("ii", $userId, $summaryId);
                
                if ($updateStmt->execute()) {
                    echo "Successfully updated summary_id $summaryId with user_id $userId.\n";
                } else {
                    echo "Error updating summary_id $summaryId: " . $updateStmt->error . "\n";
                }
            } else {
                echo "No valid user_id found for date $date. Using default user_id 1...\n";
                
                // Use default user_id 1 if no valid user_id found
                $defaultUserId = 1;
                $updateStmt = $conn->prepare("
                    UPDATE commission_summary 
                    SET user_id = ? 
                    WHERE summary_id = ?
                ");
                $updateStmt->bind_param("ii", $defaultUserId, $summaryId);
                
                if ($updateStmt->execute()) {
                    echo "Successfully updated summary_id $summaryId with default user_id 1.\n";
                } else {
                    echo "Error updating summary_id $summaryId: " . $updateStmt->error . "\n";
                }
            }
        }
    }
    
    // Add NOT NULL constraint to user_id column
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
