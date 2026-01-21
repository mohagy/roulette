<?php
// Include database connection
require_once 'php/db_connect.php';

// Check if the connection is successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get all tables
$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

// Check if the required tables exist
$requiredTables = [
    'betting_slips',
    'bets',
    'slip_details',
    'users',
    'transactions'
];

$missingTables = array_diff($requiredTables, $tables);

// Output the results
echo "<h1>Database Check</h1>";
echo "<h2>Connection</h2>";
echo "<p>Connected to database: $dbname</p>";

echo "<h2>Tables</h2>";
echo "<ul>";
foreach ($tables as $table) {
    echo "<li>$table</li>";
}
echo "</ul>";

if (!empty($missingTables)) {
    echo "<h2>Missing Tables</h2>";
    echo "<ul>";
    foreach ($missingTables as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul>";
}

// Check the structure of the betting_slips table
if (in_array('betting_slips', $tables)) {
    echo "<h2>Betting Slips Table Structure</h2>";
    $result = $conn->query("DESCRIBE betting_slips");
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Check the structure of the transactions table
if (in_array('transactions', $tables)) {
    echo "<h2>Transactions Table Structure</h2>";
    $result = $conn->query("DESCRIBE transactions");
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Check if the reprinted_from column exists in the betting_slips table
if (in_array('betting_slips', $tables)) {
    $result = $conn->query("SHOW COLUMNS FROM betting_slips LIKE 'reprinted_from'");
    if ($result->num_rows > 0) {
        echo "<p>The 'reprinted_from' column exists in the betting_slips table.</p>";
    } else {
        echo "<p>The 'reprinted_from' column does not exist in the betting_slips table.</p>";
        
        // Add the column if it doesn't exist
        echo "<p>Adding 'reprinted_from' column to betting_slips table...</p>";
        $sql = "ALTER TABLE `betting_slips` 
                ADD COLUMN `reprinted_from` INT NULL DEFAULT NULL 
                COMMENT 'Reference to the original slip_id if this is a reprint'";
        
        if ($conn->query($sql)) {
            echo "<p>reprinted_from column added successfully</p>";
        } else {
            echo "<p>Failed to add reprinted_from column: " . $conn->error . "</p>";
        }
    }
}

// Check if the is_reprint column exists in the betting_slips table
if (in_array('betting_slips', $tables)) {
    $result = $conn->query("SHOW COLUMNS FROM betting_slips LIKE 'is_reprint'");
    if ($result->num_rows > 0) {
        echo "<p>The 'is_reprint' column exists in the betting_slips table.</p>";
    } else {
        echo "<p>The 'is_reprint' column does not exist in the betting_slips table.</p>";
        
        // Add the column if it doesn't exist
        echo "<p>Adding 'is_reprint' column to betting_slips table...</p>";
        $sql = "ALTER TABLE `betting_slips` 
                ADD COLUMN `is_reprint` TINYINT(1) NOT NULL DEFAULT 0 
                COMMENT 'Whether this slip is a reprint'";
        
        if ($conn->query($sql)) {
            echo "<p>is_reprint column added successfully</p>";
        } else {
            echo "<p>Failed to add is_reprint column: " . $conn->error . "</p>";
        }
    }
}

// Check if the reprint_count column exists in the betting_slips table
if (in_array('betting_slips', $tables)) {
    $result = $conn->query("SHOW COLUMNS FROM betting_slips LIKE 'reprint_count'");
    if ($result->num_rows > 0) {
        echo "<p>The 'reprint_count' column exists in the betting_slips table.</p>";
    } else {
        echo "<p>The 'reprint_count' column does not exist in the betting_slips table.</p>";
        
        // Add the column if it doesn't exist
        echo "<p>Adding 'reprint_count' column to betting_slips table...</p>";
        $sql = "ALTER TABLE `betting_slips` 
                ADD COLUMN `reprint_count` INT NOT NULL DEFAULT 0 
                COMMENT 'Number of times this slip has been reprinted'";
        
        if ($conn->query($sql)) {
            echo "<p>reprint_count column added successfully</p>";
        } else {
            echo "<p>Failed to add reprint_count column: " . $conn->error . "</p>";
        }
    }
}

// Check if the transaction_id column exists in the betting_slips table
if (in_array('betting_slips', $tables)) {
    $result = $conn->query("SHOW COLUMNS FROM betting_slips LIKE 'transaction_id'");
    if ($result->num_rows > 0) {
        echo "<p>The 'transaction_id' column exists in the betting_slips table.</p>";
    } else {
        echo "<p>The 'transaction_id' column does not exist in the betting_slips table.</p>";
        
        // Add the column if it doesn't exist
        echo "<p>Adding 'transaction_id' column to betting_slips table...</p>";
        $sql = "ALTER TABLE `betting_slips` 
                ADD COLUMN `transaction_id` INT NULL DEFAULT NULL 
                COMMENT 'Reference to the transaction record'";
        
        if ($conn->query($sql)) {
            echo "<p>transaction_id column added successfully</p>";
        } else {
            echo "<p>Failed to add transaction_id column: " . $conn->error . "</p>";
        }
    }
}

// Close the connection
$conn->close();
?>
