<?php
// Set headers to allow cross-origin requests and proper content type
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

// Get the requested video filename
$filename = isset($_GET['file']) ? $_GET['file'] : '';

// Validate file name for security
if (!preg_match('/^adds[1-4]\.mp4$/', $filename)) {
    header("HTTP/1.1 403 Forbidden");
    echo "Access denied: Invalid file name";
    exit;
}

// Define possible paths to check
$possiblePaths = [
    __DIR__ . "/adds/{$filename}",
    dirname(__DIR__) . "/tvdisplay/adds/{$filename}",
    "C:/xampp1/htdocs/tvdisplay/adds/{$filename}",
    "C:/xampp1/htdocs/slipp/tvdisplay/adds/{$filename}"
];

$filePath = null;

// Check each path until we find the file
foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        $filePath = $path;
        break;
    }
}

// If file not found in any location
if ($filePath === null) {
    header("HTTP/1.1 404 Not Found");
    echo "File not found: $filename";
    echo "\n\nChecked paths:";
    foreach ($possiblePaths as $path) {
        echo "\n- $path";
    }
    exit;
}

// Get file info
$fileSize = filesize($filePath);
$fileType = 'video/mp4';

// Handle range requests for video streaming
$ranges = isset($_SERVER['HTTP_RANGE']) ? $_SERVER['HTTP_RANGE'] : null;
if ($ranges) {
    // Extract the range values
    list($unit, $range) = explode('=', $ranges, 2);
    
    if ($unit == 'bytes') {
        // Parse the range values
        $ranges = explode(',', $range);
        $firstRange = explode('-', $ranges[0]);
        
        $start = isset($firstRange[0]) && is_numeric($firstRange[0]) ? intval($firstRange[0]) : 0;
        $end = isset($firstRange[1]) && is_numeric($firstRange[1]) ? intval($firstRange[1]) : $fileSize - 1;
        
        // Ensure the range is valid
        $end = min($end, $fileSize - 1);
        $length = $end - $start + 1;
        
        // Set the appropriate headers for partial content
        header("HTTP/1.1 206 Partial Content");
        header("Content-Range: bytes $start-$end/$fileSize");
        header("Content-Length: $length");
        
        // Seek to the requested position
        $fp = fopen($filePath, 'rb');
        fseek($fp, $start);
        
        // Output the requested range
        $buffer = 1024 * 8;
        $bytesToRead = $length;
        
        while (!feof($fp) && $bytesToRead > 0) {
            $bytesToActuallyRead = min($buffer, $bytesToRead);
            echo fread($fp, $bytesToActuallyRead);
            $bytesToRead -= $bytesToActuallyRead;
            
            // Flush output buffer
            flush();
        }
        
        fclose($fp);
    } else {
        header("HTTP/1.1 416 Range Not Satisfiable");
        exit;
    }
} else {
    // Send the entire file
    header("Content-Type: $fileType");
    header("Content-Length: $fileSize");
    
    // Output the file content
    readfile($filePath);
}
?> 