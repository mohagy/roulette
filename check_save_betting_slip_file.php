<?php
// Set response header to JSON
header('Content-Type: application/json');

// Check if save_betting_slip.php exists
if (!file_exists('php/save_betting_slip.php')) {
    echo json_encode([
        'status' => 'error',
        'message' => 'php/save_betting_slip.php file not found'
    ]);
    exit;
}

// Read the save_betting_slip.php file
$saveBettingSlipFile = file_get_contents('php/save_betting_slip.php');

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
    'log_message' => [
        'pattern' => '/logMessage\("Warning: Using default user_id/',
        'found' => false
    ],
    'commission_insertion' => [
        'pattern' => '/INSERT INTO commission/',
        'found' => false
    ],
    'commission_user_id' => [
        'pattern' => '/\$commissionStmt->bind_param\("idssi", \$userId/',
        'found' => false
    ]
];

// Check each pattern
foreach ($patterns as $key => &$pattern) {
    $pattern['found'] = preg_match($pattern['pattern'], $saveBettingSlipFile) === 1;
}

// Extract the user_id validation code if it exists
preg_match('/\/\/ Get the logged-in user.*?logMessage\("Using user ID: \$userId for betting slip"/s', $saveBettingSlipFile, $validationCode);

// Extract the commission insertion code if it exists
preg_match('/\/\/ Record commission.*?\$commissionStmt->close\(\);/s', $saveBettingSlipFile, $commissionCode);

// Prepare response
$response = [
    'status' => 'success',
    'file_exists' => true,
    'file_size' => filesize('php/save_betting_slip.php'),
    'file_modified' => date('Y-m-d H:i:s', filemtime('php/save_betting_slip.php')),
    'patterns_found' => $patterns,
    'validation_code' => $validationCode[0] ?? 'Not found',
    'commission_code' => $commissionCode[0] ?? 'Not found',
    'fixes_applied' => $patterns['user_id_validation']['found'] && $patterns['default_user_id']['found'],
    'recommendations' => []
];

// Add recommendations based on findings
if (!$patterns['user_id_validation']['found'] || !$patterns['default_user_id']['found']) {
    $response['recommendations'][] = "Add user_id validation to save_betting_slip.php:
    // Make sure we have a valid user_id
    if (!$userId || $userId <= 0) {
        $userId = 1; // Default to user_id 1 if not set
        logMessage(\"Warning: Using default user_id 1 because session user_id is invalid\", 'WARNING');
    }";
}

if (!$patterns['commission_insertion']['found'] || !$patterns['commission_user_id']['found']) {
    $response['recommendations'][] = "Check the commission insertion code to ensure it's using the correct user_id.";
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>
