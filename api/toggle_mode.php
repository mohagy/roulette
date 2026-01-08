<?php
// Set response header to JSON
header('Content-Type: application/json');

// Include database connection and helper functions
require_once '../includes/db_connection.php';
require_once '../includes/helper_functions.php';

// Set timezone to UTC
date_default_timezone_set('UTC');

// Default response (will be overwritten on success)
$response = [
    'status' => 'error',
    'message' => 'Failed to toggle automatic mode',
    'timestamp' => time()
];

// Function to log mode changes
function logModeChange($message, $type = 'INFO') {
    $logFile = '../logs/automatic_mode_changes.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$type] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

try {
    // Check if mode is provided
    if (!isset($_POST['mode'])) {
        throw new Exception("No mode parameter provided");
    }
    
    // Get the desired mode (0 for manual, 1 for automatic)
    $mode = $_POST['mode'] === 'automatic' ? 1 : 0;
    $modeText = $mode === 1 ? 'automatic' : 'manual';
    
    // Check if automatic_mode column exists
    $checkColumnQuery = "SHOW COLUMNS FROM roulette_settings LIKE 'automatic_mode'";
    $columnResult = $conn->query($checkColumnQuery);
    $hasAutomaticModeColumn = ($columnResult->num_rows > 0);
    
    if ($hasAutomaticModeColumn) {
        // Using direct column approach
        $stmt = $conn->prepare("
            UPDATE roulette_settings 
            SET automatic_mode = ?,
                updated_at = NOW() 
            WHERE id = 1
        ");
        $stmt->bind_param("i", $mode);
    } else {
        // Using setting_name/setting_value approach
        $modeStr = (string)$mode;
        $stmt = $conn->prepare("
            UPDATE roulette_settings 
            SET setting_value = ?,
                updated_at = NOW() 
            WHERE setting_name = 'automatic_mode'
        ");
        $stmt->bind_param("s", $modeStr);
    }
    
    $success = $stmt->execute();
    $stmt->close();
    
    if (!$success) {
        throw new Exception("Failed to update automatic mode: " . $conn->error);
    }
    
    // If switching to manual mode, clear any existing winning number
    if ($mode === 0) {
        // Get current draw number
        $stmt = $conn->prepare("
            SELECT current_draw_number
            FROM roulette_analytics
            WHERE id = 1
            LIMIT 1
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $drawData = $result->fetch_assoc();
            $currentDrawNumber = $drawData['current_draw_number'];
            $stmt->close();
            
            // Delete any preset winning number
            $stmt = $conn->prepare("
                DELETE FROM next_draw_winning_number 
                WHERE draw_number = ?
            ");
            $stmt->bind_param("i", $currentDrawNumber);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    // Log the mode change
    logModeChange("Draw mode changed to $modeText by user", 'INFO');
    
    // Prepare success response
    $response = [
        'status' => 'success',
        'message' => "Mode has been set to $modeText",
        'data' => [
            'mode' => $modeText,
            'automatic' => ($mode === 1)
        ],
        'timestamp' => time()
    ];
    
} catch (Exception $e) {
    logModeChange("Error changing mode: " . $e->getMessage(), 'ERROR');
    
    $response = [
        'status' => 'error',
        'message' => "Error: " . $e->getMessage(),
        'timestamp' => time()
    ];
}

// Output the response
echo json_encode($response, JSON_PRETTY_PRINT);
?> 