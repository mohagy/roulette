<?php
// Analyze complete bet exposure for all bet types
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "roulette";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== ANALYZING COMPLETE BET EXPOSURE FOR DRAW 84 ===\n\n";
    
    // Get all bets for draw 84
    $stmt = $pdo->query("
        SELECT 
            b.bet_type,
            b.bet_description,
            b.bet_amount,
            COUNT(*) as count
        FROM betting_slips bs
        JOIN slip_details sd ON bs.slip_id = sd.slip_id
        JOIN bets b ON sd.bet_id = b.bet_id
        WHERE bs.draw_number = 84
        GROUP BY b.bet_type, b.bet_description, b.bet_amount
        ORDER BY b.bet_type, b.bet_description
    ");
    $bets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "1. All bet types found in Draw 84:\n";
    $totalBetAmount = 0;
    foreach ($bets as $bet) {
        $totalAmount = $bet['bet_amount'] * $bet['count'];
        $totalBetAmount += $totalAmount;
        echo "   {$bet['bet_type']}: {$bet['bet_description']} - \${$bet['bet_amount']} x {$bet['count']} = \${$totalAmount}\n";
    }
    echo "   TOTAL BET AMOUNT: \${$totalBetAmount}\n\n";
    
    // Calculate exposure per number
    echo "2. Calculating exposure per number (0-36):\n";
    $numberExposure = array_fill(0, 37, 0); // Initialize all numbers 0-36 with 0 exposure
    
    foreach ($bets as $bet) {
        $betAmount = $bet['bet_amount'] * $bet['count'];
        $description = $bet['bet_description'];
        $type = $bet['bet_type'];
        
        echo "   Processing: {$type} - {$description} (\${$betAmount})\n";
        
        switch ($type) {
            case 'straight':
                // Straight Up on X
                if (preg_match('/Straight Up on (\d+)/', $description, $matches)) {
                    $number = (int)$matches[1];
                    if ($number >= 0 && $number <= 36) {
                        $numberExposure[$number] += $betAmount;
                        echo "     → Number {$number}: +\${$betAmount}\n";
                    }
                }
                break;
                
            case 'split':
                // Split (X,Y)
                if (preg_match('/Split \((\d+),(\d+)\)/', $description, $matches)) {
                    $num1 = (int)$matches[1];
                    $num2 = (int)$matches[2];
                    $exposurePerNumber = $betAmount / 2; // Split between 2 numbers
                    
                    if ($num1 >= 0 && $num1 <= 36) {
                        $numberExposure[$num1] += $exposurePerNumber;
                        echo "     → Number {$num1}: +\${$exposurePerNumber}\n";
                    }
                    if ($num2 >= 0 && $num2 <= 36) {
                        $numberExposure[$num2] += $exposurePerNumber;
                        echo "     → Number {$num2}: +\${$exposurePerNumber}\n";
                    }
                }
                break;
                
            case 'corner':
                // Corner (W,X,Y,Z)
                if (preg_match('/Corner \((\d+),(\d+),(\d+),(\d+)\)/', $description, $matches)) {
                    $numbers = [(int)$matches[1], (int)$matches[2], (int)$matches[3], (int)$matches[4]];
                    $exposurePerNumber = $betAmount / 4; // Split between 4 numbers
                    
                    foreach ($numbers as $number) {
                        if ($number >= 0 && $number <= 36) {
                            $numberExposure[$number] += $exposurePerNumber;
                            echo "     → Number {$number}: +\${$exposurePerNumber}\n";
                        }
                    }
                }
                break;
                
            case 'street':
                // Street (X,Y,Z) - 3 numbers
                if (preg_match('/Street \((\d+),(\d+),(\d+)\)/', $description, $matches)) {
                    $numbers = [(int)$matches[1], (int)$matches[2], (int)$matches[3]];
                    $exposurePerNumber = $betAmount / 3; // Split between 3 numbers
                    
                    foreach ($numbers as $number) {
                        if ($number >= 0 && $number <= 36) {
                            $numberExposure[$number] += $exposurePerNumber;
                            echo "     → Number {$number}: +\${$exposurePerNumber}\n";
                        }
                    }
                }
                break;
                
            case 'sixline':
                // Six Line (A,B,C,D,E,F) - 6 numbers
                if (preg_match('/Six Line \((\d+),(\d+),(\d+),(\d+),(\d+),(\d+)\)/', $description, $matches)) {
                    $numbers = [(int)$matches[1], (int)$matches[2], (int)$matches[3], (int)$matches[4], (int)$matches[5], (int)$matches[6]];
                    $exposurePerNumber = $betAmount / 6; // Split between 6 numbers
                    
                    foreach ($numbers as $number) {
                        if ($number >= 0 && $number <= 36) {
                            $numberExposure[$number] += $exposurePerNumber;
                            echo "     → Number {$number}: +\${$exposurePerNumber}\n";
                        }
                    }
                }
                break;
                
            default:
                echo "     → Unhandled bet type: {$type}\n";
                break;
        }
    }
    
    echo "\n3. Final exposure per number:\n";
    $totalExposure = 0;
    $numbersWithExposure = 0;
    
    for ($i = 0; $i <= 36; $i++) {
        if ($numberExposure[$i] > 0) {
            echo "   Number {$i}: \${$numberExposure[$i]}\n";
            $totalExposure += $numberExposure[$i];
            $numbersWithExposure++;
        }
    }
    
    echo "\n4. Summary:\n";
    echo "   Numbers with exposure: {$numbersWithExposure}\n";
    echo "   Total exposure tracked: \${$totalExposure}\n";
    echo "   Total bet amount: \${$totalBetAmount}\n";
    echo "   Difference: \$" . ($totalBetAmount - $totalExposure) . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
