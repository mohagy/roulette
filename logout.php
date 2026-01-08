<?php
// Start session
session_start();

// Create logs directory if it doesn't exist
$logDir = __DIR__ . '/logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}

// Log file for login attempts
$logFile = $logDir . '/login.log';

/**
 * Log message to file
 */
function log_message($message, $level = 'INFO') {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Log the logout
if (isset($_SESSION['username'])) {
    log_message("User logged out: " . $_SESSION['username'], 'INFO');
}

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// If this is an AJAX request, return JSON response
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => 'Logged out successfully']);
    exit;
}

// Redirect to login page
header("Location: login.php");
exit;
?>
