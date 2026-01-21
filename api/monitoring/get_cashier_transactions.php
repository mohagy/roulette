<?php
/**
 * Get Cashier Recent Transactions for Monitoring
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
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $limit = isset($_GET['limit']) ? min(intval($_GET['limit']), 100) : 20;
    
    if ($user_id > 0) {
        // Get transactions for specific cashier
        $stmt = $pdo->prepare("
            SELECT 
                t.transaction_id,
                t.user_id,
                u.username,
                t.amount,
                t.balance_after,
                t.transaction_type,
                t.reference_id,
                t.description,
                t.created_at
            FROM transactions t
            JOIN users u ON t.user_id = u.user_id
            WHERE t.user_id = ?
            ORDER BY t.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$user_id, $limit]);
    } else {
        // Get all cashier transactions
        $stmt = $pdo->prepare("
            SELECT 
                t.transaction_id,
                t.user_id,
                u.username,
                t.amount,
                t.balance_after,
                t.transaction_type,
                t.reference_id,
                t.description,
                t.created_at
            FROM transactions t
            JOIN users u ON t.user_id = u.user_id
            WHERE u.role = 'cashier'
            ORDER BY t.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
    }
    
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format transactions
    foreach ($transactions as &$tx) {
        $tx['amount'] = floatval($tx['amount']);
        $tx['balance_after'] = floatval($tx['balance_after']);
    }
    
    $response['data'] = $transactions;
    
} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
}

echo json_encode($response);

