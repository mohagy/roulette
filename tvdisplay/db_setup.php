<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "roulette_results";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) !== TRUE) {
    echo "Error creating database: " . $conn->error;
}

// Select the database
$conn->select_db($dbname);

// Create results table
$sql = "CREATE TABLE IF NOT EXISTS results (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    number INT(2) NOT NULL,
    color VARCHAR(10) NOT NULL,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) !== TRUE) {
    echo "Error creating table: " . $conn->error;
}

echo "Database setup complete!";
$conn->close();
?> 