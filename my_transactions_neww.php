<?php
// Basic My Transactions Page with Real-time Updates
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "roulette";

// Set timezone to Georgetown, Guyana (GMT-4)
date_default_timezone_set('America/Guyana');

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$userId = $_SESSION['user_id'];

// Get user info
$stmt = $conn->prepare("SELECT username, role, cash_balance FROM users WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get current draw number
$stmt = $conn->prepare("SELECT current_draw_number FROM roulette_analytics WHERE id = 1");
$stmt->execute();
$result = $stmt->get_result();
$currentDraw = $result->num_rows > 0 ? $result->fetch_assoc()['current_draw_number'] : 1;

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Transactions - Roulette</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container {
            margin-top: 20px;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            background: rgba(255,255,255,0.95);
        }
        .header-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .balance-amount {
            font-size: 2rem;
            font-weight: bold;
            color: #28a745;
        }
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #28a745;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        .table th {
            background: #f8f9fa;
            border: none;
            font-weight: 600;
        }
        .badge-win { background: #28a745; }
        .badge-loss { background: #dc3545; }
        .badge-pending { background: #6c757d; }
        .badge-active { background: #007bff; }
        .refresh-btn {
            transition: all 0.3s ease;
        }
        .refresh-btn:hover {
            transform: rotate(180deg);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark mb-4" style="background: rgba(0,0,0,0.1); backdrop-filter: blur(10px); border-radius: 15px;">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-dice"></i> Roulette
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home"></i> Game
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="my_transactions_new.php">
                            <i class="fas fa-history"></i> My Transactions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="redeem_voucher.php">
                            <i class="fas fa-ticket-alt"></i> Redeem Voucher
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="commission.php">
                            <i class="fas fa-percentage"></i> Commission
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Header -->
        <div class="card header-card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2><i class="fas fa-dice"></i> My Transactions</h2>
                        <p class="mb-0">Welcome, <?php echo htmlspecialchars($user['username']); ?> (<?php echo htmlspecialchars($user['role']); ?>)</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="balance-amount" id="balance">$<?php echo number_format($user['cash_balance'], 2); ?></div>
                        <small>Current Balance</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Bar -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <span class="status-indicator"></span>
                        <strong>Real-time Updates Active</strong>
                        <small class="text-muted">| Last updated: <span id="last-updated">Just now</span></small>
                    </div>
                    <div class="col-md-6 text-end">
                        <span class="badge bg-info">Current Draw: <span id="current-draw"><?php echo $currentDraw; ?></span></span>
                        <button class="btn btn-outline-primary btn-sm ms-2 refresh-btn" onclick="forceRefresh()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Betting Slips -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-receipt"></i> Recent Betting Slips</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Slip #</th>
                                <th>Date</th>
                                <th>Draw #</th>
                                <th>Stake</th>
                                <th>Potential Win</th>
                                <th>Winning #</th>
                                <th>Status</th>
                                <th>Actual Win</th>
                            </tr>
                        </thead>
                        <tbody id="slips-table">
                            <tr>
                                <td colspan="8" class="text-center">
                                    <i class="fas fa-spinner fa-spin"></i> Loading...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="card mt-4">
            <div class="card-header">
                <h5><i class="fas fa-exchange-alt"></i> Recent Transactions</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Balance After</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody id="transactions-table">
                            <tr>
                                <td colspan="5" class="text-center">
                                    <i class="fas fa-spinner fa-spin"></i> Loading...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Real-time update system
        let updateInterval;
        let lastUpdate = 0;

        // Start real-time updates
        function startUpdates() {
            loadData();
            updateInterval = setInterval(loadData, 3000); // Update every 3 seconds
            console.log('Real-time updates started');
        }

        // Load data via AJAX
        function loadData() {
            fetch('api/get_basic_data.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateBalance(data.balance);
                        updateCurrentDraw(data.current_draw);
                        updateSlipsTable(data.slips);
                        updateTransactionsTable(data.transactions);
                        updateLastUpdated();
                    }
                })
                .catch(error => {
                    console.error('Error loading data:', error);
                });
        }

        // Update balance
        function updateBalance(balance) {
            document.getElementById('balance').textContent = '$' + parseFloat(balance).toFixed(2);
        }

        // Update current draw
        function updateCurrentDraw(draw) {
            document.getElementById('current-draw').textContent = draw;
        }

        // Update slips table
        function updateSlipsTable(slips) {
            const tbody = document.getElementById('slips-table');
            if (slips.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No betting slips found</td></tr>';
                return;
            }

            tbody.innerHTML = slips.map(slip => `
                <tr>
                    <td>${slip.slip_number}</td>
                    <td>${slip.created_at}</td>
                    <td>${slip.draw_number}</td>
                    <td>$${parseFloat(slip.total_stake).toFixed(2)}</td>
                    <td>$${parseFloat(slip.potential_payout).toFixed(2)}</td>
                    <td>${slip.winning_number ? `<span class="badge bg-${slip.winning_color === 'red' ? 'danger' : slip.winning_color === 'black' ? 'dark' : 'success'}">${slip.winning_number}</span>` : '<span class="badge bg-secondary">-</span>'}</td>
                    <td><span class="badge badge-${slip.status}">${slip.status.toUpperCase()}</span></td>
                    <td class="${slip.actual_win > 0 ? 'text-success fw-bold' : 'text-muted'}">$${parseFloat(slip.actual_win || 0).toFixed(2)}</td>
                </tr>
            `).join('');
        }

        // Update transactions table
        function updateTransactionsTable(transactions) {
            const tbody = document.getElementById('transactions-table');
            if (transactions.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No transactions found</td></tr>';
                return;
            }

            tbody.innerHTML = transactions.map(tx => `
                <tr>
                    <td>${tx.transaction_id}</td>
                    <td><span class="badge bg-${tx.transaction_type === 'bet' ? 'danger' : tx.transaction_type === 'win' ? 'success' : 'info'}">${tx.transaction_type.toUpperCase()}</span></td>
                    <td class="${tx.amount >= 0 ? 'text-success' : 'text-danger'} fw-bold">$${parseFloat(tx.amount).toFixed(2)}</td>
                    <td>$${parseFloat(tx.balance_after).toFixed(2)}</td>
                    <td>${tx.created_at}</td>
                </tr>
            `).join('');
        }

        // Update last updated time
        function updateLastUpdated() {
            document.getElementById('last-updated').textContent = new Date().toLocaleTimeString();
        }

        // Force refresh
        function forceRefresh() {
            const btn = document.querySelector('.refresh-btn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
            loadData();
            setTimeout(() => {
                btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
            }, 1000);
        }

        // Start when page loads
        document.addEventListener('DOMContentLoaded', startUpdates);

        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            if (updateInterval) clearInterval(updateInterval);
        });
    </script>
</body>
</html>
