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
$requiredTables = ['users', 'commission', 'commission_summary'];
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

// Check if commission table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'commission'");
if ($tableCheck->num_rows == 0) {
    // Create commission table
    $createTable = "CREATE TABLE IF NOT EXISTS commission (
        commission_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        bet_amount DECIMAL(10,2) NOT NULL,
        commission_amount DECIMAL(10,2) NOT NULL,
        date_created DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id)
    )";

    if (!$conn->query($createTable)) {
        die("Error creating commission table: " . $conn->error);
    }
}

// Check if commission_summary table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'commission_summary'");
if ($tableCheck->num_rows == 0) {
    // Create commission_summary table
    $createTable = "CREATE TABLE IF NOT EXISTS commission_summary (
        summary_id INT AUTO_INCREMENT PRIMARY KEY,
        date_created DATE NOT NULL UNIQUE,
        total_bets DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        total_commission DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    if (!$conn->query($createTable)) {
        die("Error creating commission_summary table: " . $conn->error);
    }
}

// Process form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Add manual commission
        if ($_POST['action'] === 'add_commission' && isset($_POST['user_id']) && isset($_POST['bet_amount'])) {
            $userId = intval($_POST['user_id']);
            $betAmount = floatval($_POST['bet_amount']);
            $today = date('Y-m-d');

            // Validate input
            if ($betAmount <= 0) {
                $message = "Invalid bet amount.";
                $messageType = 'danger';
            } else {
                // Calculate commission (4%)
                $commissionAmount = $betAmount * 0.04;

                // Start transaction
                $conn->begin_transaction();

                try {
                    // Insert commission record
                    $stmt = $conn->prepare("INSERT INTO commission (user_id, bet_amount, commission_amount, date_created) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("idds", $userId, $betAmount, $commissionAmount, $today);

                    if (!$stmt->execute()) {
                        throw new Exception("Failed to insert commission record: " . $stmt->error);
                    }

                    // Check if summary exists for today
                    $stmt = $conn->prepare("SELECT summary_id FROM commission_summary WHERE date_created = ?");
                    $stmt->bind_param("s", $today);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows === 0) {
                        // Create new summary
                        $stmt = $conn->prepare("INSERT INTO commission_summary (date_created, total_bets, total_commission) VALUES (?, ?, ?)");
                        $stmt->bind_param("sdd", $today, $betAmount, $commissionAmount);

                        if (!$stmt->execute()) {
                            throw new Exception("Failed to create commission summary: " . $stmt->error);
                        }
                    } else {
                        // Update existing summary
                        $stmt = $conn->prepare("UPDATE commission_summary SET total_bets = total_bets + ?, total_commission = total_commission + ? WHERE date_created = ?");
                        $stmt->bind_param("dds", $betAmount, $commissionAmount, $today);

                        if (!$stmt->execute()) {
                            throw new Exception("Failed to update commission summary: " . $stmt->error);
                        }
                    }

                    // Commit transaction
                    $conn->commit();

                    $message = "Commission added successfully.";
                    $messageType = 'success';
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $conn->rollback();
                    $message = "Error: " . $e->getMessage();
                    $messageType = 'danger';
                }
            }
        }
    }
}

// Get all users
$users = [];
$result = $conn->query("SELECT user_id, username, role FROM users ORDER BY username");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

// Get commission summary
$commissionSummary = [];
$result = $conn->query("SELECT date_created, total_bets, total_commission
                        FROM commission_summary
                        ORDER BY date_created DESC
                        LIMIT 30");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $commissionSummary[] = $row;
    }
}

// Get commission by user
$commissionByUser = [];
$result = $conn->query("SELECT u.user_id, u.username, SUM(c.bet_amount) as total_bets, SUM(c.commission_amount) as total_commission
                        FROM commission c
                        JOIN users u ON c.user_id = u.user_id
                        GROUP BY c.user_id
                        ORDER BY total_commission DESC");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $commissionByUser[] = $row;
    }
}

// Get recent commission entries
$recentCommission = [];
$result = $conn->query("SELECT c.commission_id, c.user_id, u.username, c.bet_amount, c.commission_amount, c.date_created, c.created_at
                        FROM commission c
                        JOIN users u ON c.user_id = u.user_id
                        ORDER BY c.created_at DESC
                        LIMIT 20");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recentCommission[] = $row;
    }
}

// Calculate totals
$totalBets = 0;
$totalCommission = 0;
foreach ($commissionSummary as $summary) {
    $totalBets += $summary['total_bets'];
    $totalCommission += $summary['total_commission'];
}

// Close connection
$conn->close();

// Prepare data for charts
$chartLabels = [];
$chartBets = [];
$chartCommission = [];

foreach (array_reverse($commissionSummary) as $summary) {
    $chartLabels[] = date('M d', strtotime($summary['date_created']));
    $chartBets[] = $summary['total_bets'];
    $chartCommission[] = $summary['total_commission'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commission Tracking - Roulette POS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Additional styles for this page */
        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th, .table td {
            padding: 0.75rem;
            vertical-align: middle;
            border-top: 1px solid #e3e6f0;
        }

        .table thead th {
            vertical-align: bottom;
            border-bottom: 2px solid #e3e6f0;
            background-color: #f8f9fc;
            color: #4e73df;
            font-weight: 700;
        }

        .table tbody tr:hover {
            background-color: #f8f9fc;
        }

        .btn {
            display: inline-block;
            font-weight: 400;
            color: #858796;
            text-align: center;
            vertical-align: middle;
            user-select: none;
            background-color: transparent;
            border: 1px solid transparent;
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            line-height: 1.5;
            border-radius: 0.25rem;
            transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            cursor: pointer;
        }

        .btn-primary {
            color: #fff;
            background-color: #4e73df;
            border-color: #4e73df;
        }

        .btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2653d4;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-control {
            display: block;
            width: 100%;
            height: calc(1.5em + 0.75rem + 2px);
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            font-weight: 400;
            line-height: 1.5;
            color: #6e707e;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid #d1d3e2;
            border-radius: 0.25rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .form-control:focus {
            color: #6e707e;
            background-color: #fff;
            border-color: #bac8f3;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }

        .alert {
            position: relative;
            padding: 0.75rem 1.25rem;
            margin-bottom: 1rem;
            border: 1px solid transparent;
            border-radius: 0.25rem;
        }

        .alert-success {
            color: #0f6848;
            background-color: #d1e7dd;
            border-color: #badbcc;
        }

        .alert-danger {
            color: #842029;
            background-color: #f8d7da;
            border-color: #f5c2c7;
        }

        .stats-card {
            background-color: #fff;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            margin-bottom: 1.5rem;
            padding: 1.25rem;
            text-align: center;
        }

        .stats-card h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .stats-card p {
            color: #858796;
            margin-bottom: 0;
        }

        .stats-card.primary {
            border-left: 0.25rem solid #4e73df;
        }

        .stats-card.success {
            border-left: 0.25rem solid #1cc88a;
        }

        .stats-card.warning {
            border-left: 0.25rem solid #f6c23e;
        }

        .stats-card.info {
            border-left: 0.25rem solid #36b9cc;
        }

        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <nav class="top-navbar">
            <div class="navbar-search">
                <input type="text" class="search-input" placeholder="Search commission...">
            </div>

            <ul class="navbar-nav">
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
                <h1 class="page-title">Commission Tracking</h1>
                <div class="breadcrumb">
                    <div class="breadcrumb-item"><a href="index.php">Admin</a></div>
                    <div class="breadcrumb-item active">Commission</div>
                </div>
            </div>

            <!-- Alert Message -->
            <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="row">
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card primary">
                        <h3>$<?php echo number_format($totalBets, 2); ?></h3>
                        <p>Total Bets</p>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card success">
                        <h3>$<?php echo number_format($totalCommission, 2); ?></h3>
                        <p>Total Commission</p>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card warning">
                        <h3>4%</h3>
                        <p>Commission Rate</p>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card info">
                        <h3><?php echo count($commissionByUser); ?></h3>
                        <p>Active Cashiers</p>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-8 col-lg-7">
                    <!-- Commission Chart -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Commission History</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="commissionChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4 col-lg-5">
                    <!-- Add Commission Form -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Add Manual Commission</h6>
                        </div>
                        <div class="card-body">
                            <form method="post" action="">
                                <input type="hidden" name="action" value="add_commission">
                                <div class="form-group">
                                    <label for="user_id">Select Cashier:</label>
                                    <select class="form-control" id="user_id" name="user_id" required>
                                        <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['user_id']; ?>">
                                            <?php echo htmlspecialchars($user['username']); ?>
                                            (<?php echo htmlspecialchars($user['role']); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="bet_amount">Bet Amount:</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">$</span>
                                        </div>
                                        <input type="number" class="form-control" id="bet_amount" name="bet_amount" min="1" step="0.01" required>
                                    </div>
                                    <small class="text-muted">Commission will be calculated at 4%.</small>
                                </div>
                                <div class="form-group">
                                    <label>Commission (4%):</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">$</span>
                                        </div>
                                        <input type="text" class="form-control" id="commission_preview" readonly>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">Add Commission</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-6">
                    <!-- Commission by User -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Commission by Cashier</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Cashier</th>
                                            <th>Total Bets</th>
                                            <th>Commission</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($commissionByUser as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td>$<?php echo number_format($user['total_bets'], 2); ?></td>
                                            <td>$<?php echo number_format($user['total_commission'], 2); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6">
                    <!-- Recent Commission Entries -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Recent Commission Entries</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Cashier</th>
                                            <th>Bet Amount</th>
                                            <th>Commission</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentCommission as $commission): ?>
                                        <tr>
                                            <td><?php echo $commission['commission_id']; ?></td>
                                            <td><?php echo htmlspecialchars($commission['username']); ?></td>
                                            <td>$<?php echo number_format($commission['bet_amount'], 2); ?></td>
                                            <td>$<?php echo number_format($commission['commission_amount'], 2); ?></td>
                                            <td><?php echo date('M d, Y H:i', strtotime($commission['created_at'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Calculate commission preview
        document.getElementById('bet_amount').addEventListener('input', function() {
            const betAmount = parseFloat(this.value) || 0;
            const commission = betAmount * 0.04;
            document.getElementById('commission_preview').value = commission.toFixed(2);
        });

        // Initialize chart
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('commissionChart').getContext('2d');

            const chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($chartLabels); ?>,
                    datasets: [
                        {
                            label: 'Total Bets',
                            data: <?php echo json_encode($chartBets); ?>,
                            backgroundColor: 'rgba(78, 115, 223, 0.5)',
                            borderColor: 'rgba(78, 115, 223, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Commission',
                            data: <?php echo json_encode($chartCommission); ?>,
                            backgroundColor: 'rgba(28, 200, 138, 0.5)',
                            borderColor: 'rgba(28, 200, 138, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value;
                                }
                            }
                        }
                    },
                    tooltips: {
                        callbacks: {
                            label: function(tooltipItem, data) {
                                return data.datasets[tooltipItem.datasetIndex].label + ': $' + tooltipItem.yLabel.toFixed(2);
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>
