<?php
// Sales Dashboard API
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
        'total_shops' => 0,
        'active_campaigns' => 0,
        'pending_requests' => 0,
        'low_inventory_items' => 0,
        'total_revenue_today' => 0,
        'equipment_maintenance_due' => 0
    ];

    // Total active shops
    $result = $conn->query("SELECT COUNT(*) as count FROM betting_shops WHERE status = 'active'");
    if ($result && $result->num_rows > 0) {
        $stats['total_shops'] = $result->fetch_assoc()['count'];
    }

    // Active campaigns
    $result = $conn->query("SELECT COUNT(*) as count FROM marketing_campaigns WHERE status = 'active'");
    if ($result && $result->num_rows > 0) {
        $stats['active_campaigns'] = $result->fetch_assoc()['count'];
    }

    // Pending service requests
    $result = $conn->query("SELECT COUNT(*) as count FROM service_requests WHERE status IN ('pending', 'assigned')");
    if ($result && $result->num_rows > 0) {
        $stats['pending_requests'] = $result->fetch_assoc()['count'];
    }

    // Low inventory items
    $result = $conn->query("
        SELECT COUNT(*) as count 
        FROM shop_inventory si 
        JOIN inventory_items ii ON si.item_id = ii.item_id 
        WHERE si.current_stock <= ii.reorder_level
    ");
    if ($result && $result->num_rows > 0) {
        $stats['low_inventory_items'] = $result->fetch_assoc()['count'];
    }

    // Today's revenue
    $result = $conn->query("
        SELECT SUM(total_bets) as revenue 
        FROM shop_performance 
        WHERE date = CURDATE()
    ");
    if ($result && $result->num_rows > 0) {
        $stats['total_revenue_today'] = $result->fetch_assoc()['revenue'] ?? 0;
    }

    // Equipment maintenance due
    $result = $conn->query("
        SELECT COUNT(*) as count 
        FROM equipment 
        WHERE next_maintenance <= CURDATE() AND status = 'active'
    ");
    if ($result && $result->num_rows > 0) {
        $stats['equipment_maintenance_due'] = $result->fetch_assoc()['count'];
    }

    // Get recent activities
    $activities = [];
    $sql = "
        (SELECT 'campaign' as type, campaign_name as description, created_at as activity_time 
         FROM marketing_campaigns 
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))
        UNION ALL
        (SELECT 'service' as type, CONCAT('Service request: ', LEFT(description, 50)) as description, requested_date as activity_time 
         FROM service_requests 
         WHERE requested_date >= DATE_SUB(NOW(), INTERVAL 7 DAY))
        ORDER BY activity_time DESC 
        LIMIT 10
    ";

    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $activities[] = $row;
        }
    }

    // Get shop performance data
    $shop_performance = [];
    $result = $conn->query("
        SELECT bs.shop_name, bs.shop_code, COALESCE(sp.total_bets, 0) as today_bets,
               COALESCE(sp.total_commission, 0) as today_commission,
               COALESCE(sp.total_transactions, 0) as today_transactions
        FROM betting_shops bs 
        LEFT JOIN shop_performance sp ON bs.shop_id = sp.shop_id AND sp.date = CURDATE()
        WHERE bs.status = 'active'
        ORDER BY today_bets DESC
        LIMIT 10
    ");

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $shop_performance[] = $row;
        }
    }

    // Get inventory alerts
    $inventory_alerts = [];
    $result = $conn->query("
        SELECT ii.item_name, ii.item_code, bs.shop_name, si.current_stock, ii.reorder_level
        FROM shop_inventory si 
        JOIN inventory_items ii ON si.item_id = ii.item_id 
        JOIN betting_shops bs ON si.shop_id = bs.shop_id
        WHERE si.current_stock <= ii.reorder_level
        ORDER BY (si.current_stock / ii.reorder_level) ASC
        LIMIT 10
    ");

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $inventory_alerts[] = $row;
        }
    }

    // Get equipment maintenance alerts
    $maintenance_alerts = [];
    $result = $conn->query("
        SELECT e.equipment_name, e.equipment_type, bs.shop_name, e.next_maintenance, e.status
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
        'activities' => $activities,
        'shop_performance' => $shop_performance,
        'inventory_alerts' => $inventory_alerts,
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
