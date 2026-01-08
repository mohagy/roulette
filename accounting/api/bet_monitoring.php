<?php
// Bet Monitoring API for Real-time Warning System
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "roulette";

date_default_timezone_set('America/Guyana');

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Get current draw number from roulette_state
    $current_draw = 0;
    $result = $conn->query("SELECT current_draw_number FROM roulette_state ORDER BY id DESC LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $current_draw = $result->fetch_assoc()['current_draw_number'] ?? 0;
    }

    // Calculate straight up bet totals for current draw
    $straight_bet_data = [
        'total_straight_bets' => 0,
        'total_potential_payout' => 0,
        'bet_count' => 0,
        'warning_threshold' => 1600,
        'is_warning' => false,
        'warning_level' => 'none', // none, approaching, exceeded
        'individual_bets' => []
    ];

    // Query to get all straight up bets for current draw
    $query = "
        SELECT 
            bs.slip_number,
            bs.customer_name,
            bd.bet_numbers,
            bd.bet_amount,
            bd.potential_payout,
            bs.created_at
        FROM betting_slips bs
        JOIN bet_details bd ON bs.slip_id = bd.slip_id
        WHERE bs.draw_number = ? 
        AND bs.status = 'active'
        AND bd.bet_type = 'straight_up'
        ORDER BY bs.created_at DESC
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $current_draw);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $straight_bet_data['total_straight_bets'] += $row['bet_amount'];
            $straight_bet_data['total_potential_payout'] += $row['potential_payout'];
            $straight_bet_data['bet_count']++;
            
            $straight_bet_data['individual_bets'][] = [
                'slip_number' => $row['slip_number'],
                'customer_name' => $row['customer_name'],
                'number' => $row['bet_numbers'],
                'amount' => $row['bet_amount'],
                'potential_payout' => $row['potential_payout'],
                'created_at' => $row['created_at']
            ];
        }
    }

    // Determine warning level
    $threshold = $straight_bet_data['warning_threshold'];
    $total = $straight_bet_data['total_straight_bets'];

    if ($total >= $threshold) {
        $straight_bet_data['is_warning'] = true;
        $straight_bet_data['warning_level'] = 'exceeded';
    } elseif ($total >= ($threshold * 0.8)) { // 80% of threshold
        $straight_bet_data['is_warning'] = true;
        $straight_bet_data['warning_level'] = 'approaching';
    }

    // Get cash drawer information
    $cash_drawer = [
        'current_balance' => 0,
        'opening_balance' => 0,
        'total_payouts' => 0,
        'is_sufficient' => true,
        'shortage_amount' => 0
    ];

    $result = $conn->query("
        SELECT 
            current_balance,
            opening_balance,
            total_payouts
        FROM cash_drawer 
        WHERE status = 'open' 
        ORDER BY shift_start DESC 
        LIMIT 1
    ");

    if ($result && $result->num_rows > 0) {
        $drawer = $result->fetch_assoc();
        $cash_drawer['current_balance'] = $drawer['current_balance'];
        $cash_drawer['opening_balance'] = $drawer['opening_balance'];
        $cash_drawer['total_payouts'] = $drawer['total_payouts'];

        // Check if current balance can cover potential straight bet payouts
        $potential_payout = $straight_bet_data['total_potential_payout'];
        if ($cash_drawer['current_balance'] < $potential_payout) {
            $cash_drawer['is_sufficient'] = false;
            $cash_drawer['shortage_amount'] = $potential_payout - $cash_drawer['current_balance'];
        }
    }

    // Get recent high-value straight bets (last 10)
    $recent_high_bets = [];
    $result = $conn->query("
        SELECT 
            bs.slip_number,
            bs.customer_name,
            bd.bet_numbers,
            bd.bet_amount,
            bd.potential_payout,
            bs.created_at
        FROM betting_slips bs
        JOIN bet_details bd ON bs.slip_id = bd.slip_id
        WHERE bs.draw_number = $current_draw
        AND bs.status = 'active'
        AND bd.bet_type = 'straight_up'
        AND bd.bet_amount >= 50
        ORDER BY bd.bet_amount DESC, bs.created_at DESC
        LIMIT 10
    ");

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $recent_high_bets[] = [
                'slip_number' => $row['slip_number'],
                'customer_name' => $row['customer_name'],
                'number' => $row['bet_numbers'],
                'amount' => $row['bet_amount'],
                'potential_payout' => $row['potential_payout'],
                'time_ago' => time() - strtotime($row['created_at'])
            ];
        }
    }

    // Calculate risk metrics
    $risk_metrics = [
        'risk_percentage' => ($total / $threshold) * 100,
        'payout_to_cash_ratio' => $cash_drawer['current_balance'] > 0 ? 
            ($straight_bet_data['total_potential_payout'] / $cash_drawer['current_balance']) * 100 : 0,
        'average_bet_size' => $straight_bet_data['bet_count'] > 0 ? 
            $straight_bet_data['total_straight_bets'] / $straight_bet_data['bet_count'] : 0
    ];

    // Return comprehensive data
    echo json_encode([
        'success' => true,
        'current_draw' => $current_draw,
        'straight_bet_data' => $straight_bet_data,
        'cash_drawer' => $cash_drawer,
        'recent_high_bets' => $recent_high_bets,
        'risk_metrics' => $risk_metrics,
        'timestamp' => time(),
        'formatted_time' => date('g:i:s A')
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
