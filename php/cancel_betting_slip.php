<?php
/**
 * Cancel Betting Slip
 *
 * This script handles the cancellation of betting slips for the upcoming draw.
 * Slips can only be cancelled if the draw has not yet occurred.
 */

// Include database connection
require_once 'db_connect.php';
session_start();

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to cancel betting slips'
    ]);
    exit;
}

// Get the cashier ID
$cashierId = $_SESSION['user_id'];

// Check if the slip ID was provided
if (!isset($_POST['slip_id']) || empty($_POST['slip_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Betting slip ID is required'
    ]);
    exit;
}

// Get the slip ID
$slipId = trim($_POST['slip_id']);

// Get the current draw number
$currentDrawNumber = isset($_POST['draw_number']) ? intval($_POST['draw_number']) : 0;

// Enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_log("Cancel betting slip request received for slip ID: $slipId");

try {
    // Start a transaction - use $pdo for PDO operations
    $pdo->beginTransaction();
    error_log("Transaction started for cancelling slip ID: $slipId");

    // Get the betting slip
    $stmt = $pdo->prepare("
        SELECT bs.*, u.cash_balance
        FROM betting_slips bs
        JOIN users u ON bs.user_id = u.user_id
        WHERE bs.slip_number = :slip_id
    ");
    $stmt->bindParam(':slip_id', $slipId);
    $stmt->execute();

    $slip = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if the slip exists
    if (!$slip) {
        throw new Exception('Betting slip not found');
    }

    // Check if the slip belongs to the current cashier
    if ($slip['user_id'] != $cashierId) {
        throw new Exception('You can only cancel your own betting slips');
    }

    // Check if the slip is for the current or future draw
    if ($slip['draw_number'] < $currentDrawNumber) {
        throw new Exception('Cannot cancel betting slips for past draws');
    }

    // Check if the slip is already cancelled
    if ($slip['status'] === 'cancelled') {
        throw new Exception('This betting slip is already cancelled');
    }

    // Check if the slip is already paid out
    if ($slip['status'] === 'paid') {
        throw new Exception('Cannot cancel betting slips that have been paid out');
    }

    // Update the slip status
    $stmt = $pdo->prepare("
        UPDATE betting_slips
        SET status = 'cancelled',
            is_cancelled = 1,
            updated_at = NOW()
        WHERE slip_id = :slip_id
    ");
    $slipIdFromDB = $slip['slip_id'];
    $stmt->bindParam(':slip_id', $slipIdFromDB);
    $stmt->execute();

    // Get the total amount of the slip
    $stmt = $pdo->prepare("
        SELECT SUM(b.bet_amount) as total_stake
        FROM bets b
        JOIN slip_details sd ON b.bet_id = sd.bet_id
        WHERE sd.slip_id = :slip_id
    ");
    $stmt->bindParam(':slip_id', $slipIdFromDB);
    $stmt->execute();

    $totalStake = $stmt->fetch(PDO::FETCH_ASSOC)['total_stake'] ?? 0;

    // Update the cashier's balance
    $newBalance = $slip['cash_balance'] + $totalStake;

    $stmt = $pdo->prepare("
        UPDATE users
        SET cash_balance = :new_balance
        WHERE user_id = :cashier_id
    ");
    $stmt->bindParam(':new_balance', $newBalance);
    $stmt->bindParam(':cashier_id', $cashierId);
    $stmt->execute();

    // Get the current balance after update
    $balanceStmt = $pdo->prepare("SELECT cash_balance FROM users WHERE user_id = :cashier_id");
    $balanceStmt->bindParam(':cashier_id', $cashierId);
    $balanceStmt->execute();
    $currentBalance = $balanceStmt->fetch(PDO::FETCH_ASSOC)['cash_balance'] ?? $newBalance;

    // Record the transaction with correct column names and enum value
    $stmt = $pdo->prepare("
        INSERT INTO transactions (
            user_id,
            amount,
            balance_after,
            transaction_type,
            description,
            reference_id
        ) VALUES (
            :user_id,
            :amount,
            :balance_after,
            'refund',
            'Betting slip cancelled',
            :reference_id
        )
    ");
    $stmt->bindParam(':user_id', $cashierId);
    $stmt->bindParam(':amount', $totalStake);
    $stmt->bindParam(':balance_after', $currentBalance);
    $stmt->bindParam(':reference_id', $slipIdFromDB);
    $stmt->execute();

    // Delete commission record for this slip
    $deleteCommissionStmt = $pdo->prepare("
        DELETE FROM commission
        WHERE slip_number = :slip_number
    ");
    $slipNumber = $slip['slip_number'] ?? '';
    $deleteCommissionStmt->bindParam(':slip_number', $slipNumber);
    $deleteCommissionStmt->execute();

    // Commit the transaction
    $pdo->commit();

    // Return success
    echo json_encode([
        'success' => true,
        'message' => "Betting slip #$slipId cancelled successfully",
        'cashBalance' => $newBalance
    ]);

    // Log success
    error_log("Betting slip #$slipId (ID: $slipIdFromDB) cancelled successfully. Amount refunded: $totalStake");

} catch (Exception $e) {
    // Rollback the transaction
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        error_log("Transaction rolled back for slip ID: $slipId");
    }

    // Log detailed error
    error_log("Error cancelling betting slip #$slipId: " . $e->getMessage());
    error_log("Error trace: " . $e->getTraceAsString());

    // Return error
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
