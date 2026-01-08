<?php
/**
 * Get Latest Roulette Draws API
 * 
 * This API returns the latest records from the roulette_draws table.
 */

// Initialize cache prevention
require_once "cache_prevention.php";

// Include database connection
require_once "db_connect.php";

// Set response header to JSON
header("Content-Type: application/json");

try {
    // Get latest roulette draws using fresh data
    $result = getFreshData("SELECT * FROM roulette_draws ORDER BY id DESC LIMIT 10");
    
    if (!empty($result)) {
        echo json_encode([
            "status" => "success",
            "data" => $result,
            "count" => count($result),
            "message" => "Latest roulette draws retrieved successfully",
            "timestamp" => date("Y-m-d H:i:s")
        ]);
    } else {
        echo json_encode([
            "status" => "success",
            "data" => [],
            "count" => 0,
            "message" => "No roulette draws found",
            "timestamp" => date("Y-m-d H:i:s")
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to get latest roulette draws: " . $e->getMessage(),
        "timestamp" => date("Y-m-d H:i:s")
    ]);
}
?>
