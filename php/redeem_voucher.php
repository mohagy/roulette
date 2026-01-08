<?php
/**
 * Voucher Redemption API
 *
 * This script handles voucher redemption and updates the user's cash balance
 * using both the transactions table and the dedicated voucher_transactions table.
 */

// Include database connection
require_once 'db_connect.php';

// Start session to get user information
session_start();

// Set headers for JSON response
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'User not authenticated. Please log in.'
    ]);
    exit;
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method. Only POST is allowed.'
    ]);
    exit;
}

// Get POST data
$postData = json_decode(file_get_contents('php://input'), true);

// If no JSON data, try regular POST
if (!$postData) {
    $postData = $_POST;
}

// Validate required parameters
if (empty($postData['voucher_code'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Voucher code is required.'
    ]);
    exit;
}

// Get parameters
$voucherCode = trim($postData['voucher_code']);
$userId = $_SESSION['user_id']; // Use the logged-in user's ID

try {
    // Start transaction
    $conn->begin_transaction();

    // First check if the voucher exists and is not used
    $voucherStmt = $conn->prepare("SELECT voucher_id, amount, is_used FROM vouchers WHERE voucher_code = ?");
    $voucherStmt->bind_param("s", $voucherCode);
    $voucherStmt->execute();
    $voucherResult = $voucherStmt->get_result();

    if ($voucherResult->num_rows === 0) {
        throw new Exception("Invalid voucher code");
    }

    $voucherRow = $voucherResult->fetch_assoc();
    $voucherId = $voucherRow['voucher_id'];
    $voucherAmount = $voucherRow['amount'];
    $isUsed = $voucherRow['is_used'];

    if ($isUsed) {
        throw new Exception("Voucher has already been used");
    }

    $voucherStmt->close();

    // Get current user balance
    $balanceStmt = $conn->prepare("SELECT cash_balance FROM users WHERE user_id = ?");
    $balanceStmt->bind_param("i", $userId);
    $balanceStmt->execute();
    $balanceResult = $balanceStmt->get_result();

    if ($balanceResult->num_rows === 0) {
        throw new Exception("User not found");
    }

    $balanceRow = $balanceResult->fetch_assoc();
    $currentBalance = $balanceRow['cash_balance'];
    $newBalance = $currentBalance + $voucherAmount;

    $balanceStmt->close();

    // 1. Create transaction record in the main transactions table
    $transactionStmt = $conn->prepare("
        INSERT INTO transactions (
            user_id,
            amount,
            balance_after,
            transaction_type,
            reference_id,
            description
        ) VALUES (?, ?, ?, 'voucher', ?, ?)
    ");

    $description = "Voucher redemption: " . $voucherCode;

    $transactionStmt->bind_param("idsss", $userId, $voucherAmount, $newBalance, $voucherCode, $description);
    $transactionStmt->execute();

    if ($transactionStmt->error) {
        throw new Exception("Transaction error: " . $transactionStmt->error);
    }

    $transactionId = $transactionStmt->insert_id;
    $transactionStmt->close();

    // 2. Create voucher transaction record in the dedicated voucher_transactions table
    $voucherTransactionStmt = $conn->prepare("
        INSERT INTO voucher_transactions (
            voucher_id,
            user_id,
            amount,
            transaction_id
        ) VALUES (?, ?, ?, ?)
    ");

    $voucherTransactionStmt->bind_param("iidd", $voucherId, $userId, $voucherAmount, $transactionId);
    $voucherTransactionStmt->execute();

    if ($voucherTransactionStmt->error) {
        throw new Exception("Voucher transaction error: " . $voucherTransactionStmt->error);
    }

    $voucherTransactionStmt->close();

    // 3. Mark voucher as used
    $updateVoucherStmt = $conn->prepare("
        UPDATE vouchers
        SET is_used = 1,
            used_by = ?,
            used_at = NOW()
        WHERE voucher_id = ?
    ");

    $updateVoucherStmt->bind_param("ii", $userId, $voucherId);
    $updateVoucherStmt->execute();

    if ($updateVoucherStmt->error) {
        throw new Exception("Voucher update error: " . $updateVoucherStmt->error);
    }

    $updateVoucherStmt->close();

    // 4. Update user balance
    $updateBalanceStmt = $conn->prepare("UPDATE users SET cash_balance = ? WHERE user_id = ?");
    $updateBalanceStmt->bind_param("di", $newBalance, $userId);
    $updateBalanceStmt->execute();

    if ($updateBalanceStmt->error) {
        throw new Exception("Balance update error: " . $updateBalanceStmt->error);
    }

    $updateBalanceStmt->close();

    // Commit the transaction
    $conn->commit();

    // Return success response
    echo json_encode([
        'status' => 'success',
        'message' => 'Voucher redeemed successfully.',
        'voucher_amount' => $voucherAmount,
        'new_balance' => $newBalance
    ]);

} catch (Exception $e) {
    // Rollback the transaction on error
    $conn->rollback();

    // Return error response
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to redeem voucher: ' . $e->getMessage()
    ]);
}

// Close the database connection
$conn->close();
