<?php
/**
 * My Transactions API
 *
 * Provides real-time transaction and betting slip data for AJAX updates
 * Integrates with Georgetown Time Manager (GMT-4/UTC-4)
 */

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header("Content-Type: application/json");

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'User not logged in',
        'timestamp' => date("Y-m-d H:i:s")
    ]);
    exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "roulette";

try {
    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $conn->set_charset("utf8mb4");

    // Set timezone to Georgetown, Guyana (GMT-4)
    $conn->query("SET time_zone = '-04:00'");

    $userId = $_SESSION['user_id'];
    $action = $_GET['action'] ?? 'summary';

    switch ($action) {
        case 'summary':
            echo json_encode(getTransactionSummary($conn, $userId));
            break;

        case 'recent_slips':
            echo json_encode(getRecentBettingSlips($conn, $userId));
            break;

        case 'balance':
            echo json_encode(getUserBalance($conn, $userId));
            break;

        case 'recent_transactions':
            echo json_encode(getRecentTransactions($conn, $userId));
            break;

        case 'cashout_notification':
            // POS System: Cashout notifications disabled for cashier interface
            echo json_encode([
                'status' => 'success',
                'data' => [], // Return empty notifications array
                'timestamp' => date("Y-m-d H:i:s"),
                'timezone' => 'GMT-4 (Georgetown)'
            ]);
            break;

        case 'full_update':
            echo json_encode(getFullUpdate($conn, $userId));
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid action',
                'timestamp' => date("Y-m-d H:i:s")
            ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'timestamp' => date("Y-m-d H:i:s")
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

/**
 * Get transaction summary with corrected total wins calculation
 */
function getTransactionSummary($conn, $userId) {
    // Get betting slips with win calculation
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
            ddr.timestamp as draw_time
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

    $totalBets = 0;
    $totalActualWins = 0;
    $totalPotentialWins = 0;
    $winningSlips = 0;
    $totalSlips = 0;

    while ($row = $result->fetch_assoc()) {
        $totalSlips++;
        $totalBets += $row['total_stake'];
        $totalPotentialWins += $row['potential_payout'];

        // Calculate actual wins from betting slips
        if ($row['is_winner'] || $row['status'] === 'won') {
            $winAmount = isset($row['winning_amount']) && $row['winning_amount'] > 0
                ? $row['winning_amount']
                : (isset($row['paid_out_amount']) ? $row['paid_out_amount'] : 0);

            if ($winAmount > 0) {
                $totalActualWins += $winAmount;
                $winningSlips++;
            }
        }
    }

    // Get transaction-based totals for comparison
    $stmt2 = $conn->prepare("
        SELECT
            SUM(CASE WHEN transaction_type = 'bet' THEN ABS(amount) ELSE 0 END) as transaction_bets,
            SUM(CASE WHEN transaction_type = 'win' THEN amount ELSE 0 END) as transaction_wins
        FROM transactions
        WHERE user_id = ?
    ");
    $stmt2->bind_param("i", $userId);
    $stmt2->execute();
    $transactionResult = $stmt2->get_result()->fetch_assoc();

    $netProfit = $totalActualWins - $totalBets;
    $roi = $totalBets > 0 ? ($netProfit / $totalBets) * 100 : 0;
    $winRate = $totalSlips > 0 ? ($winningSlips / $totalSlips) * 100 : 0;

    return [
        'status' => 'success',
        'data' => [
            'total_bets' => $totalBets,
            'total_wins' => $totalActualWins, // Corrected: Use actual wins from betting slips
            'total_potential_wins' => $totalPotentialWins,
            'net_profit' => $netProfit,
            'roi' => round($roi, 2),
            'win_rate' => round($winRate, 1),
            'winning_slips' => $winningSlips,
            'total_slips' => $totalSlips,
            'transaction_wins' => $transactionResult['transaction_wins'] ?? 0, // For debugging
            'calculation_method' => 'betting_slips' // Indicates source of wins calculation
        ],
        'timestamp' => date("Y-m-d H:i:s"),
        'timezone' => 'GMT-4 (Georgetown)'
    ];
}

/**
 * Get recent betting slips with real-time status
 */
function getRecentBettingSlips($conn, $userId) {
    $stmt = $conn->prepare("
        SELECT
            bs.slip_id,
            bs.slip_number,
            bs.draw_number,
            bs.total_stake,
            bs.potential_payout,
            bs.created_at,
            bs.status,
            bs.winning_amount,
            bs.paid_out_amount,
            ddr.winning_number,
            ddr.color as winning_color,
            ddr.timestamp as draw_time,
            CASE
                WHEN ddr.timestamp IS NOT NULL AND ddr.timestamp <= NOW() THEN 'completed'
                WHEN bs.draw_number <= (SELECT MAX(draw_number) FROM detailed_draw_results) THEN 'completed'
                ELSE 'pending'
            END as draw_status
        FROM betting_slips bs
        LEFT JOIN transactions t ON bs.transaction_id = t.transaction_id
        LEFT JOIN detailed_draw_results ddr ON bs.draw_number = ddr.draw_number
        WHERE t.user_id = ?
        ORDER BY bs.created_at DESC
        LIMIT 10
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $slips = [];
    while ($row = $result->fetch_assoc()) {
        $slips[] = $row;
    }

    return [
        'status' => 'success',
        'data' => $slips,
        'timestamp' => date("Y-m-d H:i:s"),
        'timezone' => 'GMT-4 (Georgetown)'
    ];
}

/**
 * Get current user balance
 */
function getUserBalance($conn, $userId) {
    $stmt = $conn->prepare("SELECT cash_balance, updated_at FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        return [
            'status' => 'success',
            'data' => [
                'balance' => $user['cash_balance'],
                'last_updated' => $user['updated_at']
            ],
            'timestamp' => date("Y-m-d H:i:s"),
            'timezone' => 'GMT-4 (Georgetown)'
        ];
    } else {
        return [
            'status' => 'error',
            'message' => 'User not found',
            'timestamp' => date("Y-m-d H:i:s")
        ];
    }
}

/**
 * Get recent transactions with real-time updates
 */
function getRecentTransactions($conn, $userId) {
    $stmt = $conn->prepare("
        SELECT
            transaction_id,
            amount,
            balance_after,
            transaction_type,
            reference_id,
            description,
            created_at
        FROM transactions
        WHERE user_id = ?
        ORDER BY created_at DESC, transaction_id DESC
        LIMIT 20
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }

    return [
        'status' => 'success',
        'data' => $transactions,
        'timestamp' => date("Y-m-d H:i:s"),
        'timezone' => 'GMT-4 (Georgetown)'
    ];
}

/**
 * Get cashout notifications for recent wins
 */
function getCashoutNotifications($conn, $userId) {
    // Get recent cashouts (last 24 hours)
    $stmt = $conn->prepare("
        SELECT
            t.transaction_id,
            t.amount,
            t.balance_after,
            t.reference_id as slip_number,
            t.description,
            t.created_at,
            bs.draw_number,
            bs.winning_number
        FROM transactions t
        LEFT JOIN betting_slips bs ON t.reference_id = bs.slip_number
        WHERE t.user_id = ?
        AND t.transaction_type = 'win'
        AND t.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY t.created_at DESC
        LIMIT 10
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'type' => 'cashout',
            'transaction_id' => $row['transaction_id'],
            'amount' => $row['amount'],
            'balance_after' => $row['balance_after'],
            'slip_number' => $row['slip_number'],
            'draw_number' => $row['draw_number'],
            'winning_number' => $row['winning_number'],
            'description' => $row['description'],
            'created_at' => $row['created_at'],
            'message' => "🎉 Congratulations! You won $" . number_format($row['amount'], 2) . " from betting slip #{$row['slip_number']}"
        ];
    }

    return [
        'status' => 'success',
        'data' => $notifications,
        'timestamp' => date("Y-m-d H:i:s"),
        'timezone' => 'GMT-4 (Georgetown)'
    ];
}

/**
 * Get full update including balance, transactions, and notifications
 */
function getFullUpdate($conn, $userId) {
    $summary = getTransactionSummary($conn, $userId);
    $balance = getUserBalance($conn, $userId);
    $transactions = getRecentTransactions($conn, $userId);
    // POS System: Notifications disabled for cashier interface

    return [
        'status' => 'success',
        'data' => [
            'summary' => $summary['data'] ?? null,
            'balance' => $balance['data'] ?? null,
            'transactions' => $transactions['data'] ?? [],
            'notifications' => [] // POS System: Always return empty notifications
        ],
        'timestamp' => date("Y-m-d H:i:s"),
        'timezone' => 'GMT-4 (Georgetown)'
    ];
}
?>
