<?php
// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include the database connection
require_once 'db_connect.php';

echo "Connecting to database...\n";

// Check if the connection was successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected successfully.\n";

// First, check if there are any records in the players table
$result = $conn->query("SELECT COUNT(*) as count FROM players");
$row = $result->fetch_assoc();
$playerCount = $row['count'];

echo "Current player count: " . $playerCount . "\n";

// Insert a guest player if the table is empty or has no GUEST player
if ($playerCount == 0) {
    echo "No players found. Creating guest player...\n";
    
    // Insert a guest player with ID 1
    $sql = "INSERT INTO players (player_id, username, created_at) VALUES (1, 'GUEST', NOW())";
    
    if ($conn->query($sql) === TRUE) {
        echo "Guest player created successfully with ID 1.\n";
    } else {
        echo "Error creating guest player: " . $conn->error . "\n";
    }
} else {
    // Check if there's a GUEST player
    $result = $conn->query("SELECT * FROM players WHERE username = 'GUEST'");
    
    if ($result->num_rows == 0) {
        echo "No GUEST player found. Creating guest player...\n";
        
        // Insert a guest player (let AUTO_INCREMENT handle the ID)
        $sql = "INSERT INTO players (username, created_at) VALUES ('GUEST', NOW())";
        
        if ($conn->query($sql) === TRUE) {
            $guestId = $conn->insert_id;
            echo "Guest player created successfully with ID " . $guestId . ".\n";
        } else {
            echo "Error creating guest player: " . $conn->error . "\n";
        }
    } else {
        $guestPlayer = $result->fetch_assoc();
        echo "Guest player already exists with ID " . $guestPlayer['player_id'] . ".\n";
    }
}

// List all players for verification
$result = $conn->query("SELECT * FROM players");
echo "\nCurrent players in database:\n";
echo "------------------------------\n";
echo "ID\tUsername\tCreated At\n";
echo "------------------------------\n";

while ($row = $result->fetch_assoc()) {
    echo $row['player_id'] . "\t" . $row['username'] . "\t\t" . $row['created_at'] . "\n";
}

echo "------------------------------\n";

$conn->close();
echo "Database connection closed.\n";
?> 