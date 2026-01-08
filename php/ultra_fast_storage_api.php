<?php
/**
 * Ultra-Fast Storage API
 * 
 * Minimal overhead API for maximum speed
 * Only saves to detailed_draw_results table for immediate betting validation
 * All other updates are queued for background processing
 */

// Minimal headers
header("Content-Type: application/json");

// Direct database connection without overhead
$host = "localhost";
$username = "root";
$password = "";
$database = "roulette";

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    http_response_code(500);
    echo '{"status":"error","message":"Connection failed"}';
    exit;
}

// Performance timer
$start = microtime(true);

// Fast input processing
$input = file_get_contents("php://input");
$data = json_decode($input, true);

// Minimal validation
if (!$data || !isset($data["winning_number"]) || !isset($data["draw_number"])) {
    http_response_code(400);
    echo '{"status":"error","message":"Missing data"}';
    exit;
}

$num = (int)$data["winning_number"];
$draw = (int)$data["draw_number"];

// Quick validation
if ($num < 0 || $num > 36 || $draw < 1) {
    http_response_code(400);
    echo '{"status":"error","message":"Invalid data"}';
    exit;
}

// Fast color lookup
$color = ($num === 0) ? "green" : 
    (($num % 2 === 1 && $num <= 10) || ($num % 2 === 0 && $num >= 11 && $num <= 18) || 
     ($num % 2 === 1 && $num >= 19 && $num <= 28) || ($num % 2 === 0 && $num >= 29) ? "red" : "black");

// Ultra-fast insert with prepared statement
$stmt = $conn->prepare("INSERT INTO detailed_draw_results (draw_number, winning_number, color, timestamp) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("iis", $draw, $num, $color);

if ($stmt->execute()) {
    $id = $conn->insert_id;
    $time = round((microtime(true) - $start) * 1000, 2);
    
    echo json_encode([
        "status" => "success",
        "id" => $id,
        "draw" => $draw,
        "number" => $num,
        "color" => $color,
        "time_ms" => $time
    ]);
    
    // Queue background updates (write to file for background processor)
    $queue_data = json_encode([
        "winning_number" => $num,
        "draw_number" => $draw,
        "color" => $color,
        "timestamp" => date("Y-m-d H:i:s"),
        "queued_at" => microtime(true)
    ]);
    
    file_put_contents("../logs/background_queue.log", $queue_data . "\n", FILE_APPEND | LOCK_EX);
    
} else {
    $time = round((microtime(true) - $start) * 1000, 2);
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Insert failed",
        "time_ms" => $time
    ]);
}

$stmt->close();
$conn->close();
?>
