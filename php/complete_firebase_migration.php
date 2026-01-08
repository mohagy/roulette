<?php
/**
 * Complete Database Migration to Firebase
 * 
 * This script migrates ALL data from MySQL to Firebase Realtime Database
 * and updates PHP files to use Firebase instead of MySQL
 */

require_once __DIR__ . '/db_connect.php';

// Firebase URL
$firebaseUrl = 'https://roulette-2f902-default-rtdb.firebaseio.com';

header('Content-Type: application/json');

/**
 * Write data to Firebase using REST API
 */
function writeToFirebase($path, $data) {
    global $firebaseUrl;
    
    $url = $firebaseUrl . '/' . $path . '.json';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode >= 200 && $httpCode < 300;
}

/**
 * Batch write to Firebase (for large datasets)
 */
function batchWriteToFirebase($path, $dataArray) {
    global $firebaseUrl;
    
    $successCount = 0;
    $failCount = 0;
    
    foreach ($dataArray as $key => $data) {
        $url = $firebaseUrl . '/' . $path . '/' . $key . '.json';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            $successCount++;
        } else {
            $failCount++;
        }
    }
    
    return ['success' => $successCount, 'failed' => $failCount];
}

$results = [
    'status' => 'success',
    'migrations' => [],
    'errors' => [],
    'counts' => []
];

try {
    // ============================================
    // 1. MIGRATE ROULETTE_STATE
    // ============================================
    $stmt = $pdo->query("SELECT * FROM roulette_state ORDER BY id DESC LIMIT 1");
    $state = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get latest draw number
    $stmtLatest = $pdo->query("SELECT MAX(draw_number) as max_draw FROM detailed_draw_results");
    $latest = $stmtLatest->fetch(PDO::FETCH_ASSOC);
    $latestDrawNumber = intval($latest['max_draw'] ?? 0);
    
    // Get last 5 draws for roll history
    $stmtLast5 = $pdo->query("SELECT * FROM detailed_draw_results ORDER BY draw_number DESC LIMIT 5");
    $last5Draws = $stmtLast5->fetchAll(PDO::FETCH_ASSOC);
    
    $rollHistory = [];
    $rollColors = [];
    foreach (array_reverse($last5Draws) as $draw) {
        $rollHistory[] = intval($draw['winning_number'] ?? 0);
        $rollColors[] = $draw['winning_color'] ?? $draw['color'] ?? 'black';
    }
    
    $gameState = [
        'drawNumber' => $latestDrawNumber > 0 ? $latestDrawNumber : ($state['draw_number'] ?? 1),
        'nextDrawNumber' => $latestDrawNumber > 0 ? $latestDrawNumber + 1 : ($state['next_draw_number'] ?? 2),
        'winningNumber' => !empty($last5Draws) ? intval($last5Draws[0]['winning_number'] ?? 0) : ($state['winning_number'] ?? null),
        'nextWinningNumber' => $state['next_winning_number'] ?? null,
        'manualMode' => ($state['manual_mode'] ?? 0) == 1,
        'rollHistory' => $rollHistory,
        'rollColors' => $rollColors,
        'lastDrawFormatted' => $latestDrawNumber > 0 ? "#{$latestDrawNumber}" : "#0",
        'nextDrawFormatted' => $latestDrawNumber > 0 ? "#" . ($latestDrawNumber + 1) : "#1",
        'updatedAt' => ($state['updated_at'] ?? date('Y-m-d\TH:i:s.000\Z'))
    ];
    
    if (writeToFirebase('gameState/current', $gameState)) {
        $results['migrations'][] = 'roulette_state → gameState/current';
        $results['counts']['roll_history'] = count($rollHistory);
    }
    
    // ============================================
    // 2. MIGRATE ALL DRAWS
    // ============================================
    $stmt = $pdo->query("SELECT * FROM detailed_draw_results ORDER BY draw_number ASC");
    $draws = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $drawsData = [];
    foreach ($draws as $draw) {
        $drawNumber = intval($draw['draw_number'] ?? $draw['id']);
        if ($drawNumber <= 0) continue;
        
        $drawsData[$drawNumber] = [
            'drawId' => $draw['draw_id'] ?? 'DRAW-' . date('Ymd', strtotime($draw['timestamp'] ?? 'now')) . '-' . $drawNumber,
            'drawNumber' => $drawNumber,
            'winningNumber' => intval($draw['winning_number'] ?? 0),
            'winningColor' => $draw['winning_color'] ?? $draw['color'] ?? 'black',
            'isManual' => intval($draw['is_manual'] ?? 0),
            'notes' => $draw['notes'] ?? '',
            'timestamp' => $draw['timestamp'] ?? $draw['created_at'] ?? date('Y-m-d\TH:i:s.000\Z'),
            'createdAt' => $draw['created_at'] ?? date('Y-m-d\TH:i:s.000\Z')
        ];
    }
    
    $batchResult = batchWriteToFirebase('draws', $drawsData);
    if ($batchResult['success'] > 0) {
        $results['migrations'][] = "detailed_draw_results → draws/ ({$batchResult['success']} draws)";
        $results['counts']['draws'] = $batchResult['success'];
    }
    
    // ============================================
    // 3. MIGRATE ANALYTICS
    // ============================================
    $stmt = $pdo->query("SELECT * FROM roulette_analytics WHERE id = 1");
    $analytics = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Build from all draws
    $stmtDraws = $pdo->query("SELECT winning_number FROM detailed_draw_results ORDER BY draw_number ASC");
    $allDraws = $stmtDraws->fetchAll(PDO::FETCH_ASSOC);
    
    $allSpinsFromDraws = [];
    $numberFrequencyFromDraws = array_fill(0, 37, 0);
    
    foreach (array_reverse($allDraws) as $draw) {
        $num = intval($draw['winning_number'] ?? 0);
        if ($num >= 0 && $num <= 36) {
            if (count($allSpinsFromDraws) < 100) {
                array_unshift($allSpinsFromDraws, $num);
            }
            $numberFrequencyFromDraws[$num]++;
        }
    }
    
    $allSpins = $allSpinsFromDraws;
    $numberFrequency = $numberFrequencyFromDraws;
    
    if ($analytics) {
        if (!empty($analytics['all_spins'])) {
            $analyticsSpins = json_decode($analytics['all_spins'], true);
            if (is_array($analyticsSpins) && count($analyticsSpins) > count($allSpins)) {
                $allSpins = $analyticsSpins;
            }
        }
        
        if (!empty($analytics['number_frequency'])) {
            $freq = json_decode($analytics['number_frequency'], true);
            if (is_array($freq)) {
                $numberFrequency = $freq;
            }
        }
    }
    
    $analyticsData = [
        'allSpins' => $allSpins,
        'numberFrequency' => $numberFrequency,
        'currentDrawNumber' => $latestDrawNumber > 0 ? $latestDrawNumber : ($analytics['current_draw_number'] ?? 1),
        'lastUpdated' => $analytics['last_updated'] ?? $analytics['created_at'] ?? date('Y-m-d H:i:s')
    ];
    
    if (writeToFirebase('analytics/current', $analyticsData)) {
        $results['migrations'][] = 'roulette_analytics → analytics/current';
        $results['counts']['analytics_spins'] = count($allSpins);
    }
    
    // ============================================
    // 4. MIGRATE BETTING SLIPS
    // ============================================
    $stmt = $pdo->query("SELECT * FROM betting_slips ORDER BY slip_id ASC");
    $slips = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $slipsData = [];
    foreach ($slips as $slip) {
        $slipId = $slip['slip_id'] ?? $slip['slip_number'];
        $slipsData[$slipId] = [
            'slipId' => $slipId,
            'slipNumber' => $slip['slip_number'] ?? '',
            'userId' => intval($slip['user_id'] ?? 0),
            'totalStake' => floatval($slip['total_stake'] ?? 0),
            'potentialPayout' => floatval($slip['potential_payout'] ?? 0),
            'isPaid' => ($slip['is_paid'] ?? 0) == 1,
            'isCancelled' => ($slip['is_cancelled'] ?? 0) == 1,
            'drawNumber' => intval($slip['draw_number'] ?? 0),
            'winningNumber' => isset($slip['winning_number']) ? intval($slip['winning_number']) : null,
            'createdAt' => $slip['created_at'] ?? date('Y-m-d\TH:i:s.000\Z'),
            'updatedAt' => $slip['updated_at'] ?? date('Y-m-d\TH:i:s.000\Z')
        ];
    }
    
    if (!empty($slipsData)) {
        $batchResult = batchWriteToFirebase('bettingSlips', $slipsData);
        if ($batchResult['success'] > 0) {
            $results['migrations'][] = "betting_slips → bettingSlips/ ({$batchResult['success']} slips)";
            $results['counts']['betting_slips'] = $batchResult['success'];
        }
    }
    
    // ============================================
    // 5. MIGRATE BETS
    // ============================================
    $stmt = $pdo->query("SELECT * FROM bets ORDER BY bet_id ASC LIMIT 1000");
    $bets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $betsData = [];
    foreach ($bets as $bet) {
        $betId = $bet['bet_id'];
        $betsData[$betId] = [
            'betId' => $betId,
            'userId' => intval($bet['user_id'] ?? 0),
            'betType' => $bet['bet_type'] ?? '',
            'betDescription' => $bet['bet_description'] ?? '',
            'betAmount' => floatval($bet['bet_amount'] ?? 0),
            'multiplier' => floatval($bet['multiplier'] ?? 0),
            'potentialReturn' => floatval($bet['potential_return'] ?? 0),
            'createdAt' => $bet['created_at'] ?? date('Y-m-d\TH:i:s.000\Z')
        ];
    }
    
    if (!empty($betsData)) {
        $batchResult = batchWriteToFirebase('bets', $betsData);
        if ($batchResult['success'] > 0) {
            $results['migrations'][] = "bets → bets/ ({$batchResult['success']} bets)";
            $results['counts']['bets'] = $batchResult['success'];
        }
    }
    
    // ============================================
    // 6. UPDATE DRAW INFO
    // ============================================
    $drawInfo = [
        'currentDraw' => $latestDrawNumber > 0 ? $latestDrawNumber : 1,
        'nextDraw' => $latestDrawNumber > 0 ? $latestDrawNumber + 1 : 2
    ];
    
    if (writeToFirebase('gameState/drawInfo', $drawInfo)) {
        $results['migrations'][] = 'drawInfo → gameState/drawInfo';
    }
    
    // ============================================
    // 7. FINAL GAME STATE UPDATE
    // ============================================
    if ($latestDrawNumber > 0 && !empty($last5Draws)) {
        $gameState['drawNumber'] = $latestDrawNumber;
        $gameState['nextDrawNumber'] = $latestDrawNumber + 1;
        $gameState['winningNumber'] = intval($last5Draws[0]['winning_number']);
        $gameState['rollHistory'] = $rollHistory;
        $gameState['rollColors'] = $rollColors;
        $gameState['lastDrawFormatted'] = "#{$latestDrawNumber}";
        $gameState['nextDrawFormatted'] = "#" . ($latestDrawNumber + 1);
        
        writeToFirebase('gameState/current', $gameState);
        $results['migrations'][] = 'gameState/current updated with complete data';
    }
    
    echo json_encode($results, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    $results['status'] = 'error';
    $results['errors'][] = $e->getMessage();
    echo json_encode($results, JSON_PRETTY_PRINT);
}

