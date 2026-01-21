<?php
// Set error reporting to show all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to print results both to HTML and to a log file for debugging
function writeOutput($message) {
    echo $message . "\n";
    
    // Also write to a log file for debugging
    $logFile = 'logs/db_check.log';
    file_put_contents($logFile, $message . "\n", FILE_APPEND);
}

writeOutput("<h1>Database Structure Check</h1>");

// Database configuration
$host = 'localhost';
$database = 'roulette';
$user = 'root';
$password = '';

try {
    // Create connection
    $conn = new mysqli($host, $user, $password, $database);

    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    writeOutput("<p>Connected to database successfully</p>");
    
    // Check if database exists
    $dbCheckQuery = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$database'";
    $dbCheckResult = $conn->query($dbCheckQuery);
    
    if ($dbCheckResult->num_rows == 0) {
        writeOutput("<p style='color:red'>Database '$database' does not exist!</p>");
        throw new Exception("Database '$database' does not exist!");
    } else {
        writeOutput("<p style='color:green'>Database '$database' exists</p>");
    }

    // Check if table exists
    $tableCheckQuery = "SHOW TABLES LIKE 'roulette_settings'";
    $result = $conn->query($tableCheckQuery);
    
    if ($result->num_rows == 0) {
        writeOutput("<p style='color:red'>roulette_settings table does not exist!</p>");
        
        // Show what tables exist in the database
        writeOutput("<h3>Existing tables in the database:</h3>");
        $tablesResult = $conn->query("SHOW TABLES");
        
        if ($tablesResult) {
            writeOutput("<ul>");
            while ($table = $tablesResult->fetch_array()) {
                writeOutput("<li>{$table[0]}</li>");
            }
            writeOutput("</ul>");
        }
        
        // Create the table if it doesn't exist
        writeOutput("<p>Attempting to create roulette_settings table...</p>");
        
        $createTableSql = "CREATE TABLE roulette_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            automatic_mode TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        if ($conn->query($createTableSql) === TRUE) {
            writeOutput("<p style='color:green'>roulette_settings table created successfully</p>");
            
            // Insert initial record
            $initSql = "INSERT INTO roulette_settings (id, automatic_mode) VALUES (1, 1)";
                       
            if ($conn->query($initSql) === TRUE) {
                writeOutput("<p style='color:green'>Initial record inserted into roulette_settings</p>");
            } else {
                writeOutput("<p style='color:red'>Error inserting initial record: " . $conn->error . "</p>");
            }
        } else {
            writeOutput("<p style='color:red'>Error creating roulette_settings table: " . $conn->error . "</p>");
        }
    } else {
        writeOutput("<p style='color:green'>roulette_settings table exists</p>");
        
        // Get table structure
        writeOutput("<h2>roulette_settings Table Structure:</h2>");
        $describeResult = $conn->query("DESCRIBE roulette_settings");
        
        if ($describeResult) {
            writeOutput("<table border='1' cellpadding='5'>");
            writeOutput("<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>");
            
            while ($row = $describeResult->fetch_assoc()) {
                writeOutput("<tr>");
                writeOutput("<td>{$row['Field']}</td>");
                writeOutput("<td>{$row['Type']}</td>");
                writeOutput("<td>{$row['Null']}</td>");
                writeOutput("<td>{$row['Key']}</td>");
                writeOutput("<td>{$row['Default']}</td>");
                writeOutput("<td>{$row['Extra']}</td>");
                writeOutput("</tr>");
            }
            
            writeOutput("</table>");
        } else {
            writeOutput("Error describing table: " . $conn->error);
        }
        
        // Check for automatic_mode column specifically
        $columnCheckQuery = "SHOW COLUMNS FROM roulette_settings LIKE 'automatic_mode'";
        $columnResult = $conn->query($columnCheckQuery);
        
        if ($columnResult->num_rows == 0) {
            writeOutput("<p style='color:red'>automatic_mode column does not exist in roulette_settings table!</p>");
            
            // Show what columns actually exist
            writeOutput("<h3>Existing columns in roulette_settings:</h3>");
            $columnsResult = $conn->query("SHOW COLUMNS FROM roulette_settings");
            
            if ($columnsResult) {
                writeOutput("<ul>");
                while ($column = $columnsResult->fetch_assoc()) {
                    writeOutput("<li>{$column['Field']}</li>");
                }
                writeOutput("</ul>");
            }
            
            // Add the column if it doesn't exist
            writeOutput("<p>Attempting to add automatic_mode column...</p>");
            
            $addColumnSql = "ALTER TABLE roulette_settings ADD COLUMN automatic_mode TINYINT(1) DEFAULT 1";
            
            if ($conn->query($addColumnSql) === TRUE) {
                writeOutput("<p style='color:green'>automatic_mode column added successfully</p>");
            } else {
                writeOutput("<p style='color:red'>Error adding automatic_mode column: " . $conn->error . "</p>");
            }
        } else {
            writeOutput("<p style='color:green'>automatic_mode column exists in roulette_settings table</p>");
        }
    }
    
    // Try running the actual query from draw_info.php
    writeOutput("<h2>Testing Query from draw_info.php:</h2>");
    $testQuery = "SELECT automatic_mode FROM roulette_settings WHERE id = 1 LIMIT 1";
    
    try {
        $testResult = $conn->query($testQuery);
        if ($testResult) {
            if ($testResult->num_rows > 0) {
                $row = $testResult->fetch_assoc();
                writeOutput("<p>Query successful. Value: " . $row['automatic_mode'] . "</p>");
            } else {
                writeOutput("<p>Query successful but no rows returned.</p>");
                
                // Insert a row if none exists
                writeOutput("<p>Attempting to insert a row into roulette_settings...</p>");
                
                $insertSql = "INSERT INTO roulette_settings (id, automatic_mode) VALUES (1, 1)";
                
                if ($conn->query($insertSql) === TRUE) {
                    writeOutput("<p style='color:green'>Row inserted successfully</p>");
                } else {
                    writeOutput("<p style='color:red'>Error inserting row: " . $conn->error . "</p>");
                }
            }
        } else {
            writeOutput("<p style='color:red'>Query failed: " . $conn->error . "</p>");
        }
    } catch (Exception $e) {
        writeOutput("<p style='color:red'>Exception: " . $e->getMessage() . "</p>");
    }
    
    // Close the connection
    $conn->close();
    
} catch (Exception $e) {
    writeOutput("<p style='color:red'>Error: " . $e->getMessage() . "</p>");
}

writeOutput("<p>Check complete! Check the logs directory for the db_check.log file for details.</p>");
?> 