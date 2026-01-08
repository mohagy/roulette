<?php
/**
 * Get Slip ID from Slip Number
 *
 * This file retrieves the slip ID for a given slip number.
 */

// Set error reporting to show all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set response header to JSON
header('Content-Type: application/json');

// Include database connection
require_once 'db_connect.php';

// Get the slip number from the request
$slip_number = isset($_POST['slip_number']) ? $_POST['slip_number'] : '';

if (empty($slip_number)) {
    echo json_encode([
        'success' => false,
        'message' => 'Slip number is required'
    ]);
    exit;
}

try {
    // Get the slip ID from the slip number
    $stmt = $conn->prepare("
        SELECT slip_id FROM betting_slips WHERE slip_number = ?
    ");
    
    if (!$stmt) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }
    
    $stmt->bind_param('s', $slip_number);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Slip not found'
        ]);
        exit;
    }
    
    $slip = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'message' => 'Slip found',
        'slip_id' => $slip['slip_id']
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error getting slip ID: ' . $e->getMessage()
    ]);
}
?>
