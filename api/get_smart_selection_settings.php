<?php
/**
 * Get Smart Selection Settings
 * Retrieves the saved time preset and pattern type preferences
 */

header('Content-Type: application/json');
require_once '../includes/db_connection.php';

$response = [
    'status' => 'success',
    'data' => [
        'time_preset' => 'auto',
        'pattern_type' => 'smart'
    ],
    'timestamp' => time()
];

try {
    $stmt = $conn->prepare("
        SELECT setting_name, setting_value 
        FROM roulette_settings 
        WHERE setting_name IN ('smart_time_preset', 'smart_pattern_type')
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        if ($row['setting_name'] === 'smart_time_preset') {
            $response['data']['time_preset'] = $row['setting_value'];
        } elseif ($row['setting_name'] === 'smart_pattern_type') {
            $response['data']['pattern_type'] = $row['setting_value'];
        }
    }
    $stmt->close();
    
} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
    error_log("Get Smart Selection Settings Error: " . $e->getMessage());
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>


