<?php
header('Content-Type: application/json');

// Check if Firebase config exists
$firebaseConfigPath = '../../js/firebase-config.js';
$hasFirebase = file_exists($firebaseConfigPath);

$status = [
    'status' => 'success',
    'data' => [
        'config_exists' => $hasFirebase,
        'timestamp' => time(),
        'message' => $hasFirebase ? 'Firebase configuration found' : 'Firebase configuration not found'
    ]
];

echo json_encode($status);
?>
