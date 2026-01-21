<?php
/**
 * Fix Database Collation Script
 * 
 * This script fixes the collation mismatch between tables in the roulette database.
 * It converts all tables to use the same collation (utf8mb4_unicode_ci).
 */

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

// Tables that need to be converted from utf8mb4_general_ci to utf8mb4_unicode_ci
$tablesToFix = [
    'commission',
    'commission_summary',
    'settings',
    'transactions',
    'users',
    'vouchers'
];

// Start transaction
$conn->begin_transaction();

try {
    echo "<h1>Fixing Database Collation</h1>";
    
    // Get all tables in the database
    $stmt = $conn->prepare("SHOW TABLES");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $allTables = [];
    while ($row = $result->fetch_row()) {
        $allTables[] = $row[0];
    }
    
    echo "<p>Found " . count($allTables) . " tables in the database.</p>";
    
    // Fix collation for each table
    foreach ($tablesToFix as $table) {
        if (!in_array($table, $allTables)) {
            echo "<p>Table '$table' not found in database. Skipping.</p>";
            continue;
        }
        
        echo "<p>Fixing collation for table '$table'...</p>";
        
        // Get table structure
        $stmt = $conn->prepare("SHOW CREATE TABLE `$table`");
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $createTableSql = $row['Create Table'];
        
        // Convert table to utf8mb4_unicode_ci
        $sql = "ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
        if ($conn->query($sql) === TRUE) {
            echo "<p>Successfully converted table '$table' to utf8mb4_unicode_ci.</p>";
        } else {
            throw new Exception("Error converting table '$table': " . $conn->error);
        }
        
        // Get columns for the table
        $stmt = $conn->prepare("SHOW COLUMNS FROM `$table`");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $columns = [];
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row;
        }
        
        // Fix collation for each column that needs it
        foreach ($columns as $column) {
            $columnName = $column['Field'];
            
            // Only fix string columns (CHAR, VARCHAR, TEXT, etc.)
            if (strpos($column['Type'], 'char') !== false || 
                strpos($column['Type'], 'text') !== false || 
                strpos($column['Type'], 'enum') !== false) {
                
                $sql = "ALTER TABLE `$table` MODIFY `$columnName` {$column['Type']} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
                
                // Add NULL/NOT NULL constraint
                if ($column['Null'] === 'YES') {
                    $sql .= " NULL";
                } else {
                    $sql .= " NOT NULL";
                }
                
                // Add default value if exists
                if ($column['Default'] !== null) {
                    $sql .= " DEFAULT '" . $conn->real_escape_string($column['Default']) . "'";
                }
                
                if ($conn->query($sql) === TRUE) {
                    echo "<p>Fixed collation for column '$columnName' in table '$table'.</p>";
                } else {
                    throw new Exception("Error fixing collation for column '$columnName' in table '$table': " . $conn->error);
                }
            }
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    echo "<p style='color: green; font-weight: bold;'>All collation fixes applied successfully.</p>";
    echo "<p><a href='auto_fix_transactions.php'>Run Auto Fix Transactions</a></p>";
    echo "<p><a href='index.html'>Return to Game</a></p>";
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo "<h1>Error</h1>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
    echo "<p><a href='index.html'>Return to Game</a></p>";
}

// Close connection
$conn->close();
?>
