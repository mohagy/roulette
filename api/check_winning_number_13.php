<?php
/**
 * Diagnostic script to check why winning number keeps returning 13
 */

header('Content-Type: application/json');
require_once '../includes/db_connection.php';

$response = [
    'status' => 'success',
    'data' => []
];

try {
    // Check next_draw_winning_number table
    $stmt = $conn->prepare("
        SELECT draw_number, winning_number, source, reason, created_at, updated_at
        FROM next_draw_winning_number
        ORDER BY draw_number DESC
        LIMIT 10
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $nextDrawNumbers = [];
    while ($row = $result->fetch_assoc()) {
        $nextDrawNumbers[] = $row;
    }
    $stmt->close();
    
    // Check detailed_draw_results for recent draws
    $stmt = $conn->prepare("
        SELECT draw_number, winning_number, color, timestamp
        FROM detailed_draw_results
        ORDER BY draw_number DESC
        LIMIT 10
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $recentDraws = [];
    while ($row = $result->fetch_assoc()) {
        $recentDraws[] = $row;
    }
    $stmt->close();
    
    // Check current draw number
    $stmt = $conn->prepare("SELECT current_draw_number FROM roulette_analytics WHERE id = 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $currentDraw = $result->fetch_assoc()['current_draw_number'] ?? null;
    $stmt->close();
    
    // Count how many times 13 appears
    $count13 = 0;
    foreach ($recentDraws as $draw) {
        if ($draw['winning_number'] == 13) {
            $count13++;
        }
    }
    
    $response['data'] = [
        'current_draw_number' => $currentDraw,
        'next_draw_winning_numbers' => $nextDrawNumbers,
        'recent_draws' => $recentDraws,
        'count_of_13' => $count13,
        'percentage_13' => count($recentDraws) > 0 ? round(($count13 / count($recentDraws)) * 100, 2) : 0,
        'diagnosis' => []
    ];
    
    // Diagnose the issue
    if ($count13 > count($recentDraws) * 0.5) {
        $response['data']['diagnosis'][] = 'WARNING: Number 13 appears in more than 50% of recent draws';
    }
    
    // Check if 13 is in next_draw_winning_number
    $has13InNext = false;
    foreach ($nextDrawNumbers as $next) {
        if ($next['winning_number'] == 13) {
            $has13InNext = true;
            $response['data']['diagnosis'][] = "Number 13 is set for draw #{$next['draw_number']} in next_draw_winning_number table";
            break;
        }
    }
    
    if (!$has13InNext && $count13 > 0) {
        $response['data']['diagnosis'][] = 'Number 13 is appearing in results but not in next_draw_winning_number - may be from automatic selection';
    }
    
} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>

