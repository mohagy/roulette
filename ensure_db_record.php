<?php
// Include database configuration
require_once 'db_config.php';

try {
    // Check if record with ID 1 exists
    $checkStmt = $pdo->query("SELECT COUNT(*) FROM roulette_state WHERE id = 1");
    $exists = $checkStmt->fetchColumn();
    
    if (!$exists) {
        // Insert initial record if it doesn't exist
        $insertSql = "INSERT INTO roulette_state (id, roll_history, roll_colors, last_draw, next_draw, countdown_time) 
                      VALUES (1, '', '', '#0', '#1', 120)";
        $pdo->exec($insertSql);
        echo "Initial record created.<br>";
    } else {
        echo "Record with ID 1 already exists.<br>";
    }
    
    // Show current database data
    $dataStmt = $pdo->query("SELECT * FROM roulette_state WHERE id = 1");
    $data = $dataStmt->fetch();
    
    echo "<h2>Current Database State:</h2>";
    echo "<pre>";
    print_r($data);
    echo "</pre>";
    
    echo "<p><a href='tvdisplay/index.html'>Return to game</a></p>";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?> 