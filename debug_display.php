<?php
header('Content-Type: application/json');

// Check if files exist
$files = [
    'css/cashier-draw-display.css',
    'js/cashier-draw-display.js',
    'php/get_last_completed_draw_details.php'
];

$fileStatus = [];
foreach ($files as $file) {
    $fileStatus[$file] = [
        'exists' => file_exists($file),
        'readable' => is_readable($file),
        'size' => file_exists($file) ? filesize($file) : 0
    ];
}

// Test API endpoint
$apiResponse = null;
$apiError = null;
try {
    $apiUrl = 'http://localhost:8080/slipp/php/get_last_completed_draw_details.php';
    $context = stream_context_create([
        'http' => [
            'timeout' => 5
        ]
    ]);
    $apiResponse = file_get_contents($apiUrl, false, $context);
    if ($apiResponse) {
        $apiResponse = json_decode($apiResponse, true);
    }
} catch (Exception $e) {
    $apiError = $e->getMessage();
}

// Check database connection
$dbStatus = null;
$dbError = null;
try {
    $conn = new mysqli("localhost", "root", "", "roulette");
    if ($conn->connect_error) {
        $dbError = $conn->connect_error;
    } else {
        $dbStatus = "Connected";
        
        // Check if we have recent draw data
        $result = $conn->query("SELECT COUNT(*) as count FROM detailed_draw_results WHERE timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $recentDraws = $result->fetch_assoc()['count'];
        
        $result = $conn->query("SELECT COUNT(*) as count FROM betting_slips WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $recentSlips = $result->fetch_assoc()['count'];
        
        $dbStatus = [
            'connection' => 'OK',
            'recent_draws' => $recentDraws,
            'recent_slips' => $recentSlips
        ];
    }
    $conn->close();
} catch (Exception $e) {
    $dbError = $e->getMessage();
}

$response = [
    'timestamp' => date('Y-m-d H:i:s'),
    'files' => $fileStatus,
    'api' => [
        'response' => $apiResponse,
        'error' => $apiError
    ],
    'database' => [
        'status' => $dbStatus,
        'error' => $dbError
    ],
    'server_info' => [
        'php_version' => phpversion(),
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown'
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT);
?>
