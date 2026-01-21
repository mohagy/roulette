<?php
echo "=== DATABASE CHECK AND SETUP ===\n\n";

try {
    // Connect without specifying database
    $pdo = new PDO("mysql:host=localhost;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Connected to MySQL server\n\n";
    
    // List all databases
    echo "Available databases:\n";
    $stmt = $pdo->query("SHOW DATABASES");
    $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($databases as $db) {
        echo "  - $db\n";
    }
    
    // Check if roulette database exists
    if (in_array('roulette', $databases)) {
        echo "\n✅ 'roulette' database exists\n";
        
        // Connect to roulette database
        $pdo = new PDO("mysql:host=localhost;dbname=roulette;charset=utf8mb4", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // List tables in roulette database
        echo "\nTables in 'roulette' database:\n";
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            echo "  - $table\n";
        }
        
        // Check key tables
        $keyTables = ['betting_slips', 'bets', 'slip_details', 'detailed_draw_results', 'roulette_state'];
        echo "\nKey tables status:\n";
        foreach ($keyTables as $table) {
            if (in_array($table, $tables)) {
                // Get row count
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
                $count = $stmt->fetch()['count'];
                echo "  ✅ $table ($count rows)\n";
            } else {
                echo "  ❌ $table (missing)\n";
            }
        }
        
    } else {
        echo "\n❌ 'roulette' database does not exist\n";
        echo "Creating 'roulette' database...\n";
        
        $pdo->exec("CREATE DATABASE roulette CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "✅ 'roulette' database created\n";
        
        // You would need to import your database structure here
        echo "\n⚠️  Database created but empty. You need to:\n";
        echo "1. Import your database structure/data\n";
        echo "2. Or restore from backup\n";
        echo "3. Or run your database setup scripts\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}
?>
