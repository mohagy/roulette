<?php
// Stock Dashboard API
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
        'total_inventory_items' => 0,
        'low_stock_alerts' => 0,
        'pending_orders' => 0,
        'open_equipment_issues' => 0,
        'active_vendors' => 0,
        'pending_deliveries' => 0
    ];

    // Total inventory items
    $result = $conn->query("SELECT COUNT(*) as count FROM inventory_items");
    if ($result && $result->num_rows > 0) {
        $stats['total_inventory_items'] = $result->fetch_assoc()['count'];
    }

    // Low stock alerts
    $result = $conn->query("
        SELECT COUNT(*) as count 
        FROM shop_inventory si 
        JOIN inventory_items ii ON si.item_id = ii.item_id 
        WHERE si.current_stock <= ii.reorder_level
    ");
    if ($result && $result->num_rows > 0) {
        $stats['low_stock_alerts'] = $result->fetch_assoc()['count'];
    }

    // Pending purchase orders
    $result = $conn->query("SELECT COUNT(*) as count FROM purchase_orders WHERE status IN ('draft', 'sent', 'confirmed')");
    if ($result && $result->num_rows > 0) {
        $stats['pending_orders'] = $result->fetch_assoc()['count'];
    }

    // Open equipment issues
    $result = $conn->query("SELECT COUNT(*) as count FROM equipment_issues WHERE status IN ('open', 'assigned', 'in_progress')");
    if ($result && $result->num_rows > 0) {
        $stats['open_equipment_issues'] = $result->fetch_assoc()['count'];
    }

    // Active vendors
    $result = $conn->query("SELECT COUNT(*) as count FROM vendors WHERE status = 'active'");
    if ($result && $result->num_rows > 0) {
        $stats['active_vendors'] = $result->fetch_assoc()['count'];
    }

    // Pending deliveries
    $result = $conn->query("SELECT COUNT(*) as count FROM purchase_orders WHERE status = 'shipped'");
    if ($result && $result->num_rows > 0) {
        $stats['pending_deliveries'] = $result->fetch_assoc()['count'];
    }

    // Get recent stock movements
    $recent_movements = [];
    $result = $conn->query("
        SELECT 
            sm.movement_type,
            sm.quantity,
            sm.created_at,
            ii.item_name,
            bs.shop_name,
            u.username as created_by
        FROM stock_movements sm
        JOIN inventory_items ii ON sm.item_id = ii.item_id
        JOIN betting_shops bs ON sm.shop_id = bs.shop_id
        LEFT JOIN users u ON sm.created_by = u.user_id
        ORDER BY sm.created_at DESC
        LIMIT 15
    ");

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $recent_movements[] = $row;
        }
    }

    // Get low stock items
    $low_stock_items = [];
    $result = $conn->query("
        SELECT 
            ii.item_name,
            ii.item_code,
            bs.shop_name,
            si.current_stock,
            ii.reorder_level,
            ii.unit_cost
        FROM shop_inventory si 
        JOIN inventory_items ii ON si.item_id = ii.item_id 
        JOIN betting_shops bs ON si.shop_id = bs.shop_id
        WHERE si.current_stock <= ii.reorder_level
        ORDER BY (si.current_stock / ii.reorder_level) ASC
        LIMIT 15
    ");

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $low_stock_items[] = $row;
        }
    }

    // Get pending purchase orders
    $pending_orders = [];
    $result = $conn->query("
        SELECT 
            po.po_number,
            po.order_date,
            po.expected_delivery,
            po.total_amount,
            po.status,
            v.vendor_name,
            bs.shop_name
        FROM purchase_orders po
        JOIN vendors v ON po.vendor_id = v.vendor_id
        LEFT JOIN betting_shops bs ON po.shop_id = bs.shop_id
        WHERE po.status IN ('draft', 'sent', 'confirmed', 'shipped')
        ORDER BY po.order_date DESC
        LIMIT 10
    ");

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $pending_orders[] = $row;
        }
    }

    // Get equipment issues
    $equipment_issues = [];
    $result = $conn->query("
        SELECT 
            ei.issue_type,
            ei.severity,
            ei.description,
            ei.status,
            ei.reported_at,
            e.equipment_name,
            bs.shop_name
        FROM equipment_issues ei
        JOIN equipment e ON ei.equipment_id = e.equipment_id
        LEFT JOIN betting_shops bs ON e.shop_id = bs.shop_id
        WHERE ei.status IN ('open', 'assigned', 'in_progress')
        ORDER BY 
            CASE ei.severity 
                WHEN 'critical' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                WHEN 'low' THEN 4 
            END,
            ei.reported_at DESC
        LIMIT 10
    ");

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $equipment_issues[] = $row;
        }
    }

    // Get vendor performance
    $vendor_performance = [];
    $result = $conn->query("
        SELECT 
            v.vendor_name,
            v.vendor_code,
            v.rating,
            COUNT(po.po_id) as total_orders,
            AVG(DATEDIFF(po.actual_delivery, po.expected_delivery)) as avg_delay_days,
            SUM(po.total_amount) as total_value
        FROM vendors v
        LEFT JOIN purchase_orders po ON v.vendor_id = po.vendor_id 
            AND po.status = 'delivered' 
            AND po.order_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        WHERE v.status = 'active'
        GROUP BY v.vendor_id
        ORDER BY v.rating DESC, total_value DESC
        LIMIT 10
    ");

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $vendor_performance[] = $row;
        }
    }

    // Return data
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'recent_movements' => $recent_movements,
        'low_stock_items' => $low_stock_items,
        'pending_orders' => $pending_orders,
        'equipment_issues' => $equipment_issues,
        'vendor_performance' => $vendor_performance,
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
