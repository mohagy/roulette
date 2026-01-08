<?php
// IT Department Dashboard
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Check if user has IT department access
$user_role = $_SESSION['role'] ?? '';
$allowed_roles = ['admin', 'it_manager', 'it_staff'];

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
    'open_tickets' => 0,
    'critical_tickets' => 0,
    'active_projects' => 0,
    'equipment_total' => 0,
    'maintenance_overdue' => 0,
    'resolved_today' => 0
];

// Open tickets
$result = $conn->query("SELECT COUNT(*) as count FROM it_tickets WHERE status IN ('open', 'assigned', 'in_progress')");
if ($result && $result->num_rows > 0) {
    $stats['open_tickets'] = $result->fetch_assoc()['count'];
}

// Critical tickets
$result = $conn->query("SELECT COUNT(*) as count FROM it_tickets WHERE priority = 'critical' AND status NOT IN ('resolved', 'closed')");
if ($result && $result->num_rows > 0) {
    $stats['critical_tickets'] = $result->fetch_assoc()['count'];
}

// Active projects
$result = $conn->query("SELECT COUNT(*) as count FROM it_projects WHERE status IN ('approved', 'in_progress')");
if ($result && $result->num_rows > 0) {
    $stats['active_projects'] = $result->fetch_assoc()['count'];
}

// Total equipment
$result = $conn->query("SELECT COUNT(*) as count FROM equipment WHERE status != 'retired'");
if ($result && $result->num_rows > 0) {
    $stats['equipment_total'] = $result->fetch_assoc()['count'];
}

// Maintenance overdue
$result = $conn->query("SELECT COUNT(*) as count FROM equipment WHERE next_maintenance < CURDATE() AND status = 'active'");
if ($result && $result->num_rows > 0) {
    $stats['maintenance_overdue'] = $result->fetch_assoc()['count'];
}

// Resolved today
$result = $conn->query("SELECT COUNT(*) as count FROM it_tickets WHERE DATE(resolved_at) = CURDATE()");
if ($result && $result->num_rows > 0) {
    $stats['resolved_today'] = $result->fetch_assoc()['count'];
}

// Get recent tickets
$recent_tickets = [];
$result = $conn->query("
    SELECT t.ticket_number, t.title, t.priority, t.status, t.created_at, bs.shop_name
    FROM it_tickets t
    LEFT JOIN betting_shops bs ON t.shop_id = bs.shop_id
    ORDER BY t.created_at DESC
    LIMIT 10
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recent_tickets[] = $row;
    }
}

// Get equipment status distribution
$equipment_status = [];
$result = $conn->query("
    SELECT status, COUNT(*) as count
    FROM equipment
    WHERE status != 'retired'
    GROUP BY status
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $equipment_status[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Department Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
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
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
        }
        .priority-critical { color: #dc3545; font-weight: bold; }
        .priority-high { color: #fd7e14; font-weight: bold; }
        .priority-medium { color: #ffc107; }
        .priority-low { color: #28a745; }
        .status-open { color: #dc3545; }
        .status-assigned { color: #fd7e14; }
        .status-in_progress { color: #007bff; }
        .status-resolved { color: #28a745; }
        .ticket-item {
            padding: 10px;
            border-left: 3px solid #1e3c72;
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
            <h4 class="text-primary"><i class="fas fa-laptop"></i> IT Dept</h4>
        </div>
        <nav class="nav flex-column px-3">
            <a class="nav-link active" href="index.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a class="nav-link" href="tickets.php">
                <i class="fas fa-ticket-alt"></i> Help Desk Tickets
            </a>
            <a class="nav-link" href="equipment.php">
                <i class="fas fa-desktop"></i> Equipment Management
            </a>
            <a class="nav-link" href="maintenance.php">
                <i class="fas fa-tools"></i> Maintenance Schedule
            </a>
            <a class="nav-link" href="projects.php">
                <i class="fas fa-project-diagram"></i> IT Projects
            </a>
            <a class="nav-link" href="staff.php">
                <i class="fas fa-users"></i> Staff Management
            </a>
            <a class="nav-link" href="assets.php">
                <i class="fas fa-inventory"></i> Asset Management
            </a>
            <a class="nav-link" href="monitoring.php">
                <i class="fas fa-chart-line"></i> Network Monitoring
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
                <h1 class="text-white">IT Department Dashboard</h1>
                <p class="text-white-50 mb-0">
                    <span class="real-time-indicator"></span>
                    System monitoring | Last updated: <span id="last-updated"><?php echo date('g:i:s A'); ?></span>
                </p>
            </div>
            <div>
                <button class="btn btn-light" onclick="refreshDashboard()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <a href="tickets.php?action=new" class="btn btn-danger">
                    <i class="fas fa-plus"></i> New Ticket
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card stat-card border-left-danger">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Open Tickets</div>
                                <div class="stat-value text-gray-800" id="open-tickets"><?php echo $stats['open_tickets']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-ticket-alt stat-icon text-gray-300"></i>
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
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Critical Issues</div>
                                <div class="stat-value text-gray-800" id="critical-tickets"><?php echo $stats['critical_tickets']; ?></div>
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
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Active Projects</div>
                                <div class="stat-value text-gray-800" id="active-projects"><?php echo $stats['active_projects']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-project-diagram stat-icon text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card stat-card border-left-primary">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Equipment</div>
                                <div class="stat-value text-gray-800" id="equipment-total"><?php echo $stats['equipment_total']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-desktop stat-icon text-gray-300"></i>
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
                                <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Overdue Maintenance</div>
                                <div class="stat-value text-gray-800" id="maintenance-overdue"><?php echo $stats['maintenance_overdue']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-wrench stat-icon text-gray-300"></i>
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
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Resolved Today</div>
                                <div class="stat-value text-gray-800" id="resolved-today"><?php echo $stats['resolved_today']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-check-circle stat-icon text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts and Recent Tickets -->
        <div class="row">
            <!-- Equipment Status Chart -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-chart-pie"></i> Equipment Status Distribution
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="position: relative; height: 300px;">
                            <canvas id="equipmentStatusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Tickets -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-list"></i> Recent Tickets
                        </h6>
                    </div>
                    <div class="card-body">
                        <div id="recent-tickets" style="max-height: 300px; overflow-y: auto;">
                            <?php if (empty($recent_tickets)): ?>
                                <p class="text-muted text-center">No recent tickets</p>
                            <?php else: ?>
                                <?php foreach ($recent_tickets as $ticket): ?>
                                <div class="ticket-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?php echo htmlspecialchars($ticket['ticket_number']); ?></strong>
                                            <br>
                                            <small><?php echo htmlspecialchars($ticket['title']); ?></small>
                                            <?php if ($ticket['shop_name']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($ticket['shop_name']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge priority-<?php echo $ticket['priority']; ?>"><?php echo ucfirst($ticket['priority']); ?></span>
                                            <br>
                                            <span class="badge status-<?php echo $ticket['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?></span>
                                            <br>
                                            <small class="text-muted"><?php echo date('M d', strtotime($ticket['created_at'])); ?></small>
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
                                <a href="tickets.php?action=new" class="btn btn-danger btn-block mb-2">
                                    <i class="fas fa-plus"></i> Create Ticket
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="equipment.php?action=add" class="btn btn-primary btn-block mb-2">
                                    <i class="fas fa-plus"></i> Add Equipment
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="maintenance.php" class="btn btn-warning btn-block mb-2">
                                    <i class="fas fa-calendar"></i> Schedule Maintenance
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="projects.php?action=new" class="btn btn-success btn-block mb-2">
                                    <i class="fas fa-plus"></i> New Project
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
        // Equipment Status Chart
        const equipmentData = <?php echo json_encode($equipment_status); ?>;

        if (equipmentData.length > 0) {
            const ctx = document.getElementById('equipmentStatusChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: equipmentData.map(item => item.status.charAt(0).toUpperCase() + item.status.slice(1)),
                    datasets: [{
                        data: equipmentData.map(item => parseInt(item.count)),
                        backgroundColor: [
                            '#28a745', // active - green
                            '#ffc107', // maintenance - yellow
                            '#dc3545', // repair - red
                            '#6c757d'  // other - gray
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
                        }
                    }
                }
            });
        }

        // Real-time updates
        function refreshDashboard() {
            fetch('api/it_dashboard_data.php')
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
            document.getElementById('open-tickets').textContent = stats.open_tickets;
            document.getElementById('critical-tickets').textContent = stats.critical_tickets;
            document.getElementById('active-projects').textContent = stats.active_projects;
            document.getElementById('equipment-total').textContent = stats.equipment_total;
            document.getElementById('maintenance-overdue').textContent = stats.maintenance_overdue;
            document.getElementById('resolved-today').textContent = stats.resolved_today;
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
