<?php
/**
 * API endpoint to get bet counts for upcoming draws
 * 
 * This script returns the number of betting slips placed for each upcoming draw.
 */

// Include database connection
require_once 'db_connect.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Initialize response array
$response = array(
    'status' => 'error',
    'message' => 'An error occurred while fetching bet counts',
    'data' => array()
);

try {
    // Get draw numbers from request
    $data = json_decode(file_get_contents('php://input'), true);
    
    // If no draw numbers provided, get them from query parameters
    if (!isset($data['draw_numbers']) || empty($data['draw_numbers'])) {
        if (isset($_GET['draw_numbers'])) {
            $drawNumbers = explode(',', $_GET['draw_numbers']);
        } else {
            // Get the current draw number
            $query = "SELECT current_draw_number FROM roulette_analytics WHERE id = 1";
            $result = $conn->query($query);
            
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $currentDrawNumber = $row['current_draw_number'];
                
                // Generate upcoming draw numbers (10 draws)
                $drawNumbers = array();
                for ($i = 0; $i < 10; $i++) {
                    $drawNumbers[] = $currentDrawNumber + $i;
                }
            } else {
                throw new Exception("Could not determine current draw number");
            }
        }
    } else {
        $drawNumbers = $data['draw_numbers'];
    }
    
    // Validate draw numbers
    foreach ($drawNumbers as $key => $drawNumber) {
        $drawNumbers[$key] = intval($drawNumber);
        if ($drawNumbers[$key] <= 0) {
            throw new Exception("Invalid draw number: " . $drawNumber);
        }
    }
    
    // Prepare placeholders for the IN clause
    $placeholders = str_repeat('?,', count($drawNumbers) - 1) . '?';
    
    // Query to get bet counts for each draw
    $query = "
        SELECT draw_number, COUNT(*) as bet_count, SUM(total_stake) as total_stake
        FROM betting_slips
        WHERE draw_number IN ($placeholders)
        GROUP BY draw_number
    ";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Error preparing statement: " . $conn->error);
    }
    
    // Bind parameters dynamically
    $types = str_repeat('i', count($drawNumbers));
    $stmt->bind_param($types, ...$drawNumbers);
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Initialize counts array with zeros for all draw numbers
    $betCounts = array();
    foreach ($drawNumbers as $drawNumber) {
        $betCounts[$drawNumber] = array(
            'draw_number' => $drawNumber,
            'bet_count' => 0,
            'total_stake' => 0
        );
    }
    
    // Fill in actual counts from database
    while ($row = $result->fetch_assoc()) {
        $betCounts[$row['draw_number']] = array(
            'draw_number' => intval($row['draw_number']),
            'bet_count' => intval($row['bet_count']),
            'total_stake' => floatval($row['total_stake'])
        );
    }
    
    // Convert to indexed array for response
    $betCountsArray = array_values($betCounts);
    
    // Update response
    $response['status'] = 'success';
    $response['message'] = 'Bet counts retrieved successfully';
    $response['data'] = $betCountsArray;
    
} catch (Exception $e) {
    // Log the error for server-side debugging
    error_log("Error in get_draw_bet_counts.php: " . $e->getMessage());
    
    // Update the error response
    $response['message'] = "Error fetching bet counts: " . $e->getMessage();
}

// Send JSON response
echo json_encode($response);
