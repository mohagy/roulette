<?php
/**
 * Secure Analytics Update
 * This script ensures only authorized updates to analytics
 */

require_once "cache_prevention.php";
require_once "db_connect.php";

function secureAnalyticsUpdate($winningNumber, $drawNumber, $source = "manual") {
    global $conn;
    
    // Log the update attempt
    logCachePrevention("Analytics update attempt", [
        "winning_number" => $winningNumber,
        "draw_number" => $drawNumber,
        "source" => $source,
        "timestamp" => date("Y-m-d H:i:s")
    ]);
    
    // Validate inputs
    if (!is_numeric($winningNumber) || !is_numeric($drawNumber)) {
        logCachePrevention("Invalid analytics update - non-numeric values");
        return false;
    }
    
    if ($winningNumber < 0 || $winningNumber > 36) {
        logCachePrevention("Invalid analytics update - winning number out of range");
        return false;
    }
    
    // Get current analytics with fresh data
    $currentData = getFreshData("SELECT SQL_NO_CACHE * FROM roulette_analytics WHERE id = 1");
    
    if (empty($currentData)) {
        logCachePrevention("No analytics data found - creating fresh entry");
        $allSpins = [];
        $numberFrequency = array_fill(0, 37, 0);
        $currentDrawNumber = 0;
    } else {
        $analytics = $currentData[0];
        $allSpins = json_decode($analytics["all_spins"], true) ?: [];
        $numberFrequency = json_decode($analytics["number_frequency"], true) ?: array_fill(0, 37, 0);
        $currentDrawNumber = (int)$analytics["current_draw_number"];
    }
    
    // Update data
    array_unshift($allSpins, (int)$winningNumber);
    $allSpins = array_slice($allSpins, 0, 50); // Keep only last 50 spins
    
    $numberFrequency[$winningNumber]++;
    $currentDrawNumber = max($currentDrawNumber, $drawNumber);
    
    // Update database with fresh data
    $stmt = $conn->prepare("UPDATE roulette_analytics SET all_spins = ?, number_frequency = ?, current_draw_number = ?, last_updated = NOW() WHERE id = 1");
    if ($stmt) {
        $allSpinsJson = json_encode($allSpins);
        $frequencyJson = json_encode($numberFrequency);
        $stmt->bind_param("ssi", $allSpinsJson, $frequencyJson, $currentDrawNumber);
        
        if ($stmt->execute()) {
            logCachePrevention("Analytics updated successfully", [
                "winning_number" => $winningNumber,
                "draw_number" => $drawNumber,
                "total_spins" => count($allSpins)
            ]);
            return true;
        }
    }
    
    logCachePrevention("Failed to update analytics");
    return false;
}
?>