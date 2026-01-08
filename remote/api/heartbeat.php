<?php
// Remote Heartbeat Monitoring API
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "roulette";

date_default_timezone_set('America/Guyana');

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception("Invalid JSON input");
    }

    $session_id = $input['session_id'] ?? null;
    $user_id = $input['user_id'] ?? null;
    $mouse_activity = $input['mouse_activity'] ?? false;
    $page_visible = $input['page_visible'] ?? true;
    $activity_status = $input['activity_status'] ?? 'active';

    // Validate required fields
    if (!$session_id || !$user_id) {
        throw new Exception("Missing required fields");
    }

    // Verify session belongs to user
    $stmt = $conn->prepare("SELECT session_id FROM remote_sessions WHERE session_id = ? AND user_id = ? AND status != 'ended'");
    $stmt->bind_param("ii", $session_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Invalid session");
    }

    // Insert heartbeat record
    $stmt = $conn->prepare("
        INSERT INTO remote_heartbeat (
            session_id, user_id, mouse_activity, page_visible, 
            cpu_usage, memory_usage, network_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    // Simulate system metrics (in production, these would come from client-side monitoring)
    $cpu_usage = rand(10, 80) + (rand(0, 99) / 100);
    $memory_usage = rand(30, 90) + (rand(0, 99) / 100);
    $network_status = 'online';
    
    $stmt->bind_param("iiiidds", 
        $session_id, $user_id, $mouse_activity, $page_visible,
        $cpu_usage, $memory_usage, $network_status
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to record heartbeat: " . $stmt->error);
    }

    // Update session last activity and status
    $stmt = $conn->prepare("UPDATE remote_sessions SET last_activity = NOW(), status = ? WHERE session_id = ?");
    $stmt->bind_param("si", $activity_status, $session_id);
    $stmt->execute();

    // Calculate session duration and activity metrics
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
        $session_duration = time() - strtotime($session_data['login_time']);
        
        // Update session duration
        $stmt = $conn->prepare("UPDATE remote_sessions SET session_duration = ? WHERE session_id = ?");
        $stmt->bind_param("ii", $session_duration, $session_id);
        $stmt->execute();
    }

    // Check for alerts based on activity patterns
    $alerts = [];
    
    // Check for extended idle periods
    if ($activity_status === 'idle') {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as idle_count 
            FROM remote_heartbeat 
            WHERE session_id = ? 
            AND heartbeat_time >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
            AND mouse_activity = 0
        ");
        $stmt->bind_param("i", $session_id);
        $stmt->execute();
        $idle_result = $stmt->get_result()->fetch_assoc();
        
        if ($idle_result['idle_count'] > 20) { // More than 20 heartbeats (10+ minutes) without activity
            // Check if alert already exists
            $stmt = $conn->prepare("
                SELECT alert_id 
                FROM remote_alerts 
                WHERE user_id = ? 
                AND alert_type = 'extended_idle' 
                AND status = 'active'
                AND triggered_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows === 0) {
                // Create new alert
                $stmt = $conn->prepare("
                    INSERT INTO remote_alerts (
                        user_id, alert_type, severity, title, message
                    ) VALUES (?, 'extended_idle', 'medium', 'Extended Idle Period', 'Employee has been idle for more than 10 minutes')
                ");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                
                $alerts[] = [
                    'type' => 'extended_idle',
                    'message' => 'Extended idle period detected'
                ];
            }
        }
    }

    // Check for unusual activity patterns
    if (!$page_visible) {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as invisible_count 
            FROM remote_heartbeat 
            WHERE session_id = ? 
            AND heartbeat_time >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            AND page_visible = 0
        ");
        $stmt->bind_param("i", $session_id);
        $stmt->execute();
        $invisible_result = $stmt->get_result()->fetch_assoc();
        
        if ($invisible_result['invisible_count'] > 8) { // Page not visible for more than 4 minutes
            $alerts[] = [
                'type' => 'unusual_activity',
                'message' => 'Page has been hidden for extended period'
            ];
        }
    }

    // Update performance metrics for today
    $today = date('Y-m-d');
    $stmt = $conn->prepare("
        INSERT INTO remote_performance_metrics (
            user_id, date, total_work_hours, active_work_hours, idle_hours
        ) VALUES (?, ?, 0, 0, 0)
        ON DUPLICATE KEY UPDATE
        total_work_hours = ?,
        active_work_hours = total_work_hours * 0.8,
        idle_hours = total_work_hours * 0.2
    ");
    
    $work_hours = $session_duration / 3600; // Convert to hours
    $stmt->bind_param("isdd", $user_id, $today, $work_hours, $work_hours);
    $stmt->execute();

    // Clean up old heartbeat records (keep only last 24 hours)
    $stmt = $conn->prepare("
        DELETE FROM remote_heartbeat 
        WHERE heartbeat_time < DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'heartbeat_recorded' => true,
        'session_duration' => $session_duration ?? 0,
        'activity_status' => $activity_status,
        'alerts' => $alerts,
        'system_metrics' => [
            'cpu_usage' => $cpu_usage,
            'memory_usage' => $memory_usage,
            'network_status' => $network_status
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
