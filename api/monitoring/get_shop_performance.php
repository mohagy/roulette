<?php
/**
 * Get Shop Performance for Monitoring Dashboard
 * Reads directly from your MySQL database
 */

require_once '../../php/db_connect.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Access-Control-Allow-Origin: *');

$response = [
    'status' => 'success',
    'data' => []
];

try {
    // Get shop performance from existing database
    $stmt = $pdo->query("
        SELECT 
            bs.shop_id,
            bs.shop_name,
            bs.shop_code,
            COALESCE(sp.total_bets, 0) as today_bets,
            COALESCE(sp.total_wins, 0) as today_payouts,
            COALESCE(sp.total_commission, 0) as today_commission
        FROM betting_shops bs
        LEFT JOIN shop_performance sp ON bs.shop_id = sp.shop_id AND sp.date = CURDATE()
        WHERE bs.status = 'active'
        ORDER BY today_bets DESC
    ");
    
    $shops = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count alerts if monitoring_alerts table exists
    foreach ($shops as &$shop) {
        try {
            $alertStmt = $pdo->prepare("SELECT COUNT(*) as count FROM monitoring_alerts WHERE related_shop_id = ? AND status = 'new'");
            $alertStmt->execute([$shop['shop_id']]);
            $alertCount = $alertStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        } catch (Exception $e) {
            $alertCount = 0;
        }
        
        $shop['active_alerts'] = intval($alertCount);
        $shop['today_bets'] = floatval($shop['today_bets']);
        $shop['today_payouts'] = floatval($shop['today_payouts']);
    }
    
    $response['data'] = $shops;
    
} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
