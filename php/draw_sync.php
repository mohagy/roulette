<?php
/**
 * Draw Synchronization API
 * 
 * This endpoint provides real-time draw number synchronization between
 * the main game and TV display interfaces.
 */

// Set response header to JSON
header('Content-Type: application/json');

// Include the database configuration
require_once 'db_config.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Create log file for debugging if needed
$logFile = '../logs/draw_sync.log';

// Function to log messages
function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND);
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

    // Get the current draw information from the roulette_state table
    $sql = "SELECT current_draw, next_draw FROM roulette_state ORDER BY id DESC LIMIT 1";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // Get additional analytics if needed
        $analytics = [];
        $analyticsSql = "SELECT * FROM roulette_analytics ORDER BY id DESC LIMIT 1";
        $analyticsResult = $conn->query($analyticsSql);
        
        if ($analyticsResult && $analyticsResult->num_rows > 0) {
            $analytics = $analyticsResult->fetch_assoc();
        }

        // Log successful fetch
        logMessage("Successfully fetched draw info: Current #{$row['current_draw']}, Next #{$row['next_draw']}");
        
        // Return the draw information
        echo json_encode([
            'success' => true,
            'currentDraw' => intval($row['current_draw']),
            'nextDraw' => intval($row['next_draw']),
            'analytics' => $analytics
        ]);
    } else {
        // No data found, create initial state
        $initialCurrentDraw = 1;
        $initialNextDraw = 2;
        
        $insertSql = "INSERT INTO roulette_state (current_draw, next_draw) VALUES (?, ?)";
        $stmt = $conn->prepare($insertSql);
        $stmt->bind_param("ii", $initialCurrentDraw, $initialNextDraw);
        
        if ($stmt->execute()) {
            logMessage("Created initial draw state: Current #$initialCurrentDraw, Next #$initialNextDraw");
            
            echo json_encode([
                'success' => true,
                'currentDraw' => $initialCurrentDraw,
                'nextDraw' => $initialNextDraw,
                'analytics' => []
            ]);
        } else {
            logMessage("Failed to create initial state: " . $stmt->error);
            
            echo json_encode([
                'success' => false,
                'message' => 'Failed to create initial draw state: ' . $stmt->error
            ]);
        }
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