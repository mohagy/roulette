<?php
/**
 * Check Current Mode
 * Diagnostic endpoint to verify the automatic mode setting
 */

header('Content-Type: application/json');
require_once '../includes/db_connection.php';

$response = [
    'status' => 'error',
    'message' => 'An error occurred',
    'data' => [],
    'timestamp' => time()
];

try {
    // Check if automatic_mode column exists
    $checkColumnQuery = "SHOW COLUMNS FROM roulette_settings LIKE 'automatic_mode'";
    $columnResult = $conn->query($checkColumnQuery);
    $hasAutomaticModeColumn = ($columnResult->num_rows > 0);
    
    $isAutomatic = null;
    $modeValue = null;
    $tableStructure = [];
    
    // Get table structure info
    $structureQuery = "SHOW COLUMNS FROM roulette_settings";
    $structureResult = $conn->query($structureQuery);
    while ($row = $structureResult->fetch_assoc()) {
        $tableStructure[] = $row;
    }
    
    if ($hasAutomaticModeColumn) {
        // Using direct column approach
        $stmt = $conn->prepare("
            SELECT id, automatic_mode, updated_at
            FROM roulette_settings 
            WHERE id = 1
            LIMIT 1
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $modeValue = $row['automatic_mode'];
            $isAutomatic = (int)$modeValue === 1;
        } else {
            $isAutomatic = null; // No record found
        }
        $stmt->close();
    } else {
        // Using setting_name/setting_value approach
        $stmt = $conn->prepare("
            SELECT id, setting_name, setting_value, updated_at
            FROM roulette_settings 
            WHERE setting_name = 'automatic_mode'
            LIMIT 1
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $modeValue = $row['setting_value'];
            $isAutomatic = (int)$modeValue === 1;
        } else {
            $isAutomatic = null; // No record found
        }
        $stmt->close();
    }
    
    // Get all roulette_settings records for debugging
    $allSettingsQuery = "SELECT * FROM roulette_settings LIMIT 10";
    $allSettingsResult = $conn->query($allSettingsQuery);
    $allSettings = [];
    while ($row = $allSettingsResult->fetch_assoc()) {
        $allSettings[] = $row;
    }
    
    $response = [
        'status' => 'success',
        'message' => 'Mode check completed',
        'data' => [
            'has_automatic_mode_column' => $hasAutomaticModeColumn,
            'is_automatic' => $isAutomatic,
            'mode_value' => $modeValue,
            'mode_text' => $isAutomatic === null ? 'not_set' : ($isAutomatic ? 'automatic' : 'manual'),
            'table_structure' => $tableStructure,
            'all_settings' => $allSettings
        ],
        'timestamp' => time()
    ];
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log("Check Mode Error: " . $e->getMessage());
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>


