<?php
// Timer Synchronization Endpoint
header('Content-Type: application/json');

// Get countdown time from database
$db_file = dirname(__FILE__) . '/../tvdisplay/db/rolls.json';
$response = ['success' => false];

if (file_exists($db_file)) {
    $data = json_decode(file_get_contents($db_file), true);
    
    if (isset($data['countdownTime'])) {
        $response = [
            'success' => true,
            'countdownTime' => (int)$data['countdownTime']
        ];
    }
}

echo json_encode($response);
