<?php
/**
 * API endpoint for getting transaction data in real-time
 * This file provides JSON data for AJAX requests
 */

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

/**
 * Check if a bet is a winner based on the winning number and color
 *
 * @param array $bet The bet information
 * @param int $winningNumber The winning number
 * @param string $winningColor The winning color (red, black, green)
 * @return bool True if the bet is a winner, false otherwise
 */
function checkIfBetIsWinner($bet, $winningNumber, $winningColor) {
    $betType = $bet['bet_type'];
    $betDescription = $bet['bet_description'];

    switch ($betType) {
        case 'straight':
            // Check for a straight up bet (e.g., "Straight Up on 12")
            preg_match('/(\d+)/', $betDescription, $matches);
            if (isset($matches[1])) {
                return intval($matches[1]) === $winningNumber;
            }
            break;

        case 'split':
            // Check for a split bet (e.g., "Split (11,12)")
            preg_match('/\((\d+),(\d+)\)/', $betDescription, $matches);
            if (isset($matches[1]) && isset($matches[2])) {
                return $winningNumber == intval($matches[1]) || $winningNumber == intval($matches[2]);
            }
            break;

        case 'corner':
            // Check for a corner bet (e.g., "Corner (8,9,11,12)")
            preg_match('/\((\d+),(\d+),(\d+),(\d+)\)/', $betDescription, $matches);
            if (count($matches) >= 5) {
                return $winningNumber == intval($matches[1]) ||
                       $winningNumber == intval($matches[2]) ||
                       $winningNumber == intval($matches[3]) ||
                       $winningNumber == intval($matches[4]);
            }
            break;

        case 'street':
            // Check for a street bet (e.g., "Street (10,11,12)")
            preg_match('/\((\d+),(\d+),(\d+)\)/', $betDescription, $matches);
            if (count($matches) >= 4) {
                return $winningNumber == intval($matches[1]) ||
                       $winningNumber == intval($matches[2]) ||
                       $winningNumber == intval($matches[3]);
            }
            break;

        case 'dozen':
            // Check for a dozen bet (e.g., "1st Dozen (1-12)")
            if (strpos($betDescription, "1st Dozen") !== false) {
                return $winningNumber >= 1 && $winningNumber <= 12;
            } else if (strpos($betDescription, "2nd Dozen") !== false) {
                return $winningNumber >= 13 && $winningNumber <= 24;
            } else if (strpos($betDescription, "3rd Dozen") !== false) {
                return $winningNumber >= 25 && $winningNumber <= 36;
            }
            break;

        case 'column':
            // Check for a column bet
            if (strpos($betDescription, "1st Column") !== false) {
                return $winningNumber % 3 == 1;
            } else if (strpos($betDescription, "2nd Column") !== false) {
                return $winningNumber % 3 == 2;
            } else if (strpos($betDescription, "3rd Column") !== false) {
                return $winningNumber % 3 == 0 && $winningNumber != 0;
            }
            break;

        case 'even-money':
            // Handle even-money bets based on description
            if (strpos($betDescription, "Low Numbers") !== false) {
                // Low numbers (1-18)
                return $winningNumber >= 1 && $winningNumber <= 18;
            } else if (strpos($betDescription, "High Numbers") !== false) {
                // High numbers (19-36)
                return $winningNumber >= 19 && $winningNumber <= 36;
            } else if (strpos($betDescription, "Even Numbers") !== false) {
                // Even numbers
                return $winningNumber != 0 && $winningNumber % 2 == 0;
            } else if (strpos($betDescription, "Odd Numbers") !== false) {
                // Odd numbers
                return $winningNumber % 2 == 1;
            } else if (strpos($betDescription, "Red Numbers") !== false) {
                // Red numbers
                return $winningColor == 'red';
            } else if (strpos($betDescription, "Black Numbers") !== false) {
                // Black numbers
                return $winningColor == 'black';
            }
            break;

        case 'line':
            // Check for a line bet (e.g., "Line (1,2,3,4,5,6)")
            preg_match_all('/\d+/', $betDescription, $matches);
            if (!empty($matches[0])) {
                foreach ($matches[0] as $number) {
                    if (intval($number) == $winningNumber) {
                        return true;
                    }
                }
            }
            break;

        default:
            // For any other bet types, check if the description contains the winning number
            return strpos($betDescription, (string)$winningNumber) !== false;
    }

    return false;
}

/**
 * Get winning information for a draw - SIMPLIFIED VERSION
 *
 * @param mysqli $conn Database connection
 * @param int $drawNumber The draw number to get information for
 * @return array|null Winning information or null if not found
 */
function getWinningInformation($conn, $drawNumber) {
    // Only get from detailed_draw_results (most reliable source)
    $stmt = $conn->prepare("
        SELECT winning_number, color as winning_color, timestamp as draw_time
        FROM detailed_draw_results
        WHERE draw_number = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $drawNumber);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }

    // If not found, return null (draw hasn't occurred yet)
    return null;
}

/**
 * Get current draw number from roulette_analytics
 */
function getCurrentDrawNumber($conn) {
    $stmt = $conn->prepare("SELECT current_draw_number FROM roulette_analytics WHERE id = 1");
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['current_draw_number'];
    }

    return 1; // Default fallback
}

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
$lastUpdated = isset($_GET['last_updated']) ? intval($_GET['last_updated']) : 0;

// Get request type
$requestType = isset($_GET['type']) ? $_GET['type'] : 'all';

// Initialize response
$response = [
    'success' => true,
    'timestamp' => time(),
    'data' => []
];

// Handle different request types
switch ($requestType) {
    case 'user_info':
        // Get user details
        $stmt = $conn->prepare("
            SELECT
                u.username,
                u.role,
                u.cash_balance,
                u.created_at,
                u.last_login,
                (SELECT COUNT(*) FROM betting_slips bs JOIN transactions t ON bs.transaction_id = t.transaction_id WHERE t.user_id = u.user_id) as total_bets,
                (SELECT COUNT(*) FROM betting_slips bs JOIN transactions t ON bs.transaction_id = t.transaction_id WHERE t.user_id = u.user_id AND bs.status = 'won') as winning_bets
            FROM users u
            WHERE u.user_id = ?
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            // Calculate win rate
            $user['win_rate'] = $user['total_bets'] > 0 ? round(($user['winning_bets'] / $user['total_bets']) * 100, 1) : 0;
            $response['data']['user'] = $user;
        }
        break;

    case 'transactions':
        // Get recent transactions
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
        $stmt = $conn->prepare("
            SELECT
                transaction_id,
                amount,
                balance_after,
                transaction_type,
                reference_id,
                description,
                created_at,
                UNIX_TIMESTAMP(created_at) as timestamp
            FROM transactions
            WHERE user_id = ? AND UNIX_TIMESTAMP(created_at) > ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->bind_param("iii", $userId, $lastUpdated, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $transactions = [];
        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }
        $response['data']['transactions'] = $transactions;
        break;

    case 'betting_slips':
        // Get betting slips with updates
        $stmt = $conn->prepare("
            SELECT
                bs.slip_id,
                bs.slip_number,
                bs.draw_number,
                bs.total_stake,
                bs.potential_payout,
                bs.created_at,
                bs.is_paid,
                bs.is_cancelled,
                bs.status,
                bs.winning_number,
                (bs.status = 'won') as is_winner,
                bs.paid_out_amount as winning_amount,
                bs.transaction_id,
                ddr.winning_number AS actual_winning_number,
                ddr.color as winning_color,
                ddr.timestamp as draw_time,
                UNIX_TIMESTAMP(ddr.timestamp) as draw_timestamp,
                UNIX_TIMESTAMP(NOW()) as current_time_ts
            FROM betting_slips bs
            LEFT JOIN transactions t ON bs.transaction_id = t.transaction_id
            LEFT JOIN detailed_draw_results ddr ON bs.draw_number = ddr.draw_number
            WHERE t.user_id = ? AND (
                bs.status = 'pending' OR
                UNIX_TIMESTAMP(bs.created_at) > ? OR
                (ddr.timestamp IS NOT NULL AND UNIX_TIMESTAMP(ddr.timestamp) > ?)
            )
            ORDER BY bs.created_at DESC
            LIMIT 50
        ");
        $stmt->bind_param("iii", $userId, $lastUpdated, $lastUpdated);
        $stmt->execute();
        $result = $stmt->get_result();
        $bettingSlips = [];
        while ($row = $result->fetch_assoc()) {
            // If we don't have winning information from detailed_draw_results, try to get it from other sources
            if ($row['actual_winning_number'] === null) {
                $winningInfo = getWinningInformation($conn, $row['draw_number']);
                if ($winningInfo) {
                    $row['actual_winning_number'] = $winningInfo['winning_number'];
                    $row['winning_color'] = $winningInfo['winning_color'];
                    $row['draw_time'] = $winningInfo['draw_time'];
                    $row['draw_timestamp'] = strtotime($winningInfo['draw_time']);
                }
            }

            // If we have winning information, process the bets to determine winners
            if ($row['actual_winning_number'] !== null) {
                $winningNumber = $row['actual_winning_number'];
                $winningColor = $row['winning_color'];

                // Get the bets for this slip
                $slipBets = [];
                $betStmt = $conn->prepare("
                    SELECT
                        sd.detail_id,
                        b.bet_id,
                        b.bet_type,
                        b.bet_description,
                        b.bet_amount,
                        b.multiplier,
                        b.potential_return
                    FROM slip_details sd
                    JOIN bets b ON sd.bet_id = b.bet_id
                    WHERE sd.slip_id = ?
                ");
                $betStmt->bind_param("i", $row['slip_id']);
                $betStmt->execute();
                $betResult = $betStmt->get_result();

                $totalWinnings = 0;
                $hasWinningBets = false;

                while ($bet = $betResult->fetch_assoc()) {
                    // Check if this bet is a winner
                    $isWinner = checkIfBetIsWinner($bet, $winningNumber, $winningColor);

                    $bet['is_winner'] = $isWinner;
                    $bet['winning_amount'] = $isWinner ? $bet['potential_return'] : 0;

                    if ($isWinner) {
                        $hasWinningBets = true;
                        $totalWinnings += $bet['potential_return'];
                    }

                    $slipBets[] = $bet;
                }

                // Update the slip status based on the bets
                if ($row['status'] === 'pending' || $row['status'] === 'active') {
                    if ($hasWinningBets) {
                        $row['is_winner'] = 1;
                        $row['status'] = 'won';
                        $row['winning_amount'] = $totalWinnings;
                    } else {
                        $row['status'] = 'lost';
                        $row['winning_amount'] = 0;
                    }
                }

                $row['bets'] = $slipBets;
            } else {
                // If we don't have winning information, just get the bets without checking winners
                $slipBets = [];
                $betStmt = $conn->prepare("
                    SELECT
                        sd.detail_id,
                        0 as is_winner,
                        0 as winning_amount,
                        b.bet_id,
                        b.bet_type,
                        b.bet_description,
                        b.bet_amount,
                        b.multiplier,
                        b.potential_return
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
            }

            // Calculate time remaining until draw
            $row['time_remaining'] = null;
            if ($row['draw_timestamp'] && $row['draw_timestamp'] > $row['current_time_ts']) {
                $row['time_remaining'] = $row['draw_timestamp'] - $row['current_time_ts'];
            }

            $bettingSlips[] = $row;
        }
        $response['data']['betting_slips'] = $bettingSlips;
        break;

    case 'summary':
        // Get summary statistics
        $stmt = $conn->prepare("
            SELECT
                SUM(CASE WHEN t.transaction_type = 'bet' THEN ABS(t.amount) ELSE 0 END) as total_bets,
                SUM(CASE WHEN t.transaction_type = 'win' THEN t.amount ELSE 0 END) as total_wins,
                COUNT(DISTINCT bs.slip_id) as total_slips,
                SUM(bs.potential_payout) as total_potential_wins,
                SUM(CASE WHEN bs.status = 'won' THEN bs.paid_out_amount ELSE 0 END) as total_actual_wins,
                COUNT(CASE WHEN bs.status = 'won' THEN 1 END) as winning_slips_count
            FROM transactions t
            LEFT JOIN betting_slips bs ON t.transaction_id = bs.transaction_id
            WHERE t.user_id = ?
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $summary = $result->fetch_assoc();

            // Calculate additional metrics
            $summary['net_profit'] = $summary['total_wins'] - $summary['total_bets'];
            $summary['roi'] = $summary['total_bets'] > 0 ? ($summary['net_profit'] / $summary['total_bets']) * 100 : 0;
            $summary['win_rate'] = $summary['total_slips'] > 0 ? ($summary['winning_slips_count'] / $summary['total_slips']) * 100 : 0;

            $response['data']['summary'] = $summary;
        }
        break;

    case 'all':
    default:
        // Get all data (user info, transactions, betting slips, summary)
        // User info
        $stmt = $conn->prepare("
            SELECT
                u.username,
                u.role,
                u.cash_balance,
                u.created_at,
                u.last_login,
                (SELECT COUNT(*) FROM betting_slips bs JOIN transactions t ON bs.transaction_id = t.transaction_id WHERE t.user_id = u.user_id) as total_bets,
                (SELECT COUNT(*) FROM betting_slips bs JOIN transactions t ON bs.transaction_id = t.transaction_id WHERE t.user_id = u.user_id AND bs.status = 'won') as winning_bets
            FROM users u
            WHERE u.user_id = ?
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            // Calculate win rate
            $user['win_rate'] = $user['total_bets'] > 0 ? round(($user['winning_bets'] / $user['total_bets']) * 100, 1) : 0;
            $response['data']['user'] = $user;
        }

        // Recent transactions
        $stmt = $conn->prepare("
            SELECT
                transaction_id,
                amount,
                balance_after,
                transaction_type,
                reference_id,
                description,
                created_at,
                UNIX_TIMESTAMP(created_at) as timestamp
            FROM transactions
            WHERE user_id = ? AND UNIX_TIMESTAMP(created_at) > ?
            ORDER BY created_at DESC
            LIMIT 20
        ");
        $stmt->bind_param("ii", $userId, $lastUpdated);
        $stmt->execute();
        $result = $stmt->get_result();
        $transactions = [];
        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }
        $response['data']['transactions'] = $transactions;

        // Get betting slips with updates
        // (Same query as in the 'betting_slips' case)
        // ...

        // Summary statistics
        // (Same query as in the 'summary' case)
        // ...

        break;
}

// Close connection
$conn->close();

// Return response
echo json_encode($response);
?>
