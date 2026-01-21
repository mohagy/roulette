<?php
/**
 * Slip Analytics API
 * Returns betting slips for a draw and calculates which ones would win for a given number
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once '../includes/db_connection.php';

$response = [
    'status' => 'error',
    'message' => 'Failed to fetch slip analytics',
    'data' => null
];

// Helper function to check if a bet wins for a given number
function checkBetWins($bet, $winningNumber, $winningColor) {
    $betType = $bet['bet_type'];
    $betDescription = $bet['bet_description'];
    
    switch ($betType) {
        case 'straight':
            // Direct number bet - extract from description
            preg_match('/(\d+)/', $betDescription, $matches);
            if (isset($matches[1])) {
                return (int)$matches[1] === $winningNumber;
            }
            return false;
            
        case 'split':
            // Split bet on two numbers
            preg_match('/\((\d+),(\d+)\)/', $betDescription, $matches);
            if (isset($matches[1]) && isset($matches[2])) {
                return in_array($winningNumber, [(int)$matches[1], (int)$matches[2]]);
            }
            return false;
            
        case 'street':
            // Street bet on three numbers
            preg_match('/\((\d+),(\d+),(\d+)\)/', $betDescription, $matches);
            if (isset($matches[1]) && isset($matches[2]) && isset($matches[3])) {
                return in_array($winningNumber, [(int)$matches[1], (int)$matches[2], (int)$matches[3]]);
            }
            return false;
            
        case 'corner':
            // Corner bet on four numbers
            preg_match('/\((\d+),(\d+),(\d+),(\d+)\)/', $betDescription, $matches);
            if (isset($matches[1]) && isset($matches[2]) && isset($matches[3]) && isset($matches[4])) {
                return in_array($winningNumber, [(int)$matches[1], (int)$matches[2], (int)$matches[3], (int)$matches[4]]);
            }
            return false;
            
        case 'color':
            // Red or Black
            $color = strtolower($betDescription);
            if (strpos($color, 'red') !== false) {
                return $winningColor === 'red';
            } else if (strpos($color, 'black') !== false) {
                return $winningColor === 'black';
            } else if (strpos($color, 'green') !== false) {
                return $winningColor === 'green';
            }
            return false;
            
        case 'even':
            return $winningNumber % 2 === 0 && $winningNumber !== 0;
            
        case 'odd':
            return $winningNumber % 2 === 1;
            
        case 'high':
            return $winningNumber >= 19 && $winningNumber <= 36;
            
        case 'low':
            return $winningNumber >= 1 && $winningNumber <= 18;
            
        case 'dozen':
            if (strpos($betDescription, '1st') !== false || strpos($betDescription, 'first') !== false) {
                return $winningNumber >= 1 && $winningNumber <= 12;
            } else if (strpos($betDescription, '2nd') !== false || strpos($betDescription, 'second') !== false) {
                return $winningNumber >= 13 && $winningNumber <= 24;
            } else if (strpos($betDescription, '3rd') !== false || strpos($betDescription, 'third') !== false) {
                return $winningNumber >= 25 && $winningNumber <= 36;
            }
            return false;
            
        case 'column':
            // Column bets: column 1 (1,4,7,10,13,16,19,22,25,28,31,34), column 2 (2,5,8...), column 3 (3,6,9...)
            preg_match('/(\d+)/', $betDescription, $matches);
            if (isset($matches[1])) {
                $col = (int)$matches[1] % 3;
                return ($winningNumber - 1) % 3 === $col;
            }
            return false;
            
        default:
            return false;
    }
}

// Helper function to get number color
function getNumberColor($number) {
    if ($number === 0) return 'green';
    $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
    return in_array($number, $redNumbers) ? 'red' : 'black';
}

try {
    // Get draw number (required)
    $drawNumber = isset($_GET['draw_number']) ? (int)$_GET['draw_number'] : null;
    if (!$drawNumber) {
        throw new Exception('draw_number parameter is required');
    }
    
    // Get optional test_number parameter (to see which slips would win)
    $testNumber = isset($_GET['test_number']) ? (int)$_GET['test_number'] : null;
    $testColor = $testNumber !== null ? getNumberColor($testNumber) : null;
    
    // Get all betting slips for this draw
    $stmt = $conn->prepare("
        SELECT 
            bs.slip_id,
            bs.slip_number,
            bs.total_stake,
            bs.potential_payout,
            bs.created_at,
            COUNT(DISTINCT sd.bet_id) as bet_count
        FROM betting_slips bs
        LEFT JOIN slip_details sd ON bs.slip_id = sd.slip_id
        WHERE bs.draw_number = ?
        AND bs.is_paid = 0
        AND bs.is_cancelled = 0
        GROUP BY bs.slip_id
        ORDER BY bs.potential_payout ASC, bs.created_at DESC
    ");
    $stmt->bind_param("i", $drawNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $slips = [];
    $totalStake = 0;
    $totalPotentialPayout = 0;
    $winningSlipsCount = 0;
    $winningSlipsPayout = 0;
    
    while ($slip = $result->fetch_assoc()) {
        $slipId = (int)$slip['slip_id'];
        
        // Get all bets for this slip
        $betStmt = $conn->prepare("
            SELECT 
                b.bet_id,
                b.bet_type,
                b.bet_description,
                b.bet_amount,
                b.potential_return
            FROM slip_details sd
            JOIN bets b ON sd.bet_id = b.bet_id
            WHERE sd.slip_id = ?
        ");
        $betStmt->bind_param("i", $slipId);
        $betStmt->execute();
        $betResult = $betStmt->get_result();
        
        $bets = [];
        $slipWouldWin = false;
        $slipWinningBets = 0;
        $slipWinningPayout = 0;
        
        while ($bet = $betResult->fetch_assoc()) {
            $betWins = false;
            if ($testNumber !== null) {
                $betWins = checkBetWins($bet, $testNumber, $testColor);
            }
            
            $bets[] = [
                'bet_id' => (int)$bet['bet_id'],
                'bet_type' => $bet['bet_type'],
                'bet_description' => $bet['bet_description'],
                'bet_amount' => (float)$bet['bet_amount'],
                'potential_return' => (float)$bet['potential_return'],
                'would_win' => $betWins
            ];
            
            if ($betWins) {
                $slipWouldWin = true;
                $slipWinningBets++;
                $slipWinningPayout += (float)$bet['potential_return'];
            }
        }
        $betStmt->close();
        
        $slipData = [
            'slip_id' => $slipId,
            'slip_number' => $slip['slip_number'],
            'total_stake' => (float)$slip['total_stake'],
            'total_potential_payout' => (float)$slip['potential_payout'],
            'bet_count' => (int)$slip['bet_count'],
            'created_at' => $slip['created_at'],
            'bets' => $bets,
            'would_win' => $slipWouldWin,
            'winning_bets_count' => $slipWinningBets,
            'winning_payout' => round($slipWinningPayout, 2)
        ];
        
        $slips[] = $slipData;
        $totalStake += (float)$slip['total_stake'];
        $totalPotentialPayout += (float)$slip['potential_payout'];
        
        if ($slipWouldWin) {
            $winningSlipsCount++;
            $winningSlipsPayout += $slipWinningPayout;
        }
    }
    $stmt->close();
    
    // Sort slips: winning slips first (if test number provided), then by payout (lowest first)
    if ($testNumber !== null) {
        usort($slips, function($a, $b) {
            // Winning slips first
            if ($a['would_win'] !== $b['would_win']) {
                return $b['would_win'] ? 1 : -1;
            }
            // Then by payout (lowest first)
            return $a['total_potential_payout'] <=> $b['total_potential_payout'];
        });
    } else {
        // Just sort by payout (lowest first)
        usort($slips, function($a, $b) {
            return $a['total_potential_payout'] <=> $b['total_potential_payout'];
        });
    }
    
    $response = [
        'status' => 'success',
        'message' => 'Slip analytics retrieved successfully',
        'data' => [
            'draw_number' => $drawNumber,
            'test_number' => $testNumber,
            'test_color' => $testColor,
            'slips' => $slips,
            'total_slips' => count($slips),
            'total_stake' => round($totalStake, 2),
            'total_potential_payout' => round($totalPotentialPayout, 2),
            'winning_slips_count' => $winningSlipsCount,
            'winning_slips_payout' => round($winningSlipsPayout, 2),
            'losing_slips_count' => count($slips) - $winningSlipsCount
        ]
    ];
    
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
    error_log("Slip Analytics Error: " . $e->getMessage());
}

echo json_encode($response, JSON_PRETTY_PRINT);
$conn->close();
?>

