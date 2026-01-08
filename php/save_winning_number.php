<?php
/**
 * Save Winning Number API - NO CACHE VERSION
 *
 * SECURITY: This API ensures no data is cached for security reasons.
 * All database operations use fresh data with no caching.
 */

// Initialize comprehensive cache prevention FIRST
require_once 'cache_prevention.php';

// Set response header to JSON
header('Content-Type: application/json');

// Include database connection
require_once 'db_connect.php';

// Create logs directory if it doesn't exist
$logDir = __DIR__ . '/../logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}

// Log file for draw results
$logFile = $logDir . '/draw_results.log';

/**
 * Log message to file
 */
function log_message($message, $level = 'info') {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

/**
 * Send JSON response
 */
function send_response($status, $message, $data = []) {
    $response = [
        'status' => $status,
        'message' => $message
    ];

    if (!empty($data)) {
        $response['data'] = $data;
    }

    echo json_encode($response);
    exit;
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response('error', 'Only POST requests are allowed');
}

// Get the JSON data from the request
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate JSON data
if (!$data) {
    log_message('Invalid JSON data received', 'error');
    send_response('error', 'Invalid JSON data');
}

// Validate required fields
if (!isset($data['winning_number']) || !isset($data['draw_number'])) {
    log_message('Missing required fields: winning_number or draw_number', 'error');
    send_response('error', 'Missing required fields: winning_number and draw_number are required');
}

// Extract data
$winning_number = intval($data['winning_number']);
$draw_number = intval($data['draw_number']);
$winning_color = isset($data['winning_color']) ? $data['winning_color'] : determineColor($winning_number);
$timestamp = isset($data['timestamp']) ? $data['timestamp'] : date('Y-m-d H:i:s');
$draw_id = isset($data['draw_id']) ? $data['draw_id'] : "DRAW-{$draw_number}-" . time();

// Log the incoming data
log_message("Received winning number: $winning_number, draw number: $draw_number, color: $winning_color", 'info');

try {
    // Check if detailed_draw_results table exists
    $result = $conn->query("DESCRIBE detailed_draw_results");
    if (!$result) {
        throw new Exception("detailed_draw_results table does not exist");
    }

    // Check for duplicate draw entry
    $checkSql = "SELECT COUNT(*) as count FROM detailed_draw_results WHERE draw_number = ? AND winning_number = ?";
    $checkStmt = $conn->prepare($checkSql);
    if (!$checkStmt) {
        throw new Exception("Error preparing check statement: " . $conn->error);
    }

    $checkStmt->bind_param("ii", $draw_number, $winning_number);
    if (!$checkStmt->execute()) {
        throw new Exception("Error executing check statement: " . $checkStmt->error);
    }

    $checkResult = $checkStmt->get_result();
    $row = $checkResult->fetch_assoc();

    if ($row['count'] > 0) {
        // Already have this draw result
        log_message("Draw #$draw_number with winning number $winning_number already exists, skipping", 'info');
        send_response('success', 'Winning number already saved', [
            'winning_number' => $winning_number,
            'draw_number' => $draw_number,
            'winning_color' => $winning_color,
            'draw_id' => $draw_id,
            'timestamp' => $timestamp,
            'duplicate' => true
        ]);
        exit;
    }

    // Insert winning number into detailed_draw_results
    $sql = "INSERT INTO detailed_draw_results
            (draw_id, draw_number, winning_number, winning_color)
            VALUES (?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Error preparing statement: " . $conn->error);
    }

    $stmt->bind_param("siis", $draw_id, $draw_number, $winning_number, $winning_color);
    if (!$stmt->execute()) {
        throw new Exception("Error executing statement: " . $stmt->error);
    }

    log_message("Saved winning number $winning_number for draw #$draw_number", 'info');

    // Also update game_history if it exists, but check for duplicates first
    $result = $conn->query("SHOW TABLES LIKE 'game_history'");
    if ($result->num_rows > 0) {
        // Check for duplicate in game_history
        $checkGameSql = "SELECT COUNT(*) as count FROM game_history WHERE winning_number = ? AND DATE(played_at) = CURDATE()";
        $checkGameStmt = $conn->prepare($checkGameSql);

        if ($checkGameStmt) {
            $checkGameStmt->bind_param("i", $winning_number);
            $checkGameStmt->execute();
            $checkGameResult = $checkGameStmt->get_result();
            $gameRow = $checkGameResult->fetch_assoc();

            if ($gameRow['count'] == 0) {
                // Only insert if no duplicate exists
                $sql = "INSERT INTO game_history (winning_number, winning_color, draw_id) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("iss", $winning_number, $winning_color, $draw_id);
                    $stmt->execute();
                    log_message("Updated game history", 'info');
                }
            } else {
                log_message("Skipping game_history update, duplicate exists", 'info');
            }
        }
    }

    // Return success response
    send_response('success', 'Winning number saved successfully', [
        'winning_number' => $winning_number,
        'draw_number' => $draw_number,
        'winning_color' => $winning_color,
        'draw_id' => $draw_id,
        'timestamp' => $timestamp
    ]);

} catch (Exception $e) {
    // Log the error
    log_message("Error saving winning number: " . $e->getMessage(), 'error');

    // Return error response
    send_response('error', 'Failed to save winning number: ' . $e->getMessage());
}

/**
 * Determine the color based on the winning number
 */
function determineColor($number) {
    if ($number == 0) {
        return 'green';
    }

    $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];

    return in_array($number, $redNumbers) ? 'red' : 'black';
}
