<?php
// Set response header to JSON
header('Content-Type: application/json');

// Start session to get user information
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Not authenticated'
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

// Get user ID from session
$userId = $_SESSION['user_id'];

// Get today's date
$today = date('Y-m-d');

// Start transaction
$conn->begin_transaction();

try {
    // Log the start of the fix
    error_log("Starting commission fix for user $userId on $today");
    
    // Step 1: Fix any NULL user_id values in commission table
    $stmt = $conn->prepare("UPDATE commission SET user_id = ? WHERE (user_id IS NULL OR user_id = 0) AND date_created = ?");
    $stmt->bind_param("is", $userId, $today);
    $stmt->execute();
    $nullCommissionFixed = $stmt->affected_rows;
    
    // Step 2: Fix any NULL user_id values in commission_summary table
    $stmt = $conn->prepare("UPDATE commission_summary SET user_id = ? WHERE (user_id IS NULL OR user_id = 0) AND date_created = ?");
    $stmt->bind_param("is", $userId, $today);
    $stmt->execute();
    $nullSummaryFixed = $stmt->affected_rows;
    
    // Step 3: Recalculate commission based on betting slips
    // Get all betting slips for this user for today
    $stmt = $conn->prepare("SELECT * FROM betting_slips WHERE user_id = ? AND DATE(created_at) = ?");
    $stmt->bind_param("is", $userId, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $totalBets = 0;
    $totalCommission = 0;
    $processedSlips = [];
    
    if ($result->num_rows > 0) {
        while ($slip = $result->fetch_assoc()) {
            $totalBets += floatval($slip['total_stake']);
            $totalCommission += floatval($slip['total_stake']) * 0.04; // 4% commission
            $processedSlips[] = $slip['slip_number'];
            
            // Check if commission record exists for this slip
            $checkStmt = $conn->prepare("SELECT * FROM commission WHERE slip_number = ?");
            $checkStmt->bind_param("s", $slip['slip_number']);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows === 0) {
                // Create commission record for this slip
                $insertStmt = $conn->prepare("
                    INSERT INTO commission (
                        user_id,
                        bet_amount,
                        commission_amount,
                        slip_number,
                        transaction_id,
                        date_created
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                $commissionAmount = floatval($slip['total_stake']) * 0.04;
                $insertStmt->bind_param("idssss", $userId, $slip['total_stake'], $commissionAmount, $slip['slip_number'], $slip['transaction_id'], $today);
                $insertStmt->execute();
                
                error_log("Created commission record for slip {$slip['slip_number']} with amount {$slip['total_stake']} and commission $commissionAmount");
            }
        }
    }
    
    // Step 4: Update or create commission_summary record
    $stmt = $conn->prepare("SELECT * FROM commission_summary WHERE user_id = ? AND date_created = ?");
    $stmt->bind_param("is", $userId, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        // Update existing record
        $summaryRow = $result->fetch_assoc();
        $stmt = $conn->prepare("UPDATE commission_summary SET total_bets = ?, total_commission = ? WHERE summary_id = ?");
        $stmt->bind_param("ddi", $totalBets, $totalCommission, $summaryRow['summary_id']);
        $stmt->execute();
        $summaryUpdated = true;
        $summaryId = $summaryRow['summary_id'];
    } else {
        // Create new record
        $stmt = $conn->prepare("INSERT INTO commission_summary (user_id, date_created, total_bets, total_commission) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isdd", $userId, $today, $totalBets, $totalCommission);
        $stmt->execute();
        $summaryUpdated = false;
        $summaryId = $conn->insert_id;
    }
    
    // Step 5: Add NOT NULL constraint to user_id if it doesn't exist
    $result = $conn->query("SHOW COLUMNS FROM commission_summary WHERE Field = 'user_id' AND Null = 'NO'");
    if ($result->num_rows === 0) {
        $conn->query("ALTER TABLE commission_summary MODIFY user_id INT(11) NOT NULL");
        $notNullAdded = true;
    } else {
        $notNullAdded = false;
    }
    
    // Step 6: Add unique constraint if it doesn't exist
    $result = $conn->query("SHOW INDEXES FROM commission_summary WHERE Key_name = 'user_id_date_unique'");
    if ($result->num_rows === 0) {
        $conn->query("ALTER TABLE commission_summary ADD CONSTRAINT user_id_date_unique UNIQUE (user_id, date_created)");
        $uniqueAdded = true;
    } else {
        $uniqueAdded = false;
    }
    
    // Commit transaction
    $conn->commit();
    
    // Prepare response
    $response = [
        'status' => 'success',
        'user_id' => $userId,
        'today' => $today,
        'fixes_applied' => [
            'null_commission_fixed' => $nullCommissionFixed,
            'null_summary_fixed' => $nullSummaryFixed,
            'processed_slips' => count($processedSlips),
            'total_bets_calculated' => $totalBets,
            'total_commission_calculated' => $totalCommission,
            'summary_updated' => $summaryUpdated,
            'summary_id' => $summaryId,
            'not_null_constraint_added' => $notNullAdded,
            'unique_constraint_added' => $uniqueAdded
        ]
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
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
