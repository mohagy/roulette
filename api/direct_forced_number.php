<?php
/**
 * Direct Forced Number API
 * 
 * This API endpoint directly checks for forced numbers in the next_draw_winning_number table
 * and returns them in a simple format for the wheel to use.
 */

// Include database connection
require_once '../php/db_connect.php';

// Set headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Initialize response
$response = [
    'status' => 'error',
    'message' => 'An error occurred',
    'has_forced_number' => false,
    'forced_number' => null,
    'forced_color' => null,
    'draw_number' => null
];

// Function to get the color for a number
function getNumberColor($number) {
    $number = intval($number);
    
    if ($number === 0) {
        return 'green';
    }
    
    $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
    
    if (in_array($number, $redNumbers)) {
        return 'red';
    }
    
    return 'black';
}

try {
    // Get the current draw number
    $stmt = $pdo->prepare("
        SELECT current_draw_number 
        FROM roulette_analytics 
        LIMIT 1
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        throw new Exception("Could not determine current draw number");
    }
    
    $currentDrawNumber = intval($result['current_draw_number']);
    
    // First check if there's a forced number for the current draw
    $stmt = $pdo->prepare("
        SELECT draw_number, winning_number
        FROM next_draw_winning_number
        WHERE draw_number = ?
        LIMIT 1
    ");
    $stmt->execute([$currentDrawNumber]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If not found, check for the next draw
    if (!$result) {
        $nextDrawNumber = $currentDrawNumber + 1;
        
        $stmt = $pdo->prepare("
            SELECT draw_number, winning_number
            FROM next_draw_winning_number
            WHERE draw_number = ?
            LIMIT 1
        ");
        $stmt->execute([$nextDrawNumber]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // If we found a forced number, return it
    if ($result) {
        $forcedNumber = intval($result['winning_number']);
        $drawNumber = intval($result['draw_number']);
        
        $response = [
            'status' => 'success',
            'message' => 'Forced number found',
            'has_forced_number' => true,
            'forced_number' => $forcedNumber,
            'forced_color' => getNumberColor($forcedNumber),
            'draw_number' => $drawNumber
        ];
    } else {
        // No forced number found
        $response = [
            'status' => 'success',
            'message' => 'No forced number found',
            'has_forced_number' => false,
            'forced_number' => null,
            'forced_color' => null,
            'draw_number' => $currentDrawNumber
        ];
    }
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

// Return the response
echo json_encode($response);
