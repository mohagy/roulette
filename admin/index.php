<?php
// Start session
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Redirect to login page if not logged in or not an admin
    header('Location: ../login.php');
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
    die("Connection failed: " . $conn->connect_error);
}

// Check if required tables exist
$requiredTables = ['users', 'transactions', 'vouchers', 'commission', 'commission_summary', 'settings'];
$missingTables = [];

foreach ($requiredTables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows == 0) {
        $missingTables[] = $table;
    }
}

// If any tables are missing, redirect to the setup script
if (!empty($missingTables)) {
    header("Location: db_setup.php");
    exit;
}

// Get statistics
$stats = [
    'users' => 0,
    'transactions' => 0,
    'vouchers' => 0,
    'total_cash' => 0,
    'total_bets' => 0,
    'total_commission' => 0
];

// Count users
$result = $conn->query("SELECT COUNT(*) as count FROM users");
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $stats['users'] = $row['count'];
}

// Count transactions
$result = $conn->query("SELECT COUNT(*) as count FROM transactions");
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $stats['transactions'] = $row['count'];
}

// Count vouchers
$result = $conn->query("SELECT COUNT(*) as count FROM vouchers");
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $stats['vouchers'] = $row['count'];
}

// Get total cash balance
$result = $conn->query("SELECT SUM(cash_balance) as total FROM users");
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $stats['total_cash'] = $row['total'] ?? 0;
}

// Get total bets
$result = $conn->query("SELECT SUM(bet_amount) as total FROM commission");
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $stats['total_bets'] = $row['total'] ?? 0;
}

// Get total commission
$result = $conn->query("SELECT SUM(commission_amount) as total FROM commission");
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $stats['total_commission'] = $row['total'] ?? 0;
}

// Get recent transactions
$recentTransactions = [];
$result = $conn->query("SELECT t.transaction_id, t.user_id, u.username, t.amount, t.transaction_type, t.created_at
                        FROM transactions t
                        JOIN users u ON t.user_id = u.user_id
                        ORDER BY t.created_at DESC
                        LIMIT 5");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recentTransactions[] = $row;
    }
}

// Get recent users
$recentUsers = [];
$result = $conn->query("SELECT user_id, username, role, cash_balance, created_at
                        FROM users
                        ORDER BY created_at DESC
                        LIMIT 5");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recentUsers[] = $row;
    }
}

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#4e73df">
    <title>Admin Dashboard - Roulette POS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <nav class="top-navbar">
            <div class="navbar-search">
                <input type="text" class="search-input" placeholder="Search...">
            </div>

            <ul class="navbar-nav">
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-bell"></i>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-envelope"></i>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">Dashboard</h1>
                <div class="breadcrumb">
                    <div class="breadcrumb-item"><a href="index.php">Admin</a></div>
                    <div class="breadcrumb-item active">Dashboard</div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row">
                <div class="col-xl-3 col-md-6">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Total Users</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['users']; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Total Cash Balance</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($stats['total_cash'], 2); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Total Bets</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($stats['total_bets'], 2); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-dice fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Total Commission</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($stats['total_commission'], 2); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-percentage fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Row -->
            <div class="row">
                <!-- Area Chart -->
                <div class="col-xl-8 col-lg-7">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Earnings Overview</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-area">
                                <canvas id="earningsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pie Chart -->
                <div class="col-xl-4 col-lg-5">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Transaction Types</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-pie">
                                <canvas id="transactionTypesChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Row -->
            <div class="row">
                <!-- Recent Transactions -->
                <div class="col-lg-6 mb-4">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Recent Transactions</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>User</th>
                                            <th>Amount</th>
                                            <th>Type</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentTransactions as $transaction): ?>
                                        <tr>
                                            <td><?php echo $transaction['transaction_id']; ?></td>
                                            <td><?php echo htmlspecialchars($transaction['username']); ?></td>
                                            <td class="<?php echo $transaction['amount'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                $<?php echo number_format($transaction['amount'], 2); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($transaction['transaction_type']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($transaction['created_at'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <a href="transactions.php" class="btn btn-primary btn-sm">View All Transactions</a>
                        </div>
                    </div>
                </div>

                <!-- Recent Users -->
                <div class="col-lg-6 mb-4">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Recent Users</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Username</th>
                                            <th>Role</th>
                                            <th>Balance</th>
                                            <th>Created</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentUsers as $user): ?>
                                        <tr>
                                            <td><?php echo $user['user_id']; ?></td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['role']); ?></td>
                                            <td>$<?php echo number_format($user['cash_balance'], 2); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <a href="users.php" class="btn btn-primary btn-sm">View All Users</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Function to initialize charts
        function initializeCharts() {
            // Area Chart - Earnings
            var earningsCtx = document.getElementById("earningsChart");
            if (earningsCtx) {
                var myLineChart = new Chart(earningsCtx, {
                    type: 'line',
                    data: {
                        labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
                        datasets: [{
                            label: "Earnings",
                            lineTension: 0.3,
                            backgroundColor: "rgba(78, 115, 223, 0.05)",
                            borderColor: "rgba(78, 115, 223, 1)",
                            pointRadius: 3,
                            pointBackgroundColor: "rgba(78, 115, 223, 1)",
                            pointBorderColor: "rgba(78, 115, 223, 1)",
                            pointHoverRadius: 3,
                            pointHoverBackgroundColor: "rgba(78, 115, 223, 1)",
                            pointHoverBorderColor: "rgba(78, 115, 223, 1)",
                            pointHitRadius: 10,
                            pointBorderWidth: 2,
                            data: [0, 10000, 5000, 15000, 10000, 20000, 15000, 25000, 20000, 30000, 25000, 40000],
                        }],
                    },
                    options: {
                        maintainAspectRatio: false,
                        layout: {
                            padding: {
                                left: 10,
                                right: 25,
                                top: 25,
                                bottom: 0
                            }
                        },
                        scales: {
                            xAxes: [{
                                time: {
                                    unit: 'date'
                                },
                                gridLines: {
                                    display: false,
                                    drawBorder: false
                                },
                                ticks: {
                                    maxTicksLimit: window.innerWidth < 768 ? 4 : 7,
                                    fontColor: "#858796",
                                    fontStyle: "normal"
                                }
                            }],
                            yAxes: [{
                                ticks: {
                                    maxTicksLimit: window.innerWidth < 768 ? 3 : 5,
                                    padding: 10,
                                    fontColor: "#858796",
                                    fontStyle: "normal",
                                    callback: function(value, index, values) {
                                        return '$' + value;
                                    }
                                },
                                gridLines: {
                                    color: "rgb(234, 236, 244)",
                                    zeroLineColor: "rgb(234, 236, 244)",
                                    drawBorder: false,
                                    borderDash: [2],
                                    zeroLineBorderDash: [2]
                                }
                            }],
                        },
                        legend: {
                            display: false
                        },
                        tooltips: {
                            backgroundColor: "rgb(255,255,255)",
                            bodyFontColor: "#858796",
                            titleMarginBottom: 10,
                            titleFontColor: '#6e707e',
                            titleFontSize: 14,
                            borderColor: '#dddfeb',
                            borderWidth: 1,
                            xPadding: 15,
                            yPadding: 15,
                            displayColors: false,
                            intersect: false,
                            mode: 'index',
                            caretPadding: 10,
                            callbacks: {
                                label: function(tooltipItem, chart) {
                                    var datasetLabel = chart.datasets[tooltipItem.datasetIndex].label || '';
                                    return datasetLabel + ': $' + tooltipItem.yLabel;
                                }
                            }
                        },
                        responsive: true
                    }
                });
            }

            // Pie Chart - Transaction Types
            var transactionCtx = document.getElementById("transactionTypesChart");
            if (transactionCtx) {
                var myPieChart = new Chart(transactionCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ["Bets", "Wins", "Vouchers", "Admin", "Refunds"],
                        datasets: [{
                            data: [55, 30, 10, 3, 2],
                            backgroundColor: ['#e74a3b', '#1cc88a', '#4e73df', '#f6c23e', '#36b9cc'],
                            hoverBackgroundColor: ['#be3c2d', '#17a673', '#2e59d9', '#dda20a', '#2c9faf'],
                            hoverBorderColor: "rgba(234, 236, 244, 1)",
                        }],
                    },
                    options: {
                        maintainAspectRatio: false,
                        tooltips: {
                            backgroundColor: "rgb(255,255,255)",
                            bodyFontColor: "#858796",
                            borderColor: '#dddfeb',
                            borderWidth: 1,
                            xPadding: 15,
                            yPadding: 15,
                            displayColors: false,
                            caretPadding: 10,
                        },
                        legend: {
                            display: true,
                            position: window.innerWidth < 768 ? 'right' : 'bottom',
                            labels: {
                                fontColor: "#858796",
                                fontSize: window.innerWidth < 768 ? 10 : 12
                            }
                        },
                        cutoutPercentage: 70,
                        responsive: true
                    },
                });
            }
        }

        // Initialize charts on page load
        document.addEventListener('DOMContentLoaded', initializeCharts);

        // Redraw charts on window resize for better responsiveness
        let chartResizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(chartResizeTimer);
            chartResizeTimer = setTimeout(function() {
                // Destroy and reinitialize charts
                Chart.helpers.each(Chart.instances, function(instance) {
                    instance.destroy();
                });
                initializeCharts();
            }, 300);
        });
    </script>
</body>
</html>
