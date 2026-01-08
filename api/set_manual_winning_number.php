<?php
// Set response header to JSON
header('Content-Type: application/json');

// Include database connection and helper functions
require_once '../includes/db_connection.php';
require_once '../includes/helper_functions.php';

// Default response
$response = [
    'status' => 'error',
    'message' => 'Failed to set manual winning number',
    'timestamp' => time()
];

try {
    // Check if the request contains the number parameter
    if (!isset($_POST['number'])) {
        throw new Exception("Missing number parameter");
    }
    
    // Get the number value and validate it
    $number = (int)$_POST['number'];
    if (!isValidRouletteNumber($number)) {
        throw new Exception("Invalid roulette number. Must be between 0 and 36.");
    }
    
    // Get current draw number
    $drawNumber = getCurrentDrawNumber($conn);
    if ($drawNumber === false) {
        throw new Exception("Failed to retrieve current draw number");
    }
    
    // Get the reason parameter or default to "Manual selection"
    $reason = isset($_POST['reason']) ? $_POST['reason'] : "Manual selection";
    
    // Set the winning number
    $success = setWinningNumber($conn, $drawNumber, $number, 'manual', $reason);
    if (!$success) {
        throw new Exception("Failed to set manual winning number");
    }
    
    // Update the mode to manual if it's not already
    $stmt = $conn->prepare("
        UPDATE roulette_settings 
        SET automatic_mode = 0, 
            updated_at = NOW() 
        WHERE id = 1
    ");
    $stmt->execute();
    
    if ($stmt->affected_rows === 0) {
        // If no rows were updated, check if the table has no records
        $checkStmt = $conn->prepare("SELECT COUNT(*) AS count FROM roulette_settings");
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] === 0) {
            // Insert a new record if none exists
            $insertStmt = $conn->prepare("
                INSERT INTO roulette_settings (id, automatic_mode, updated_at) 
                VALUES (1, 0, NOW())
            ");
            $insertStmt->execute();
        }
    }
    
    // Get the color of the number
    $color = getNumberColor($number);
    
    // Log the manual winning number setting
    logMessage("Manual winning number set to {$number} ({$color}) for draw #{$drawNumber}", 'INFO');
    
    // Prepare success response
    $response = [
        'status' => 'success',
        'message' => 'Manual winning number set successfully',
        'number' => $number,
        'color' => $color,
        'draw_number' => $drawNumber,
        'reason' => $reason,
        'timestamp' => time()
    ];
    
} catch (Exception $e) {
    // Log the error
    logMessage('Error in set_manual_winning_number.php: ' . $e->getMessage(), 'ERROR');
    
    $response = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'timestamp' => time()
    ];
}

// Output the JSON response
echo json_encode($response); 