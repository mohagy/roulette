<?php
/**
 * Get Next Winning Number API
 * 
 * This API endpoint gets the winning number for the next draw.
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

// Get the draw number from the query string
$drawNumber = isset($_GET['draw_number']) ? intval($_GET['draw_number']) : null;

// If no draw number is provided, get the current draw number
if ($drawNumber === null) {
    $stmt = $pdo->prepare("
        SELECT current_draw_number FROM roulette_analytics LIMIT 1
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $drawNumber = $result['current_draw_number'];
    } else {
        $drawNumber = 1; // Default to 1 if no draw number is found
    }
}

try {
    // Get the winning number for the next draw
    $stmt = $pdo->prepare("
        SELECT draw_number, winning_number, source, reason, created_at, updated_at 
        FROM next_draw_winning_number 
        WHERE draw_number = ?
    ");
    $stmt->execute([$drawNumber]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        // Set success response
        $response['status'] = 'success';
        $response['message'] = 'Next winning number found';
        $response['data'] = [
            'draw_number' => intval($result['draw_number']),
            'winning_number' => intval($result['winning_number']),
            'source' => $result['source'],
            'reason' => $result['reason'],
            'created_at' => $result['created_at'],
            'updated_at' => $result['updated_at']
        ];
    } else {
        // No winning number found
        $response['status'] = 'success';
        $response['message'] = 'No winning number set for this draw';
        $response['data'] = [
            'draw_number' => $drawNumber,
            'winning_number' => null,
            'is_set' => false
        ];
    }
    
} catch (PDOException $e) {
    // Set error response
    $response['message'] = 'Database error: ' . $e->getMessage();
}

// Return the response
echo json_encode($response);
