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
    
    echo "<h2>Roulette Database Setup</h2>";
    echo "<p>Connected to MySQL server successfully.</p>";
    
    // Read SQL file content
    $sqlFile = file_get_contents(__DIR__ . '/complete_database_setup.sql');
    
    if ($sqlFile === false) {
        throw new Exception("Could not read the SQL file. Make sure it exists in the same directory as this script.");
    }
    
    // Execute multi-query SQL commands
    if ($conn->multi_query($sqlFile)) {
        echo "<p>Starting execution of SQL script...</p>";
        
        // Process all result sets
        $resultCount = 0;
        do {
            $resultCount++;
            
            // Check if there are more results
            if ($result = $conn->store_result()) {
                $result->free();
                echo "<p>Query $resultCount executed successfully.</p>";
            } else {
                if ($conn->error) {
                    echo "<p style='color: red;'>Error in query $resultCount: " . $conn->error . "</p>";
                } else {
                    echo "<p>Query $resultCount executed (no result).</p>";
                }
            }
        } while ($conn->more_results() && $conn->next_result());
        
        if ($conn->error) {
            echo "<p style='color: red;'>Error in final queries: " . $conn->error . "</p>";
        } else {
            echo "<p style='color: green;'>All queries executed successfully.</p>";
        }
    } else {
        echo "<p style='color: red;'>Error executing SQL script: " . $conn->error . "</p>";
    }
    
    // Create a new connection to verify the database and tables
    $verifyConn = new mysqli($host, $username, $password, "roulette_db");
    
    if ($verifyConn->connect_error) {
        throw new Exception("Failed to connect to the roulette_db database: " . $verifyConn->connect_error);
    }
    
    // Verify the tables were created
    $tables = array("players", "bets", "game_history", "betting_slips", "slip_details");
    $allTablesExist = true;
    
    echo "<h3>Verifying database setup:</h3>";
    echo "<ul>";
    foreach ($tables as $table) {
        $result = $verifyConn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            echo "<li style='color: green;'>✓ Table '$table' exists.</li>";
            
            // Count rows in the table
            $countResult = $verifyConn->query("SELECT COUNT(*) as count FROM $table");
            $count = $countResult->fetch_assoc()['count'];
            echo "<li style='margin-left: 20px;'>- Contains $count records</li>";
        } else {
            echo "<li style='color: red;'>✗ Table '$table' does not exist.</li>";
            $allTablesExist = false;
        }
    }
    echo "</ul>";
    
    if ($allTablesExist) {
        echo "<p style='color: green; font-weight: bold;'>Database setup completed successfully!</p>";
        echo "<p>You can now run the roulette game application.</p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>Some tables could not be created. Please check the errors above.</p>";
    }
    
    $verifyConn->close();
    $conn->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    
    // Make sure to close the connection if it exists
    if (isset($conn)) {
        $conn->close();
    }
    if (isset($verifyConn)) {
        $verifyConn->close();
    }
}
?>

<style>
    body {
        font-family: Arial, sans-serif;
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
        line-height: 1.5;
    }
    h2 {
        color: #333;
        border-bottom: 2px solid #eee;
        padding-bottom: 10px;
    }
    p {
        margin: 10px 0;
    }
    ul {
        list-style-type: none;
        padding-left: 10px;
    }
    li {
        margin: 5px 0;
    }
</style> 