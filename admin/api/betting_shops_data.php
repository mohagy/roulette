<?php
// API for Betting Shops Real-time Data
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
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

    // Get statistics
    $stats = [
        'total_shops' => 0,
        'active_shops' => 0,
        'total_users' => 0,
        'total_revenue' => 0
    ];

    // Count total shops
    $result = $conn->query("SELECT COUNT(*) as count FROM betting_shops");
    if ($result && $result->num_rows > 0) {
        $stats['total_shops'] = $result->fetch_assoc()['count'];
    }

    // Count active shops
    $result = $conn->query("SELECT COUNT(*) as count FROM betting_shops WHERE status = 'active'");
    if ($result && $result->num_rows > 0) {
        $stats['active_shops'] = $result->fetch_assoc()['count'];
    }

    // Count users assigned to shops
    $result = $conn->query("SELECT COUNT(DISTINCT user_id) as count FROM shop_users WHERE status = 'active'");
    if ($result && $result->num_rows > 0) {
        $stats['total_users'] = $result->fetch_assoc()['count'];
    }

    // Get total revenue (last 30 days)
    $result = $conn->query("SELECT SUM(total_bets) as revenue FROM shop_performance WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    if ($result && $result->num_rows > 0) {
        $stats['total_revenue'] = $result->fetch_assoc()['revenue'] ?? 0;
    }

    // Get all betting shops with current data
    $shops = [];
    $sql = "SELECT 
                bs.*,
                COUNT(DISTINCT su.user_id) as user_count,
                COALESCE(sp.total_bets, 0) as today_bets,
                COALESCE(sp.total_commission, 0) as today_commission,
                COALESCE(sp.total_transactions, 0) as today_transactions
            FROM betting_shops bs
            LEFT JOIN shop_users su ON bs.shop_id = su.shop_id AND su.status = 'active'
            LEFT JOIN shop_performance sp ON bs.shop_id = sp.shop_id AND sp.date = CURDATE()
            GROUP BY bs.shop_id
            ORDER BY bs.created_at DESC";

    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $shops[] = [
                'shop_id' => $row['shop_id'],
                'shop_code' => $row['shop_code'],
                'shop_name' => $row['shop_name'],
                'city' => $row['city'],
                'address' => $row['address'],
                'manager_name' => $row['manager_name'],
                'manager_phone' => $row['manager_phone'],
                'status' => $row['status'],
                'user_count' => $row['user_count'],
                'today_bets' => $row['today_bets'],
                'today_commission' => $row['today_commission'],
                'today_transactions' => $row['today_transactions'],
                'commission_rate' => $row['commission_rate']
            ];
        }
    }

    // Get recent activities
    $activities = [];
    $sql = "SELECT 
                'shop_created' as activity_type,
                bs.shop_name as description,
                bs.created_at as activity_time
            FROM betting_shops bs
            WHERE bs.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            
            UNION ALL
            
            SELECT 
                'user_assigned' as activity_type,
                CONCAT(u.username, ' assigned to ', bs.shop_name) as description,
                su.assigned_at as activity_time
            FROM shop_users su
            JOIN users u ON su.user_id = u.user_id
            JOIN betting_shops bs ON su.shop_id = bs.shop_id
            WHERE su.assigned_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            
            ORDER BY activity_time DESC
            LIMIT 10";

    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $activities[] = $row;
        }
    }

    // Return data
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'shops' => $shops,
        'activities' => $activities,
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
