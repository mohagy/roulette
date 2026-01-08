<?php
// Finance Department Dashboard
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Check if user has finance department access
$user_role = $_SESSION['role'] ?? '';
$allowed_roles = ['admin', 'finance_manager', 'finance_staff'];

// For now, allow admin access - later implement proper department role checking
if (!in_array($user_role, $allowed_roles) && $user_role !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "roulette";

date_default_timezone_set('America/Guyana');

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
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

// Get monthly revenue trend
$monthly_revenue = [];
$result = $conn->query("
    SELECT 
        DATE_FORMAT(date, '%Y-%m') as month,
        SUM(total_bets) as revenue
    FROM shop_performance 
    WHERE date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(date, '%Y-%m')
    ORDER BY month
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $monthly_revenue[] = $row;
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
    LIMIT 10
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recent_transactions[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Department Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #2d5016 0%, #3e7b27 100%);
            min-height: 100vh;
            font-family: 'Nunito', sans-serif;
        }
        .sidebar {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            min-height: 100vh;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            background: rgba(255,255,255,0.95);
            margin-bottom: 20px;
        }
        .stat-card {
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
        }
        .nav-link {
            color: #5a5c69;
            padding: 12px 20px;
            border-radius: 10px;
            margin: 2px 0;
            transition: all 0.3s ease;
        }
        .nav-link:hover, .nav-link.active {
            background: linear-gradient(135deg, #2d5016 0%, #3e7b27 100%);
            color: white;
        }
        .transaction-item {
            padding: 10px;
            border-left: 3px solid #2d5016;
            margin-bottom: 10px;
            background: #f8f9fc;
            border-radius: 5px;
        }
        .amount-positive { color: #28a745; font-weight: bold; }
        .amount-negative { color: #dc3545; font-weight: bold; }
        .real-time-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #28a745;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar position-fixed">
        <div class="p-4">
            <h4 class="text-success"><i class="fas fa-dollar-sign"></i> Finance Dept</h4>
        </div>
        <nav class="nav flex-column px-3">
            <a class="nav-link active" href="index.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a class="nav-link" href="credit.php">
                <i class="fas fa-credit-card"></i> Credit Management
            </a>
            <a class="nav-link" href="deposits.php">
                <i class="fas fa-piggy-bank"></i> Deposits & Withdrawals
            </a>
            <a class="nav-link" href="expenses.php">
                <i class="fas fa-receipt"></i> Business Expenses
            </a>
            <a class="nav-link" href="performance.php">
                <i class="fas fa-chart-line"></i> Shop Performance
            </a>
            <a class="nav-link" href="accounts.php">
                <i class="fas fa-balance-scale"></i> Accounts P&R
            </a>
            <a class="nav-link" href="reports.php">
                <i class="fas fa-file-alt"></i> Financial Reports
            </a>
            <a class="nav-link" href="budget.php">
                <i class="fas fa-calculator"></i> Budget Management
            </a>
            <hr>
            <a class="nav-link" href="../admin/index.php">
                <i class="fas fa-cog"></i> Admin Panel
            </a>
            <a class="nav-link" href="../logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="text-white">Finance Department Dashboard</h1>
                <p class="text-white-50 mb-0">
                    <span class="real-time-indicator"></span>
                    Financial monitoring | Last updated: <span id="last-updated"><?php echo date('g:i:s A'); ?></span>
                </p>
            </div>
            <div>
                <button class="btn btn-light" onclick="refreshDashboard()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <a href="reports.php?action=generate" class="btn btn-success">
                    <i class="fas fa-file-export"></i> Generate Report
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card stat-card border-left-success">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Today's Revenue</div>
                                <div class="stat-value text-gray-800" id="today-revenue">$<?php echo number_format($stats['total_revenue_today'], 0); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-dollar-sign stat-icon text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card stat-card border-left-danger">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Today's Expenses</div>
                                <div class="stat-value text-gray-800" id="today-expenses">$<?php echo number_format($stats['total_expenses_today'], 0); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-receipt stat-icon text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card stat-card border-left-<?php echo $stats['net_profit_today'] >= 0 ? 'success' : 'warning'; ?>">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs font-weight-bold text-<?php echo $stats['net_profit_today'] >= 0 ? 'success' : 'warning'; ?> text-uppercase mb-1">Net Profit Today</div>
                                <div class="stat-value text-gray-800" id="net-profit">$<?php echo number_format($stats['net_profit_today'], 0); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-chart-line stat-icon text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card stat-card border-left-warning">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Expenses</div>
                                <div class="stat-value text-gray-800" id="pending-expenses"><?php echo $stats['pending_expenses']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-clock stat-icon text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card stat-card border-left-info">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">User Balances</div>
                                <div class="stat-value text-gray-800" id="user-balances">$<?php echo number_format($stats['total_user_balance'], 0); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-users stat-icon text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card stat-card border-left-primary">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Credit Users</div>
                                <div class="stat-value text-gray-800" id="credit-users"><?php echo $stats['credit_users']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-credit-card stat-icon text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts and Transactions -->
        <div class="row">
            <!-- Monthly Revenue Trend -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-chart-area"></i> Monthly Revenue Trend (Last 6 Months)
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="position: relative; height: 300px;">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-exchange-alt"></i> Recent Transactions
                        </h6>
                    </div>
                    <div class="card-body">
                        <div id="recent-transactions" style="max-height: 300px; overflow-y: auto;">
                            <?php if (empty($recent_transactions)): ?>
                                <p class="text-muted text-center">No recent transactions</p>
                            <?php else: ?>
                                <?php foreach ($recent_transactions as $transaction): ?>
                                <div class="transaction-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?php echo htmlspecialchars($transaction['username']); ?></strong>
                                            <br>
                                            <small><?php echo ucfirst(str_replace('_', ' ', $transaction['transaction_type'])); ?></small>
                                            <?php if ($transaction['shop_name']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($transaction['shop_name']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-end">
                                            <span class="amount-<?php echo $transaction['amount'] >= 0 ? 'positive' : 'negative'; ?>">
                                                $<?php echo number_format(abs($transaction['amount']), 2); ?>
                                            </span>
                                            <br>
                                            <small class="text-muted"><?php echo date('M d H:i', strtotime($transaction['created_at'])); ?></small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expense Categories and Quick Actions -->
        <div class="row">
            <!-- Today's Expense Categories -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-chart-pie"></i> Today's Expense Categories
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($expense_categories)): ?>
                            <p class="text-muted text-center">No expenses recorded today</p>
                        <?php else: ?>
                            <div class="chart-container" style="position: relative; height: 250px;">
                                <canvas id="expenseChart"></canvas>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-bolt"></i> Quick Actions
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <a href="expenses.php?action=new" class="btn btn-danger btn-block mb-2">
                                    <i class="fas fa-plus"></i> Add Expense
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="credit.php?action=new" class="btn btn-primary btn-block mb-2">
                                    <i class="fas fa-plus"></i> Manage Credit
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="reports.php?type=daily" class="btn btn-success btn-block mb-2">
                                    <i class="fas fa-file-alt"></i> Daily Report
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="budget.php" class="btn btn-warning btn-block mb-2">
                                    <i class="fas fa-calculator"></i> Budget Review
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Monthly Revenue Chart
        const revenueData = <?php echo json_encode($monthly_revenue); ?>;
        
        if (revenueData.length > 0) {
            const ctx1 = document.getElementById('revenueChart').getContext('2d');
            new Chart(ctx1, {
                type: 'line',
                data: {
                    labels: revenueData.map(item => {
                        const date = new Date(item.month + '-01');
                        return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                    }),
                    datasets: [{
                        label: 'Monthly Revenue',
                        data: revenueData.map(item => parseFloat(item.revenue)),
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toFixed(0);
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Revenue: $' + context.parsed.y.toFixed(2);
                                }
                            }
                        }
                    }
                }
            });
        }

        // Expense Categories Chart
        const expenseData = <?php echo json_encode($expense_categories); ?>;
        
        if (expenseData.length > 0) {
            const ctx2 = document.getElementById('expenseChart').getContext('2d');
            new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: expenseData.map(item => item.category.charAt(0).toUpperCase() + item.category.slice(1)),
                    datasets: [{
                        data: expenseData.map(item => parseFloat(item.total)),
                        backgroundColor: [
                            '#dc3545', '#fd7e14', '#ffc107', '#28a745', '#20c997', '#6f42c1', '#6c757d'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': $' + context.parsed.toFixed(2);
                                }
                            }
                        }
                    }
                }
            });
        }

        // Real-time updates
        function refreshDashboard() {
            fetch('api/finance_dashboard_data.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateStats(data.stats);
                        updateLastUpdated();
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function updateStats(stats) {
            document.getElementById('today-revenue').textContent = '$' + parseInt(stats.total_revenue_today).toLocaleString();
            document.getElementById('today-expenses').textContent = '$' + parseInt(stats.total_expenses_today).toLocaleString();
            document.getElementById('net-profit').textContent = '$' + parseInt(stats.net_profit_today).toLocaleString();
            document.getElementById('pending-expenses').textContent = stats.pending_expenses;
            document.getElementById('user-balances').textContent = '$' + parseInt(stats.total_user_balance).toLocaleString();
            document.getElementById('credit-users').textContent = stats.credit_users;
        }

        function updateLastUpdated() {
            document.getElementById('last-updated').textContent = new Date().toLocaleTimeString();
        }

        // Auto-refresh every 30 seconds
        setInterval(refreshDashboard, 30000);
        
        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            updateLastUpdated();
        });
    </script>
</body>
</html>
