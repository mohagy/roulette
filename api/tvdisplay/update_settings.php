<?php
// TV Display Settings - Client-side only (localStorage)
// No database storage for TV display settings
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

echo json_encode([
    'status' => 'success',
    'message' => 'TV display settings are managed client-side via localStorage. No database storage needed.'
]);
?>
