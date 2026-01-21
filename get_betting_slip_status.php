<?php
// Start session
session_start();

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Not authenticated'
    ]);
    exit;
}

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
        'success' => false,
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]);
    exit;
}

// Get user ID from session
$userId = $_SESSION['user_id'];

// Get the last updated timestamp if provided
$lastUpdated = isset($_GET['last_updated']) ? $_GET['last_updated'] : 0;

// Get user's betting slips with updated status
$bettingSlips = [];
$stmt = $conn->prepare("
    SELECT bs.slip_id, bs.slip_number, bs.draw_number, bs.total_stake, bs.potential_payout,
           bs.created_at, bs.is_paid, bs.is_cancelled, bs.status,
           bs.winning_number, bs.paid_out_amount, bs.transaction_id,
           ddr.winning_number AS actual_winning_number, ddr.winning_color,
           ddr.draw_time,
           UNIX_TIMESTAMP(ddr.draw_time) as draw_timestamp
    FROM betting_slips bs
    LEFT JOIN transactions t ON bs.transaction_id = t.transaction_id
    LEFT JOIN detailed_draw_results ddr ON bs.draw_number = ddr.draw_number
    WHERE t.user_id = ?
    ORDER BY bs.created_at DESC
    LIMIT 50
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Skip if this slip hasn't been updated since last check
        if (isset($row['draw_timestamp']) && $row['draw_timestamp'] <= $lastUpdated) {
            continue;
        }

        // Get the bets for this slip
        $slipBets = [];
        $betStmt = $conn->prepare("
            SELECT b.bet_type, b.bet_description, b.bet_amount, b.multiplier, b.potential_return
            FROM slip_details sd
            JOIN bets b ON sd.bet_id = b.bet_id
            WHERE sd.slip_id = ?
        ");
        $betStmt->bind_param("i", $row['slip_id']);
        $betStmt->execute();
        $betResult = $betStmt->get_result();
        while ($bet = $betResult->fetch_assoc()) {
            $slipBets[] = $bet;
        }
        $row['bets'] = $slipBets;

        // Calculate if this slip is a winner
        $row['is_winner'] = false;
        $row['winning_amount'] = 0;

        if ($row['actual_winning_number'] !== null) {
            foreach ($slipBets as $bet) {
                // Check if bet is a winner based on bet type and winning number
                $isWinningBet = false;
                $winningNumber = $row['actual_winning_number'];

                switch ($bet['bet_type']) {
                    case 'straight':
                        // Extract the number from the description (e.g., "Straight Up on 5" -> 5)
                        preg_match('/(\d+)/', $bet['bet_description'], $matches);
                        if (isset($matches[1]) && $matches[1] == $winningNumber) {
                            $isWinningBet = true;
                        }
                        break;

                    case 'split':
                        // Extract numbers from description (e.g., "Split (5,8)" -> 5,8)
                        preg_match('/\((\d+),(\d+)\)/', $bet['bet_description'], $matches);
                        if (isset($matches[1]) && isset($matches[2])) {
                            if ($matches[1] == $winningNumber || $matches[2] == $winningNumber) {
                                $isWinningBet = true;
                            }
                        }
                        break;

                    case 'street':
                        // Extract numbers from description (e.g., "Street (1,2,3)" -> 1,2,3)
                        preg_match('/\((\d+),(\d+),(\d+)\)/', $bet['bet_description'], $matches);
                        if (count($matches) >= 4) {
                            if ($matches[1] == $winningNumber || $matches[2] == $winningNumber || $matches[3] == $winningNumber) {
                                $isWinningBet = true;
                            }
                        }
                        break;

                    case 'corner':
                        // Extract numbers from description (e.g., "Corner (1,2,4,5)" -> 1,2,4,5)
                        preg_match('/\((\d+),(\d+),(\d+),(\d+)\)/', $bet['bet_description'], $matches);
                        if (count($matches) >= 5) {
                            if ($matches[1] == $winningNumber || $matches[2] == $winningNumber ||
                                $matches[3] == $winningNumber || $matches[4] == $winningNumber) {
                                $isWinningBet = true;
                            }
                        }
                        break;

                    case 'line':
                        // Extract numbers from description (e.g., "Line (1,2,3,4,5,6)" -> 1,2,3,4,5,6)
                        preg_match('/\((\d+),(\d+),(\d+),(\d+),(\d+),(\d+)\)/', $bet['bet_description'], $matches);
                        if (count($matches) >= 7) {
                            if ($matches[1] == $winningNumber || $matches[2] == $winningNumber ||
                                $matches[3] == $winningNumber || $matches[4] == $winningNumber ||
                                $matches[5] == $winningNumber || $matches[6] == $winningNumber) {
                                $isWinningBet = true;
                            }
                        }
                        break;

                    case 'dozen':
                        // Check if winning number is in the dozen range
                        if (strpos($bet['bet_description'], '1st Dozen') !== false && $winningNumber >= 1 && $winningNumber <= 12) {
                            $isWinningBet = true;
                        } else if (strpos($bet['bet_description'], '2nd Dozen') !== false && $winningNumber >= 13 && $winningNumber <= 24) {
                            $isWinningBet = true;
                        } else if (strpos($bet['bet_description'], '3rd Dozen') !== false && $winningNumber >= 25 && $winningNumber <= 36) {
                            $isWinningBet = true;
                        }
                        break;

                    case 'column':
                        // Check if winning number is in the column
                        if (strpos($bet['bet_description'], '1st Column') !== false) {
                            // 1, 4, 7, 10, 13, 16, 19, 22, 25, 28, 31, 34
                            if ($winningNumber % 3 == 1) {
                                $isWinningBet = true;
                            }
                        } else if (strpos($bet['bet_description'], '2nd Column') !== false) {
                            // 2, 5, 8, 11, 14, 17, 20, 23, 26, 29, 32, 35
                            if ($winningNumber % 3 == 2) {
                                $isWinningBet = true;
                            }
                        } else if (strpos($bet['bet_description'], '3rd Column') !== false) {
                            // 3, 6, 9, 12, 15, 18, 21, 24, 27, 30, 33, 36
                            if ($winningNumber % 3 == 0 && $winningNumber > 0) {
                                $isWinningBet = true;
                            }
                        }
                        break;

                    case 'even-money':
                        // Handle even-money bets (Red/Black, Odd/Even, 1-18/19-36)
                        if (strpos($bet['bet_description'], 'Red Numbers') !== false) {
                            // Red numbers: 1,3,5,7,9,12,14,16,18,19,21,23,25,27,30,32,34,36
                            $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
                            if (in_array($winningNumber, $redNumbers)) {
                                $isWinningBet = true;
                            }
                        } else if (strpos($bet['bet_description'], 'Black Numbers') !== false) {
                            // Black numbers: 2,4,6,8,10,11,13,15,17,20,22,24,26,28,29,31,33,35
                            $blackNumbers = [2, 4, 6, 8, 10, 11, 13, 15, 17, 20, 22, 24, 26, 28, 29, 31, 33, 35];
                            if (in_array($winningNumber, $blackNumbers)) {
                                $isWinningBet = true;
                            }
                        } else if (strpos($bet['bet_description'], 'Even') !== false) {
                            // Even numbers: 2,4,6,8,10,12,14,16,18,20,22,24,26,28,30,32,34,36
                            if ($winningNumber > 0 && $winningNumber % 2 == 0) {
                                $isWinningBet = true;
                            }
                        } else if (strpos($bet['bet_description'], 'Odd') !== false) {
                            // Odd numbers: 1,3,5,7,9,11,13,15,17,19,21,23,25,27,29,31,33,35
                            if ($winningNumber > 0 && $winningNumber % 2 == 1) {
                                $isWinningBet = true;
                            }
                        } else if (strpos($bet['bet_description'], '1-18') !== false) {
                            // Low numbers: 1-18
                            if ($winningNumber >= 1 && $winningNumber <= 18) {
                                $isWinningBet = true;
                            }
                        } else if (strpos($bet['bet_description'], '19-36') !== false) {
                            // High numbers: 19-36
                            if ($winningNumber >= 19 && $winningNumber <= 36) {
                                $isWinningBet = true;
                            }
                        } else if (preg_match('/\(([\d,]+)\)/', $bet['bet_description'], $matches)) {
                            // Handle custom number lists like "Red Numbers (1,3,5,7,9,12,14,16,18,19,21,23,25,27,30,32,34,36)"
                            $numberList = explode(',', $matches[1]);
                            if (in_array($winningNumber, $numberList)) {
                                $isWinningBet = true;
                            }
                        }
                        break;

                    case 'basket':
                        // Basket bet (0,1,2,3)
                        if ($winningNumber >= 0 && $winningNumber <= 3) {
                            $isWinningBet = true;
                        }
                        break;

                    case 'snake':
                        // Snake bet (1,5,9,12,14,16,19,23,27,30,32,34)
                        $snakeNumbers = [1, 5, 9, 12, 14, 16, 19, 23, 27, 30, 32, 34];
                        if (in_array($winningNumber, $snakeNumbers)) {
                            $isWinningBet = true;
                        }
                        break;

                    default:
                        // For any other bet type, try to extract numbers from parentheses and check
                        if (preg_match('/\(([\d,]+)\)/', $bet['bet_description'], $matches)) {
                            $numberList = explode(',', $matches[1]);
                            if (in_array($winningNumber, $numberList)) {
                                $isWinningBet = true;
                            }
                        }
                        break;
                }

                if ($isWinningBet) {
                    $row['is_winner'] = true;
                    $row['winning_amount'] += $bet['potential_return'];
                }
            }
        }

        $bettingSlips[] = $row;
    }
}

// Get current server timestamp
$currentTimestamp = time();

// Prepare response
$response = [
    'success' => true,
    'timestamp' => $currentTimestamp,
    'betting_slips' => $bettingSlips
];

// Close connection
$conn->close();

// Return response
echo json_encode($response);
?>
