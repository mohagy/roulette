<?php
// Include database connection
require_once 'includes/db_connection.php';

// Check if next_draw_winning_number table exists
$result = $conn->query("SHOW TABLES LIKE 'next_draw_winning_number'");
$tableExists = $result->num_rows > 0;

echo "<h1>Next Draw Winning Number Table Check</h1>";
echo "<p>Table next_draw_winning_number exists: " . ($tableExists ? "Yes" : "No") . "</p>";

// If table exists, get its structure
if ($tableExists) {
    $result = $conn->query("DESCRIBE next_draw_winning_number");
    echo "<h2>Table Structure:</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['Field']}</td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>{$row['Default']}</td>";
        echo "<td>{$row['Extra']}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Check if there are any records
    $result = $conn->query("SELECT * FROM next_draw_winning_number");
    echo "<p>Number of records: " . $result->num_rows . "</p>";
    
    if ($result->num_rows > 0) {
        echo "<h2>Existing Records:</h2>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Draw Number</th><th>Winning Number</th><th>Source</th><th>Reason</th><th>Created At</th><th>Updated At</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['draw_number']}</td>";
            echo "<td>{$row['winning_number']}</td>";
            echo "<td>{$row['source']}</td>";
            echo "<td>{$row['reason']}</td>";
            echo "<td>{$row['created_at']}</td>";
            echo "<td>{$row['updated_at']}</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
} else {
    // Create the table if it doesn't exist
    $createTableSQL = "
    CREATE TABLE next_draw_winning_number (
        id INT AUTO_INCREMENT PRIMARY KEY,
        draw_number INT NOT NULL,
        winning_number INT NOT NULL,
        source VARCHAR(50) NOT NULL DEFAULT 'manual',
        reason VARCHAR(255) NOT NULL DEFAULT 'Set by administrator',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (draw_number)
    )";
    
    if ($conn->query($createTableSQL)) {
        echo "<p style='color:green'>Table next_draw_winning_number created successfully!</p>";
    } else {
        echo "<p style='color:red'>Error creating table: " . $conn->error . "</p>";
    }
}

// Check current draw number
$result = $conn->query("SELECT current_draw_number FROM draw_control WHERE id = 1");
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "<p>Current draw number: " . $row['current_draw_number'] . "</p>";
} else {
    echo "<p style='color:red'>No current draw number found!</p>";
}

// Close the connection
$conn->close();
