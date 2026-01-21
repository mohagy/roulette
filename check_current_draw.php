<?php
// Check current draw from database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "roulette";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== CHECKING CURRENT DRAW ===\n\n";
    
    // Check roulette_state table
    echo "1. roulette_state table (latest 3 rows):\n";
    try {
        $stmt = $pdo->query("SELECT id, draw_number, next_draw_number, state_type, created_at FROM roulette_state ORDER BY id DESC LIMIT 3");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($results) {
            foreach ($results as $row) {
                echo "   ID: {$row['id']}, Draw: {$row['draw_number']}, Next: {$row['next_draw_number']}, State: {$row['state_type']}, Time: {$row['created_at']}\n";
            }
        } else {
            echo "   No data in roulette_state\n";
        }
    } catch (Exception $e) {
        echo "   Error: " . $e->getMessage() . "\n";
    }
    
    echo "\n2. detailed_draw_results table (latest 3 rows):\n";
    try {
        $stmt = $pdo->query("SELECT id, draw_number, winning_number, timestamp FROM detailed_draw_results ORDER BY id DESC LIMIT 3");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($results) {
            foreach ($results as $row) {
                echo "   ID: {$row['id']}, Draw: {$row['draw_number']}, Winner: {$row['winning_number']}, Time: {$row['timestamp']}\n";
            }
            
            $latestDraw = $results[0]['draw_number'];
            $nextDraw = $latestDraw + 1;
            echo "   → Latest completed draw: {$latestDraw}, Next draw should be: {$nextDraw}\n";
        } else {
            echo "   No data in detailed_draw_results\n";
        }
    } catch (Exception $e) {
        echo "   Error: " . $e->getMessage() . "\n";
    }
    
    echo "\n3. What the API should return:\n";
    
    // Try the same logic as the API
    try {
        $stmt = $pdo->query("SELECT draw_number FROM roulette_state ORDER BY id DESC LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $currentDraw = $result['draw_number'];
            echo "   From roulette_state: Draw {$currentDraw}\n";
        } else {
            $stmt = $pdo->query("SELECT draw_number FROM detailed_draw_results ORDER BY id DESC LIMIT 1");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $currentDraw = $result ? $result['draw_number'] + 1 : 81;
            echo "   From detailed_draw_results: Next draw {$currentDraw}\n";
        }
        
        echo "   → API should return: {$currentDraw}\n";
        
    } catch (Exception $e) {
        echo "   Error in API logic: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "Database connection error: " . $e->getMessage() . "\n";
}
?>
