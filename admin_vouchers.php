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
        die("Error creating vouchers table: " . $conn->error);
    }
}

// Process form submission for creating new voucher
$message = '';
$messageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create' && isset($_POST['voucher_code']) && isset($_POST['amount'])) {
        $voucherCode = strtoupper(trim($_POST['voucher_code']));
        $amount = floatval($_POST['amount']);

        // Validate input
        if (empty($voucherCode) || $amount <= 0) {
            $message = "Invalid voucher code or amount.";
            $messageType = 'danger';
        } else {
            // Check if voucher code already exists
            $stmt = $conn->prepare("SELECT voucher_id FROM vouchers WHERE voucher_code = ?");
            $stmt->bind_param("s", $voucherCode);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $message = "Voucher code already exists.";
                $messageType = 'warning';
            } else {
                // Create new voucher
                $stmt = $conn->prepare("INSERT INTO vouchers (voucher_code, amount) VALUES (?, ?)");
                $stmt->bind_param("sd", $voucherCode, $amount);

                if ($stmt->execute()) {
                    $message = "Voucher created successfully.";
                    $messageType = 'success';
                } else {
                    $message = "Error creating voucher: " . $stmt->error;
                    $messageType = 'danger';
                }
            }
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['voucher_id'])) {
        $voucherId = intval($_POST['voucher_id']);

        // Check if voucher is used
        $stmt = $conn->prepare("SELECT is_used FROM vouchers WHERE voucher_id = ?");
        $stmt->bind_param("i", $voucherId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $voucher = $result->fetch_assoc();

            if ($voucher['is_used'] == 1) {
                $message = "Cannot delete a used voucher.";
                $messageType = 'warning';
            } else {
                // Delete voucher
                $stmt = $conn->prepare("DELETE FROM vouchers WHERE voucher_id = ?");
                $stmt->bind_param("i", $voucherId);

                if ($stmt->execute()) {
                    $message = "Voucher deleted successfully.";
                    $messageType = 'success';
                } else {
                    $message = "Error deleting voucher: " . $stmt->error;
                    $messageType = 'danger';
                }
            }
        } else {
            $message = "Voucher not found.";
            $messageType = 'danger';
        }
    }
}

// Get all vouchers
$vouchers = [];
$result = $conn->query("SELECT v.*, u.username
                        FROM vouchers v
                        LEFT JOIN users u ON v.used_by = u.user_id
                        ORDER BY v.created_at DESC");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $vouchers[] = $row;
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
    <title>Admin - Vouchers</title>
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
        .navbar {
            margin-bottom: 20px;
        }
        .voucher-code {
            font-family: monospace;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .badge-used {
            background-color: #6c757d;
        }
        .badge-available {
            background-color: #28a745;
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
                    <li class="nav-item">
                        <a class="nav-link" href="admin_cash.php">Cash Management</a>
                    </li>
                    <li class="nav-item active">
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

        <h1 class="mb-4">Voucher Management</h1>

        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Create New Voucher</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <input type="hidden" name="action" value="create">
                            <div class="form-group">
                                <label for="voucher_code">Voucher Code:</label>
                                <input type="text" class="form-control" id="voucher_code" name="voucher_code" placeholder="e.g., BONUS100" required>
                                <small class="form-text text-muted">Enter a unique code for the voucher.</small>
                            </div>
                            <div class="form-group">
                                <label for="amount">Amount:</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">$</span>
                                    </div>
                                    <input type="number" class="form-control" id="amount" name="amount" min="1" step="0.01" placeholder="100.00" required>
                                </div>
                                <small class="form-text text-muted">Enter the amount to be credited when the voucher is redeemed.</small>
                            </div>
                            <button type="submit" class="btn btn-primary">Create Voucher</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Voucher List</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($vouchers)): ?>
                        <div class="alert alert-info">No vouchers found.</div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Code</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Used By</th>
                                        <th>Used At</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($vouchers as $voucher): ?>
                                    <tr>
                                        <td><?php echo $voucher['voucher_id']; ?></td>
                                        <td class="voucher-code"><?php echo htmlspecialchars($voucher['voucher_code']); ?></td>
                                        <td>$<?php echo number_format($voucher['amount'], 2); ?></td>
                                        <td>
                                            <?php if ($voucher['is_used'] == 1): ?>
                                            <span class="badge badge-used">Used</span>
                                            <?php else: ?>
                                            <span class="badge badge-available">Available</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $voucher['username'] ? htmlspecialchars($voucher['username']) : '-'; ?></td>
                                        <td><?php echo $voucher['used_at'] ? $voucher['used_at'] : '-'; ?></td>
                                        <td><?php echo $voucher['created_at']; ?></td>
                                        <td>
                                            <?php if ($voucher['is_used'] == 0): ?>
                                            <form method="post" action="" onsubmit="return confirm('Are you sure you want to delete this voucher?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="voucher_id" value="<?php echo $voucher['voucher_id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                            <?php else: ?>
                                            -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
