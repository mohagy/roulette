<?php
/**
 * Debug cashout network issues
 */

echo "<h1>Debug Cashout Network Issues</h1>";
echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto;'>";

// Test 1: Direct API call with the exact same parameters
echo "<h2>Test 1: Direct API Call</h2>";

$slip_number = 'CASHOUT_TEST_123';
echo "<p>Testing with slip number: <strong>$slip_number</strong></p>";

// Simulate the exact same request that JavaScript would make
$postData = array(
    'action' => 'verify_cashout',
    'slip_number' => $slip_number
);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/slipp/php/cashout_api.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_VERBOSE, true);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "<p><strong>HTTP Code:</strong> $http_code</p>";
echo "<p><strong>Content Type:</strong> $content_type</p>";

if ($curl_error) {
    echo "<p style='color: red;'><strong>cURL Error:</strong> $curl_error</p>";
}

// Split headers and body
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr($response, 0, $header_size);
$body = substr($response, $header_size);

echo "<h3>Response Headers:</h3>";
echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>";
echo htmlspecialchars($headers);
echo "</pre>";

echo "<h3>Response Body:</h3>";
echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>";
echo htmlspecialchars($body);
echo "</pre>";

// Test 2: Check if the file exists and is accessible
echo "<h2>Test 2: File Accessibility</h2>";

$api_file = 'php/cashout_api.php';
if (file_exists($api_file)) {
    echo "<p style='color: green;'>✓ File exists: $api_file</p>";
    
    if (is_readable($api_file)) {
        echo "<p style='color: green;'>✓ File is readable</p>";
    } else {
        echo "<p style='color: red;'>✗ File is not readable</p>";
    }
    
    // Check file size
    $file_size = filesize($api_file);
    echo "<p>File size: $file_size bytes</p>";
    
} else {
    echo "<p style='color: red;'>✗ File does not exist: $api_file</p>";
}

// Test 3: Check for PHP syntax errors
echo "<h2>Test 3: PHP Syntax Check</h2>";

$syntax_check = shell_exec("php -l $api_file 2>&1");
echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>";
echo htmlspecialchars($syntax_check);
echo "</pre>";

// Test 4: Test with a simple direct include
echo "<h2>Test 4: Direct Include Test</h2>";

try {
    // Capture any output
    ob_start();
    
    // Simulate POST data
    $_POST['action'] = 'verify_cashout';
    $_POST['slip_number'] = $slip_number;
    
    // Include the file
    include $api_file;
    
    $output = ob_get_clean();
    
    echo "<p style='color: green;'>✓ File included successfully</p>";
    echo "<h3>Output:</h3>";
    echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>";
    echo htmlspecialchars($output);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error including file: " . $e->getMessage() . "</p>";
}

// Test 5: JavaScript debugging helper
echo "<h2>Test 5: JavaScript Debug Helper</h2>";
echo "<p>Use this JavaScript code in your browser console to debug the network request:</p>";

echo '<textarea style="width: 100%; height: 200px; font-family: monospace; font-size: 12px;" readonly>
// Debug the cashout API call
const debugCashout = async (slipNumber) => {
    console.log("Starting debug cashout test...");
    
    const formData = new FormData();
    formData.append("action", "verify_cashout");
    formData.append("slip_number", slipNumber);
    
    try {
        console.log("Making fetch request to:", "php/cashout_api.php");
        console.log("Form data:", {
            action: "verify_cashout",
            slip_number: slipNumber
        });
        
        const response = await fetch("php/cashout_api.php", {
            method: "POST",
            body: formData
        });
        
        console.log("Response status:", response.status);
        console.log("Response headers:", response.headers);
        console.log("Response ok:", response.ok);
        
        const text = await response.text();
        console.log("Raw response text:", text);
        
        try {
            const json = JSON.parse(text);
            console.log("Parsed JSON:", json);
            return json;
        } catch (parseError) {
            console.error("JSON parse error:", parseError);
            console.log("Response was not valid JSON");
            return { error: "Invalid JSON", response: text };
        }
        
    } catch (fetchError) {
        console.error("Fetch error:", fetchError);
        return { error: "Network error", details: fetchError.message };
    }
};

// Run the debug test
debugCashout("' . $slip_number . '");
</textarea>';

echo "<p><strong>Instructions:</strong></p>";
echo "<ol>";
echo "<li>Open your browser's Developer Tools (F12)</li>";
echo "<li>Go to the Console tab</li>";
echo "<li>Copy and paste the code above into the console</li>";
echo "<li>Press Enter to run it</li>";
echo "<li>Check the console output for detailed error information</li>";
echo "</ol>";

echo "</div>";
?>
