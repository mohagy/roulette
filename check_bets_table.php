<?php
// Check bets table structure and data
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "roulette";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== CHECKING BETS TABLE STRUCTURE ===\n\n";
    
    // Check bets table structure
    echo "1. Bets table structure:\n";
    $stmt = $pdo->query("DESCRIBE bets");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        echo "   {$column['Field']} ({$column['Type']}) - {$column['Null']} - {$column['Key']} - {$column['Default']}\n";
    }
    
    // Check recent bets
    echo "\n2. Recent bets (last 10):\n";
    $stmt = $pdo->query("SELECT * FROM bets ORDER BY bet_id DESC LIMIT 10");
    $bets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($bets as $bet) {
        echo "   Bet ID: {$bet['bet_id']}, User: {$bet['user_id']}, Type: {$bet['bet_type']}, Desc: {$bet['bet_description']}, Amount: \${$bet['bet_amount']}, Return: \${$bet['potential_return']}, Time: {$bet['created_at']}\n";
    }
    
    // Check the relationship between tables
    echo "\n3. Checking relationship between betting_slips, slip_details, and bets for draw 84:\n";
    $stmt = $pdo->query("
        SELECT 
            bs.slip_id,
            bs.draw_number,
            bs.total_stake,
            sd.bet_id,
            b.bet_type,
            b.bet_description,
            b.bet_amount,
            b.potential_return
        FROM betting_slips bs
        JOIN slip_details sd ON bs.slip_id = sd.slip_id
        JOIN bets b ON sd.bet_id = b.bet_id
        WHERE bs.draw_number = 84
        ORDER BY bs.slip_id DESC
    ");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($results) {
        foreach ($results as $row) {
            echo "   Slip: {$row['slip_id']}, Draw: {$row['draw_number']}, Stake: \${$row['total_stake']}, Bet: {$row['bet_id']}, Type: {$row['bet_type']}, Desc: {$row['bet_description']}, Amount: \${$row['bet_amount']}, Return: \${$row['potential_return']}\n";
        }
    } else {
        echo "   No joined data found for draw 84\n";
    }
    
    // Try to extract roulette number from bet_description
    echo "\n4. Analyzing bet descriptions to extract roulette numbers:\n";
    $stmt = $pdo->query("
        SELECT 
            b.bet_description,
            b.bet_amount,
            COUNT(*) as count
        FROM betting_slips bs
        JOIN slip_details sd ON bs.slip_id = sd.slip_id
        JOIN bets b ON sd.bet_id = b.bet_id
        WHERE bs.draw_number = 84
        GROUP BY b.bet_description, b.bet_amount
    ");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($results) {
        foreach ($results as $row) {
            echo "   Description: '{$row['bet_description']}', Amount: \${$row['bet_amount']}, Count: {$row['count']}\n";
            
            // Try to extract number from description
            if (preg_match('/(\d+)/', $row['bet_description'], $matches)) {
                $number = $matches[1];
                if ($number >= 0 && $number <= 36) {
                    echo "     → Extracted roulette number: {$number}\n";
                }
            }
        }
    } else {
        echo "   No bet descriptions found for draw 84\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
