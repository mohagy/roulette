<?php
// Start session
session_start();

// Set response header to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    // User is logged in
    echo json_encode([
        'status' => 'success',
        'authenticated' => true,
        'user' => [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role']
        ]
    ]);
} else {
    // User is not logged in
    echo json_encode([
        'status' => 'error',
        'authenticated' => false,
        'message' => 'Not authenticated'
    ]);
}
?>
