<?php
/**
 * get_latest_winning_number.php
 * 
 * API endpoint to retrieve the most recent winning number from the database
 * Used by the main POS application for cashout verification
 */

// Save any potential output
ob_start();

// Set response header to JSON
header('Content-Type: application/json');

// Include the database connection
require_once 'db_connect.php';

// Create logs directory if it doesn't exist
if (!file_exists('../logs')) {
    mkdir('../logs', 0777, true);
}

/**
 * Log messages to a file
 * 
 * @param string $message The message to log
 * @param string $level The log level (info, warning, error)
 * @return void
 */
function log_message($message, $level = 'info') {
    $log_file = '../logs/draw_results.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_line = "[$timestamp] [$level] $message" . PHP_EOL;
    
    // Append to log file
    file_put_contents($log_file, $log_line, FILE_APPEND);
}

/**
 * Send a JSON response and exit
 * 
 * @param string $status The status of the response (success, error)
 * @param string $message The response message
 * @param array $data Additional data to include in the response
 * @return void
 */
function send_response($status, $message, $data = []) {
    // Clear any previous output
    ob_clean();
    
    $response = [
        'status' => $status,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => $data
    ];
    
    echo json_encode($response);
    exit;
}

try {
    // First try to get from the analytics table for fastest response
    $analytics_query = "SELECT last_draw_number, last_winning_number, last_winning_color, last_draw_time 
                       FROM roulette_analytics 
                       WHERE id = 1 
                       LIMIT 1";
    
    $analytics_result = $conn->query($analytics_query);
    
    if ($analytics_result && $row = $analytics_result->fetch(PDO::FETCH_ASSOC)) {
        // We have data from analytics table
        $data = [
            'draw_number' => $row['last_draw_number'],
            'winning_number' => $row['last_winning_number'],
            'winning_color' => $row['last_winning_color'],
            'draw_time' => $row['last_draw_time']
        ];
        
        log_message("Retrieved latest winning number from analytics: Draw #{$data['draw_number']}, Number: {$data['winning_number']}");
        send_response('success', 'Latest winning number retrieved successfully', $data);
    }
    
    // If no data in analytics table, try the detailed_draw_results table
    $draw_query = "SELECT draw_number, winning_number, winning_color, created_at 
                   FROM detailed_draw_results 
                   ORDER BY draw_number DESC 
                   LIMIT 1";
    
    $draw_result = $conn->query($draw_query);
    
    if ($draw_result && $row = $draw_result->fetch(PDO::FETCH_ASSOC)) {
        // We have data from detailed_draw_results table
        $data = [
            'draw_number' => $row['draw_number'],
            'winning_number' => $row['winning_number'],
            'winning_color' => $row['winning_color'],
            'draw_time' => $row['created_at']
        ];
        
        log_message("Retrieved latest winning number from detailed draw results: Draw #{$data['draw_number']}, Number: {$data['winning_number']}");
        send_response('success', 'Latest winning number retrieved successfully', $data);
    }
    
    // If still no data, try the game_history table
    $history_query = "SELECT draw_number, winning_number, winning_color, timestamp 
                      FROM game_history 
                      ORDER BY id DESC 
                      LIMIT 1";
    
    $history_result = $conn->query($history_query);
    
    if ($history_result && $row = $history_result->fetch(PDO::FETCH_ASSOC)) {
        // We have data from game_history table
        $data = [
            'draw_number' => $row['draw_number'],
            'winning_number' => $row['winning_number'],
            'winning_color' => $row['winning_color'],
            'draw_time' => $row['timestamp']
        ];
        
        log_message("Retrieved latest winning number from game history: Draw #{$data['draw_number']}, Number: {$data['winning_number']}");
        send_response('success', 'Latest winning number retrieved successfully', $data);
    }
    
    // No data found in any table
    log_message("No winning number data found in the database", 'warning');
    send_response('error', 'No winning number data found in the database');
    
} catch (PDOException $e) {
    log_message("Database error: " . $e->getMessage(), 'error');
    send_response('error', 'Database error: ' . $e->getMessage());
} 