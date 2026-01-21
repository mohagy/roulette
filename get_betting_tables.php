<?php
// Database configuration
require_once 'db_config.php';

try {
    // Get betting_slips table structure
    echo "BETTING SLIPS TABLE STRUCTURE:\n";
    echo "-----------------------------\n";
    $stmt = $pdo->query("DESCRIBE betting_slips");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo "{$column['Field']} ({$column['Type']})\n";
    }
    
    echo "\n\nBETS TABLE STRUCTURE:\n";
    echo "--------------------\n";
    $stmt = $pdo->query("DESCRIBE bets");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo "{$column['Field']} ({$column['Type']})\n";
    }
    
    echo "\n\nSLIP_DETAILS TABLE STRUCTURE:\n";
    echo "----------------------------\n";
    $stmt = $pdo->query("DESCRIBE slip_details");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo "{$column['Field']} ({$column['Type']})\n";
    }
    
    echo "\n\nUSERS TABLE STRUCTURE:\n";
    echo "---------------------\n";
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo "{$column['Field']} ({$column['Type']})\n";
    }
    
    echo "\n\nSample betting_slips data:\n";
    $stmt = $pdo->query("SELECT * FROM betting_slips LIMIT 5");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($rows);
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
