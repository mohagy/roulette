<?php
/**
 * Safe Draw Advance API
 * 
 * This API endpoint uses the centralized DrawNumberManager to safely advance
 * draw numbers without race conditions or sequence skips.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include required files
require_once '../includes/db_connection.php';
require_once '../php/draw_number_manager.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in JSON response

/**
 * Log API activity
 */
function logApiActivity($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [SafeDrawAdvance] [$level] $message";
    error_log($logMessage);
}

try {
    // Initialize the draw number manager
    $drawManager = new DrawNumberManager($conn);
    
    // Get the requested action
    $action = $_GET['action'] ?? $_POST['action'] ?? 'info';
    
    logApiActivity("API called with action: $action");
    
    switch ($action) {
        case 'advance':
            // Advance to the next draw
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Advance action requires POST method');
            }
            
            logApiActivity("Attempting to advance draw number");
            $result = $drawManager->advanceToNextDraw();
            
            if ($result['success']) {
                logApiActivity("Draw advanced successfully: {$result['previousDraw']} -> {$result['currentDraw']}");
            } else {
                logApiActivity("Draw advance failed: {$result['message']}", 'ERROR');
            }
            
            echo json_encode($result);
            break;
            
        case 'info':
            // Get current draw information
            logApiActivity("Getting current draw info");
            $result = $drawManager->getCurrentDrawInfo();
            echo json_encode($result);
            break;
            
        case 'detect_gaps':
            // Detect sequence gaps
            logApiActivity("Detecting sequence gaps");
            $result = $drawManager->detectSequenceGaps();
            
            if ($result['success'] && $result['hasGaps']) {
                logApiActivity("Sequence gaps detected: " . implode(', ', $result['gaps']), 'WARNING');
            }
            
            echo json_encode($result);
            break;
            
        case 'backfill':
            // Backfill missing draws
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Backfill action requires POST method');
            }
            
            // First detect gaps
            $gapResult = $drawManager->detectSequenceGaps();
            
            if (!$gapResult['success']) {
                throw new Exception('Failed to detect gaps: ' . $gapResult['message']);
            }
            
            if (!$gapResult['hasGaps']) {
                $result = [
                    'success' => true,
                    'message' => 'No gaps detected, nothing to backfill',
                    'gaps' => []
                ];
            } else {
                logApiActivity("Backfilling gaps: " . implode(', ', $gapResult['gaps']));
                $result = $drawManager->backfillMissingDraws($gapResult['gaps']);
                
                if ($result['success']) {
                    logApiActivity("Backfill completed: {$result['count']} draws backfilled");
                } else {
                    logApiActivity("Backfill failed: {$result['message']}", 'ERROR');
                }
            }
            
            echo json_encode($result);
            break;
            
        case 'validate':
            // Validate current system state
            logApiActivity("Validating system state");
            
            // Get current draw info
            $drawInfo = $drawManager->getCurrentDrawInfo();
            
            // Detect gaps
            $gapInfo = $drawManager->detectSequenceGaps();
            
            // Check database consistency
            $consistencyCheck = checkDatabaseConsistency($conn);
            
            $result = [
                'success' => true,
                'validation' => [
                    'drawInfo' => $drawInfo,
                    'gaps' => $gapInfo,
                    'consistency' => $consistencyCheck
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            echo json_encode($result);
            break;
            
        default:
            throw new Exception("Unknown action: $action");
    }
    
} catch (Exception $e) {
    logApiActivity("API error: " . $e->getMessage(), 'ERROR');
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'API_ERROR'
    ]);
}

/**
 * Check database consistency across tables
 */
function checkDatabaseConsistency($conn) {
    try {
        $consistency = [
            'roulette_analytics' => null,
            'roulette_state' => null,
            'detailed_draw_results_max' => null,
            'matches' => false,
            'issues' => []
        ];
        
        // Get from roulette_analytics
        $stmt = $conn->prepare("SELECT current_draw_number FROM roulette_analytics WHERE id = 1");
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $consistency['roulette_analytics'] = (int)$row['current_draw_number'];
        }
        
        // Get from roulette_state
        $stmt = $conn->prepare("SELECT current_draw FROM roulette_state WHERE id = 1");
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $drawNum = str_replace('#', '', $row['current_draw']);
            $consistency['roulette_state'] = (int)$drawNum;
        }
        
        // Get max from detailed_draw_results
        $stmt = $conn->prepare("SELECT MAX(draw_number) as max_draw FROM detailed_draw_results");
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $consistency['detailed_draw_results_max'] = (int)$row['max_draw'];
        }
        
        // Check for consistency
        $values = array_filter([
            $consistency['roulette_analytics'],
            $consistency['roulette_state'],
            $consistency['detailed_draw_results_max']
        ]);
        
        if (count(array_unique($values)) === 1) {
            $consistency['matches'] = true;
        } else {
            $consistency['matches'] = false;
            $consistency['issues'][] = 'Draw numbers do not match across tables';
        }
        
        return $consistency;
        
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage()
        ];
    }
}

// Close database connection
if (isset($conn)) {
    $conn->close();
}
?>
