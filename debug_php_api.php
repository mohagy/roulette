<?php
// Debug the PHP API
echo "=== DEBUGGING PHP API ===\n\n";

// Test 1: Check if Python is accessible
echo "1. Testing Python accessibility:\n";
$pythonVersion = shell_exec('python --version 2>&1');
echo "Python version: " . trim($pythonVersion) . "\n\n";

// Test 2: Test Python script directly
echo "2. Testing Python script directly:\n";
$testData = json_encode([
    'slip_number' => 'TEST-001',
    'date' => date('Y-m-d H:i:s'),
    'draw_number' => '999',
    'total_stake' => '100.00',
    'potential_win' => '3600.00',
    'bets' => [
        [
            'type' => 'straight',
            'description' => 'Test Bet',
            'amount' => '100.00',
            'odds' => '35:1',
            'potential_return' => '3600.00'
        ]
    ]
]);

$pythonScript = __DIR__ . '/api/print_slip.py';
$command = "python \"$pythonScript\" " . escapeshellarg($testData) . " " . escapeshellarg("Microsoft Print to PDF");

echo "Command: $command\n";
$output = shell_exec($command . " 2>&1");
echo "Output: " . trim($output) . "\n\n";

// Test 3: Check if output is valid JSON
echo "3. Testing JSON parsing:\n";
$result = json_decode(trim($output), true);
if ($result === null) {
    echo "❌ JSON parsing failed\n";
    echo "Raw output: " . var_export($output, true) . "\n";
} else {
    echo "✅ JSON parsing successful\n";
    echo "Result: " . print_r($result, true) . "\n";
}

// Test 4: Test printer detection
echo "4. Testing printer detection:\n";
$printerCommand = 'powershell "Get-Printer | Select-Object Name | ConvertTo-Json"';
$printerOutput = shell_exec($printerCommand);
echo "Printer output: " . trim($printerOutput) . "\n";

$printers = json_decode($printerOutput, true);
if (is_array($printers)) {
    echo "✅ Found " . count($printers) . " printers\n";
} else {
    echo "❌ Printer detection failed\n";
}
?>
