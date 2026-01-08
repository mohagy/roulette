<?php
// Database connection parameters
$host = "localhost";
$username = "root"; // Default XAMPP username
$password = ""; // Default XAMPP password is empty

try {
    // Create connection to MySQL server (without specifying a database)
    $conn = new mysqli($host, $username, $password);
    
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "Connected to MySQL server successfully.<br>";
    
    // Create database if it doesn't exist
    $conn->query("CREATE DATABASE IF NOT EXISTS roulette_db");
    echo "Database 'roulette_db' created or verified.<br>";
    
    // Read SQL file content
    $sqlFile = file_get_contents(__DIR__ . '/setup_database.sql');
    
    if ($sqlFile === false) {
        throw new Exception("Could not read the SQL file. Make sure it exists in the same directory as this script.");
    }
    
    // Execute multi-query SQL commands
    if ($conn->multi_query($sqlFile)) {
        echo "Starting execution of SQL script...<br>";
        
        // Process all result sets
        $resultCount = 0;
        do {
            $resultCount++;
            
            // Check if there are more results
            if ($result = $conn->store_result()) {
                $result->free();
                echo "Query $resultCount executed successfully.<br>";
            } else {
                if ($conn->error) {
                    echo "Error in query $resultCount: " . $conn->error . "<br>";
                } else {
                    echo "Query $resultCount executed (no result).<br>";
                }
            }
        } while ($conn->more_results() && $conn->next_result());
        
        if ($conn->error) {
            echo "Error in final queries: " . $conn->error . "<br>";
        } else {
            echo "All queries executed successfully.<br>";
        }
    } else {
        echo "Error executing SQL script: " . $conn->error . "<br>";
    }
    
    // Verify the tables were created
    $conn->select_db("roulette_db");
    $tables = array("players", "bets", "game_history", "betting_slips", "slip_details");
    $allTablesExist = true;
    
    echo "<br>Verifying tables:<br>";
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            echo "✓ Table '$table' exists.<br>";
        } else {
            echo "✗ Table '$table' does not exist.<br>";
            $allTablesExist = false;
        }
    }
    
    if ($allTablesExist) {
        echo "<br><strong>Database setup completed successfully!</strong><br>";
    } else {
        echo "<br><strong>Some tables could not be created. Please check the errors above.</strong><br>";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
    
    // Make sure to close the connection if it exists
    if (isset($conn)) {
        $conn->close();
    }
}
?> 