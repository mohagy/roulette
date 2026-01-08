<?php
// This script ensures that a guest player exists in the database

// Include database connection from db_connect.php which is working
require_once 'db_connect.php';

// Use the existing connection $conn from db_connect.php
try {
    // We already have $conn from db_connect.php
    echo "Connected to database successfully.\n";
    
    // Check if the players table exists
    $tableCheckResult = $conn->query("SHOW TABLES LIKE 'players'");
    if ($tableCheckResult->num_rows == 0) {
        // Create the players table
        $createTableSQL = "CREATE TABLE players (
            player_id INT NOT NULL AUTO_INCREMENT,
            username VARCHAR(100) NOT NULL,
            balance DECIMAL(10,2) DEFAULT 1000.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (player_id)
        )";
        
        if ($conn->query($createTableSQL)) {
            echo "Created players table successfully.\n";
        } else {
            die("Error creating players table: " . $conn->error);
        }
    } else {
        echo "Players table already exists.\n";
    }
    
    // First check the structure of the players table to debug
    $result = $conn->query("DESCRIBE players");
    if (!$result) {
        die("Error describing players table: " . $conn->error);
    }
    
    echo "\nPlayers table structure:\n";
    echo "------------------------\n";
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " | " . $row['Type'] . " | " . $row['Null'] . " | " . $row['Key'] . " | " . $row['Default'] . " | " . $row['Extra'] . "\n";
    }
    
    // Check if the guest player exists - using a direct query instead of prepare
    $result = $conn->query("SELECT * FROM players WHERE username = 'GUEST' LIMIT 1");
    if (!$result) {
        die("Error querying for GUEST player: " . $conn->error);
    }
    
    if ($result->num_rows > 0) {
        $player = $result->fetch_assoc();
        echo "Guest player already exists with ID: " . $player['player_id'] . "\n";
    } else {
        // Create a guest player - first try with ID 1
        $insertResult = $conn->query("INSERT INTO players (username, balance, created_at) VALUES ('GUEST', 1000, NOW())");
        
        if ($insertResult) {
            $newPlayerId = $conn->insert_id;
            echo "Created guest player successfully with ID: $newPlayerId\n";
        } else {
            echo "Error creating guest player: " . $conn->error . "\n";
        }
    }
    
    // List all players
    $result = $conn->query("SELECT * FROM players");
    if (!$result) {
        die("Error listing players: " . $conn->error);
    }
    
    echo "\nExisting players:\n";
    echo "----------------\n";
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['player_id'] . " | Username: " . $row['username'] . " | Balance: " . $row['balance'] . "\n";
    }
    
    // We don't close $conn since it might be used elsewhere
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

echo "\nScript completed.\n";
?> 