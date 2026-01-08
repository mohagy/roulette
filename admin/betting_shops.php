<?php
// Betting Shops Management
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "roulette";

// Set timezone to Georgetown, Guyana (GMT-4)
date_default_timezone_set('America/Guyana');

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if betting_shops table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'betting_shops'");
if ($tableCheck->num_rows == 0) {
    header("Location: betting_shops_setup.php");
    exit;
}

// Get statistics
$stats = [
    'total_shops' => 0,
    'active_shops' => 0,
    'total_users' => 0,
    'total_revenue' => 0
];

// Count total shops
$result = $conn->query("SELECT COUNT(*) as count FROM betting_shops");
if ($result && $result->num_rows > 0) {
    $stats['total_shops'] = $result->fetch_assoc()['count'];
}

// Count active shops
$result = $conn->query("SELECT COUNT(*) as count FROM betting_shops WHERE status = 'active'");
if ($result && $result->num_rows > 0) {
    $stats['active_shops'] = $result->fetch_assoc()['count'];
}

// Count users assigned to shops
$result = $conn->query("SELECT COUNT(DISTINCT user_id) as count FROM shop_users WHERE status = 'active'");
if ($result && $result->num_rows > 0) {
    $stats['total_users'] = $result->fetch_assoc()['count'];
}

// Get total revenue (from shop_performance table)
$result = $conn->query("SELECT SUM(total_bets) as revenue FROM shop_performance WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
if ($result && $result->num_rows > 0) {
    $stats['total_revenue'] = $result->fetch_assoc()['revenue'] ?? 0;
}

// Get all betting shops with user counts
$shops = [];
$sql = "SELECT 
            bs.*,
            COUNT(DISTINCT su.user_id) as user_count,
            COALESCE(sp.total_bets, 0) as today_bets,
            COALESCE(sp.total_commission, 0) as today_commission
        FROM betting_shops bs
        LEFT JOIN shop_users su ON bs.shop_id = su.shop_id AND su.status = 'active'
        LEFT JOIN shop_performance sp ON bs.shop_id = sp.shop_id AND sp.date = CURDATE()
        GROUP BY bs.shop_id
        ORDER BY bs.created_at DESC";

$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $shops[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Betting Shops Management - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        .shop-card {
            transition: transform 0.2s ease-in-out;
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .shop-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
        }
        .status-active { background: #28a745; color: white; }
        .status-inactive { background: #6c757d; color: white; }
        .status-suspended { background: #dc3545; color: white; }
        .shop-stats {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 1rem;
        }
        .quick-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        .real-time-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #28a745;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <nav class="top-navbar">
            <div class="navbar-search">
                <input type="text" class="search-input" placeholder="Search shops...">
            </div>
            <ul class="navbar-nav">
                <li class="nav-item">
                    <span class="real-time-indicator"></span>
                    <small class="text-muted ms-1">Live Updates</small>
                </li>
                <li class="nav-item">
                    <a href="../logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </li>
            </ul>
        </nav>

        <div class="content-wrapper">
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-store"></i> Betting Shops Management
                </h1>
                <div class="breadcrumb">
                    <div class="breadcrumb-item"><a href="index.php">Admin</a></div>
                    <div class="breadcrumb-item active">Betting Shops</div>
                </div>
                <div class="page-actions">
                    <button class="btn btn-primary" onclick="openAddShopModal()">
                        <i class="fas fa-plus"></i> Add New Shop
                    </button>
                    <button class="btn btn-success" onclick="refreshData()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Total Shops</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="total-shops">
                                        <?php echo $stats['total_shops']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-store fa-2x text-gray-300"></i>
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
                                        Active Shops</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="active-shops">
                                        <?php echo $stats['active_shops']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check-circle fa-2x text-gray-300"></i>
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
                                        Assigned Users</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="total-users">
                                        <?php echo $stats['total_users']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
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
                                        30-Day Revenue</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="total-revenue">
                                        $<?php echo number_format($stats['total_revenue'], 2); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Shops Table -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-list"></i> All Betting Shops
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="shopsTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Shop Code</th>
                                    <th>Shop Name</th>
                                    <th>Location</th>
                                    <th>Manager</th>
                                    <th>Users</th>
                                    <th>Today's Bets</th>
                                    <th>Commission</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="shops-table-body">
                                <?php foreach ($shops as $shop): ?>
                                <tr data-shop-id="<?php echo $shop['shop_id']; ?>">
                                    <td><strong><?php echo htmlspecialchars($shop['shop_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($shop['shop_name']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($shop['city']); ?>
                                        <?php if ($shop['address']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($shop['address']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($shop['manager_name']); ?>
                                        <?php if ($shop['manager_phone']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($shop['manager_phone']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $shop['user_count']; ?> users</span>
                                    </td>
                                    <td>
                                        <strong>$<?php echo number_format($shop['today_bets'], 2); ?></strong>
                                    </td>
                                    <td>
                                        <strong class="text-success">$<?php echo number_format($shop['today_commission'], 2); ?></strong>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $shop['status']; ?>">
                                            <?php echo ucfirst($shop['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="quick-actions">
                                            <button class="btn btn-sm btn-primary" onclick="viewShop(<?php echo $shop['shop_id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-warning" onclick="editShop(<?php echo $shop['shop_id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-info" onclick="manageUsers(<?php echo $shop['shop_id']; ?>)">
                                                <i class="fas fa-users"></i>
                                            </button>
                                        </div>
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
        // Real-time updates
        let updateInterval;

        function startRealTimeUpdates() {
            updateInterval = setInterval(refreshData, 30000); // Update every 30 seconds
        }

        function refreshData() {
            fetch('api/betting_shops_data.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateStats(data.stats);
                        updateShopsTable(data.shops);
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function updateStats(stats) {
            document.getElementById('total-shops').textContent = stats.total_shops;
            document.getElementById('active-shops').textContent = stats.active_shops;
            document.getElementById('total-users').textContent = stats.total_users;
            document.getElementById('total-revenue').textContent = '$' + parseFloat(stats.total_revenue).toFixed(2);
        }

        function updateShopsTable(shops) {
            // Update table content
            console.log('Updating shops table with', shops.length, 'shops');
        }

        function openAddShopModal() {
            window.location.href = 'betting_shops_add.php';
        }

        function viewShop(shopId) {
            window.location.href = 'betting_shops_view.php?id=' + shopId;
        }

        function editShop(shopId) {
            window.location.href = 'betting_shops_edit.php?id=' + shopId;
        }

        function manageUsers(shopId) {
            window.location.href = 'betting_shops_users.php?shop_id=' + shopId;
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            startRealTimeUpdates();
        });
    </script>
</body>
</html>
