<?php
// Finance Dashboard API
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

    // Get dashboard statistics
    $stats = [
        'total_revenue_today' => 0,
        'total_expenses_today' => 0,
        'net_profit_today' => 0,
        'pending_expenses' => 0,
        'total_user_balance' => 0,
        'credit_users' => 0
    ];

    // Today's revenue
    $result = $conn->query("
        SELECT SUM(total_bets) as revenue 
        FROM shop_performance 
        WHERE date = CURDATE()
    ");
    if ($result && $result->num_rows > 0) {
        $stats['total_revenue_today'] = $result->fetch_assoc()['revenue'] ?? 0;
    }

    // Today's expenses
    $result = $conn->query("
        SELECT SUM(amount) as expenses 
        FROM business_expenses 
        WHERE expense_date = CURDATE() AND status = 'approved'
    ");
    if ($result && $result->num_rows > 0) {
        $stats['total_expenses_today'] = $result->fetch_assoc()['expenses'] ?? 0;
    }

    // Calculate net profit
    $stats['net_profit_today'] = $stats['total_revenue_today'] - $stats['total_expenses_today'];

    // Pending expenses
    $result = $conn->query("SELECT COUNT(*) as count FROM business_expenses WHERE status = 'pending'");
    if ($result && $result->num_rows > 0) {
        $stats['pending_expenses'] = $result->fetch_assoc()['count'];
    }

    // Total user balance
    $result = $conn->query("SELECT SUM(cash_balance) as total FROM users WHERE cash_balance > 0");
    if ($result && $result->num_rows > 0) {
        $stats['total_user_balance'] = $result->fetch_assoc()['total'] ?? 0;
    }

    // Users with credit
    $result = $conn->query("SELECT COUNT(*) as count FROM user_credit WHERE status = 'active' AND credit_limit > 0");
    if ($result && $result->num_rows > 0) {
        $stats['credit_users'] = $result->fetch_assoc()['count'];
    }

    // Get recent transactions
    $recent_transactions = [];
    $result = $conn->query("
        SELECT 
            t.transaction_id,
            t.transaction_type,
            t.amount,
            t.created_at,
            u.username,
            bs.shop_name
        FROM transactions t
        JOIN users u ON t.user_id = u.user_id
        LEFT JOIN betting_shops bs ON u.shop_id = bs.shop_id
        ORDER BY t.created_at DESC
        LIMIT 15
    ");

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $recent_transactions[] = $row;
        }
    }

    // Get expense categories for today
    $expense_categories = [];
    $result = $conn->query("
        SELECT 
            category,
            SUM(amount) as total
        FROM business_expenses 
        WHERE expense_date = CURDATE() AND status = 'approved'
        GROUP BY category
        ORDER BY total DESC
    ");

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $expense_categories[] = $row;
        }
    }

    // Get shop financial performance
    $shop_performance = [];
    $result = $conn->query("
        SELECT 
            bs.shop_name,
            bs.shop_code,
            COALESCE(sp.total_bets, 0) as today_revenue,
            COALESCE(sp.total_commission, 0) as today_commission,
            COALESCE(expenses.total_expenses, 0) as today_expenses
        FROM betting_shops bs
        LEFT JOIN shop_performance sp ON bs.shop_id = sp.shop_id AND sp.date = CURDATE()
        LEFT JOIN (
            SELECT shop_id, SUM(amount) as total_expenses
            FROM business_expenses 
            WHERE expense_date = CURDATE() AND status = 'approved'
            GROUP BY shop_id
        ) expenses ON bs.shop_id = expenses.shop_id
        WHERE bs.status = 'active'
        ORDER BY today_revenue DESC
        LIMIT 10
    ");

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $row['net_profit'] = $row['today_revenue'] - $row['today_expenses'];
            $shop_performance[] = $row;
        }
    }

    // Get pending expense approvals
    $pending_expenses = [];
    $result = $conn->query("
        SELECT 
            be.expense_id,
            be.description,
            be.amount,
            be.category,
            be.expense_date,
            bs.shop_name,
            u.username as created_by
        FROM business_expenses be
        LEFT JOIN betting_shops bs ON be.shop_id = bs.shop_id
        LEFT JOIN users u ON be.created_by = u.user_id
        WHERE be.status = 'pending'
        ORDER BY be.expense_date DESC
        LIMIT 10
    ");

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $pending_expenses[] = $row;
        }
    }

    // Get credit alerts (users near credit limit)
    $credit_alerts = [];
    $result = $conn->query("
        SELECT 
            u.username,
            uc.credit_limit,
            uc.current_balance,
            uc.available_credit,
            bs.shop_name
        FROM user_credit uc
        JOIN users u ON uc.user_id = u.user_id
        LEFT JOIN betting_shops bs ON u.shop_id = bs.shop_id
        WHERE uc.status = 'active' 
        AND uc.available_credit <= (uc.credit_limit * 0.1)
        AND uc.credit_limit > 0
        ORDER BY (uc.available_credit / uc.credit_limit) ASC
        LIMIT 10
    ");

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $credit_alerts[] = $row;
        }
    }

    // Return data
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'recent_transactions' => $recent_transactions,
        'expense_categories' => $expense_categories,
        'shop_performance' => $shop_performance,
        'pending_expenses' => $pending_expenses,
        'credit_alerts' => $credit_alerts,
        'timestamp' => time()
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
