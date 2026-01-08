<?php
/**
 * Direct Cash Update from Betting Slips
 * 
 * This script directly updates the cash balance based on sold betting slips.
 * It calculates the total amount of all sold bets and updates the user's cash balance accordingly.
 * This is a reliable way to ensure the cash balance is always accurate.
 */

// Start session
session_start();

// Set response header based on request type
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
if ($isAjax) {
    header('Content-Type: application/json');
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
    $error = "Database connection failed: " . $conn->connect_error;
    if ($isAjax) {
        die(json_encode(['status' => 'error', 'message' => $error]));
    } else {
        die($error);
    }
}

// Get the user ID from the request or use default
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 1;

// Start transaction
$conn->begin_transaction();

try {
    // Get current cash balance
    $stmt = $conn->prepare("SELECT cash_balance FROM users WHERE user_id = ? FOR UPDATE");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        throw new Exception("User not found");
    }

    $user = $result->fetch_assoc();
    $currentBalance = floatval($user['cash_balance']);
    
    // Get the initial balance (from a default value or first transaction)
    $initialBalance = 100000.00; // Default initial balance
    
    // Get the total amount of all sold bets for this user
    $stmt = $conn->prepare("
        SELECT SUM(bs.total_stake) as total_bet_amount
        FROM betting_slips bs
        WHERE bs.player_id = ?
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $totalBetAmount = floatval($row['total_bet_amount'] ?? 0);
    
    // Calculate what the balance should be
    $calculatedBalance = $initialBalance - $totalBetAmount;
    
    // Only update if the calculated balance is different
    if (abs($calculatedBalance - $currentBalance) > 0.01) { // Using small epsilon for float comparison
        // Update user's cash balance
        $stmt = $conn->prepare("UPDATE users SET cash_balance = ?, updated_at = NOW() WHERE user_id = ?");
        $stmt->bind_param("di", $calculatedBalance, $userId);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update cash balance: " . $stmt->error);
        }
        
        // Record transaction for the adjustment
        $adjustmentAmount = $calculatedBalance - $currentBalance;
        $referenceId = "DIRECT_UPDATE_" . time();
        $description = "Direct update based on total bet amount";
        $transactionType = "adjustment";
        
        $stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, balance_after, transaction_type, reference_id, description) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iddsss", $userId, $adjustmentAmount, $calculatedBalance, $transactionType, $referenceId, $description);
        if (!$stmt->execute()) {
            throw new Exception("Failed to record transaction: " . $stmt->error);
        }
        
        $message = "Cash balance updated from $currentBalance to $calculatedBalance (adjustment: $adjustmentAmount)";
        $updated = true;
    } else {
        $message = "Cash balance is already correct: $currentBalance";
        $updated = false;
    }

    // Commit transaction
    $conn->commit();

    // Return response based on request type
    if ($isAjax) {
        echo json_encode([
            'status' => 'success',
            'updated' => $updated,
            'message' => $message,
            'user_id' => $userId,
            'current_balance' => $currentBalance,
            'total_bet_amount' => $totalBetAmount,
            'calculated_balance' => $calculatedBalance,
            'new_balance' => $updated ? $calculatedBalance : $currentBalance
        ]);
    } else {
        echo "<h1>Cash Balance Update</h1>";
        echo "<p>User ID: $userId</p>";
        echo "<p>Current Balance: $currentBalance</p>";
        echo "<p>Initial Balance: $initialBalance</p>";
        echo "<p>Total Bet Amount: $totalBetAmount</p>";
        echo "<p>Calculated Balance: $calculatedBalance</p>";
        echo "<p>Result: $message</p>";
        
        echo "<p><a href='update_cash_from_bets.php?user_id=$userId'>Run Again</a></p>";
        echo "<p><a href='index.html'>Return to Game</a></p>";
    }
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    if ($isAjax) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    } else {
        echo "<h1>Error</h1>";
        echo "<p>" . $e->getMessage() . "</p>";
    }
}

// Close connection
$conn->close();
?>
