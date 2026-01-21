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

// Check betting_slips table structure
echo "BETTING_SLIPS TABLE STRUCTURE:\n";
echo "----------------------------\n";
$result = $conn->query("DESCRIBE betting_slips");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "{$row['Field']} ({$row['Type']}) - {$row['Key']} - {$row['Extra']}\n";
    }
} else {
    echo "Error getting betting_slips table structure: " . $conn->error . "\n";
}

echo "\n\nSample betting_slips data:\n";
$result = $conn->query("SELECT * FROM betting_slips LIMIT 5");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        print_r($row);
        echo "\n";
    }
} else {
    echo "No betting_slips data found or error: " . $conn->error . "\n";
}

// Close connection
$conn->close();
?>
