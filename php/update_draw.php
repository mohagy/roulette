<?php
/**
 * Update Draw Number API
 * 
 * This endpoint updates the current and next draw numbers in the database.
 * Used when a draw completes and we need to advance to the next one.
 */

// Set response header to JSON
header('Content-Type: application/json');

// Include the database configuration
require_once 'db_config.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Create log file for debugging
$logFile = '../logs/draw_update.log';

// Function to log messages
function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

// Only accept POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logMessage("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode([
        'success' => false,
        'message' => 'Only POST method is allowed'
    ]);
    exit;
}

// Check if required data is present
if (!isset($_POST['currentDraw']) || !isset($_POST['nextDraw'])) {
    logMessage("Missing required parameters");
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters: currentDraw and nextDraw'
    ]);
    exit;
}

// Get and validate the draw numbers
$currentDraw = intval($_POST['currentDraw']);
$nextDraw = intval($_POST['nextDraw']);

if ($currentDraw <= 0 || $nextDraw <= 0) {
    logMessage("Invalid draw numbers: current={$currentDraw}, next={$nextDraw}");
    echo json_encode([
        'success' => false,
        'message' => 'Invalid draw numbers. Must be positive integers.'
    ]);
    exit;
}

try {
    // Create a database connection
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // Check connection
    if ($conn->connect_error) {
        logMessage("Connection failed: " . $conn->connect_error);
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed: ' . $conn->connect_error
        ]);
        exit;
    }

    // Start transaction for data consistency
    $conn->begin_transaction();

    try {
        // Update the roulette_state table
        $updateStateSql = "INSERT INTO roulette_state (current_draw, next_draw, updated_at)
                          VALUES (?, ?, NOW())
                          ON DUPLICATE KEY UPDATE 
                          current_draw = VALUES(current_draw),
                          next_draw = VALUES(next_draw),
                          updated_at = VALUES(updated_at)";
        
        $stateStmt = $conn->prepare($updateStateSql);
        $stateStmt->bind_param("ii", $currentDraw, $nextDraw);
        $stateResult = $stateStmt->execute();
        
        if (!$stateResult) {
            throw new Exception("Failed to update roulette_state: " . $stateStmt->error);
        }
        
        // Update the analytics table if needed
        $updateAnalyticsSql = "UPDATE roulette_analytics SET 
                              current_draw_number = ?,
                              updated_at = NOW()
                              WHERE id = 1";
        
        $analyticsStmt = $conn->prepare($updateAnalyticsSql);
        $analyticsStmt->bind_param("i", $currentDraw);
        $analyticsResult = $analyticsStmt->execute();
        
        // If analytics update fails, it's not critical
        if (!$analyticsResult) {
            logMessage("Warning: Failed to update roulette_analytics: " . $analyticsStmt->error);
        }
        
        // Commit transaction
        $conn->commit();
        
        // Log successful update
        logMessage("Successfully updated draw numbers: Current #{$currentDraw}, Next #{$nextDraw}");
        
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Draw numbers updated successfully',
            'currentDraw' => $currentDraw,
            'nextDraw' => $nextDraw
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        throw $e;
    }

    // Close the database connection
    $conn->close();
    
} catch (Exception $e) {
    logMessage("Exception: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}
?> 