<?php
// Set response header to JSON
header('Content-Type: application/json');

// Default response
$response = [
    'status' => 'success',
    'timestamp' => time(),
    'message' => 'Ping successful'
];

// Check if we need to ping a specific target or service
if (isset($_GET['target'])) {
    $target = $_GET['target'];
    
    switch ($target) {
        case 'tv':
            // For TV display, we can simply respond that we're here
            // Later we can implement actual checking if needed
            $response['target'] = 'tv';
            $response['alive'] = true;
            break;
            
        case 'management':
            // For management dashboard
            $response['target'] = 'management';
            $response['alive'] = true;
            break;
            
        default:
            // Unknown target
            $response['status'] = 'error';
            $response['message'] = 'Unknown target: ' . $target;
    }
}

// Return the response as JSON
echo json_encode($response); 