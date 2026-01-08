<?php
// Remote Activity Logging API
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
    $activity_type = $input['activity_type'] ?? null;
    $page_url = $input['page_url'] ?? null;
    $action_details = $input['action_details'] ?? [];
    $screen_resolution = $input['screen_resolution'] ?? null;

    // Validate required fields
    if (!$session_id || !$user_id || !$activity_type) {
        throw new Exception("Missing required fields");
    }

    // Verify session belongs to user
    $stmt = $conn->prepare("SELECT session_id FROM remote_sessions WHERE session_id = ? AND user_id = ? AND status = 'active'");
    $stmt->bind_param("ii", $session_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Invalid session");
    }

    // Prepare browser info
    $browser_info = [
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'screen_resolution' => $screen_resolution,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    // Insert activity log
    $stmt = $conn->prepare("
        INSERT INTO remote_activity_logs (
            session_id, user_id, activity_type, page_url, action_details, 
            screen_resolution, browser_info
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $action_details_json = json_encode($action_details);
    $browser_info_json = json_encode($browser_info);
    
    $stmt->bind_param("iisssss", 
        $session_id, $user_id, $activity_type, $page_url, 
        $action_details_json, $screen_resolution, $browser_info_json
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to log activity: " . $stmt->error);
    }

    // Update session last activity
    $stmt = $conn->prepare("UPDATE remote_sessions SET last_activity = NOW() WHERE session_id = ?");
    $stmt->bind_param("i", $session_id);
    $stmt->execute();

    // Handle specific activity types
    switch ($activity_type) {
        case 'idle_start':
            // Update session status to idle
            $stmt = $conn->prepare("UPDATE remote_sessions SET status = 'idle' WHERE session_id = ?");
            $stmt->bind_param("i", $session_id);
            $stmt->execute();
            
            // Check if idle time exceeds threshold for alert
            $idle_threshold = 600; // 10 minutes
            if (isset($action_details['duration']) && $action_details['duration'] > $idle_threshold) {
                // Create alert for extended idle time
                $stmt = $conn->prepare("
                    INSERT INTO remote_alerts (
                        user_id, alert_type, severity, title, message
                    ) VALUES (?, 'extended_idle', 'medium', 'Extended Idle Period', ?)
                ");
                $message = "Employee has been idle for " . round($action_details['duration'] / 60, 1) . " minutes";
                $stmt->bind_param("is", $user_id, $message);
                $stmt->execute();
            }
            break;
            
        case 'idle_end':
            // Update session status back to active
            $stmt = $conn->prepare("UPDATE remote_sessions SET status = 'active' WHERE session_id = ?");
            $stmt->bind_param("i", $session_id);
            $stmt->execute();
            break;
            
        case 'draw_control':
            // Log draw control action for compliance
            $stmt = $conn->prepare("
                INSERT INTO remote_compliance_logs (
                    user_id, compliance_type, compliance_status, details
                ) VALUES (?, 'security_check', 'compliant', ?)
            ");
            $details = "Draw control action: " . ($action_details['action'] ?? 'unknown');
            $stmt->bind_param("is", $user_id, $details);
            $stmt->execute();
            break;
            
        case 'window_blur':
        case 'tab_switch':
            // Track potential distraction
            $stmt = $conn->prepare("
                UPDATE remote_screen_monitoring 
                SET tab_switches = tab_switches + 1 
                WHERE session_id = ? AND screen_active_end IS NULL
            ");
            $stmt->bind_param("i", $session_id);
            $stmt->execute();
            break;
    }

    echo json_encode([
        'success' => true,
        'activity_logged' => true,
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
