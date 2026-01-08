<?php
// Basic API for real-time transaction data
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "roulette";

// Set timezone
date_default_timezone_set('America/Guyana');

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $userId = $_SESSION['user_id'];

    // Get user balance
    $stmt = $conn->prepare("SELECT cash_balance FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $balance = $result->num_rows > 0 ? $result->fetch_assoc()['cash_balance'] : 0;

    // Get current draw number
    $stmt = $conn->prepare("SELECT current_draw_number FROM roulette_analytics WHERE id = 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $currentDraw = $result->num_rows > 0 ? $result->fetch_assoc()['current_draw_number'] : 1;

    // Get recent betting slips
    $stmt = $conn->prepare("
        SELECT 
            bs.slip_number,
            bs.draw_number,
            bs.total_stake,
            bs.potential_payout,
            bs.status,
            bs.paid_out_amount as actual_win,
            DATE_FORMAT(bs.created_at, '%Y-%m-%d %H:%i') as created_at,
            ddr.winning_number,
            ddr.color as winning_color
        FROM betting_slips bs
        LEFT JOIN transactions t ON bs.transaction_id = t.transaction_id
        LEFT JOIN detailed_draw_results ddr ON bs.draw_number = ddr.draw_number
        WHERE t.user_id = ?
        ORDER BY bs.created_at DESC
        LIMIT 20
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $slips = [];
    while ($row = $result->fetch_assoc()) {
        // Determine status based on draw completion
        if ($row['draw_number'] < $currentDraw) {
            // Past draw - check if we have results
            if ($row['winning_number'] !== null) {
                // We have results, determine win/loss
                if ($row['actual_win'] > 0) {
                    $row['status'] = 'win';
                } else {
                    $row['status'] = 'loss';
                }
            } else {
                $row['status'] = 'pending';
            }
        } else {
            // Future or current draw
            $row['status'] = 'active';
        }
        
        $slips[] = $row;
    }

    // Get recent transactions
    $stmt = $conn->prepare("
        SELECT 
            transaction_id,
            transaction_type,
            amount,
            balance_after,
            DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') as created_at
        FROM transactions
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 15
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }

    // Return data
    echo json_encode([
        'success' => true,
        'balance' => $balance,
        'current_draw' => $currentDraw,
        'slips' => $slips,
        'transactions' => $transactions,
        'timestamp' => time()
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
