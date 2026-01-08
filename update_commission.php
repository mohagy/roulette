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
if (!isset($postData['bet_amount']) || !is_numeric($postData['bet_amount'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid bet amount'
    ]);
    exit;
}

// Get bet amount
$betAmount = floatval($postData['bet_amount']);

// Get optional slip number for reference
$slipNumber = isset($postData['slip_number']) ? $postData['slip_number'] : null;

// Calculate commission (4%)
$commissionAmount = $betAmount * 0.04;

// Log the commission update
error_log("Updating commission: Amount: $betAmount, Commission: $commissionAmount, Slip: " . ($slipNumber ?: 'N/A'));

// Get today's date
$today = date('Y-m-d');

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

// Start transaction
$conn->begin_transaction();

try {
    // Check if the commission table has a slip_number column
    $checkStmt = $conn->prepare("SHOW COLUMNS FROM commission LIKE 'slip_number'");
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $hasSlipNumberColumn = $result->num_rows > 0;
    $checkStmt->close();

    // Insert commission record
    if ($hasSlipNumberColumn && $slipNumber) {
        // If the slip_number column exists and we have a slip number, include it
        $stmt = $conn->prepare("INSERT INTO commission (user_id, bet_amount, commission_amount, date_created, slip_number) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iddss", $userId, $betAmount, $commissionAmount, $today, $slipNumber);
    } else {
        // Otherwise use the original query
        $stmt = $conn->prepare("INSERT INTO commission (user_id, bet_amount, commission_amount, date_created) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("idds", $userId, $betAmount, $commissionAmount, $today);
    }

    if (!$stmt->execute()) {
        throw new Exception("Failed to insert commission record: " . $stmt->error);
    }

    // Get updated commission summary for the specific cashier
    // Make sure we have a valid user_id
    if (!$userId || $userId <= 0) {
        $userId = 1; // Default to user_id 1 if not set
        error_log("Warning: Using default user_id 1 for commission because session user_id is invalid");
    }

    $stmt = $conn->prepare("SELECT * FROM commission_summary WHERE date_created = ? AND user_id = ?");
    $stmt->bind_param("si", $today, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        // Update existing summary record for this cashier
        $commissionSummary = $result->fetch_assoc();

        // Update the totals
        $newTotalBets = $commissionSummary['total_bets'] + $betAmount;
        $newTotalCommission = $commissionSummary['total_commission'] + $commissionAmount;

        // Update the record
        $updateStmt = $conn->prepare("UPDATE commission_summary SET total_bets = ?, total_commission = ? WHERE summary_id = ?");
        $updateStmt->bind_param("ddi", $newTotalBets, $newTotalCommission, $commissionSummary['summary_id']);

        if (!$updateStmt->execute()) {
            throw new Exception("Failed to update commission summary: " . $updateStmt->error);
        }

        // Update the summary object for response
        $commissionSummary['total_bets'] = $newTotalBets;
        $commissionSummary['total_commission'] = $newTotalCommission;
    } else {
        // Create new summary record for this cashier
        $insertStmt = $conn->prepare("INSERT INTO commission_summary (user_id, date_created, total_bets, total_commission) VALUES (?, ?, ?, ?)");
        $insertStmt->bind_param("isdd", $userId, $today, $betAmount, $commissionAmount);

        if (!$insertStmt->execute()) {
            throw new Exception("Failed to insert commission summary: " . $insertStmt->error);
        }

        // Get the new summary ID
        $summaryId = $conn->insert_id;

        // Create summary object for response
        $commissionSummary = [
            'summary_id' => $summaryId,
            'user_id' => $userId,
            'date_created' => $today,
            'total_bets' => $betAmount,
            'total_commission' => $commissionAmount
        ];
    }

    // Commit transaction
    $conn->commit();

    // Return success response
    echo json_encode([
        'status' => 'success',
        'bet_amount' => $betAmount,
        'commission_amount' => $commissionAmount,
        'commission_summary' => $commissionSummary
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
