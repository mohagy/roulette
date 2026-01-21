<?php
/**
 * Test Analytics API to verify current draw is included
 */

date_default_timezone_set('America/Guyana');
$now = new DateTime('now', new DateTimeZone('America/Guyana'));
$h = (int)$now->format('H');
$m = (int)$now->format('i');
$total = ($h * 60) + $m;
$currentDraw = floor($total / 3) + 1;

echo "=== Testing Analytics API ===\n\n";
echo "Current server time: " . $now->format('H:i:s') . "\n";
echo "Current draw number: #{$currentDraw}\n\n";

// Test the API
$url = 'http://localhost/slipp/api/get_analytics_history.php?limit=8';
$response = file_get_contents($url);
$data = json_decode($response, true);

if ($data && $data['status'] === 'success') {
    echo "API Response:\n";
    echo "Current draw (from API): #" . ($data['data']['current_draw_number'] ?? 'N/A') . "\n";
    echo "Server time: " . ($data['data']['server_time'] ?? 'N/A') . "\n\n";
    
    echo "Recent draws:\n";
    if (isset($data['data']['draws']) && is_array($data['data']['draws'])) {
        foreach ($data['data']['draws'] as $draw) {
            $isCurrent = ($draw['draw_number'] == $currentDraw) ? ' ← CURRENT' : '';
            echo "  Draw #{$draw['draw_number']} at {$draw['draw_time']} - {$draw['winning_number']} ({$draw['winning_color']}) - Source: {$draw['source']}{$isCurrent}\n";
        }
        
        // Check if current draw is in the list
        $hasCurrent = false;
        foreach ($data['data']['draws'] as $draw) {
            if ($draw['draw_number'] == $currentDraw) {
                $hasCurrent = true;
                break;
            }
        }
        
        echo "\n";
        if ($hasCurrent) {
            echo "✓ Current draw (#{$currentDraw}) is included in the results\n";
        } else {
            echo "✗ Current draw (#{$currentDraw}) is NOT included in the results\n";
        }
    } else {
        echo "No draws in response\n";
    }
} else {
    echo "API Error: " . ($data['message'] ?? 'Unknown error') . "\n";
}

