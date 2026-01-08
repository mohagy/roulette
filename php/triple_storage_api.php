<?php
/**
 * Triple Storage API
 * 
 * Enhanced storage API that saves spin data to all three tables:
 * - roulette_analytics (aggregate data)
 * - detailed_draw_results (individual records)
 * - roulette_draws (complete draw information with betting data)
 */

// Initialize comprehensive cache prevention
require_once "cache_prevention.php";

// Include database connection
require_once "db_connect.php";

// Set response header to JSON
header("Content-Type: application/json");

// Log the request
logCachePrevention("Triple storage API called", [
    "method" => $_SERVER["REQUEST_METHOD"],
    "user_agent" => $_SERVER["HTTP_USER_AGENT"] ?? "unknown",
    "timestamp" => date("Y-m-d H:i:s")
]);

// Only allow POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "Only POST requests allowed",
        "timestamp" => date("Y-m-d H:i:s")
    ]);
    exit;
}

// Get JSON input
$input = file_get_contents("php://input");
$data = json_decode($input, true);

// Validate input data
if (!$data || !isset($data["winning_number"]) || !isset($data["draw_number"])) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Missing required fields: winning_number and draw_number",
        "timestamp" => date("Y-m-d H:i:s")
    ]);
    exit;
}

$winningNumber = (int)$data["winning_number"];
$drawNumber = (int)$data["draw_number"];
$timestamp = $data["timestamp"] ?? date("Y-m-d H:i:s");
$isManual = isset($data["is_manual"]) ? (bool)$data["is_manual"] : false;
$totalBets = isset($data["total_bets"]) ? (int)$data["total_bets"] : 0;
$totalStake = isset($data["total_stake"]) ? (float)$data["total_stake"] : 0.00;
$totalPayout = isset($data["total_payout"]) ? (float)$data["total_payout"] : 0.00;

// Validate winning number range
if ($winningNumber < 0 || $winningNumber > 36) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid winning number. Must be between 0 and 36",
        "timestamp" => date("Y-m-d H:i:s")
    ]);
    exit;
}

// Validate draw number
if ($drawNumber < 1) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid draw number. Must be greater than 0",
        "timestamp" => date("Y-m-d H:i:s")
    ]);
    exit;
}

// Determine color
function getRouletteColor($number) {
    if ($number === 0) {
        return "green";
    } elseif (in_array($number, [1,3,5,7,9,12,14,16,18,19,21,23,25,27,30,32,34,36])) {
        return "red";
    } else {
        return "black";
    }
}

$winningColor = getRouletteColor($winningNumber);

try {
    // Start transaction for atomic operation across all three tables
    $conn->autocommit(false);
    
    logCachePrevention("Starting triple storage transaction", [
        "winning_number" => $winningNumber,
        "draw_number" => $drawNumber,
        "winning_color" => $winningColor,
        "is_manual" => $isManual,
        "timestamp" => $timestamp
    ]);
    
    // Step 1: Update roulette_analytics table
    $currentAnalytics = getFreshData("SELECT * FROM roulette_analytics WHERE id = 1");
    
    if (empty($currentAnalytics)) {
        $allSpins = [];
        $numberFrequency = array_fill(0, 37, 0);
        $currentDrawNumber = 0;
    } else {
        $analytics = $currentAnalytics[0];
        $allSpins = json_decode($analytics["all_spins"], true) ?: [];
        $numberFrequency = json_decode($analytics["number_frequency"], true) ?: array_fill(0, 37, 0);
        $currentDrawNumber = (int)$analytics["current_draw_number"];
    }
    
    array_unshift($allSpins, $winningNumber);
    $allSpins = array_slice($allSpins, 0, 100);
    $numberFrequency[$winningNumber]++;
    $newDrawNumber = max($currentDrawNumber, $drawNumber);
    
    $allSpinsJson = json_encode($allSpins);
    $frequencyJson = json_encode($numberFrequency);
    
    if (empty($currentAnalytics)) {
        $stmt1 = $conn->prepare("INSERT INTO roulette_analytics (id, all_spins, number_frequency, current_draw_number, last_updated, created_at) VALUES (1, ?, ?, ?, NOW(), NOW())");
        $stmt1->bind_param("ssi", $allSpinsJson, $frequencyJson, $newDrawNumber);
    } else {
        $stmt1 = $conn->prepare("UPDATE roulette_analytics SET all_spins = ?, number_frequency = ?, current_draw_number = ?, last_updated = NOW() WHERE id = 1");
        $stmt1->bind_param("ssi", $allSpinsJson, $frequencyJson, $newDrawNumber);
    }
    
    if (!$stmt1->execute()) {
        throw new Exception("Failed to update roulette_analytics: " . $stmt1->error);
    }
    
    // Step 2: Insert into detailed_draw_results table
    $stmt2 = $conn->prepare("INSERT INTO detailed_draw_results (draw_number, winning_number, color, timestamp) VALUES (?, ?, ?, ?)");
    $stmt2->bind_param("iiss", $drawNumber, $winningNumber, $winningColor, $timestamp);
    
    if (!$stmt2->execute()) {
        throw new Exception("Failed to insert into detailed_draw_results: " . $stmt2->error);
    }
    
    $detailedResultId = $conn->insert_id;
    
    // Step 3: Insert into roulette_draws table
    $stmt3 = $conn->prepare("INSERT INTO roulette_draws (draw_number, winning_number, winning_color, draw_time, is_manual, total_bets, total_stake, total_payout) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt3->bind_param("iissiidd", $drawNumber, $winningNumber, $winningColor, $timestamp, $isManual, $totalBets, $totalStake, $totalPayout);
    
    if (!$stmt3->execute()) {
        throw new Exception("Failed to insert into roulette_draws: " . $stmt3->error);
    }
    
    $rouletteDrawId = $conn->insert_id;
    
    // Commit transaction
    $conn->commit();
    
    logCachePrevention("Triple storage transaction completed successfully", [
        "analytics_updated" => true,
        "detailed_record_id" => $detailedResultId,
        "roulette_draw_id" => $rouletteDrawId,
        "total_spins" => count($allSpins)
    ]);
    
    // Return success response
    echo json_encode([
        "status" => "success",
        "message" => "Spin data saved to all three tables successfully",
        "data" => [
            "draw_number" => $drawNumber,
            "winning_number" => $winningNumber,
            "winning_color" => $winningColor,
            "is_manual" => $isManual,
            "detailed_record_id" => $detailedResultId,
            "roulette_draw_id" => $rouletteDrawId,
            "total_spins_recorded" => count($allSpins),
            "timestamp" => $timestamp,
            "betting_data" => [
                "total_bets" => $totalBets,
                "total_stake" => $totalStake,
                "total_payout" => $totalPayout
            ]
        ],
        "timestamp" => date("Y-m-d H:i:s")
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    logCachePrevention("Triple storage transaction failed", [
        "error" => $e->getMessage(),
        "winning_number" => $winningNumber,
        "draw_number" => $drawNumber
    ]);
    
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to save spin data: " . $e->getMessage(),
        "timestamp" => date("Y-m-d H:i:s")
    ]);
} finally {
    // Restore autocommit
    $conn->autocommit(true);
}
?>