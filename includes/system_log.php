<?php
/**
 * System Log Functions
 * 
 * This file contains functions for logging system events for audit purposes.
 */

// Create logs directory if it doesn't exist
if (!file_exists(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0777, true);
}

// System log file path
define('SYSTEM_LOG_FILE', __DIR__ . '/../logs/system_audit.log');

/**
 * Log a system event
 * 
 * @param string $event_type The type of event (e.g., 'login', 'logout', 'reset', etc.)
 * @param string $message The event message
 * @param array $data Additional data to log (optional)
 * @param int $user_id The ID of the user who performed the action (optional)
 * @return bool True if logging was successful, false otherwise
 */
function log_system_event($event_type, $message, $data = [], $user_id = null) {
    try {
        // Get current timestamp
        $timestamp = date('Y-m-d H:i:s');
        
        // Get user ID from session if not provided
        if ($user_id === null && isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
        }
        
        // Get IP address
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // Format log entry as JSON
        $log_entry = [
            'timestamp' => $timestamp,
            'event_type' => $event_type,
            'message' => $message,
            'user_id' => $user_id,
            'ip_address' => $ip_address,
            'data' => $data
        ];
        
        // Convert to JSON string
        $log_json = json_encode($log_entry) . "\n";
        
        // Write to log file
        $result = file_put_contents(SYSTEM_LOG_FILE, $log_json, FILE_APPEND);
        
        return ($result !== false);
    } catch (Exception $e) {
        // If logging fails, write to PHP error log
        error_log("Failed to write to system log: " . $e->getMessage());
        return false;
    }
}

/**
 * Get system log entries
 * 
 * @param int $limit Maximum number of entries to return (default: 100)
 * @param string $event_type Filter by event type (optional)
 * @param int $user_id Filter by user ID (optional)
 * @return array Array of log entries
 */
function get_system_log_entries($limit = 100, $event_type = null, $user_id = null) {
    try {
        // Check if log file exists
        if (!file_exists(SYSTEM_LOG_FILE)) {
            return [];
        }
        
        // Read log file
        $log_content = file_get_contents(SYSTEM_LOG_FILE);
        if (empty($log_content)) {
            return [];
        }
        
        // Split into lines
        $log_lines = explode("\n", trim($log_content));
        
        // Parse JSON entries
        $log_entries = [];
        foreach ($log_lines as $line) {
            if (empty($line)) continue;
            
            $entry = json_decode($line, true);
            if ($entry === null) continue;
            
            // Apply filters
            if ($event_type !== null && $entry['event_type'] !== $event_type) continue;
            if ($user_id !== null && $entry['user_id'] != $user_id) continue;
            
            $log_entries[] = $entry;
        }
        
        // Sort by timestamp (newest first)
        usort($log_entries, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        // Apply limit
        return array_slice($log_entries, 0, $limit);
    } catch (Exception $e) {
        error_log("Failed to read system log: " . $e->getMessage());
        return [];
    }
}
?>
