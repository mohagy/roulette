<?php
// Sales Department Dashboard
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Check if user has sales department access
$user_role = $_SESSION['role'] ?? '';
$allowed_roles = ['admin', 'sales_manager', 'sales_staff'];

// For now, allow admin access - later implement proper department role checking
if (!in_array($user_role, $allowed_roles) && $user_role !== 'admin') {
    header('Location: ../index.php');
    exit;
}

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

// Get dashboard statistics
$stats = [
    'total_shops' => 0,
    'active_campaigns' => 0,
    'pending_requests' => 0,
    'low_inventory_items' => 0,
    'total_revenue_today' => 0,
    'equipment_maintenance_due' => 0
];

// Total shops
$result = $conn->query("SELECT COUNT(*) as count FROM betting_shops WHERE status = 'active'");
if ($result && $result->num_rows > 0) {
    $stats['total_shops'] = $result->fetch_assoc()['count'];
}

// Active campaigns
$result = $conn->query("SELECT COUNT(*) as count FROM marketing_campaigns WHERE status = 'active'");
if ($result && $result->num_rows > 0) {
    $stats['active_campaigns'] = $result->fetch_assoc()['count'];
}

// Pending service requests
$result = $conn->query("SELECT COUNT(*) as count FROM service_requests WHERE status IN ('pending', 'assigned')");
if ($result && $result->num_rows > 0) {
    $stats['pending_requests'] = $result->fetch_assoc()['count'];
}

// Low inventory items
$result = $conn->query("
    SELECT COUNT(*) as count
    FROM shop_inventory si
    JOIN inventory_items ii ON si.item_id = ii.item_id
    WHERE si.current_stock <= ii.reorder_level
");
if ($result && $result->num_rows > 0) {
    $stats['low_inventory_items'] = $result->fetch_assoc()['count'];
}

// Today's revenue
$result = $conn->query("
    SELECT SUM(total_bets) as revenue
    FROM shop_performance
    WHERE date = CURDATE()
");
if ($result && $result->num_rows > 0) {
    $stats['total_revenue_today'] = $result->fetch_assoc()['revenue'] ?? 0;
}

// Equipment maintenance due
$result = $conn->query("
    SELECT COUNT(*) as count
    FROM equipment
    WHERE next_maintenance <= CURDATE() AND status = 'active'
");
if ($result && $result->num_rows > 0) {
    $stats['equipment_maintenance_due'] = $result->fetch_assoc()['count'];
}

// Get recent activities
$recent_activities = [];
$sql = "
    (SELECT 'campaign' as type, campaign_name as description, created_at as activity_time
     FROM marketing_campaigns
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))
    UNION ALL
    (SELECT 'service' as type, CONCAT('Service request: ', description) as description, requested_date as activity_time
     FROM service_requests
     WHERE requested_date >= DATE_SUB(NOW(), INTERVAL 7 DAY))
    ORDER BY activity_time DESC
    LIMIT 10
";

$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recent_activities[] = $row;
    }
}

// Get shop performance data for chart
$shop_performance = [];
$result = $conn->query("
    SELECT bs.shop_name, COALESCE(sp.total_bets, 0) as today_bets
    FROM betting_shops bs
    LEFT JOIN shop_performance sp ON bs.shop_id = sp.shop_id AND sp.date = CURDATE()
    WHERE bs.status = 'active'
    ORDER BY today_bets DESC
    LIMIT 10
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $shop_performance[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Department Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Nunito', sans-serif;
        }
        .sidebar {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            min-height: 100vh;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            background: rgba(255,255,255,0.95);
            margin-bottom: 20px;
        }
        .stat-card {
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
        }
        .nav-link {
            color: #5a5c69;
            padding: 12px 20px;
            border-radius: 10px;
            margin: 2px 0;
            transition: all 0.3s ease;
        }
        .nav-link:hover, .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
        .activity-item {
            padding: 10px;
            border-left: 3px solid #667eea;
            margin-bottom: 10px;
            background: #f8f9fc;
            border-radius: 5px;
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
    <!-- Sidebar -->
    <div class="sidebar position-fixed">
        <div class="p-4">
            <h4 class="text-primary"><i class="fas fa-chart-line"></i> Sales Dept</h4>
        </div>
        <nav class="nav flex-column px-3">
            <a class="nav-link active" href="index.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a class="nav-link" href="shops.php">
                <i class="fas fa-store"></i> Shop Operations
            </a>
            <a class="nav-link" href="campaigns.php">
                <i class="fas fa-bullhorn"></i> Marketing Campaigns
            </a>
            <a class="nav-link" href="inventory.php">
                <i class="fas fa-boxes"></i> Inventory Management
            </a>
            <a class="nav-link" href="equipment.php">
                <i class="fas fa-tools"></i> Equipment & Maintenance
            </a>
            <a class="nav-link" href="services.php">
                <i class="fas fa-concierge-bell"></i> Service Requests
            </a>
            <a class="nav-link" href="analytics.php">
                <i class="fas fa-chart-bar"></i> Performance Analytics
            </a>
            <hr>
            <a class="nav-link" href="../admin/index.php">
                <i class="fas fa-cog"></i> Admin Panel
            </a>
            <a class="nav-link" href="../logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="text-white">Sales Department Dashboard</h1>
                <p class="text-white-50 mb-0">
                    <span class="real-time-indicator"></span>
                    Real-time monitoring | Last updated: <span id="last-updated"><?php echo date('g:i:s A'); ?></span>
                </p>
            </div>
            <div>
                <button class="btn btn-light" onclick="refreshDashboard()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card stat-card border-left-primary">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Active Shops</div>
                                <div class="stat-value text-gray-800" id="total-shops"><?php echo $stats['total_shops']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-store stat-icon text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card stat-card border-left-success">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Campaigns</div>
                                <div class="stat-value text-gray-800" id="active-campaigns"><?php echo $stats['active_campaigns']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-bullhorn stat-icon text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card stat-card border-left-warning">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Requests</div>
                                <div class="stat-value text-gray-800" id="pending-requests"><?php echo $stats['pending_requests']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-clock stat-icon text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card stat-card border-left-danger">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Low Inventory</div>
                                <div class="stat-value text-gray-800" id="low-inventory"><?php echo $stats['low_inventory_items']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-exclamation-triangle stat-icon text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card stat-card border-left-info">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Today's Revenue</div>
                                <div class="stat-value text-gray-800" id="today-revenue">$<?php echo number_format($stats['total_revenue_today'], 0); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-dollar-sign stat-icon text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card stat-card border-left-secondary">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Maintenance Due</div>
                                <div class="stat-value text-gray-800" id="maintenance-due"><?php echo $stats['equipment_maintenance_due']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-wrench stat-icon text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts and Activities -->
        <div class="row">
            <!-- Shop Performance Chart -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-chart-bar"></i> Today's Shop Performance
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="shopPerformanceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-list"></i> Recent Activities
                        </h6>
                    </div>
                    <div class="card-body">
                        <div id="recent-activities">
                            <?php if (empty($recent_activities)): ?>
                                <p class="text-muted text-center">No recent activities</p>
                            <?php else: ?>
                                <?php foreach ($recent_activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <i class="fas fa-<?php echo $activity['type'] === 'campaign' ? 'bullhorn' : 'tools'; ?> text-primary me-2"></i>
                                            <small><?php echo htmlspecialchars($activity['description']); ?></small>
                                        </div>
                                        <small class="text-muted"><?php echo date('M d', strtotime($activity['activity_time'])); ?></small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-bolt"></i> Quick Actions
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <a href="campaigns.php?action=new" class="btn btn-success btn-block mb-2">
                                    <i class="fas fa-plus"></i> New Campaign
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="services.php?action=new" class="btn btn-warning btn-block mb-2">
                                    <i class="fas fa-plus"></i> Service Request
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="inventory.php?action=restock" class="btn btn-info btn-block mb-2">
                                    <i class="fas fa-boxes"></i> Restock Items
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="analytics.php" class="btn btn-primary btn-block mb-2">
                                    <i class="fas fa-chart-line"></i> View Analytics
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Shop Performance Chart
        const shopData = <?php echo json_encode($shop_performance); ?>;

        const ctx = document.getElementById('shopPerformanceChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: shopData.map(shop => shop.shop_name),
                datasets: [{
                    label: 'Today\'s Bets ($)',
                    data: shopData.map(shop => parseFloat(shop.today_bets)),
                    backgroundColor: 'rgba(102, 126, 234, 0.8)',
                    borderColor: 'rgba(102, 126, 234, 1)',
                    borderWidth: 1
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
                                return 'Bets: $' + context.parsed.y.toFixed(2);
                            }
                        }
                    }
                }
            }
        });

        // Real-time updates
        function refreshDashboard() {
            fetch('api/sales_dashboard_data.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateStats(data.stats);
                        updateLastUpdated();
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function updateStats(stats) {
            document.getElementById('total-shops').textContent = stats.total_shops;
            document.getElementById('active-campaigns').textContent = stats.active_campaigns;
            document.getElementById('pending-requests').textContent = stats.pending_requests;
            document.getElementById('low-inventory').textContent = stats.low_inventory_items;
            document.getElementById('today-revenue').textContent = '$' + parseInt(stats.total_revenue_today).toLocaleString();
            document.getElementById('maintenance-due').textContent = stats.equipment_maintenance_due;
        }

        function updateLastUpdated() {
            document.getElementById('last-updated').textContent = new Date().toLocaleTimeString();
        }

        // Auto-refresh every 30 seconds
        setInterval(refreshDashboard, 30000);

        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            updateLastUpdated();
        });
    </script>
</body>
</html>
