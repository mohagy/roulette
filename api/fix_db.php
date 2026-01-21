<?php
require_once '../includes/db_connection.php';

// Check if row exists
$check = $conn->query("SELECT id FROM roulette_analytics WHERE id = 1");
if ($check->num_rows > 0) {
    echo "Row with id=1 already exists.\n";
    exit;
}

// Get columns to construct query dynamically or just use known columns
// Based on previous debug_db.php, we couldn't see columns because table was empty? No, SHOW COLUMNS works even if empty.
// Let's check columns first to be safe.
$cols = $conn->query("SHOW COLUMNS FROM roulette_analytics");
$columns = [];
while ($row = $cols->fetch_assoc()) {
    $columns[] = $row['Field'];
}

echo "Columns found: " . implode(", ", $columns) . "\n";

// Construct insert query
// We'll insert default values. 
// Assuming structure based on draw_info.php usage: current_draw_number
// And potentially others. Let's try to insert just id=1 and let others be default if possible, or set reasonable defaults.

$sql = "INSERT INTO roulette_analytics (id, current_draw_number) VALUES (1, 1)";

// If there are other required columns without defaults, we might need to know them.
// But let's try this simple insert first. If it fails, we'll see the error.

if ($conn->query($sql) === TRUE) {
    echo "New record created successfully\n";
} else {
    echo "Error: " . $sql . "\n" . $conn->error . "\n";
    
    // If error is about field doesn't have default value, we'll know which one.
}

$conn->close();
?>
