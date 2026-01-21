<?php
// TV Display Settings - Client-side only (localStorage)
// No database storage for TV display settings
header('Content-Type: application/json');

echo json_encode([
    'status' => 'success',
    'message' => 'TV display settings are managed client-side via localStorage',
    'data' => []
]);
?>
