<?php
// Vendor Management System
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
    <title>Vendor Management - Stock Department</title>
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
        .vendor-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        .vendor-card:hover {
            transform: translateY(-5px);
        }
        .vendor-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            z-index: 1;
        }
        .vendor-card-content {
            position: relative;
            z-index: 2;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .rating-stars {
            color: #ffc107;
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
        .performance-meter {
            height: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }
        .performance-fill {
            height: 100%;
            background: linear-gradient(90deg, #e74a3b 0%, #f6c23e 50%, #1cc88a 100%);
            transition: width 0.3s ease;
        }
        .contact-info {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 10px;
            margin-top: 10px;
        }
        .vendor-metrics {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
        }
        .metric-item {
            text-align: center;
        }
        .metric-value {
            font-size: 1.2rem;
            font-weight: bold;
        }
        .metric-label {
            font-size: 0.8rem;
            opacity: 0.8;
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
            <a class="nav-link" href="purchase_orders.php">
                <i class="fas fa-shopping-cart"></i> Purchase Orders
            </a>
            <a class="nav-link active" href="vendors.php">
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
                <h1 class="text-white">Vendor Management</h1>
                <p class="text-white-50 mb-0">
                    <span class="real-time-indicator"></span>
                    Real-time vendor tracking | Last updated: <span id="last-updated"><?php echo date('g:i:s A'); ?></span>
                </p>
            </div>
            <div>
                <button class="btn btn-light" onclick="refreshVendors()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#addVendorModal">
                    <i class="fas fa-plus"></i> Add Vendor
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
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Vendors</div>
                                <div class="stat-value text-gray-800" id="totalVendors">0</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-truck stat-icon text-gray-300"></i>
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
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Vendors</div>
                                <div class="stat-value text-gray-800" id="activeVendors">0</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-check-circle stat-icon text-gray-300"></i>
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
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Orders</div>
                                <div class="stat-value text-gray-800" id="totalOrders">0</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-shopping-cart stat-icon text-gray-300"></i>
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
                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Value</div>
                                <div class="stat-value text-gray-800" id="totalValue">$0</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-dollar-sign stat-icon text-gray-300"></i>
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
                        <input type="text" class="form-control" id="searchInput" placeholder="Search vendors...">
                    </div>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
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
                    <select class="form-select" id="ratingFilter">
                        <option value="">All Ratings</option>
                        <option value="5">5 Stars</option>
                        <option value="4">4+ Stars</option>
                        <option value="3">3+ Stars</option>
                        <option value="2">2+ Stars</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-primary w-100" onclick="exportVendors()">
                        <i class="fas fa-file-excel"></i> Export
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
                        <button class="btn btn-outline-primary w-100 mb-2" onclick="bulkEmail()">
                            <i class="fas fa-envelope"></i> Bulk Email
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-success w-100 mb-2" onclick="performanceReport()">
                            <i class="fas fa-chart-line"></i> Performance Report
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-warning w-100 mb-2" onclick="paymentReminders()">
                            <i class="fas fa-bell"></i> Payment Reminders
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-info w-100 mb-2" onclick="contractReview()">
                            <i class="fas fa-file-contract"></i> Contract Review
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vendor Cards Grid -->
        <div class="row" id="vendorCardsContainer">
            <div class="col-12 text-center py-4">
                <div class="spinner-border text-white" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="text-white mt-2">Loading vendors...</p>
            </div>
        </div>

        <!-- Vendor Table -->
        <div class="card">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Vendor Details</h6>
                <div class="d-flex align-items-center">
                    <span class="me-3 small text-muted">Auto-refresh every 30 seconds</span>
                    <button class="btn btn-sm btn-outline-primary" id="refreshVendorsTable">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="vendorsTable">
                        <thead>
                            <tr>
                                <th>Vendor Name</th>
                                <th>Contact Person</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Rating</th>
                                <th>Orders</th>
                                <th>Total Value</th>
                                <th>Last Order</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="vendorsTableBody">
                            <tr>
                                <td colspan="10" class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2 text-muted">Loading vendor data...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Analytics Charts -->
        <div class="row mt-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Vendor Performance</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="performanceChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Order Distribution</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="distributionChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Performers -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-success">
                            <i class="fas fa-trophy"></i> Top Performing Vendors
                        </h6>
                    </div>
                    <div class="card-body">
                        <div id="topPerformers">
                            <div class="text-center py-3">
                                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                <span class="ms-2">Loading top performers...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- End of Main Content -->

    <!-- Add Vendor Modal -->
    <div class="modal fade" id="addVendorModal" tabindex="-1" aria-labelledby="addVendorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addVendorModalLabel">
                        <i class="fas fa-plus"></i> Add New Vendor
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addVendorForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="vendorName" placeholder="Vendor Name" required>
                                    <label for="vendorName">Vendor Name</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="contactPerson" placeholder="Contact Person" required>
                                    <label for="contactPerson">Contact Person</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="email" class="form-control" id="vendorEmail" placeholder="Email" required>
                                    <label for="vendorEmail">Email</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="tel" class="form-control" id="vendorPhone" placeholder="Phone" required>
                                    <label for="vendorPhone">Phone</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="vendorCategory" required>
                                        <option value="">Select Category</option>
                                        <option value="equipment">Equipment</option>
                                        <option value="supplies">Supplies</option>
                                        <option value="maintenance">Maintenance</option>
                                        <option value="office">Office</option>
                                    </select>
                                    <label for="vendorCategory">Primary Category</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="creditLimit" placeholder="Credit Limit" step="0.01" min="0">
                                    <label for="creditLimit">Credit Limit ($)</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-floating mb-3">
                            <textarea class="form-control" id="vendorAddress" placeholder="Address" style="height: 100px"></textarea>
                            <label for="vendorAddress">Address</label>
                        </div>
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="paymentTerms" placeholder="Payment Terms">
                            <label for="paymentTerms">Payment Terms</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveVendor()">
                        <i class="fas fa-save"></i> Save Vendor
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Vendor Modal -->
    <div class="modal fade" id="editVendorModal" tabindex="-1" aria-labelledby="editVendorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editVendorModalLabel">
                        <i class="fas fa-edit"></i> Edit Vendor
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editVendorForm">
                        <input type="hidden" id="editVendorId">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="editVendorName" placeholder="Vendor Name" required>
                                    <label for="editVendorName">Vendor Name</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="editContactPerson" placeholder="Contact Person" required>
                                    <label for="editContactPerson">Contact Person</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="email" class="form-control" id="editVendorEmail" placeholder="Email" required>
                                    <label for="editVendorEmail">Email</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="tel" class="form-control" id="editVendorPhone" placeholder="Phone" required>
                                    <label for="editVendorPhone">Phone</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="editVendorCategory" required>
                                        <option value="">Select Category</option>
                                        <option value="equipment">Equipment</option>
                                        <option value="supplies">Supplies</option>
                                        <option value="maintenance">Maintenance</option>
                                        <option value="office">Office</option>
                                    </select>
                                    <label for="editVendorCategory">Primary Category</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="editCreditLimit" placeholder="Credit Limit" step="0.01" min="0">
                                    <label for="editCreditLimit">Credit Limit ($)</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-floating mb-3">
                            <textarea class="form-control" id="editVendorAddress" placeholder="Address" style="height: 100px"></textarea>
                            <label for="editVendorAddress">Address</label>
                        </div>
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="editPaymentTerms" placeholder="Payment Terms">
                            <label for="editPaymentTerms">Payment Terms</label>
                        </div>
                        <div class="form-floating mb-3">
                            <select class="form-select" id="editVendorStatus" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            <label for="editVendorStatus">Status</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="updateVendor()">
                        <i class="fas fa-save"></i> Update Vendor
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap core JavaScript-->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Vendor management variables
        let vendorsData = [];
        let filteredData = [];
        let performanceChart;
        let distributionChart;

        // Initialize vendor system
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Vendor management system loaded');

            initializeVendors();
            loadVendorsData();
            setupEventListeners();
            initializeCharts();

            // Set up auto-refresh every 30 seconds
            setInterval(loadVendorsData, 30000);
        });

        function initializeVendors() {
            // Set Georgetown timezone for all timestamps
            console.log('Initializing vendors with Georgetown timezone (GMT-4)');
        }

        function setupEventListeners() {
            // Search and filter functionality
            document.getElementById('searchInput').addEventListener('input', filterVendors);
            document.getElementById('statusFilter').addEventListener('change', filterVendors);
            document.getElementById('categoryFilter').addEventListener('change', filterVendors);
            document.getElementById('ratingFilter').addEventListener('change', filterVendors);

            // Refresh button
            document.getElementById('refreshVendorsTable').addEventListener('click', loadVendorsData);
        }

        async function loadVendorsData() {
            try {
                // Load real vendors data from database
                const response = await fetch('../api/vendors_api.php?action=get_vendors');
                const data = await response.json();

                if (data.status === 'success') {
                    vendorsData = data.data;
                    filteredData = [...vendorsData];

                    updateOverviewMetrics();
                    displayVendorCards();
                    displayVendorsTable();
                    updateCharts();
                    loadTopPerformers();

                    // Update last updated time
                    document.getElementById('last-updated').textContent = new Date().toLocaleTimeString();

                    console.log('Vendors data loaded successfully from database');
                } else {
                    throw new Error(data.message || 'Failed to load vendors data');
                }
            } catch (error) {
                console.error('Error loading vendors data:', error);
                showAlert('Error loading vendors data: ' + error.message, 'danger');

                // Initialize empty arrays - no dummy data
                vendorsData = [];
                filteredData = [];
                updateOverviewMetrics();
                displayVendorCards();
                displayVendorsTable();
                updateCharts();
                loadTopPerformers();
            }
        }



        function updateOverviewMetrics() {
            const totalVendors = vendorsData.length;
            const activeVendors = vendorsData.filter(vendor => vendor.status === 'active').length;
            const totalOrders = vendorsData.reduce((sum, vendor) => sum + (vendor.total_orders || 0), 0);
            const totalValue = vendorsData.reduce((sum, vendor) => sum + parseFloat(vendor.total_value || 0), 0);

            document.getElementById('totalVendors').textContent = totalVendors;
            document.getElementById('activeVendors').textContent = activeVendors;
            document.getElementById('totalOrders').textContent = totalOrders;
            document.getElementById('totalValue').textContent = '$' + totalValue.toLocaleString('en-US', {minimumFractionDigits: 2});
        }

        function displayVendorCards() {
            const container = document.getElementById('vendorCardsContainer');

            if (filteredData.length === 0) {
                container.innerHTML = `
                    <div class="col-12 text-center py-4">
                        <i class="fas fa-truck fa-3x text-white-50 mb-3"></i>
                        <p class="text-white">No vendors found</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = filteredData.map(vendor => {
                const rating = vendor.rating || 0;
                const stars = generateStars(rating);
                const performance = vendor.performance_score || 0;

                return `
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="vendor-card">
                            <div class="vendor-card-content">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="mb-1">${vendor.supplier_name}</h5>
                                        <small class="opacity-75">${vendor.contact_person}</small>
                                    </div>
                                    <span class="status-badge status-${vendor.status}">${vendor.status}</span>
                                </div>

                                <div class="contact-info">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-envelope me-2"></i>
                                        <small>${vendor.email}</small>
                                    </div>
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-phone me-2"></i>
                                        <small>${vendor.phone}</small>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-tag me-2"></i>
                                        <small>${vendor.category || 'General'}</small>
                                    </div>
                                </div>

                                <div class="vendor-metrics">
                                    <div class="metric-item">
                                        <div class="metric-value">${vendor.total_orders || 0}</div>
                                        <div class="metric-label">Orders</div>
                                    </div>
                                    <div class="metric-item">
                                        <div class="metric-value">$${(vendor.total_value || 0).toLocaleString()}</div>
                                        <div class="metric-label">Total Value</div>
                                    </div>
                                    <div class="metric-item">
                                        <div class="rating-stars">${stars}</div>
                                        <div class="metric-label">${rating.toFixed(1)} Rating</div>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <small>Performance</small>
                                        <small>${performance}%</small>
                                    </div>
                                    <div class="performance-meter">
                                        <div class="performance-fill" style="width: ${performance}%"></div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <small class="opacity-75">Last Order: ${formatDate(vendor.last_order_date)}</small>
                                    <div>
                                        <button class="btn btn-sm btn-outline-light me-2" onclick="viewVendor(${vendor.supplier_id})">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-light" onclick="editVendor(${vendor.supplier_id})">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function displayVendorsTable() {
            const tbody = document.getElementById('vendorsTableBody');

            if (filteredData.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="10" class="text-center py-4">
                            <i class="fas fa-truck fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No vendors found</p>
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = filteredData.map(vendor => {
                const rating = vendor.rating || 0;
                const stars = generateStars(rating);

                return `
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div>
                                    <div class="fw-bold">${vendor.supplier_name}</div>
                                    <small class="text-muted">${vendor.category || 'General'}</small>
                                </div>
                            </div>
                        </td>
                        <td>${vendor.contact_person}</td>
                        <td>
                            <a href="mailto:${vendor.email}" class="text-decoration-none">${vendor.email}</a>
                        </td>
                        <td>
                            <a href="tel:${vendor.phone}" class="text-decoration-none">${vendor.phone}</a>
                        </td>
                        <td>
                            <span class="status-badge status-${vendor.status}">${vendor.status}</span>
                        </td>
                        <td>
                            <div class="rating-stars">${stars}</div>
                            <small class="text-muted">${rating.toFixed(1)}</small>
                        </td>
                        <td>
                            <span class="badge bg-primary">${vendor.total_orders || 0}</span>
                        </td>
                        <td class="fw-bold">$${parseFloat(vendor.total_value || 0).toFixed(2)}</td>
                        <td>
                            <small class="text-muted">${formatDate(vendor.last_order_date)}</small>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-primary btn-action" onclick="viewVendor(${vendor.supplier_id})" title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-warning btn-action" onclick="editVendor(${vendor.supplier_id})" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-success btn-action" onclick="createPO(${vendor.supplier_id})" title="Create PO">
                                    <i class="fas fa-shopping-cart"></i>
                                </button>
                                <button class="btn btn-danger btn-action" onclick="deactivateVendor(${vendor.supplier_id})" title="Deactivate">
                                    <i class="fas fa-ban"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function filterVendors() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const categoryFilter = document.getElementById('categoryFilter').value;
            const ratingFilter = document.getElementById('ratingFilter').value;

            filteredData = vendorsData.filter(vendor => {
                const matchesSearch = (vendor.supplier_name || '').toLowerCase().includes(searchTerm) ||
                                    (vendor.contact_person || '').toLowerCase().includes(searchTerm) ||
                                    (vendor.email || '').toLowerCase().includes(searchTerm);
                const matchesStatus = !statusFilter || vendor.status === statusFilter;
                const matchesCategory = !categoryFilter || vendor.category === categoryFilter;
                const matchesRating = !ratingFilter || (vendor.rating || 0) >= parseFloat(ratingFilter);

                return matchesSearch && matchesStatus && matchesCategory && matchesRating;
            });

            displayVendorCards();
            displayVendorsTable();
        }

        function initializeCharts() {
            // Performance Chart
            const performanceCtx = document.getElementById('performanceChart').getContext('2d');
            performanceChart = new Chart(performanceCtx, {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Performance Score',
                        data: [],
                        backgroundColor: 'rgba(78, 115, 223, 0.8)',
                        borderColor: '#4e73df',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });

            // Distribution Chart
            const distributionCtx = document.getElementById('distributionChart').getContext('2d');
            distributionChart = new Chart(distributionCtx, {
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
        }

        function updateCharts() {
            // Update performance chart
            const topVendors = vendorsData
                .sort((a, b) => (b.performance_score || 0) - (a.performance_score || 0))
                .slice(0, 5);

            performanceChart.data.labels = topVendors.map(v => v.supplier_name);
            performanceChart.data.datasets[0].data = topVendors.map(v => v.performance_score || 0);
            performanceChart.update();

            // Update distribution chart
            const categoryData = {};
            vendorsData.forEach(vendor => {
                const category = vendor.category || 'Other';
                categoryData[category] = (categoryData[category] || 0) + (vendor.total_value || 0);
            });

            distributionChart.data.labels = Object.keys(categoryData);
            distributionChart.data.datasets[0].data = Object.values(categoryData);
            distributionChart.update();
        }

        function loadTopPerformers() {
            const topVendors = vendorsData
                .sort((a, b) => (b.performance_score || 0) - (a.performance_score || 0))
                .slice(0, 5);

            const container = document.getElementById('topPerformers');

            if (topVendors.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-3">
                        <i class="fas fa-trophy fa-2x text-muted mb-2"></i>
                        <p class="text-muted">No performance data available</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = topVendors.map((vendor, index) => `
                <div class="d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <span class="badge bg-warning text-dark">#${index + 1}</span>
                        </div>
                        <div>
                            <div class="fw-bold">${vendor.supplier_name}</div>
                            <small class="text-muted">${vendor.total_orders || 0} orders • $${(vendor.total_value || 0).toLocaleString()}</small>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold text-success">${vendor.performance_score || 0}%</div>
                        <div class="rating-stars">${generateStars(vendor.rating || 0)}</div>
                    </div>
                </div>
            `).join('');
        }

        // Utility functions
        function generateStars(rating) {
            const fullStars = Math.floor(rating);
            const halfStar = rating % 1 >= 0.5;
            const emptyStars = 5 - fullStars - (halfStar ? 1 : 0);

            let stars = '';
            for (let i = 0; i < fullStars; i++) {
                stars += '<i class="fas fa-star"></i>';
            }
            if (halfStar) {
                stars += '<i class="fas fa-star-half-alt"></i>';
            }
            for (let i = 0; i < emptyStars; i++) {
                stars += '<i class="far fa-star"></i>';
            }

            return stars;
        }

        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                timeZone: 'America/Guyana',
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }

        // Refresh function
        function refreshVendors() {
            loadVendorsData();
            showAlert('Vendors data refreshed successfully', 'success');
        }

        // Modal functions
        async function saveVendor() {
            const formData = {
                supplier_name: document.getElementById('vendorName').value,
                contact_person: document.getElementById('contactPerson').value,
                email: document.getElementById('vendorEmail').value,
                phone: document.getElementById('vendorPhone').value,
                address: document.getElementById('vendorAddress').value,
                category: document.getElementById('vendorCategory').value,
                credit_limit: parseFloat(document.getElementById('creditLimit').value) || 0,
                payment_terms: document.getElementById('paymentTerms').value
            };

            try {
                const response = await fetch('../api/vendors_api.php?action=add_vendor', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });

                const result = await response.json();

                if (result.status === 'success') {
                    // Close modal and reset form
                    bootstrap.Modal.getInstance(document.getElementById('addVendorModal')).hide();
                    document.getElementById('addVendorForm').reset();

                    // Reload data
                    loadVendorsData();

                    showAlert('Vendor added successfully', 'success');
                } else {
                    throw new Error(result.message || 'Failed to add vendor');
                }
            } catch (error) {
                console.error('Error saving vendor:', error);
                showAlert('Error saving vendor: ' + error.message, 'danger');
            }
        }

        function editVendor(vendorId) {
            const vendor = vendorsData.find(v => v.supplier_id === vendorId);
            if (!vendor) return;

            // Populate edit form
            document.getElementById('editVendorId').value = vendor.supplier_id;
            document.getElementById('editVendorName').value = vendor.supplier_name;
            document.getElementById('editContactPerson').value = vendor.contact_person;
            document.getElementById('editVendorEmail').value = vendor.email;
            document.getElementById('editVendorPhone').value = vendor.phone;
            document.getElementById('editVendorAddress').value = vendor.address || '';
            document.getElementById('editVendorCategory').value = vendor.category || '';
            document.getElementById('editCreditLimit').value = vendor.credit_limit || 0;
            document.getElementById('editPaymentTerms').value = vendor.payment_terms || '';
            document.getElementById('editVendorStatus').value = vendor.status;

            // Show modal
            new bootstrap.Modal(document.getElementById('editVendorModal')).show();
        }

        async function updateVendor() {
            // Implementation for updating vendor
            showAlert('Update vendor feature coming soon', 'info');
        }

        function viewVendor(vendorId) {
            // Implementation for viewing vendor details
            showAlert('View vendor feature coming soon', 'info');
        }

        function createPO(vendorId) {
            // Redirect to purchase orders page with vendor pre-selected
            window.location.href = `purchase_orders.php?vendor=${vendorId}`;
        }

        function deactivateVendor(vendorId) {
            // Implementation for deactivating vendor
            showAlert('Deactivate vendor feature coming soon', 'info');
        }

        // Quick action functions
        function bulkEmail() {
            showAlert('Bulk email feature coming soon', 'info');
        }

        function performanceReport() {
            showAlert('Performance report feature coming soon', 'info');
        }

        function paymentReminders() {
            showAlert('Payment reminders feature coming soon', 'info');
        }

        function contractReview() {
            showAlert('Contract review feature coming soon', 'info');
        }

        function exportVendors() {
            showAlert('Export feature coming soon', 'info');
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