<?php
// Set response header to JSON
header('Content-Type: application/json');

// Include database connection
require_once 'db_connect.php';

// Start session to get user information
session_start();

// Get the HTTP method and action from the request
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Also check POST for action if not found in GET
if (empty($action) && isset($_POST['action'])) {
    $action = $_POST['action'];
}

// Default response
$response = array(
    'status' => 'error',
    'message' => 'Invalid request'
);

// Process based on request method and action
if ($method === 'GET') {
    if ($action === 'verify_slip') {
        if (isset($_GET['slip_number'])) {
            $slip_number = $_GET['slip_number'];

            $stmt = $conn->prepare("
                SELECT bs.*, u.username
                FROM betting_slips bs
                JOIN users u ON bs.user_id = u.user_id
                WHERE bs.slip_number = ?
            ");
            $stmt->bind_param("s", $slip_number);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $slip = $result->fetch_assoc();

                // Get the latest game result
                $latestGameStmt = $conn->prepare("
                    SELECT * FROM game_history
                    ORDER BY played_at DESC
                    LIMIT 1
                ");
                $latestGameStmt->execute();
                $latestGame = $latestGameStmt->get_result()->fetch_assoc();
                $latestGameStmt->close();

                // Get the bet details
                $betDetailsStmt = $conn->prepare("
                    SELECT b.*
                    FROM slip_details sd
                    JOIN bets b ON sd.bet_id = b.bet_id
                    WHERE sd.slip_id = ?
                ");
                $betDetailsStmt->bind_param("i", $slip['slip_id']);
                $betDetailsStmt->execute();
                $betResult = $betDetailsStmt->get_result();

                $bets = array();
                while ($bet = $betResult->fetch_assoc()) {
                    $bets[] = $bet;
                }
                $betDetailsStmt->close();

                $response = array(
                    'status' => 'success',
                    'slip' => $slip,
                    'bets' => $bets,
                    'latest_game' => $latestGame
                );
            } else {
                $response = array(
                    'status' => 'error',
                    'message' => 'Betting slip not found'
                );
            }

            $stmt->close();
        } else {
            $response = array(
                'status' => 'error',
                'message' => 'Missing slip number'
            );
        }
    }
} elseif ($method === 'POST') {
    // Handle save_slip action
    if ($action === 'save_slip') {
        try {
            // For testing/debugging
            error_log("Saving slip: " . print_r($_POST, true));

            // Get the data from the POST request
            $barcode = isset($_POST['barcode']) ? $_POST['barcode'] : '';
            $bets_json = isset($_POST['bets']) ? $_POST['bets'] : '';
            $total_stakes = isset($_POST['total_stakes']) ? floatval($_POST['total_stakes']) : 0;
            $potential_return = isset($_POST['potential_return']) ? floatval($_POST['potential_return']) : 0;
            $date = isset($_POST['date']) ? $_POST['date'] : date('Y-m-d H:i:s');
            $draw_number = isset($_POST['draw_number']) ? intval($_POST['draw_number']) : 0;

            // Log the received draw number for debugging
            error_log("Received draw_number from POST: " . $draw_number);

            // Validate inputs
            if (empty($barcode)) {
                throw new Exception("Missing barcode");
            }

            if (empty($bets_json)) {
                throw new Exception("Missing bets data");
            }

            if ($total_stakes <= 0) {
                throw new Exception("Invalid total stakes amount");
            }

            if ($draw_number <= 0) {
                // Get next draw number if not provided (for new betting slips)
                // First try roulette_analytics table and add 1 to get next draw
                $drawStmt = $conn->prepare("SELECT current_draw_number FROM roulette_analytics WHERE id = 1");
                $drawStmt->execute();
                $drawResult = $drawStmt->get_result();
                if ($drawResult->num_rows > 0) {
                    $drawRow = $drawResult->fetch_assoc();
                    $draw_number = $drawRow['current_draw_number'] + 1; // ✅ FIXED: Add 1 for next draw
                } else {
                    // Fallback: Try detailed_draw_results table
                    $fallbackStmt = $conn->prepare("SELECT MAX(draw_number) as max_draw FROM detailed_draw_results");
                    $fallbackStmt->execute();
                    $fallbackResult = $fallbackStmt->get_result();
                    if ($fallbackResult->num_rows > 0) {
                        $fallbackRow = $fallbackResult->fetch_assoc();
                        $draw_number = ($fallbackRow['max_draw'] ?? 0) + 1; // Next draw after last completed
                    } else {
                        $draw_number = 1; // Final fallback
                    }
                    $fallbackStmt->close();
                }
                $drawStmt->close();

                // Log the fallback draw number assignment for debugging
                error_log("Fallback draw number assignment: Using draw #$draw_number for betting slip $barcode");
            }

            // Log the final draw number that will be saved to database
            error_log("Final draw_number to be saved: $draw_number for betting slip $barcode");

            // Decode the bets JSON
            $bets = json_decode($bets_json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid bets data format: " . json_last_error_msg());
            }

            // Validate bets structure
            if (empty($bets) || !is_array($bets)) {
                throw new Exception("No valid bets found");
            }

            foreach ($bets as $index => $bet) {
                if (!isset($bet['type'])) {
                    throw new Exception("Missing bet type for bet #" . ($index + 1));
                }
                if (!isset($bet['description'])) {
                    throw new Exception("Missing bet description for bet #" . ($index + 1));
                }
                if (!isset($bet['amount']) || floatval($bet['amount']) <= 0) {
                    throw new Exception("Invalid bet amount for bet #" . ($index + 1));
                }
            }

            // Start transaction
            $conn->begin_transaction();

            // Get the current player ID (use 1 as default for guest if no login system)
            $player_id = 1; // Default to guest player

            // Get the logged-in user ID for the transaction
            $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;

            if (isset($_SESSION['user_id'])) {
                error_log("User ID {$_SESSION['user_id']} ({$_SESSION['username']}) is processing betting slip {$barcode}");
            } else {
                error_log("No user logged in, using default user ID 1 for betting slip {$barcode}");
            }

            // Check if slip already exists
            $checkStmt = $conn->prepare("SELECT slip_id FROM betting_slips WHERE slip_number = ?");
            $checkStmt->bind_param("s", $barcode);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
                $checkStmt->close();
                throw new Exception("A betting slip with this barcode already exists");
            }
            $checkStmt->close();

            // Insert betting slip
            $slipStmt = $conn->prepare("
                INSERT INTO betting_slips (
                    slip_number, user_id, total_stake, potential_payout,
                    created_at, is_paid, is_cancelled, draw_number, transaction_id
                ) VALUES (?, ?, ?, ?, ?, 0, 0, ?, NULL)
            ");
            $created_at = date('Y-m-d H:i:s');
            $slipStmt->bind_param("sidssi", $barcode, $userId, $total_stakes, $potential_return, $created_at, $draw_number);

            if (!$slipStmt->execute()) {
                throw new Exception("Failed to insert betting slip: " . $slipStmt->error);
            }

            $slip_id = $conn->insert_id;
            $slipStmt->close();

            // Insert each bet
            $betStmt = $conn->prepare("
                INSERT INTO bets (
                    user_id, bet_type, bet_description, bet_amount, multiplier, potential_return
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");

            $detailStmt = $conn->prepare("
                INSERT INTO slip_details (slip_id, bet_id) VALUES (?, ?)
            ");

            foreach ($bets as $bet) {
                $bet_type = $bet['type'];
                $bet_description = $bet['description'];
                $bet_amount = $bet['amount'];
                $multiplier = isset($bet['multiplier']) ? $bet['multiplier'] : 0;
                $bet_potential_return = isset($bet['potentialReturn']) ? $bet['potentialReturn'] : ($bet_amount * $multiplier);

                $betStmt->bind_param("issddd", $userId, $bet_type, $bet_description, $bet_amount, $multiplier, $bet_potential_return);

                if (!$betStmt->execute()) {
                    throw new Exception("Failed to insert bet: " . $betStmt->error);
                }

                $bet_id = $conn->insert_id;

                $detailStmt->bind_param("ii", $slip_id, $bet_id);

                if (!$detailStmt->execute()) {
                    throw new Exception("Failed to insert slip detail: " . $detailStmt->error);
                }
            }

            $betStmt->close();
            $detailStmt->close();

            // Update the user's cash balance if logged in
            if (isset($_SESSION['user_id'])) {
                // Get current user balance
                $balanceStmt = $conn->prepare("SELECT cash_balance FROM users WHERE user_id = ?");
                $balanceStmt->bind_param("i", $userId);
                $balanceStmt->execute();
                $balanceResult = $balanceStmt->get_result();

                if ($balanceResult->num_rows > 0) {
                    $balanceRow = $balanceResult->fetch_assoc();
                    $currentBalance = $balanceRow['cash_balance'];
                    $newBalance = $currentBalance - $total_stakes; // Use the total_stakes variable

                    // Update user balance
                    $updateBalanceStmt = $conn->prepare("
                        UPDATE users
                        SET cash_balance = ?, updated_at = NOW()
                        WHERE user_id = ?
                    ");
                    $updateBalanceStmt->bind_param("di", $newBalance, $userId);

                    if (!$updateBalanceStmt->execute()) {
                        throw new Exception("Failed to update cash balance: " . $updateBalanceStmt->error);
                    }

                    $updateBalanceStmt->close();

                    // Record the transaction
                    $transactionStmt = $conn->prepare("
                        INSERT INTO transactions
                        (user_id, amount, balance_after, transaction_type, reference_id, description)
                        VALUES (?, ?, ?, 'bet', ?, ?)
                    ");
                    $negativeAmount = -$total_stakes; // Make the amount negative for deductions
                    $description = "Betting slip sold #" . $barcode;

                    $transactionStmt->bind_param("idsss", $userId, $negativeAmount, $newBalance, $barcode, $description);

                    if (!$transactionStmt->execute()) {
                        throw new Exception("Failed to record transaction: " . $transactionStmt->error);
                    }

                    $transactionId = $conn->insert_id;

                    // Update the betting slip with the transaction ID
                    $updateSlipStmt = $conn->prepare("
                        UPDATE betting_slips
                        SET transaction_id = ?
                        WHERE slip_id = ?
                    ");
                    $updateSlipStmt->bind_param("ii", $transactionId, $slip_id);

                    if (!$updateSlipStmt->execute()) {
                        error_log("Warning: Failed to update betting slip with transaction ID: " . $updateSlipStmt->error);
                    } else {
                        error_log("Betting slip #$slip_id updated with transaction ID #$transactionId");
                    }

                    $updateSlipStmt->close();
                    $transactionStmt->close();

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
                    $commissionAmount = $total_stakes * ($commissionRate / 100);

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

                    $commissionStmt->bind_param("idssi", $userId, $total_stakes, $commissionAmount, $barcode, $transactionId);

                    if (!$commissionStmt->execute()) {
                        error_log("Warning: Failed to record commission: " . $commissionStmt->error);
                    } else {
                        error_log("Commission recorded: $commissionAmount (${commissionRate}% of $total_stakes) for slip #$barcode");
                    }

                    $commissionStmt->close();
                }

                $balanceStmt->close();
            }

            // Commit the transaction
            $conn->commit();

            // Save game state when slip is saved
            if (isset($_POST['winning_number']) && isset($_POST['winning_color']) && isset($_POST['draw_id'])) {
                $winning_number = $_POST['winning_number'];
                $winning_color = $_POST['winning_color'];
                $draw_id = $_POST['draw_id'];

                $gameStmt = $conn->prepare("
                    INSERT INTO game_history (
                        winning_number, winning_color, draw_id
                    ) VALUES (?, ?, ?)
                ");

                $gameStmt->bind_param("iss", $winning_number, $winning_color, $draw_id);
                $gameStmt->execute();
                $gameStmt->close();
            }

            $response = array(
                'status' => 'success',
                'message' => 'Betting slip saved successfully',
                'slip_id' => $slip_id,
                'barcode' => $barcode,
                'draw_number' => $draw_number
            );

        } catch (Exception $e) {
            // Rollback the transaction on error
            if (isset($conn) && $conn->ping()) {
                $conn->rollback();
            }

            $response = array(
                'status' => 'error',
                'message' => 'Failed to save betting slip: ' . $e->getMessage()
            );

            // Log the error
            error_log("Error saving slip: " . $e->getMessage());
        }
    } elseif ($action === 'cancel_slip') {
        // Slip cancellation logic here
        try {
            if (!isset($_POST['slip_number'])) {
                throw new Exception("Missing slip number");
            }

            $slip_number = $_POST['slip_number'];

            // Start transaction
            $conn->begin_transaction();

            // Get the slip details
            $slipStmt = $conn->prepare("
                SELECT * FROM betting_slips WHERE slip_number = ?
            ");
            $slipStmt->bind_param("s", $slip_number);
            $slipStmt->execute();
            $result = $slipStmt->get_result();

            if ($result->num_rows === 0) {
                $slipStmt->close();
                throw new Exception("Betting slip not found");
            }

            $slip = $result->fetch_assoc();
            $slipStmt->close();

            // Update the slip to mark as cancelled
            $updateStmt = $conn->prepare("
                UPDATE betting_slips SET is_cancelled = 1 WHERE slip_id = ?
            ");
            $updateStmt->bind_param("i", $slip['slip_id']);

            if (!$updateStmt->execute()) {
                throw new Exception("Failed to cancel betting slip: " . $updateStmt->error);
            }

            $updateStmt->close();

            // Commit the transaction
            $conn->commit();

            $response = array(
                'status' => 'success',
                'message' => 'Betting slip cancelled successfully',
                'slip_id' => $slip['slip_id'],
                'barcode' => $slip_number
            );

        } catch (Exception $e) {
            // Rollback the transaction on error
            if (isset($conn) && $conn->ping()) {
                $conn->rollback();
            }

            $response = array(
                'status' => 'error',
                'message' => 'Failed to cancel betting slip: ' . $e->getMessage()
            );

            // Log the error
            error_log("Error cancelling slip: " . $e->getMessage());
        }
    } elseif ($action === 'collect_winnings') {
        if (isset($_POST['slip_number'])) {
            $slip_number = $_POST['slip_number'];

            // Start transaction
            $conn->begin_transaction();

            try {
                // Get the slip details
                $stmt = $conn->prepare("
                    SELECT * FROM betting_slips
                    WHERE slip_number = ? AND is_cancelled = 0 AND is_paid = 0
                ");
                $stmt->bind_param("s", $slip_number);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 0) {
                    throw new Exception("Slip not found or already processed");
                }

                $slip = $result->fetch_assoc();
                $user_id = $slip['user_id'];
                $potential_payout = $slip['potential_payout'];

                // In a real application, you would verify if the slip is a winner
                // For demo purposes, we'll assume all verified slips are winners

                // Mark slip as paid
                $paidStmt = $conn->prepare("
                    UPDATE betting_slips
                    SET is_paid = 1
                    WHERE slip_number = ?
                ");
                $paidStmt->bind_param("s", $slip_number);
                $paidStmt->execute();
                $paidStmt->close();

                // Add winnings to user's balance
                $payoutStmt = $conn->prepare("
                    UPDATE users
                    SET cash_balance = cash_balance + ?
                    WHERE user_id = ?
                ");
                $payoutStmt->bind_param("di", $potential_payout, $user_id);
                $payoutStmt->execute();
                $payoutStmt->close();

                // Get updated balance
                $balanceStmt = $conn->prepare("
                    SELECT cash_balance FROM users WHERE user_id = ?
                ");
                $balanceStmt->bind_param("i", $user_id);
                $balanceStmt->execute();
                $balanceResult = $balanceStmt->get_result();
                $balanceData = $balanceResult->fetch_assoc();
                $balanceStmt->close();

                // Commit the transaction
                $conn->commit();

                $response = array(
                    'status' => 'success',
                    'message' => 'Winnings collected successfully',
                    'payout_amount' => $potential_payout,
                    'new_balance' => $balanceData['cash_balance']
                );

            } catch (Exception $e) {
                // Rollback the transaction on error
                $conn->rollback();

                $response = array(
                    'status' => 'error',
                    'message' => $e->getMessage()
                );
            }
        } else {
            $response = array(
                'status' => 'error',
                'message' => 'Missing slip number'
            );
        }
    }
} else {
    $response = array(
        'status' => 'error',
        'message' => 'Unsupported HTTP method'
    );
}

// Output the response
echo json_encode($response);
?>