<?php
/**
 * Get Latest Draw Number API
 * 
 * This API returns the latest draw number from the detailed_draw_results table.
 */

// Initialize cache prevention
require_once "cache_prevention.php";

// Include database connection
require_once "db_connect.php";

// Set response header to JSON
header("Content-Type: application/json");

try {
    // Get latest draw number using fresh data
    $result = getFreshData("SELECT MAX(draw_number) as latest_draw_number FROM detailed_draw_results");
    
    if (!empty($result)) {
        $latestDrawNumber = $result[0]["latest_draw_number"];
        
        echo json_encode([
            "status" => "success",
            "latest_draw_number" => $latestDrawNumber ? (int)$latestDrawNumber : 0,
            "message" => "Latest draw number retrieved successfully",
            "timestamp" => date("Y-m-d H:i:s")
        ]);
    } else {
        echo json_encode([
            "status" => "success",
            "latest_draw_number" => 0,
            "message" => "No draw numbers found",
            "timestamp" => date("Y-m-d H:i:s")
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to get latest draw number: " . $e->getMessage(),
        "timestamp" => date("Y-m-d H:i:s")
    ]);
}
?>