<?php
// Include database connection
require_once 'php/db_connect.php';

echo "CHECKING DATABASE SCHEMA\n";
echo "========================\n\n";

// Check betting_slips table structure
echo "BETTING_SLIPS TABLE COLUMNS:\n";
$result = $conn->query("DESCRIBE betting_slips");
while ($row = $result->fetch_assoc()) {
    echo "- " . $row['Field'] . " (" . $row['Type'] . ")" . ($row['Null'] == 'NO' ? ' NOT NULL' : '') . "\n";
}

echo "\n";

// Check bets table structure
echo "BETS TABLE COLUMNS:\n";
$result = $conn->query("DESCRIBE bets");
while ($row = $result->fetch_assoc()) {
    echo "- " . $row['Field'] . " (" . $row['Type'] . ")" . ($row['Null'] == 'NO' ? ' NOT NULL' : '') . "\n";
}

echo "\n";

// Check players table
echo "PLAYERS TABLE RECORDS:\n";
$result = $conn->query("SELECT * FROM players");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "- ID: " . $row['player_id'] . ", Username: " . $row['username'] . ", Created: " . $row['created_at'] . "\n";
    }
} else {
    echo "No players found in the database!\n";
}

// Close connection
$conn->close();
?> 