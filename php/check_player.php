<?php
// Set response header to JSON
header('Content-Type: application/json');

// Include the database connection
require_once 'db_connect.php';

// Get the player ID from the request
$playerId = isset($_GET['player_id']) ? intval($_GET['player_id']) : 0;

// If no player ID, check for default GUEST player
if ($playerId <= 0) {
    // Look for GUEST player
    $result = $conn->query("SELECT player_id FROM players WHERE username = 'GUEST' LIMIT 1");
    
    if ($result && $result->num_rows > 0) {
        $player = $result->fetch_assoc();
        echo json_encode([
            'status' => 'success',
            'message' => 'Using default GUEST player',
            'player_id' => $player['player_id']
        ]);
        exit;
    }
    
    // Try to create a default GUEST player
    $insertResult = $conn->query("INSERT INTO players (username, created_at) VALUES ('GUEST', NOW())");
    
    if ($insertResult) {
        $newPlayerId = $conn->insert_id;
        echo json_encode([
            'status' => 'success',
            'message' => 'Created new GUEST player',
            'player_id' => $newPlayerId
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to create GUEST player: ' . $conn->error
        ]);
    }
    exit;
}

// Check if the player exists
$stmt = $conn->prepare("SELECT player_id FROM players WHERE player_id = ? LIMIT 1");

if (!$stmt) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $conn->error
    ]);
    exit;
}

$stmt->bind_param('i', $playerId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Player exists
    $player = $result->fetch_assoc();
    echo json_encode([
        'status' => 'success',
        'message' => 'Player exists',
        'player_id' => $player['player_id']
    ]);
} else {
    // Player doesn't exist, check if any guest player exists
    $result = $conn->query("SELECT player_id FROM players WHERE username = 'GUEST' LIMIT 1");
    
    if ($result && $result->num_rows > 0) {
        $player = $result->fetch_assoc();
        echo json_encode([
            'status' => 'success',
            'message' => 'Using GUEST player instead',
            'player_id' => $player['player_id']
        ]);
    } else {
        // Create a guest player
        $insertResult = $conn->query("INSERT INTO players (username, created_at) VALUES ('GUEST', NOW())");
        
        if ($insertResult) {
            $newPlayerId = $conn->insert_id;
            echo json_encode([
                'status' => 'success',
                'message' => 'Created new GUEST player',
                'player_id' => $newPlayerId
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to create GUEST player: ' . $conn->error
            ]);
        }
    }
}
?> 