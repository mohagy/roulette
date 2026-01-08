<?php
// Equipment Issues Management System
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
    <title>Equipment Issues - Stock Department</title>
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
        .priority-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .priority-critical { background: #dc3545; color: white; }
        .priority-high { background: #fd7e14; color: white; }
        .priority-medium { background: #ffc107; color: #212529; }
        .priority-low { background: #28a745; color: white; }
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-open { background: #dc3545; color: white; }
        .status-in-progress { background: #ffc107; color: #212529; }
        .status-pending-parts { background: #fd7e14; color: white; }
        .status-resolved { background: #28a745; color: white; }
        .status-closed { background: #6c757d; color: white; }
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
        .issue-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        .issue-card:hover {
            transform: translateY(-5px);
        }
        .issue-card::before {
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
        .issue-card-content {
            position: relative;
            z-index: 2;
        }
        .equipment-info {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 10px;
            margin-top: 10px;
        }
        .issue-timeline {
            border-left: 3px solid #dee2e6;
            padding-left: 15px;
            margin-left: 10px;
        }
        .timeline-item {
            position: relative;
            padding-bottom: 15px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -18px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #007bff;
            border: 3px solid white;
        }
        .timeline-item.completed::before {
            background: #28a745;
        }
        .timeline-item.current::before {
            background: #ffc107;
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
            <a class="nav-link" href="vendors.php">
                <i class="fas fa-truck"></i> Vendor Management
            </a>
            <a class="nav-link active" href="equipment_issues.php">
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
                <h1 class="text-white">Equipment Issues Management</h1>
                <p class="text-white-50 mb-0">
                    <span class="real-time-indicator"></span>
                    Real-time issue tracking | Last updated: <span id="last-updated"><?php echo date('g:i:s A'); ?></span>
                </p>
            </div>
            <div>
                <button class="btn btn-light" onclick="refreshIssues()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#reportIssueModal">
                    <i class="fas fa-plus"></i> Report Issue
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-xl-3 col-md-6 col-sm-6">
                <div class="card stat-card border-left-danger">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Open Issues</div>
                                <div class="stat-value text-gray-800" id="openIssues">0</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-exclamation-triangle stat-icon text-gray-300"></i>
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
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">In Progress</div>
                                <div class="stat-value text-gray-800" id="inProgressIssues">0</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-tools stat-icon text-gray-300"></i>
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
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Resolved Today</div>
                                <div class="stat-value text-gray-800" id="resolvedToday">0</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-check-circle stat-icon text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 col-sm-6">
                <div class="card stat-card border-left-primary">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Critical Issues</div>
                                <div class="stat-value text-gray-800" id="criticalIssues">0</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-fire stat-icon text-gray-300"></i>
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
                        <input type="text" class="form-control" id="searchInput" placeholder="Search issues...">
                    </div>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="open">Open</option>
                        <option value="in-progress">In Progress</option>
                        <option value="pending-parts">Pending Parts</option>
                        <option value="resolved">Resolved</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="priorityFilter">
                        <option value="">All Priority</option>
                        <option value="critical">Critical</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="equipmentFilter">
                        <option value="">All Equipment</option>
                        <option value="gaming-terminal">Gaming Terminal</option>
                        <option value="printer">Printer</option>
                        <option value="network">Network Equipment</option>
                        <option value="pos">POS System</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-primary" onclick="exportIssues()">
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
                        <button class="btn btn-outline-danger w-100 mb-2" onclick="viewCriticalIssues()">
                            <i class="fas fa-fire"></i> Critical Issues
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-warning w-100 mb-2" onclick="scheduleMaintenace()">
                            <i class="fas fa-calendar"></i> Schedule Maintenance
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-info w-100 mb-2" onclick="orderParts()">
                            <i class="fas fa-shopping-cart"></i> Order Parts
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-success w-100 mb-2" onclick="bulkUpdate()">
                            <i class="fas fa-edit"></i> Bulk Update
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Critical Issues Alert -->
        <div id="criticalIssuesAlert" class="alert alert-danger d-none" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-fire fa-2x me-3"></i>
                <div>
                    <h5 class="alert-heading mb-1">Critical Issues Detected!</h5>
                    <p class="mb-0">There are <span id="criticalCount">0</span> critical issues requiring immediate attention.</p>
                </div>
                <button class="btn btn-outline-danger ms-auto" onclick="viewCriticalIssues()">
                    View Critical Issues
                </button>
            </div>
        </div>

        <!-- Issues Cards Grid -->
        <div class="row" id="issuesCardsContainer">
            <div class="col-12 text-center py-4">
                <div class="spinner-border text-white" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="text-white mt-2">Loading equipment issues...</p>
            </div>
        </div>

        <!-- Issues Table -->
        <div class="card">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Equipment Issues</h6>
                <div class="d-flex align-items-center">
                    <span class="me-3 small text-muted">Auto-refresh every 30 seconds</span>
                    <button class="btn btn-sm btn-outline-primary" id="refreshIssuesTable">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="issuesTable">
                        <thead>
                            <tr>
                                <th>Issue ID</th>
                                <th>Equipment</th>
                                <th>Description</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Assigned To</th>
                                <th>Reported Date</th>
                                <th>Last Update</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="issuesTableBody">
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2 text-muted">Loading equipment issues...</p>
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
                        <h6 class="m-0 font-weight-bold text-primary">Issues by Status</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="statusChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Issues by Equipment Type</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="equipmentChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resolution Timeline -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-success">
                            <i class="fas fa-clock"></i> Recent Resolutions
                        </h6>
                    </div>
                    <div class="card-body">
                        <div id="recentResolutions">
                            <div class="text-center py-3">
                                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                <span class="ms-2">Loading recent resolutions...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- End of Main Content -->

    <!-- Report Issue Modal -->
    <div class="modal fade" id="reportIssueModal" tabindex="-1" aria-labelledby="reportIssueModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reportIssueModalLabel">
                        <i class="fas fa-plus"></i> Report New Issue
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="reportIssueForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="equipmentType" required>
                                        <option value="">Select Equipment Type</option>
                                        <option value="gaming-terminal">Gaming Terminal</option>
                                        <option value="printer">Printer</option>
                                        <option value="network">Network Equipment</option>
                                        <option value="pos">POS System</option>
                                        <option value="other">Other</option>
                                    </select>
                                    <label for="equipmentType">Equipment Type</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="equipmentId" placeholder="Equipment ID" required>
                                    <label for="equipmentId">Equipment ID/Serial Number</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="issuePriority" required>
                                        <option value="">Select Priority</option>
                                        <option value="critical">Critical</option>
                                        <option value="high">High</option>
                                        <option value="medium">Medium</option>
                                        <option value="low">Low</option>
                                    </select>
                                    <label for="issuePriority">Priority Level</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="issueLocation" placeholder="Location" required>
                                    <label for="issueLocation">Location</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="issueTitle" placeholder="Issue Title" required>
                            <label for="issueTitle">Issue Title</label>
                        </div>
                        <div class="form-floating mb-3">
                            <textarea class="form-control" id="issueDescription" placeholder="Description" style="height: 120px" required></textarea>
                            <label for="issueDescription">Detailed Description</label>
                        </div>
                        <div class="form-floating mb-3">
                            <textarea class="form-control" id="stepsToReproduce" placeholder="Steps to Reproduce" style="height: 100px"></textarea>
                            <label for="stepsToReproduce">Steps to Reproduce (if applicable)</label>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="assignedTo">
                                        <option value="">Auto-assign</option>
                                        <option value="tech1">Tech Support 1</option>
                                        <option value="tech2">Tech Support 2</option>
                                        <option value="maintenance">Maintenance Team</option>
                                    </select>
                                    <label for="assignedTo">Assign To</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="datetime-local" class="form-control" id="expectedResolution">
                                    <label for="expectedResolution">Expected Resolution</label>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitIssue()">
                        <i class="fas fa-save"></i> Report Issue
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Issue Modal -->
    <div class="modal fade" id="updateIssueModal" tabindex="-1" aria-labelledby="updateIssueModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateIssueModalLabel">
                        <i class="fas fa-edit"></i> Update Issue
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="updateIssueForm">
                        <input type="hidden" id="updateIssueId">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="updateStatus" required>
                                        <option value="open">Open</option>
                                        <option value="in-progress">In Progress</option>
                                        <option value="pending-parts">Pending Parts</option>
                                        <option value="resolved">Resolved</option>
                                        <option value="closed">Closed</option>
                                    </select>
                                    <label for="updateStatus">Status</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="updatePriority" required>
                                        <option value="critical">Critical</option>
                                        <option value="high">High</option>
                                        <option value="medium">Medium</option>
                                        <option value="low">Low</option>
                                    </select>
                                    <label for="updatePriority">Priority</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-floating mb-3">
                            <select class="form-select" id="updateAssignedTo">
                                <option value="">Unassigned</option>
                                <option value="tech1">Tech Support 1</option>
                                <option value="tech2">Tech Support 2</option>
                                <option value="maintenance">Maintenance Team</option>
                            </select>
                            <label for="updateAssignedTo">Assigned To</label>
                        </div>
                        <div class="form-floating mb-3">
                            <textarea class="form-control" id="updateNotes" placeholder="Update Notes" style="height: 120px" required></textarea>
                            <label for="updateNotes">Update Notes</label>
                        </div>
                        <div class="form-floating mb-3">
                            <input type="datetime-local" class="form-control" id="updateExpectedResolution">
                            <label for="updateExpectedResolution">Expected Resolution</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveIssueUpdate()">
                        <i class="fas fa-save"></i> Update Issue
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap core JavaScript-->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Equipment Issues management variables
        let issuesData = [];
        let filteredData = [];
        let statusChart;
        let equipmentChart;

        // Initialize equipment issues system
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Equipment Issues system loaded');

            initializeIssues();
            loadIssuesData();
            setupEventListeners();
            initializeCharts();

            // Set up auto-refresh every 30 seconds
            setInterval(loadIssuesData, 30000);
        });

        function initializeIssues() {
            // Set Georgetown timezone for all timestamps
            console.log('Initializing equipment issues with Georgetown timezone (GMT-4)');
        }

        function setupEventListeners() {
            // Search and filter functionality
            document.getElementById('searchInput').addEventListener('input', filterIssues);
            document.getElementById('statusFilter').addEventListener('change', filterIssues);
            document.getElementById('priorityFilter').addEventListener('change', filterIssues);
            document.getElementById('equipmentFilter').addEventListener('change', filterIssues);

            // Refresh button
            document.getElementById('refreshIssuesTable').addEventListener('click', loadIssuesData);
        }

        async function loadIssuesData() {
            try {
                // Load real equipment issues data from database
                const response = await fetch('../api/equipment_issues_api.php?action=get_issues');
                const data = await response.json();

                if (data.status === 'success') {
                    issuesData = data.data;
                    filteredData = [...issuesData];

                    updateOverviewMetrics();
                    displayIssuesCards();
                    displayIssuesTable();
                    updateCharts();
                    loadRecentResolutions();
                    updateCriticalAlert();

                    // Update last updated time
                    document.getElementById('last-updated').textContent = new Date().toLocaleTimeString();

                    console.log('Equipment issues data loaded successfully from database');
                } else {
                    throw new Error(data.message || 'Failed to load equipment issues data');
                }
            } catch (error) {
                console.error('Error loading equipment issues data:', error);
                showAlert('Error loading equipment issues data: ' + error.message, 'danger');

                // Initialize empty arrays - no dummy data
                issuesData = [];
                filteredData = [];
                updateOverviewMetrics();
                displayIssuesCards();
                displayIssuesTable();
                updateCharts();
                loadRecentResolutions();
                updateCriticalAlert();
            }
        }

        function updateOverviewMetrics() {
            const openIssues = issuesData.filter(issue => issue.status === 'open').length;
            const inProgressIssues = issuesData.filter(issue => issue.status === 'in-progress').length;
            const criticalIssues = issuesData.filter(issue => issue.priority === 'critical').length;

            // Calculate resolved today
            const today = new Date().toDateString();
            const resolvedToday = issuesData.filter(issue => {
                return issue.status === 'resolved' &&
                       new Date(issue.resolved_date).toDateString() === today;
            }).length;

            document.getElementById('openIssues').textContent = openIssues;
            document.getElementById('inProgressIssues').textContent = inProgressIssues;
            document.getElementById('resolvedToday').textContent = resolvedToday;
            document.getElementById('criticalIssues').textContent = criticalIssues;
        }

        function displayIssuesCards() {
            const container = document.getElementById('issuesCardsContainer');

            if (filteredData.length === 0) {
                container.innerHTML = `
                    <div class="col-12 text-center py-4">
                        <i class="fas fa-tools fa-3x text-white-50 mb-3"></i>
                        <p class="text-white">No equipment issues found</p>
                    </div>
                `;
                return;
            }

            // Show only critical and high priority issues in cards
            const priorityIssues = filteredData.filter(issue =>
                ['critical', 'high'].includes(issue.priority) &&
                !['resolved', 'closed'].includes(issue.status)
            );

            if (priorityIssues.length === 0) {
                container.innerHTML = `
                    <div class="col-12 text-center py-4">
                        <i class="fas fa-check-circle fa-3x text-white-50 mb-3"></i>
                        <p class="text-white">No critical or high priority issues</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = priorityIssues.map(issue => {
                const timeAgo = getTimeAgo(issue.created_date);

                return `
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="issue-card">
                            <div class="issue-card-content">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h6 class="mb-1">#${issue.issue_id}</h6>
                                        <small class="opacity-75">${issue.equipment_type}</small>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <span class="priority-badge priority-${issue.priority}">${issue.priority}</span>
                                        <span class="status-badge status-${issue.status}">${issue.status}</span>
                                    </div>
                                </div>

                                <h5 class="mb-2">${issue.title}</h5>
                                <p class="mb-3 opacity-75">${issue.description.substring(0, 100)}${issue.description.length > 100 ? '...' : ''}</p>

                                <div class="equipment-info">
                                    <div class="d-flex justify-content-between mb-2">
                                        <small><i class="fas fa-desktop me-1"></i> ${issue.equipment_id}</small>
                                        <small><i class="fas fa-map-marker-alt me-1"></i> ${issue.location}</small>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <small><i class="fas fa-user me-1"></i> ${issue.assigned_to || 'Unassigned'}</small>
                                        <small><i class="fas fa-clock me-1"></i> ${timeAgo}</small>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <small class="opacity-75">Reported: ${formatDate(issue.created_date)}</small>
                                    <div>
                                        <button class="btn btn-sm btn-outline-light me-2" onclick="viewIssue(${issue.issue_id})">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-light" onclick="updateIssue(${issue.issue_id})">
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

        function displayIssuesTable() {
            const tbody = document.getElementById('issuesTableBody');

            if (filteredData.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="9" class="text-center py-4">
                            <i class="fas fa-tools fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No equipment issues found</p>
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = filteredData.map(issue => {
                return `
                    <tr>
                        <td>
                            <span class="fw-bold">#${issue.issue_id}</span>
                        </td>
                        <td>
                            <div>
                                <div class="fw-bold">${issue.equipment_type}</div>
                                <small class="text-muted">${issue.equipment_id}</small>
                            </div>
                        </td>
                        <td>
                            <div>
                                <div class="fw-bold">${issue.title}</div>
                                <small class="text-muted">${issue.description.substring(0, 50)}${issue.description.length > 50 ? '...' : ''}</small>
                            </div>
                        </td>
                        <td>
                            <span class="priority-badge priority-${issue.priority}">${issue.priority}</span>
                        </td>
                        <td>
                            <span class="status-badge status-${issue.status}">${issue.status.replace('-', ' ')}</span>
                        </td>
                        <td>
                            <span class="badge bg-secondary">${issue.assigned_to || 'Unassigned'}</span>
                        </td>
                        <td>
                            <small class="text-muted">${formatDate(issue.created_date)}</small>
                        </td>
                        <td>
                            <small class="text-muted">${formatDate(issue.updated_date)}</small>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-primary btn-action" onclick="viewIssue(${issue.issue_id})" title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-warning btn-action" onclick="updateIssue(${issue.issue_id})" title="Update">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-success btn-action" onclick="resolveIssue(${issue.issue_id})" title="Resolve">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button class="btn btn-danger btn-action" onclick="deleteIssue(${issue.issue_id})" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function filterIssues() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const priorityFilter = document.getElementById('priorityFilter').value;
            const equipmentFilter = document.getElementById('equipmentFilter').value;

            filteredData = issuesData.filter(issue => {
                const matchesSearch = (issue.title || '').toLowerCase().includes(searchTerm) ||
                                    (issue.description || '').toLowerCase().includes(searchTerm) ||
                                    (issue.equipment_id || '').toLowerCase().includes(searchTerm);
                const matchesStatus = !statusFilter || issue.status === statusFilter;
                const matchesPriority = !priorityFilter || issue.priority === priorityFilter;
                const matchesEquipment = !equipmentFilter || issue.equipment_type === equipmentFilter;

                return matchesSearch && matchesStatus && matchesPriority && matchesEquipment;
            });

            displayIssuesCards();
            displayIssuesTable();
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
                            '#dc3545',
                            '#ffc107',
                            '#fd7e14',
                            '#28a745',
                            '#6c757d'
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

            // Equipment Chart
            const equipmentCtx = document.getElementById('equipmentChart').getContext('2d');
            equipmentChart = new Chart(equipmentCtx, {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Issues Count',
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
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        function updateCharts() {
            // Update status chart
            const statusData = {};
            issuesData.forEach(issue => {
                statusData[issue.status] = (statusData[issue.status] || 0) + 1;
            });

            statusChart.data.labels = Object.keys(statusData);
            statusChart.data.datasets[0].data = Object.values(statusData);
            statusChart.update();

            // Update equipment chart
            const equipmentData = {};
            issuesData.forEach(issue => {
                const type = issue.equipment_type || 'Other';
                equipmentData[type] = (equipmentData[type] || 0) + 1;
            });

            equipmentChart.data.labels = Object.keys(equipmentData);
            equipmentChart.data.datasets[0].data = Object.values(equipmentData);
            equipmentChart.update();
        }

        function loadRecentResolutions() {
            const resolvedIssues = issuesData
                .filter(issue => issue.status === 'resolved')
                .sort((a, b) => new Date(b.resolved_date) - new Date(a.resolved_date))
                .slice(0, 5);

            const container = document.getElementById('recentResolutions');

            if (resolvedIssues.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-3">
                        <i class="fas fa-clock fa-2x text-muted mb-2"></i>
                        <p class="text-muted">No recent resolutions</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = resolvedIssues.map(issue => `
                <div class="d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded">
                    <div>
                        <div class="fw-bold">#${issue.issue_id} - ${issue.title}</div>
                        <small class="text-muted">
                            ${issue.equipment_type} • ${issue.equipment_id} •
                            Resolved: ${formatDate(issue.resolved_date)}
                        </small>
                    </div>
                    <div class="text-end">
                        <span class="priority-badge priority-${issue.priority}">${issue.priority}</span>
                    </div>
                </div>
            `).join('');
        }

        function updateCriticalAlert() {
            const criticalIssues = issuesData.filter(issue =>
                issue.priority === 'critical' && !['resolved', 'closed'].includes(issue.status)
            );

            const alertElement = document.getElementById('criticalIssuesAlert');
            const countElement = document.getElementById('criticalCount');

            if (criticalIssues.length > 0) {
                countElement.textContent = criticalIssues.length;
                alertElement.classList.remove('d-none');
            } else {
                alertElement.classList.add('d-none');
            }
        }

        // Utility functions
        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                timeZone: 'America/Guyana',
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function getTimeAgo(dateString) {
            const now = new Date();
            const date = new Date(dateString);
            const diffInMinutes = Math.floor((now - date) / (1000 * 60));

            if (diffInMinutes < 60) return `${diffInMinutes}m ago`;
            if (diffInMinutes < 1440) return `${Math.floor(diffInMinutes / 60)}h ago`;
            return `${Math.floor(diffInMinutes / 1440)}d ago`;
        }

        // Refresh function
        function refreshIssues() {
            loadIssuesData();
            showAlert('Equipment issues data refreshed successfully', 'success');
        }

        // Modal functions
        async function submitIssue() {
            const formData = {
                equipment_type: document.getElementById('equipmentType').value,
                equipment_id: document.getElementById('equipmentId').value,
                title: document.getElementById('issueTitle').value,
                description: document.getElementById('issueDescription').value,
                priority: document.getElementById('issuePriority').value,
                location: document.getElementById('issueLocation').value,
                steps_to_reproduce: document.getElementById('stepsToReproduce').value,
                assigned_to: document.getElementById('assignedTo').value,
                expected_resolution: document.getElementById('expectedResolution').value
            };

            try {
                const response = await fetch('../api/equipment_issues_api.php?action=create_issue', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });

                const result = await response.json();

                if (result.status === 'success') {
                    // Close modal and reset form
                    bootstrap.Modal.getInstance(document.getElementById('reportIssueModal')).hide();
                    document.getElementById('reportIssueForm').reset();

                    // Reload data
                    loadIssuesData();

                    showAlert('Issue reported successfully', 'success');
                } else {
                    throw new Error(result.message || 'Failed to report issue');
                }
            } catch (error) {
                console.error('Error reporting issue:', error);
                showAlert('Error reporting issue: ' + error.message, 'danger');
            }
        }

        function updateIssue(issueId) {
            const issue = issuesData.find(i => i.issue_id === issueId);
            if (!issue) return;

            // Populate update form
            document.getElementById('updateIssueId').value = issue.issue_id;
            document.getElementById('updateStatus').value = issue.status;
            document.getElementById('updatePriority').value = issue.priority;
            document.getElementById('updateAssignedTo').value = issue.assigned_to || '';
            document.getElementById('updateExpectedResolution').value = issue.expected_resolution || '';

            // Show modal
            new bootstrap.Modal(document.getElementById('updateIssueModal')).show();
        }

        async function saveIssueUpdate() {
            // Implementation for updating issue
            showAlert('Update issue feature coming soon', 'info');
        }

        function viewIssue(issueId) {
            // Implementation for viewing issue details
            showAlert('View issue feature coming soon', 'info');
        }

        function resolveIssue(issueId) {
            // Implementation for resolving issue
            showAlert('Resolve issue feature coming soon', 'info');
        }

        function deleteIssue(issueId) {
            // Implementation for deleting issue
            showAlert('Delete issue feature coming soon', 'info');
        }

        // Quick action functions
        function viewCriticalIssues() {
            document.getElementById('priorityFilter').value = 'critical';
            filterIssues();
            showAlert('Showing critical issues only', 'info');
        }

        function scheduleMaintenace() {
            showAlert('Schedule maintenance feature coming soon', 'info');
        }

        function orderParts() {
            showAlert('Order parts feature coming soon', 'info');
        }

        function bulkUpdate() {
            showAlert('Bulk update feature coming soon', 'info');
        }

        function exportIssues() {
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