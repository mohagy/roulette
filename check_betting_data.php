<?php
// Check betting data in database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "roulette";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== CHECKING BETTING DATA FOR DRAW 84 ===\n\n";
    
    // Check betting_slips table for draw 84
    echo "1. Recent betting_slips entries (last 10):\n";
    try {
        $stmt = $pdo->query("SELECT slip_id, draw_number, total_stake, created_at FROM betting_slips ORDER BY slip_id DESC LIMIT 10");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($results) {
            foreach ($results as $row) {
                echo "   Slip ID: {$row['slip_id']}, Draw: {$row['draw_number']}, Stake: \${$row['total_stake']}, Time: {$row['created_at']}\n";
            }
        } else {
            echo "   No betting slips found\n";
        }
    } catch (Exception $e) {
        echo "   Error: " . $e->getMessage() . "\n";
    }
    
    // Check slip_details for draw 84
    echo "\n2. Slip details for draw 84:\n";
    try {
        $stmt = $pdo->query("
            SELECT sd.*, bs.draw_number, bs.total_stake, bs.created_at 
            FROM slip_details sd 
            JOIN betting_slips bs ON sd.slip_id = bs.slip_id 
            WHERE bs.draw_number = 84 
            ORDER BY sd.detail_id DESC
        ");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($results) {
            foreach ($results as $row) {
                echo "   Detail ID: {$row['detail_id']}, Slip: {$row['slip_id']}, Bet ID: {$row['bet_id']}, Draw: {$row['draw_number']}, Stake: \${$row['total_stake']}, Time: {$row['created_at']}\n";
            }
        } else {
            echo "   No slip details found for draw 84\n";
        }
    } catch (Exception $e) {
        echo "   Error: " . $e->getMessage() . "\n";
    }
    
    // Check if there's a separate bets table
    echo "\n3. Checking for other bet-related tables:\n";
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '%bet%'");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if ($tables) {
            foreach ($tables as $table) {
                echo "   Found table: {$table}\n";
                
                // Check structure of each bet table
                $stmt = $pdo->query("DESCRIBE {$table}");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo "     Columns: " . implode(', ', array_column($columns, 'Field')) . "\n";
                
                // Check for recent data
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM {$table}");
                $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                echo "     Row count: {$count}\n";
            }
        } else {
            echo "   No bet-related tables found\n";
        }
    } catch (Exception $e) {
        echo "   Error: " . $e->getMessage() . "\n";
    }
    
    // Test the exact query the API uses
    echo "\n4. Testing API query for draw 84:\n";
    try {
        $stmt = $pdo->prepare("
            SELECT 
                bet_number,
                SUM(bet_amount) as total_amount,
                COUNT(*) as bet_count
            FROM betting_slips 
            WHERE draw_number = ? AND bet_type = 'straight'
            GROUP BY bet_number
        ");
        $stmt->execute([84]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($results) {
            echo "   Found betting data:\n";
            foreach ($results as $row) {
                echo "     Number: {$row['bet_number']}, Amount: \${$row['total_amount']}, Count: {$row['bet_count']}\n";
            }
        } else {
            echo "   No betting data found with API query\n";
            
            // Check if bet_type column exists
            $stmt = $pdo->query("SHOW COLUMNS FROM betting_slips LIKE 'bet_type'");
            if ($stmt->rowCount() == 0) {
                echo "   NOTE: 'bet_type' column does not exist in betting_slips table\n";
            }
            
            // Check if bet_number column exists
            $stmt = $pdo->query("SHOW COLUMNS FROM betting_slips LIKE 'bet_number'");
            if ($stmt->rowCount() == 0) {
                echo "   NOTE: 'bet_number' column does not exist in betting_slips table\n";
            }
            
            // Check if bet_amount column exists
            $stmt = $pdo->query("SHOW COLUMNS FROM betting_slips LIKE 'bet_amount'");
            if ($stmt->rowCount() == 0) {
                echo "   NOTE: 'bet_amount' column does not exist in betting_slips table\n";
            }
        }
    } catch (Exception $e) {
        echo "   API Query Error: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "Database connection error: " . $e->getMessage() . "\n";
}
?>
