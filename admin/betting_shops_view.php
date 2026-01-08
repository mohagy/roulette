<?php
// View Betting Shop Details
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Check if shop ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: betting_shops.php');
    exit;
}

$shop_id = intval($_GET['id']);

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "roulette";

date_default_timezone_set('America/Guyana');

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get shop details
$shop = null;
$stmt = $conn->prepare("SELECT * FROM betting_shops WHERE shop_id = ?");
$stmt->bind_param("i", $shop_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: betting_shops.php');
    exit;
}

$shop = $result->fetch_assoc();

// Get shop users
$shop_users = [];
$stmt = $conn->prepare("
    SELECT su.*, u.username, u.role as user_role, u.cash_balance, u.created_at as user_created
    FROM shop_users su
    JOIN users u ON su.user_id = u.user_id
    WHERE su.shop_id = ?
    ORDER BY su.assigned_at DESC
");
$stmt->bind_param("i", $shop_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $shop_users[] = $row;
}

// Get shop performance (last 30 days)
$performance = [];
$stmt = $conn->prepare("
    SELECT * FROM shop_performance 
    WHERE shop_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY date DESC
");
$stmt->bind_param("i", $shop_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $performance[] = $row;
}

// Get summary statistics
$stats = [
    'total_users' => count($shop_users),
    'active_users' => 0,
    'total_bets_30d' => 0,
    'total_commission_30d' => 0,
    'avg_daily_bets' => 0
];

foreach ($shop_users as $user) {
    if ($user['status'] === 'active') {
        $stats['active_users']++;
    }
}

foreach ($performance as $perf) {
    $stats['total_bets_30d'] += $perf['total_bets'];
    $stats['total_commission_30d'] += $perf['total_commission'];
}

$stats['avg_daily_bets'] = count($performance) > 0 ? $stats['total_bets_30d'] / count($performance) : 0;

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($shop['shop_name']); ?> - Shop Details</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .shop-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .status-badge {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 600;
        }
        .status-active { background: #28a745; }
        .status-inactive { background: #6c757d; }
        .status-suspended { background: #dc3545; }
        .info-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        .info-card .card-header {
            background: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            font-weight: 600;
            color: #5a5c69;
        }
        .stat-item {
            text-align: center;
            padding: 1rem;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #4e73df;
        }
        .stat-label {
            color: #858796;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #4e73df;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        .performance-chart {
            height: 300px;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <nav class="top-navbar">
            <div class="navbar-search">
                <input type="text" class="search-input" placeholder="Search...">
            </div>
            <ul class="navbar-nav">
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
                    <i class="fas fa-store"></i> Shop Details
                </h1>
                <div class="breadcrumb">
                    <div class="breadcrumb-item"><a href="index.php">Admin</a></div>
                    <div class="breadcrumb-item"><a href="betting_shops.php">Betting Shops</a></div>
                    <div class="breadcrumb-item active"><?php echo htmlspecialchars($shop['shop_code']); ?></div>
                </div>
                <div class="page-actions">
                    <a href="betting_shops_edit.php?id=<?php echo $shop['shop_id']; ?>" class="btn btn-warning">
                        <i class="fas fa-edit"></i> Edit Shop
                    </a>
                    <a href="betting_shops_users.php?shop_id=<?php echo $shop['shop_id']; ?>" class="btn btn-info">
                        <i class="fas fa-users"></i> Manage Users
                    </a>
                    <a href="betting_shops.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>
            </div>

            <!-- Shop Header -->
            <div class="shop-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="mb-2"><?php echo htmlspecialchars($shop['shop_name']); ?></h2>
                        <p class="mb-2">
                            <strong>Code:</strong> <?php echo htmlspecialchars($shop['shop_code']); ?> |
                            <strong>Manager:</strong> <?php echo htmlspecialchars($shop['manager_name']); ?>
                        </p>
                        <p class="mb-0">
                            <i class="fas fa-map-marker-alt"></i>
                            <?php echo htmlspecialchars($shop['address'] . ', ' . $shop['city']); ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="status-badge status-<?php echo $shop['status']; ?>">
                            <?php echo ucfirst($shop['status']); ?>
                        </span>
                        <div class="mt-2">
                            <small>Commission Rate: <?php echo $shop['commission_rate']; ?>%</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="info-card">
                        <div class="card-body stat-item">
                            <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                            <div class="stat-label">Total Users</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="info-card">
                        <div class="card-body stat-item">
                            <div class="stat-value"><?php echo $stats['active_users']; ?></div>
                            <div class="stat-label">Active Users</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="info-card">
                        <div class="card-body stat-item">
                            <div class="stat-value">$<?php echo number_format($stats['total_bets_30d'], 0); ?></div>
                            <div class="stat-label">30-Day Bets</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="info-card">
                        <div class="card-body stat-item">
                            <div class="stat-value">$<?php echo number_format($stats['total_commission_30d'], 0); ?></div>
                            <div class="stat-label">30-Day Commission</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Shop Information -->
                <div class="col-lg-6">
                    <div class="info-card">
                        <div class="card-header">
                            <i class="fas fa-info-circle"></i> Shop Information
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Phone:</strong></td>
                                    <td><?php echo htmlspecialchars($shop['phone'] ?: 'Not provided'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Email:</strong></td>
                                    <td><?php echo htmlspecialchars($shop['email'] ?: 'Not provided'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Operating Hours:</strong></td>
                                    <td><?php echo date('g:i A', strtotime($shop['opening_time'])) . ' - ' . date('g:i A', strtotime($shop['closing_time'])); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Created:</strong></td>
                                    <td><?php echo date('M d, Y g:i A', strtotime($shop['created_at'])); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Last Updated:</strong></td>
                                    <td><?php echo date('M d, Y g:i A', strtotime($shop['updated_at'])); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Assigned Users -->
                <div class="col-lg-6">
                    <div class="info-card">
                        <div class="card-header">
                            <i class="fas fa-users"></i> Assigned Users (<?php echo count($shop_users); ?>)
                        </div>
                        <div class="card-body">
                            <?php if (empty($shop_users)): ?>
                                <p class="text-muted text-center">No users assigned to this shop yet.</p>
                                <div class="text-center">
                                    <a href="betting_shops_users.php?shop_id=<?php echo $shop['shop_id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus"></i> Assign Users
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Role</th>
                                                <th>Status</th>
                                                <th>Assigned</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($shop_users as $user): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="user-avatar me-2">
                                                            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                                        </div>
                                                        <div>
                                                            <div class="fw-bold"><?php echo htmlspecialchars($user['username']); ?></div>
                                                            <small class="text-muted">$<?php echo number_format($user['cash_balance'], 2); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo ucfirst($user['role']); ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                        <?php echo ucfirst($user['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small><?php echo date('M d, Y', strtotime($user['assigned_at'])); ?></small>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="betting_shops_users.php?shop_id=<?php echo $shop['shop_id']; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-cog"></i> Manage Users
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Chart -->
            <?php if (!empty($performance)): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <div class="info-card">
                        <div class="card-header">
                            <i class="fas fa-chart-line"></i> Performance Trend (Last 30 Days)
                        </div>
                        <div class="card-body">
                            <div class="performance-chart">
                                <canvas id="performanceChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Performance Chart
        <?php if (!empty($performance)): ?>
        const performanceData = <?php echo json_encode(array_reverse($performance)); ?>;
        
        const ctx = document.getElementById('performanceChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: performanceData.map(item => new Date(item.date).toLocaleDateString()),
                datasets: [{
                    label: 'Daily Bets',
                    data: performanceData.map(item => parseFloat(item.total_bets)),
                    borderColor: '#4e73df',
                    backgroundColor: 'rgba(78, 115, 223, 0.1)',
                    tension: 0.3,
                    fill: true
                }, {
                    label: 'Commission',
                    data: performanceData.map(item => parseFloat(item.total_commission)),
                    borderColor: '#1cc88a',
                    backgroundColor: 'rgba(28, 200, 138, 0.1)',
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toFixed(0);
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': $' + context.parsed.y.toFixed(2);
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
