<?php
/**
 * Authentication Check Endpoint
 * Returns JSON response indicating if user is authenticated and user information
 */

// Start session
session_start();

// Set JSON header
header('Content-Type: application/json');

// Check if user is authenticated
if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    // User is authenticated
    echo json_encode([
        'authenticated' => true,
        'user' => [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role'] ?? 'cashier'
        ]
    ]);
} else {
    // User is not authenticated
    echo json_encode([
        'authenticated' => false,
        'user' => null
    ]);
}
exit;
?>

