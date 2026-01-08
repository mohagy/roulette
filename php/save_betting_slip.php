<?php
// Set response header to JSON
header('Content-Type: application/json');

// Start session to get user information
session_start();

// Get the database configuration details from db_connect.php which is working
require_once 'db_connect.php';

// Check if user is logged in - commented out for now to allow testing
// if (!isset($_SESSION['user_id'])) {
//     sendResponse('error', 'User not authenticated. Please log in.');
//     exit;
// }

// Log file path
$logFile = '../logs/betting_slip.log';

/**
 * Log a message to the log file
 */
function logMessage($message, $level = 'INFO') {
    global $logFile;

    // Create logs directory if it doesn't exist
    $logsDir = dirname($logFile);
    if (!is_dir($logsDir)) {
        mkdir($logsDir, 0777, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;

    // Append to log file
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

/**
 * Return a JSON response
 */
function sendResponse($status, $message, $data = null) {
    $response = [
        'status' => $status,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response);
    exit;
}

// Get the raw POST data
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

if (!$data) {
    logMessage("Invalid JSON data received: $rawData", 'ERROR');
    sendResponse('error', 'Invalid JSON data received');
}

// Validate required fields
if (!isset($data['slip_number']) || empty($data['slip_number'])) {
    logMessage("Missing slip number", 'ERROR');
    sendResponse('error', 'Missing slip number');
}

if (!isset($data['bets']) || !is_array($data['bets']) || empty($data['bets'])) {
    logMessage("Missing or invalid bets data", 'ERROR');
    sendResponse('error', 'Missing or invalid bets data');
}

if (!isset($data['total_stake']) || !is_numeric($data['total_stake'])) {
    logMessage("Missing or invalid total stake", 'ERROR');
    sendResponse('error', 'Missing or invalid total stake');
}

if (!isset($data['potential_return']) || !is_numeric($data['potential_return'])) {
    logMessage("Missing or invalid potential return", 'ERROR');
    sendResponse('error', 'Missing or invalid potential return');
}

// Get the logged-in user's ID from the session or use default user ID 1
$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;

// Make sure we have a valid user_id
if (!$userId || $userId <= 0) {
    $userId = 1; // Default to user_id 1 if not set
    logMessage("Warning: Using default user_id 1 because session user_id is invalid", 'WARNING');
}
$drawNumber = isset($data['draw_number']) ? intval($data['draw_number']) : getCurrentDrawNumber($conn);

logMessage("Using user ID: $userId for betting slip", 'INFO');

try {
    // Note: We're now using the $conn connection from db_connect.php
    // Start a transaction
    $conn->begin_transaction();

    // Insert the betting slip
    $stmt = $conn->prepare("INSERT INTO betting_slips
                         (slip_number, user_id, draw_number,
                          total_stake, potential_payout, transaction_id)
                         VALUES (?, ?, ?, ?, ?, NULL)");

    if (!$stmt) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }

    $stmt->bind_param('siiss',
        $data['slip_number'],
        $userId, // Use the user ID from the session instead of player ID
        $drawNumber,
        $data['total_stake'],
        $data['potential_return']
    );

    if (!$stmt->execute()) {
        throw new Exception("Failed to insert betting slip: " . $stmt->error);
    }

    $slipId = $conn->insert_id;
    logMessage("Created betting slip #$slipId with slip number {$data['slip_number']}", 'INFO');

    // Insert each bet
    foreach ($data['bets'] as $bet) {
        if (!isset($bet['type']) || !isset($bet['description']) || !isset($bet['amount'])) {
            logMessage("Invalid bet data: " . json_encode($bet), 'WARNING');
            continue;
        }

        $betType = $bet['type'];
        $betDescription = $bet['description'];
        $betAmount = floatval($bet['amount']);
        $multiplier = isset($bet['multiplier']) ? floatval($bet['multiplier']) : 1;
        $potentialReturn = isset($bet['potentialReturn']) ? floatval($bet['potentialReturn']) : ($betAmount * $multiplier);

        // Insert bet with the user's ID
        $stmt = $conn->prepare("INSERT INTO bets
                             (bet_type, bet_description, bet_amount, multiplier, potential_return, user_id)
                             VALUES (?, ?, ?, ?, ?, ?)");

        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $conn->error);
        }

        $stmt->bind_param('ssdddi',
            $betType,
            $betDescription,
            $betAmount,
            $multiplier,
            $potentialReturn,
            $userId
        );

        if (!$stmt->execute()) {
            throw new Exception("Failed to insert bet: " . $stmt->error);
        }

        $betId = $conn->insert_id;
        logMessage("Created bet #$betId of type $betType with amount $betAmount", 'INFO');

        // Insert slip_details (junction table)
        $stmt = $conn->prepare("INSERT INTO slip_details (slip_id, bet_id) VALUES (?, ?)");

        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $conn->error);
        }

        $stmt->bind_param('ii', $slipId, $betId);

        if (!$stmt->execute()) {
            throw new Exception("Failed to insert slip details: " . $stmt->error);
        }
    }

    $stmt->close();

    // Get the draw details if not already set
    if (!isset($drawNumber) || $drawNumber <= 0) {
        $drawNumber = getCurrentDrawNumber($conn);
    }

    // Set the bet amount
    $betAmount = $data['total_stake'];

    // Log which user is making the transaction
    if (isset($_SESSION['username'])) {
        logMessage("User ID $userId ({$_SESSION['username']}) is processing betting slip {$data['slip_number']}", 'INFO');
    } else {
        logMessage("User ID $userId is processing betting slip {$data['slip_number']}", 'INFO');
    }

    // Update the user's cash balance
    $stmt = $conn->prepare("
        UPDATE users
        SET cash_balance = cash_balance - ?, updated_at = NOW()
        WHERE user_id = ?
    ");
    $stmt->bind_param("di", $betAmount, $userId);

    // Execute and check for errors
    if (!$stmt->execute()) {
        throw new Exception("Failed to update cash balance: " . $stmt->error);
    }

    // Log the update for debugging
    error_log("Cash balance updated for user $userId: deducted $betAmount for slip " . $data['slip_number']);

    // Double-check that the update was successful
    $stmt = $conn->prepare("SELECT cash_balance FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $updatedBalance = $row['cash_balance'];
    error_log("New cash balance for user $userId: $updatedBalance");

    // Record the transaction
    $stmt = $conn->prepare("
        INSERT INTO transactions
        (user_id, amount, balance_after, transaction_type, reference_id, description)
        VALUES (?, ?, ?, 'bet', CAST(? AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci, ?)
    ");
    $negativeAmount = -$betAmount; // Make the amount negative for deductions
    $referenceId = $data['slip_number']; // Use just the slip number as reference
    $description = "Betting slip sold #" . $data['slip_number'];
    $stmt->bind_param("idsss", $userId, $negativeAmount, $updatedBalance, $referenceId, $description);

    // Execute and check for errors
    if (!$stmt->execute()) {
        throw new Exception("Failed to record transaction: " . $stmt->error);
    }

    $transactionId = $conn->insert_id;

    // Log the transaction for debugging
    error_log("Transaction #$transactionId recorded for user $userId: amount -$betAmount, reference $referenceId, balance after: $updatedBalance");

    // Update the betting slip with the transaction ID to ensure proper linking
    $updateSlipStmt = $conn->prepare("
        UPDATE betting_slips
        SET transaction_id = ?
        WHERE slip_id = ?
    ");
    $updateSlipStmt->bind_param("ii", $transactionId, $slipId);

    if (!$updateSlipStmt->execute()) {
        logMessage("Warning: Failed to update betting slip with transaction ID: " . $updateSlipStmt->error, 'WARNING');
    } else {
        logMessage("Betting slip #$slipId updated with transaction ID #$transactionId", 'INFO');
    }

    $updateSlipStmt->close();

    // Record commission (4% of bet amount)
    // First get the commission rate from settings
    $commissionRateStmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'commission_rate'");
    $commissionRateStmt->execute();
    $commissionRateResult = $commissionRateStmt->get_result();
    $commissionRate = 4; // Default to 4% if not found

    if ($commissionRateResult->num_rows > 0) {
        $commissionRateRow = $commissionRateResult->fetch_assoc();
        $commissionRate = floatval($commissionRateRow['setting_value']);
    }
    $commissionRateStmt->close();

    // Calculate commission amount
    $commissionAmount = $betAmount * ($commissionRate / 100);

    // Insert commission record
    $commissionStmt = $conn->prepare("
        INSERT INTO commission (
            user_id,
            bet_amount,
            commission_amount,
            slip_number,
            transaction_id,
            date_created
        ) VALUES (?, ?, ?, ?, ?, CURDATE())
    ");

    $commissionStmt->bind_param("idssi", $userId, $betAmount, $commissionAmount, $data['slip_number'], $transactionId);

    if (!$commissionStmt->execute()) {
        logMessage("Warning: Failed to record commission: " . $commissionStmt->error, 'WARNING');
    } else {
        logMessage("Commission recorded: $commissionAmount (${commissionRate}% of $betAmount) for slip #{$data['slip_number']}", 'INFO');
    }

    $commissionStmt->close();

    // Get the updated balance
    $stmt = $conn->prepare("SELECT cash_balance FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $newBalance = $row['cash_balance'];

    // Commit the transaction
    $conn->commit();

    // Send success response with the updated balance
    sendResponse('success', 'Betting slip saved successfully', [
        'slip_id' => $slipId,
        'slip_number' => $data['slip_number'],
        'bets_saved' => count($data['bets']),
        'total_stake' => $data['total_stake'],
        'total_potential_payout' => $data['potential_return'],
        'new_balance' => $newBalance
    ]);

} catch (Exception $e) {
    // Roll back the transaction if there was an error
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
    }

    logMessage("Error saving betting slip: " . $e->getMessage(), 'ERROR');
    sendResponse('error', 'Error saving betting slip: ' . $e->getMessage());
}

/**
 * Get the current draw number
 */
function getCurrentDrawNumber($conn) {
    try {
        // Try to get draw information from roulette_state table
        $stmt = $conn->prepare("SELECT id FROM roulette_state LIMIT 1");

        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                // If we have a roulette_state record, use a default draw number
                return 1;
            }
        }

        // If that fails, try to get the max draw_number from detailed_draw_results
        $stmt = $conn->prepare("SELECT MAX(draw_number) as max_draw FROM detailed_draw_results");

        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                if ($row['max_draw']) {
                    return $row['max_draw'] + 1;
                }
            }
        }
    } catch (Exception $e) {
        logMessage("Error getting current draw number: " . $e->getMessage(), 'ERROR');
    }

    // Default to draw number 1 if we couldn't get it from the database
    return 1;
}
?>