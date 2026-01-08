<?php
// HR Department Dashboard
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Check if user has HR department access
$user_role = $_SESSION['role'] ?? '';
$allowed_roles = ['admin', 'hr_manager', 'hr_staff'];

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
    'total_employees' => 0,
    'active_employees' => 0,
    'new_hires_month' => 0,
    'pending_leave_requests' => 0,
    'monthly_payroll' => 0,
    'pending_applications' => 0
];

// Total employees
$result = $conn->query("SELECT COUNT(*) as count FROM employees");
if ($result && $result->num_rows > 0) {
    $stats['total_employees'] = $result->fetch_assoc()['count'];
}

// Active employees
$result = $conn->query("SELECT COUNT(*) as count FROM employees WHERE employment_status = 'active'");
if ($result && $result->num_rows > 0) {
    $stats['active_employees'] = $result->fetch_assoc()['count'];
}

// New hires this month
$result = $conn->query("SELECT COUNT(*) as count FROM employees WHERE MONTH(hire_date) = MONTH(CURDATE()) AND YEAR(hire_date) = YEAR(CURDATE())");
if ($result && $result->num_rows > 0) {
    $stats['new_hires_month'] = $result->fetch_assoc()['count'];
}

// Pending leave requests
$result = $conn->query("SELECT COUNT(*) as count FROM leave_requests WHERE status = 'pending'");
if ($result && $result->num_rows > 0) {
    $stats['pending_leave_requests'] = $result->fetch_assoc()['count'];
}

// Monthly payroll (current month)
$result = $conn->query("
    SELECT SUM(net_pay) as total 
    FROM payroll_records 
    WHERE MONTH(pay_period_start) = MONTH(CURDATE()) 
    AND YEAR(pay_period_start) = YEAR(CURDATE())
    AND status = 'paid'
");
if ($result && $result->num_rows > 0) {
    $stats['monthly_payroll'] = $result->fetch_assoc()['total'] ?? 0;
}

// Pending job applications
$result = $conn->query("SELECT COUNT(*) as count FROM job_applications WHERE status IN ('applied', 'screening', 'interview')");
if ($result && $result->num_rows > 0) {
    $stats['pending_applications'] = $result->fetch_assoc()['count'];
}

// Get department distribution
$department_distribution = [];
$result = $conn->query("
    SELECT 
        department,
        COUNT(*) as employee_count
    FROM employees 
    WHERE employment_status = 'active'
    GROUP BY department
    ORDER BY employee_count DESC
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $department_distribution[] = $row;
    }
}

// Get recent activities
$recent_activities = [];
$result = $conn->query("
    (SELECT 'hire' as activity_type, CONCAT(first_name, ' ', last_name, ' hired as ', job_title) as description, hire_date as activity_time 
     FROM employees 
     WHERE hire_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY))
    UNION ALL
    (SELECT 'leave' as activity_type, CONCAT('Leave request: ', leave_type) as description, requested_at as activity_time 
     FROM leave_requests 
     WHERE requested_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))
    UNION ALL
    (SELECT 'training' as activity_type, CONCAT('Training: ', training_name) as description, training_date as activity_time 
     FROM training_records 
     WHERE training_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY))
    ORDER BY activity_time DESC 
    LIMIT 10
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recent_activities[] = $row;
    }
}

// Get attendance summary for today
$attendance_today = [];
$result = $conn->query("
    SELECT 
        status,
        COUNT(*) as count
    FROM attendance_records 
    WHERE date = CURDATE()
    GROUP BY status
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $attendance_today[] = $row;
    }
}

// Get upcoming birthdays
$upcoming_birthdays = [];
$result = $conn->query("
    SELECT 
        first_name,
        last_name,
        date_of_birth,
        job_title,
        DAYOFYEAR(date_of_birth) - DAYOFYEAR(CURDATE()) as days_until
    FROM employees 
    WHERE employment_status = 'active'
    AND date_of_birth IS NOT NULL
    AND (
        (DAYOFYEAR(date_of_birth) >= DAYOFYEAR(CURDATE()) AND DAYOFYEAR(date_of_birth) <= DAYOFYEAR(CURDATE()) + 30)
        OR (DAYOFYEAR(date_of_birth) <= 30 AND DAYOFYEAR(CURDATE()) > 335)
    )
    ORDER BY 
        CASE 
            WHEN DAYOFYEAR(date_of_birth) >= DAYOFYEAR(CURDATE()) 
            THEN DAYOFYEAR(date_of_birth) - DAYOFYEAR(CURDATE())
            ELSE (365 - DAYOFYEAR(CURDATE())) + DAYOFYEAR(date_of_birth)
        END
    LIMIT 5
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $upcoming_birthdays[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Department Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #008B8B 0%, #20B2AA 100%);
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
            background: linear-gradient(135deg, #008B8B 0%, #20B2AA 100%);
            color: white;
        }
        .activity-item {
            padding: 10px;
            border-left: 3px solid #008B8B;
            margin-bottom: 10px;
            background: #f8f9fc;
            border-radius: 5px;
        }
        .activity-hire { border-left-color: #28a745; }
        .activity-leave { border-left-color: #ffc107; }
        .activity-training { border-left-color: #007bff; }
        .birthday-item {
            padding: 8px;
            margin-bottom: 8px;
            border-radius: 5px;
            background: #e8f5e8;
            border-left: 3px solid #28a745;
        }
        .attendance-item {
            padding: 8px;
            margin-bottom: 8px;
            border-radius: 5px;
            background: #f8f9fc;
            border-left: 3px solid #6c757d;
        }
        .attendance-present { border-left-color: #28a745; background: #d4edda; }
        .attendance-absent { border-left-color: #dc3545; background: #f8d7da; }
        .attendance-late { border-left-color: #ffc107; background: #fff3cd; }
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
            <h4 class="text-info"><i class="fas fa-users"></i> HR Dept</h4>
        </div>
        <nav class="nav flex-column px-3">
            <a class="nav-link active" href="index.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a class="nav-link" href="employees.php">
                <i class="fas fa-users"></i> Employee Management
            </a>
            <a class="nav-link" href="payroll.php">
                <i class="fas fa-money-check-alt"></i> Payroll System
            </a>
            <a class="nav-link" href="attendance.php">
                <i class="fas fa-clock"></i> Attendance Tracking
            </a>
            <a class="nav-link" href="performance.php">
                <i class="fas fa-chart-line"></i> Performance Management
            </a>
            <a class="nav-link" href="recruitment.php">
                <i class="fas fa-user-plus"></i> Recruitment & Hiring
            </a>
            <a class="nav-link" href="training.php">
                <i class="fas fa-graduation-cap"></i> Training & Development
            </a>
            <a class="nav-link" href="benefits.php">
                <i class="fas fa-heart"></i> Benefits Administration
            </a>
            <a class="nav-link" href="disciplinary.php">
                <i class="fas fa-gavel"></i> Disciplinary Actions
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
                <h1 class="text-white">Human Resources Dashboard</h1>
                <p class="text-white-50 mb-0">
                    <span class="real-time-indicator"></span>
                    Employee monitoring | Last updated: <span id="last-updated"><?php echo date('g:i:s A'); ?></span>
                </p>
            </div>
            <div>
                <button class="btn btn-light" onclick="refreshDashboard()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <a href="employees.php?action=new" class="btn btn-info">
                    <i class="fas fa-user-plus"></i> Add Employee
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
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Employees</div>
                                <div class="stat-value text-gray-800" id="total-employees"><?php echo $stats['total_employees']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-users stat-icon text-gray-300"></i>
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
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Staff</div>
                                <div class="stat-value text-gray-800" id="active-employees"><?php echo $stats['active_employees']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-user-check stat-icon text-gray-300"></i>
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
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">New Hires (Month)</div>
                                <div class="stat-value text-gray-800" id="new-hires"><?php echo $stats['new_hires_month']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-user-plus stat-icon text-gray-300"></i>
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
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Leave</div>
                                <div class="stat-value text-gray-800" id="pending-leave"><?php echo $stats['pending_leave_requests']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-calendar-times stat-icon text-gray-300"></i>
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
                                <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Monthly Payroll</div>
                                <div class="stat-value text-gray-800" id="monthly-payroll">$<?php echo number_format($stats['monthly_payroll'], 0); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-money-check-alt stat-icon text-gray-300"></i>
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
                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Job Applications</div>
                                <div class="stat-value text-gray-800" id="pending-applications"><?php echo $stats['pending_applications']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-file-alt stat-icon text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts and Activities -->
        <div class="row">
            <!-- Department Distribution Chart -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-chart-pie"></i> Employee Distribution by Department
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="position: relative; height: 300px;">
                            <canvas id="departmentChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent HR Activities -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-list"></i> Recent HR Activities
                        </h6>
                    </div>
                    <div class="card-body">
                        <div id="recent-activities" style="max-height: 300px; overflow-y: auto;">
                            <?php if (empty($recent_activities)): ?>
                                <p class="text-muted text-center">No recent activities</p>
                            <?php else: ?>
                                <?php foreach ($recent_activities as $activity): ?>
                                <div class="activity-item activity-<?php echo $activity['activity_type']; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <i class="fas fa-<?php echo $activity['activity_type'] === 'hire' ? 'user-plus' : ($activity['activity_type'] === 'leave' ? 'calendar-times' : 'graduation-cap'); ?> text-primary me-2"></i>
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

        <!-- Attendance and Birthdays -->
        <div class="row">
            <!-- Today's Attendance -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-clock"></i> Today's Attendance
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($attendance_today)): ?>
                            <p class="text-muted text-center">No attendance data for today</p>
                        <?php else: ?>
                            <?php foreach ($attendance_today as $attendance): ?>
                            <div class="attendance-item attendance-<?php echo $attendance['status']; ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo ucfirst(str_replace('_', ' ', $attendance['status'])); ?></strong>
                                    </div>
                                    <div>
                                        <span class="badge bg-<?php echo $attendance['status'] === 'present' ? 'success' : ($attendance['status'] === 'absent' ? 'danger' : 'warning'); ?>">
                                            <?php echo $attendance['count']; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Upcoming Birthdays -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-birthday-cake"></i> Upcoming Birthdays
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($upcoming_birthdays)): ?>
                            <p class="text-muted text-center">No upcoming birthdays</p>
                        <?php else: ?>
                            <?php foreach ($upcoming_birthdays as $birthday): ?>
                            <div class="birthday-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($birthday['first_name'] . ' ' . $birthday['last_name']); ?></strong>
                                        <br>
                                        <small><?php echo htmlspecialchars($birthday['job_title']); ?></small>
                                    </div>
                                    <div class="text-end">
                                        <small><?php echo date('M d', strtotime($birthday['date_of_birth'])); ?></small>
                                        <br>
                                        <small class="text-muted">
                                            <?php 
                                            $days = $birthday['days_until'];
                                            if ($days < 0) $days += 365;
                                            echo $days == 0 ? 'Today!' : "in {$days} days";
                                            ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
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
                            <a href="employees.php?action=new" class="btn btn-info">
                                <i class="fas fa-user-plus"></i> Add Employee
                            </a>
                            <a href="payroll.php?action=process" class="btn btn-success">
                                <i class="fas fa-money-check-alt"></i> Process Payroll
                            </a>
                            <a href="attendance.php?action=today" class="btn btn-warning">
                                <i class="fas fa-clock"></i> View Attendance
                            </a>
                            <a href="recruitment.php?action=new" class="btn btn-primary">
                                <i class="fas fa-briefcase"></i> Post Job
                            </a>
                            <a href="training.php?action=schedule" class="btn btn-secondary">
                                <i class="fas fa-graduation-cap"></i> Schedule Training
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Department Distribution Chart
        const departmentData = <?php echo json_encode($department_distribution); ?>;
        
        if (departmentData.length > 0) {
            const ctx = document.getElementById('departmentChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: departmentData.map(item => item.department),
                    datasets: [{
                        data: departmentData.map(item => parseInt(item.employee_count)),
                        backgroundColor: [
                            '#008B8B', '#20B2AA', '#48CAE4', '#90E0EF', '#ADE8F4', '#CAF0F8'
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
                                    return context.label + ': ' + context.parsed + ' employees';
                                }
                            }
                        }
                    }
                }
            });
        }

        // Real-time updates
        function refreshDashboard() {
            fetch('api/hr_dashboard_data.php')
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
            document.getElementById('total-employees').textContent = stats.total_employees;
            document.getElementById('active-employees').textContent = stats.active_employees;
            document.getElementById('new-hires').textContent = stats.new_hires_month;
            document.getElementById('pending-leave').textContent = stats.pending_leave_requests;
            document.getElementById('monthly-payroll').textContent = '$' + parseInt(stats.monthly_payroll).toLocaleString();
            document.getElementById('pending-applications').textContent = stats.pending_applications;
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
