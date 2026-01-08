<?php
// Stock/Inventory Department Dashboard
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Check if user has stock department access
$user_role = $_SESSION['role'] ?? '';
$allowed_roles = ['admin', 'stock_manager', 'stock_staff'];

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
    'total_inventory_items' => 0,
    'low_stock_alerts' => 0,
    'pending_orders' => 0,
    'open_equipment_issues' => 0,
    'active_vendors' => 0,
    'pending_deliveries' => 0
];

// Total inventory items
$result = $conn->query("SELECT COUNT(*) as count FROM inventory_items");
if ($result && $result->num_rows > 0) {
    $stats['total_inventory_items'] = $result->fetch_assoc()['count'];
}

// Low stock alerts
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM shop_inventory si 
    JOIN inventory_items ii ON si.item_id = ii.item_id 
    WHERE si.current_stock <= ii.reorder_level
");
if ($result && $result->num_rows > 0) {
    $stats['low_stock_alerts'] = $result->fetch_assoc()['count'];
}

// Pending purchase orders
$result = $conn->query("SELECT COUNT(*) as count FROM purchase_orders WHERE status IN ('draft', 'sent', 'confirmed')");
if ($result && $result->num_rows > 0) {
    $stats['pending_orders'] = $result->fetch_assoc()['count'];
}

// Open equipment issues
$result = $conn->query("SELECT COUNT(*) as count FROM equipment_issues WHERE status IN ('open', 'assigned', 'in_progress')");
if ($result && $result->num_rows > 0) {
    $stats['open_equipment_issues'] = $result->fetch_assoc()['count'];
}

// Active vendors
$result = $conn->query("SELECT COUNT(*) as count FROM vendors WHERE status = 'active'");
if ($result && $result->num_rows > 0) {
    $stats['active_vendors'] = $result->fetch_assoc()['count'];
}

// Pending deliveries
$result = $conn->query("SELECT COUNT(*) as count FROM purchase_orders WHERE status = 'shipped'");
if ($result && $result->num_rows > 0) {
    $stats['pending_deliveries'] = $result->fetch_assoc()['count'];
}

// Get recent stock movements
$recent_movements = [];
$result = $conn->query("
    SELECT 
        sm.movement_type,
        sm.quantity,
        sm.created_at,
        ii.item_name,
        bs.shop_name,
        u.username as created_by
    FROM stock_movements sm
    JOIN inventory_items ii ON sm.item_id = ii.item_id
    JOIN betting_shops bs ON sm.shop_id = bs.shop_id
    LEFT JOIN users u ON sm.created_by = u.user_id
    ORDER BY sm.created_at DESC
    LIMIT 10
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recent_movements[] = $row;
    }
}

// Get low stock items
$low_stock_items = [];
$result = $conn->query("
    SELECT 
        ii.item_name,
        ii.item_code,
        bs.shop_name,
        si.current_stock,
        ii.reorder_level,
        ii.unit_cost
    FROM shop_inventory si 
    JOIN inventory_items ii ON si.item_id = ii.item_id 
    JOIN betting_shops bs ON si.shop_id = bs.shop_id
    WHERE si.current_stock <= ii.reorder_level
    ORDER BY (si.current_stock / ii.reorder_level) ASC
    LIMIT 10
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $low_stock_items[] = $row;
    }
}

// Get inventory distribution by category
$inventory_distribution = [];
$result = $conn->query("
    SELECT 
        ii.category,
        COUNT(*) as item_count,
        SUM(si.current_stock * ii.unit_cost) as total_value
    FROM inventory_items ii
    LEFT JOIN shop_inventory si ON ii.item_id = si.item_id
    GROUP BY ii.category
    ORDER BY total_value DESC
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $inventory_distribution[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock/Inventory Department Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #8B4513 0%, #D2691E 100%);
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
            background: linear-gradient(135deg, #8B4513 0%, #D2691E 100%);
            color: white;
        }
        .movement-item {
            padding: 10px;
            border-left: 3px solid #8B4513;
            margin-bottom: 10px;
            background: #f8f9fc;
            border-radius: 5px;
        }
        .movement-in { border-left-color: #28a745; }
        .movement-out { border-left-color: #dc3545; }
        .movement-transfer { border-left-color: #007bff; }
        .movement-adjustment { border-left-color: #ffc107; }
        .low-stock-item {
            padding: 8px;
            margin-bottom: 8px;
            border-radius: 5px;
            background: #fff3cd;
            border-left: 3px solid #ffc107;
        }
        .critical-stock {
            background: #f8d7da;
            border-left-color: #dc3545;
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
            <h4 class="text-warning"><i class="fas fa-boxes"></i> Stock Dept</h4>
        </div>
        <nav class="nav flex-column px-3">
            <a class="nav-link active" href="index.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a class="nav-link" href="inventory.php">
                <i class="fas fa-warehouse"></i> Inventory Management
            </a>
            <a class="nav-link" href="purchase_orders.php">
                <i class="fas fa-shopping-cart"></i> Purchase Orders
            </a>
            <a class="nav-link" href="vendors.php">
                <i class="fas fa-truck"></i> Vendor Management
            </a>
            <a class="nav-link" href="equipment_issues.php">
                <i class="fas fa-exclamation-triangle"></i> Equipment Issues
            </a>
            <a class="nav-link" href="stock_movements.php">
                <i class="fas fa-exchange-alt"></i> Stock Movements
            </a>
            <a class="nav-link" href="audits.php">
                <i class="fas fa-clipboard-check"></i> Stock Audits
            </a>
            <a class="nav-link" href="distribution.php">
                <i class="fas fa-shipping-fast"></i> Distribution Tracking
            </a>
            <a class="nav-link" href="analytics.php">
                <i class="fas fa-chart-bar"></i> Analytics & Reports
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
                <h1 class="text-white">Stock/Inventory Department Dashboard</h1>
                <p class="text-white-50 mb-0">
                    <span class="real-time-indicator"></span>
                    Inventory monitoring | Last updated: <span id="last-updated"><?php echo date('g:i:s A'); ?></span>
                </p>
            </div>
            <div>
                <button class="btn btn-light" onclick="refreshDashboard()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <a href="purchase_orders.php?action=new" class="btn btn-warning">
                    <i class="fas fa-plus"></i> New Purchase Order
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card stat-card border-left-primary">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Inventory Items</div>
                                <div class="stat-value text-gray-800" id="total-items"><?php echo $stats['total_inventory_items']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-boxes stat-icon text-gray-300"></i>
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
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Low Stock Alerts</div>
                                <div class="stat-value text-gray-800" id="low-stock"><?php echo $stats['low_stock_alerts']; ?></div>
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
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Pending Orders</div>
                                <div class="stat-value text-gray-800" id="pending-orders"><?php echo $stats['pending_orders']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-shopping-cart stat-icon text-gray-300"></i>
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
                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Equipment Issues</div>
                                <div class="stat-value text-gray-800" id="equipment-issues"><?php echo $stats['open_equipment_issues']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-tools stat-icon text-gray-300"></i>
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
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Vendors</div>
                                <div class="stat-value text-gray-800" id="active-vendors"><?php echo $stats['active_vendors']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-truck stat-icon text-gray-300"></i>
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
                                <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Pending Deliveries</div>
                                <div class="stat-value text-gray-800" id="pending-deliveries"><?php echo $stats['pending_deliveries']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-shipping-fast stat-icon text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts and Activities -->
        <div class="row">
            <!-- Inventory Distribution Chart -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-chart-pie"></i> Inventory Distribution by Category
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="position: relative; height: 300px;">
                            <canvas id="inventoryChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Stock Movements -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-exchange-alt"></i> Recent Stock Movements
                        </h6>
                    </div>
                    <div class="card-body">
                        <div id="recent-movements" style="max-height: 300px; overflow-y: auto;">
                            <?php if (empty($recent_movements)): ?>
                                <p class="text-muted text-center">No recent stock movements</p>
                            <?php else: ?>
                                <?php foreach ($recent_movements as $movement): ?>
                                <div class="movement-item movement-<?php echo $movement['movement_type']; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?php echo ucfirst($movement['movement_type']); ?></strong>
                                            <br>
                                            <small><?php echo htmlspecialchars($movement['item_name']); ?></small>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($movement['shop_name']); ?></small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-<?php echo $movement['movement_type'] === 'in' ? 'success' : ($movement['movement_type'] === 'out' ? 'danger' : 'info'); ?>">
                                                <?php echo $movement['movement_type'] === 'out' ? '-' : '+'; ?><?php echo $movement['quantity']; ?>
                                            </span>
                                            <br>
                                            <small class="text-muted"><?php echo date('M d H:i', strtotime($movement['created_at'])); ?></small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Low Stock Alerts and Quick Actions -->
        <div class="row">
            <!-- Low Stock Alerts -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-warning">
                            <i class="fas fa-exclamation-triangle"></i> Low Stock Alerts (<?php echo count($low_stock_items); ?>)
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($low_stock_items)): ?>
                            <div class="text-center py-3">
                                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                <p class="text-muted">All items are adequately stocked!</p>
                            </div>
                        <?php else: ?>
                            <div style="max-height: 300px; overflow-y: auto;">
                                <?php foreach ($low_stock_items as $item): ?>
                                <div class="low-stock-item <?php echo $item['current_stock'] == 0 ? 'critical-stock' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                            <small class="text-muted">(<?php echo htmlspecialchars($item['item_code']); ?>)</small>
                                            <br>
                                            <small><?php echo htmlspecialchars($item['shop_name']); ?></small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-<?php echo $item['current_stock'] == 0 ? 'danger' : 'warning'; ?>">
                                                <?php echo $item['current_stock']; ?> / <?php echo $item['reorder_level']; ?>
                                            </span>
                                            <br>
                                            <small class="text-muted">$<?php echo number_format($item['unit_cost'], 2); ?> each</small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-bolt"></i> Quick Actions
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="purchase_orders.php?action=new" class="btn btn-warning">
                                <i class="fas fa-plus"></i> Create Purchase Order
                            </a>
                            <a href="stock_movements.php?action=new" class="btn btn-info">
                                <i class="fas fa-exchange-alt"></i> Record Stock Movement
                            </a>
                            <a href="equipment_issues.php?action=new" class="btn btn-danger">
                                <i class="fas fa-exclamation-triangle"></i> Report Equipment Issue
                            </a>
                            <a href="audits.php?action=new" class="btn btn-success">
                                <i class="fas fa-clipboard-check"></i> Start Stock Audit
                            </a>
                            <a href="vendors.php?action=new" class="btn btn-primary">
                                <i class="fas fa-truck"></i> Add New Vendor
                            </a>
                            <a href="analytics.php" class="btn btn-secondary">
                                <i class="fas fa-chart-bar"></i> View Analytics
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Inventory Distribution Chart
        const inventoryData = <?php echo json_encode($inventory_distribution); ?>;
        
        if (inventoryData.length > 0) {
            const ctx = document.getElementById('inventoryChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: inventoryData.map(item => item.category.charAt(0).toUpperCase() + item.category.slice(1)),
                    datasets: [{
                        data: inventoryData.map(item => parseFloat(item.total_value)),
                        backgroundColor: [
                            '#8B4513', '#D2691E', '#CD853F', '#DEB887', '#F4A460', '#BC8F8F'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': $' + context.parsed.toFixed(2);
                                }
                            }
                        }
                    }
                }
            });
        }

        // Real-time updates
        function refreshDashboard() {
            fetch('api/stock_dashboard_data.php')
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
            document.getElementById('total-items').textContent = stats.total_inventory_items;
            document.getElementById('low-stock').textContent = stats.low_stock_alerts;
            document.getElementById('pending-orders').textContent = stats.pending_orders;
            document.getElementById('equipment-issues').textContent = stats.open_equipment_issues;
            document.getElementById('active-vendors').textContent = stats.active_vendors;
            document.getElementById('pending-deliveries').textContent = stats.pending_deliveries;
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
