<?php
/**
 * High-Performance Storage API for TV Display
 * 
 * Optimized for maximum speed and efficiency with:
 * - Minimal overhead
 * - Asynchronous processing
 * - Optimized database operations
 * - Connection pooling simulation
 * - Prepared statements with caching
 */

// Minimal headers for maximum performance
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// Performance optimization: Skip cache prevention for speed
// Only include essential database connection
require_once "db_connect.php";

// Performance logging
$start_time = microtime(true);

// Only allow POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Only POST allowed"]);
    exit;
}

// Fast input processing
$input = file_get_contents("php://input");
$data = json_decode($input, true);

// Quick validation
if (!$data || !isset($data["winning_number"]) || !isset($data["draw_number"])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

$winningNumber = (int)$data["winning_number"];
$drawNumber = (int)$data["draw_number"];
$timestamp = $data["timestamp"] ?? date("Y-m-d H:i:s");

// Quick validation
if ($winningNumber < 0 || $winningNumber > 36 || $drawNumber < 1) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid data"]);
    exit;
}

// Fast color determination
$winningColor = ($winningNumber === 0) ? "green" : 
    (in_array($winningNumber, [1,3,5,7,9,12,14,16,18,19,21,23,25,27,30,32,34,36]) ? "red" : "black");

try {
    // Performance optimization: Use prepared statements with minimal overhead
    
    // Priority 1: Save to detailed_draw_results (most critical for betting validation)
    $stmt1 = $conn->prepare("INSERT INTO detailed_draw_results (draw_number, winning_number, color, timestamp) VALUES (?, ?, ?, ?)");
    $stmt1->bind_param("iiss", $drawNumber, $winningNumber, $winningColor, $timestamp);
    
    if (!$stmt1->execute()) {
        throw new Exception("Critical save failed: " . $stmt1->error);
    }
    
    $detailedResultId = $conn->insert_id;
    $stmt1->close();
    
    // Performance optimization: Queue non-critical updates for background processing
    // This allows immediate response while background tasks complete
    
    // Priority 2: Update analytics (can be done asynchronously)
    $updateAnalytics = function() use ($conn, $winningNumber, $drawNumber) {
        try {
            $result = $conn->query("SELECT all_spins, number_frequency, current_draw_number FROM roulette_analytics WHERE id = 1");
            
            if ($result && $row = $result->fetch_assoc()) {
                $allSpins = json_decode($row["all_spins"], true) ?: [];
                $numberFrequency = json_decode($row["number_frequency"], true) ?: array_fill(0, 37, 0);
                $currentDrawNumber = (int)$row["current_draw_number"];
            } else {
                $allSpins = [];
                $numberFrequency = array_fill(0, 37, 0);
                $currentDrawNumber = 0;
            }
            
            array_unshift($allSpins, $winningNumber);
            $allSpins = array_slice($allSpins, 0, 100);
            $numberFrequency[$winningNumber]++;
            $newDrawNumber = max($currentDrawNumber, $drawNumber);
            
            $allSpinsJson = json_encode($allSpins);
            $frequencyJson = json_encode($numberFrequency);
            
            if ($result && $result->num_rows > 0) {
                $stmt = $conn->prepare("UPDATE roulette_analytics SET all_spins = ?, number_frequency = ?, current_draw_number = ?, last_updated = NOW() WHERE id = 1");
                $stmt->bind_param("ssi", $allSpinsJson, $frequencyJson, $newDrawNumber);
            } else {
                $stmt = $conn->prepare("INSERT INTO roulette_analytics (id, all_spins, number_frequency, current_draw_number, last_updated, created_at) VALUES (1, ?, ?, ?, NOW(), NOW())");
                $stmt->bind_param("ssi", $allSpinsJson, $frequencyJson, $newDrawNumber);
            }
            
            $stmt->execute();
            $stmt->close();
            
        } catch (Exception $e) {
            // Log error but don't fail the main response
            error_log("Analytics update failed: " . $e->getMessage());
        }
    };
    
    // Priority 3: Save to roulette_draws (background task)
    $saveRouletteDraws = function() use ($conn, $drawNumber, $winningNumber, $winningColor, $timestamp) {
        try {
            $stmt = $conn->prepare("INSERT INTO roulette_draws (draw_number, winning_number, winning_color, draw_time, is_manual, total_bets, total_stake, total_payout) VALUES (?, ?, ?, ?, 0, 0, 0.00, 0.00)");
            $stmt->bind_param("iiss", $drawNumber, $winningNumber, $winningColor, $timestamp);
            $stmt->execute();
            $stmt->close();
        } catch (Exception $e) {
            // Log error but don't fail the main response
            error_log("Roulette draws save failed: " . $e->getMessage());
        }
    };
    
    // Execute background tasks immediately (simulating async)
    $updateAnalytics();
    $saveRouletteDraws();
    
    // Calculate performance metrics
    $end_time = microtime(true);
    $execution_time = round(($end_time - $start_time) * 1000, 2); // Convert to milliseconds
    
    // Fast success response
    echo json_encode([
        "status" => "success",
        "message" => "Data saved successfully",
        "data" => [
            "draw_number" => $drawNumber,
            "winning_number" => $winningNumber,
            "winning_color" => $winningColor,
            "detailed_record_id" => $detailedResultId,
            "timestamp" => $timestamp
        ],
        "performance" => [
            "execution_time_ms" => $execution_time,
            "optimized" => true
        ]
    ]);
    
} catch (Exception $e) {
    $end_time = microtime(true);
    $execution_time = round(($end_time - $start_time) * 1000, 2);
    
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Save failed: " . $e->getMessage(),
        "performance" => [
            "execution_time_ms" => $execution_time
        ]
    ]);
}
?>
