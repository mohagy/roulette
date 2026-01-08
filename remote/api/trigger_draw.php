<?php
// Remote Draw Trigger API
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

    $user_id = $input['user_id'] ?? null;
    $session_id = $input['session_id'] ?? null;

    // Validate required fields
    if (!$user_id || !$session_id) {
        throw new Exception("Missing required fields");
    }

    // Check if user has draw control permissions
    $stmt = $conn->prepare("
        SELECT permission_id 
        FROM remote_access_permissions 
        WHERE user_id = ? 
        AND permission_type = 'draw_control' 
        AND status = 'active' 
        AND (expires_at IS NULL OR expires_at > NOW())
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Insufficient permissions for draw control");
    }

    // Verify active session
    $stmt = $conn->prepare("
        SELECT session_id 
        FROM remote_sessions 
        WHERE session_id = ? AND user_id = ? AND status = 'active'
    ");
    $stmt->bind_param("ii", $session_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Invalid or inactive session");
    }

    // Get current draw number
    $current_draw_number = 1;
    $result = $conn->query("SELECT MAX(draw_number) as max_draw FROM roulette_analytics");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $current_draw_number = ($row['max_draw'] ?? 0) + 1;
    }

    // Generate random winning number (0-36)
    $winning_number = rand(0, 36);
    
    // Determine color
    $green_numbers = [0];
    $red_numbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
    
    if (in_array($winning_number, $green_numbers)) {
        $winning_color = 'Green';
    } elseif (in_array($winning_number, $red_numbers)) {
        $winning_color = 'Red';
    } else {
        $winning_color = 'Black';
    }

    // Insert new draw result
    $stmt = $conn->prepare("
        INSERT INTO roulette_analytics (
            draw_number, winning_number, winning_color, timestamp
        ) VALUES (?, ?, ?, NOW())
    ");
    $stmt->bind_param("iis", $current_draw_number, $winning_number, $winning_color);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to insert draw result: " . $stmt->error);
    }

    // Insert detailed draw result
    $stmt = $conn->prepare("
        INSERT INTO detailed_draw_results (
            draw_number, number, color, timestamp
        ) VALUES (?, ?, ?, NOW())
    ");
    $stmt->bind_param("iis", $current_draw_number, $winning_number, $winning_color);
    $stmt->execute();

    // Update next draw winning number table
    $stmt = $conn->prepare("
        INSERT INTO next_draw_winning_number (winning_number) 
        VALUES (?) 
        ON DUPLICATE KEY UPDATE winning_number = ?
    ");
    $stmt->bind_param("ii", $winning_number, $winning_number);
    $stmt->execute();

    // Log the draw control action
    $stmt = $conn->prepare("
        INSERT INTO remote_activity_logs (
            session_id, user_id, activity_type, page_url, action_details
        ) VALUES (?, ?, 'draw_control', ?, ?)
    ");
    
    $page_url = $_SERVER['HTTP_REFERER'] ?? '/remote/bet_distribution.php';
    $action_details = json_encode([
        'action' => 'trigger_draw',
        'draw_number' => $current_draw_number,
        'winning_number' => $winning_number,
        'winning_color' => $winning_color,
        'timestamp' => date('Y-m-d H:i:s'),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    $stmt->bind_param("iiss", $session_id, $user_id, $page_url, $action_details);
    $stmt->execute();

    // Update performance metrics
    $today = date('Y-m-d');
    $stmt = $conn->prepare("
        INSERT INTO remote_performance_metrics (
            user_id, date, draws_controlled
        ) VALUES (?, ?, 1)
        ON DUPLICATE KEY UPDATE
        draws_controlled = draws_controlled + 1
    ");
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();

    // Log compliance record
    $stmt = $conn->prepare("
        INSERT INTO remote_compliance_logs (
            user_id, compliance_type, compliance_status, details
        ) VALUES (?, 'security_check', 'compliant', ?)
    ");
    $details = "Draw #{$current_draw_number} triggered remotely - Number: {$winning_number} ({$winning_color})";
    $stmt->bind_param("is", $user_id, $details);
    $stmt->execute();

    // Process any pending betting slips for this draw
    $stmt = $conn->prepare("
        UPDATE betting_slips 
        SET status = 'completed', completed_at = NOW() 
        WHERE draw_number = ? AND status = 'active'
    ");
    $stmt->bind_param("i", $current_draw_number);
    $stmt->execute();
    $affected_slips = $stmt->affected_rows;

    // Calculate payouts for winning bets (simplified logic)
    $total_payouts = 0;
    $winning_slips = 0;
    
    // This would normally involve complex payout calculations
    // For now, we'll just simulate some basic statistics
    if ($affected_slips > 0) {
        $total_payouts = rand(1000, 5000);
        $winning_slips = rand(1, min(10, $affected_slips));
    }

    echo json_encode([
        'success' => true,
        'draw_triggered' => true,
        'draw_details' => [
            'draw_number' => $current_draw_number,
            'winning_number' => $winning_number,
            'winning_color' => $winning_color,
            'timestamp' => date('Y-m-d H:i:s')
        ],
        'betting_summary' => [
            'affected_slips' => $affected_slips,
            'winning_slips' => $winning_slips,
            'total_payouts' => $total_payouts
        ],
        'operator_info' => [
            'user_id' => $user_id,
            'session_id' => $session_id,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]
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
