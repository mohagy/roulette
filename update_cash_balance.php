<?php
// Start session
session_start();

// Set response header to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Not authenticated'
    ]);
    exit;
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Get POST data
$postData = json_decode(file_get_contents('php://input'), true);

// Validate POST data
if (!isset($postData['amount']) || !is_numeric($postData['amount'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid amount'
    ]);
    exit;
}

// Get transaction details
$amount = floatval($postData['amount']);
$transactionType = isset($postData['transaction_type']) ? $postData['transaction_type'] : 'bet';
$referenceId = isset($postData['reference_id']) ? $postData['reference_id'] : null;
$description = isset($postData['description']) ? $postData['description'] : null;

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
    // Get user ID from session
    $userId = $_SESSION['user_id'];

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
    $newBalance = $currentBalance + $amount;

    // Check if new balance would be negative
    if ($newBalance < 0) {
        throw new Exception("Insufficient funds");
    }

    // Update user's cash balance
    $stmt = $conn->prepare("UPDATE users SET cash_balance = ? WHERE user_id = ?");
    $stmt->bind_param("di", $newBalance, $userId);
    if (!$stmt->execute()) {
        throw new Exception("Failed to update cash balance");
    }

    // Record transaction
    $stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, balance_after, transaction_type, reference_id, description) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iddsss", $userId, $amount, $newBalance, $transactionType, $referenceId, $description);
    if (!$stmt->execute()) {
        throw new Exception("Failed to record transaction");
    }

    // Commit transaction
    $conn->commit();

    // Return success response
    echo json_encode([
        'status' => 'success',
        'previous_balance' => $currentBalance,
        'amount' => $amount,
        'new_balance' => $newBalance,
        'transaction_type' => $transactionType
    ]);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();

    // Return error response
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

// Close connection
$conn->close();
?>
