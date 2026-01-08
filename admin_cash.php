<?php
// Start session
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Redirect to login page if not logged in or not an admin
    header('Location: login.html');
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

// Process form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['user_id']) && isset($_POST['amount'])) {
        $userId = $_POST['user_id'];
        $amount = floatval($_POST['amount']);
        $action = $_POST['action'];

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
                $description = 'Admin added cash';
            } elseif ($action === 'subtract') {
                $newBalance = $currentBalance - $amount;
                $transactionAmount = -$amount;
                $transactionType = 'admin';
                $description = 'Admin subtracted cash';
            } elseif ($action === 'set') {
                $newBalance = $amount;
                $transactionAmount = $amount - $currentBalance;
                $transactionType = 'admin';
                $description = 'Admin set cash balance';
            }

            // Ensure balance is not negative
            if ($newBalance < 0) {
                $message = '<div class="alert alert-danger">Error: Cash balance cannot be negative.</div>';
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
                    $stmt->bind_param("iddss", $userId, $transactionAmount, $newBalance, $transactionType, $description);
                    $stmt->execute();

                    // Commit transaction
                    $conn->commit();

                    $message = '<div class="alert alert-success">Cash balance updated successfully.</div>';
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $conn->rollback();
                    $message = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
                }
            }
        } else {
            $message = '<div class="alert alert-danger">Error: User not found.</div>';
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
    <title>Admin - Cash Management</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        body {
            padding-top: 20px;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 1200px;
        }
        .card {
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .table th {
            background-color: #f8f9fa;
        }
        .positive {
            color: green;
        }
        .negative {
            color: red;
        }
        .navbar {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <a class="navbar-brand" href="#">Roulette Admin</a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="index.html">Game</a>
                    </li>
                    <li class="nav-item active">
                        <a class="nav-link" href="admin_cash.php">Cash Management</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_vouchers.php">Vouchers</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="commission.php">Commission</a>
                    </li>
                </ul>
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </nav>

        <h1 class="mb-4">Cash Management</h1>

        <?php echo $message; ?>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Update Cash Balance</h5>
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
                            <button type="submit" class="btn btn-primary">Update Balance</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">User Balances</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
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
                                        <td><?php echo htmlspecialchars($user['role']); ?></td>
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

        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">Recent Transactions</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
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
                                <td><?php echo htmlspecialchars($transaction['transaction_type']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                <td><?php echo $transaction['created_at']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
