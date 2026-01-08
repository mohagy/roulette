<?php
/**
 * Secure Dual Storage API
 * 
 * This API saves spin data to both roulette_analytics and detailed_draw_results
 * tables while maintaining security against phantom data generation.
 */

// Initialize comprehensive cache prevention
require_once "cache_prevention.php";

// Include database connection
require_once "db_connect.php";

// Set response header to JSON
header("Content-Type: application/json");

// Log the request
logCachePrevention("Dual storage API called", [
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

try {
    // Start transaction for atomic operation
    $conn->autocommit(false);
    
    logCachePrevention("Starting dual storage transaction", [
        "winning_number" => $winningNumber,
        "draw_number" => $drawNumber,
        "timestamp" => $timestamp
    ]);
    
    // Step 1: Get current analytics data using fresh query
    $currentAnalytics = getFreshData("SELECT * FROM roulette_analytics WHERE id = 1");
    
    if (empty($currentAnalytics)) {
        // Initialize analytics if not exists
        $allSpins = [];
        $numberFrequency = array_fill(0, 37, 0);
        $currentDrawNumber = 0;
    } else {
        $analytics = $currentAnalytics[0];
        $allSpins = json_decode($analytics["all_spins"], true) ?: [];
        $numberFrequency = json_decode($analytics["number_frequency"], true) ?: array_fill(0, 37, 0);
        $currentDrawNumber = (int)$analytics["current_draw_number"];
    }
    
    // Step 2: Update analytics data
    array_unshift($allSpins, $winningNumber);
    $allSpins = array_slice($allSpins, 0, 100); // Keep only last 100 spins
    
    $numberFrequency[$winningNumber]++;
    $newDrawNumber = max($currentDrawNumber, $drawNumber);
    
    // Step 3: Save to roulette_analytics table
    $allSpinsJson = json_encode($allSpins);
    $frequencyJson = json_encode($numberFrequency);
    
    if (empty($currentAnalytics)) {
        // Insert new record
        $stmt = $conn->prepare("INSERT INTO roulette_analytics (id, all_spins, number_frequency, current_draw_number, last_updated, created_at) VALUES (1, ?, ?, ?, NOW(), NOW())");
        $stmt->bind_param("ssi", $allSpinsJson, $frequencyJson, $newDrawNumber);
    } else {
        // Update existing record
        $stmt = $conn->prepare("UPDATE roulette_analytics SET all_spins = ?, number_frequency = ?, current_draw_number = ?, last_updated = NOW() WHERE id = 1");
        $stmt->bind_param("ssi", $allSpinsJson, $frequencyJson, $newDrawNumber);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update roulette_analytics: " . $stmt->error);
    }
    
    logCachePrevention("Updated roulette_analytics table", [
        "spins_count" => count($allSpins),
        "draw_number" => $newDrawNumber
    ]);
    
    // Step 4: Save to detailed_draw_results table
    $stmt2 = $conn->prepare("INSERT INTO detailed_draw_results (draw_number, winning_number, color, timestamp) VALUES (?, ?, get_roulette_color(?), ?)");
    $stmt2->bind_param("iiis", $drawNumber, $winningNumber, $winningNumber, $timestamp);
    
    if (!$stmt2->execute()) {
        throw new Exception("Failed to insert into detailed_draw_results: " . $stmt2->error);
    }
    
    $detailedResultId = $conn->insert_id;
    
    logCachePrevention("Inserted into detailed_draw_results table", [
        "id" => $detailedResultId,
        "draw_number" => $drawNumber,
        "winning_number" => $winningNumber
    ]);
    
    // Commit transaction
    $conn->commit();
    
    // Get the color for response
    $colorResult = $conn->query("SELECT get_roulette_color($winningNumber) as color");
    $color = $colorResult ? $colorResult->fetch_assoc()["color"] : "unknown";
    
    logCachePrevention("Dual storage transaction completed successfully", [
        "analytics_updated" => true,
        "detailed_record_id" => $detailedResultId,
        "total_spins" => count($allSpins)
    ]);
    
    // Return success response
    echo json_encode([
        "status" => "success",
        "message" => "Spin data saved to both tables successfully",
        "data" => [
            "draw_number" => $drawNumber,
            "winning_number" => $winningNumber,
            "color" => $color,
            "detailed_record_id" => $detailedResultId,
            "total_spins_recorded" => count($allSpins),
            "timestamp" => $timestamp
        ],
        "timestamp" => date("Y-m-d H:i:s")
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    logCachePrevention("Dual storage transaction failed", [
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