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

$conn->select_db($dbname);

// Get recent results
$sql = "SELECT * FROM results ORDER BY timestamp DESC LIMIT 20";
$result = $conn->query($sql);

$recent_results = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $recent_results[] = [
            'number' => $row['number'],
            'color' => $row['color'],
            'timestamp' => $row['timestamp']
        ];
    }
}

// Get hot numbers (last 100 spins)
$sql = "SELECT number, COUNT(*) as count FROM results 
        ORDER BY timestamp DESC LIMIT 100 
        GROUP BY number 
        ORDER BY count DESC 
        LIMIT 5";
$result = $conn->query($sql);

$hot_numbers = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $hot_numbers[] = [
            'number' => $row['number'],
            'count' => $row['count']
        ];
    }
}

// Get cold numbers (least frequent in last 100 spins)
$sql = "SELECT number, COUNT(*) as count FROM results 
        ORDER BY timestamp DESC LIMIT 100 
        GROUP BY number 
        ORDER BY count ASC 
        LIMIT 5";
$result = $conn->query($sql);

$cold_numbers = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $cold_numbers[] = [
            'number' => $row['number'],
            'count' => $row['count']
        ];
    }
}

echo json_encode([
    'recent' => $recent_results,
    'hot' => $hot_numbers,
    'cold' => $cold_numbers
]);

$conn->close();
?> 