<?php
// IT Dashboard API
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

    // Get ticket priority distribution
    $ticket_priorities = [];
    $result = $conn->query("
        SELECT priority, COUNT(*) as count 
        FROM it_tickets 
        WHERE status NOT IN ('resolved', 'closed')
        GROUP BY priority
    ");

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $ticket_priorities[] = $row;
        }
    }

    // Get maintenance alerts
    $maintenance_alerts = [];
    $result = $conn->query("
        SELECT e.equipment_name, e.equipment_type, bs.shop_name, e.next_maintenance
        FROM equipment e
        JOIN betting_shops bs ON e.shop_id = bs.shop_id
        WHERE e.next_maintenance <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND e.status = 'active'
        ORDER BY e.next_maintenance ASC
        LIMIT 10
    ");

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $maintenance_alerts[] = $row;
        }
    }

    // Return data
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'recent_tickets' => $recent_tickets,
        'equipment_status' => $equipment_status,
        'ticket_priorities' => $ticket_priorities,
        'maintenance_alerts' => $maintenance_alerts,
        'timestamp' => time()
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
