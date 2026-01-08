<?php
/**
 * Cashier Draw Sync API
 * Provides draw number information specifically for the cashier interface
 * Returns the upcoming draw number for new betting slips and last completed draw
 */

// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json');

// Include database connection
require_once '../php/db_connect.php';

// Default response
$response = [
    'status' => 'error',
    'message' => 'Failed to fetch draw information',
    'timestamp' => time()
];

/**
 * Log messages for debugging
 */
function logCashierSync($message, $type = 'INFO') {
    $logFile = '../logs/cashier_draw_sync.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$type] $message\n";
    
    // Ensure logs directory exists
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

/**
 * Get the current completed draw number from detailed_draw_results (authoritative)
 */
function getLastCompletedDraw($conn) {
    try {
        $stmt = $conn->prepare("SELECT MAX(draw_number) as max_completed_draw FROM detailed_draw_results");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return (int)($row['max_completed_draw'] ?? 0);
        }
        
        return 0;
    } catch (Exception $e) {
        logCashierSync("Error getting last completed draw: " . $e->getMessage(), 'ERROR');
        return 0;
    }
}

/**
 * Get the next draw number for new betting slips
 */
function getNextDrawNumber($conn) {
    try {
        // Get the last completed draw
        $lastCompletedDraw = getLastCompletedDraw($conn);
        
        // The next draw for betting slips should be the last completed + 1
        // This ensures betting slips are always assigned to future draws
        $nextDraw = $lastCompletedDraw + 1;
        
        logCashierSync("Calculated next draw: $nextDraw (last completed: $lastCompletedDraw)");
        
        return $nextDraw;
        
    } catch (Exception $e) {
        logCashierSync("Error calculating next draw: " . $e->getMessage(), 'ERROR');
        return 1; // Fallback to draw #1
    }
}

/**
 * Get additional system status information
 */
function getSystemStatus($conn) {
    $status = [
        'total_completed_draws' => 0,
        'last_draw_time' => null,
        'system_time' => date('Y-m-d H:i:s'),
        'timezone' => date_default_timezone_get()
    ];
    
    try {
        // Get total completed draws
        $stmt = $conn->prepare("SELECT COUNT(*) as total_draws FROM detailed_draw_results");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $status['total_completed_draws'] = (int)$row['total_draws'];
        }
        
        // Get last draw time (check for both possible column names)
        $timeColumn = 'timestamp'; // Default to timestamp
        
        // Check if timestamp column exists
        $columnCheck = $conn->prepare("SHOW COLUMNS FROM detailed_draw_results LIKE 'timestamp'");
        $columnCheck->execute();
        $columnResult = $columnCheck->get_result();
        
        if ($columnResult->num_rows === 0) {
            // Check for draw_time column
            $columnCheck = $conn->prepare("SHOW COLUMNS FROM detailed_draw_results LIKE 'draw_time'");
            $columnCheck->execute();
            $columnResult = $columnCheck->get_result();
            
            if ($columnResult->num_rows > 0) {
                $timeColumn = 'draw_time';
            }
        }
        
        // Get the most recent draw time
        $stmt = $conn->prepare("SELECT $timeColumn FROM detailed_draw_results ORDER BY draw_number DESC LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $status['last_draw_time'] = $row[$timeColumn];
        }
        
    } catch (Exception $e) {
        logCashierSync("Error getting system status: " . $e->getMessage(), 'ERROR');
    }
    
    return $status;
}

try {
    logCashierSync("Cashier draw sync request received");
    
    // Get draw information
    $lastCompletedDraw = getLastCompletedDraw($conn);
    $nextDrawNumber = getNextDrawNumber($conn);
    $systemStatus = getSystemStatus($conn);
    
    // Validate the data
    if ($nextDrawNumber <= $lastCompletedDraw) {
        logCashierSync("Warning: Next draw ($nextDrawNumber) is not greater than last completed ($lastCompletedDraw)", 'WARNING');
        $nextDrawNumber = $lastCompletedDraw + 1;
    }
    
    // Prepare successful response
    $response = [
        'status' => 'success',
        'data' => [
            'last_completed_draw' => $lastCompletedDraw,
            'next_draw_for_betting' => $nextDrawNumber,
            'upcoming_draw' => $nextDrawNumber,
            'current_completed_draw' => $lastCompletedDraw,
            'system_status' => $systemStatus
        ],
        'message' => 'Draw information retrieved successfully',
        'timestamp' => time(),
        'server_time' => date('Y-m-d H:i:s')
    ];
    
    logCashierSync("Successfully retrieved draw info - Last completed: $lastCompletedDraw, Next for betting: $nextDrawNumber");
    
} catch (Exception $e) {
    logCashierSync("Error in cashier draw sync: " . $e->getMessage(), 'ERROR');
    
    $response = [
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage(),
        'timestamp' => time()
    ];
}

// Close database connection
if (isset($conn)) {
    $conn->close();
}

// Output the response
echo json_encode($response, JSON_PRETTY_PRINT);
?>
