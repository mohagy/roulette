<?php
// Include database connection
require_once 'db_connect.php';

// Set response header to JSON
header('Content-Type: application/json');

// Get the HTTP method and action from the request
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Default response
$response = array(
    'status' => 'error',
    'message' => 'Invalid request'
);

// Process based on request method and action
if ($method === 'GET') {
    if ($action === 'get_player') {
        $player_id = isset($_GET['player_id']) ? intval($_GET['player_id']) : 1; // Default to player 1
        
        $stmt = $conn->prepare("SELECT player_id, username, cash_balance FROM players WHERE player_id = ?");
        $stmt->bind_param("i", $player_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $player = $result->fetch_assoc();
            $response = array(
                'status' => 'success',
                'player' => $player
            );
        } else {
            $response = array(
                'status' => 'error',
                'message' => 'Player not found'
            );
        }
        
        $stmt->close();
    }
} elseif ($method === 'POST') {
    // Decode the JSON request body
    $json_data = json_decode(file_get_contents('php://input'), true);
    
    if ($action === 'update_balance') {
        if (isset($json_data['player_id']) && isset($json_data['amount'])) {
            $player_id = intval($json_data['player_id']);
            $amount = floatval($json_data['amount']);
            
            // Update the player's balance
            $stmt = $conn->prepare("UPDATE players SET cash_balance = cash_balance + ? WHERE player_id = ?");
            $stmt->bind_param("di", $amount, $player_id);
            
            if ($stmt->execute()) {
                // Get the updated balance
                $stmt2 = $conn->prepare("SELECT cash_balance FROM players WHERE player_id = ?");
                $stmt2->bind_param("i", $player_id);
                $stmt2->execute();
                $result = $stmt2->get_result();
                $player = $result->fetch_assoc();
                
                $response = array(
                    'status' => 'success',
                    'message' => 'Balance updated successfully',
                    'new_balance' => $player['cash_balance']
                );
                
                $stmt2->close();
            } else {
                $response = array(
                    'status' => 'error',
                    'message' => 'Failed to update balance'
                );
            }
            
            $stmt->close();
        } else {
            $response = array(
                'status' => 'error',
                'message' => 'Missing player_id or amount'
            );
        }
    }
}

// Output the response as JSON
echo json_encode($response);

// Close the database connection
$conn->close();
?> 