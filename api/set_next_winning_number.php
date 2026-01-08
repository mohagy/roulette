<?php
/**
 * Set Next Winning Number API
 * 
 * This API endpoint sets the winning number for the next draw.
 */

// Include database connection
require_once '../php/db_connect.php';

// Set headers
header('Content-Type: application/json');

// Initialize response
$response = [
    'status' => 'error',
    'message' => 'An error occurred',
    'data' => []
];

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

// Get the POST data
$drawNumber = isset($_POST['draw_number']) ? intval($_POST['draw_number']) : null;
$winningNumber = isset($_POST['winning_number']) ? intval($_POST['winning_number']) : null;
$source = isset($_POST['source']) ? $_POST['source'] : 'admin';
$reason = isset($_POST['reason']) ? $_POST['reason'] : 'Set by administrator';

// Validate the data
if ($drawNumber === null || $winningNumber === null) {
    $response['message'] = 'Missing required parameters';
    echo json_encode($response);
    exit;
}

// Validate the winning number
if ($winningNumber < 0 || $winningNumber > 36) {
    $response['message'] = 'Invalid winning number';
    echo json_encode($response);
    exit;
}

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Check if there's already a winning number for this draw
    $stmt = $pdo->prepare("
        SELECT id FROM next_draw_winning_number 
        WHERE draw_number = ?
    ");
    $stmt->execute([$drawNumber]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Update existing record
        $stmt = $pdo->prepare("
            UPDATE next_draw_winning_number 
            SET winning_number = ?, 
                source = ?, 
                reason = ?, 
                updated_at = NOW() 
            WHERE draw_number = ?
        ");
        $stmt->execute([$winningNumber, $source, $reason, $drawNumber]);
    } else {
        // Insert new record
        $stmt = $pdo->prepare("
            INSERT INTO next_draw_winning_number 
            (draw_number, winning_number, source, reason) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$drawNumber, $winningNumber, $source, $reason]);
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Set success response
    $response['status'] = 'success';
    $response['message'] = 'Next winning number set successfully';
    $response['data'] = [
        'draw_number' => $drawNumber,
        'winning_number' => $winningNumber,
        'source' => $source
    ];
    
} catch (PDOException $e) {
    // Rollback transaction
    $pdo->rollBack();
    
    // Set error response
    $response['message'] = 'Database error: ' . $e->getMessage();
}

// Return the response
echo json_encode($response);
