<?php
/**
 * Get Cashiers Data for Monitoring Dashboard
 * Reads directly from MySQL database (users table with role='cashier')
 */

require_once '../../php/db_connect.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Access-Control-Allow-Origin: *');

$response = [
    'status' => 'success',
    'data' => []
];

try {
    // Get all cashiers with their balances and activity
    $stmt = $pdo->query("
        SELECT 
            u.user_id,
            u.username,
            u.role,
            u.cash_balance,
            u.last_login,
            u.created_at,
            -- Today's transaction counts
            (SELECT COUNT(*) FROM transactions t WHERE t.user_id = u.user_id AND DATE(t.created_at) = CURDATE() AND t.transaction_type = 'bet') as today_bets_count,
            (SELECT COALESCE(SUM(ABS(t.amount)), 0) FROM transactions t WHERE t.user_id = u.user_id AND DATE(t.created_at) = CURDATE() AND t.transaction_type = 'bet') as today_bets_total,
            (SELECT COUNT(*) FROM transactions t WHERE t.user_id = u.user_id AND DATE(t.created_at) = CURDATE() AND t.transaction_type = 'win') as today_wins_count,
            (SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.user_id = u.user_id AND DATE(t.created_at) = CURDATE() AND t.transaction_type = 'win') as today_wins_total,
            -- Last transaction time
            (SELECT MAX(t.created_at) FROM transactions t WHERE t.user_id = u.user_id) as last_transaction_time,
            -- Recent transaction count (last hour)
            (SELECT COUNT(*) FROM transactions t WHERE t.user_id = u.user_id AND t.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)) as recent_transactions_1h
        FROM users u
        WHERE u.role = 'cashier'
        ORDER BY u.cash_balance DESC, u.last_login DESC
    ");
    
    $cashiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add status indicators
    foreach ($cashiers as &$cashier) {
        $balance = floatval($cashier['cash_balance']);
        $lastLogin = $cashier['last_login'];
        $lastTransaction = $cashier['last_transaction_time'];
        
        // Determine status
        $status = 'active';
        $statusClass = 'success';
        $issues = [];
        
        // Check for low balance
        if ($balance < 100) {
            $issues[] = 'Low balance';
            $statusClass = 'warning';
        }
        
        // Check for negative balance
        if ($balance < 0) {
            $issues[] = 'Negative balance';
            $statusClass = 'danger';
            $status = 'critical';
        }
        
        // Check for inactivity (no login in last 24 hours)
        if ($lastLogin) {
            $lastLoginTime = new DateTime($lastLogin);
            $hoursSinceLogin = (time() - $lastLoginTime->getTimestamp()) / 3600;
            if ($hoursSinceLogin > 24) {
                $issues[] = 'Inactive (24h+)';
                if ($status === 'active') {
                    $statusClass = 'warning';
                }
            }
        }
        
        // Check for no recent transactions (last hour) but logged in recently (activity check)
        $recentTrans = intval($cashier['recent_transactions_1h']);
        if ($recentTrans == 0 && $lastLogin && (time() - strtotime($lastLogin)) < 3600) {
            $issues[] = 'No recent activity';
        }
        
        $cashier['status'] = $status;
        $cashier['status_class'] = $statusClass;
        $cashier['issues'] = $issues;
        $cashier['cash_balance'] = floatval($balance);
        $cashier['today_bets_total'] = floatval($cashier['today_bets_total']);
        $cashier['today_wins_total'] = floatval($cashier['today_wins_total']);
    }
    
    $response['data'] = $cashiers;
    
} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
}

echo json_encode($response);

