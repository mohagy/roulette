<?php
/**
 * Roulette TV Display Sharing Script
 *
 * This script provides a secure way to share the roulette TV display
 * with multiple viewers without allowing them to interact with it.
 */

// Optional: Basic authentication
$enableAuth = false; // Set to true to enable authentication
$validToken = 'your-secret-token'; // Change this to a secure random string

// Check if authentication is enabled and token is valid
if ($enableAuth) {
    $token = isset($_GET['token']) ? $_GET['token'] : '';

    if ($token !== $validToken) {
        header('HTTP/1.0 403 Forbidden');
        echo '<h1>Access Denied</h1>';
        echo '<p>You need a valid access token to view this page.</p>';
        exit;
    }
}

// Set cache control headers to prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// Create logs directory if it doesn't exist
$logsDir = '../logs';
if (!file_exists($logsDir)) {
    mkdir($logsDir, 0755, true);
}

// Log the access (optional)
$logFile = '../logs/view_access.log';
$timestamp = date('Y-m-d H:i:s');
$ipAddress = $_SERVER['REMOTE_ADDR'];
$userAgent = $_SERVER['HTTP_USER_AGENT'];
$logEntry = "[$timestamp] IP: $ipAddress, UA: $userAgent" . PHP_EOL;
file_put_contents($logFile, $logEntry, FILE_APPEND);

// Get the current draw number from the database (optional)
try {
    require_once('../php/db_config.php');
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if (!$conn->connect_error) {
        $sql = "SELECT current_draw, next_draw FROM roulette_state ORDER BY id DESC LIMIT 1";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $currentDraw = $row['current_draw'];
            $nextDraw = $row['next_draw'];
        }

        $conn->close();
    }
} catch (Exception $e) {
    // Silently fail - we'll just use the default values in the HTML
}

// Serve the view-only page
include('view-only.html');
?>
