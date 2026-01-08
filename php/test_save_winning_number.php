<?php
/**
 * Test script for save_winning_number.php
 */
echo "<pre>";
echo "Testing save_winning_number.php...\n\n";

// Prepare data
$data = [
    'winning_number' => 17,
    'draw_number' => 200,
    'winning_color' => 'black'
];

// Encode data
$jsonData = json_encode($data);

// Set up curl
$ch = curl_init('http://localhost/slipp/php/save_winning_number.php');
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($jsonData)
]);

// Execute request
echo "Sending request...\n";
echo "Data: " . $jsonData . "\n\n";

$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Check for errors
if (curl_errno($ch)) {
    echo "Error: " . curl_error($ch) . "\n";
} else {
    echo "HTTP Status: " . $httpCode . "\n";
    echo "Response: " . $result . "\n";
    
    // Pretty print the JSON
    $response = json_decode($result);
    if ($response) {
        echo "\nParsed Response:\n";
        echo "Status: " . $response->status . "\n";
        echo "Message: " . $response->message . "\n";
        
        if (isset($response->data)) {
            echo "Data:\n";
            foreach ($response->data as $key => $value) {
                echo "  $key: $value\n";
            }
        }
    }
}

curl_close($ch);
echo "</pre>"; 