<?php
// Set response header to JSON
header('Content-Type: application/json');

// Include database connection and helper functions
require_once '../includes/db_connection.php';
require_once '../includes/helper_functions.php';

// Default response
$response = [
    'status' => 'error',
    'message' => 'Failed to update mode',
    'timestamp' => time()
];

try {
    // Check if the request contains the mode parameter
    if (!isset($_POST['automatic'])) {
        throw new Exception("Missing automatic parameter");
    }
    
    // Get the mode value (0 for manual, 1 for automatic)
    $automatic = (int)$_POST['automatic'];
    if ($automatic !== 0 && $automatic !== 1) {
        throw new Exception("Automatic parameter must be 0 (manual) or 1 (automatic)");
    }
    
    // Update the automatic mode in the roulette_settings table
    $stmt = $conn->prepare("
        UPDATE roulette_settings 
        SET automatic_mode = ?, 
            updated_at = NOW() 
        WHERE id = 1
    ");
    $stmt->bind_param("i", $automatic);
    $stmt->execute();
    
    if ($stmt->affected_rows === 0) {
        // If no rows were updated, check if the table has no records
        $stmt->close();
        
        $checkStmt = $conn->prepare("SELECT COUNT(*) AS count FROM roulette_settings");
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        $row = $result->fetch_assoc();
        $checkStmt->close();
        
        if ($row['count'] === 0) {
            // Insert a new record if none exists
            $insertStmt = $conn->prepare("
                INSERT INTO roulette_settings (id, automatic_mode, updated_at) 
                VALUES (1, ?, NOW())
            ");
            $insertStmt->bind_param("i", $automatic);
            $insertStmt->execute();
            $insertStmt->close();
        } else {
            throw new Exception("Failed to update mode setting");
        }
    } else {
        $stmt->close();
    }
    
    // Log the mode setting update
    $modeText = $automatic ? "automatic" : "manual";
    logMessage("Winning number mode updated to {$modeText}", 'INFO');
    
    // Prepare success response
    $response = [
        'status' => 'success',
        'message' => 'Mode updated successfully',
        'automatic' => (bool)$automatic,
        'mode_text' => $modeText,
        'timestamp' => time()
    ];
    
} catch (Exception $e) {
    // Log the error
    logMessage('Error in set_mode.php: ' . $e->getMessage(), 'ERROR');
    
    $response = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'timestamp' => time()
    ];
}

// Output the JSON response
echo json_encode($response); 