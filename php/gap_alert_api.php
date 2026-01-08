<?php
/**
 * Gap Alert API
 * 
 * Monitors for draw number gap attempts and provides alerts
 */

header("Content-Type: application/json");
header("Cache-Control: no-cache, no-store, must-revalidate");

try {
    $conn = new mysqli("localhost", "root", "", "roulette");
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed");
    }
    
    $action = $_GET['action'] ?? 'check';
    
    if ($action === 'check') {
        // Check for recent gap attempts
        $result = $conn->query("
            SELECT 
                attempted_draw_number,
                expected_draw_number,
                gap_size,
                timestamp,
                source_table
            FROM draw_gap_attempts 
            WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ORDER BY timestamp DESC
            LIMIT 10
        ");
        
        $gapAttempts = [];
        while ($row = $result->fetch_assoc()) {
            $gapAttempts[] = $row;
        }
        
        // Get current sequence health
        $healthResult = $conn->query("SELECT * FROM draw_sequence_health");
        $health = [];
        while ($row = $healthResult->fetch_assoc()) {
            $health[] = $row;
        }
        
        echo json_encode([
            "status" => "success",
            "gap_attempts" => $gapAttempts,
            "sequence_health" => $health,
            "alert_level" => count($gapAttempts) > 0 ? "warning" : "normal",
            "timestamp" => date("Y-m-d H:i:s")
        ]);
        
    } elseif ($action === 'latest_draws') {
        // Get latest draws with sequence analysis
        $result = $conn->query("
            SELECT 
                draw_number,
                winning_number,
                color,
                timestamp,
                CASE 
                    WHEN LAG(draw_number) OVER (ORDER BY draw_number) IS NULL THEN 'FIRST'
                    WHEN draw_number - LAG(draw_number) OVER (ORDER BY draw_number) = 1 THEN 'SEQUENTIAL'
                    ELSE CONCAT('GAP: ', draw_number - LAG(draw_number) OVER (ORDER BY draw_number) - 1, ' missing')
                END as sequence_status
            FROM detailed_draw_results 
            ORDER BY draw_number DESC 
            LIMIT 10
        ");
        
        $draws = [];
        while ($row = $result->fetch_assoc()) {
            $draws[] = $row;
        }
        
        echo json_encode([
            "status" => "success",
            "latest_draws" => $draws,
            "timestamp" => date("Y-m-d H:i:s")
        ]);
        
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Invalid action"
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
?>
