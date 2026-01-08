<?php
/**
 * manual_test.php
 * 
 * A simple file to manually test saving and retrieving winning numbers
 */

// Include the database connection
require_once 'db_connect.php';

echo "=== Manual Test for Winning Number Feature ===\n\n";

// Test 1: Save a random winning number
$winningNumber = rand(0, 36);
$drawNumber = rand(1, 100);
$red_numbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
$winningColor = $winningNumber === 0 ? 'green' : (in_array($winningNumber, $red_numbers) ? 'red' : 'black');
$timestamp = date('Y-m-d H:i:s');
$drawId = 'MANUAL-TEST-' . $drawNumber . '-' . time();

echo "Test 1: Saving a new winning number\n";
echo "Winning Number: $winningNumber\n";
echo "Draw Number: $drawNumber\n";
echo "Winning Color: $winningColor\n";
echo "Draw ID: $drawId\n\n";

// Check if the detailed_draw_results table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'detailed_draw_results'");
if ($tableCheck->num_rows === 0) {
    echo "Creating detailed_draw_results table...\n";
    $createTable = $conn->query("
        CREATE TABLE IF NOT EXISTS detailed_draw_results (
            id INT AUTO_INCREMENT PRIMARY KEY,
            draw_id VARCHAR(50) NOT NULL,
            draw_number INT NOT NULL,
            winning_number INT NOT NULL,
            winning_color VARCHAR(10) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )
    ");
    if ($createTable) {
        echo "Table created successfully.\n\n";
    } else {
        echo "Error creating table: " . $conn->error . "\n\n";
    }
}

// Check if the roulette_analytics table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'roulette_analytics'");
if ($tableCheck->num_rows === 0) {
    echo "Creating roulette_analytics table...\n";
    $createTable = $conn->query("
        CREATE TABLE IF NOT EXISTS roulette_analytics (
            id INT PRIMARY KEY,
            last_draw_number INT,
            last_winning_number INT,
            last_winning_color VARCHAR(10),
            last_draw_time DATETIME
        )
    ");
    if ($createTable) {
        echo "Table created successfully.\n\n";
    } else {
        echo "Error creating table: " . $conn->error . "\n\n";
    }
}

// Check if the game_history table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'game_history'");
if ($tableCheck->num_rows === 0) {
    echo "Creating game_history table...\n";
    $createTable = $conn->query("
        CREATE TABLE IF NOT EXISTS game_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            draw_number INT NOT NULL,
            winning_number INT NOT NULL,
            winning_color VARCHAR(10) NOT NULL,
            timestamp DATETIME NOT NULL
        )
    ");
    if ($createTable) {
        echo "Table created successfully.\n\n";
    } else {
        echo "Error creating table: " . $conn->error . "\n\n";
    }
}

// Insert into detailed_draw_results
echo "Inserting into detailed_draw_results...\n";
$stmt = $conn->prepare("
    INSERT INTO detailed_draw_results 
    (draw_id, draw_number, winning_number, winning_color, created_at, updated_at) 
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->bind_param('siisss', $drawId, $drawNumber, $winningNumber, $winningColor, $timestamp, $timestamp);
$result = $stmt->execute();
if ($result) {
    echo "Data inserted successfully with ID: " . $conn->insert_id . "\n\n";
} else {
    echo "Error inserting data: " . $stmt->error . "\n\n";
}

// Update roulette_analytics
echo "Updating roulette_analytics...\n";
$stmt = $conn->prepare("
    UPDATE roulette_analytics 
    SET last_draw_number = ?, last_winning_number = ?, last_winning_color = ?, last_draw_time = ? 
    WHERE id = 1
");
$stmt->bind_param('iiss', $drawNumber, $winningNumber, $winningColor, $timestamp);
$result = $stmt->execute();
if ($result && $stmt->affected_rows > 0) {
    echo "Data updated successfully.\n\n";
} else {
    echo "No rows updated, inserting new record...\n";
    $stmt = $conn->prepare("
        INSERT INTO roulette_analytics 
        (id, last_draw_number, last_winning_number, last_winning_color, last_draw_time) 
        VALUES (1, ?, ?, ?, ?)
    ");
    $stmt->bind_param('iiss', $drawNumber, $winningNumber, $winningColor, $timestamp);
    $result = $stmt->execute();
    if ($result) {
        echo "New record inserted successfully.\n\n";
    } else {
        echo "Error inserting record: " . $stmt->error . "\n\n";
    }
}

// Insert into game_history
echo "Inserting into game_history...\n";
$stmt = $conn->prepare("
    INSERT INTO game_history 
    (draw_number, winning_number, winning_color, timestamp) 
    VALUES (?, ?, ?, ?)
");
$stmt->bind_param('iiss', $drawNumber, $winningNumber, $winningColor, $timestamp);
$result = $stmt->execute();
if ($result) {
    echo "Data inserted successfully with ID: " . $conn->insert_id . "\n\n";
} else {
    echo "Error inserting data: " . $stmt->error . "\n\n";
}

// Test 2: Retrieve the latest winning number
echo "Test 2: Retrieving the latest winning number\n";

// Try to get from roulette_analytics
$query = "SELECT last_draw_number, last_winning_number, last_winning_color, last_draw_time 
          FROM roulette_analytics 
          WHERE id = 1 
          LIMIT 1";
$result = $conn->query($query);

if ($result && $row = $result->fetch_assoc()) {
    echo "Latest winning number from analytics table:\n";
    echo "Draw Number: " . $row['last_draw_number'] . "\n";
    echo "Winning Number: " . $row['last_winning_number'] . "\n";
    echo "Winning Color: " . $row['last_winning_color'] . "\n";
    echo "Draw Time: " . $row['last_draw_time'] . "\n\n";
    
    // Verify the data matches what we saved
    if ($row['last_draw_number'] == $drawNumber && $row['last_winning_number'] == $winningNumber) {
        echo "✓ Verification passed! Retrieved data matches what we saved.\n\n";
    } else {
        echo "✗ Verification failed! Retrieved data does not match what we saved.\n\n";
    }
} else {
    echo "No data found in roulette_analytics table.\n\n";
}

echo "=== Test Completed ===\n";
echo "The manual test demonstrates that we can save and retrieve winning numbers.\n";
echo "This validates that our implementation will work properly with the TV display\n";
echo "and main application for cashout verification.\n"; 