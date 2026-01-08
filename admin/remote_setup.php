<?php
// Remote Employee Monitoring System Database Setup
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

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_remote'])) {
    try {
        // REMOTE EMPLOYEE MONITORING TABLES
        
        // Remote Employee Sessions
        $sql = "CREATE TABLE IF NOT EXISTS remote_sessions (
            session_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_token VARCHAR(255) UNIQUE NOT NULL,
            login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            logout_time TIMESTAMP NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            location_data JSON,
            session_duration INT DEFAULT 0,
            total_active_time INT DEFAULT 0,
            total_idle_time INT DEFAULT 0,
            status ENUM('active', 'idle', 'disconnected', 'ended') DEFAULT 'active',
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id)
        )";
        $conn->query($sql);

        // Activity Tracking
        $sql = "CREATE TABLE IF NOT EXISTS remote_activity_logs (
            activity_id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            user_id INT NOT NULL,
            activity_type ENUM('login', 'logout', 'page_view', 'button_click', 'draw_control', 'bet_action', 'idle_start', 'idle_end', 'tab_switch', 'window_focus', 'window_blur') NOT NULL,
            page_url VARCHAR(500),
            action_details JSON,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            screen_resolution VARCHAR(20),
            browser_info JSON,
            FOREIGN KEY (session_id) REFERENCES remote_sessions(session_id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(user_id)
        )";
        $conn->query($sql);

        // Screen Monitoring
        $sql = "CREATE TABLE IF NOT EXISTS remote_screen_monitoring (
            monitor_id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            user_id INT NOT NULL,
            screen_active_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            screen_active_end TIMESTAMP NULL,
            duration_seconds INT DEFAULT 0,
            tab_switches INT DEFAULT 0,
            window_minimized_count INT DEFAULT 0,
            idle_periods INT DEFAULT 0,
            productivity_score DECIMAL(5,2) DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (session_id) REFERENCES remote_sessions(session_id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(user_id)
        )";
        $conn->query($sql);

        // Work Performance Metrics
        $sql = "CREATE TABLE IF NOT EXISTS remote_performance_metrics (
            metric_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            date DATE NOT NULL,
            total_work_hours DECIMAL(5,2) DEFAULT 0.00,
            active_work_hours DECIMAL(5,2) DEFAULT 0.00,
            idle_hours DECIMAL(5,2) DEFAULT 0.00,
            bets_monitored INT DEFAULT 0,
            draws_controlled INT DEFAULT 0,
            critical_events_handled INT DEFAULT 0,
            average_response_time DECIMAL(8,2) DEFAULT 0.00,
            productivity_percentage DECIMAL(5,2) DEFAULT 0.00,
            efficiency_rating ENUM('excellent', 'good', 'average', 'below_average', 'poor') DEFAULT 'average',
            supervisor_notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id),
            UNIQUE KEY unique_user_date (user_id, date)
        )";
        $conn->query($sql);

        // Remote Access Permissions
        $sql = "CREATE TABLE IF NOT EXISTS remote_access_permissions (
            permission_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            permission_type ENUM('bet_monitoring', 'draw_control', 'financial_data', 'user_management', 'system_admin') NOT NULL,
            granted_by INT NOT NULL,
            granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NULL,
            status ENUM('active', 'suspended', 'revoked') DEFAULT 'active',
            notes TEXT,
            FOREIGN KEY (user_id) REFERENCES users(user_id),
            FOREIGN KEY (granted_by) REFERENCES users(user_id)
        )";
        $conn->query($sql);

        // Compliance Tracking
        $sql = "CREATE TABLE IF NOT EXISTS remote_compliance_logs (
            compliance_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            compliance_type ENUM('work_hours', 'break_time', 'security_check', 'policy_acknowledgment', 'training_completion') NOT NULL,
            compliance_status ENUM('compliant', 'non_compliant', 'warning', 'violation') NOT NULL,
            details TEXT,
            violation_severity ENUM('minor', 'moderate', 'major', 'critical') NULL,
            action_required TEXT,
            resolved_at TIMESTAMP NULL,
            resolved_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id),
            FOREIGN KEY (resolved_by) REFERENCES users(user_id)
        )";
        $conn->query($sql);

        // Alert System
        $sql = "CREATE TABLE IF NOT EXISTS remote_alerts (
            alert_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            alert_type ENUM('extended_idle', 'unusual_activity', 'security_breach', 'performance_issue', 'compliance_violation') NOT NULL,
            severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
            title VARCHAR(200) NOT NULL,
            message TEXT NOT NULL,
            triggered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            acknowledged_at TIMESTAMP NULL,
            acknowledged_by INT NULL,
            resolved_at TIMESTAMP NULL,
            status ENUM('active', 'acknowledged', 'resolved', 'dismissed') DEFAULT 'active',
            FOREIGN KEY (user_id) REFERENCES users(user_id),
            FOREIGN KEY (acknowledged_by) REFERENCES users(user_id)
        )";
        $conn->query($sql);

        // Heartbeat Monitoring
        $sql = "CREATE TABLE IF NOT EXISTS remote_heartbeat (
            heartbeat_id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            user_id INT NOT NULL,
            heartbeat_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            mouse_activity BOOLEAN DEFAULT FALSE,
            keyboard_activity BOOLEAN DEFAULT FALSE,
            page_visible BOOLEAN DEFAULT TRUE,
            cpu_usage DECIMAL(5,2) DEFAULT 0.00,
            memory_usage DECIMAL(5,2) DEFAULT 0.00,
            network_status ENUM('online', 'offline', 'slow') DEFAULT 'online',
            FOREIGN KEY (session_id) REFERENCES remote_sessions(session_id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(user_id)
        )";
        $conn->query($sql);

        // Insert sample remote access permissions for admin users
        $stmt = $conn->prepare("INSERT IGNORE INTO remote_access_permissions (user_id, permission_type, granted_by) VALUES (?, ?, ?)");
        
        // Get admin users
        $result = $conn->query("SELECT user_id FROM users WHERE role = 'admin' LIMIT 3");
        if ($result && $result->num_rows > 0) {
            $admin_users = [];
            while ($row = $result->fetch_assoc()) {
                $admin_users[] = $row['user_id'];
            }
            
            $permissions = ['bet_monitoring', 'draw_control', 'financial_data'];
            
            foreach ($admin_users as $user_id) {
                foreach ($permissions as $permission) {
                    $stmt->bind_param("isi", $user_id, $permission, $admin_users[0]);
                    $stmt->execute();
                }
            }
        }

        // Create sample performance metrics for current month
        $stmt = $conn->prepare("INSERT IGNORE INTO remote_performance_metrics (
            user_id, date, total_work_hours, active_work_hours, idle_hours, 
            bets_monitored, draws_controlled, productivity_percentage, efficiency_rating
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if (!empty($admin_users)) {
            for ($i = 1; $i <= 10; $i++) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $work_hours = rand(6, 9) + (rand(0, 59) / 60);
                $active_hours = $work_hours * (rand(75, 95) / 100);
                $idle_hours = $work_hours - $active_hours;
                $bets_monitored = rand(50, 200);
                $draws_controlled = rand(10, 30);
                $productivity = rand(70, 98);
                $efficiency = $productivity >= 90 ? 'excellent' : ($productivity >= 80 ? 'good' : 'average');
                
                foreach (array_slice($admin_users, 0, 2) as $user_id) {
                    $stmt->bind_param("isdddiids", 
                        $user_id, $date, $work_hours, $active_hours, $idle_hours,
                        $bets_monitored, $draws_controlled, $productivity, $efficiency
                    );
                    $stmt->execute();
                }
            }
        }

        $message = "Remote Employee Monitoring System database tables created successfully with sample data!";
        $messageType = "success";

    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remote Monitoring Setup - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin.css">
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
                <h1 class="page-title">Remote Employee Monitoring Setup</h1>
                <div class="breadcrumb">
                    <div class="breadcrumb-item"><a href="index.php">Admin</a></div>
                    <div class="breadcrumb-item active">Remote Monitoring Setup</div>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-12">
                    <div class="card shadow">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold text-primary">Setup Remote Employee Monitoring System</h6>
                        </div>
                        <div class="card-body">
                            <p>This will create comprehensive database tables for remote employee monitoring and tracking:</p>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card border-left-success">
                                        <div class="card-body">
                                            <h5 class="text-success"><i class="fas fa-desktop"></i> Session Monitoring</h5>
                                            <ul class="list-unstyled">
                                                <li>• Login/Logout Tracking</li>
                                                <li>• Screen Activity Monitoring</li>
                                                <li>• Work Duration Calculation</li>
                                                <li>• Real-time Heartbeat System</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="card border-left-warning">
                                        <div class="card-body">
                                            <h5 class="text-warning"><i class="fas fa-chart-line"></i> Performance Analytics</h5>
                                            <ul class="list-unstyled">
                                                <li>• Productivity Metrics</li>
                                                <li>• Activity Logging</li>
                                                <li>• Compliance Tracking</li>
                                                <li>• Alert Management</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <div class="card border-left-info">
                                        <div class="card-body">
                                            <h5 class="text-info"><i class="fas fa-shield-alt"></i> Security Features</h5>
                                            <ul class="list-unstyled">
                                                <li>• Access Permission Control</li>
                                                <li>• Session Token Management</li>
                                                <li>• IP Address Tracking</li>
                                                <li>• Security Breach Detection</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="card border-left-danger">
                                        <div class="card-body">
                                            <h5 class="text-danger"><i class="fas fa-bell"></i> Alert System</h5>
                                            <ul class="list-unstyled">
                                                <li>• Extended Idle Alerts</li>
                                                <li>• Unusual Activity Detection</li>
                                                <li>• Performance Issue Warnings</li>
                                                <li>• Compliance Violations</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center mt-4">
                                <form method="post" action="">
                                    <button type="submit" name="setup_remote" class="btn btn-success btn-lg">
                                        <i class="fas fa-database"></i> Create Remote Monitoring Tables
                                    </button>
                                </form>
                            </div>

                            <div class="mt-4">
                                <h6 class="text-primary">System Features:</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <ul class="list-unstyled">
                                            <li><i class="fas fa-check text-success"></i> Real-time session tracking</li>
                                            <li><i class="fas fa-check text-success"></i> Screen activity monitoring</li>
                                            <li><i class="fas fa-check text-success"></i> Productivity analytics</li>
                                            <li><i class="fas fa-check text-success"></i> Compliance reporting</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <ul class="list-unstyled">
                                            <li><i class="fas fa-check text-success"></i> Automated alert system</li>
                                            <li><i class="fas fa-check text-success"></i> Performance dashboards</li>
                                            <li><i class="fas fa-check text-success"></i> Security monitoring</li>
                                            <li><i class="fas fa-check text-success"></i> Georgetown timezone support</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
