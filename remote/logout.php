<?php
// Remote Employee Logout System
session_start();

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

// Get session information before destroying
$user_id = $_SESSION['user_id'] ?? null;
$session_token = $_SESSION['remote_session_token'] ?? null;
$session_id = null;

if ($user_id && $session_token) {
    // Get session ID
    $stmt = $conn->prepare("SELECT session_id FROM remote_sessions WHERE session_token = ? AND user_id = ?");
    $stmt->bind_param("si", $session_token, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $session_id = $result->fetch_assoc()['session_id'];
        
        // Calculate final session metrics
        $stmt = $conn->prepare("
            SELECT 
                login_time,
                total_active_time,
                total_idle_time
            FROM remote_sessions 
            WHERE session_id = ?
        ");
        $stmt->bind_param("i", $session_id);
        $stmt->execute();
        $session_data = $stmt->get_result()->fetch_assoc();
        
        if ($session_data) {
            $total_session_time = time() - strtotime($session_data['login_time']);
            
            // Update session with logout information
            $stmt = $conn->prepare("
                UPDATE remote_sessions 
                SET 
                    logout_time = NOW(),
                    session_duration = ?,
                    status = 'ended'
                WHERE session_id = ?
            ");
            $stmt->bind_param("ii", $total_session_time, $session_id);
            $stmt->execute();
            
            // Log logout activity
            $stmt = $conn->prepare("
                INSERT INTO remote_activity_logs (
                    session_id, user_id, activity_type, page_url, action_details
                ) VALUES (?, ?, 'logout', ?, ?)
            ");
            
            $page_url = $_SERVER['REQUEST_URI'];
            $action_details = json_encode([
                'logout_time' => date('Y-m-d H:i:s'),
                'session_duration' => $total_session_time,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            
            $stmt->bind_param("iiss", $session_id, $user_id, $page_url, $action_details);
            $stmt->execute();
            
            // Update daily performance metrics
            $today = date('Y-m-d');
            $work_hours = $total_session_time / 3600; // Convert to hours
            $active_hours = $work_hours * 0.8; // Estimate 80% active time
            $idle_hours = $work_hours * 0.2; // Estimate 20% idle time
            
            $stmt = $conn->prepare("
                INSERT INTO remote_performance_metrics (
                    user_id, date, total_work_hours, active_work_hours, idle_hours
                ) VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                total_work_hours = total_work_hours + ?,
                active_work_hours = active_work_hours + ?,
                idle_hours = idle_hours + ?
            ");
            $stmt->bind_param("isdddddd", 
                $user_id, $today, $work_hours, $active_hours, $idle_hours,
                $work_hours, $active_hours, $idle_hours
            );
            $stmt->execute();
            
            // Close any open screen monitoring sessions
            $stmt = $conn->prepare("
                UPDATE remote_screen_monitoring 
                SET 
                    screen_active_end = NOW(),
                    duration_seconds = TIMESTAMPDIFF(SECOND, screen_active_start, NOW())
                WHERE session_id = ? AND screen_active_end IS NULL
            ");
            $stmt->bind_param("i", $session_id);
            $stmt->execute();
        }
    }
}

$conn->close();

// Destroy session
session_unset();
session_destroy();

// Clear any client-side storage
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remote Logout - Session Ended</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Nunito', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .logout-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 500px;
            text-align: center;
        }
        .logout-icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 20px;
        }
        .logout-title {
            color: #333;
            font-weight: 700;
            margin-bottom: 15px;
        }
        .logout-message {
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .session-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: left;
        }
        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 5px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .summary-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .btn-login-again {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            font-size: 16px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
            margin-right: 10px;
        }
        .btn-login-again:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
            color: white;
        }
        .btn-admin {
            background: #6c757d;
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            font-size: 16px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
        }
        .btn-admin:hover {
            background: #5a6268;
            color: white;
        }
        .security-note {
            background: #e3f2fd;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            font-size: 12px;
            color: #1976d2;
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="logout-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        
        <h2 class="logout-title">Session Ended Successfully</h2>
        
        <p class="logout-message">
            Your remote monitoring session has been securely terminated. All activity has been logged and your work metrics have been updated.
        </p>

        <?php if ($session_id): ?>
        <div class="session-summary">
            <h6><i class="fas fa-chart-bar"></i> Session Summary</h6>
            <div class="summary-item">
                <span>Session ID:</span>
                <strong>#<?php echo $session_id; ?></strong>
            </div>
            <div class="summary-item">
                <span>Logout Time:</span>
                <strong><?php echo date('M d, Y g:i:s A'); ?></strong>
            </div>
            <div class="summary-item">
                <span>Timezone:</span>
                <strong>Georgetown (GMT-4)</strong>
            </div>
            <div class="summary-item">
                <span>Status:</span>
                <strong class="text-success">Completed</strong>
            </div>
        </div>
        <?php endif; ?>

        <div class="mb-4">
            <a href="login.php" class="btn-login-again">
                <i class="fas fa-sign-in-alt"></i> Login Again
            </a>
            <a href="../admin/index.php" class="btn-admin">
                <i class="fas fa-cog"></i> Admin Panel
            </a>
        </div>

        <div class="security-note">
            <h6><i class="fas fa-shield-alt"></i> Security Notice</h6>
            <p class="mb-0">
                For security purposes, all remote sessions are monitored and logged. 
                Your activity data has been securely stored and will be used for 
                performance evaluation and compliance reporting.
            </p>
        </div>

        <div class="text-center mt-3">
            <small class="text-muted">
                Remote Employee Monitoring System<br>
                Georgetown, Guyana | <?php echo date('Y'); ?>
            </small>
        </div>
    </div>

    <script>
        // Clear any client-side storage
        if (typeof(Storage) !== "undefined") {
            localStorage.clear();
            sessionStorage.clear();
        }

        // Clear any cached data
        if ('caches' in window) {
            caches.keys().then(function(names) {
                for (let name of names) {
                    caches.delete(name);
                }
            });
        }

        // Prevent back button access
        window.history.pushState(null, "", window.location.href);
        window.onpopstate = function() {
            window.history.pushState(null, "", window.location.href);
        };

        // Auto-redirect after 30 seconds
        setTimeout(function() {
            window.location.href = 'login.php';
        }, 30000);

        // Show countdown
        let countdown = 30;
        const countdownElement = document.createElement('div');
        countdownElement.className = 'mt-3 text-muted';
        countdownElement.innerHTML = '<small>Redirecting to login in <span id="countdown">30</span> seconds...</small>';
        document.querySelector('.logout-container').appendChild(countdownElement);

        const countdownInterval = setInterval(function() {
            countdown--;
            document.getElementById('countdown').textContent = countdown;
            
            if (countdown <= 0) {
                clearInterval(countdownInterval);
                window.location.href = 'login.php';
            }
        }, 1000);
    </script>
</body>
</html>
