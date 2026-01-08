<?php
/**
 * Migrate from SQL File to Firebase Realtime Database
 * 
 * This script reads the SQL file and migrates data to Firebase
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
 * Update data in Firebase
 */
function updateFirebase($path, $data) {
    global $firebaseUrl;
    
    $url = $firebaseUrl . '/' . $path . '.json';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode >= 200 && $httpCode < 300;
}

$results = [
    'status' => 'success',
    'migrations' => [],
    'errors' => [],
    'counts' => []
];

try {
    // Get latest draw number first (we'll use this for game state)
    $stmtLatest = $pdo->query("SELECT MAX(draw_number) as max_draw FROM detailed_draw_results");
    $latest = $stmtLatest->fetch(PDO::FETCH_ASSOC);
    $latestDrawNumber = intval($latest['max_draw'] ?? 0);
    
    // Get the last 5 draws for roll history
    $stmtLast5 = $pdo->query("SELECT * FROM detailed_draw_results ORDER BY draw_number DESC LIMIT 5");
    $last5Draws = $stmtLast5->fetchAll(PDO::FETCH_ASSOC);
    
    // Build roll history from last 5 draws (most recent first)
    $rollHistory = [];
    $rollColors = [];
    foreach (array_reverse($last5Draws) as $draw) {
        $rollHistory[] = intval($draw['winning_number'] ?? 0);
        $rollColors[] = $draw['winning_color'] ?? $draw['color'] ?? 'black';
    }
    
    // 1. Migrate roulette_state (get latest, but we'll update it with real data)
    $stmt = $pdo->query("SELECT * FROM roulette_state ORDER BY id DESC LIMIT 1");
    $state = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Build game state from actual data
    $gameState = [
        'drawNumber' => $latestDrawNumber > 0 ? $latestDrawNumber : ($state['draw_number'] ?? 1),
        'nextDrawNumber' => $latestDrawNumber > 0 ? $latestDrawNumber + 1 : ($state['next_draw_number'] ?? 2),
        'winningNumber' => !empty($last5Draws) ? intval($last5Draws[0]['winning_number'] ?? 0) : ($state['winning_number'] ?? null),
        'nextWinningNumber' => $state['next_winning_number'] ?? null,
        'manualMode' => ($state['manual_mode'] ?? 0) == 1,
        'rollHistory' => $rollHistory,
        'rollColors' => $rollColors,
        'lastDrawFormatted' => $latestDrawNumber > 0 ? "#{$latestDrawNumber}" : ($state['additional_data'] ? json_decode($state['additional_data'], true)['last_draw_formatted'] ?? "#0" : "#0"),
        'nextDrawFormatted' => $latestDrawNumber > 0 ? "#" . ($latestDrawNumber + 1) : ($state['additional_data'] ? json_decode($state['additional_data'], true)['next_draw_formatted'] ?? "#1" : "#1"),
        'updatedAt' => ($state['updated_at'] ?? $state['created_at'] ?? date('Y-m-d\TH:i:s.000\Z'))
    ];
    
    if (writeToFirebase('gameState/current', $gameState)) {
        $results['migrations'][] = 'roulette_state → gameState/current (with roll history)';
        $results['counts']['roll_history'] = count($rollHistory);
    } else {
        $results['errors'][] = 'Failed to write roulette_state';
    }
    
    // 2. Migrate roulette_analytics (build from ALL actual draws)
    $stmt = $pdo->query("SELECT * FROM roulette_analytics WHERE id = 1");
    $analytics = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get ALL draws to build complete analytics
    $stmtDraws = $pdo->query("SELECT winning_number FROM detailed_draw_results ORDER BY draw_number ASC");
    $allDraws = $stmtDraws->fetchAll(PDO::FETCH_ASSOC);
    
    // Build allSpins from ALL actual draws (most recent first, limit 100)
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
    
    // Prefer analytics table data if it exists and is valid
    $allSpins = $allSpinsFromDraws;
    $numberFrequency = $numberFrequencyFromDraws;
    
    if ($analytics) {
        // Use analytics table data if it's valid JSON and has data
        if (!empty($analytics['all_spins'])) {
            $analyticsSpins = json_decode($analytics['all_spins'], true);
            if (is_array($analyticsSpins) && count($analyticsSpins) > 0) {
                // Use analytics data if it has more spins or if our data is empty
                if (count($analyticsSpins) >= count($allSpins) || count($allSpins) == 0) {
                    $allSpins = $analyticsSpins;
                }
            }
        }
        
        if (!empty($analytics['number_frequency'])) {
            $freq = json_decode($analytics['number_frequency'], true);
            if (is_array($freq) && count(array_filter($freq)) > 0) {
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
        $results['counts']['total_draws_in_db'] = count($allDraws);
    } else {
        $results['errors'][] = 'Failed to write roulette_analytics';
    }
    
    // 3. Migrate detailed_draw_results (ALL draws, no limit)
    $stmt = $pdo->query("SELECT * FROM detailed_draw_results ORDER BY draw_number ASC");
    $draws = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $drawsMigrated = 0;
    
    // Migrate all draws to Firebase
    foreach ($draws as $draw) {
        $drawNumber = intval($draw['draw_number'] ?? $draw['id']);
        if ($drawNumber <= 0) continue;
        
        $winningNumber = intval($draw['winning_number'] ?? 0);
        $winningColor = $draw['winning_color'] ?? $draw['color'] ?? 'black';
        
        $drawData = [
            'drawId' => $draw['draw_id'] ?? 'DRAW-' . date('Ymd', strtotime($draw['timestamp'] ?? 'now')) . '-' . $drawNumber,
            'drawNumber' => $drawNumber,
            'winningNumber' => $winningNumber,
            'winningColor' => $winningColor,
            'isManual' => intval($draw['is_manual'] ?? 0),
            'notes' => $draw['notes'] ?? '',
            'timestamp' => $draw['timestamp'] ?? $draw['created_at'] ?? date('Y-m-d\TH:i:s.000\Z'),
            'createdAt' => $draw['created_at'] ?? date('Y-m-d\TH:i:s.000\Z')
        ];
        
        if (writeToFirebase('draws/' . $drawNumber, $drawData)) {
            $drawsMigrated++;
        } else {
            $results['errors'][] = "Failed to write draw {$drawNumber}";
        }
    }
    
    if ($drawsMigrated > 0) {
        $results['migrations'][] = "detailed_draw_results → draws/ ({$drawsMigrated} draws)";
        $results['counts']['draws'] = $drawsMigrated;
    }
    
    // 4. Update drawInfo with latest draw number
    $drawInfo = [
        'currentDraw' => $latestDrawNumber > 0 ? $latestDrawNumber : 1,
        'nextDraw' => $latestDrawNumber > 0 ? $latestDrawNumber + 1 : 2
    ];
    
    if (writeToFirebase('gameState/drawInfo', $drawInfo)) {
        $results['migrations'][] = 'drawInfo → gameState/drawInfo';
        $results['counts']['latest_draw'] = $latestDrawNumber;
    }
    
    // 5. Final update of gameState with complete data (roll history already set above)
    if ($drawsMigrated > 0 && $latestDrawNumber > 0) {
        // Get latest draw details
        $stmtLatestDraw = $pdo->query("SELECT * FROM detailed_draw_results WHERE draw_number = {$latestDrawNumber} LIMIT 1");
        $latestDrawData = $stmtLatestDraw->fetch(PDO::FETCH_ASSOC);
        
        if ($latestDrawData) {
            $gameState['drawNumber'] = $latestDrawNumber;
            $gameState['nextDrawNumber'] = $latestDrawNumber + 1;
            $gameState['winningNumber'] = intval($latestDrawData['winning_number']);
            $gameState['rollHistory'] = $rollHistory; // Already built above
            $gameState['rollColors'] = $rollColors; // Already built above
            $gameState['lastDrawFormatted'] = "#{$latestDrawNumber}";
            $gameState['nextDrawFormatted'] = "#" . ($latestDrawNumber + 1);
            
            // Final write with complete data
            writeToFirebase('gameState/current', $gameState);
            $results['migrations'][] = 'gameState/current updated with complete data';
        }
    }
    
    // Only output JSON, no echo statements
    echo json_encode($results, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    $results['status'] = 'error';
    $results['errors'][] = $e->getMessage();
    echo json_encode($results, JSON_PRETTY_PRINT);
}

