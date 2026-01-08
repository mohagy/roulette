<?php
/**
 * Test script for winning number saving and retrieval functionality
 * This script tests both saving a winning number and retrieving it
 */

// Include the database connection
require_once 'db_connect.php';

echo "=== Testing Winning Number Functionality ===\n\n";

// Test database connection
echo "Checking database connection...\n";
if ($conn) {
    echo "✓ Database connection successful.\n\n";
} else {
    echo "✗ Database connection failed!\n\n";
    exit(1);
}

// Function to simulate a POST request to our API
function simulateApiPost($endpoint, $data) {
    echo "Simulating POST to $endpoint...\n";
    
    // Make sure the PHP file exists
    if (!file_exists($endpoint)) {
        echo "Error: File $endpoint does not exist.\n";
        return ['status' => 'error', 'message' => "File $endpoint not found"];
    }
    
    // Set up fake input stream
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['CONTENT_TYPE'] = 'application/json';
    
    // Use output buffering to capture response and prevent headers being sent
    ob_start();
    
    // Include the file via require with a closure to isolate its scope
    $json_data = json_encode($data);
    $input = fopen('php://temp', 'r+');
    fputs($input, $json_data);
    rewind($input);
    
    // Temporarily mock the input stream
    $GLOBALS['_MOCK_INPUT'] = $json_data;
    
    // Override file_get_contents to return our mock data
    function file_get_contents($path) {
        if ($path === 'php://input') {
            return $GLOBALS['_MOCK_INPUT'];
        }
        return \file_get_contents($path);
    }
    
    // Include the file (it will use our mocked input stream)
    include($endpoint);
    
    // Get the output and clean buffer
    $output = ob_get_clean();
    
    // Clean up mock
    unset($GLOBALS['_MOCK_INPUT']);
    
    // Parse the JSON response
    $response = json_decode($output, true);
    if ($response) {
        echo "Response: " . ($response['status'] ?? 'unknown') . " - " . ($response['message'] ?? 'No message') . "\n\n";
    } else {
        echo "Response: Failed to parse JSON response.\n";
        echo "Raw response: " . substr($output, 0, 200) . (strlen($output) > 200 ? '...' : '') . "\n\n";
    }
    
    return $response ?: ['status' => 'error', 'message' => 'Failed to parse response'];
}

// Function to simulate a GET request to our API
function simulateApiGet($endpoint, $params = []) {
    echo "Simulating GET to $endpoint...\n";
    
    // Make sure the PHP file exists
    if (!file_exists($endpoint)) {
        echo "Error: File $endpoint does not exist.\n";
        return ['status' => 'error', 'message' => "File $endpoint not found"];
    }
    
    // Set up environment
    $_SERVER['REQUEST_METHOD'] = 'GET';
    
    // Save original GET params
    $original_get = $_GET;
    
    // Set up new GET params
    $_GET = $params;
    
    // Use output buffering to capture response and prevent headers being sent
    ob_start();
    
    // Include the file
    include($endpoint);
    
    // Get the output and clean buffer
    $output = ob_get_clean();
    
    // Restore original GET params
    $_GET = $original_get;
    
    // Parse the JSON response
    $response = json_decode($output, true);
    if ($response) {
        echo "Response: " . ($response['status'] ?? 'unknown') . " - " . ($response['message'] ?? 'No message') . "\n\n";
    } else {
        echo "Response: Failed to parse JSON response.\n";
        echo "Raw response: " . substr($output, 0, 200) . (strlen($output) > 200 ? '...' : '') . "\n\n";
    }
    
    return $response ?: ['status' => 'error', 'message' => 'Failed to parse response'];
}

// Force create tables if they don't exist for mysqli connection
try {
    // Create detailed_draw_results table if it doesn't exist
    $conn->query("
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
    
    // Create roulette_analytics table if it doesn't exist
    $conn->query("
        CREATE TABLE IF NOT EXISTS roulette_analytics (
            id INT PRIMARY KEY,
            last_draw_number INT,
            last_winning_number INT,
            last_winning_color VARCHAR(10),
            last_draw_time DATETIME
        )
    ");
    
    // Create game_history table if it doesn't exist
    $conn->query("
        CREATE TABLE IF NOT EXISTS game_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            draw_number INT NOT NULL,
            winning_number INT NOT NULL,
            winning_color VARCHAR(10) NOT NULL,
            timestamp DATETIME NOT NULL
        )
    ");
    
    echo "✓ Verified required tables exist in the database.\n\n";
} catch (Exception $e) {
    echo "✗ Error creating tables: " . $e->getMessage() . "\n\n";
}

// Test 1: Save a winning number
echo "Test 1: Saving a winning number\n";
$testWinningNumber = rand(0, 36); // Random winning number
$testDrawNumber = rand(1, 100); // Random draw number
$testColor = $testWinningNumber === 0 ? 'green' : (in_array($testWinningNumber, [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36]) ? 'red' : 'black');

echo "Using winning number: $testWinningNumber ($testColor), draw number: $testDrawNumber\n";

// Save the winning number
$saveResponse = simulateApiPost('php/save_winning_number.php', [
    'winning_number' => $testWinningNumber,
    'draw_number' => $testDrawNumber,
    'winning_color' => $testColor,
    'draw_id' => "TEST-$testDrawNumber-" . time()
]);

// Test 2: Retrieve the latest winning number
echo "Test 2: Retrieving the latest winning number\n";
$retrieveResponse = simulateApiGet('php/get_latest_winning_number.php');

// Verify the retrieved number matches what we saved
if (isset($retrieveResponse['data']['winning_number']) && $retrieveResponse['data']['winning_number'] == $testWinningNumber &&
    isset($retrieveResponse['data']['draw_number']) && $retrieveResponse['data']['draw_number'] == $testDrawNumber) {
    echo "✓ Verification passed! Retrieved data matches what we saved.\n";
} else {
    echo "✗ Verification failed! Retrieved data does not match what we saved.\n";
    echo "Expected: winning_number=$testWinningNumber, draw_number=$testDrawNumber\n";
    echo "Received: winning_number=" . ($retrieveResponse['data']['winning_number'] ?? 'N/A') . 
         ", draw_number=" . ($retrieveResponse['data']['draw_number'] ?? 'N/A') . "\n";
}

echo "\n=== Test Completed ===\n";
echo "You can now verify that the cashout functionality works correctly in the main application.\n";
echo "When a winning number appears on the TV display, it will be saved to the database\n";
echo "and immediately available to the cashout system in the main application.\n"; 