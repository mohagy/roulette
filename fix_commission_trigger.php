<?php
// Set response header to JSON
header('Content-Type: application/json');

// Start session to get user information
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Not authorized. Admin access required.'
    ]);
    exit;
}

// Database connection parameters
$servername = "localhost";
$username = "root";  // Default XAMPP username
$password = "";      // Default XAMPP password (empty)
$dbname = "roulette";  // Using the roulette database

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]);
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    // Step 1: Drop the existing trigger
    $dropTriggerSQL = "DROP TRIGGER IF EXISTS update_commission_summary";
    if (!$conn->query($dropTriggerSQL)) {
        throw new Exception("Failed to drop existing trigger: " . $conn->error);
    }
    
    // Step 2: Create the new trigger with user_id included
    $createTriggerSQL = "
    CREATE TRIGGER update_commission_summary AFTER INSERT ON commission
    FOR EACH ROW
    BEGIN
        INSERT INTO commission_summary (user_id, date_created, total_bets, total_commission)
        VALUES (NEW.user_id, NEW.date_created, NEW.bet_amount, NEW.commission_amount)
        ON DUPLICATE KEY UPDATE
        total_bets = total_bets + NEW.bet_amount,
        total_commission = total_commission + NEW.commission_amount;
    END
    ";
    
    if (!$conn->query($createTriggerSQL)) {
        throw new Exception("Failed to create new trigger: " . $conn->error);
    }
    
    // Step 3: Fix existing commission_summary records with NULL or 0 user_id
    $result = $conn->query("SELECT * FROM commission_summary WHERE user_id = 0");
    $nullRecords = $result->num_rows;
    
    if ($nullRecords > 0) {
        echo "Found $nullRecords records with user_id = 0 in commission_summary.\n";
        
        while ($row = $result->fetch_assoc()) {
            $date = $row['date_created'];
            $summaryId = $row['summary_id'];
            $totalBets = $row['total_bets'];
            $totalCommission = $row['total_commission'];
            
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
                
                // Check if there's already a record for this user and date
                $existingStmt = $conn->prepare("
                    SELECT * FROM commission_summary 
                    WHERE user_id = ? AND date_created = ?
                ");
                $existingStmt->bind_param("is", $userId, $date);
                $existingStmt->execute();
                $existingResult = $existingStmt->get_result();
                
                if ($existingResult->num_rows > 0) {
                    // Merge with existing record
                    $existingRow = $existingResult->fetch_assoc();
                    $existingId = $existingRow['summary_id'];
                    $newTotalBets = $existingRow['total_bets'] + $totalBets;
                    $newTotalCommission = $existingRow['total_commission'] + $totalCommission;
                    
                    $updateStmt = $conn->prepare("
                        UPDATE commission_summary 
                        SET total_bets = ?, total_commission = ? 
                        WHERE summary_id = ?
                    ");
                    $updateStmt->bind_param("ddi", $newTotalBets, $newTotalCommission, $existingId);
                    
                    if ($updateStmt->execute()) {
                        // Delete the record with user_id = 0
                        $deleteStmt = $conn->prepare("DELETE FROM commission_summary WHERE summary_id = ?");
                        $deleteStmt->bind_param("i", $summaryId);
                        $deleteStmt->execute();
                    }
                } else {
                    // Update the record with the found user_id
                    $updateStmt = $conn->prepare("
                        UPDATE commission_summary 
                        SET user_id = ? 
                        WHERE summary_id = ?
                    ");
                    $updateStmt->bind_param("ii", $userId, $summaryId);
                    $updateStmt->execute();
                }
            }
        }
    }
    
    // Step 4: Rebuild commission_summary data from commission records
    $conn->query("TRUNCATE TABLE commission_summary");
    
    $rebuildSQL = "
    INSERT INTO commission_summary (user_id, date_created, total_bets, total_commission)
    SELECT user_id, date_created, SUM(bet_amount) as total_bets, SUM(commission_amount) as total_commission
    FROM commission
    GROUP BY user_id, date_created
    ";
    
    if (!$conn->query($rebuildSQL)) {
        throw new Exception("Failed to rebuild commission_summary data: " . $conn->error);
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Commission trigger fixed and commission_summary data rebuilt successfully.',
        'details' => [
            'trigger_updated' => true,
            'null_records_fixed' => $nullRecords,
            'commission_summary_rebuilt' => true
        ]
    ]);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

// Close connection
$conn->close();
?>
