<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if not logged in
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

// Get user ID from session
$userId = $_SESSION['user_id'];

// Get user details
$user = null;
$stmt = $conn->prepare("SELECT username, role, cash_balance FROM users WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
}

// Process voucher redemption
$message = '';
$messageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['voucher_code'])) {
    $voucherCode = trim($_POST['voucher_code']);

    // Check if vouchers table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'vouchers'");
    if ($tableCheck->num_rows == 0) {
        // Create vouchers table
        $createTable = "CREATE TABLE IF NOT EXISTS vouchers (
            voucher_id INT AUTO_INCREMENT PRIMARY KEY,
            voucher_code VARCHAR(20) NOT NULL UNIQUE,
            amount DECIMAL(10,2) NOT NULL,
            is_used TINYINT(1) NOT NULL DEFAULT 0,
            used_by INT NULL,
            used_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";

        if (!$conn->query($createTable)) {
            $message = "Error creating vouchers table: " . $conn->error;
            $messageType = 'danger';
        } else {
            // Create some sample vouchers
            $sampleVouchers = [
                ['code' => 'BONUS100', 'amount' => 100.00],
                ['code' => 'BONUS200', 'amount' => 200.00],
                ['code' => 'BONUS500', 'amount' => 500.00],
                ['code' => 'WELCOME1000', 'amount' => 1000.00]
            ];

            $stmt = $conn->prepare("INSERT INTO vouchers (voucher_code, amount) VALUES (?, ?)");
            foreach ($sampleVouchers as $voucher) {
                $stmt->bind_param("sd", $voucher['code'], $voucher['amount']);
                $stmt->execute();
            }
        }
    }

    // Check if voucher exists and is not used
    $stmt = $conn->prepare("SELECT voucher_id, amount, is_used FROM vouchers WHERE voucher_code = ?");
    $stmt->bind_param("s", $voucherCode);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $voucher = $result->fetch_assoc();

        if ($voucher['is_used'] == 1) {
            $message = "This voucher has already been used.";
            $messageType = 'warning';
        } else {
            // Start transaction
            $conn->begin_transaction();

            try {
                // Mark voucher as used
                $stmt = $conn->prepare("UPDATE vouchers SET is_used = 1, used_by = ?, used_at = NOW() WHERE voucher_id = ?");
                $stmt->bind_param("ii", $userId, $voucher['voucher_id']);
                $stmt->execute();

                // Get current cash balance
                $stmt = $conn->prepare("SELECT cash_balance FROM users WHERE user_id = ? FOR UPDATE");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                $currentBalance = floatval($user['cash_balance']);

                // Update user's cash balance
                $newBalance = $currentBalance + $voucher['amount'];
                $stmt = $conn->prepare("UPDATE users SET cash_balance = ? WHERE user_id = ?");
                $stmt->bind_param("di", $newBalance, $userId);
                $stmt->execute();

                // Record transaction
                $stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, balance_after, transaction_type, reference_id, description) VALUES (?, ?, ?, ?, ?, ?)");
                $transactionType = 'voucher';
                $referenceId = $voucher['voucher_id'];
                $description = "Voucher redemption: " . $voucherCode;
                $stmt->bind_param("iddsss", $userId, $voucher['amount'], $newBalance, $transactionType, $referenceId, $description);
                $stmt->execute();

                // Commit transaction
                $conn->commit();

                $message = "Voucher redeemed successfully! $" . number_format($voucher['amount'], 2) . " has been added to your account.";
                $messageType = 'success';

                // Update user object with new balance
                $user['cash_balance'] = $newBalance;
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $message = "Error redeeming voucher: " . $e->getMessage();
                $messageType = 'danger';
            }
        }
    } else {
        $message = "Invalid voucher code.";
        $messageType = 'danger';
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
    <title>Redeem Voucher</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        body {
            padding-top: 20px;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 800px;
        }
        .card {
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .balance-card {
            background: linear-gradient(135deg, #4b6cb7, #182848);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .balance-amount {
            font-size: 2.5rem;
            font-weight: bold;
        }
        .navbar {
            margin-bottom: 20px;
        }
        .voucher-form {
            max-width: 500px;
            margin: 0 auto;
        }
        .voucher-input {
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <a class="navbar-brand" href="#">
                <i class="fas fa-dice"></i> Roulette
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home"></i> Game
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my_transactions_new.php">
                            <i class="fas fa-history"></i> My Transactions
                        </a>
                    </li>
                    <li class="nav-item active">
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
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <h1 class="mb-4">Redeem Voucher</h1>

        <?php if ($user): ?>
        <div class="balance-card">
            <div class="row">
                <div class="col-md-6">
                    <h5>Welcome, <?php echo htmlspecialchars($user['username']); ?></h5>
                    <p>Role: <?php echo htmlspecialchars($user['role']); ?></p>
                </div>
                <div class="col-md-6 text-right">
                    <div>Current Balance</div>
                    <div class="balance-amount">$<?php echo number_format($user['cash_balance'], 2); ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Enter Voucher Code</h5>
            </div>
            <div class="card-body">
                <form method="post" action="" class="voucher-form">
                    <div class="form-group">
                        <label for="voucher_code">Voucher Code:</label>
                        <input type="text" class="form-control voucher-input" id="voucher_code" name="voucher_code" placeholder="Enter voucher code" required>
                        <small class="form-text text-muted">Enter the voucher code to add credits to your account.</small>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Redeem Voucher</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Sample Voucher Codes</h5>
            </div>
            <div class="card-body">
                <p>For testing purposes, you can use the following voucher codes:</p>
                <ul>
                    <li><strong>BONUS100</strong> - Adds $100.00 to your account</li>
                    <li><strong>BONUS200</strong> - Adds $200.00 to your account</li>
                    <li><strong>BONUS500</strong> - Adds $500.00 to your account</li>
                    <li><strong>WELCOME1000</strong> - Adds $1,000.00 to your account</li>
                </ul>
                <p class="text-muted">Note: Each voucher can only be used once.</p>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
