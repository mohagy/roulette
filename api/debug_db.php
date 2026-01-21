<?php
require_once '../includes/db_connection.php';

echo "Tables:\n";
$tables = $conn->query("SHOW TABLES");
while ($row = $tables->fetch_array()) {
    echo "- " . $row[0] . "\n";
}

echo "\nRoulette Analytics (id=1):\n";
$analytics = $conn->query("SELECT * FROM roulette_analytics WHERE id = 1");
if ($analytics->num_rows > 0) {
    print_r($analytics->fetch_assoc());
} else {
    echo "No row with id=1 found.\n";
    // Check if table is empty
    $count = $conn->query("SELECT COUNT(*) as c FROM roulette_analytics")->fetch_assoc()['c'];
    echo "Total rows: $count\n";
}

echo "\nRoulette State Columns:\n";
$cols = $conn->query("SHOW COLUMNS FROM roulette_state");
while ($row = $cols->fetch_assoc()) {
    echo "- " . $row['Field'] . "\n";
}

echo "\nRoulette State (LIMIT 1):\n";
$state = $conn->query("SELECT * FROM roulette_state LIMIT 1");
if ($state->num_rows > 0) {
    print_r($state->fetch_assoc());
} else {
    echo "Table is empty.\n";
}
?>
