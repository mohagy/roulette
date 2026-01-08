<?php
// Analytics Monitor - Detects unauthorized changes
require_once "cache_prevention.php";

function monitorAnalytics() {
    $expectedData = [
        "all_spins" => "[]",
        "number_frequency" => "[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0]",
        "current_draw_number" => 0
    ];
    
    $currentData = getFreshData("SELECT SQL_NO_CACHE * FROM roulette_analytics WHERE id = 1");
    
    if (!empty($currentData)) {
        $analytics = $currentData[0];
        
        // Check for unauthorized changes
        if ($analytics["current_draw_number"] > 0 && $analytics["all_spins"] !== "[]") {
            logCachePrevention("UNAUTHORIZED ANALYTICS CHANGE DETECTED", [
                "current_draw" => $analytics["current_draw_number"],
                "spins" => $analytics["all_spins"],
                "timestamp" => date("Y-m-d H:i:s")
            ]);
            
            // Auto-reset if unauthorized
            $conn = getFreshDatabaseConnection();
            $stmt = $conn->prepare("UPDATE roulette_analytics SET all_spins = ?, number_frequency = ?, current_draw_number = 0, last_updated = NOW() WHERE id = 1");
            $stmt->bind_param("ss", $expectedData["all_spins"], $expectedData["number_frequency"]);
            $stmt->execute();
            
            logCachePrevention("Analytics auto-reset due to unauthorized change");
        }
    }
}

// Run monitoring
monitorAnalytics();
?>