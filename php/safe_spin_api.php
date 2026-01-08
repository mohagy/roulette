<?php
/**
 * Safe Spin API - Uses Database Safeguards
 * 
 * This API uses the database stored procedure with triggers
 * to guarantee sequential draw numbers and prevent gaps.
 */

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header("Content-Type: application/json");

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

// Validate input
if (!$data || !isset($data["winning_number"])) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Missing required field: winning_number",
        "timestamp" => date("Y-m-d H:i:s")
    ]);
    exit;
}

$winningNumber = (int)$data["winning_number"];
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

try {
    // Database connection
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "roulette";
    
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
    // Use the safe stored procedure
    $stmt = $conn->prepare("CALL add_sequential_spin(?, ?)");
    $stmt->bind_param("is", $winningNumber, $timestamp);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute safe spin procedure: " . $stmt->error);
    }
    
    // Get the result
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $stmt->close();
    
    // Update roulette_analytics table
    updateAnalytics($conn, $winningNumber, $row['draw_number']);
    
    // Success response
    echo json_encode([
        "status" => "success",
        "message" => "Spin saved with guaranteed sequential draw number",
        "data" => [
            "draw_number" => (int)$row['draw_number'],
            "winning_number" => (int)$row['winning_number'],
            "winning_color" => $row['winning_color'],
            "timestamp" => $timestamp,
            "safeguards_active" => true
        ],
        "timestamp" => date("Y-m-d H:i:s")
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to save spin: " . $e->getMessage(),
        "timestamp" => date("Y-m-d H:i:s")
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

/**
 * Update analytics table
 */
function updateAnalytics($conn, $winningNumber, $drawNumber) {
    try {
        // Get current analytics
        $result = $conn->query("SELECT * FROM roulette_analytics WHERE id = 1");
        
        if ($result->num_rows === 0) {
            $allSpins = [$winningNumber];
            $numberFrequency = array_fill(0, 37, 0);
            $numberFrequency[$winningNumber] = 1;
        } else {
            $analytics = $result->fetch_assoc();
            $allSpins = json_decode($analytics["all_spins"], true) ?: [];
            $numberFrequency = json_decode($analytics["number_frequency"], true) ?: array_fill(0, 37, 0);
            
            // Add new spin to beginning
            array_unshift($allSpins, $winningNumber);
            $allSpins = array_slice($allSpins, 0, 100); // Keep last 100
            $numberFrequency[$winningNumber]++;
        }
        
        $allSpinsJson = json_encode($allSpins);
        $frequencyJson = json_encode($numberFrequency);
        
        if ($result->num_rows === 0) {
            $stmt = $conn->prepare("INSERT INTO roulette_analytics (id, all_spins, number_frequency, current_draw_number, last_updated, created_at) VALUES (1, ?, ?, ?, NOW(), NOW())");
            $stmt->bind_param("ssi", $allSpinsJson, $frequencyJson, $drawNumber);
        } else {
            $stmt = $conn->prepare("UPDATE roulette_analytics SET all_spins = ?, number_frequency = ?, current_draw_number = ?, last_updated = NOW() WHERE id = 1");
            $stmt->bind_param("ssi", $allSpinsJson, $frequencyJson, $drawNumber);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update analytics: " . $stmt->error);
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        // Log error but don't fail the main operation
        error_log("Analytics update failed: " . $e->getMessage());
    }
}
?>
