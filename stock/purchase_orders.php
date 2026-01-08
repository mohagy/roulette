<?php
// Purchase Orders Management System
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

// Database connection to existing roulette database
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
    <title>Purchase Orders Management - Stock Department</title>
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
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-draft { background: #e9ecef; color: #495057; }
        .status-sent { background: #cce5ff; color: #0066cc; }
        .status-confirmed { background: #d4edda; color: #155724; }
        .status-partial { background: #fff3cd; color: #856404; }
        .status-completed { background: #d1ecf1; color: #0c5460; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
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
        .po-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 10px;
        }
        .po-total {
            background: linear-gradient(135deg, #8B4513 0%, #D2691E 100%);
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
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
            <a class="nav-link" href="inventory.php">
                <i class="fas fa-warehouse"></i> Inventory Management
            </a>
            <a class="nav-link active" href="purchase_orders.php">
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
                <h1 class="text-white">Purchase Orders Management</h1>
                <p class="text-white-50 mb-0">
                    <span class="real-time-indicator"></span>
                    Real-time purchase order tracking | Last updated: <span id="last-updated"><?php echo date('g:i:s A'); ?></span>
                </p>
            </div>
            <div>
                <button class="btn btn-light" onclick="refreshPurchaseOrders()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#createPOModal">
                    <i class="fas fa-plus"></i> Create PO
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
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total POs</div>
                                <div class="stat-value text-gray-800" id="totalPOs">0</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-shopping-cart stat-icon text-gray-300"></i>
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
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending POs</div>
                                <div class="stat-value text-gray-800" id="pendingPOs">0</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-clock stat-icon text-gray-300"></i>
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
                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Overdue POs</div>
                                <div class="stat-value text-gray-800" id="overduePOs">0</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-exclamation-triangle stat-icon text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filter Bar -->
        <div class="search-filter-bar">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" id="searchInput" placeholder="Search PO number or supplier...">
                    </div>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="draft">Draft</option>
                        <option value="sent">Sent</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="partial">Partial</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="supplierFilter">
                        <option value="">All Suppliers</option>
                        <option value="Tech Solutions Ltd">Tech Solutions Ltd</option>
                        <option value="Office Depot">Office Depot</option>
                        <option value="Equipment Pro">Equipment Pro</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="dateFilter">
                        <option value="">All Dates</option>
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-primary" onclick="exportPOs()">
                            <i class="fas fa-file-excel"></i> Export
                        </button>
                        <button class="btn btn-outline-success" onclick="generateReport()">
                            <i class="fas fa-chart-bar"></i> Report
                        </button>
                    </div>
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
                        <button class="btn btn-outline-primary w-100 mb-2" onclick="bulkApprove()">
                            <i class="fas fa-check"></i> Bulk Approve
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-warning w-100 mb-2" onclick="checkDeliveries()">
                            <i class="fas fa-truck"></i> Check Deliveries
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-info w-100 mb-2" onclick="sendReminders()">
                            <i class="fas fa-bell"></i> Send Reminders
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-success w-100 mb-2" onclick="receiveGoods()">
                            <i class="fas fa-box"></i> Receive Goods
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Purchase Orders Table -->
        <div class="card">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Purchase Orders</h6>
                <div class="d-flex align-items-center">
                    <span class="me-3 small text-muted">Auto-refresh every 30 seconds</span>
                    <button class="btn btn-sm btn-outline-primary" id="refreshPOs">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="purchaseOrdersTable">
                        <thead>
                            <tr>
                                <th>PO Number</th>
                                <th>Supplier</th>
                                <th>Order Date</th>
                                <th>Expected Delivery</th>
                                <th>Status</th>
                                <th>Items</th>
                                <th>Total Amount</th>
                                <th>Progress</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="purchaseOrdersTableBody">
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2 text-muted">Loading purchase orders...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Charts and Analytics -->
        <div class="row mt-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">PO Status Distribution</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="statusChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Monthly PO Trends</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="trendsChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Approvals -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-warning">
                            <i class="fas fa-clock"></i> Pending Approvals
                        </h6>
                    </div>
                    <div class="card-body">
                        <div id="pendingApprovals">
                            <div class="text-center py-3">
                                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                <span class="ms-2">Loading pending approvals...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- End of Main Content -->

    <!-- Create PO Modal -->
    <div class="modal fade" id="createPOModal" tabindex="-1" aria-labelledby="createPOModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createPOModalLabel">
                        <i class="fas fa-plus"></i> Create New Purchase Order
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="createPOForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="poNumber" placeholder="PO Number" readonly>
                                    <label for="poNumber">PO Number (Auto-generated)</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="poSupplier" required>
                                        <option value="">Select Supplier</option>
                                        <option value="1">Tech Solutions Ltd</option>
                                        <option value="2">Office Depot</option>
                                        <option value="3">Equipment Pro</option>
                                    </select>
                                    <label for="poSupplier">Supplier</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="date" class="form-control" id="orderDate" required>
                                    <label for="orderDate">Order Date</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="date" class="form-control" id="expectedDelivery" required>
                                    <label for="expectedDelivery">Expected Delivery Date</label>
                                </div>
                            </div>
                        </div>

                        <!-- PO Items Section -->
                        <div class="card mb-3">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Purchase Order Items</h6>
                                <button type="button" class="btn btn-sm btn-primary" onclick="addPOItem()">
                                    <i class="fas fa-plus"></i> Add Item
                                </button>
                            </div>
                            <div class="card-body">
                                <div id="poItemsContainer">
                                    <!-- PO items will be added here dynamically -->
                                </div>
                                <div class="po-total mt-3">
                                    <h5 class="mb-0">Total Amount: $<span id="poTotalAmount">0.00</span></h5>
                                </div>
                            </div>
                        </div>

                        <div class="form-floating mb-3">
                            <textarea class="form-control" id="poNotes" placeholder="Notes" style="height: 100px"></textarea>
                            <label for="poNotes">Notes</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="savePurchaseOrder()">
                        <i class="fas fa-save"></i> Create Purchase Order
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- View PO Modal -->
    <div class="modal fade" id="viewPOModal" tabindex="-1" aria-labelledby="viewPOModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewPOModalLabel">
                        <i class="fas fa-eye"></i> Purchase Order Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="viewPOContent">
                    <!-- PO details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="printPO()">
                        <i class="fas fa-print"></i> Print PO
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap core JavaScript-->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Purchase Orders management variables
        let purchaseOrdersData = [];
        let filteredData = [];
        let statusChart;
        let trendsChart;
        let poItemCounter = 0;

        // Initialize purchase orders system
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Purchase Orders system loaded');

            initializePurchaseOrders();
            loadPurchaseOrdersData();
            setupEventListeners();
            initializeCharts();

            // Set up auto-refresh every 30 seconds
            setInterval(loadPurchaseOrdersData, 30000);
        });

        function initializePurchaseOrders() {
            // Set Georgetown timezone for all timestamps
            console.log('Initializing purchase orders with Georgetown timezone (GMT-4)');

            // Set default dates
            const today = new Date();
            const nextWeek = new Date(today.getTime() + 7 * 24 * 60 * 60 * 1000);

            document.getElementById('orderDate').value = today.toISOString().split('T')[0];
            document.getElementById('expectedDelivery').value = nextWeek.toISOString().split('T')[0];

            // Generate PO number
            generatePONumber();
        }

        function setupEventListeners() {
            // Search and filter functionality
            document.getElementById('searchInput').addEventListener('input', filterPurchaseOrders);
            document.getElementById('statusFilter').addEventListener('change', filterPurchaseOrders);
            document.getElementById('supplierFilter').addEventListener('change', filterPurchaseOrders);
            document.getElementById('dateFilter').addEventListener('change', filterPurchaseOrders);

            // Refresh button
            document.getElementById('refreshPOs').addEventListener('click', loadPurchaseOrdersData);
        }

        async function loadPurchaseOrdersData() {
            try {
                // Load real purchase orders data from database
                const response = await fetch('../api/purchase_orders_api.php?action=get_purchase_orders');
                const data = await response.json();

                if (data.status === 'success') {
                    purchaseOrdersData = data.data;
                    filteredData = [...purchaseOrdersData];

                    updateOverviewMetrics();
                    displayPurchaseOrdersTable();
                    updateCharts();
                    loadPendingApprovals();

                    // Update last updated time
                    document.getElementById('last-updated').textContent = new Date().toLocaleTimeString();

                    console.log('Purchase orders data loaded successfully from database');
                } else {
                    throw new Error(data.message || 'Failed to load purchase orders data');
                }
            } catch (error) {
                console.error('Error loading purchase orders data:', error);
                showAlert('Error loading purchase orders data: ' + error.message, 'danger');

                // Initialize empty arrays - no dummy data
                purchaseOrdersData = [];
                filteredData = [];
                updateOverviewMetrics();
                displayPurchaseOrdersTable();
                updateCharts();
                loadPendingApprovals();
            }
        }



        function updateOverviewMetrics() {
            const totalPOs = purchaseOrdersData.length;
            const pendingPOs = purchaseOrdersData.filter(po => ['draft', 'sent'].includes(po.status)).length;
            const totalValue = purchaseOrdersData.reduce((sum, po) => sum + parseFloat(po.total_amount || 0), 0);
            const overduePOs = purchaseOrdersData.filter(po => {
                const deliveryDate = new Date(po.expected_delivery_date);
                const today = new Date();
                return deliveryDate < today && !['completed', 'cancelled'].includes(po.status);
            }).length;

            document.getElementById('totalPOs').textContent = totalPOs;
            document.getElementById('pendingPOs').textContent = pendingPOs;
            document.getElementById('totalValue').textContent = '$' + totalValue.toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('overduePOs').textContent = overduePOs;
        }

        function displayPurchaseOrdersTable() {
            const tbody = document.getElementById('purchaseOrdersTableBody');

            if (filteredData.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="9" class="text-center py-4">
                            <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No purchase orders found</p>
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = filteredData.map(po => {
                const progress = po.progress || 0;
                const progressColor = progress < 25 ? 'danger' : progress < 75 ? 'warning' : 'success';

                return `
                    <tr>
                        <td>
                            <span class="fw-bold">${po.po_number}</span>
                        </td>
                        <td>${po.supplier_name}</td>
                        <td>
                            <small class="text-muted">${formatDate(po.order_date)}</small>
                        </td>
                        <td>
                            <small class="text-muted">${formatDate(po.expected_delivery_date)}</small>
                        </td>
                        <td>
                            <span class="status-badge status-${po.status}">${po.status.charAt(0).toUpperCase() + po.status.slice(1)}</span>
                        </td>
                        <td>
                            <span class="badge bg-secondary">${po.items_count || 0} items</span>
                        </td>
                        <td class="fw-bold">$${parseFloat(po.total_amount || 0).toFixed(2)}</td>
                        <td>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-${progressColor}" role="progressbar"
                                     style="width: ${progress}%" aria-valuenow="${progress}"
                                     aria-valuemin="0" aria-valuemax="100">
                                    ${progress}%
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-primary btn-action" onclick="viewPO(${po.po_id})" title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-warning btn-action" onclick="editPO(${po.po_id})" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-success btn-action" onclick="approvePO(${po.po_id})" title="Approve">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button class="btn btn-danger btn-action" onclick="cancelPO(${po.po_id})" title="Cancel">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function filterPurchaseOrders() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const supplierFilter = document.getElementById('supplierFilter').value;
            const dateFilter = document.getElementById('dateFilter').value;

            filteredData = purchaseOrdersData.filter(po => {
                const matchesSearch = (po.po_number || '').toLowerCase().includes(searchTerm) ||
                                    (po.supplier_name || '').toLowerCase().includes(searchTerm);
                const matchesStatus = !statusFilter || po.status === statusFilter;
                const matchesSupplier = !supplierFilter || (po.supplier_name || '').includes(supplierFilter);
                const matchesDate = !dateFilter || checkDateFilter(po.order_date, dateFilter);

                return matchesSearch && matchesStatus && matchesSupplier && matchesDate;
            });

            displayPurchaseOrdersTable();
        }

        function checkDateFilter(orderDate, filter) {
            const today = new Date();
            const poDate = new Date(orderDate);

            switch (filter) {
                case 'today':
                    return poDate.toDateString() === today.toDateString();
                case 'week':
                    const weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
                    return poDate >= weekAgo;
                case 'month':
                    const monthAgo = new Date(today.getTime() - 30 * 24 * 60 * 60 * 1000);
                    return poDate >= monthAgo;
                default:
                    return true;
            }
        }

        function initializeCharts() {
            // Status Chart
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            statusChart = new Chart(statusCtx, {
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
                            '#e74a3b',
                            '#858796'
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

            // Trends Chart
            const trendsCtx = document.getElementById('trendsChart').getContext('2d');
            trendsChart = new Chart(trendsCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Purchase Orders',
                        data: [],
                        borderColor: '#4e73df',
                        backgroundColor: 'rgba(78, 115, 223, 0.1)',
                        tension: 0.3
                    }, {
                        label: 'Total Value ($)',
                        data: [],
                        borderColor: '#1cc88a',
                        backgroundColor: 'rgba(28, 200, 138, 0.1)',
                        tension: 0.3,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            position: 'left'
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false,
                            },
                        }
                    }
                }
            });
        }

        function updateCharts() {
            // Update status chart
            const statusData = {};
            purchaseOrdersData.forEach(po => {
                statusData[po.status] = (statusData[po.status] || 0) + 1;
            });

            statusChart.data.labels = Object.keys(statusData);
            statusChart.data.datasets[0].data = Object.values(statusData);
            statusChart.update();

            // Update trends chart with sample data
            const last6Months = [];
            const poCount = [];
            const totalValues = [];

            for (let i = 5; i >= 0; i--) {
                const date = new Date();
                date.setMonth(date.getMonth() - i);
                last6Months.push(date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' }));
                poCount.push(Math.floor(Math.random() * 20) + 5);
                totalValues.push(Math.floor(Math.random() * 50000) + 10000);
            }

            trendsChart.data.labels = last6Months;
            trendsChart.data.datasets[0].data = poCount;
            trendsChart.data.datasets[1].data = totalValues;
            trendsChart.update();
        }

        function loadPendingApprovals() {
            const pendingPOs = purchaseOrdersData.filter(po => po.status === 'draft' || po.status === 'sent');
            const container = document.getElementById('pendingApprovals');

            if (pendingPOs.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-3">
                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                        <p class="text-muted">No pending approvals</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = pendingPOs.map(po => `
                <div class="alert alert-warning alert-dismissible fade show mb-2" role="alert">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong>${po.po_number}</strong> - ${po.supplier_name}
                            <p class="mb-1 small">
                                Order Date: ${formatDate(po.order_date)} |
                                Expected: ${formatDate(po.expected_delivery_date)} |
                                Amount: $${parseFloat(po.total_amount).toFixed(2)}
                            </p>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-outline-success me-2" onclick="approvePO(${po.po_id})">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button class="btn btn-sm btn-outline-primary" onclick="viewPO(${po.po_id})">
                                <i class="fas fa-eye"></i> View
                            </button>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        // Utility functions
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                timeZone: 'America/Guyana',
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }

        function generatePONumber() {
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');
            const random = Math.floor(Math.random() * 1000).toString().padStart(3, '0');

            document.getElementById('poNumber').value = `PO-${year}${month}${day}-${random}`;
        }

        // Refresh function
        function refreshPurchaseOrders() {
            loadPurchaseOrdersData();
            showAlert('Purchase orders data refreshed successfully', 'success');
        }

        // Modal functions
        function addPOItem() {
            poItemCounter++;
            const container = document.getElementById('poItemsContainer');

            const itemHtml = `
                <div class="po-item" id="poItem${poItemCounter}">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <select class="form-select" name="item_id[]" required>
                                <option value="">Select Item</option>
                                <option value="1">Gaming Terminal</option>
                                <option value="2">Thermal Paper Rolls</option>
                                <option value="3">Network Router</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="number" class="form-control" name="quantity[]" placeholder="Qty" min="1" required onchange="calculatePOTotal()">
                        </div>
                        <div class="col-md-2">
                            <input type="number" class="form-control" name="unit_price[]" placeholder="Unit Price" step="0.01" min="0" required onchange="calculatePOTotal()">
                        </div>
                        <div class="col-md-3">
                            <input type="text" class="form-control" name="total_price[]" placeholder="Total" readonly>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-danger btn-sm" onclick="removePOItem(${poItemCounter})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;

            container.insertAdjacentHTML('beforeend', itemHtml);
        }

        function removePOItem(itemId) {
            document.getElementById(`poItem${itemId}`).remove();
            calculatePOTotal();
        }

        function calculatePOTotal() {
            let total = 0;
            const quantities = document.querySelectorAll('input[name="quantity[]"]');
            const unitPrices = document.querySelectorAll('input[name="unit_price[]"]');
            const totalPrices = document.querySelectorAll('input[name="total_price[]"]');

            for (let i = 0; i < quantities.length; i++) {
                const qty = parseFloat(quantities[i].value) || 0;
                const price = parseFloat(unitPrices[i].value) || 0;
                const lineTotal = qty * price;

                totalPrices[i].value = lineTotal.toFixed(2);
                total += lineTotal;
            }

            document.getElementById('poTotalAmount').textContent = total.toFixed(2);
        }

        async function savePurchaseOrder() {
            // Implementation for saving purchase order
            showAlert('Purchase order creation feature coming soon', 'info');
        }

        function viewPO(poId) {
            // Implementation for viewing purchase order
            showAlert('View PO feature coming soon', 'info');
        }

        function editPO(poId) {
            // Implementation for editing purchase order
            showAlert('Edit PO feature coming soon', 'info');
        }

        function approvePO(poId) {
            // Implementation for approving purchase order
            showAlert('Approve PO feature coming soon', 'info');
        }

        function cancelPO(poId) {
            // Implementation for cancelling purchase order
            showAlert('Cancel PO feature coming soon', 'info');
        }

        function printPO() {
            // Implementation for printing purchase order
            window.print();
        }

        // Quick action functions
        function bulkApprove() {
            showAlert('Bulk approve feature coming soon', 'info');
        }

        function checkDeliveries() {
            showAlert('Check deliveries feature coming soon', 'info');
        }

        function sendReminders() {
            showAlert('Send reminders feature coming soon', 'info');
        }

        function receiveGoods() {
            showAlert('Receive goods feature coming soon', 'info');
        }

        function exportPOs() {
            showAlert('Export feature coming soon', 'info');
        }

        function generateReport() {
            showAlert('Generate report feature coming soon', 'info');
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