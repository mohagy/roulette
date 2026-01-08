<?php
// Suppress display of errors, but still log them
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set response header to JSON
header('Content-Type: application/json');

// Include database connection
@require_once 'db_connect.php';

// Check if database connection is successful
if (!isset($conn) || $conn->connect_error) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed'
    ]);
    exit;
}

try {
    // Get the current draw number
    $stmt = $conn->prepare("SELECT current_draw_number FROM roulette_analytics WHERE id = 1");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result->num_rows) {
        throw new Exception("Could not find current draw number");
    }
    
    $row = $result->fetch_assoc();
    $currentDrawNumber = $row['current_draw_number'];
    $nextDrawNumber = $currentDrawNumber + 1;
    
    // Check if automatic mode is enabled
    $autoModeEnabled = true; // Default value
    
    // Check if settings table exists
    $stmt = $conn->prepare("SHOW TABLES LIKE 'roulette_settings'");
    $stmt->execute();
    if ($stmt->get_result()->num_rows) {
        // Table exists, check for automatic_mode setting
        $stmt = $conn->prepare("SELECT setting_value FROM roulette_settings WHERE setting_name = 'automatic_mode'");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows) {
            $row = $result->fetch_assoc();
            $autoModeEnabled = $row['setting_value'] === '1';
        } else {
            // Setting doesn't exist, create it
            $stmt = $conn->prepare("INSERT INTO roulette_settings (setting_name, setting_value) VALUES ('automatic_mode', '1')");
            $stmt->execute();
        }
    } else {
        // Table doesn't exist, create it and insert default setting
        $conn->query("
            CREATE TABLE roulette_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_name VARCHAR(50) NOT NULL,
                setting_value TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        
        $stmt = $conn->prepare("INSERT INTO roulette_settings (setting_name, setting_value) VALUES ('automatic_mode', '1')");
        $stmt->execute();
    }
    
    // Check if next_draw_winning_number table exists
    $hasManualWinningNumber = false;
    $winningNumber = null;
    $winningColor = null;
    
    $stmt = $conn->prepare("SHOW TABLES LIKE 'next_draw_winning_number'");
    $stmt->execute();
    if ($stmt->get_result()->num_rows) {
        // Table exists, check for winning number
        $stmt = $conn->prepare("SELECT winning_number FROM next_draw_winning_number WHERE draw_number = ?");
        $stmt->bind_param("i", $nextDrawNumber);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows) {
            $row = $result->fetch_assoc();
            $winningNumber = (int)$row['winning_number'];
            $hasManualWinningNumber = true;
            $winningColor = getNumberColor($winningNumber);
        }
    } else {
        // Table doesn't exist, create it
        $conn->query("
            CREATE TABLE next_draw_winning_number (
                id INT AUTO_INCREMENT PRIMARY KEY,
                draw_number INT NOT NULL,
                winning_number INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_draw (draw_number)
            )
        ");
    }
    
    // Return the next draw information
    echo json_encode([
        'status' => 'success',
        'draw_number' => $nextDrawNumber,
        'auto_mode_enabled' => $autoModeEnabled,
        'has_manual_winning_number' => $hasManualWinningNumber,
        'winning_number' => $winningNumber,
        'winning_color' => $winningColor
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

/**
 * Get the color of a number
 */
function getNumberColor($number) {
    if ($number === 0) {
        return 'green';
    }
    
    $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
    
    return in_array($number, $redNumbers) ? 'red' : 'black';
} 