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

// Check commission table structure
echo "COMMISSION TABLE STRUCTURE:\n";
echo "-------------------------\n";
$result = $conn->query("DESCRIBE commission");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "{$row['Field']} ({$row['Type']}) - {$row['Key']} - {$row['Extra']}\n";
    }
} else {
    echo "Error getting commission table structure: " . $conn->error . "\n";
}

echo "\n\nCOMMISSION_SUMMARY TABLE STRUCTURE:\n";
echo "--------------------------------\n";
$result = $conn->query("DESCRIBE commission_summary");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "{$row['Field']} ({$row['Type']}) - {$row['Key']} - {$row['Extra']}\n";
    }
} else {
    echo "Error getting commission_summary table structure: " . $conn->error . "\n";
}

echo "\n\nSample commission data:\n";
$result = $conn->query("SELECT * FROM commission LIMIT 5");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        print_r($row);
        echo "\n";
    }
} else {
    echo "No commission data found or error: " . $conn->error . "\n";
}

echo "\n\nSample commission_summary data:\n";
$result = $conn->query("SELECT * FROM commission_summary LIMIT 5");
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
