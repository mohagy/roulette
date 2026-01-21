<?php
/**
 * Get Dashboard Statistics for Monitoring
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
    // Get alerts count (if table exists)
    $totalAlerts = 0;
    $criticalAlerts = 0;
    try {
        $alertStmt = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical FROM monitoring_alerts WHERE status = 'new'");
        $alertStats = $alertStmt->fetch(PDO::FETCH_ASSOC);
        $totalAlerts = intval($alertStats['total'] ?? 0);
        $criticalAlerts = intval($alertStats['critical'] ?? 0);
    } catch (Exception $e) {}
    
    // Get today's total payouts
    $payoutStmt = $pdo->query("SELECT SUM(total_wins) as total_payouts FROM shop_performance WHERE date = CURDATE()");
    $payoutStats = $payoutStmt->fetch(PDO::FETCH_ASSOC);
    $todayPayouts = floatval($payoutStats['total_payouts'] ?? 0);
    
    // Count active shops with activity today
    $shopStmt = $pdo->query("SELECT COUNT(DISTINCT shop_id) as count FROM shop_performance WHERE date = CURDATE() AND total_bets > 0");
    $shopCount = $shopStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // If none, count all active shops
    if ($shopCount == 0) {
        $shopStmt2 = $pdo->query("SELECT COUNT(*) as count FROM betting_shops WHERE status = 'active'");
        $shopCount = $shopStmt2->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    }
    
    $response['data'] = [
        'total_active_alerts' => $totalAlerts,
        'critical_alerts' => $criticalAlerts,
        'today_payouts_total' => $todayPayouts,
        'shops_under_monitoring' => intval($shopCount)
    ];
    
} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
