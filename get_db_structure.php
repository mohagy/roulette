<?php
// Database configuration
$db_host = 'localhost';
$db_name = 'roulette';
$db_user = 'root';
$db_pass = '';

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get all tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Database Tables:\n";
    echo "----------------\n";
    foreach ($tables as $table) {
        echo "- $table\n";
        
        // Get table structure
        $stmt = $pdo->query("DESCRIBE $table");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "  Columns:\n";
        foreach ($columns as $column) {
            echo "    - {$column['Field']} ({$column['Type']})";
            if ($column['Key'] == 'PRI') echo " PRIMARY KEY";
            if ($column['Extra'] == 'auto_increment') echo " AUTO_INCREMENT";
            echo "\n";
        }
        echo "\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
