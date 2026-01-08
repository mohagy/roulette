<?php
// Database connection parameters
$servername = "localhost";
$username = "root";  // Default XAMPP username
$password = "";      // Default XAMPP password (empty)
$dbname = "roulette";  // Using the roulette database

// First connect without specifying a database
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$conn->query("CREATE DATABASE IF NOT EXISTS $dbname");
echo "Database '$dbname' created or verified.<br>";

// Select the database
$conn->select_db($dbname);
echo "Connected to database successfully.<br>";

// Read SQL file content
$sqlFile = file_get_contents('create_users_table.sql');

if ($sqlFile === false) {
    die("Could not read the SQL file. Make sure it exists in the same directory as this script.");
}

// Execute the SQL commands
if ($conn->multi_query($sqlFile)) {
    echo "Users table created successfully.<br>";

    // Process all result sets
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
} else {
    echo "Error creating users table: " . $conn->error . "<br>";
}

$conn->close();
echo "Database connection closed.<br>";
?>
