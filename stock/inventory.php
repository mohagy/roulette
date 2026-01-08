<?php
// Stock/Inventory Management System
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

// Get user information
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Unknown';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - Stock Department</title>
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
        .stock-level-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .stock-high { background-color: #28a745; }
        .stock-medium { background-color: #ffc107; }
        .stock-low { background-color: #dc3545; }
        .stock-out { background-color: #6c757d; }
        .category-badge {
            background: rgba(139, 69, 19, 0.1);
            color: #8B4513;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        .btn-action {
            padding: 4px 8px;
            font-size: 0.75rem;
            border-radius: 4px;
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
        .search-filter-bar {
            background: rgba(255,255,255,0.95);
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        .table th {
            background-color: #f8f9fc;
            border: none;
            font-weight: 600;
            color: #5a5c69;
        }
        .table td {
            border: none;
            vertical-align: middle;
        }
        .table tbody tr:hover {
            background-color: #f8f9fc;
        }
        .modal-header {
            background: linear-gradient(135deg, #8B4513 0%, #D2691E 100%);
            color: white;
            border-bottom: none;
        }
        .modal-header .btn-close {
            filter: invert(1);
        }
        .form-floating label {
            color: #6c757d;
        }
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
        .border-left-primary {
            border-left: 0.25rem solid #4e73df !important;
        }
        .border-left-success {
            border-left: 0.25rem solid #1cc88a !important;
        }
        .border-left-warning {
            border-left: 0.25rem solid #f6c23e !important;
        }
        .border-left-danger {
            border-left: 0.25rem solid #e74a3b !important;
        }
        .text-xs {
            font-size: 0.75rem;
        }
        .text-gray-800 {
            color: #5a5c69 !important;
        }
        .text-gray-300 {
            color: #dddfeb !important;
        }
        .text-primary {
            color: #4e73df !important;
        }
        .text-success {
            color: #1cc88a !important;
        }
        .text-warning {
            color: #f6c23e !important;
        }
        .text-danger {
            color: #e74a3b !important;
        }
        .font-weight-bold {
            font-weight: 700 !important;
        }
        .text-uppercase {
            text-transform: uppercase !important;
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
            <a class="nav-link" href="index.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a class="nav-link active" href="inventory.php">
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
                <h1 class="text-white">Inventory Management</h1>
                <p class="text-white-50 mb-0">
                    <span class="real-time-indicator"></span>
                    Real-time inventory tracking | Last updated: <span id="last-updated"><?php echo date('g:i:s A'); ?></span>
                </p>
            </div>
            <div>
                <button class="btn btn-light" onclick="refreshInventory()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#addItemModal">
                    <i class="fas fa-plus"></i> Add Item
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-xl-3 col-md-6 col-sm-6">
                <div class="card stat-card border-left-primary">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Items</div>
                                <div class="stat-value text-gray-800" id="totalItems">0</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-boxes stat-icon text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 col-sm-6">
                <div class="card stat-card border-left-warning">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Low Stock Items</div>
                                <div class="stat-value text-gray-800" id="lowStockItems">0</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-exclamation-triangle stat-icon text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 col-sm-6">
                <div class="card stat-card border-left-success">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Value</div>
                                <div class="stat-value text-gray-800" id="totalValue">$0</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-dollar-sign stat-icon text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 col-sm-6">
                <div class="card stat-card border-left-danger">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Out of Stock</div>
                                <div class="stat-value text-gray-800" id="outOfStock">0</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-times-circle stat-icon text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filter Bar -->
        <div class="search-filter-bar">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" id="searchInput" placeholder="Search items...">
                    </div>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="categoryFilter">
                        <option value="">All Categories</option>
                        <option value="equipment">Equipment</option>
                        <option value="supplies">Supplies</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="office">Office</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="stockFilter">
                        <option value="">All Stock Levels</option>
                        <option value="high">High Stock</option>
                        <option value="medium">Medium Stock</option>
                        <option value="low">Low Stock</option>
                        <option value="out">Out of Stock</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="supplierFilter">
                        <option value="">All Suppliers</option>
                        <option value="supplier1">Tech Solutions Ltd</option>
                        <option value="supplier2">Office Depot</option>
                        <option value="supplier3">Equipment Pro</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-warning w-100" data-bs-toggle="modal" data-bs-target="#addItemModal">
                        <i class="fas fa-plus"></i> Add Item
                    </button>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <button class="btn btn-outline-primary w-100 mb-2" onclick="bulkStockUpdate()">
                            <i class="fas fa-edit"></i> Bulk Stock Update
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-success w-100 mb-2" onclick="generateReport()">
                            <i class="fas fa-file-excel"></i> Export Report
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-warning w-100 mb-2" onclick="lowStockAlert()">
                            <i class="fas fa-exclamation-triangle"></i> Low Stock Alert
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-info w-100 mb-2" onclick="stockAudit()">
                            <i class="fas fa-clipboard-check"></i> Stock Audit
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inventory Table -->
        <div class="card">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Inventory Items</h6>
                <div class="d-flex align-items-center">
                    <span class="me-3 small text-muted">Auto-refresh every 30 seconds</span>
                    <button class="btn btn-sm btn-outline-primary" id="refreshInventory">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="inventoryTable">
                        <thead>
                            <tr>
                                <th>Item Code</th>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Current Stock</th>
                                <th>Min Stock</th>
                                <th>Unit Price</th>
                                <th>Total Value</th>
                                <th>Supplier</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="inventoryTableBody">
                            <tr>
                                <td colspan="10" class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2 text-muted">Loading inventory data...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Stock Level Charts -->
        <div class="row mt-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Stock Levels by Category</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="categoryChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Stock Movement Trends</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="movementChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Low Stock Alerts -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-danger">
                            <i class="fas fa-exclamation-triangle"></i> Low Stock Alerts
                        </h6>
                    </div>
                    <div class="card-body">
                        <div id="lowStockAlerts">
                            <div class="text-center py-3">
                                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                <span class="ms-2">Loading alerts...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- End of Main Content -->

    <!-- Add Item Modal -->
    <div class="modal fade" id="addItemModal" tabindex="-1" aria-labelledby="addItemModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addItemModalLabel">
                        <i class="fas fa-plus"></i> Add New Inventory Item
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addItemForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="itemCode" placeholder="Item Code" required>
                                    <label for="itemCode">Item Code</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="itemName" placeholder="Item Name" required>
                                    <label for="itemName">Item Name</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="itemCategory" required>
                                        <option value="">Select Category</option>
                                        <option value="equipment">Equipment</option>
                                        <option value="supplies">Supplies</option>
                                        <option value="maintenance">Maintenance</option>
                                        <option value="office">Office</option>
                                    </select>
                                    <label for="itemCategory">Category</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="itemSupplier" required>
                                        <option value="">Select Supplier</option>
                                        <option value="supplier1">Tech Solutions Ltd</option>
                                        <option value="supplier2">Office Depot</option>
                                        <option value="supplier3">Equipment Pro</option>
                                    </select>
                                    <label for="itemSupplier">Supplier</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="currentStock" placeholder="Current Stock" min="0" required>
                                    <label for="currentStock">Current Stock</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="minStock" placeholder="Minimum Stock" min="0" required>
                                    <label for="minStock">Minimum Stock</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="unitPrice" placeholder="Unit Price" step="0.01" min="0" required>
                                    <label for="unitPrice">Unit Price ($)</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-floating mb-3">
                            <textarea class="form-control" id="itemDescription" placeholder="Description" style="height: 100px"></textarea>
                            <label for="itemDescription">Description</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveItem()">
                        <i class="fas fa-save"></i> Save Item
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Item Modal -->
    <div class="modal fade" id="editItemModal" tabindex="-1" aria-labelledby="editItemModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editItemModalLabel">
                        <i class="fas fa-edit"></i> Edit Inventory Item
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editItemForm">
                        <input type="hidden" id="editItemId">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="editItemCode" placeholder="Item Code" required>
                                    <label for="editItemCode">Item Code</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="editItemName" placeholder="Item Name" required>
                                    <label for="editItemName">Item Name</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="editItemCategory" required>
                                        <option value="">Select Category</option>
                                        <option value="equipment">Equipment</option>
                                        <option value="supplies">Supplies</option>
                                        <option value="maintenance">Maintenance</option>
                                        <option value="office">Office</option>
                                    </select>
                                    <label for="editItemCategory">Category</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="editItemSupplier" required>
                                        <option value="">Select Supplier</option>
                                        <option value="supplier1">Tech Solutions Ltd</option>
                                        <option value="supplier2">Office Depot</option>
                                        <option value="supplier3">Equipment Pro</option>
                                    </select>
                                    <label for="editItemSupplier">Supplier</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="editCurrentStock" placeholder="Current Stock" min="0" required>
                                    <label for="editCurrentStock">Current Stock</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="editMinStock" placeholder="Minimum Stock" min="0" required>
                                    <label for="editMinStock">Minimum Stock</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="editUnitPrice" placeholder="Unit Price" step="0.01" min="0" required>
                                    <label for="editUnitPrice">Unit Price ($)</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-floating mb-3">
                            <textarea class="form-control" id="editItemDescription" placeholder="Description" style="height: 100px"></textarea>
                            <label for="editItemDescription">Description</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="updateItem()">
                        <i class="fas fa-save"></i> Update Item
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Stock Adjustment Modal -->
    <div class="modal fade" id="stockAdjustmentModal" tabindex="-1" aria-labelledby="stockAdjustmentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="stockAdjustmentModalLabel">
                        <i class="fas fa-exchange-alt"></i> Stock Adjustment
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="stockAdjustmentForm">
                        <input type="hidden" id="adjustItemId">
                        <div class="mb-3">
                            <label class="form-label">Item: <span id="adjustItemName" class="fw-bold"></span></label>
                            <p class="text-muted small">Current Stock: <span id="adjustCurrentStock" class="fw-bold"></span></p>
                        </div>
                        <div class="form-floating mb-3">
                            <select class="form-select" id="adjustmentType" required>
                                <option value="">Select Adjustment Type</option>
                                <option value="in">Stock In</option>
                                <option value="out">Stock Out</option>
                                <option value="adjustment">Manual Adjustment</option>
                            </select>
                            <label for="adjustmentType">Adjustment Type</label>
                        </div>
                        <div class="form-floating mb-3">
                            <input type="number" class="form-control" id="adjustmentQuantity" placeholder="Quantity" min="1" required>
                            <label for="adjustmentQuantity">Quantity</label>
                        </div>
                        <div class="form-floating mb-3">
                            <textarea class="form-control" id="adjustmentReason" placeholder="Reason" style="height: 80px" required></textarea>
                            <label for="adjustmentReason">Reason</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="processStockAdjustment()">
                        <i class="fas fa-check"></i> Process Adjustment
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap core JavaScript-->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Inventory management variables
        let inventoryData = [];
        let filteredData = [];
        let categoryChart;
        let movementChart;

        // Initialize inventory system
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Stock inventory system loaded');

            initializeInventory();
            loadInventoryData();
            setupEventListeners();
            initializeCharts();

            // Set up auto-refresh every 30 seconds
            setInterval(loadInventoryData, 30000);
        });

        function initializeInventory() {
            // Set Georgetown timezone for all timestamps
            console.log('Initializing inventory with Georgetown timezone (GMT-4)');
        }

        function setupEventListeners() {
            // Search and filter functionality
            document.getElementById('searchInput').addEventListener('input', filterInventory);
            document.getElementById('categoryFilter').addEventListener('change', filterInventory);
            document.getElementById('stockFilter').addEventListener('change', filterInventory);
            document.getElementById('supplierFilter').addEventListener('change', filterInventory);

            // Refresh button
            document.getElementById('refreshInventory').addEventListener('click', loadInventoryData);

            // Sidebar toggle
            document.getElementById('sidebarToggle')?.addEventListener('click', function() {
                document.body.classList.toggle('sidebar-toggled');
                document.querySelector('.sidebar').classList.toggle('toggled');
            });
        }

        async function loadInventoryData() {
            try {
                // Load real inventory data from database
                const response = await fetch('../api/inventory_api.php?action=get_inventory');
                const data = await response.json();

                if (data.status === 'success') {
                    inventoryData = data.data;
                    filteredData = [...inventoryData];

                    updateOverviewMetrics();
                    displayInventoryTable();
                    updateCharts();
                    loadLowStockAlerts();

                    // Update last updated time
                    document.getElementById('last-updated').textContent = new Date().toLocaleTimeString();

                    console.log('Inventory data loaded successfully from database');
                } else {
                    throw new Error(data.message || 'Failed to load inventory data');
                }
            } catch (error) {
                console.error('Error loading inventory data:', error);
                showAlert('Error loading inventory data: ' + error.message, 'danger');
            }
        }

        async function loadOverviewMetrics() {
            try {
                const response = await fetch('../api/inventory_api.php?action=get_overview');
                const data = await response.json();

                if (data.status === 'success') {
                    const metrics = data.data;
                    document.getElementById('totalItems').textContent = metrics.total_items;
                    document.getElementById('lowStockItems').textContent = metrics.low_stock_items;
                    document.getElementById('outOfStock').textContent = metrics.out_of_stock;
                    document.getElementById('totalValue').textContent = '$' + parseFloat(metrics.total_value || 0).toLocaleString('en-US', {minimumFractionDigits: 2});
                }
            } catch (error) {
                console.error('Error loading overview metrics:', error);
            }
        }

        async function loadLowStockAlerts() {
            try {
                const response = await fetch('../api/inventory_api.php?action=get_low_stock');
                const data = await response.json();

                if (data.status === 'success') {
                    displayLowStockAlerts(data.data);
                }
            } catch (error) {
                console.error('Error loading low stock alerts:', error);
            }
        }

        function displayLowStockAlerts(alerts) {
            const container = document.getElementById('lowStockAlerts');

            if (alerts.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-3">
                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                        <p class="text-muted">No low stock alerts</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = alerts.map(alert => `
                <div class="low-stock-item ${alert.alert_level === 'critical' ? 'critical-stock' : ''}">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong>${alert.item_name} (${alert.item_code})</strong>
                            <p class="mb-1 small">
                                Current Stock: ${alert.current_stock} | Minimum: ${alert.min_stock_level}
                                ${alert.current_stock === 0 ? ' - OUT OF STOCK' : ''}
                            </p>
                            <small class="text-muted">Category: ${alert.category} | Supplier: ${alert.supplier_name || 'N/A'}</small>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-outline-primary" onclick="adjustStock(${alert.item_id})">
                                <i class="fas fa-plus"></i> Restock
                            </button>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function updateOverviewMetrics() {
            // This function is now handled by loadOverviewMetrics() which calls the API
            loadOverviewMetrics();
        }

        function displayInventoryTable() {
            const tbody = document.getElementById('inventoryTableBody');

            if (filteredData.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="10" class="text-center py-4">
                            <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No inventory items found</p>
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = filteredData.map(item => {
                const stockLevel = item.stock_level || getStockLevel(item);
                const totalValue = parseFloat(item.total_value || (item.current_stock * item.unit_price));

                return `
                    <tr>
                        <td>
                            <span class="fw-bold">${item.item_code}</span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <span class="stock-level-indicator stock-${stockLevel}"></span>
                                <span>${item.item_name}</span>
                            </div>
                        </td>
                        <td>
                            <span class="category-badge">${item.category}</span>
                        </td>
                        <td>
                            <span class="fw-bold ${item.current_stock <= item.min_stock_level ? 'text-danger' : 'text-success'}">
                                ${item.current_stock}
                            </span>
                        </td>
                        <td>${item.min_stock_level}</td>
                        <td>$${parseFloat(item.unit_price).toFixed(2)}</td>
                        <td class="fw-bold">$${totalValue.toFixed(2)}</td>
                        <td>${item.supplier_name || 'N/A'}</td>
                        <td>
                            <small class="text-muted">${formatDateTime(item.updated_at)}</small>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-primary btn-action" onclick="editItem(${item.item_id})" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-warning btn-action" onclick="adjustStock(${item.item_id})" title="Adjust Stock">
                                    <i class="fas fa-exchange-alt"></i>
                                </button>
                                <button class="btn btn-danger btn-action" onclick="deleteItem(${item.item_id})" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function getStockLevel(item) {
            const currentStock = item.current_stock || item.currentStock || 0;
            const minStock = item.min_stock_level || item.minStock || 0;

            if (currentStock === 0) return 'out';
            if (currentStock <= minStock) return 'low';
            if (currentStock <= minStock * 2) return 'medium';
            return 'high';
        }

        function formatDateTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString('en-US', {
                timeZone: 'America/Guyana',
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function filterInventory() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const categoryFilter = document.getElementById('categoryFilter').value;
            const stockFilter = document.getElementById('stockFilter').value;
            const supplierFilter = document.getElementById('supplierFilter').value;

            filteredData = inventoryData.filter(item => {
                const matchesSearch = (item.item_name || '').toLowerCase().includes(searchTerm) ||
                                    (item.item_code || '').toLowerCase().includes(searchTerm);
                const matchesCategory = !categoryFilter || item.category === categoryFilter;
                const matchesStock = !stockFilter || getStockLevel(item) === stockFilter;
                const matchesSupplier = !supplierFilter || (item.supplier_name || '').includes(supplierFilter);

                return matchesSearch && matchesCategory && matchesStock && matchesSupplier;
            });

            displayInventoryTable();
        }

        function initializeCharts() {
            // Category Chart
            const categoryCtx = document.getElementById('categoryChart').getContext('2d');
            categoryChart = new Chart(categoryCtx, {
                type: 'doughnut',
                data: {
                    labels: [],
                    datasets: [{
                        data: [],
                        backgroundColor: [
                            '#4e73df',
                            '#1cc88a',
                            '#36b9cc',
                            '#f6c23e',
                            '#e74a3b'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });

            // Movement Chart
            const movementCtx = document.getElementById('movementChart').getContext('2d');
            movementChart = new Chart(movementCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Stock In',
                        data: [],
                        borderColor: '#1cc88a',
                        backgroundColor: 'rgba(28, 200, 138, 0.1)',
                        tension: 0.3
                    }, {
                        label: 'Stock Out',
                        data: [],
                        borderColor: '#e74a3b',
                        backgroundColor: 'rgba(231, 74, 59, 0.1)',
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        function updateCharts() {
            // Update category chart
            const categoryData = {};
            inventoryData.forEach(item => {
                categoryData[item.category] = (categoryData[item.category] || 0) + item.currentStock;
            });

            categoryChart.data.labels = Object.keys(categoryData);
            categoryChart.data.datasets[0].data = Object.values(categoryData);
            categoryChart.update();

            // Update movement chart with mock data
            const last7Days = [];
            const stockInData = [];
            const stockOutData = [];

            for (let i = 6; i >= 0; i--) {
                const date = new Date();
                date.setDate(date.getDate() - i);
                last7Days.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
                stockInData.push(Math.floor(Math.random() * 50) + 10);
                stockOutData.push(Math.floor(Math.random() * 30) + 5);
            }

            movementChart.data.labels = last7Days;
            movementChart.data.datasets[0].data = stockInData;
            movementChart.data.datasets[1].data = stockOutData;
            movementChart.update();
        }

        function updateLowStockAlerts() {
            const lowStockItems = inventoryData.filter(item => item.currentStock <= item.minStock);
            const alertsContainer = document.getElementById('lowStockAlerts');

            if (lowStockItems.length === 0) {
                alertsContainer.innerHTML = `
                    <div class="text-center py-3">
                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                        <p class="text-muted">No low stock alerts</p>
                    </div>
                `;
                return;
            }

            alertsContainer.innerHTML = lowStockItems.map(item => `
                <div class="alert alert-${item.currentStock === 0 ? 'danger' : 'warning'} alert-dismissible fade show mb-2" role="alert">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong>${item.name} (${item.code})</strong>
                            <p class="mb-1 small">
                                Current Stock: ${item.currentStock} | Minimum: ${item.minStock}
                                ${item.currentStock === 0 ? ' - OUT OF STOCK' : ''}
                            </p>
                            <small class="text-muted">Category: ${item.category} | Supplier: ${item.supplier}</small>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-outline-primary" onclick="adjustStock(${item.id})">
                                <i class="fas fa-plus"></i> Restock
                            </button>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        // Refresh function
        function refreshInventory() {
            loadInventoryData();
            showAlert('Inventory data refreshed successfully', 'success');
        }

        // Modal functions
        async function saveItem() {
            const formData = {
                code: document.getElementById('itemCode').value,
                name: document.getElementById('itemName').value,
                category: document.getElementById('itemCategory').value,
                supplier: document.getElementById('itemSupplier').value,
                currentStock: parseInt(document.getElementById('currentStock').value),
                minStock: parseInt(document.getElementById('minStock').value),
                unitPrice: parseFloat(document.getElementById('unitPrice').value),
                costPrice: parseFloat(document.getElementById('unitPrice').value), // Use same as unit price for now
                description: document.getElementById('itemDescription').value,
                location: 'Main Warehouse'
            };

            try {
                const response = await fetch('../api/inventory_api.php?action=add_item', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });

                const result = await response.json();

                if (result.status === 'success') {
                    // Close modal and reset form
                    bootstrap.Modal.getInstance(document.getElementById('addItemModal')).hide();
                    document.getElementById('addItemForm').reset();

                    // Reload data
                    loadInventoryData();

                    showAlert('Item added successfully', 'success');
                } else {
                    throw new Error(result.message || 'Failed to add item');
                }
            } catch (error) {
                console.error('Error saving item:', error);
                showAlert('Error saving item: ' + error.message, 'danger');
            }
        }

        function editItem(itemId) {
            const item = inventoryData.find(i => i.item_id === itemId);
            if (!item) return;

            // Populate edit form
            document.getElementById('editItemId').value = item.item_id;
            document.getElementById('editItemCode').value = item.item_code;
            document.getElementById('editItemName').value = item.item_name;
            document.getElementById('editItemCategory').value = item.category;
            document.getElementById('editItemSupplier').value = item.supplier_name || '';
            document.getElementById('editCurrentStock').value = item.current_stock;
            document.getElementById('editMinStock').value = item.min_stock_level;
            document.getElementById('editUnitPrice').value = item.unit_price;
            document.getElementById('editItemDescription').value = item.description || '';

            // Show modal
            new bootstrap.Modal(document.getElementById('editItemModal')).show();
        }

        function updateItem() {
            const itemId = parseInt(document.getElementById('editItemId').value);
            const itemIndex = inventoryData.findIndex(i => i.id === itemId);

            if (itemIndex === -1) return;

            // Update item data
            inventoryData[itemIndex] = {
                ...inventoryData[itemIndex],
                code: document.getElementById('editItemCode').value,
                name: document.getElementById('editItemName').value,
                category: document.getElementById('editItemCategory').value,
                supplier: document.getElementById('editItemSupplier').value,
                currentStock: parseInt(document.getElementById('editCurrentStock').value),
                minStock: parseInt(document.getElementById('editMinStock').value),
                unitPrice: parseFloat(document.getElementById('editUnitPrice').value),
                description: document.getElementById('editItemDescription').value,
                lastUpdated: new Date().toISOString().slice(0, 19).replace('T', ' ')
            };

            filteredData = [...inventoryData];

            updateOverviewMetrics();
            displayInventoryTable();
            updateCharts();
            updateLowStockAlerts();

            // Close modal
            bootstrap.Modal.getInstance(document.getElementById('editItemModal')).hide();

            showAlert('Item updated successfully', 'success');
        }

        function adjustStock(itemId) {
            const item = inventoryData.find(i => i.id === itemId);
            if (!item) return;

            // Populate adjustment form
            document.getElementById('adjustItemId').value = item.id;
            document.getElementById('adjustItemName').textContent = item.name;
            document.getElementById('adjustCurrentStock').textContent = item.currentStock;

            // Show modal
            new bootstrap.Modal(document.getElementById('stockAdjustmentModal')).show();
        }

        function processStockAdjustment() {
            const itemId = parseInt(document.getElementById('adjustItemId').value);
            const adjustmentType = document.getElementById('adjustmentType').value;
            const quantity = parseInt(document.getElementById('adjustmentQuantity').value);
            const reason = document.getElementById('adjustmentReason').value;

            const itemIndex = inventoryData.findIndex(i => i.id === itemId);
            if (itemIndex === -1) return;

            let newStock = inventoryData[itemIndex].currentStock;

            switch (adjustmentType) {
                case 'in':
                    newStock += quantity;
                    break;
                case 'out':
                    newStock = Math.max(0, newStock - quantity);
                    break;
                case 'adjustment':
                    newStock = quantity;
                    break;
            }

            // Update stock
            inventoryData[itemIndex].currentStock = newStock;
            inventoryData[itemIndex].lastUpdated = new Date().toISOString().slice(0, 19).replace('T', ' ');

            filteredData = [...inventoryData];

            updateOverviewMetrics();
            displayInventoryTable();
            updateCharts();
            updateLowStockAlerts();

            // Close modal and reset form
            bootstrap.Modal.getInstance(document.getElementById('stockAdjustmentModal')).hide();
            document.getElementById('stockAdjustmentForm').reset();

            showAlert(`Stock ${adjustmentType} processed successfully`, 'success');
        }

        function deleteItem(itemId) {
            if (!confirm('Are you sure you want to delete this item?')) return;

            const itemIndex = inventoryData.findIndex(i => i.id === itemId);
            if (itemIndex === -1) return;

            inventoryData.splice(itemIndex, 1);
            filteredData = [...inventoryData];

            updateOverviewMetrics();
            displayInventoryTable();
            updateCharts();
            updateLowStockAlerts();

            showAlert('Item deleted successfully', 'success');
        }

        // Quick action functions
        function bulkStockUpdate() {
            showAlert('Bulk stock update feature coming soon', 'info');
        }

        function generateReport() {
            showAlert('Report generation feature coming soon', 'info');
        }

        function lowStockAlert() {
            const lowStockCount = inventoryData.filter(item => item.currentStock <= item.minStock).length;
            showAlert(`Found ${lowStockCount} items with low stock`, 'warning');
        }

        function stockAudit() {
            showAlert('Stock audit feature coming soon', 'info');
        }

        function showAlert(message, type) {
            // Create alert element
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;

            document.body.appendChild(alertDiv);

            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
    </script>
</body>
</html>
