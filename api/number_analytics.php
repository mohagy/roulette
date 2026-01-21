<?php
/**
 * Number Analytics API
 * Returns analytics about numbers: uncalled numbers, lowest/highest payout numbers
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once '../includes/db_connection.php';

$response = [
    'status' => 'error',
    'message' => 'Failed to fetch number analytics',
    'data' => null
];

try {
    // Get current draw number
    $currentDrawNumber = null;
    $stmt = $conn->prepare("SELECT current_draw_number FROM roulette_analytics WHERE id = 1 LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $currentDrawNumber = (int)$row['current_draw_number'];
    }
    $stmt->close();
    
    if (!$currentDrawNumber) {
        $currentDrawNumber = 1;
    }
    
    // Get optional draw_number parameter (for specific draw analysis)
    $drawNumber = isset($_GET['draw_number']) ? (int)$_GET['draw_number'] : $currentDrawNumber;
    
    // 1. Get numbers that haven't been called (uncalled numbers)
    // Check last 50 draws to see which numbers haven't appeared
    $uncalledNumbers = [];
    $stmt = $conn->prepare("
        SELECT DISTINCT winning_number 
        FROM detailed_draw_results 
        WHERE draw_number <= ? 
        AND draw_number > ? - 50
        AND winning_number IS NOT NULL
        ORDER BY draw_number DESC
        LIMIT 50
    ");
    $stmt->bind_param("ii", $drawNumber, $drawNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $calledNumbers = [];
    while ($row = $result->fetch_assoc()) {
        $calledNumbers[] = (int)$row['winning_number'];
    }
    $stmt->close();
    
    // Find numbers that haven't been called in the last 50 draws
    $allNumbers = range(0, 36);
    $uncalledNumbers = array_values(array_diff($allNumbers, array_unique($calledNumbers)));
    
    // 2. Calculate payouts for each number for the specified draw
    $numberPayouts = array_fill(0, 37, 0.00);
    $numbersWithBets = [];
    
    // Get all bets for this draw
    $stmt = $conn->prepare("
        SELECT 
            b.bet_type,
            b.bet_description,
            b.bet_amount,
            b.potential_return
        FROM betting_slips bs
        JOIN slip_details sd ON bs.slip_id = sd.slip_id
        JOIN bets b ON sd.bet_id = b.bet_id
        WHERE bs.draw_number = ?
        AND bs.is_paid = 0
        AND bs.is_cancelled = 0
    ");
    $stmt->bind_param("i", $drawNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Process each bet to calculate payouts per number
    while ($bet = $result->fetch_assoc()) {
        $betType = $bet['bet_type'];
        $betAmount = (float)$bet['bet_amount'];
        $potentialReturn = (float)$bet['potential_return'];
        
        // Process based on bet type
        switch ($betType) {
            case 'straight':
                // Direct number bet - extract number from description
                preg_match('/(\d+)/', $bet['bet_description'], $matches);
                if (isset($matches[1])) {
                    $num = (int)$matches[1];
                    if ($num >= 0 && $num <= 36) {
                        $numberPayouts[$num] += $potentialReturn;
                        if (!in_array($num, $numbersWithBets)) {
                            $numbersWithBets[] = $num;
                        }
                    }
                }
                break;
                
            case 'split':
                // Split bet on two numbers
                preg_match('/\((\d+),(\d+)\)/', $bet['bet_description'], $matches);
                if (isset($matches[1]) && isset($matches[2])) {
                    $nums = [(int)$matches[1], (int)$matches[2]];
                    foreach ($nums as $num) {
                        if ($num >= 0 && $num <= 36) {
                            $numberPayouts[$num] += $potentialReturn;
                            if (!in_array($num, $numbersWithBets)) {
                                $numbersWithBets[] = $num;
                            }
                        }
                    }
                }
                break;
                
            case 'street':
                // Street bet on three numbers
                preg_match('/\((\d+),(\d+),(\d+)\)/', $bet['bet_description'], $matches);
                if (isset($matches[1]) && isset($matches[2]) && isset($matches[3])) {
                    $nums = [(int)$matches[1], (int)$matches[2], (int)$matches[3]];
                    foreach ($nums as $num) {
                        if ($num >= 0 && $num <= 36) {
                            $numberPayouts[$num] += $potentialReturn;
                            if (!in_array($num, $numbersWithBets)) {
                                $numbersWithBets[] = $num;
                            }
                        }
                    }
                }
                break;
                
            case 'corner':
                // Corner bet on four numbers
                preg_match('/\((\d+),(\d+),(\d+),(\d+)\)/', $bet['bet_description'], $matches);
                if (isset($matches[1]) && isset($matches[2]) && isset($matches[3]) && isset($matches[4])) {
                    $nums = [(int)$matches[1], (int)$matches[2], (int)$matches[3], (int)$matches[4]];
                    foreach ($nums as $num) {
                        if ($num >= 0 && $num <= 36) {
                            $numberPayouts[$num] += $potentialReturn;
                            if (!in_array($num, $numbersWithBets)) {
                                $numbersWithBets[] = $num;
                            }
                        }
                    }
                }
                break;
                
            case 'color':
            case 'even':
            case 'odd':
            case 'high':
            case 'low':
            case 'dozen':
            case 'column':
                // These bet types affect multiple numbers, calculate based on bet type
                // For simplicity, we'll distribute the payout across affected numbers
                $affectedNumbers = [];
                
                if ($betType === 'color') {
                    // Red or Black
                    $color = strtolower($bet['bet_description']);
                    $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
                    if (strpos($color, 'red') !== false) {
                        $affectedNumbers = $redNumbers;
                    } else if (strpos($color, 'black') !== false) {
                        $affectedNumbers = array_diff(range(1, 36), $redNumbers);
                    }
                } else if ($betType === 'even') {
                    $affectedNumbers = array_filter(range(1, 36), function($n) { return $n % 2 === 0; });
                } else if ($betType === 'odd') {
                    $affectedNumbers = array_filter(range(1, 36), function($n) { return $n % 2 === 1; });
                } else if ($betType === 'high') {
                    $affectedNumbers = range(19, 36);
                } else if ($betType === 'low') {
                    $affectedNumbers = range(1, 18);
                } else if ($betType === 'dozen') {
                    if (strpos($bet['bet_description'], '1st') !== false) {
                        $affectedNumbers = range(1, 12);
                    } else if (strpos($bet['bet_description'], '2nd') !== false) {
                        $affectedNumbers = range(13, 24);
                    } else if (strpos($bet['bet_description'], '3rd') !== false) {
                        $affectedNumbers = range(25, 36);
                    }
                } else if ($betType === 'column') {
                    // Column bets affect numbers in columns (1, 4, 7, 10, 13, 16, 19, 22, 25, 28, 31, 34), etc.
                    preg_match('/(\d+)/', $bet['bet_description'], $matches);
                    if (isset($matches[1])) {
                        $col = (int)$matches[1] % 3;
                        $affectedNumbers = array_filter(range(1, 36), function($n) use ($col) { return ($n - 1) % 3 === $col; });
                    }
                }
                
                // Distribute payout across affected numbers
                if (!empty($affectedNumbers)) {
                    $payoutPerNumber = $potentialReturn / count($affectedNumbers);
                    foreach ($affectedNumbers as $num) {
                        $numberPayouts[$num] += $payoutPerNumber;
                        if (!in_array($num, $numbersWithBets)) {
                            $numbersWithBets[] = $num;
                        }
                    }
                }
                break;
        }
    }
    $stmt->close();
    
    // Find lowest and highest payout numbers
    $lowestPayoutNumbers = [];
    $highestPayoutNumbers = [];
    
    // Filter out numbers with zero payout for lowest (only show numbers with bets)
    $numbersWithPayouts = array_filter($numberPayouts, function($payout) {
        return $payout > 0;
    });
    
    if (!empty($numbersWithPayouts)) {
        $minPayout = min($numbersWithPayouts);
        $maxPayout = max($numbersWithPayouts);
        
        // Get all numbers with minimum payout
        foreach ($numberPayouts as $num => $payout) {
            if ($payout > 0 && $payout == $minPayout) {
                $lowestPayoutNumbers[] = [
                    'number' => $num,
                    'payout' => round($payout, 2)
                ];
            }
            if ($payout > 0 && $payout == $maxPayout) {
                $highestPayoutNumbers[] = [
                    'number' => $num,
                    'payout' => round($payout, 2)
                ];
            }
        }
    }
    
    // Sort by number
    usort($lowestPayoutNumbers, function($a, $b) {
        return $a['number'] - $b['number'];
    });
    usort($highestPayoutNumbers, function($a, $b) {
        return $a['number'] - $b['number'];
    });
    
    $response = [
        'status' => 'success',
        'message' => 'Number analytics retrieved successfully',
        'data' => [
            'draw_number' => $drawNumber,
            'uncalled_numbers' => $uncalledNumbers,
            'uncalled_count' => count($uncalledNumbers),
            'lowest_payout_numbers' => $lowestPayoutNumbers,
            'highest_payout_numbers' => $highestPayoutNumbers,
            'number_payouts' => array_map(function($p) { return round($p, 2); }, $numberPayouts),
            'numbers_with_bets' => $numbersWithBets,
            'numbers_with_bets_count' => count($numbersWithBets)
        ]
    ];
    
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
    error_log("Number Analytics Error: " . $e->getMessage());
}

echo json_encode($response, JSON_PRETTY_PRINT);
$conn->close();
?>

