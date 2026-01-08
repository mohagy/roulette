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
$requiredTables = ['betting_slips', 'bets', 'slip_details', 'users'];
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
$filterStatus = isset($_GET['status']) ? $_GET['status'] : '';
$filterDateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$filterDateTo = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$filterDrawNumber = isset($_GET['draw_number']) ? intval($_GET['draw_number']) : 0;
$filterWinningNumber = isset($_GET['winning_number']) ? intval($_GET['winning_number']) : -1;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = 20;

// Build query with filters
$query = "SELECT bs.slip_id, bs.slip_number, bs.user_id, u.username, bs.total_stake, bs.potential_payout,
          bs.created_at, bs.is_paid, bs.is_cancelled, bs.draw_number, bs.winning_number, bs.status,
          bs.paid_out_amount, bs.cashout_time
          FROM betting_slips bs
          JOIN users u ON bs.user_id = u.user_id
          WHERE 1=1";

$countQuery = "SELECT COUNT(*) as total FROM betting_slips bs WHERE 1=1";

$params = [];
$types = "";

if ($filterUser > 0) {
    $query .= " AND bs.user_id = ?";
    $countQuery .= " AND bs.user_id = ?";
    $params[] = $filterUser;
    $types .= "i";
}

if (!empty($filterStatus)) {
    $query .= " AND bs.status = ?";
    $countQuery .= " AND bs.status = ?";
    $params[] = $filterStatus;
    $types .= "s";
}

if (!empty($filterDateFrom)) {
    $query .= " AND DATE(bs.created_at) >= ?";
    $countQuery .= " AND DATE(bs.created_at) >= ?";
    $params[] = $filterDateFrom;
    $types .= "s";
}

if (!empty($filterDateTo)) {
    $query .= " AND DATE(bs.created_at) <= ?";
    $countQuery .= " AND DATE(bs.created_at) <= ?";
    $params[] = $filterDateTo;
    $types .= "s";
}

if ($filterDrawNumber > 0) {
    $query .= " AND bs.draw_number = ?";
    $countQuery .= " AND bs.draw_number = ?";
    $params[] = $filterDrawNumber;
    $types .= "i";
}

if ($filterWinningNumber >= 0) {
    $query .= " AND bs.winning_number = ?";
    $countQuery .= " AND bs.winning_number = ?";
    $params[] = $filterWinningNumber;
    $types .= "i";
}

// Get total count for pagination
$countStmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalCount = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalCount / $perPage);

// Ensure page is within valid range
if ($page < 1) $page = 1;
if ($page > $totalPages && $totalPages > 0) $page = $totalPages;

// Add pagination to query
$offset = ($page - 1) * $perPage;
$query .= " ORDER BY bs.created_at DESC LIMIT ?, ?";
$params[] = $offset;
$params[] = $perPage;
$types .= "ii";

// Prepare and execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get betting slips
$bettingSlips = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $bettingSlips[] = $row;
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

// Get all statuses for filter dropdown
$statuses = [];
$result = $conn->query("SELECT DISTINCT status FROM betting_slips WHERE status IS NOT NULL AND status != '' ORDER BY status");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $statuses[] = $row['status'];
    }
}

// Calculate totals
$totalStake = 0;
$totalPayout = 0;
$totalPotentialPayout = 0;
$countByStatus = [];

foreach ($bettingSlips as $slip) {
    $totalStake += floatval($slip['total_stake']);
    $totalPotentialPayout += floatval($slip['potential_payout']);

    if ($slip['is_paid']) {
        $totalPayout += floatval($slip['paid_out_amount']);
    }

    $status = $slip['status'] ?: ($slip['is_cancelled'] ? 'cancelled' : ($slip['is_paid'] ? 'paid' : 'pending'));
    if (!isset($countByStatus[$status])) {
        $countByStatus[$status] = ['count' => 0, 'stake' => 0, 'payout' => 0];
    }
    $countByStatus[$status]['count']++;
    $countByStatus[$status]['stake'] += floatval($slip['total_stake']);
    if ($slip['is_paid']) {
        $countByStatus[$status]['payout'] += floatval($slip['paid_out_amount']);
    }
}

// Function to get bet details for a slip
function getBetDetails($conn, $slipId) {
    $query = "SELECT b.bet_type, b.bet_description, b.bet_amount, b.multiplier, b.potential_return
              FROM slip_details sd
              JOIN bets b ON sd.bet_id = b.bet_id
              WHERE sd.slip_id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $slipId);
    $stmt->execute();
    $result = $stmt->get_result();

    $bets = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $bets[] = $row;
        }
    }

    return $bets;
}

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Betting History - Roulette POS</title>
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

        .btn-info {
            color: #fff;
            background-color: #36b9cc;
            border-color: #36b9cc;
        }

        .btn-info:hover {
            background-color: #2c9faf;
            border-color: #2a96a5;
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

        .pagination {
            display: flex;
            padding-left: 0;
            list-style: none;
            border-radius: 0.35rem;
            justify-content: center;
            margin-top: 1.5rem;
        }

        .pagination .page-item {
            margin: 0 0.2rem;
        }

        .pagination .page-link {
            position: relative;
            display: block;
            padding: 0.5rem 0.75rem;
            margin-left: -1px;
            line-height: 1.25;
            color: #4e73df;
            background-color: #fff;
            border: 1px solid #dddfeb;
            border-radius: 0.35rem;
            text-decoration: none;
        }

        .pagination .page-item.active .page-link {
            z-index: 3;
            color: #fff;
            background-color: #4e73df;
            border-color: #4e73df;
        }

        .pagination .page-item.disabled .page-link {
            color: #858796;
            pointer-events: none;
            cursor: auto;
            background-color: #fff;
            border-color: #dddfeb;
        }

        .bet-details {
            display: none;
            background-color: #f8f9fc;
            border: 1px solid #e3e6f0;
            border-radius: 0.35rem;
            padding: 1rem;
            margin-top: 0.5rem;
        }

        .bet-details table {
            width: 100%;
            margin-bottom: 0;
        }

        .bet-details th {
            background-color: #eaecf4;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 600px;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
        }

        .modal-header {
            padding-bottom: 1rem;
            border-bottom: 1px solid #e3e6f0;
            margin-bottom: 1rem;
        }

        .modal-footer {
            padding-top: 1rem;
            border-top: 1px solid #e3e6f0;
            margin-top: 1rem;
            text-align: right;
        }

        @media (max-width: 768px) {
            .filter-form .form-group {
                width: 100%;
                margin-bottom: 0.5rem;
            }

            .table-responsive {
                overflow-x: auto;
            }
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
                <input type="text" class="search-input" placeholder="Search betting slips...">
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
                <h1 class="page-title">Betting History</h1>
                <div class="breadcrumb">
                    <div class="breadcrumb-item"><a href="index.php">Admin</a></div>
                    <div class="breadcrumb-item active">Betting History</div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row">
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card primary">
                        <h3><?php echo $totalCount; ?></h3>
                        <p>Total Betting Slips</p>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card warning">
                        <h3>$<?php echo number_format($totalStake, 2); ?></h3>
                        <p>Total Stakes</p>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card success">
                        <h3>$<?php echo number_format($totalPayout, 2); ?></h3>
                        <p>Total Payouts</p>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card danger">
                        <h3>$<?php echo number_format($totalPotentialPayout, 2); ?></h3>
                        <p>Potential Payouts</p>
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
                        <label for="status">Status:</label>
                        <select class="form-control" id="status" name="status">
                            <option value="">All Statuses</option>
                            <?php foreach ($statuses as $status): ?>
                            <option value="<?php echo $status; ?>" <?php echo $filterStatus == $status ? 'selected' : ''; ?>>
                                <?php echo ucfirst($status); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="draw_number">Draw #:</label>
                        <input type="number" class="form-control" id="draw_number" name="draw_number" value="<?php echo $filterDrawNumber > 0 ? $filterDrawNumber : ''; ?>" placeholder="Any">
                    </div>
                    <div class="form-group">
                        <label for="winning_number">Winning #:</label>
                        <input type="number" class="form-control" id="winning_number" name="winning_number" value="<?php echo $filterWinningNumber >= 0 ? $filterWinningNumber : ''; ?>" placeholder="Any" min="0" max="36">
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

            <!-- Betting Slips Table -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Betting Slip History</h6>
                    <div>
                        <button class="btn btn-sm btn-primary" onclick="exportToCSV()">
                            <i class="fas fa-download"></i> Export to CSV
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table" id="bettingSlipsTable">
                            <thead>
                                <tr>
                                    <th>Slip #</th>
                                    <th>User</th>
                                    <th>Draw #</th>
                                    <th>Stake</th>
                                    <th>Potential Payout</th>
                                    <th>Status</th>
                                    <th>Date/Time</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($bettingSlips)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">No betting slips found</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($bettingSlips as $slip): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($slip['slip_number']); ?></td>
                                    <td><?php echo htmlspecialchars($slip['username']); ?></td>
                                    <td><?php echo $slip['draw_number']; ?></td>
                                    <td>$<?php echo number_format($slip['total_stake'], 2); ?></td>
                                    <td>$<?php echo number_format($slip['potential_payout'], 2); ?></td>
                                    <td>
                                        <?php
                                        $status = $slip['status'] ?: ($slip['is_cancelled'] ? 'cancelled' : ($slip['is_paid'] ? 'paid' : 'pending'));
                                        $badgeClass = 'badge-secondary';
                                        switch ($status) {
                                            case 'pending':
                                                $badgeClass = 'badge-warning';
                                                break;
                                            case 'paid':
                                            case 'won':
                                                $badgeClass = 'badge-success';
                                                break;
                                            case 'lost':
                                                $badgeClass = 'badge-danger';
                                                break;
                                            case 'cancelled':
                                                $badgeClass = 'badge-secondary';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $badgeClass; ?>">
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y H:i:s', strtotime($slip['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info view-details" data-slip-id="<?php echo $slip['slip_id']; ?>">
                                            <i class="fas fa-eye"></i> Details
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <nav>
                        <ul class="pagination">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=1<?php echo $filterUser ? '&user_id='.$filterUser : ''; ?><?php echo $filterStatus ? '&status='.$filterStatus : ''; ?><?php echo $filterDateFrom ? '&date_from='.$filterDateFrom : ''; ?><?php echo $filterDateTo ? '&date_to='.$filterDateTo : ''; ?><?php echo $filterDrawNumber ? '&draw_number='.$filterDrawNumber : ''; ?><?php echo $filterWinningNumber >= 0 ? '&winning_number='.$filterWinningNumber : ''; ?>">First</a>
                            </li>
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo $filterUser ? '&user_id='.$filterUser : ''; ?><?php echo $filterStatus ? '&status='.$filterStatus : ''; ?><?php echo $filterDateFrom ? '&date_from='.$filterDateFrom : ''; ?><?php echo $filterDateTo ? '&date_to='.$filterDateTo : ''; ?><?php echo $filterDrawNumber ? '&draw_number='.$filterDrawNumber : ''; ?><?php echo $filterWinningNumber >= 0 ? '&winning_number='.$filterWinningNumber : ''; ?>">Previous</a>
                            </li>

                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);

                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo $filterUser ? '&user_id='.$filterUser : ''; ?><?php echo $filterStatus ? '&status='.$filterStatus : ''; ?><?php echo $filterDateFrom ? '&date_from='.$filterDateFrom : ''; ?><?php echo $filterDateTo ? '&date_to='.$filterDateTo : ''; ?><?php echo $filterDrawNumber ? '&draw_number='.$filterDrawNumber : ''; ?><?php echo $filterWinningNumber >= 0 ? '&winning_number='.$filterWinningNumber : ''; ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>

                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo $filterUser ? '&user_id='.$filterUser : ''; ?><?php echo $filterStatus ? '&status='.$filterStatus : ''; ?><?php echo $filterDateFrom ? '&date_from='.$filterDateFrom : ''; ?><?php echo $filterDateTo ? '&date_to='.$filterDateTo : ''; ?><?php echo $filterDrawNumber ? '&draw_number='.$filterDrawNumber : ''; ?><?php echo $filterWinningNumber >= 0 ? '&winning_number='.$filterWinningNumber : ''; ?>">Next</a>
                            </li>
                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo $filterUser ? '&user_id='.$filterUser : ''; ?><?php echo $filterStatus ? '&status='.$filterStatus : ''; ?><?php echo $filterDateFrom ? '&date_from='.$filterDateFrom : ''; ?><?php echo $filterDateTo ? '&date_to='.$filterDateTo : ''; ?><?php echo $filterDrawNumber ? '&draw_number='.$filterDrawNumber : ''; ?><?php echo $filterWinningNumber >= 0 ? '&winning_number='.$filterWinningNumber : ''; ?>">Last</a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Bet Details Modal -->
            <div id="betDetailsModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4>Betting Slip Details</h4>
                        <span class="close">&times;</span>
                    </div>
                    <div class="modal-body">
                        <div id="slipInfo">
                            <p><strong>Slip Number:</strong> <span id="modalSlipNumber"></span></p>
                            <p><strong>User:</strong> <span id="modalUsername"></span></p>
                            <p><strong>Draw Number:</strong> <span id="modalDrawNumber"></span></p>
                            <p><strong>Winning Number:</strong> <span id="modalWinningNumber"></span></p>
                            <p><strong>Total Stake:</strong> $<span id="modalTotalStake"></span></p>
                            <p><strong>Potential Payout:</strong> $<span id="modalPotentialPayout"></span></p>
                            <p><strong>Status:</strong> <span id="modalStatus"></span></p>
                            <p><strong>Created:</strong> <span id="modalCreatedAt"></span></p>
                        </div>
                        <h5 class="mt-4">Bets</h5>
                        <div class="table-responsive">
                            <table class="table" id="modalBetsTable">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Multiplier</th>
                                        <th>Potential Return</th>
                                    </tr>
                                </thead>
                                <tbody id="modalBetsList">
                                    <!-- Bet details will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-primary" id="printSlipBtn">Print Slip</button>
                        <button class="btn btn-secondary" onclick="closeModal()">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Export to CSV function
        function exportToCSV() {
            const table = document.getElementById('bettingSlipsTable');
            let csv = [];
            const rows = table.querySelectorAll('tr');

            for (let i = 0; i < rows.length; i++) {
                const row = [], cols = rows[i].querySelectorAll('td, th');

                for (let j = 0; j < cols.length - 1; j++) { // Skip the Actions column
                    // Get the text content and clean it
                    let data = cols[j].textContent.trim();
                    // Escape quotes and wrap in quotes if it contains commas
                    data = data.replace(/"/g, '""');
                    if (data.includes(',')) {
                        data = `"${data}"`;
                    }
                    row.push(data);
                }
                csv.push(row.join(','));
            }

            const csvContent = 'data:text/csv;charset=utf-8,' + csv.join('\n');
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', 'betting_history.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Modal functionality
        const modal = document.getElementById('betDetailsModal');
        const closeBtn = document.getElementsByClassName('close')[0];

        // Close modal when clicking the X
        closeBtn.onclick = closeModal;

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        // View bet details
        document.querySelectorAll('.view-details').forEach(button => {
            button.addEventListener('click', function() {
                const slipId = this.getAttribute('data-slip-id');
                fetchBetDetails(slipId);
            });
        });

        function fetchBetDetails(slipId) {
            // AJAX request to get bet details
            fetch(`get_bet_details.php?slip_id=${slipId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayBetDetails(data.slip, data.bets);
                    } else {
                        alert('Error loading bet details: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading bet details. Please try again.');
                });
        }

        function displayBetDetails(slip, bets) {
            // Fill in slip details
            document.getElementById('modalSlipNumber').textContent = slip.slip_number;
            document.getElementById('modalUsername').textContent = slip.username;
            document.getElementById('modalDrawNumber').textContent = slip.draw_number;
            document.getElementById('modalWinningNumber').textContent = slip.winning_number || 'N/A';
            document.getElementById('modalTotalStake').textContent = parseFloat(slip.total_stake).toFixed(2);
            document.getElementById('modalPotentialPayout').textContent = parseFloat(slip.potential_payout).toFixed(2);

            const status = slip.status || (slip.is_cancelled ? 'cancelled' : (slip.is_paid ? 'paid' : 'pending'));
            document.getElementById('modalStatus').textContent = status.charAt(0).toUpperCase() + status.slice(1);

            document.getElementById('modalCreatedAt').textContent = new Date(slip.created_at).toLocaleString();

            // Fill in bet details
            const betsList = document.getElementById('modalBetsList');
            betsList.innerHTML = '';

            bets.forEach(bet => {
                const row = document.createElement('tr');

                const typeCell = document.createElement('td');
                typeCell.textContent = bet.bet_type;
                row.appendChild(typeCell);

                const descCell = document.createElement('td');
                descCell.textContent = bet.bet_description;
                row.appendChild(descCell);

                const amountCell = document.createElement('td');
                amountCell.textContent = '$' + parseFloat(bet.bet_amount).toFixed(2);
                row.appendChild(amountCell);

                const multiplierCell = document.createElement('td');
                multiplierCell.textContent = parseFloat(bet.multiplier).toFixed(2) + 'x';
                row.appendChild(multiplierCell);

                const returnCell = document.createElement('td');
                returnCell.textContent = '$' + parseFloat(bet.potential_return).toFixed(2);
                row.appendChild(returnCell);

                betsList.appendChild(row);
            });

            // Set up print button
            document.getElementById('printSlipBtn').onclick = function() {
                window.open(`../print_slip.php?slip_id=${slip.slip_id}`, '_blank');
            };

            // Show the modal
            modal.style.display = 'block';
        }
    </script>
</body>
</html>