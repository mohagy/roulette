<?php
// Remote Employee Bet Distribution Monitoring System
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
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

// Check remote access permissions
$user_id = $_SESSION['user_id'];
$has_bet_monitoring = false;
$has_draw_control = false;

$stmt = $conn->prepare("SELECT permission_type FROM remote_access_permissions WHERE user_id = ? AND status = 'active' AND (expires_at IS NULL OR expires_at > NOW())");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    if ($row['permission_type'] === 'bet_monitoring') $has_bet_monitoring = true;
    if ($row['permission_type'] === 'draw_control') $has_draw_control = true;
}

if (!$has_bet_monitoring && !$has_draw_control) {
    header('Location: access_denied.php');
    exit;
}

// Initialize or get current session
$session_token = $_SESSION['remote_session_token'] ?? null;
$session_id = null;

if (!$session_token) {
    // Create new remote session
    $session_token = bin2hex(random_bytes(32));
    $_SESSION['remote_session_token'] = $session_token;
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $stmt = $conn->prepare("INSERT INTO remote_sessions (user_id, session_token, ip_address, user_agent) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $session_token, $ip_address, $user_agent);
    $stmt->execute();
    $session_id = $conn->insert_id;
} else {
    // Get existing session
    $stmt = $conn->prepare("SELECT session_id FROM remote_sessions WHERE session_token = ? AND user_id = ? AND status = 'active'");
    $stmt->bind_param("si", $session_token, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $session_id = $result->fetch_assoc()['session_id'];
    }
}

// Log page access
if ($session_id) {
    $stmt = $conn->prepare("INSERT INTO remote_activity_logs (session_id, user_id, activity_type, page_url, action_details) VALUES (?, ?, 'page_view', ?, ?)");
    $page_url = $_SERVER['REQUEST_URI'];
    $action_details = json_encode(['timestamp' => date('Y-m-d H:i:s'), 'referrer' => $_SERVER['HTTP_REFERER'] ?? null]);
    $stmt->bind_param("iiss", $session_id, $user_id, $page_url, $action_details);
    $stmt->execute();
}

// Get current draw information
$current_draw = null;
$result = $conn->query("SELECT * FROM roulette_analytics ORDER BY draw_number DESC LIMIT 1");
if ($result && $result->num_rows > 0) {
    $current_draw = $result->fetch_assoc();
}

// Get upcoming draws
$upcoming_draws = [];
$result = $conn->query("
    SELECT 
        draw_number,
        scheduled_time,
        status
    FROM upcoming_draws 
    ORDER BY scheduled_time ASC 
    LIMIT 10
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $upcoming_draws[] = $row;
    }
}

// Get recent betting activity
$recent_bets = [];
$result = $conn->query("
    SELECT 
        sd.slip_id,
        sd.bet_amount,
        sd.potential_payout,
        sd.created_at,
        u.username,
        bs.shop_name
    FROM slip_details sd
    JOIN betting_slips s ON sd.slip_id = s.slip_id
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN betting_shops bs ON u.shop_id = bs.shop_id
    WHERE sd.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ORDER BY sd.created_at DESC
    LIMIT 20
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recent_bets[] = $row;
    }
}

// Get betting statistics
$betting_stats = [
    'total_bets_today' => 0,
    'total_amount_today' => 0,
    'active_slips' => 0,
    'completed_draws_today' => 0
];

$result = $conn->query("
    SELECT 
        COUNT(*) as total_bets,
        SUM(bet_amount) as total_amount
    FROM slip_details sd
    JOIN betting_slips s ON sd.slip_id = s.slip_id
    WHERE DATE(sd.created_at) = CURDATE()
");

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $betting_stats['total_bets_today'] = $row['total_bets'] ?? 0;
    $betting_stats['total_amount_today'] = $row['total_amount'] ?? 0;
}

$result = $conn->query("SELECT COUNT(*) as count FROM betting_slips WHERE status = 'active'");
if ($result && $result->num_rows > 0) {
    $betting_stats['active_slips'] = $result->fetch_assoc()['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM roulette_analytics WHERE DATE(timestamp) = CURDATE()");
if ($result && $result->num_rows > 0) {
    $betting_stats['completed_draws_today'] = $result->fetch_assoc()['count'];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remote Bet Distribution Monitoring</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Nunito', sans-serif;
        }
        .main-container {
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
        .monitoring-panel {
            position: fixed;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 10px;
            border-radius: 10px;
            font-size: 12px;
            z-index: 1000;
        }
        .activity-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 5px;
        }
        .status-active { background: #28a745; animation: pulse 2s infinite; }
        .status-idle { background: #ffc107; }
        .status-away { background: #dc3545; }
        .draw-control-panel {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .bet-item {
            padding: 10px;
            border-left: 3px solid #667eea;
            margin-bottom: 10px;
            background: #f8f9fc;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        .bet-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        .upcoming-draw {
            padding: 8px;
            margin-bottom: 8px;
            border-radius: 5px;
            background: #e3f2fd;
            border-left: 3px solid #2196f3;
        }
        .draw-active {
            background: #e8f5e8;
            border-left-color: #28a745;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        .navbar-remote {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            margin-bottom: 20px;
            padding: 15px 20px;
        }
        .btn-draw-control {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            border: none;
            color: white;
            font-weight: 600;
            margin: 5px;
        }
        .btn-draw-control:hover {
            background: linear-gradient(135deg, #ee5a24 0%, #ff6b6b 100%);
            color: white;
        }
    </style>
</head>
<body>
    <!-- Remote Monitoring Panel -->
    <div class="monitoring-panel" id="monitoringPanel">
        <div><span class="activity-indicator status-active" id="activityIndicator"></span> <span id="activityStatus">Active</span></div>
        <div>Session: <span id="sessionTime">00:00:00</span></div>
        <div>Active: <span id="activeTime">00:00:00</span></div>
        <div>Idle: <span id="idleTime">00:00:00</span></div>
    </div>

    <div class="main-container">
        <!-- Navigation -->
        <div class="navbar-remote">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-0"><i class="fas fa-desktop text-primary"></i> Remote Bet Distribution</h4>
                    <small class="text-muted">Employee: <?php echo htmlspecialchars($_SESSION['username'] ?? 'Unknown'); ?> | Session: <?php echo date('M d, Y g:i A'); ?></small>
                </div>
                <div>
                    <button class="btn btn-outline-primary btn-sm" onclick="refreshData()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <a href="logout.php" class="btn btn-outline-danger btn-sm">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card border-left-primary">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Today's Bets</div>
                                <div class="stat-value text-gray-800" id="totalBets"><?php echo number_format($betting_stats['total_bets_today']); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-ticket-alt stat-icon text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card stat-card border-left-success">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Amount</div>
                                <div class="stat-value text-gray-800" id="totalAmount">$<?php echo number_format($betting_stats['total_amount_today'], 2); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-dollar-sign stat-icon text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card stat-card border-left-info">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Active Slips</div>
                                <div class="stat-value text-gray-800" id="activeSlips"><?php echo number_format($betting_stats['active_slips']); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-file-alt stat-icon text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card stat-card border-left-warning">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Draws Today</div>
                                <div class="stat-value text-gray-800" id="drawsToday"><?php echo number_format($betting_stats['completed_draws_today']); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-circle-notch stat-icon text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Draw Control Panel -->
        <?php if ($has_draw_control): ?>
        <div class="draw-control-panel">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5 class="mb-2"><i class="fas fa-cogs"></i> Draw Control Center</h5>
                    <p class="mb-0">Current Draw: #<?php echo $current_draw['draw_number'] ?? 'N/A'; ?> | 
                    Last Number: <strong><?php echo $current_draw['winning_number'] ?? 'N/A'; ?></strong> |
                    Status: <span class="badge bg-light text-dark">Active</span></p>
                </div>
                <div class="col-md-4 text-end">
                    <button class="btn btn-draw-control" onclick="triggerDraw()">
                        <i class="fas fa-play"></i> Trigger Draw
                    </button>
                    <button class="btn btn-draw-control" onclick="resetSystem()">
                        <i class="fas fa-redo"></i> Reset System
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Main Content -->
        <div class="row">
            <!-- Recent Betting Activity -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-chart-line"></i> Recent Betting Activity (Last Hour)
                        </h6>
                    </div>
                    <div class="card-body">
                        <div id="recentBets" style="max-height: 400px; overflow-y: auto;">
                            <?php if (empty($recent_bets)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No Recent Activity</h5>
                                    <p class="text-muted">No betting activity in the last hour.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recent_bets as $bet): ?>
                                <div class="bet-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong>Slip #<?php echo $bet['slip_id']; ?></strong>
                                            <br>
                                            <small><?php echo htmlspecialchars($bet['username']); ?></small>
                                            <?php if ($bet['shop_name']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($bet['shop_name']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-success">$<?php echo number_format($bet['bet_amount'], 2); ?></span>
                                            <br>
                                            <small class="text-muted">Potential: $<?php echo number_format($bet['potential_payout'], 2); ?></small>
                                            <br>
                                            <small class="text-muted"><?php echo date('g:i A', strtotime($bet['created_at'])); ?></small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upcoming Draws -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-clock"></i> Upcoming Draws
                        </h6>
                    </div>
                    <div class="card-body">
                        <div id="upcomingDraws" style="max-height: 400px; overflow-y: auto;">
                            <?php if (empty($upcoming_draws)): ?>
                                <p class="text-muted text-center">No upcoming draws scheduled</p>
                            <?php else: ?>
                                <?php foreach ($upcoming_draws as $draw): ?>
                                <div class="upcoming-draw <?php echo $draw['status'] === 'active' ? 'draw-active' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>Draw #<?php echo $draw['draw_number']; ?></strong>
                                            <br>
                                            <small><?php echo date('M d, g:i A', strtotime($draw['scheduled_time'])); ?></small>
                                        </div>
                                        <div>
                                            <span class="badge bg-<?php echo $draw['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($draw['status']); ?>
                                            </span>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Remote monitoring variables
        let sessionStartTime = new Date();
        let lastActivityTime = new Date();
        let totalIdleTime = 0;
        let isIdle = false;
        let idleStartTime = null;
        let activityStatus = 'active';
        
        // Session tracking
        const sessionId = <?php echo $session_id ?? 'null'; ?>;
        const userId = <?php echo $user_id; ?>;

        // Initialize monitoring
        document.addEventListener('DOMContentLoaded', function() {
            initializeMonitoring();
            updateTimers();
            setInterval(updateTimers, 1000);
            setInterval(sendHeartbeat, 30000); // Send heartbeat every 30 seconds
            setInterval(refreshData, 60000); // Refresh data every minute
        });

        function initializeMonitoring() {
            // Track mouse movement
            document.addEventListener('mousemove', recordActivity);
            document.addEventListener('keypress', recordActivity);
            document.addEventListener('click', recordActivity);
            
            // Track window focus/blur
            window.addEventListener('focus', function() {
                recordActivity();
                logActivity('window_focus');
            });
            
            window.addEventListener('blur', function() {
                logActivity('window_blur');
            });
            
            // Track page visibility
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    logActivity('tab_switch');
                } else {
                    recordActivity();
                }
            });
        }

        function recordActivity() {
            const now = new Date();
            
            if (isIdle) {
                // Coming back from idle
                const idleDuration = Math.floor((now - idleStartTime) / 1000);
                totalIdleTime += idleDuration;
                isIdle = false;
                idleStartTime = null;
                activityStatus = 'active';
                logActivity('idle_end', {duration: idleDuration});
            }
            
            lastActivityTime = now;
            updateActivityIndicator();
        }

        function checkIdleStatus() {
            const now = new Date();
            const timeSinceActivity = now - lastActivityTime;
            
            if (timeSinceActivity > 300000 && !isIdle) { // 5 minutes
                isIdle = true;
                idleStartTime = now;
                activityStatus = 'idle';
                logActivity('idle_start');
                updateActivityIndicator();
            }
        }

        function updateTimers() {
            const now = new Date();
            const sessionDuration = Math.floor((now - sessionStartTime) / 1000);
            const activeDuration = sessionDuration - totalIdleTime;
            
            document.getElementById('sessionTime').textContent = formatTime(sessionDuration);
            document.getElementById('activeTime').textContent = formatTime(activeDuration);
            document.getElementById('idleTime').textContent = formatTime(totalIdleTime);
            
            checkIdleStatus();
        }

        function updateActivityIndicator() {
            const indicator = document.getElementById('activityIndicator');
            const status = document.getElementById('activityStatus');
            
            indicator.className = 'activity-indicator status-' + activityStatus;
            status.textContent = activityStatus.charAt(0).toUpperCase() + activityStatus.slice(1);
        }

        function formatTime(seconds) {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = seconds % 60;
            return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        }

        function logActivity(activityType, details = {}) {
            if (!sessionId) return;
            
            fetch('api/log_activity.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    session_id: sessionId,
                    user_id: userId,
                    activity_type: activityType,
                    page_url: window.location.href,
                    action_details: details,
                    screen_resolution: screen.width + 'x' + screen.height
                })
            }).catch(error => console.error('Activity logging error:', error));
        }

        function sendHeartbeat() {
            if (!sessionId) return;
            
            fetch('api/heartbeat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    session_id: sessionId,
                    user_id: userId,
                    mouse_activity: !isIdle,
                    page_visible: !document.hidden,
                    activity_status: activityStatus
                })
            }).catch(error => console.error('Heartbeat error:', error));
        }

        function refreshData() {
            logActivity('button_click', {action: 'refresh_data'});
            location.reload();
        }

        function triggerDraw() {
            logActivity('draw_control', {action: 'trigger_draw'});
            
            if (confirm('Are you sure you want to trigger a new draw?')) {
                fetch('api/trigger_draw.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        user_id: userId,
                        session_id: sessionId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Draw triggered successfully!');
                        refreshData();
                    } else {
                        alert('Error triggering draw: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Draw trigger error:', error);
                    alert('Error triggering draw');
                });
            }
        }

        function resetSystem() {
            logActivity('draw_control', {action: 'reset_system'});
            
            if (confirm('Are you sure you want to reset the system? This will clear current data.')) {
                fetch('api/reset_system.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        user_id: userId,
                        session_id: sessionId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('System reset successfully!');
                        refreshData();
                    } else {
                        alert('Error resetting system: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('System reset error:', error);
                    alert('Error resetting system');
                });
            }
        }

        // Log initial page load
        logActivity('page_view', {
            timestamp: new Date().toISOString(),
            referrer: document.referrer
        });
    </script>
</body>
</html>
