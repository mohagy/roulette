<?php
// Set response header to JSON
header('Content-Type: application/json');

// Include database connection
require_once 'php/db_connect.php';

// Get the slip ID from the request
$slip_id = isset($_GET['slip_id']) ? intval($_GET['slip_id']) : 0;

if ($slip_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid slip ID',
        'exists' => false
    ]);
    exit;
}

try {
    // Check if the slip exists with user information
    $stmt = $conn->prepare("
        SELECT bs.slip_id, bs.slip_number, bs.draw_number, bs.total_stake, bs.potential_payout, bs.user_id, u.username
        FROM betting_slips bs
        LEFT JOIN users u ON bs.user_id = u.user_id
        WHERE bs.slip_id = ?
    ");

    if (!$stmt) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }

    $stmt->bind_param('i', $slip_id);

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Slip not found',
            'exists' => false
        ]);
        exit;
    }

    // Get the slip data
    $slip = $result->fetch_assoc();

    // Check if the slip has bets
    $stmt = $conn->prepare("
        SELECT COUNT(*) as bet_count
        FROM slip_details
        WHERE slip_id = ?
    ");

    if (!$stmt) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }

    $stmt->bind_param('i', $slip_id);

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $bet_count = $result->fetch_assoc()['bet_count'];

    echo json_encode([
        'success' => true,
        'message' => 'Slip found',
        'exists' => true,
        'slip' => [
            'slip_id' => $slip['slip_id'],
            'slip_number' => $slip['slip_number'],
            'draw_number' => $slip['draw_number'],
            'total_stake' => $slip['total_stake'],
            'potential_payout' => $slip['potential_payout'],
            'user_id' => $slip['user_id'],
            'username' => $slip['username']
        ],
        'has_bets' => $bet_count > 0,
        'bet_count' => $bet_count
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error checking slip: ' . $e->getMessage(),
        'exists' => false
    ]);
}
?>
