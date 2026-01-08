<?php
/**
 * Firebase Helper Functions for PHP
 * 
 * Provides PHP functions to write to Firebase Realtime Database
 */

$firebaseUrl = 'https://roulette-2f902-default-rtdb.firebaseio.com';

/**
 * Write data to Firebase
 */
function firebaseWrite($path, $data) {
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
function firebaseUpdate($path, $data) {
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

/**
 * Save draw result to Firebase
 */
function firebaseSaveDrawResult($drawNumber, $winningNumber, $winningColor, $isForced = false, $source = 'php') {
    $timestamp = date('Y-m-d\TH:i:s.000\Z');
    $drawId = 'DRAW-' . date('Ymd') . '-' . $drawNumber;
    
    $drawData = [
        'drawId' => $drawId,
        'drawNumber' => intval($drawNumber),
        'winningNumber' => intval($winningNumber),
        'winningColor' => $winningColor,
        'isManual' => $isForced ? 1 : 0,
        'source' => $source,
        'notes' => $isForced ? "Forced number set by {$source}" : 'Random number',
        'timestamp' => $timestamp,
        'createdAt' => $timestamp
    ];
    
    // Save draw result
    $success1 = firebaseWrite("draws/{$drawNumber}", $drawData);
    
    // Update game state
    $gameState = [
        'drawNumber' => intval($drawNumber),
        'nextDrawNumber' => intval($drawNumber) + 1,
        'winningNumber' => intval($winningNumber),
        'lastDrawFormatted' => "#{$drawNumber}",
        'nextDrawFormatted' => "#" . (intval($drawNumber) + 1),
        'updatedAt' => $timestamp
    ];
    
    $success2 = firebaseUpdate('gameState/current', $gameState);
    
    // Update drawInfo
    $drawInfo = [
        'currentDraw' => intval($drawNumber),
        'nextDraw' => intval($drawNumber) + 1
    ];
    
    $success3 = firebaseWrite('gameState/drawInfo', $drawInfo);
    
    return $success1 && $success2 && $success3;
}

/**
 * Update analytics in Firebase
 */
function firebaseUpdateAnalytics($winningNumber, $drawNumber) {
    // Get current analytics
    $url = 'https://roulette-2f902-default-rtdb.firebaseio.com/analytics/current.json';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $analytics = json_decode($response, true) ?: [];
    
    // Update analytics
    $allSpins = $analytics['allSpins'] ?? [];
    array_unshift($allSpins, intval($winningNumber));
    $allSpins = array_slice($allSpins, 0, 100);
    
    $numberFrequency = $analytics['numberFrequency'] ?? array_fill(0, 37, 0);
    $numberFrequency[intval($winningNumber)] = ($numberFrequency[intval($winningNumber)] ?? 0) + 1;
    
    $analyticsData = [
        'allSpins' => $allSpins,
        'numberFrequency' => $numberFrequency,
        'currentDrawNumber' => intval($drawNumber),
        'lastUpdated' => date('Y-m-d H:i:s')
    ];
    
    return firebaseWrite('analytics/current', $analyticsData);
}

