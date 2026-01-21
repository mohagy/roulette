<?php
/**
 * Smart Number Selection API
 * 
 * This API provides intelligent number selection that:
 * - Minimizes payouts (house profit)
 * - Creates patterns that look mathematical/random
 * - Uses time-based presets
 * - Appears fair but favors the house
 */

header('Content-Type: application/json');
require_once '../includes/db_connection.php';
require_once '../includes/helper_functions.php';

date_default_timezone_set('America/La_Paz');

$response = [
    'status' => 'error',
    'message' => 'An error occurred',
    'data' => []
];

try {
    $drawNumber = isset($_GET['draw_number']) ? intval($_GET['draw_number']) : null;
    $timePreset = isset($_GET['time_preset']) ? $_GET['time_preset'] : 'auto';
    $patternType = isset($_GET['pattern_type']) ? $_GET['pattern_type'] : 'smart';
    
    // Get current draw number if not provided
    if ($drawNumber === null) {
        $stmt = $conn->prepare("SELECT current_draw_number FROM roulette_analytics WHERE id = 1 LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $drawNumber = $result->fetch_assoc()['current_draw_number'];
        } else {
            $drawNumber = 1;
        }
        $stmt->close();
    }
    
    // Get recent winning numbers to create patterns
    $stmt = $conn->prepare("
        SELECT winning_number, draw_number, timestamp
        FROM detailed_draw_results
        WHERE draw_number < ?
        ORDER BY draw_number DESC
        LIMIT 20
    ");
    $stmt->bind_param("i", $drawNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    $recentNumbers = [];
    while ($row = $result->fetch_assoc()) {
        $recentNumbers[] = $row;
    }
    $stmt->close();
    
    // Get bet distribution for this draw
    $stmt = $conn->prepare("
        SELECT b.bet_type, b.bet_description, b.bet_amount, b.potential_return
        FROM betting_slips bs
        JOIN slip_details sd ON bs.slip_id = sd.slip_id
        JOIN bets b ON sd.bet_id = b.bet_id
        WHERE bs.draw_number = ?
    ");
    $stmt->bind_param('i', $drawNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Calculate payouts for each number
    $numberPayouts = array_fill(0, 37, 0);
    $numbersWithNoBets = range(0, 36);
    
    while ($bet = $result->fetch_assoc()) {
        // Process bet to calculate payouts (simplified - you may need to adjust based on your bet processing logic)
        processBet($bet['bet_type'], $bet['bet_description'], $bet['bet_amount'], $bet['potential_return'], $numberPayouts, $numbersWithNoBets);
    }
    $stmt->close();
    
    // Get current time for time-based presets
    $now = new DateTime('now', new DateTimeZone('America/La_Paz'));
    $currentHour = (int)$now->format('H');
    $currentMinute = (int)$now->format('i');
    
    // Time-based preset logic
    $timeBasedNumbers = [];
    if ($timePreset === 'auto' || $timePreset === 'time_based') {
        // Morning (6-12): Favor lower numbers (0-12)
        if ($currentHour >= 6 && $currentHour < 12) {
            $timeBasedNumbers = array_merge(range(0, 12), [18, 24, 30, 36]);
        }
        // Afternoon (12-18): Mix of mid-range (13-24)
        elseif ($currentHour >= 12 && $currentHour < 18) {
            $timeBasedNumbers = array_merge(range(13, 24), [0, 7, 14, 21, 28, 35]);
        }
        // Evening (18-24): Higher numbers (25-36)
        elseif ($currentHour >= 18 && $currentHour < 24) {
            $timeBasedNumbers = array_merge(range(25, 36), [0, 6, 12, 18, 24, 30]);
        }
        // Night (0-6): Random distribution
        else {
            $timeBasedNumbers = range(0, 36);
        }
    } else {
        $timeBasedNumbers = range(0, 36);
    }
    
    // Pattern-based selection
    $patternNumbers = [];
    if ($patternType === 'smart' && count($recentNumbers) >= 3) {
        // Create patterns that look mathematical
        $lastThree = array_slice($recentNumbers, 0, 3);
        $lastNumbers = array_column($lastThree, 'winning_number');
        
        // Pattern 1: Fibonacci-like sequence (but not actual Fibonacci)
        $pattern1 = [];
        if (count($lastNumbers) >= 2) {
            $diff = abs($lastNumbers[0] - $lastNumbers[1]);
            $next = ($lastNumbers[0] + $diff) % 37;
            $pattern1[] = $next;
            $pattern1[] = ($next + $diff) % 37;
        }
        
        // Pattern 2: Alternating colors (but choose the one with less payout)
        $lastColor = getNumberColor($lastNumbers[0]);
        $oppositeColor = ($lastColor === 'red') ? 'black' : (($lastColor === 'black') ? 'red' : 'green');
        $pattern2 = [];
        for ($i = 0; $i <= 36; $i++) {
            if (getNumberColor($i) === $oppositeColor) {
                $pattern2[] = $i;
            }
        }
        
        // Pattern 3: Number that hasn't appeared recently (but with low payout)
        $recentNumberSet = array_unique(array_column($recentNumbers, 'winning_number'));
        $pattern3 = array_diff(range(0, 36), $recentNumberSet);
        
        // Combine patterns, prioritizing low payout numbers
        $patternNumbers = array_merge($pattern1, $pattern2, $pattern3);
        $patternNumbers = array_unique($patternNumbers);
    } else {
        $patternNumbers = range(0, 36);
    }
    
    // Smart selection: Combine time-based, pattern-based, and payout minimization
    $candidateNumbers = array_intersect($timeBasedNumbers, $patternNumbers);
    if (empty($candidateNumbers)) {
        $candidateNumbers = range(0, 36);
    }
    
    // Filter to numbers with no bets or low payouts
    $bestCandidates = [];
    $lowPayoutCandidates = [];
    
    foreach ($candidateNumbers as $num) {
        if (in_array($num, $numbersWithNoBets)) {
            $bestCandidates[] = $num;
        } elseif ($numberPayouts[$num] < 100) { // Low payout threshold
            $lowPayoutCandidates[] = $num;
        }
    }
    
    // Prioritize: no bets > low payouts > others
    if (!empty($bestCandidates)) {
        $selectedNumber = $bestCandidates[array_rand($bestCandidates)];
        $reason = 'No bets on this number (time-based pattern)';
    } elseif (!empty($lowPayoutCandidates)) {
        // Sort by payout and pick from bottom 25%
        usort($lowPayoutCandidates, function($a, $b) use ($numberPayouts) {
            return $numberPayouts[$a] <=> $numberPayouts[$b];
        });
        $bottom25 = array_slice($lowPayoutCandidates, 0, max(1, floor(count($lowPayoutCandidates) * 0.25)));
        $selectedNumber = $bottom25[array_rand($bottom25)];
        $reason = 'Low payout selection (minimizes house loss) - $' . number_format($numberPayouts[$selectedNumber], 2);
    } else {
        // Fallback: pick from candidates with lowest payout
        $candidatePayouts = [];
        foreach ($candidateNumbers as $num) {
            $candidatePayouts[$num] = $numberPayouts[$num];
        }
        asort($candidatePayouts);
        $lowestPayoutNumbers = array_slice(array_keys($candidatePayouts), 0, 5);
        $selectedNumber = $lowestPayoutNumbers[array_rand($lowestPayoutNumbers)];
        $reason = 'Smart selection (pattern + low payout) - $' . number_format($numberPayouts[$selectedNumber], 2);
    }
    
    // Calculate "pattern analysis" for display (to make it look mathematical)
    $patternAnalysis = [
        'last_three' => array_slice(array_column($recentNumbers, 'winning_number'), 0, 3),
        'suggested_pattern' => 'Time-based distribution with payout optimization',
        'mathematical_basis' => 'Fibonacci-like sequence with color alternation',
        'confidence' => rand(75, 95) . '%'
    ];
    
    // Prepare recent numbers for frontend
    $recentNumberValues = array_column($recentNumbers, 'winning_number');
    
    // Prepare candidate payouts if not already set
    if (!isset($candidatePayouts)) {
        $candidatePayouts = [];
        foreach ($candidateNumbers as $num) {
            $candidatePayouts[$num] = $numberPayouts[$num];
        }
        asort($candidatePayouts);
    }
    
    // Prepare payout data for frontend
    $payoutData = [
        'number_payouts' => $numberPayouts,
        'low_payout_numbers' => array_slice(array_keys($candidatePayouts), 0, 15),
        'no_bet_numbers' => $bestCandidates
    ];
    
    $response = [
        'status' => 'success',
        'message' => 'Smart number selected',
        'data' => [
            'selected_number' => $selectedNumber,
            'selected_color' => getNumberColor($selectedNumber),
            'reason' => $reason,
            'payout' => $numberPayouts[$selectedNumber],
            'time_preset' => $timePreset,
            'pattern_type' => $patternType,
            'current_time' => $now->format('H:i:s'),
            'pattern_analysis' => $patternAnalysis,
            'candidates_analyzed' => count($candidateNumbers),
            'numbers_with_no_bets' => count($bestCandidates),
            'low_payout_options' => count($lowPayoutCandidates),
            'recent_numbers' => array_slice($recentNumberValues, 0, 10), // Last 10 numbers
            'payout_data' => $payoutData,
            'low_payout_numbers' => array_slice(array_keys($candidatePayouts ?? []), 0, 15)
        ]
    ];
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log("Smart Number Selection Error: " . $e->getMessage());
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>

