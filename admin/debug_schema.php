<?php
require_once '../php/db_connect.php';

echo "<h1>Schema Debug</h1>";

// Describe roulette_state
echo "<h2>roulette_state Schema</h2>";
$query = "DESCRIBE roulette_state";
$result = $conn->query($query);
if ($result) {
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $val) echo "<td>$val</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error: " . $conn->error;
}

// Content of roulette_state
echo "<h2>roulette_state Content</h2>";
$query = "SELECT * FROM roulette_state";
$result = $conn->query($query);
if ($result) {
    if ($result->num_rows > 0) {
        echo "<table border='1'>";
        // Header
        $fields = $result->fetch_fields();
        echo "<tr>";
        foreach ($fields as $field) echo "<th>{$field->name}</th>";
        echo "</tr>";
        
        // Data
        while ($row = $result->fetch_assoc()) {
            echo "ID: " . $row['id'] . " | Next Draw: " . $row['next_draw_number'] . " | Current Draw: " . $row['current_draw_number'] . "\n";
        }
    } else {
        echo "Table is empty.\n";
    }
} else {
    echo "Error: " . $conn->error;
}

// Count betting_slips again
echo "<h2>Betting Slips Count</h2>";
$query = "SELECT COUNT(*) as count FROM betting_slips";
$result = $conn->query($query);
$row = $result->fetch_assoc();
echo "Total: " . $row['count'];
?>
