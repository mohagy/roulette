<?php
// Database connection parameters
$servername = "localhost";
$username = "root";  // Default XAMPP username
$password = "";      // Default XAMPP password (empty)
$dbname = "roulette";  // Using the roulette database

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check commission_summary table structure
echo "COMMISSION_SUMMARY TABLE STRUCTURE:\n";
echo "--------------------------------\n";
$result = $conn->query("DESCRIBE commission_summary");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "{$row['Field']} ({$row['Type']}) - {$row['Key']} - {$row['Extra']}\n";
    }
} else {
    echo "Error getting commission_summary table structure: " . $conn->error . "\n";
}

// Check for unique constraints
echo "\nUNIQUE CONSTRAINTS ON COMMISSION_SUMMARY:\n";
echo "----------------------------------------\n";
$result = $conn->query("SHOW INDEXES FROM commission_summary WHERE Non_unique = 0");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "Index: {$row['Key_name']}, Column: {$row['Column_name']}\n";
    }
} else {
    echo "Error getting unique constraints: " . $conn->error . "\n";
}

// Get all commission_summary records
echo "\nALL COMMISSION_SUMMARY RECORDS:\n";
echo "-----------------------------\n";
$result = $conn->query("SELECT * FROM commission_summary");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        print_r($row);
        echo "\n";
    }
} else {
    echo "No commission_summary data found or error: " . $conn->error . "\n";
}

// Close connection
$conn->close();
?>
