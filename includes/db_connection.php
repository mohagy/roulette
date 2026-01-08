<?php
// Database configuration
$host = 'localhost';
$database = 'roulette';
$user = 'root';
$password = '';

// Create connection
$conn = new mysqli($host, $user, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set character set
$conn->set_charset('utf8mb4');

/**
 * Log SQL errors to a file and the PHP error log
 *
 * @param string $query The SQL query that failed
 * @param string $error The error message
 * @return void
 */
function logSqlError($query, $error) {
    // Create logs directory if it doesn't exist
    if (!file_exists('../logs')) {
        mkdir('../logs', 0777, true);
    }
    
    $logFile = '../logs/sql_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    $message = "$timestamp - SQL Error: $error\nQuery: $query\n\n";
    
    // Append to log file
    file_put_contents($logFile, $message, FILE_APPEND);
    
    // Also log to PHP error log
    error_log("SQL Error: $error | Query: $query");
} 