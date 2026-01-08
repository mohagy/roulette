<?php
/**
 * process_cashout.php
 * 
 * API endpoint to process cashout of winning bets
 * This updates the betting slip status and records the payout
 */

// Save any potential output
ob_start();

// Set response header to JSON
header('Content-Type: application/json');

// Include the database connection
require_once 'db_connect.php';

// Create logs directory if it doesn't exist
if (!file_exists('../logs')) {
    mkdir('../logs', 0777, true);
}

/**
 * Log messages to a file
 * 
 * @param string $message The message to log
 * @param string $level The log level (info, warning, error)
 * @return void
 */
function log_message($message, $level = 'info') {
    $log_file = '../logs/cashout.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_line = "[$timestamp] [$level] $message" . PHP_EOL;
    
    // Append to log file
    file_put_contents($log_file, $log_line, FILE_APPEND);
}

/**
 * Send a JSON response and exit
 * 
 * @param string $status The status of the response (success, error)
 * @param string $message The response message
 * @param array $data Additional data to include in the response
 * @return void
 */
function send_response($status, $message, $data = []) {
    // Clear any previous output
    ob_clean();
    
    $response = [
        'status' => $status,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => $data
    ];
    
    echo json_encode($response);
    exit;
}

// Get the raw input data
$input_data = file_get_contents('php://input');
$data = json_decode($input_data, true);

// Validate required fields
if (!$data) {
    log_message("Invalid JSON data received: $input_data", 'error');
    send_response('error', 'Invalid JSON data');
}

if (!isset($data['slip_number']) || !isset($data['win_amount'])) {
    log_message("Missing required fields in data: " . json_encode($data), 'error');
    send_response('error', 'Missing required fields: slip_number and win_amount are required');
}

// Extract data
$slip_number = $data['slip_number'];
$win_amount = floatval($data['win_amount']);
$draw_number = isset($data['draw_number']) ? intval($data['draw_number']) : null;
$winning_number = isset($data['winning_number']) ? intval($data['winning_number']) : null;
$operator_id = isset($data['operator_id']) ? $data['operator_id'] : 'system';
$cashout_time = date('Y-m-d H:i:s');

log_message("Processing cashout for slip: $slip_number, amount: $win_amount");

try {
    // Begin transaction
    $conn->beginTransaction();
    
    // Check if the slip exists and hasn't been cashed out yet
    $check_stmt = $conn->prepare("
        SELECT bs.id, bs.player_id, bs.total_stake, bs.potential_payout, bs.status, bs.draw_number, bs.paid_out_amount
        FROM betting_slips bs
        WHERE bs.slip_number = ?
        LIMIT 1
    ");
    
    $check_stmt->execute([$slip_number]);
    $slip = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$slip) {
        log_message("Betting slip not found: $slip_number", 'error');
        send_response('error', 'Betting slip not found');
    }
    
    // Check if already cashed out
    if ($slip['status'] === 'cashed_out') {
        log_message("Betting slip already cashed out: $slip_number", 'error');
        send_response('error', 'This betting slip has already been cashed out');
    }
    
    // Verify the draw number matches if provided
    if ($draw_number !== null && $slip['draw_number'] != $draw_number) {
        log_message("Draw number mismatch: expected {$slip['draw_number']}, got $draw_number", 'error');
        send_response('error', 'Draw number mismatch');
    }
    
    // Verify the win amount is valid (not too high)
    $max_win = $slip['potential_payout'] * 1.01; // Allow for 1% rounding error
    if ($win_amount > $max_win) {
        log_message("Win amount too high: $win_amount > $max_win", 'error');
        send_response('error', 'Win amount is higher than the potential payout');
    }
    
    // Update the betting slip status
    $update_stmt = $conn->prepare("
        UPDATE betting_slips
        SET status = 'cashed_out',
            paid_out_amount = ?,
            cashout_time = ?,
            cashier_id = ?,
            winning_number = ?
        WHERE slip_number = ?
    ");
    
    $update_stmt->execute([
        $win_amount,
        $cashout_time,
        $operator_id,
        $winning_number,
        $slip_number
    ]);
    
    // Update the player's balance if needed
    // This is optional depending on your requirements
    if ($slip['player_id'] != 1) { // Skip for guest player
        $update_player_stmt = $conn->prepare("
            UPDATE players
            SET balance = balance + ?
            WHERE player_id = ?
        ");
        
        $update_player_stmt->execute([$win_amount, $slip['player_id']]);
        log_message("Updated balance for player ID {$slip['player_id']} with win amount $win_amount");
    }
    
    // Record the cashout in transactions log
    $transaction_stmt = $conn->prepare("
        INSERT INTO cashout_transactions
        (slip_number, draw_number, win_amount, cashout_time, operator_id, player_id)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $transaction_stmt->execute([
        $slip_number,
        $draw_number ?: $slip['draw_number'],
        $win_amount,
        $cashout_time,
        $operator_id,
        $slip['player_id']
    ]);
    
    // Commit transaction
    $conn->commit();
    
    // Send success response
    log_message("Cashout successful for slip: $slip_number, amount: $win_amount");
    send_response('success', 'Cashout processed successfully', [
        'slip_number' => $slip_number,
        'win_amount' => $win_amount,
        'cashout_time' => $cashout_time
    ]);
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    log_message("Database error: " . $e->getMessage(), 'error');
    send_response('error', 'Database error: ' . $e->getMessage());
} 