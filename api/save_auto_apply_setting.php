<?php
/**
 * Save Auto-Apply Setting API
 * Stores the auto-apply forced number setting in the database
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
    // Check request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method. Use POST.');
    }
    
    // Get the auto-apply setting value
    $autoApply = isset($_POST['auto_apply']) ? (int)$_POST['auto_apply'] : 
                 (isset($_POST['enabled']) ? (int)$_POST['enabled'] : 0);
    
    // Validate (should be 0 or 1)
    if ($autoApply !== 0 && $autoApply !== 1) {
        throw new Exception('Invalid auto_apply value. Must be 0 or 1.');
    }
    
    // Save to roulette_settings table
    // First check if setting exists
    $checkStmt = $pdo->prepare("
        SELECT id FROM roulette_settings 
        WHERE setting_name = 'auto_apply_forced_number' 
        LIMIT 1
    ");
    $checkStmt->execute();
    $exists = $checkStmt->fetch(PDO::FETCH_ASSOC);
    $checkStmt->closeCursor();
    
    if ($exists) {
        // Update existing setting
        $stmt = $pdo->prepare("
            UPDATE roulette_settings 
            SET setting_value = ?,
                updated_at = NOW() 
            WHERE setting_name = 'auto_apply_forced_number'
        ");
    } else {
        // Insert new setting
        $stmt = $pdo->prepare("
            INSERT INTO roulette_settings (setting_name, setting_value, automatic_mode, updated_at) 
            VALUES ('auto_apply_forced_number', ?, 1, NOW())
        ");
    }
    
    $value = (string)$autoApply;
    $success = $stmt->execute([$value]);
    
    if (!$success) {
        throw new Exception('Failed to save auto-apply setting to database');
    }
    
    $response['status'] = 'success';
    $response['message'] = 'Auto-apply setting saved successfully';
    $response['data'] = [
        'auto_apply' => (bool)$autoApply,
        'saved_at' => date('Y-m-d H:i:s')
    ];
    
} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>

