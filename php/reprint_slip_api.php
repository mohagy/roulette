<?php
/**
 * Reprint Slip API
 *
 * This file handles API requests for the reprint slip functionality.
 */

// Start output buffering to prevent any unexpected output
ob_start();

// Set response header to JSON
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Include database connection
require_once 'db_connect.php';

// Create logs directory if it doesn't exist
if (!file_exists('../logs')) {
    mkdir('../logs', 0777, true);
}

// Function to log messages
function log_message($message, $level = 'INFO') {
    $log_file = '../logs/reprint_slip.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$level] $message\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);

    // Also log to PHP error log for easier debugging
    error_log("REPRINT_SLIP_API: [$level] $message");
}

// Set error reporting to show all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to send JSON response
function send_response($success, $message, $data = []) {
    $response = [
        'success' => $success,
        'message' => $message
    ];

    if (!empty($data)) {
        $response = array_merge($response, $data);
    }

    // Log the response for debugging
    log_message("Sending response: " . json_encode($response), $success ? 'INFO' : 'ERROR');

    // Make sure there's no output before our JSON
    if (ob_get_length()) ob_clean();

    // Set proper content type
    header('Content-Type: application/json');

    echo json_encode($response);
    exit;
}

// Get the action from the request
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Log the request for debugging
log_message("Received request with action: $action", 'INFO');
log_message("POST data: " . json_encode($_POST), 'DEBUG');

// Process based on the action
switch ($action) {
    case 'get_slip_info':
        get_slip_info();
        break;
    case 'reprint_slip':
        reprint_slip();
        break;
    default:
        send_response(false, 'Invalid action: ' . $action);
}

/**
 * Get slip information
 */
function get_slip_info() {
    global $conn;

    // Get the slip number from the request
    $slip_number = isset($_POST['slip_number']) ? $_POST['slip_number'] : '';

    if (empty($slip_number)) {
        send_response(false, 'Slip number is required');
    }

    log_message("Fetching information for slip number: $slip_number");

    try {
        // Get the slip information
        $stmt = $conn->prepare("
            SELECT bs.*, u.username
            FROM betting_slips bs
            LEFT JOIN users u ON bs.user_id = u.user_id
            WHERE bs.slip_number = ?
        ");

        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $conn->error);
        }

        $stmt->bind_param('s', $slip_number);

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            send_response(false, 'Betting slip not found');
        }

        $slip = $result->fetch_assoc();

        // Check if the slip is already paid out
        if ($slip['is_paid']) {
            send_response(false, 'This betting slip has already been paid out and cannot be reprinted');
        }

        // Check if the slip is cancelled
        if ($slip['is_cancelled']) {
            send_response(false, 'This betting slip has been cancelled and cannot be reprinted');
        }

        // Get the bets for this slip
        $stmt = $conn->prepare("
            SELECT b.*
            FROM bets b
            JOIN slip_details sd ON b.bet_id = sd.bet_id
            WHERE sd.slip_id = ?
        ");

        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $conn->error);
        }

        $stmt->bind_param('i', $slip['slip_id']);

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $result = $stmt->get_result();
        $bets = [];

        while ($bet = $result->fetch_assoc()) {
            $bets[] = $bet;
        }

        // Get the next draw number
        $next_draw_number = get_next_draw_number();

        // Send the response
        send_response(true, 'Slip information retrieved successfully', [
            'slip' => $slip,
            'bets' => $bets,
            'next_draw_number' => $next_draw_number
        ]);

    } catch (Exception $e) {
        log_message("Error fetching slip information: " . $e->getMessage(), 'ERROR');
        send_response(false, 'Error fetching slip information: ' . $e->getMessage());
    }
}

/**
 * Reprint a betting slip
 */
function reprint_slip() {
    global $conn;

    // Get the slip ID and draw number from the request
    $slip_id = isset($_POST['slip_id']) ? intval($_POST['slip_id']) : 0;
    $draw_number = isset($_POST['draw_number']) ? intval($_POST['draw_number']) : 0;

    if ($slip_id <= 0) {
        send_response(false, 'Invalid slip ID');
    }

    if ($draw_number <= 0) {
        send_response(false, 'Invalid draw number');
    }

    log_message("Reprinting slip ID: $slip_id for draw number: $draw_number");

    try {
        log_message("Starting reprint process for slip ID: $slip_id, draw number: $draw_number", 'INFO');

        // Start a transaction
        if (!$conn->begin_transaction()) {
            throw new Exception("Failed to start transaction: " . $conn->error);
        }

        log_message("Transaction started successfully", 'DEBUG');

        // Get the original slip information
        $stmt = $conn->prepare("
            SELECT * FROM betting_slips WHERE slip_id = ?
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
            throw new Exception("Original betting slip not found");
        }

        $original_slip = $result->fetch_assoc();

        // Check if the slip is already paid out
        if ($original_slip['is_paid']) {
            throw new Exception("This betting slip has already been paid out and cannot be reprinted");
        }

        // Check if the slip is cancelled
        if ($original_slip['is_cancelled']) {
            throw new Exception("This betting slip has been cancelled and cannot be reprinted");
        }

        // Get the user's current cash balance
        $stmt = $conn->prepare("
            SELECT cash_balance FROM users WHERE user_id = ? FOR UPDATE
        ");

        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $conn->error);
        }

        $stmt->bind_param('i', $original_slip['user_id']);

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception("User not found");
        }

        $user = $result->fetch_assoc();
        $currentBalance = floatval($user['cash_balance']);
        $betAmount = floatval($original_slip['total_stake']);

        // Check if the user has enough balance
        if ($currentBalance < $betAmount) {
            throw new Exception("Insufficient funds to reprint this betting slip");
        }

        // Calculate the new balance
        $newBalance = $currentBalance - $betAmount;

        // Generate a new slip number
        $new_slip_number = generate_slip_number();

        // Record the transaction
        $stmt = $conn->prepare("
            INSERT INTO transactions
            (user_id, amount, balance_after, transaction_type, reference_id, description)
            VALUES (?, ?, ?, 'bet', ?, ?)
        ");

        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $conn->error);
        }

        $negativeAmount = -$betAmount; // Make the amount negative for deductions
        $description = "Reprinted betting slip #" . $new_slip_number . " (from #" . $original_slip['slip_number'] . ")";

        $stmt->bind_param(
            'idsss',
            $original_slip['user_id'],
            $negativeAmount,
            $newBalance,
            $new_slip_number,
            $description
        );

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $transaction_id = $conn->insert_id;

        // Update the user's cash balance
        $stmt = $conn->prepare("
            UPDATE users SET cash_balance = ? WHERE user_id = ?
        ");

        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $conn->error);
        }

        $stmt->bind_param('di', $newBalance, $original_slip['user_id']);

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        // Insert the new betting slip
        $stmt = $conn->prepare("
            INSERT INTO betting_slips (
                slip_number, user_id, total_stake, potential_payout,
                created_at, is_paid, is_cancelled, draw_number,
                reprinted_from, is_reprint, transaction_id
            ) VALUES (?, ?, ?, ?, NOW(), 0, 0, ?, ?, 1, ?)
        ");

        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $conn->error);
        }

        $stmt->bind_param(
            'siddiii',
            $new_slip_number,
            $original_slip['user_id'],
            $original_slip['total_stake'],
            $original_slip['potential_payout'],
            $draw_number,
            $original_slip['slip_id'],
            $transaction_id
        );

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $new_slip_id = $conn->insert_id;

        // Get the bets for the original slip
        $stmt = $conn->prepare("
            SELECT b.*
            FROM bets b
            INNER JOIN slip_details sd ON b.bet_id = sd.bet_id
            WHERE sd.slip_id = ?
        ");

        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $conn->error);
        }

        $stmt->bind_param('i', $original_slip['slip_id']);

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $result = $stmt->get_result();
        $bets = [];

        while ($bet = $result->fetch_assoc()) {
            $bets[] = $bet;
        }

        // Insert the bets for the new slip
        foreach ($bets as $bet) {
            // Insert the bet
            $stmt = $conn->prepare("
                INSERT INTO bets (
                    user_id, bet_type, bet_description, bet_amount,
                    multiplier, potential_return, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");

            if (!$stmt) {
                throw new Exception("Prepare statement failed: " . $conn->error);
            }

            $stmt->bind_param(
                'issddd',
                $bet['user_id'],
                $bet['bet_type'],
                $bet['bet_description'],
                $bet['bet_amount'],
                $bet['multiplier'],
                $bet['potential_return']
            );

            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $new_bet_id = $conn->insert_id;

            // Insert the slip detail
            $stmt = $conn->prepare("
                INSERT INTO slip_details (slip_id, bet_id)
                VALUES (?, ?)
            ");

            if (!$stmt) {
                throw new Exception("Prepare statement failed: " . $conn->error);
            }

            $stmt->bind_param('ii', $new_slip_id, $new_bet_id);

            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
        }

        // Update the reprint count on the original slip
        $stmt = $conn->prepare("
            UPDATE betting_slips
            SET reprint_count = reprint_count + 1
            WHERE slip_id = ?
        ");

        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $conn->error);
        }

        $stmt->bind_param('i', $original_slip['slip_id']);

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        // Verify that all the necessary data was created
        log_message("Verifying data before committing transaction", 'DEBUG');

        // Check if the new slip exists
        $verifySlipStmt = $conn->prepare("SELECT * FROM betting_slips WHERE slip_id = ?");
        $verifySlipStmt->bind_param('i', $new_slip_id);
        $verifySlipStmt->execute();
        $verifySlipResult = $verifySlipStmt->get_result();

        if ($verifySlipResult->num_rows === 0) {
            throw new Exception("Failed to create new betting slip record");
        }

        // Check if the bets were created
        $verifyBetsStmt = $conn->prepare("
            SELECT COUNT(*) as bet_count
            FROM slip_details
            WHERE slip_id = ?
        ");
        $verifyBetsStmt->bind_param('i', $new_slip_id);
        $verifyBetsStmt->execute();
        $verifyBetsResult = $verifyBetsStmt->get_result();
        $betCount = $verifyBetsResult->fetch_assoc()['bet_count'];

        if ($betCount === 0) {
            throw new Exception("Failed to create bet records for the new slip");
        }

        log_message("Verification successful. New slip and bets created.", 'DEBUG');

        // Commit the transaction
        if (!$conn->commit()) {
            throw new Exception("Failed to commit transaction: " . $conn->error);
        }

        log_message("Transaction committed successfully", 'DEBUG');

        // Log the successful reprint
        log_message("Slip reprinted successfully. Original slip ID: $slip_id, New slip ID: $new_slip_id, New slip number: $new_slip_number, Transaction ID: $transaction_id");

        // Send the response
        send_response(true, 'Slip reprinted successfully', [
            'original_slip_id' => $slip_id,
            'new_slip_id' => $new_slip_id,
            'new_slip_number' => $new_slip_number,
            'draw_number' => $draw_number,
            'transaction_id' => $transaction_id,
            'new_balance' => $newBalance,
            'print_url' => "print_slip.php?slip_id=$new_slip_id"
        ]);

    } catch (Exception $e) {
        // Rollback the transaction on error
        if ($conn->inTransaction()) {
            log_message("Rolling back transaction due to error", 'ERROR');
            $conn->rollback();
        }

        log_message("Error reprinting slip: " . $e->getMessage(), 'ERROR');
        log_message("Stack trace: " . $e->getTraceAsString(), 'ERROR');
        send_response(false, 'Error reprinting slip: ' . $e->getMessage());
    }
}

/**
 * Get the next draw number
 */
function get_next_draw_number() {
    global $conn;

    try {
        // Try to get the next draw number from the roulette_state table
        $result = $conn->query("SELECT next_draw FROM roulette_state WHERE id = 1");

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $next_draw = $row['next_draw'];

            // Extract the number from the string (e.g., "#123" -> 123)
            if (preg_match('/#(\d+)/', $next_draw, $matches)) {
                return intval($matches[1]);
            }
        }

        // If that fails, try to get it from the detailed_draw_results table
        $result = $conn->query("
            SELECT MAX(draw_number) + 1 as next_draw
            FROM detailed_draw_results
        ");

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if ($row['next_draw']) {
                return intval($row['next_draw']);
            }
        }

        // If all else fails, return 1
        return 1;

    } catch (Exception $e) {
        log_message("Error getting next draw number: " . $e->getMessage(), 'ERROR');
        return 1;
    }
}

/**
 * Generate a unique slip number
 */
function generate_slip_number() {
    // Generate a random 8-digit number
    $slip_number = mt_rand(10000000, 99999999);

    log_message("Generated slip number: $slip_number", 'DEBUG');

    // Check if it already exists
    global $conn;
    $stmt = $conn->prepare("SELECT slip_id FROM betting_slips WHERE slip_number = ?");

    if (!$stmt) {
        log_message("Error preparing statement: " . $conn->error, 'ERROR');
        // Generate a different number if there was an error
        return mt_rand(10000000, 99999999);
    }

    $stmt->bind_param('s', $slip_number);

    if (!$stmt->execute()) {
        log_message("Error executing statement: " . $stmt->error, 'ERROR');
        // Generate a different number if there was an error
        return mt_rand(10000000, 99999999);
    }

    $result = $stmt->get_result();

    // If it exists, generate a new one
    if ($result->num_rows > 0) {
        log_message("Slip number $slip_number already exists, generating a new one", 'DEBUG');
        return generate_slip_number();
    }

    log_message("Using slip number: $slip_number", 'DEBUG');
    return $slip_number;
}
