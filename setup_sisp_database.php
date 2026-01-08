<?php
echo "=== SETTING UP SISP DATABASE ===\n\n";

try {
    // Connect to MySQL
    $pdo = new PDO("mysql:host=localhost", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Connected to MySQL\n";
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS sisp_features CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✅ Database 'sisp_features' ready\n";
    
    // Connect to the sisp_features database
    $pdo = new PDO("mysql:host=localhost;dbname=sisp_features", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Read and execute the schema file
    $schemaFile = '../sisp/database/schema.sql';
    if (file_exists($schemaFile)) {
        echo "✅ Found schema file\n";
        
        $sql = file_get_contents($schemaFile);
        
        // Remove the CREATE DATABASE and USE statements since we're already connected
        $sql = preg_replace('/CREATE DATABASE.*?;/i', '', $sql);
        $sql = preg_replace('/USE.*?;/i', '', $sql);
        
        // Split into individual statements
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        $executed = 0;
        foreach ($statements as $statement) {
            if (!empty($statement) && !preg_match('/^\s*--/', $statement)) {
                try {
                    $pdo->exec($statement);
                    $executed++;
                } catch (PDOException $e) {
                    echo "⚠️  Warning executing statement: " . $e->getMessage() . "\n";
                }
            }
        }
        
        echo "✅ Executed $executed SQL statements\n";
        
        // Check if sample data exists and import it
        $sampleDataFile = '../sisp/database/sample_data.sql';
        if (file_exists($sampleDataFile)) {
            echo "✅ Found sample data file\n";
            
            $sampleSql = file_get_contents($sampleDataFile);
            $sampleStatements = array_filter(array_map('trim', explode(';', $sampleSql)));
            
            $sampleExecuted = 0;
            foreach ($sampleStatements as $statement) {
                if (!empty($statement) && !preg_match('/^\s*--/', $statement)) {
                    try {
                        $pdo->exec($statement);
                        $sampleExecuted++;
                    } catch (PDOException $e) {
                        echo "⚠️  Warning executing sample data: " . $e->getMessage() . "\n";
                    }
                }
            }
            
            echo "✅ Executed $sampleExecuted sample data statements\n";
        }
        
        // Verify tables were created
        echo "\nVerifying database setup:\n";
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $count = $stmt->fetch()['count'];
            echo "  ✅ $table ($count rows)\n";
        }
        
        echo "\n🎉 SISP database setup complete!\n";
        echo "You can now access: http://localhost:8080/sisp/\n";
        
    } else {
        echo "❌ Schema file not found: $schemaFile\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}
?>
