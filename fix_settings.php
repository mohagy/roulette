<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Fixing Roulette Settings</h1>";

// Include the database connection file
require_once 'includes/db_connection.php';

// Check if a record exists in roulette_settings
$checkQuery = "SELECT * FROM roulette_settings WHERE id = 1";
$result = $conn->query($checkQuery);

if ($result->num_rows === 0) {
    // No record exists, so insert one with automatic_mode set to 0 (manual)
    $insertQuery = "INSERT INTO roulette_settings (id, automatic_mode, updated_at) VALUES (1, 0, NOW())";
    
    if ($conn->query($insertQuery) === TRUE) {
        echo "<p style='color:green'>Success: Added missing record to roulette_settings table with automatic_mode = 0 (manual).</p>";
    } else {
        echo "<p style='color:red'>Error: " . $conn->error . "</p>";
    }
} else {
    // Record exists, make sure automatic_mode is set to 0 (manual)
    $updateQuery = "UPDATE roulette_settings SET automatic_mode = 0, updated_at = NOW() WHERE id = 1";
    
    if ($conn->query($updateQuery) === TRUE) {
        echo "<p style='color:green'>Success: Updated existing record in roulette_settings table to automatic_mode = 0 (manual).</p>";
    } else {
        echo "<p style='color:red'>Error: " . $conn->error . "</p>";
    }
}

// Now check the result to make sure it worked
$checkQuery = "SELECT * FROM roulette_settings WHERE id = 1";
$result = $conn->query($checkQuery);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "<p>Current settings: ID: {$row['id']}, Automatic Mode: {$row['automatic_mode']}, Updated: {$row['updated_at']}</p>";
} else {
    echo "<p style='color:red'>Error: Still no record in roulette_settings table!</p>";
}

// Check next_draw_winning_number table
echo "<h2>Current Winning Number Settings</h2>";
$query = "SELECT ra.current_draw_number, ndwn.winning_number, ndwn.source, ndwn.reason
          FROM roulette_analytics ra
          LEFT JOIN next_draw_winning_number ndwn ON ra.current_draw_number = ndwn.draw_number
          WHERE ra.id = 1";
$result = $conn->query($query);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "<p>Current Draw Number: {$row['current_draw_number']}</p>";
    
    if ($row['winning_number'] !== null) {
        echo "<p>Winning Number: {$row['winning_number']}</p>";
        echo "<p>Source: {$row['source']}</p>";
        echo "<p>Reason: {$row['reason']}</p>";
    } else {
        echo "<p>No winning number set for current draw</p>";
    }
} else {
    echo "<p>No records found</p>";
}

echo "<p><a href='check_settings.php'>Check Settings Again</a></p>";
?> 