<?php
/**
 * Get Next Draw Number API
 * Returns the next draw number that new betting slips should be assigned to
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once 'db_connect.php';

try {
    $nextDrawNumber = null;
    $currentDrawNumber = null;
    $source = '';
    
    // Method 1: Try to get from roulette_state table (most reliable)
    $stmt = $conn->prepare("SELECT last_draw, next_draw FROM roulette_state WHERE id = 1");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $state = $result->fetch_assoc();
            if ($state['next_draw']) {
                $nextDrawNumber = (int)str_replace('#', '', $state['next_draw']);
                $currentDrawNumber = (int)str_replace('#', '', $state['last_draw']);
                $source = 'roulette_state';
            }
        }
        $stmt->close();
    }
    
    // Method 2: If not found, try roulette_analytics table
    if (!$nextDrawNumber) {
        $stmt = $conn->prepare("SELECT current_draw_number FROM roulette_analytics WHERE id = 1");
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $analytics = $result->fetch_assoc();
                $currentDrawNumber = (int)$analytics['current_draw_number'];
                $nextDrawNumber = $currentDrawNumber + 1;
                $source = 'roulette_analytics';
            }
            $stmt->close();
        }
    }
    
    // Method 3: If still not found, try detailed_draw_results table
    if (!$nextDrawNumber) {
        $stmt = $conn->prepare("SELECT MAX(draw_number) as max_draw FROM detailed_draw_results");
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                if ($row['max_draw']) {
                    $currentDrawNumber = (int)$row['max_draw'];
                    $nextDrawNumber = $currentDrawNumber + 1;
                    $source = 'detailed_draw_results';
                }
            }
            $stmt->close();
        }
    }
    
    // Method 4: Final fallback
    if (!$nextDrawNumber) {
        $currentDrawNumber = 0;
        $nextDrawNumber = 1;
        $source = 'fallback';
    }
    
    // Validate the numbers
    if ($nextDrawNumber < 1) {
        $nextDrawNumber = 1;
    }
    
    // Return the response
    echo json_encode([
        'status' => 'success',
        'current_draw_number' => $currentDrawNumber,
        'next_draw_number' => $nextDrawNumber,
        'source' => $source,
        'message' => "Next draw number retrieved from $source",
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error retrieving next draw number: ' . $e->getMessage(),
        'current_draw_number' => null,
        'next_draw_number' => 1, // Safe fallback
        'source' => 'error_fallback',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
