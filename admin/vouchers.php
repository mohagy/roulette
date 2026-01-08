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
$requiredTables = ['users', 'vouchers'];
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
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (used_by) REFERENCES users(user_id)
    )";

    if (!$conn->query($createTable)) {
        die("Error creating vouchers table: " . $conn->error);
    }
}

// Process form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Create new voucher
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
        }
        // Create multiple vouchers
        elseif ($_POST['action'] === 'create_multiple' && isset($_POST['prefix']) && isset($_POST['amount']) && isset($_POST['count'])) {
            $prefix = strtoupper(trim($_POST['prefix']));
            $amount = floatval($_POST['amount']);
            $count = intval($_POST['count']);

            // Validate input
            if (empty($prefix) || $amount <= 0 || $count <= 0 || $count > 100) {
                $message = "Invalid prefix, amount, or count.";
                $messageType = 'danger';
            } else {
                // Start transaction
                $conn->begin_transaction();

                try {
                    $successCount = 0;
                    $stmt = $conn->prepare("INSERT INTO vouchers (voucher_code, amount) VALUES (?, ?)");

                    for ($i = 1; $i <= $count; $i++) {
                        // Generate unique code with prefix and random suffix
                        $suffix = str_pad(mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);
                        $voucherCode = $prefix . $suffix;

                        // Check if code already exists
                        $checkStmt = $conn->prepare("SELECT voucher_id FROM vouchers WHERE voucher_code = ?");
                        $checkStmt->bind_param("s", $voucherCode);
                        $checkStmt->execute();
                        $result = $checkStmt->get_result();

                        if ($result->num_rows === 0) {
                            $stmt->bind_param("sd", $voucherCode, $amount);
                            if ($stmt->execute()) {
                                $successCount++;
                            }
                        }
                    }

                    // Commit transaction
                    $conn->commit();

                    if ($successCount > 0) {
                        $message = "Created $successCount vouchers successfully.";
                        $messageType = 'success';
                    } else {
                        $message = "No vouchers were created. Try a different prefix.";
                        $messageType = 'warning';
                    }
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $conn->rollback();
                    $message = "Error creating vouchers: " . $e->getMessage();
                    $messageType = 'danger';
                }
            }
        }
        // Delete voucher
        elseif ($_POST['action'] === 'delete' && isset($_POST['voucher_id'])) {
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

// Get voucher statistics
$stats = [
    'total' => count($vouchers),
    'used' => 0,
    'available' => 0,
    'total_value' => 0,
    'redeemed_value' => 0
];

foreach ($vouchers as $voucher) {
    if ($voucher['is_used'] == 1) {
        $stats['used']++;
        $stats['redeemed_value'] += $voucher['amount'];
    } else {
        $stats['available']++;
    }
    $stats['total_value'] += $voucher['amount'];
}

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voucher Management - Roulette POS</title>
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

        .btn-danger {
            color: #fff;
            background-color: #e74a3b;
            border-color: #e74a3b;
        }

        .btn-danger:hover {
            background-color: #be3c2d;
            border-color: #be3c2d;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            line-height: 1.5;
            border-radius: 0.2rem;
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

        .alert-warning {
            color: #664d03;
            background-color: #fff3cd;
            border-color: #ffecb5;
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

        .badge-success {
            color: #fff;
            background-color: #1cc88a;
        }

        .badge-secondary {
            color: #fff;
            background-color: #858796;
        }

        .voucher-code {
            font-family: monospace;
            font-weight: bold;
            letter-spacing: 1px;
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

        .stats-card.danger {
            border-left: 0.25rem solid #e74a3b;
        }

        .nav-tabs {
            display: flex;
            border-bottom: 1px solid #e3e6f0;
            margin-bottom: 1rem;
        }

        .nav-tabs .nav-item {
            margin-bottom: -1px;
        }

        .nav-tabs .nav-link {
            border: 1px solid transparent;
            border-top-left-radius: 0.25rem;
            border-top-right-radius: 0.25rem;
            padding: 0.5rem 1rem;
            margin-right: 0.25rem;
            color: #4e73df;
        }

        .nav-tabs .nav-link:hover {
            border-color: #e3e6f0 #e3e6f0 #e3e6f0;
        }

        .nav-tabs .nav-link.active {
            color: #6e707e;
            background-color: #fff;
            border-color: #e3e6f0 #e3e6f0 #fff;
            font-weight: 600;
        }

        .tab-content > .tab-pane {
            display: none;
        }

        .tab-content > .active {
            display: block;
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
                <input type="text" class="search-input" placeholder="Search vouchers...">
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
                <h1 class="page-title">Voucher Management</h1>
                <div class="breadcrumb">
                    <div class="breadcrumb-item"><a href="index.php">Admin</a></div>
                    <div class="breadcrumb-item active">Vouchers</div>
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
                        <h3><?php echo $stats['total']; ?></h3>
                        <p>Total Vouchers</p>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card success">
                        <h3><?php echo $stats['available']; ?></h3>
                        <p>Available Vouchers</p>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card warning">
                        <h3>$<?php echo number_format($stats['total_value'], 2); ?></h3>
                        <p>Total Value</p>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card danger">
                        <h3>$<?php echo number_format($stats['redeemed_value'], 2); ?></h3>
                        <p>Redeemed Value</p>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <ul class="nav nav-tabs" id="voucherTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="single-tab" data-toggle="tab" href="#single" role="tab">Create Single Voucher</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="multiple-tab" data-toggle="tab" href="#multiple" role="tab">Create Multiple Vouchers</a>
                </li>
            </ul>

            <div class="tab-content" id="voucherTabContent">
                <!-- Single Voucher Tab -->
                <div class="tab-pane fade show active" id="single" role="tabpanel">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Create New Voucher</h6>
                        </div>
                        <div class="card-body">
                            <form method="post" action="">
                                <input type="hidden" name="action" value="create">
                                <div class="form-group">
                                    <label for="voucher_code">Voucher Code:</label>
                                    <input type="text" class="form-control" id="voucher_code" name="voucher_code" placeholder="e.g., BONUS100" required>
                                    <small class="text-muted">Enter a unique code for the voucher.</small>
                                </div>
                                <div class="form-group">
                                    <label for="amount">Amount:</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">$</span>
                                        </div>
                                        <input type="number" class="form-control" id="amount" name="amount" min="1" step="0.01" placeholder="100.00" required>
                                    </div>
                                    <small class="text-muted">Enter the amount to be credited when the voucher is redeemed.</small>
                                </div>
                                <button type="submit" class="btn btn-primary">Create Voucher</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Multiple Vouchers Tab -->
                <div class="tab-pane fade" id="multiple" role="tabpanel">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Create Multiple Vouchers</h6>
                        </div>
                        <div class="card-body">
                            <form method="post" action="">
                                <input type="hidden" name="action" value="create_multiple">
                                <div class="form-group">
                                    <label for="prefix">Voucher Code Prefix:</label>
                                    <input type="text" class="form-control" id="prefix" name="prefix" placeholder="e.g., BONUS" required>
                                    <small class="text-muted">Enter a prefix for the voucher codes. Random numbers will be appended.</small>
                                </div>
                                <div class="form-group">
                                    <label for="amount_multiple">Amount:</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">$</span>
                                        </div>
                                        <input type="number" class="form-control" id="amount_multiple" name="amount" min="1" step="0.01" placeholder="100.00" required>
                                    </div>
                                    <small class="text-muted">Enter the amount to be credited when the vouchers are redeemed.</small>
                                </div>
                                <div class="form-group">
                                    <label for="count">Number of Vouchers:</label>
                                    <input type="number" class="form-control" id="count" name="count" min="1" max="100" value="10" required>
                                    <small class="text-muted">Enter the number of vouchers to create (max 100).</small>
                                </div>
                                <button type="submit" class="btn btn-primary">Create Vouchers</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Vouchers Table -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">All Vouchers</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
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
                                        <span class="badge badge-secondary">Used</span>
                                        <?php else: ?>
                                        <span class="badge badge-success">Available</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $voucher['username'] ? htmlspecialchars($voucher['username']) : '-'; ?></td>
                                    <td><?php echo $voucher['used_at'] ? date('M d, Y H:i', strtotime($voucher['used_at'])) : '-'; ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($voucher['created_at'])); ?></td>
                                    <td>
                                        <?php if ($voucher['is_used'] == 0): ?>
                                        <form method="post" action="" onsubmit="return confirm('Are you sure you want to delete this voucher?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="voucher_id" value="<?php echo $voucher['voucher_id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">
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
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.nav-link');
            tabs.forEach(tab => {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();

                    // Remove active class from all tabs and tab panes
                    document.querySelectorAll('.nav-link').forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.tab-pane').forEach(p => {
                        p.classList.remove('show');
                        p.classList.remove('active');
                    });

                    // Add active class to clicked tab
                    this.classList.add('active');

                    // Get the target tab pane
                    const target = this.getAttribute('href').substring(1);
                    const targetPane = document.getElementById(target);

                    // Show the target tab pane
                    targetPane.classList.add('show');
                    targetPane.classList.add('active');
                });
            });
        });
    </script>
</body>
</html>
