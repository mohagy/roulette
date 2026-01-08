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
$requiredTables = ['users', 'transactions'];
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

// Set default filter values
$filterUser = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$filterType = isset($_GET['type']) ? $_GET['type'] : '';
$filterDateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$filterDateTo = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Build query with filters
$query = "SELECT t.transaction_id, t.user_id, u.username, t.amount, t.balance_after, t.transaction_type, t.reference_id, t.description, t.created_at
          FROM transactions t
          JOIN users u ON t.user_id = u.user_id
          WHERE 1=1";

$params = [];
$types = "";

if ($filterUser > 0) {
    $query .= " AND t.user_id = ?";
    $params[] = $filterUser;
    $types .= "i";
}

if (!empty($filterType)) {
    $query .= " AND t.transaction_type = ?";
    $params[] = $filterType;
    $types .= "s";
}

if (!empty($filterDateFrom)) {
    $query .= " AND DATE(t.created_at) >= ?";
    $params[] = $filterDateFrom;
    $types .= "s";
}

if (!empty($filterDateTo)) {
    $query .= " AND DATE(t.created_at) <= ?";
    $params[] = $filterDateTo;
    $types .= "s";
}

$query .= " ORDER BY t.created_at DESC LIMIT 1000";

// Prepare and execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get transactions
$transactions = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
}

// Get all users for filter dropdown
$users = [];
$result = $conn->query("SELECT user_id, username, role FROM users ORDER BY username");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

// Get transaction types for filter dropdown
$transactionTypes = [];
$result = $conn->query("SELECT DISTINCT transaction_type FROM transactions ORDER BY transaction_type");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $transactionTypes[] = $row['transaction_type'];
    }
}

// Calculate totals
$totalAmount = 0;
$totalPositive = 0;
$totalNegative = 0;
$countByType = [];

foreach ($transactions as $transaction) {
    $amount = floatval($transaction['amount']);
    $totalAmount += $amount;

    if ($amount >= 0) {
        $totalPositive += $amount;
    } else {
        $totalNegative += $amount;
    }

    $type = $transaction['transaction_type'];
    if (!isset($countByType[$type])) {
        $countByType[$type] = ['count' => 0, 'amount' => 0];
    }
    $countByType[$type]['count']++;
    $countByType[$type]['amount'] += $amount;
}

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions - Roulette POS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin.css">
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

        .positive {
            color: #1cc88a;
            font-weight: 600;
        }

        .negative {
            color: #e74a3b;
            font-weight: 600;
        }

        .badge {
            display: inline-block;
            padding: 0.25em 0.4em;
            font-size: 75%;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
        }

        .badge-primary {
            color: #fff;
            background-color: #4e73df;
        }

        .badge-success {
            color: #fff;
            background-color: #1cc88a;
        }

        .badge-warning {
            color: #fff;
            background-color: #f6c23e;
        }

        .badge-danger {
            color: #fff;
            background-color: #e74a3b;
        }

        .badge-info {
            color: #fff;
            background-color: #36b9cc;
        }

        .badge-secondary {
            color: #fff;
            background-color: #858796;
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

        .stats-card.danger {
            border-left: 0.25rem solid #e74a3b;
        }

        .stats-card.warning {
            border-left: 0.25rem solid #f6c23e;
        }

        .filter-form {
            background-color: #f8f9fc;
            border-radius: 0.35rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e3e6f0;
        }

        .filter-form .form-row {
            display: flex;
            flex-wrap: wrap;
            margin-right: -0.5rem;
            margin-left: -0.5rem;
        }

        .filter-form .form-group {
            flex: 0 0 auto;
            width: auto;
            max-width: 100%;
            padding-right: 0.5rem;
            padding-left: 0.5rem;
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
                <input type="text" class="search-input" placeholder="Search transactions...">
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
                <h1 class="page-title">Transactions</h1>
                <div class="breadcrumb">
                    <div class="breadcrumb-item"><a href="index.php">Admin</a></div>
                    <div class="breadcrumb-item active">Transactions</div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row">
                <div class="col-xl-4 col-md-6">
                    <div class="stats-card primary">
                        <h3><?php echo count($transactions); ?></h3>
                        <p>Total Transactions</p>
                    </div>
                </div>
                <div class="col-xl-4 col-md-6">
                    <div class="stats-card success">
                        <h3>$<?php echo number_format($totalPositive, 2); ?></h3>
                        <p>Total Credits</p>
                    </div>
                </div>
                <div class="col-xl-4 col-md-6">
                    <div class="stats-card danger">
                        <h3>$<?php echo number_format(abs($totalNegative), 2); ?></h3>
                        <p>Total Debits</p>
                    </div>
                </div>
            </div>

            <!-- Filter Form -->
            <form class="filter-form" method="get" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="user_id">User:</label>
                        <select class="form-control" id="user_id" name="user_id">
                            <option value="0">All Users</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['user_id']; ?>" <?php echo $filterUser == $user['user_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="type">Type:</label>
                        <select class="form-control" id="type" name="type">
                            <option value="">All Types</option>
                            <?php foreach ($transactionTypes as $type): ?>
                            <option value="<?php echo $type; ?>" <?php echo $filterType == $type ? 'selected' : ''; ?>>
                                <?php echo ucfirst($type); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="date_from">From:</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $filterDateFrom; ?>">
                    </div>
                    <div class="form-group">
                        <label for="date_to">To:</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $filterDateTo; ?>">
                    </div>
                    <div class="form-group" style="align-self: flex-end;">
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </div>
                </div>
            </form>

            <!-- Transactions Table -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Transaction History</h6>
                    <div>
                        <button class="btn btn-sm btn-primary" onclick="exportToCSV()">
                            <i class="fas fa-download"></i> Export to CSV
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table" id="transactionsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Amount</th>
                                    <th>Balance After</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Date/Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $transaction): ?>
                                <tr>
                                    <td><?php echo $transaction['transaction_id']; ?></td>
                                    <td><?php echo htmlspecialchars($transaction['username']); ?></td>
                                    <td class="<?php echo $transaction['amount'] >= 0 ? 'positive' : 'negative'; ?>">
                                        $<?php echo number_format($transaction['amount'], 2); ?>
                                    </td>
                                    <td>$<?php echo number_format($transaction['balance_after'], 2); ?></td>
                                    <td>
                                        <?php
                                        $badgeClass = 'badge-secondary';
                                        switch ($transaction['transaction_type']) {
                                            case 'bet':
                                                $badgeClass = 'badge-danger';
                                                break;
                                            case 'win':
                                                $badgeClass = 'badge-success';
                                                break;
                                            case 'voucher':
                                                $badgeClass = 'badge-primary';
                                                break;
                                            case 'admin':
                                                $badgeClass = 'badge-warning';
                                                break;
                                            case 'refund':
                                                $badgeClass = 'badge-info';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $badgeClass; ?>">
                                            <?php echo htmlspecialchars($transaction['transaction_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                    <td><?php echo date('M d, Y H:i:s', strtotime($transaction['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Export table to CSV
        function exportToCSV() {
            const table = document.getElementById('transactionsTable');
            let csv = [];
            const rows = table.querySelectorAll('tr');

            for (let i = 0; i < rows.length; i++) {
                const row = [], cols = rows[i].querySelectorAll('td, th');

                for (let j = 0; j < cols.length; j++) {
                    // Get the text content and clean it
                    let data = cols[j].textContent.replace(/(\r\n|\n|\r)/gm, '').trim();

                    // Escape double quotes
                    data = data.replace(/"/g, '""');

                    // Add quotes around the data
                    row.push('"' + data + '"');
                }

                csv.push(row.join(','));
            }

            // Create CSV file
            const csvString = csv.join('\n');
            const filename = 'transactions_export_' + new Date().toISOString().slice(0, 10) + '.csv';

            // Create download link
            const link = document.createElement('a');
            link.style.display = 'none';
            link.setAttribute('href', 'data:text/csv;charset=utf-8,' + encodeURIComponent(csvString));
            link.setAttribute('download', filename);
            document.body.appendChild(link);

            // Click the link to trigger download
            link.click();

            // Clean up
            document.body.removeChild(link);
        }
    </script>
</body>
</html>
