<?php
// Suppress display of errors, but still log them
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set response header to JSON
header('Content-Type: application/json');

// Include database connection
require_once 'db_connect.php';

// Create logs directory if it doesn't exist
$logDir = __DIR__ . '/../logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}

/**
 * Log message to file
 */
function log_message($message) {
    global $logDir;
    $logFile = $logDir . '/system.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

// Check if database connection is successful
if (!isset($conn) || $conn->connect_error) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed'
    ]);
    exit;
}

try {
    // Verify the request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    // Check if the enabled parameter is set
    if (!isset($_POST['enabled'])) {
        throw new Exception('Missing required parameter: enabled');
    }
    
    $enabled = ($_POST['enabled'] === '1') ? 1 : 0;
    
    // Make sure we have the roulette_settings table
    $createTableSql = "
    CREATE TABLE IF NOT EXISTS roulette_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_name VARCHAR(50) NOT NULL,
        setting_value VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );";
    
    if (!$conn->query($createTableSql)) {
        throw new Exception('Failed to create settings table');
    }
    
    // Update or insert the automatic_mode setting
    $stmt = $conn->prepare("
        INSERT INTO roulette_settings (setting_name, setting_value) 
        VALUES ('automatic_mode', ?)
    ");
    $stmt->bind_param("s", $enabledStr);
    $enabledStr = (string)$enabled;
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update automatic mode setting');
    }
    
    log_message("Automatic mode " . ($enabled ? "enabled" : "disabled"));
    
    // Return success response
    echo json_encode([
        'status' => 'success',
        'message' => 'Automatic mode ' . ($enabled ? 'enabled' : 'disabled'),
        'auto_mode_enabled' => (bool)$enabled
    ]);
    
} catch (Exception $e) {
    log_message("Error: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 