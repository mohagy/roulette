<?php
// Accounting Dashboard API
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
        'total_assets' => 0,
        'total_liabilities' => 0,
        'accounts_payable' => 0,
        'accounts_receivable' => 0,
        'pending_invoices' => 0,
        'overdue_payments' => 0
    ];

    // Total fixed assets value
    $result = $conn->query("SELECT SUM(current_value) as total FROM fixed_assets WHERE status = 'active'");
    if ($result && $result->num_rows > 0) {
        $stats['total_assets'] = $result->fetch_assoc()['total'] ?? 0;
    }

    // Total liabilities
    $result = $conn->query("SELECT SUM(current_balance) as total FROM liabilities WHERE status = 'active'");
    if ($result && $result->num_rows > 0) {
        $stats['total_liabilities'] = $result->fetch_assoc()['total'] ?? 0;
    }

    // Accounts payable balance
    $result = $conn->query("SELECT SUM(balance) as total FROM accounts_payable WHERE status IN ('pending', 'partial')");
    if ($result && $result->num_rows > 0) {
        $stats['accounts_payable'] = $result->fetch_assoc()['total'] ?? 0;
    }

    // Accounts receivable balance
    $result = $conn->query("SELECT SUM(balance) as total FROM accounts_receivable WHERE status IN ('pending', 'partial')");
    if ($result && $result->num_rows > 0) {
        $stats['accounts_receivable'] = $result->fetch_assoc()['total'] ?? 0;
    }

    // Pending invoices
    $result = $conn->query("SELECT COUNT(*) as count FROM accounts_payable WHERE status = 'pending'");
    if ($result && $result->num_rows > 0) {
        $stats['pending_invoices'] = $result->fetch_assoc()['count'];
    }

    // Overdue payments
    $result = $conn->query("SELECT COUNT(*) as count FROM accounts_payable WHERE status IN ('pending', 'partial') AND due_date < CURDATE()");
    if ($result && $result->num_rows > 0) {
        $stats['overdue_payments'] = $result->fetch_assoc()['count'];
    }

    // Get recent journal entries
    $recent_entries = [];
    $result = $conn->query("
        SELECT 
            je.entry_number,
            je.description,
            je.total_debit,
            je.total_credit,
            je.entry_date,
            je.status,
            u.username as created_by
        FROM journal_entries je
        LEFT JOIN users u ON je.created_by = u.user_id
        ORDER BY je.created_at DESC
        LIMIT 15
    ");

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $recent_entries[] = $row;
        }
    }

    // Get aging report data
    $aging_data = [];
    $result = $conn->query("
        SELECT 
            CASE 
                WHEN DATEDIFF(CURDATE(), due_date) <= 0 THEN 'Current'
                WHEN DATEDIFF(CURDATE(), due_date) <= 30 THEN '1-30 Days'
                WHEN DATEDIFF(CURDATE(), due_date) <= 60 THEN '31-60 Days'
                WHEN DATEDIFF(CURDATE(), due_date) <= 90 THEN '61-90 Days'
                ELSE '90+ Days'
            END as age_group,
            COUNT(*) as invoice_count,
            SUM(balance) as total_amount
        FROM accounts_payable 
        WHERE status IN ('pending', 'partial')
        GROUP BY age_group
        ORDER BY 
            CASE age_group
                WHEN 'Current' THEN 1
                WHEN '1-30 Days' THEN 2
                WHEN '31-60 Days' THEN 3
                WHEN '61-90 Days' THEN 4
                ELSE 5
            END
    ");

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $aging_data[] = $row;
        }
    }

    // Get overdue invoices
    $overdue_invoices = [];
    $result = $conn->query("
        SELECT 
            ap.invoice_number,
            ap.amount,
            ap.balance,
            ap.due_date,
            DATEDIFF(CURDATE(), ap.due_date) as days_overdue,
            v.vendor_name
        FROM accounts_payable ap
        JOIN vendors v ON ap.vendor_id = v.vendor_id
        WHERE ap.status IN ('pending', 'partial') 
        AND ap.due_date < CURDATE()
        ORDER BY days_overdue DESC
        LIMIT 10
    ");

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $overdue_invoices[] = $row;
        }
    }

    // Get asset depreciation alerts
    $depreciation_alerts = [];
    $result = $conn->query("
        SELECT 
            fa.asset_name,
            fa.asset_code,
            fa.purchase_cost,
            fa.current_value,
            fa.accumulated_depreciation,
            bs.shop_name,
            ROUND((fa.accumulated_depreciation / fa.purchase_cost) * 100, 1) as depreciation_percentage
        FROM fixed_assets fa
        LEFT JOIN betting_shops bs ON fa.shop_id = bs.shop_id
        WHERE fa.status = 'active'
        AND (fa.accumulated_depreciation / fa.purchase_cost) > 0.8
        ORDER BY depreciation_percentage DESC
        LIMIT 10
    ");

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $depreciation_alerts[] = $row;
        }
    }

    // Get liability payment schedule
    $payment_schedule = [];
    $result = $conn->query("
        SELECT 
            l.creditor_name,
            l.liability_type,
            l.current_balance,
            l.monthly_payment,
            l.maturity_date,
            DATEDIFF(l.maturity_date, CURDATE()) as days_to_maturity
        FROM liabilities l
        WHERE l.status = 'active'
        AND l.maturity_date >= CURDATE()
        ORDER BY l.maturity_date ASC
        LIMIT 10
    ");

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $payment_schedule[] = $row;
        }
    }

    // Get cash flow summary
    $cash_flow = [
        'total_inflows' => 0,
        'total_outflows' => 0,
        'net_cash_flow' => 0
    ];

    // Calculate inflows (revenue from shop performance)
    $result = $conn->query("
        SELECT SUM(total_bets) as inflows 
        FROM shop_performance 
        WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    if ($result && $result->num_rows > 0) {
        $cash_flow['total_inflows'] = $result->fetch_assoc()['inflows'] ?? 0;
    }

    // Calculate outflows (business expenses)
    $result = $conn->query("
        SELECT SUM(amount) as outflows 
        FROM business_expenses 
        WHERE expense_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        AND status = 'approved'
    ");
    if ($result && $result->num_rows > 0) {
        $cash_flow['total_outflows'] = $result->fetch_assoc()['outflows'] ?? 0;
    }

    $cash_flow['net_cash_flow'] = $cash_flow['total_inflows'] - $cash_flow['total_outflows'];

    // Get roulette state for countdown timer
    $roulette_state = [
        'current_draw' => 0,
        'next_draw' => 1,
        'last_draw' => 0,
        'winning_number' => 0,
        'countdown_time' => 180
    ];

    $result = $conn->query("SELECT * FROM roulette_state ORDER BY id DESC LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $state = $result->fetch_assoc();
        $roulette_state = [
            'current_draw' => $state['current_draw_number'] ?? 0,
            'next_draw' => ($state['current_draw_number'] ?? 0) + 1,
            'last_draw' => $state['current_draw_number'] > 0 ? $state['current_draw_number'] - 1 : 0,
            'winning_number' => $state['winning_number'] ?? 0,
            'countdown_time' => $state['countdown_time'] ?? 180
        ];
    }

    // Return data
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'recent_entries' => $recent_entries,
        'aging_data' => $aging_data,
        'overdue_invoices' => $overdue_invoices,
        'depreciation_alerts' => $depreciation_alerts,
        'payment_schedule' => $payment_schedule,
        'cash_flow' => $cash_flow,
        'roulette_state' => $roulette_state,
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
