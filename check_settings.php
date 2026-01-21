<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Roulette Settings Debug</h1>";

// Include the database connection file
require_once 'includes/db_connection.php';

echo "<h2>Database Connection: Success</h2>";

// Check roulette_settings table
echo "<h2>Checking Roulette Settings Table</h2>";

// Check if automatic_mode column exists
$checkColumnQuery = "SHOW COLUMNS FROM roulette_settings LIKE 'automatic_mode'";
$columnResult = $conn->query($checkColumnQuery);
$hasAutomaticModeColumn = ($columnResult->num_rows > 0);

echo "Has direct automatic_mode column: " . ($hasAutomaticModeColumn ? "Yes" : "No") . "<br>";

if ($hasAutomaticModeColumn) {
    // Use direct column approach
    $query = "SELECT id, automatic_mode, updated_at FROM roulette_settings WHERE id = 1";
    $result = $conn->query($query);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo "ID: {$row['id']}, Automatic Mode: {$row['automatic_mode']}, Updated: {$row['updated_at']}<br>";
    } else {
        echo "No records found in roulette_settings<br>";
    }
} else {
    // Using setting_name/setting_value approach
    $query = "SELECT id, setting_name, setting_value, updated_at FROM roulette_settings WHERE setting_name = 'automatic_mode'";
    $result = $conn->query($query);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo "ID: {$row['id']}, Setting Name: {$row['setting_name']}, Value: {$row['setting_value']}, Updated: {$row['updated_at']}<br>";
    } else {
        echo "No automatic_mode setting found in roulette_settings<br>";
    }
}

// Get current draw number
echo "<h2>Checking Current Draw Number</h2>";
$query = "SELECT current_draw_number FROM roulette_analytics WHERE id = 1";
$result = $conn->query($query);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $currentDrawNumber = $row['current_draw_number'];
    echo "Current Draw Number: {$currentDrawNumber}<br>";
} else {
    echo "No records found in roulette_analytics<br>";
    $currentDrawNumber = 0;
}

// Check next_draw_winning_number table
echo "<h2>Checking Winning Number for Current Draw</h2>";
$query = "SELECT * FROM next_draw_winning_number WHERE draw_number = {$currentDrawNumber}";
$result = $conn->query($query);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "Draw Number: {$row['draw_number']}, Winning Number: {$row['winning_number']}<br>";
    echo "Source: {$row['source']}, Reason: {$row['reason']}<br>";
    echo "Created At: {$row['created_at']}, Updated At: {$row['updated_at']}<br>";
} else {
    echo "No winning number set for current draw ({$currentDrawNumber})<br>";
}

// No need to close connection as it may be used elsewhere
?> 