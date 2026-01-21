<?php
// Set response header to JSON
header('Content-Type: application/json');

// Check if commission.php exists
if (!file_exists('commission.php')) {
    echo json_encode([
        'status' => 'error',
        'message' => 'commission.php file not found'
    ]);
    exit;
}

// Read the commission.php file
$commissionFile = file_get_contents('commission.php');

// Check for key code patterns to determine if our fixes have been applied
$patterns = [
    'user_id_validation' => [
        'pattern' => '/\$userId\s*=\s*isset\(\$_SESSION\[\'user_id\'\]\)\s*\?\s*\$_SESSION\[\'user_id\'\]\s*:\s*0;/',
        'found' => false
    ],
    'redirect_if_invalid' => [
        'pattern' => '/if\s*\(\s*!\$userId\s*\|\|\s*\$userId\s*<=\s*0\s*\)\s*{/',
        'found' => false
    ],
    'not_null_check' => [
        'pattern' => '/\$userId\s*=\s*\$_SESSION\[\'user_id\'\];/',
        'found' => false
    ]
];

// Check each pattern
foreach ($patterns as $key => &$pattern) {
    $pattern['found'] = preg_match($pattern['pattern'], $commissionFile) === 1;
}

// Check for the specific query that gets commission data
$queryPatterns = [
    'commission_query' => [
        'pattern' => '/\$stmt\s*=\s*\$conn->prepare\("SELECT \* FROM commission_summary WHERE date_created = \? AND user_id = \?"\);/',
        'found' => false
    ],
    'bind_param' => [
        'pattern' => '/\$stmt->bind_param\("si", \$mostRecentDate, \$userId\);/',
        'found' => false
    ]
];

// Check each query pattern
foreach ($queryPatterns as $key => &$pattern) {
    $pattern['found'] = preg_match($pattern['pattern'], $commissionFile) === 1;
}

// Extract the user_id assignment code
preg_match('/\/\/ Get user ID from session.*?\$userId.*?;/s', $commissionFile, $userIdCode);

// Extract the commission query code
preg_match('/\/\/ Get commission summary for.*?if \(\$result->num_rows === 1\) {/s', $commissionFile, $queryCode);

// Prepare response
$response = [
    'status' => 'success',
    'file_exists' => true,
    'file_size' => filesize('commission.php'),
    'file_modified' => date('Y-m-d H:i:s', filemtime('commission.php')),
    'patterns_found' => $patterns,
    'query_patterns_found' => $queryPatterns,
    'user_id_code' => $userIdCode[0] ?? 'Not found',
    'query_code' => $queryCode[0] ?? 'Not found',
    'fixes_applied' => $patterns['user_id_validation']['found'] && $patterns['redirect_if_invalid']['found'],
    'recommendations' => []
];

// Add recommendations based on findings
if (!$patterns['user_id_validation']['found']) {
    $response['recommendations'][] = "Update the user_id assignment to include validation: \$userId = isset(\$_SESSION['user_id']) ? \$_SESSION['user_id'] : 0;";
}

if (!$patterns['redirect_if_invalid']['found']) {
    $response['recommendations'][] = "Add redirect for invalid user_id: if (!\$userId || \$userId <= 0) { header('Location: login.html'); exit; }";
}

if (!$queryPatterns['commission_query']['found'] || !$queryPatterns['bind_param']['found']) {
    $response['recommendations'][] = "Check the commission query to ensure it's filtering by user_id correctly.";
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>
