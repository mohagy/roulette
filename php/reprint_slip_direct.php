<?php
/**
 * Direct Reprint Slip API
 *
 * This file handles direct reprint of betting slips.
 */

// Start output buffering to prevent any unexpected output
ob_start();

// Set error reporting to show all errors
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable display_errors to prevent HTML output

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
    $log_file = '../logs/reprint_direct.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$level] $message\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);

    // Also log to PHP error log for easier debugging
    error_log("REPRINT_DIRECT: [$level] $message");
}

/**
 * Generate printable HTML content for a betting slip
 *
 * @param int $slip_id The ID of the betting slip
 * @return string HTML content for printing
 */
function generatePrintableSlipContent($slip_id) {
    global $conn;

    try {
        // Get the slip information
        $stmt = $conn->prepare("
            SELECT bs.*, u.username
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
            throw new Exception("Betting slip not found");
        }

        $slip = $result->fetch_assoc();

        // Get the bets for the slip
        $stmt = $conn->prepare("
            SELECT b.*
            FROM bets b
            JOIN slip_details sd ON b.bet_id = sd.bet_id
            WHERE sd.slip_id = ?
        ");

        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $conn->error);
        }

        $stmt->bind_param('i', $slip_id);

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $result = $stmt->get_result();
        $bets = [];

        while ($bet = $result->fetch_assoc()) {
            $bets[] = $bet;
        }

        // Format date
        $slipDate = new DateTime($slip['created_at']);
        $formattedDate = $slipDate->format('m/d/Y g:i:s A');

        // Generate the HTML content
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <title>Betting Slip #' . $slip['slip_number'] . '</title>
            <style>
                body {
                    font-family: "Courier New", monospace;
                    width: 300px;
                    margin: 0 auto;
                    padding: 10px;
                }
                .header {
                    text-align: center;
                    margin-bottom: 15px;
                }
                .header h1 {
                    font-size: 18px;
                    margin: 0;
                }
                .header p {
                    margin: 5px 0;
                    font-size: 12px;
                }
                .bet-item {
                    margin-bottom: 10px;
                    border-bottom: 1px dashed #ccc;
                    padding-bottom: 5px;
                }
                .bet-type {
                    font-weight: bold;
                }
                .bet-details {
                    display: flex;
                    justify-content: space-between;
                    font-size: 12px;
                }
                .totals {
                    margin-top: 15px;
                    font-weight: bold;
                    border-top: 1px solid #000;
                    padding-top: 5px;
                }
                .footer {
                    margin-top: 15px;
                    font-size: 12px;
                    text-align: center;
                }
                .disclaimer {
                    font-size: 10px;
                    font-style: italic;
                    margin-top: 5px;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>ROULETTE BETTING SLIP</h1>
                <p>' . $formattedDate . '</p>
                <p>Player ID: ' . htmlspecialchars($slip['username'] ?: 'GUEST') . '</p>';

        // Add original draw number if this is a reprint
        if ($slip['is_reprint'] && $slip['reprinted_from']) {
            $stmt = $conn->prepare("
                SELECT draw_number FROM betting_slips WHERE slip_id = ?
            ");
            $stmt->bind_param('i', $slip['reprinted_from']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $originalSlip = $result->fetch_assoc();
                $html .= '<p>Original Draw #: ' . $originalSlip['draw_number'] . '</p>';
            }
        }

        $html .= '
                <p>Draw #: ' . $slip['draw_number'] . '</p>
            </div>

            <div class="bets-list">';

        // Add each bet to the slip
        foreach ($bets as $index => $bet) {
            $html .= '
                <div class="bet-item">
                    <div class="bet-type">' . ($index + 1) . '. ' . strtoupper($bet['bet_type']) . ': ' . htmlspecialchars($bet['bet_description']) . '</div>
                    <div class="bet-details">
                        <div>Stake: $' . number_format((float)$bet['bet_amount'], 2) . '</div>
                        <div>Pays: ' . number_format((float)$bet['multiplier'], 0) . ':1</div>
                    </div>
                    <div class="bet-details">
                        <div></div>
                        <div>Return: $' . number_format((float)$bet['potential_return'], 2) . '</div>
                    </div>
                </div>';
        }

        // Add total stake and potential return
        $html .= '
            </div>

            <div class="totals">
                <div class="total-stake">Total Staked: $' . number_format((float)$slip['total_stake'], 2) . '</div>
                <div class="potential-return">Potential Payout: $' . number_format((float)$slip['potential_payout'], 2) . '</div>
            </div>

            <div class="footer">
                <p>Draw Number: ' . $slip['draw_number'] . '</p>
                <p>Slip Number: ' . $slip['slip_number'] . '</p>
                <p>Good luck!</p>
                <p class="disclaimer">This betting slip is for entertainment purposes only.</p>
                <p class="disclaimer">Not redeemable for real money.</p>
            </div>
        </body>
        </html>';

        return $html;

    } catch (Exception $e) {
        log_message("Error generating printable slip content: " . $e->getMessage(), 'ERROR');
        return '<html><body><h1>Error</h1><p>' . $e->getMessage() . '</p></body></html>';
    }
}

// Wrap everything in a try-catch to handle any unexpected errors
try {
    // Get the slip ID and draw number from the request
    $slip_id = isset($_POST['slip_id']) ? intval($_POST['slip_id']) : 0;
    $draw_number = isset($_POST['draw_number']) ? intval($_POST['draw_number']) : 0;

    log_message("Received reprint request for slip ID: $slip_id, draw number: $draw_number");
    log_message("POST data: " . json_encode($_POST));

    if ($slip_id <= 0) {
        // Make sure there's no output before our JSON
        if (ob_get_length()) ob_clean();

        echo json_encode([
            'success' => false,
            'message' => 'Invalid slip ID'
        ]);
        exit;
    }

    if ($draw_number <= 0) {
        // Make sure there's no output before our JSON
        if (ob_get_length()) ob_clean();

        echo json_encode([
            'success' => false,
            'message' => 'Invalid draw number'
        ]);
        exit;
    }
} catch (Exception $e) {
    // Make sure there's no output before our JSON
    if (ob_get_length()) ob_clean();

    log_message("Unexpected error in initial validation: " . $e->getMessage(), 'ERROR');

    echo json_encode([
        'success' => false,
        'message' => 'Unexpected error: ' . $e->getMessage()
    ]);
    exit;
}

try {
    // Start a transaction
    $conn->begin_transaction();
    log_message("Transaction started");

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
    log_message("Original slip found: " . json_encode($original_slip));

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

    log_message("User current balance: $currentBalance, Bet amount: $betAmount");

    // Check if the user has enough balance
    if ($currentBalance < $betAmount) {
        throw new Exception("Insufficient funds to reprint this betting slip");
    }

    // Calculate the new balance
    $newBalance = $currentBalance - $betAmount;

    // Generate a new slip number
    $slip_number = mt_rand(10000000, 99999999);
    log_message("Generated new slip number: $slip_number");

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
    $description = "Reprinted betting slip #" . $slip_number . " (from #" . $original_slip['slip_number'] . ")";

    $stmt->bind_param(
        'idsss',
        $original_slip['user_id'],
        $negativeAmount,
        $newBalance,
        $slip_number,
        $description
    );

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $transaction_id = $conn->insert_id;
    log_message("Transaction created with ID: $transaction_id");

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

    // Check if the update was successful
    if ($stmt->affected_rows <= 0) {
        throw new Exception("Failed to update user's cash balance");
    }

    log_message("User balance updated to: $newBalance");

    // Double-check the balance was updated correctly
    $stmt = $conn->prepare("
        SELECT cash_balance FROM users WHERE user_id = ?
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
        throw new Exception("User not found after balance update");
    }

    $updatedUser = $result->fetch_assoc();
    $updatedBalance = floatval($updatedUser['cash_balance']);

    if (abs($updatedBalance - $newBalance) > 0.01) {
        throw new Exception("Balance verification failed: expected $newBalance, got $updatedBalance");
    }

    log_message("Balance verification successful: $updatedBalance");

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
        $slip_number,
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
    log_message("New slip created with ID: $new_slip_id");

    // Get the bets for the original slip
    $stmt = $conn->prepare("
        SELECT b.*
        FROM bets b
        JOIN slip_details sd ON b.bet_id = sd.bet_id
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

    log_message("Found " . count($bets) . " bets for the original slip");

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

    log_message("Created " . count($bets) . " bets for the new slip");

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

    log_message("Updated reprint count for original slip");

    // Commit the transaction
    $conn->commit();
    log_message("Transaction committed successfully");

    // Get the formatted slip content for direct printing
    $slip_content = generatePrintableSlipContent($new_slip_id);

    // Make sure there's no output before our JSON
    if (ob_get_length()) ob_clean();

    // Send the response
    echo json_encode([
        'success' => true,
        'message' => 'Slip reprinted successfully',
        'original_slip_id' => $slip_id,
        'new_slip_id' => $new_slip_id,
        'new_slip_number' => $slip_number,
        'draw_number' => $draw_number,
        'transaction_id' => $transaction_id,
        'new_balance' => $newBalance,
        'slip_content' => $slip_content,
        'print_url' => "print_slip.php?slip_id=$new_slip_id" // Keep this as a fallback
    ]);

} catch (Exception $e) {
    // Rollback the transaction on error
    try {
        $conn->rollback();
        log_message("Transaction rolled back due to error");
    } catch (Exception $rollbackError) {
        log_message("Error during rollback: " . $rollbackError->getMessage(), 'ERROR');
    }

    log_message("Error reprinting slip: " . $e->getMessage(), 'ERROR');
    log_message("Stack trace: " . $e->getTraceAsString(), 'ERROR');

    // Make sure there's no output before our JSON
    if (ob_get_length()) ob_clean();

    echo json_encode([
        'success' => false,
        'message' => 'Error reprinting slip: ' . $e->getMessage()
    ]);
}
?>
