<?php
/**
 * Debug Current Database State
 * Check all draw-related tables to understand the current state
 */

header('Content-Type: application/json');
require_once 'php/db_connect.php';

try {
    $debug = [];
    
    // Check roulette_state table
    $result = $conn->query("SELECT * FROM roulette_state ORDER BY id");
    $debug['roulette_state'] = [];
    while ($row = $result->fetch_assoc()) {
        $debug['roulette_state'][] = $row;
    }
    
    // Check roulette_analytics table
    $result = $conn->query("SELECT * FROM roulette_analytics ORDER BY id");
    $debug['roulette_analytics'] = [];
    while ($row = $result->fetch_assoc()) {
        $debug['roulette_analytics'][] = $row;
    }
    
    // Check roulette_game_state table
    $result = $conn->query("SELECT * FROM roulette_game_state ORDER BY id");
    $debug['roulette_game_state'] = [];
    while ($row = $result->fetch_assoc()) {
        $debug['roulette_game_state'][] = $row;
    }
    
    // Check last few draws from detailed_draw_results
    $result = $conn->query("SELECT * FROM detailed_draw_results ORDER BY draw_number DESC LIMIT 5");
    $debug['recent_draws'] = [];
    while ($row = $result->fetch_assoc()) {
        $debug['recent_draws'][] = $row;
    }
    
    // Check what get_next_draw_number.php returns
    $debug['api_response'] = 'Check manually at php/get_next_draw_number.php';
    
    echo json_encode($debug, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
?>
