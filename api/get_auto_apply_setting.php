<?php
/**
 * Get Auto-Apply Setting API
 * Retrieves the auto-apply forced number setting from the database
 */

header('Content-Type: application/json');
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once '../php/db_connect.php';

$response = [
    'status' => 'error',
    'message' => 'An error occurred',
    'data' => []
];

try {
    // Get the auto-apply setting from database
    $stmt = $pdo->prepare("
        SELECT setting_value, updated_at 
        FROM roulette_settings 
        WHERE setting_name = 'auto_apply_forced_number' 
        LIMIT 1
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    
    if ($result) {
        $autoApply = (int)$result['setting_value'] === 1;
        $response['status'] = 'success';
        $response['message'] = 'Auto-apply setting retrieved successfully';
        $response['data'] = [
            'auto_apply' => $autoApply,
            'last_updated' => $result['updated_at']
        ];
    } else {
        // Setting doesn't exist, return default (false/manual mode)
        $response['status'] = 'success';
        $response['message'] = 'Auto-apply setting not found, using default (manual mode)';
        $response['data'] = [
            'auto_apply' => false,
            'last_updated' => null
        ];
    }
    
} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>

