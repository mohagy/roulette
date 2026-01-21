<?php
// Script to create the sync_timer.php file for timer synchronization
header('Content-Type: application/json');

// Parse input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['file_content'])) {
    // Try POST data if JSON fails
    $file_content = isset($_POST['file_content']) ? $_POST['file_content'] : '';
    
    if (empty($file_content)) {
        echo json_encode([
            'success' => false,
            'message' => 'No file content provided'
        ]);
        exit;
    }
} else {
    $file_content = $data['file_content'];
}

// Create the sync_timer.php file
$file_path = __DIR__ . '/sync_timer.php';

$result = file_put_contents($file_path, $file_content);

if ($result !== false) {
    echo json_encode([
        'success' => true,
        'message' => 'sync_timer.php created successfully',
        'bytes_written' => $result
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create sync_timer.php',
        'error' => error_get_last()
    ]);
}
?> 