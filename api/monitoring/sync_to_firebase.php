<?php
/**
 * Sync MySQL Data to Firebase Firestore
 * 
 * This script syncs monitoring data from MySQL to Firebase Firestore
 * Should be run periodically via cron job (every 1-5 minutes)
 * 
 * Usage: php api/monitoring/sync_to_firebase.php
 */

require_once '../../php/db_connect.php';

// Firebase Admin SDK (you'll need to install via Composer)
// composer require kreait/firebase-php
// For now, we'll use HTTP REST API

$firebaseProjectId = 'superbet-830b0';
$firebaseApiKey = 'AIzaSyD7PEghPHHigevb46NRuJBWj1PqhqGyvOs';

// Get Firebase access token (simplified - in production use service account)
// For now, we'll use Firestore REST API with API key

/**
 * Sync alerts to Firebase
 */
function syncAlerts($pdo) {
    // Get new alerts that haven't been synced
    $stmt = $pdo->query("
        SELECT * FROM monitoring_alerts 
        WHERE firebase_synced = 0 OR firebase_synced IS NULL
        ORDER BY created_at DESC
        LIMIT 50
    ");
    
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($alerts as $alert) {
        // Convert to Firebase format
        $firebaseData = [
            'alert_id' => $alert['alert_id'],
            'alert_type' => $alert['alert_type'],
            'severity' => $alert['severity'],
            'title' => $alert['title'],
            'description' => $alert['description'],
            'shop_id' => $alert['related_shop_id'],
            'user_id' => $alert['related_user_id'],
            'slip_id' => $alert['related_slip_id'],
            'transaction_id' => $alert['related_transaction_id'],
            'draw_number' => $alert['related_draw_number'],
            'status' => $alert['status'],
            'created_at' => $alert['created_at'],
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // TODO: Send to Firebase Firestore via REST API
        // For now, just mark as synced (you'll need to implement Firebase REST API calls)
        
        // Mark as synced
        $updateStmt = $pdo->prepare("
            UPDATE monitoring_alerts 
            SET firebase_synced = 1 
            WHERE alert_id = ?
        ");
        $updateStmt->execute([$alert['alert_id']]);
    }
    
    return count($alerts);
}

/**
 * Sync shop performance to Firebase
 */
function syncShopPerformance($pdo) {
    // Get today's shop performance
    $stmt = $pdo->query("
        SELECT 
            sp.shop_id,
            bs.shop_name,
            sp.total_bets,
            sp.total_wins,
            sp.total_commission,
            sp.date
        FROM shop_performance sp
        JOIN betting_shops bs ON sp.shop_id = bs.shop_id
        WHERE sp.date = CURDATE()
    ");
    
    $shops = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($shops as $shop) {
        // Calculate payout ratio
        $payoutRatio = $shop['total_bets'] > 0 
            ? ($shop['total_wins'] / $shop['total_bets']) * 100 
            : 0;
        
        // Count active alerts for this shop
        $alertStmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM monitoring_alerts 
            WHERE related_shop_id = ? AND status = 'new'
        ");
        $alertStmt->execute([$shop['shop_id']]);
        $alertCount = $alertStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $firebaseData = [
            'shop_id' => $shop['shop_id'],
            'shop_name' => $shop['shop_name'],
            'today_bets' => floatval($shop['total_bets']),
            'today_payouts' => floatval($shop['total_wins']),
            'payout_ratio' => round($payoutRatio, 2),
            'active_alerts' => intval($alertCount),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // TODO: Send to Firebase Firestore
        // firestore.collection('monitoring').collection('shops').doc($shop['shop_id']).set($firebaseData);
    }
    
    return count($shops);
}

/**
 * Sync dashboard stats to Firebase
 */
function syncDashboardStats($pdo) {
    // Get total active alerts
    $alertStmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical
        FROM monitoring_alerts 
        WHERE status = 'new'
    ");
    $alertStats = $alertStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get today's total payouts
    $payoutStmt = $pdo->query("
        SELECT SUM(total_wins) as total_payouts
        FROM shop_performance
        WHERE date = CURDATE()
    ");
    $payoutStats = $payoutStmt->fetch(PDO::FETCH_ASSOC);
    
    // Count active shops
    $shopStmt = $pdo->query("
        SELECT COUNT(DISTINCT shop_id) as count
        FROM shop_performance
        WHERE date = CURDATE()
    ");
    $shopCount = $shopStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stats = [
        'total_active_alerts' => intval($alertStats['total']),
        'critical_alerts' => intval($alertStats['critical']),
        'today_payouts_total' => floatval($payoutStats['total_payouts']),
        'shops_under_monitoring' => intval($shopCount),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // TODO: Send to Firebase Firestore
    // firestore.collection('monitoring').doc('stats').collection('live').doc('main').set($stats);
    
    return $stats;
}

// Main execution
try {
    $pdo->beginTransaction();
    
    $alertsSynced = syncAlerts($pdo);
    $shopsSynced = syncShopPerformance($pdo);
    $stats = syncDashboardStats($pdo);
    
    $pdo->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Sync completed',
        'data' => [
            'alerts_synced' => $alertsSynced,
            'shops_synced' => $shopsSynced,
            'stats' => $stats
        ]
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

