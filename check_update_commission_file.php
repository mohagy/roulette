<?php
// Set response header to JSON
header('Content-Type: application/json');

// Check if update_commission.php exists
if (!file_exists('update_commission.php')) {
    echo json_encode([
        'status' => 'error',
        'message' => 'update_commission.php file not found'
    ]);
    exit;
}

// Read the update_commission.php file
$updateCommissionFile = file_get_contents('update_commission.php');

// Check for key code patterns to determine if our fixes have been applied
$patterns = [
    'user_id_validation' => [
        'pattern' => '/if\s*\(\s*!\$userId\s*\|\|\s*\$userId\s*<=\s*0\s*\)\s*{/',
        'found' => false
    ],
    'default_user_id' => [
        'pattern' => '/\$userId\s*=\s*1;/',
        'found' => false
    ],
    'error_log' => [
        'pattern' => '/error_log\("Warning: Using default user_id/',
        'found' => false
    ]
];

// Check each pattern
foreach ($patterns as $key => &$pattern) {
    $pattern['found'] = preg_match($pattern['pattern'], $updateCommissionFile) === 1;
}

// Extract the user_id validation code if it exists
preg_match('/\/\/ Get updated commission summary.*?if \(\$result->num_rows === 1\) {/s', $updateCommissionFile, $validationCode);

// Prepare response
$response = [
    'status' => 'success',
    'file_exists' => true,
    'file_size' => filesize('update_commission.php'),
    'file_modified' => date('Y-m-d H:i:s', filemtime('update_commission.php')),
    'patterns_found' => $patterns,
    'validation_code' => $validationCode[0] ?? 'Not found',
    'fixes_applied' => $patterns['user_id_validation']['found'] && $patterns['default_user_id']['found'],
    'recommendations' => []
];

// Add recommendations based on findings
if (!$patterns['user_id_validation']['found'] || !$patterns['default_user_id']['found']) {
    $response['recommendations'][] = "Add user_id validation to update_commission.php:
    // Make sure we have a valid user_id
    if (!$userId || $userId <= 0) {
        $userId = 1; // Default to user_id 1 if not set
        error_log(\"Warning: Using default user_id 1 for commission because session user_id is invalid\");
    }";
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>
