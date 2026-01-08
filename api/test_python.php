<?php
/**
 * Test Python Integration
 * Simple test to verify Python is working
 */

header('Content-Type: application/json');

function testPython() {
    $results = [];
    
    // Test 1: Check if Python is available
    $pythonCommands = ['python', 'python.exe', 'python3', 'python3.exe'];
    
    foreach ($pythonCommands as $cmd) {
        $testCommand = "where $cmd 2>nul";
        $result = shell_exec($testCommand);
        
        if ($result !== null && trim($result) !== '') {
            $results['python_found'] = $cmd;
            $results['python_path'] = trim($result);
            break;
        }
    }
    
    if (!isset($results['python_found'])) {
        return [
            'success' => false,
            'error' => 'Python not found in PATH',
            'results' => $results
        ];
    }
    
    // Test 2: Test Python execution
    $pythonExe = $results['python_found'];
    $testScript = __DIR__ . '/test_simple.py';
    
    // Create simple test script
    $simpleScript = '#!/usr/bin/env python3
import json
import sys

try:
    result = {
        "success": True,
        "message": "Python is working!",
        "version": sys.version,
        "executable": sys.executable
    }
    print(json.dumps(result))
except Exception as e:
    result = {
        "success": False,
        "error": str(e)
    }
    print(json.dumps(result))
';
    
    file_put_contents($testScript, $simpleScript);
    
    // Execute test script
    $command = "\"$pythonExe\" \"$testScript\"";
    $output = shell_exec($command . " 2>&1");
    
    // Clean up
    if (file_exists($testScript)) {
        unlink($testScript);
    }
    
    if ($output === null) {
        return [
            'success' => false,
            'error' => 'Failed to execute Python script',
            'command' => $command,
            'results' => $results
        ];
    }
    
    $pythonResult = json_decode(trim($output), true);
    
    if ($pythonResult === null) {
        return [
            'success' => false,
            'error' => 'Invalid Python output',
            'output' => $output,
            'command' => $command,
            'results' => $results
        ];
    }
    
    $results['python_test'] = $pythonResult;
    
    // Test 3: Check required modules
    $moduleTest = '#!/usr/bin/env python3
import json
import sys

modules_to_test = ["win32print", "win32api", "reportlab"]
results = {}

for module in modules_to_test:
    try:
        __import__(module)
        results[module] = "Available"
    except ImportError as e:
        results[module] = f"Missing: {str(e)}"

print(json.dumps({"modules": results}))
';
    
    $moduleScript = __DIR__ . '/test_modules.py';
    file_put_contents($moduleScript, $moduleTest);
    
    $moduleCommand = "\"$pythonExe\" \"$moduleScript\"";
    $moduleOutput = shell_exec($moduleCommand . " 2>&1");
    
    // Clean up
    if (file_exists($moduleScript)) {
        unlink($moduleScript);
    }
    
    if ($moduleOutput !== null) {
        $moduleResult = json_decode(trim($moduleOutput), true);
        if ($moduleResult !== null) {
            $results['modules'] = $moduleResult['modules'];
        }
    }
    
    return [
        'success' => true,
        'results' => $results
    ];
}

// Run test
$result = testPython();
echo json_encode($result, JSON_PRETTY_PRINT);
?>
