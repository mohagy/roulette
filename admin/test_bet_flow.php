<?php
// URL to save_betting_slip.php
$url = 'http://localhost/slipp/php/save_betting_slip.php';

// Test data
$slipNumber = 'TEST-' . time();
$data = [
    'slip_number' => $slipNumber,
    'total_stake' => 10,
    'potential_return' => 360,
    'bets' => [
        [
            'type' => 'straight',
            'description' => 'Straight Up on 5',
            'amount' => 10,
            'multiplier' => 36,
            'potentialReturn' => 360
        ]
    ]
];

// Initialize curl
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

// Execute
echo "Sending bet request...\n";
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";

// Check database
require_once '../php/db_connect.php';

echo "\nChecking database for slip $slipNumber...\n";
$stmt = $conn->prepare("SELECT * FROM betting_slips WHERE slip_number = ?");
$stmt->bind_param("s", $slipNumber);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "SUCCESS: Bet found!\n";
    echo "Slip ID: " . $row['slip_id'] . "\n";
    echo "Draw Number: " . $row['draw_number'] . "\n";
    echo "Total Stake: " . $row['total_stake'] . "\n";
    
    // Check if draw number is correct (should be > 1)
    if ($row['draw_number'] > 1) {
        echo "VERIFICATION PASSED: Draw number is valid (" . $row['draw_number'] . ")\n";
    } else {
        echo "VERIFICATION FAILED: Draw number is still 1!\n";
    }
} else {
    echo "FAILURE: Bet not found in database.\n";
}
?>
