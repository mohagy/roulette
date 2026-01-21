<?php
/**
 * Create Detailed Draw Results Table
 * 
 * This script creates the new detailed_draw_results table for storing
 * individual spin records while maintaining security measures.
 */

// Initialize cache prevention
require_once 'php/cache_prevention.php';

// Include database connection
require_once 'php/db_connect.php';

echo "<!DOCTYPE html>";
echo "<html><head><title>Create Detailed Draw Results Table</title>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} .warning{color:orange;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background-color:#f2f2f2;}</style>";
echo "</head><body>";

echo "<h1>🗄️ Create Detailed Draw Results Table</h1>";

echo "<h2>📋 Table Specifications</h2>";
echo "<p>Creating <code>detailed_draw_results</code> table with the following structure:</p>";
echo "<table>";
echo "<tr><th>Column</th><th>Type</th><th>Description</th></tr>";
echo "<tr><td>id</td><td>INT PRIMARY KEY AUTO_INCREMENT</td><td>Unique identifier for each spin record</td></tr>";
echo "<tr><td>draw_number</td><td>INT NOT NULL</td><td>Sequential draw number</td></tr>";
echo "<tr><td>winning_number</td><td>INT NOT NULL</td><td>Roulette number that won (0-36)</td></tr>";
echo "<tr><td>color</td><td>VARCHAR(10) NOT NULL</td><td>Color of winning number (red/black/green)</td></tr>";
echo "<tr><td>timestamp</td><td>DATETIME NOT NULL</td><td>Exact time when spin occurred</td></tr>";
echo "<tr><td>created_at</td><td>TIMESTAMP DEFAULT CURRENT_TIMESTAMP</td><td>Record creation time</td></tr>";
echo "</table>";

try {
    // Start transaction for atomic operation
    $conn->autocommit(false);
    
    echo "<h2>Step 1: Check if Table Exists</h2>";
    
    // Check if table already exists
    $result = $conn->query("SHOW TABLES LIKE 'detailed_draw_results'");
    
    if ($result->num_rows > 0) {
        echo "<p class='warning'>⚠️ Table 'detailed_draw_results' already exists</p>";
        
        // Show current structure
        $structure = $conn->query("DESCRIBE detailed_draw_results");
        if ($structure) {
            echo "<h3>Current Table Structure:</h3>";
            echo "<table>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
            while ($row = $structure->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
                echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
                echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
                echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
                echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
                echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
        echo "<p class='info'>ℹ️ Skipping table creation as it already exists</p>";
    } else {
        echo "<p class='info'>ℹ️ Table does not exist, creating new table</p>";
        
        echo "<h2>Step 2: Create Table</h2>";
        
        // Create the detailed_draw_results table
        $createTableSQL = "
            CREATE TABLE detailed_draw_results (
                id INT PRIMARY KEY AUTO_INCREMENT,
                draw_number INT NOT NULL,
                winning_number INT NOT NULL CHECK (winning_number >= 0 AND winning_number <= 36),
                color VARCHAR(10) NOT NULL CHECK (color IN ('red', 'black', 'green')),
                timestamp DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_draw_number (draw_number),
                INDEX idx_winning_number (winning_number),
                INDEX idx_timestamp (timestamp),
                INDEX idx_color (color)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        if ($conn->query($createTableSQL)) {
            echo "<p class='success'>✅ Table 'detailed_draw_results' created successfully</p>";
            
            logCachePrevention("Created detailed_draw_results table", [
                'timestamp' => date('Y-m-d H:i:s'),
                'table_name' => 'detailed_draw_results'
            ]);
        } else {
            throw new Exception("Failed to create table: " . $conn->error);
        }
    }
    
    echo "<h2>Step 3: Create Color Mapping Function</h2>";
    
    // Create a stored function for color mapping
    $colorFunctionSQL = "
        DROP FUNCTION IF EXISTS get_roulette_color;
        CREATE FUNCTION get_roulette_color(number INT) 
        RETURNS VARCHAR(10)
        READS SQL DATA
        DETERMINISTIC
        BEGIN
            DECLARE color VARCHAR(10);
            
            IF number = 0 THEN
                SET color = 'green';
            ELSEIF number IN (1,3,5,7,9,12,14,16,18,19,21,23,25,27,30,32,34,36) THEN
                SET color = 'red';
            ELSE
                SET color = 'black';
            END IF;
            
            RETURN color;
        END
    ";
    
    if ($conn->multi_query($colorFunctionSQL)) {
        // Process all results from multi_query
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->next_result());
        
        echo "<p class='success'>✅ Color mapping function created successfully</p>";
        
        logCachePrevention("Created get_roulette_color function", [
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        echo "<p class='warning'>⚠️ Color function creation failed (may already exist): " . $conn->error . "</p>";
    }
    
    echo "<h2>Step 4: Test Color Function</h2>";
    
    // Test the color function
    $testNumbers = [0, 1, 2, 12, 13, 36];
    echo "<table>";
    echo "<tr><th>Number</th><th>Color</th><th>Expected</th><th>Status</th></tr>";
    
    foreach ($testNumbers as $number) {
        $result = $conn->query("SELECT get_roulette_color($number) as color");
        if ($result) {
            $row = $result->fetch_assoc();
            $actualColor = $row['color'];
            
            // Expected colors
            $expectedColors = [
                0 => 'green',
                1 => 'red',
                2 => 'black',
                12 => 'red',
                13 => 'black',
                36 => 'red'
            ];
            
            $expected = $expectedColors[$number];
            $status = ($actualColor === $expected) ? 'success' : 'error';
            $statusText = ($actualColor === $expected) ? '✅ Correct' : '❌ Wrong';
            
            echo "<tr>";
            echo "<td>$number</td>";
            echo "<td class='$status'>$actualColor</td>";
            echo "<td>$expected</td>";
            echo "<td class='$status'>$statusText</td>";
            echo "</tr>";
        }
    }
    echo "</table>";
    
    echo "<h2>Step 5: Create Sample Data</h2>";
    
    // Insert some sample data to verify the table works
    $sampleData = [
        ['draw_number' => 1, 'winning_number' => 25, 'timestamp' => date('Y-m-d H:i:s', strtotime('-5 minutes'))],
        ['draw_number' => 2, 'winning_number' => 0, 'timestamp' => date('Y-m-d H:i:s', strtotime('-3 minutes'))],
        ['draw_number' => 3, 'winning_number' => 14, 'timestamp' => date('Y-m-d H:i:s', strtotime('-1 minute'))]
    ];
    
    $insertSQL = "INSERT INTO detailed_draw_results (draw_number, winning_number, color, timestamp) VALUES (?, ?, get_roulette_color(?), ?)";
    $stmt = $conn->prepare($insertSQL);
    
    if ($stmt) {
        foreach ($sampleData as $data) {
            $stmt->bind_param("iiss", $data['draw_number'], $data['winning_number'], $data['winning_number'], $data['timestamp']);
            if ($stmt->execute()) {
                echo "<p class='success'>✅ Sample record inserted: Draw #{$data['draw_number']}, Number {$data['winning_number']}</p>";
            } else {
                echo "<p class='error'>❌ Failed to insert sample record: " . $stmt->error . "</p>";
            }
        }
        $stmt->close();
    }
    
    echo "<h2>Step 6: Verify Table Contents</h2>";
    
    // Show current table contents
    $result = $conn->query("SELECT * FROM detailed_draw_results ORDER BY id DESC LIMIT 10");
    if ($result && $result->num_rows > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Draw Number</th><th>Winning Number</th><th>Color</th><th>Timestamp</th><th>Created At</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['draw_number']) . "</td>";
            echo "<td>" . htmlspecialchars($row['winning_number']) . "</td>";
            echo "<td class='" . strtolower($row['color']) . "'>" . htmlspecialchars($row['color']) . "</td>";
            echo "<td>" . htmlspecialchars($row['timestamp']) . "</td>";
            echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='info'>ℹ️ No records found in table</p>";
    }
    
    // Commit all changes
    $conn->commit();
    echo "<h2 class='success'>✅ Table Creation Complete</h2>";
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo "<h2 class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</h2>";
    echo "<p class='error'>All changes have been rolled back.</p>";
} finally {
    // Restore autocommit
    $conn->autocommit(true);
}

echo "<h2>🎯 Next Steps</h2>";
echo "<ol>";
echo "<li><strong>Implement Dual Storage:</strong> Update TV display to save to both tables</li>";
echo "<li><strong>Test Integration:</strong> Verify data is saved correctly during spins</li>";
echo "<li><strong>Monitor Security:</strong> Ensure security measures remain active</li>";
echo "<li><strong>Verify Synchronization:</strong> Check that both tables stay in sync</li>";
echo "</ol>";

echo "<button onclick='window.open(\"implement_dual_storage.php\", \"_blank\")' style='background:#28a745;color:white;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;margin:5px;'>🔄 Implement Dual Storage</button>";
echo "<button onclick='window.location.reload()' style='background:#6c757d;color:white;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;margin:5px;'>🔄 Refresh</button>";

echo "<p><a href='final_analytics_verification.php'>← Analytics Verification</a> | <a href='implement_dual_storage.php'>Implement Dual Storage →</a></p>";
echo "</body></html>";
?>
