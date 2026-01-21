<?php
/**
 * Save Smart Selection Settings
 * Stores the time preset and pattern type preferences for auto mode
 */

header('Content-Type: application/json');
require_once '../includes/db_connection.php';

$response = [
    'status' => 'error',
    'message' => 'An error occurred',
    'timestamp' => time()
];

try {
    $timePreset = isset($_POST['time_preset']) ? $_POST['time_preset'] : 'auto';
    $patternType = isset($_POST['pattern_type']) ? $_POST['pattern_type'] : 'smart';
    
    // Validate inputs
    $validPresets = ['auto', 'morning', 'afternoon', 'evening', 'night', 'custom'];
    $validPatterns = ['smart', 'fibonacci', 'color_alternate', 'cold_numbers', 'lowest_payout'];
    
    if (!in_array($timePreset, $validPresets)) {
        throw new Exception('Invalid time preset');
    }
    
    if (!in_array($patternType, $validPatterns)) {
        throw new Exception('Invalid pattern type');
    }
    
    // Store in roulette_settings table
    // First, check if settings exist
    $stmt = $conn->prepare("
        SELECT id FROM roulette_settings 
        WHERE setting_name = 'smart_time_preset' OR setting_name = 'smart_pattern_type'
        LIMIT 1
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    // Insert or update time preset
    $stmt = $conn->prepare("
        INSERT INTO roulette_settings (setting_name, setting_value, automatic_mode, updated_at)
        VALUES ('smart_time_preset', ?, 1, NOW())
        ON DUPLICATE KEY UPDATE 
            setting_value = VALUES(setting_value),
            updated_at = NOW()
    ");
    $stmt->bind_param("s", $timePreset);
    $stmt->execute();
    $stmt->close();
    
    // Insert or update pattern type
    $stmt = $conn->prepare("
        INSERT INTO roulette_settings (setting_name, setting_value, automatic_mode, updated_at)
        VALUES ('smart_pattern_type', ?, 1, NOW())
        ON DUPLICATE KEY UPDATE 
            setting_value = VALUES(setting_value),
            updated_at = NOW()
    ");
    $stmt->bind_param("s", $patternType);
    $stmt->execute();
    $stmt->close();
    
    $response = [
        'status' => 'success',
        'message' => 'Smart selection settings saved',
        'data' => [
            'time_preset' => $timePreset,
            'pattern_type' => $patternType
        ],
        'timestamp' => time()
    ];
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log("Save Smart Selection Settings Error: " . $e->getMessage());
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>


