<?php
// Set response header to JSON
header('Content-Type: application/json');

// Include database connection
require_once 'db_connect.php';

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
if ($method === 'POST') {
    if ($action === 'verify_cashout') {
        try {
            // Get the slip number from the POST request
            $slip_number = isset($_POST['slip_number']) ? $_POST['slip_number'] : '';

            if (empty($slip_number)) {
                throw new Exception("Missing slip number");
            }

            // Get the slip details from the database
            $stmt = $conn->prepare("
                SELECT bs.*, u.username
                FROM betting_slips bs
                JOIN users u ON bs.user_id = u.user_id
                WHERE bs.slip_number = ?
            ");
            $stmt->bind_param("s", $slip_number);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                throw new Exception("Betting slip not found");
            }

            $slip = $result->fetch_assoc();
            $stmt->close();

            // Check if the slip is already paid
            if ($slip['is_paid'] == 1) {
                throw new Exception("This betting slip has already been cashed out");
            }

            // Check if the slip is cancelled
            if ($slip['is_cancelled'] == 1) {
                throw new Exception("This betting slip has been cancelled");
            }

            // Check if the slip is still valid (within one week)
            $slip_date = new DateTime($slip['created_at']);
            $current_date = new DateTime();
            $interval = $slip_date->diff($current_date);
            $days_difference = $interval->days;

            if ($days_difference > 7) {
                throw new Exception("This betting slip has expired. Slips are valid for 7 days after purchase.");
            }

            // Get the draw details
            $draw_number = $slip['draw_number'];

            // STEP 1: Validate draw completion status
            $draw_completion_status = validateDrawCompletion($conn, $draw_number);

            if (!$draw_completion_status['is_completed']) {
                throw new Exception($draw_completion_status['error_message']);
            }

            // STEP 2: Get draw results (we know the draw is completed at this point)
            $winning_number = $draw_completion_status['winning_number'];
            $winning_color = $draw_completion_status['winning_color'];

            // Get all bets on this slip
            $betStmt = $conn->prepare("
                SELECT b.*
                FROM slip_details sd
                JOIN bets b ON sd.bet_id = b.bet_id
                WHERE sd.slip_id = ?
            ");
            $betStmt->bind_param("i", $slip['slip_id']);
            $betStmt->execute();
            $betResult = $betStmt->get_result();

            if ($betResult->num_rows === 0) {
                throw new Exception("No bets found on this slip");
            }

            $bets = array();
            $winning_bets = array();
            $total_winnings = 0;

            while ($bet = $betResult->fetch_assoc()) {
                $bets[] = $bet;

                // Check if this bet is a winner
                $is_winner = checkIfBetIsWinner($bet, $winning_number, $winning_color);

                if ($is_winner) {
                    $bet['is_winner'] = true;
                    $bet['winnings'] = $bet['potential_return'];
                    $winning_bets[] = $bet;
                    $total_winnings += $bet['potential_return'];
                } else {
                    $bet['is_winner'] = false;
                    $bet['winnings'] = 0;
                }
            }

            $betStmt->close();

            // Prepare response
            $response = array(
                'status' => 'success',
                'slip' => $slip,
                'draw_number' => $draw_number,
                'winning_number' => $winning_number,
                'winning_color' => $winning_color,
                'bets' => $bets,
                'winning_bets' => $winning_bets,
                'total_winnings' => $total_winnings,
                'has_winning_bets' => count($winning_bets) > 0
            );

        } catch (Exception $e) {
            $response = array(
                'status' => 'error',
                'message' => $e->getMessage()
            );
        }
    } elseif ($action === 'process_cashout') {
        try {
            // Set Georgetown timezone for proper timestamp recording
            date_default_timezone_set('America/Guyana'); // GMT-4
            $conn->query("SET time_zone = '-04:00'");

            // Get the slip number from the POST request
            $slip_number = isset($_POST['slip_number']) ? $_POST['slip_number'] : '';

            if (empty($slip_number)) {
                throw new Exception("Missing slip number");
            }

            // Start database transaction for atomic operations
            $conn->begin_transaction();

            // Get the slip details from the database
            $stmt = $conn->prepare("
                SELECT bs.*, u.username, u.cash_balance
                FROM betting_slips bs
                JOIN users u ON bs.user_id = u.user_id
                WHERE bs.slip_number = ?
            ");
            $stmt->bind_param("s", $slip_number);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                throw new Exception("Betting slip not found");
            }

            $slip = $result->fetch_assoc();
            $stmt->close();

            // Check if the slip is already paid
            if ($slip['is_paid'] == 1) {
                throw new Exception("This betting slip has already been cashed out");
            }

            // Check if the slip is cancelled
            if ($slip['is_cancelled'] == 1) {
                throw new Exception("This betting slip has been cancelled");
            }

            // Check if the slip is still valid (within one week)
            $slip_date = new DateTime($slip['created_at']);
            $current_date = new DateTime();
            $interval = $slip_date->diff($current_date);
            $days_difference = $interval->days;

            if ($days_difference > 7) {
                throw new Exception("This betting slip has expired. Slips are valid for 7 days after purchase.");
            }

            // Validate that the draw has been completed before processing cashout
            $draw_number = $slip['draw_number'];
            $draw_completion_status = validateDrawCompletion($conn, $draw_number);

            if (!$draw_completion_status['is_completed']) {
                throw new Exception($draw_completion_status['error_message']);
            }

            // Calculate actual winnings by checking all bets on this slip
            $winning_number = $draw_completion_status['winning_number'];
            $winning_color = $draw_completion_status['winning_color'];

            $betStmt = $conn->prepare("
                SELECT b.*
                FROM slip_details sd
                JOIN bets b ON sd.bet_id = b.bet_id
                WHERE sd.slip_id = ?
            ");
            $betStmt->bind_param("i", $slip['slip_id']);
            $betStmt->execute();
            $betResult = $betStmt->get_result();

            $total_winnings = 0;
            $winning_bets = array();

            while ($bet = $betResult->fetch_assoc()) {
                // Check if this bet is a winner
                $is_winner = checkIfBetIsWinner($bet, $winning_number, $winning_color);

                if ($is_winner) {
                    $total_winnings += $bet['potential_return'];
                    $winning_bets[] = $bet;
                }
            }
            $betStmt->close();

            // Only process cashout if there are actual winnings
            if ($total_winnings <= 0) {
                throw new Exception("This betting slip has no winning bets to cash out");
            }

            // Get current user balance
            $current_balance = floatval($slip['cash_balance']);
            $new_balance = $current_balance + $total_winnings;

            // Update the betting slip status with winning details
            $updateStmt = $conn->prepare("
                UPDATE betting_slips
                SET is_paid = 1,
                    status = 'cashed_out',
                    paid_out_amount = ?,
                    winning_number = ?,
                    cashout_time = NOW(),
                    updated_at = NOW()
                WHERE slip_id = ?
            ");
            $updateStmt->bind_param("dii", $total_winnings, $winning_number, $slip['slip_id']);
            $updateStmt->execute();
            $updateStmt->close();

            // Update user's cash balance
            $balanceStmt = $conn->prepare("
                UPDATE users
                SET cash_balance = ?,
                    updated_at = NOW()
                WHERE user_id = ?
            ");
            $balanceStmt->bind_param("di", $new_balance, $slip['user_id']);
            $balanceStmt->execute();
            $balanceStmt->close();

            // Create transaction record for audit trail
            $transactionStmt = $conn->prepare("
                INSERT INTO transactions (user_id, amount, balance_after, transaction_type, reference_id, description, created_at)
                VALUES (?, ?, ?, 'win', ?, ?, NOW())
            ");
            $description = "Cashout of winning betting slip #$slip_number (Draw #$draw_number, Winning Number: $winning_number)";
            $transactionStmt->bind_param("iddss",
                $slip['user_id'],
                $total_winnings,
                $new_balance,
                $slip_number,
                $description
            );
            $transactionStmt->execute();
            $transaction_id = $conn->insert_id;
            $transactionStmt->close();

            // Commit the transaction
            $conn->commit();

            // Return success response with detailed information
            $response = array(
                'status' => 'success',
                'message' => 'Cashout processed successfully',
                'data' => array(
                    'slip_number' => $slip_number,
                    'user_id' => $slip['user_id'],
                    'username' => $slip['username'],
                    'draw_number' => $draw_number,
                    'winning_number' => $winning_number,
                    'winning_color' => $winning_color,
                    'total_winnings' => $total_winnings,
                    'previous_balance' => $current_balance,
                    'new_balance' => $new_balance,
                    'transaction_id' => $transaction_id,
                    'winning_bets_count' => count($winning_bets),
                    'cashout_time' => date('Y-m-d H:i:s'),
                    'timezone' => 'GMT-4 (Georgetown)'
                )
            );

        } catch (Exception $e) {
            // Rollback transaction on error
            if ($conn->connect_errno === 0) {
                $conn->rollback();
            }

            $response = array(
                'status' => 'error',
                'message' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s'),
                'timezone' => 'GMT-4 (Georgetown)'
            );
        }
    }
}

// Function to check if a bet is a winner
function checkIfBetIsWinner($bet, $winning_number, $winning_color) {
    $bet_type = $bet['bet_type'];
    $bet_description = $bet['bet_description'];

    switch ($bet_type) {
        case 'straight':
            // Check for a straight up bet (e.g., "Straight Up on 12")
            preg_match('/(\d+)/', $bet_description, $matches);
            if (isset($matches[1])) {
                return intval($matches[1]) === $winning_number;
            }
            break;

        case 'split':
            // Check for a split bet (e.g., "Split (11,12)")
            preg_match('/\((\d+),(\d+)\)/', $bet_description, $matches);
            if (isset($matches[1]) && isset($matches[2])) {
                return $winning_number == intval($matches[1]) || $winning_number == intval($matches[2]);
            }
            break;

        case 'corner':
            // Check for a corner bet (e.g., "Corner (8,9,11,12)")
            preg_match('/\((\d+),(\d+),(\d+),(\d+)\)/', $bet_description, $matches);
            if (count($matches) >= 5) {
                return $winning_number == intval($matches[1]) ||
                       $winning_number == intval($matches[2]) ||
                       $winning_number == intval($matches[3]) ||
                       $winning_number == intval($matches[4]);
            }
            break;

        case 'street':
            // Check for a street bet (e.g., "Street (10,11,12)")
            preg_match('/\((\d+),(\d+),(\d+)\)/', $bet_description, $matches);
            if (count($matches) >= 4) {
                return $winning_number == intval($matches[1]) ||
                       $winning_number == intval($matches[2]) ||
                       $winning_number == intval($matches[3]);
            }
            break;

        case 'sixline':
            // Check for a six line bet (e.g., "Six Line (7,8,9,10,11,12)")
            preg_match('/\((\d+),(\d+),(\d+),(\d+),(\d+),(\d+)\)/', $bet_description, $matches);
            if (count($matches) >= 7) {
                return $winning_number == intval($matches[1]) ||
                       $winning_number == intval($matches[2]) ||
                       $winning_number == intval($matches[3]) ||
                       $winning_number == intval($matches[4]) ||
                       $winning_number == intval($matches[5]) ||
                       $winning_number == intval($matches[6]);
            }
            break;

        case 'dozen':
            // Check for a dozen bet (e.g., "1st Dozen (1-12)")
            if (strpos($bet_description, "1st Dozen") !== false) {
                return $winning_number >= 1 && $winning_number <= 12;
            } else if (strpos($bet_description, "2nd Dozen") !== false) {
                return $winning_number >= 13 && $winning_number <= 24;
            } else if (strpos($bet_description, "3rd Dozen") !== false) {
                return $winning_number >= 25 && $winning_number <= 36;
            }
            break;

        case 'column':
            // Check for a column bet
            if (strpos($bet_description, "1st Column") !== false) {
                return $winning_number % 3 == 1;
            } else if (strpos($bet_description, "2nd Column") !== false) {
                return $winning_number % 3 == 2;
            } else if (strpos($bet_description, "3rd Column") !== false) {
                return $winning_number % 3 == 0 && $winning_number != 0;
            }
            break;

        case 'low':
            // Check for low numbers (1-18)
            return $winning_number >= 1 && $winning_number <= 18;

        case 'high':
            // Check for high numbers (19-36)
            return $winning_number >= 19 && $winning_number <= 36;

        case 'even':
            // Check for even numbers
            return $winning_number != 0 && $winning_number % 2 == 0;

        case 'odd':
            // Check for odd numbers
            return $winning_number % 2 == 1;

        case 'red':
            // Check for red numbers
            return $winning_color == 'red';

        case 'black':
            // Check for black numbers
            return $winning_color == 'black';

        case 'even-money':
            // Handle even-money bets based on description
            if (strpos($bet_description, "Low Numbers") !== false) {
                // Low numbers (1-18)
                return $winning_number >= 1 && $winning_number <= 18;
            } else if (strpos($bet_description, "High Numbers") !== false) {
                // High numbers (19-36)
                return $winning_number >= 19 && $winning_number <= 36;
            } else if (strpos($bet_description, "Even Numbers") !== false) {
                // Even numbers
                return $winning_number != 0 && $winning_number % 2 == 0;
            } else if (strpos($bet_description, "Odd Numbers") !== false) {
                // Odd numbers
                return $winning_number % 2 == 1;
            } else if (strpos($bet_description, "Red Numbers") !== false) {
                // Red numbers
                return $winning_color == 'red';
            } else if (strpos($bet_description, "Black Numbers") !== false) {
                // Black numbers
                return $winning_color == 'black';
            }
            break;
    }

    return false;
}

/**
 * Validate if a draw has been completed and results are available
 * @param mysqli $conn Database connection
 * @param int $draw_number The draw number to validate
 * @return array Status information about the draw completion
 */
function validateDrawCompletion($conn, $draw_number) {
    $result = [
        'is_completed' => false,
        'winning_number' => null,
        'winning_color' => null,
        'error_message' => '',
        'current_draw_number' => null,
        'next_draw_number' => null
    ];

    try {
        // ONLY METHOD: Check if draw exists in detailed_draw_results (AUTHORITATIVE SOURCE)
        // This is the ONLY reliable source for completed draws - no fallbacks to analytics

        // First check what columns exist in the table
        $columnsStmt = $conn->prepare("SHOW COLUMNS FROM detailed_draw_results LIKE 'winning_color'");
        $columnsStmt->execute();
        $columnsResult = $columnsStmt->get_result();
        $hasWinningColorColumn = $columnsResult->num_rows > 0;
        $columnsStmt->close();

        // Also check for 'color' column (actual column name in this database)
        $colorColumnsStmt = $conn->prepare("SHOW COLUMNS FROM detailed_draw_results LIKE 'color'");
        $colorColumnsStmt->execute();
        $colorColumnsResult = $colorColumnsStmt->get_result();
        $hasColorColumn = $colorColumnsResult->num_rows > 0;
        $colorColumnsStmt->close();

        // Check for timestamp columns
        $timestampColumnsStmt = $conn->prepare("SHOW COLUMNS FROM detailed_draw_results LIKE 'timestamp'");
        $timestampColumnsStmt->execute();
        $timestampColumnsResult = $timestampColumnsStmt->get_result();
        $hasTimestampColumn = $timestampColumnsResult->num_rows > 0;
        $timestampColumnsStmt->close();

        // Build query based on available columns
        $selectColumns = "winning_number";
        if ($hasWinningColorColumn) {
            $selectColumns .= ", winning_color";
        } elseif ($hasColorColumn) {
            $selectColumns .= ", color";
        }
        if ($hasTimestampColumn) {
            $selectColumns .= ", timestamp";
        }

        $historyStmt = $conn->prepare("
            SELECT $selectColumns
            FROM detailed_draw_results
            WHERE draw_number = ?
            LIMIT 1
        ");

        $historyStmt->bind_param("i", $draw_number);
        $historyStmt->execute();
        $historyResult = $historyStmt->get_result();

        if ($historyResult->num_rows > 0) {
            // Draw found in detailed results - it's completed
            $drawHistory = $historyResult->fetch_assoc();
            $result['is_completed'] = true;
            $result['winning_number'] = $drawHistory['winning_number'];

            // Get winning color from database or calculate it
            if ($hasWinningColorColumn && isset($drawHistory['winning_color'])) {
                $result['winning_color'] = $drawHistory['winning_color'];
            } elseif ($hasColorColumn && isset($drawHistory['color'])) {
                $result['winning_color'] = $drawHistory['color'];
            } else {
                $result['winning_color'] = calculateNumberColor($result['winning_number']);
            }

            // Store draw time if available
            if ($hasTimestampColumn && isset($drawHistory['timestamp'])) {
                $result['draw_time'] = $drawHistory['timestamp'];
            }

            $historyStmt->close();
            return $result;
        }
        $historyStmt->close();

        // If draw not found in detailed_draw_results, it has NOT occurred yet
        // Get the actual current completed draw from detailed_draw_results (authoritative)
        $maxDrawStmt = $conn->prepare("SELECT MAX(draw_number) as max_completed_draw FROM detailed_draw_results");
        $maxDrawStmt->execute();
        $maxDrawResult = $maxDrawStmt->get_result();
        $maxCompletedDraw = 0;

        if ($maxDrawResult->num_rows > 0) {
            $maxDrawData = $maxDrawResult->fetch_assoc();
            $maxCompletedDraw = (int)($maxDrawData['max_completed_draw'] ?? 0);
        }
        $maxDrawStmt->close();

        $result['current_draw_number'] = $maxCompletedDraw;
        $result['next_draw_number'] = $maxCompletedDraw + 1;

        // Since the draw was not found in detailed_draw_results, it has not occurred yet
        $result['error_message'] = "This draw (#$draw_number) has not occurred yet. " .
                                 "Current completed draw is #$maxCompletedDraw. " .
                                 "Please wait for the draw to be completed before attempting to cash out.";

    } catch (Exception $e) {
        $result['error_message'] = "Error validating draw completion: " . $e->getMessage();
    }

    return $result;
}

/**
 * Get current draw information from multiple sources
 * @param mysqli $conn Database connection
 * @return array Current draw information
 */
function getCurrentDrawInfo($conn) {
    $info = [
        'current_draw' => 0,
        'next_draw' => 1,
        'source' => 'fallback'
    ];

    try {
        // Try roulette_analytics table first (most reliable for current draw)
        $analyticsStmt = $conn->prepare("SELECT current_draw_number FROM roulette_analytics WHERE id = 1");
        if ($analyticsStmt) {
            $analyticsStmt->execute();
            $analyticsResult = $analyticsStmt->get_result();

            if ($analyticsResult->num_rows > 0) {
                $analytics = $analyticsResult->fetch_assoc();
                $currentDrawNumber = (int)$analytics['current_draw_number'];
                if ($currentDrawNumber > 0) {  // Only use if it's a valid number
                    $info['current_draw'] = $currentDrawNumber;
                    $info['next_draw'] = $info['current_draw'] + 1;
                    $info['source'] = 'roulette_analytics';
                    $analyticsStmt->close();
                    return $info;
                }
            }
            $analyticsStmt->close();
        }

        // Try detailed_draw_results table as second priority
        $detailedStmt = $conn->prepare("SELECT MAX(draw_number) as max_draw FROM detailed_draw_results");
        if ($detailedStmt) {
            $detailedStmt->execute();
            $detailedResult = $detailedStmt->get_result();

            if ($detailedResult->num_rows > 0) {
                $detailed = $detailedResult->fetch_assoc();
                if ($detailed['max_draw'] && $detailed['max_draw'] > 0) {
                    $info['current_draw'] = (int)$detailed['max_draw'];
                    $info['next_draw'] = $info['current_draw'] + 1;
                    $info['source'] = 'detailed_draw_results';
                    $detailedStmt->close();
                    return $info;
                }
            }
            $detailedStmt->close();
        }

        // Try roulette_state table as last resort
        $stateStmt = $conn->prepare("SELECT last_draw, next_draw FROM roulette_state WHERE id = 1");
        if ($stateStmt) {
            $stateStmt->execute();
            $stateResult = $stateStmt->get_result();

            if ($stateResult->num_rows > 0) {
                $state = $stateResult->fetch_assoc();
                $currentDrawFromState = (int)str_replace('#', '', $state['last_draw']);
                if ($currentDrawFromState > 0) {  // Only use if it's a valid number
                    $info['current_draw'] = $currentDrawFromState;
                    $info['next_draw'] = (int)str_replace('#', '', $state['next_draw']);
                    $info['source'] = 'roulette_state';
                    $stateStmt->close();
                    return $info;
                }
            }
            $stateStmt->close();
        }



    } catch (Exception $e) {
        // Use fallback values
    }

    return $info;
}

/**
 * Calculate the color of a roulette number
 * @param int $number The roulette number (0-36)
 * @return string The color ('green', 'red', or 'black')
 */
function calculateNumberColor($number) {
    if ($number == 0) {
        return "green";
    } else if (in_array($number, [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36])) {
        return "red";
    } else {
        return "black";
    }
}

// Send the JSON response
echo json_encode($response);