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

// Process form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['user_id']) && isset($_POST['amount'])) {
        $userId = $_POST['user_id'];
        $amount = floatval($_POST['amount']);
        $action = $_POST['action'];
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';

        // Get current cash balance
        $stmt = $conn->prepare("SELECT cash_balance FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $currentBalance = floatval($user['cash_balance']);
            $newBalance = $currentBalance;

            // Update balance based on action
            if ($action === 'add') {
                $newBalance = $currentBalance + $amount;
                $transactionAmount = $amount;
                $transactionType = 'admin';
                $transactionDescription = !empty($description) ? $description : 'Admin added cash';
            } elseif ($action === 'subtract') {
                $newBalance = $currentBalance - $amount;
                $transactionAmount = -$amount;
                $transactionType = 'admin';
                $transactionDescription = !empty($description) ? $description : 'Admin subtracted cash';
            } elseif ($action === 'set') {
                $newBalance = $amount;
                $transactionAmount = $amount - $currentBalance;
                $transactionType = 'admin';
                $transactionDescription = !empty($description) ? $description : 'Admin set cash balance';
            }

            // Ensure balance is not negative
            if ($newBalance < 0) {
                $message = 'Error: Cash balance cannot be negative.';
                $messageType = 'danger';
            } else {
                // Start transaction
                $conn->begin_transaction();

                try {
                    // Update user's cash balance
                    $stmt = $conn->prepare("UPDATE users SET cash_balance = ? WHERE user_id = ?");
                    $stmt->bind_param("di", $newBalance, $userId);
                    $stmt->execute();

                    // Record transaction
                    $stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, balance_after, transaction_type, description) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("iddss", $userId, $transactionAmount, $newBalance, $transactionType, $transactionDescription);
                    $stmt->execute();

                    // Commit transaction
                    $conn->commit();

                    $message = 'Cash balance updated successfully.';
                    $messageType = 'success';
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $conn->rollback();
                    $message = 'Error: ' . $e->getMessage();
                    $messageType = 'danger';
                }
            }
        } else {
            $message = 'Error: User not found.';
            $messageType = 'danger';
        }
    }
}

// Get all users
$users = [];
$result = $conn->query("SELECT user_id, username, role, cash_balance FROM users ORDER BY username");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

// Get recent transactions
$transactions = [];
$result = $conn->query("SELECT t.transaction_id, t.user_id, u.username, t.amount, t.balance_after, t.transaction_type, t.description, t.created_at
                        FROM transactions t
                        JOIN users u ON t.user_id = u.user_id
                        ORDER BY t.created_at DESC
                        LIMIT 50");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
}

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash Management - Roulette POS</title>
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

        .btn-success {
            color: #fff;
            background-color: #1cc88a;
            border-color: #1cc88a;
        }

        .btn-success:hover {
            background-color: #17a673;
            border-color: #169b6b;
        }

        .btn-danger {
            color: #fff;
            background-color: #e74a3b;
            border-color: #e74a3b;
        }

        .btn-danger:hover {
            background-color: #be3c2d;
            border-color: #be3c2d;
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
                <h1 class="page-title">Cash Management</h1>
                <div class="breadcrumb">
                    <div class="breadcrumb-item"><a href="index.php">Admin</a></div>
                    <div class="breadcrumb-item active">Cash Management</div>
                </div>
            </div>

            <!-- Alert Message -->
            <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Update Cash Balance</h6>
                        </div>
                        <div class="card-body">
                            <form method="post" action="">
                                <div class="form-group">
                                    <label for="user_id">Select User:</label>
                                    <select class="form-control" id="user_id" name="user_id" required>
                                        <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['user_id']; ?>">
                                            <?php echo htmlspecialchars($user['username']); ?>
                                            (<?php echo htmlspecialchars($user['role']); ?>) -
                                            $<?php echo number_format($user['cash_balance'], 2); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="action">Action:</label>
                                    <select class="form-control" id="action" name="action" required>
                                        <option value="add">Add Cash</option>
                                        <option value="subtract">Subtract Cash</option>
                                        <option value="set">Set Cash Balance</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="amount">Amount:</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">$</span>
                                        </div>
                                        <input type="number" class="form-control" id="amount" name="amount" min="0" step="0.01" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="description">Description (optional):</label>
                                    <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">Update Balance</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">User Balances</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Username</th>
                                            <th>Role</th>
                                            <th>Cash Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td>
                                                <?php if ($user['role'] === 'admin'): ?>
                                                <span class="badge badge-primary">Admin</span>
                                                <?php elseif ($user['role'] === 'cashier'): ?>
                                                <span class="badge badge-success">Cashier</span>
                                                <?php else: ?>
                                                <span class="badge badge-secondary"><?php echo htmlspecialchars($user['role']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>$<?php echo number_format($user['cash_balance'], 2); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Transactions</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
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
</body>
</html>
