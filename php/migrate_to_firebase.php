<?php
/**
 * Migrate MySQL Database to Firebase Realtime Database
 * 
 * This script migrates all data from MySQL to Firebase
 */

require_once __DIR__ . '/db_connect.php';

// Firebase Admin SDK (we'll use REST API instead)
$firebaseUrl = 'https://roulette-2f902-default-rtdb.firebaseio.com';
$firebaseApiKey = 'AIzaSyA0ieIqlj931McUnu1CeYzkN4s5MwOm2u4';

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
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'data' => json_decode($response, true)];
    } else {
        return ['success' => false, 'error' => $response, 'code' => $httpCode];
    }
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
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'data' => json_decode($response, true)];
    } else {
        return ['success' => false, 'error' => $response, 'code' => $httpCode];
    }
}

$results = [
    'status' => 'success',
    'migrations' => [],
    'errors' => []
];

try {
    // 1. Migrate roulette_state
    $stmt = $pdo->query("SELECT * FROM roulette_state ORDER BY id DESC LIMIT 1");
    $state = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($state) {
        $gameState = [
            'drawNumber' => $state['draw_number'] ?? $state['current_draw'] ?? 1,
            'nextDrawNumber' => $state['next_draw_number'] ?? $state['next_draw'] ?? 2,
            'winningNumber' => $state['winning_number'] ?? null,
            'nextWinningNumber' => $state['next_winning_number'] ?? null,
            'manualMode' => $state['manual_mode'] ?? false,
            'updatedAt' => ($state['updated_at'] ?? date('Y-m-d H:i:s'))
        ];
        
        // Parse additional_data if it exists
        if (!empty($state['additional_data'])) {
            $additionalData = json_decode($state['additional_data'], true);
            if ($additionalData) {
                if (isset($additionalData['roll_history'])) {
                    $rollHistory = explode(',', $additionalData['roll_history']);
                    $gameState['rollHistory'] = array_filter(array_map('intval', $rollHistory));
                }
                if (isset($additionalData['roll_colors'])) {
                    $rollColors = explode(',', $additionalData['roll_colors']);
                    $gameState['rollColors'] = array_filter($rollColors);
                }
                if (isset($additionalData['last_draw_formatted'])) {
                    $gameState['lastDrawFormatted'] = $additionalData['last_draw_formatted'];
                }
                if (isset($additionalData['next_draw_formatted'])) {
                    $gameState['nextDrawFormatted'] = $additionalData['next_draw_formatted'];
                }
            }
        }
        
        $result = writeToFirebase('gameState/current', $gameState);
        if ($result['success']) {
            $results['migrations'][] = 'roulette_state → gameState/current';
        } else {
            $results['errors'][] = 'roulette_state: ' . json_encode($result);
        }
    }
    
    // 2. Migrate roulette_analytics
    $stmt = $pdo->query("SELECT * FROM roulette_analytics WHERE id = 1");
    $analytics = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($analytics) {
        $allSpins = json_decode($analytics['all_spins'] ?? '[]', true) ?: [];
        $numberFrequency = json_decode($analytics['number_frequency'] ?? '[]', true) ?: [];
        
        $analyticsData = [
            'allSpins' => $allSpins,
            'numberFrequency' => $numberFrequency,
            'currentDrawNumber' => $analytics['current_draw_number'] ?? 1,
            'lastUpdated' => $analytics['last_updated'] ?? date('Y-m-d H:i:s')
        ];
        
        $result = writeToFirebase('analytics/current', $analyticsData);
        if ($result['success']) {
            $results['migrations'][] = 'roulette_analytics → analytics/current';
        } else {
            $results['errors'][] = 'roulette_analytics: ' . json_encode($result);
        }
    }
    
    // 3. Migrate roulette_draws / detailed_draw_results
    if (php_sapi_name() === 'cli') {
        echo "Migrating draw results...\n";
    }
    $stmt = $pdo->query("SELECT * FROM detailed_draw_results ORDER BY id DESC LIMIT 100");
    $draws = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $drawsMigrated = 0;
    foreach ($draws as $draw) {
        $drawNumber = $draw['draw_number'] ?? $draw['id'];
        $drawData = [
            'drawId' => $draw['draw_id'] ?? 'DRAW-' . $drawNumber,
            'drawNumber' => intval($drawNumber),
            'winningNumber' => intval($draw['winning_number'] ?? $draw['winning_number']),
            'winningColor' => $draw['winning_color'] ?? $draw['color'] ?? 'black',
            'isManual' => $draw['is_manual'] ?? 0,
            'notes' => $draw['notes'] ?? '',
            'timestamp' => $draw['timestamp'] ?? $draw['created_at'] ?? date('Y-m-d H:i:s'),
            'createdAt' => $draw['created_at'] ?? date('Y-m-d H:i:s')
        ];
        
        $result = writeToFirebase('draws/' . $drawNumber, $drawData);
        if ($result['success']) {
            $drawsMigrated++;
        } else {
            $results['errors'][] = "draws/{$drawNumber}: " . json_encode($result);
        }
    }
    
    if ($drawsMigrated > 0) {
        $results['migrations'][] = "detailed_draw_results → draws/ ({$drawsMigrated} draws)";
    }
    
    // 4. Update drawInfo in gameState
    if ($state) {
        $drawInfo = [
            'currentDraw' => $gameState['drawNumber'] ?? 1,
            'nextDraw' => $gameState['nextDrawNumber'] ?? 2
        ];
        
        $result = writeToFirebase('gameState/drawInfo', $drawInfo);
        if ($result['success']) {
            $results['migrations'][] = 'drawInfo → gameState/drawInfo';
        }
    }
    
    // Only output JSON
    echo json_encode($results, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    $results['status'] = 'error';
    $results['errors'][] = $e->getMessage();
    echo json_encode($results, JSON_PRETTY_PRINT);
}

