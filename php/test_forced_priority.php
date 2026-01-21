<?php
/**
 * Test Forced Number Priority System
 * Verifies that manual forced numbers override preset schedule
 */

require_once 'db_connect.php';

date_default_timezone_set('America/Guyana');
$now = new DateTime('now', new DateTimeZone('America/Guyana'));
$h = (int)$now->format('H');
$m = (int)$now->format('i');
$total = ($h * 60) + $m;
$currentDraw = floor($total / 3) + 1;
$nextDraw = $currentDraw + 1;

echo "=== Testing Forced Number Priority ===\n\n";
echo "Current time: " . $now->format('H:i:s') . "\n";
echo "Current draw: #{$currentDraw}\n";
echo "Next draw: #{$nextDraw}\n\n";

// Check preset schedule for next draw
$presetStmt = $pdo->prepare("
    SELECT * FROM preset_schedule 
    WHERE start_draw_number <= ? AND end_draw_number >= ? AND is_active = 1 
    ORDER BY created_at DESC 
    LIMIT 1
");
$presetStmt->execute([$nextDraw, $nextDraw]);
$preset = $presetStmt->fetch(PDO::FETCH_ASSOC);

$presetNumber = null;
if ($preset) {
    $scheduleData = json_decode($preset['schedule_data'], true);
    if (is_array($scheduleData)) {
        $startDraw = (int)$preset['start_draw_number'];
        $index = $nextDraw - $startDraw;
        if ($index >= 0 && $index < count($scheduleData)) {
            $presetNumber = is_array($scheduleData[$index]) 
                ? intval($scheduleData[$index]['winning_number'] ?? $scheduleData[$index])
                : intval($scheduleData[$index]);
        }
    }
}

echo "Preset schedule number for draw #{$nextDraw}: " . ($presetNumber !== null ? $presetNumber : 'N/A') . "\n";

// Check forced number
$forcedStmt = $pdo->prepare("
    SELECT draw_number, winning_number, source, reason
    FROM next_draw_winning_number
    WHERE draw_number = ?
    LIMIT 1
");
$forcedStmt->execute([$nextDraw]);
$forced = $forcedStmt->fetch(PDO::FETCH_ASSOC);

if ($forced) {
    echo "Forced number for draw #{$nextDraw}: {$forced['winning_number']} (source: {$forced['source']})\n";
    echo "Reason: " . ($forced['reason'] ?? 'N/A') . "\n\n";
    
    // Test priority logic
    $isManual = isset($forced['source']) && $forced['source'] === 'manual';
    
    if ($isManual) {
        echo "✓ PRIORITY: Manual forced number will be used\n";
        echo "  → Draw #{$nextDraw} will use: {$forced['winning_number']} (manual override)\n";
        if ($presetNumber !== null && $presetNumber != $forced['winning_number']) {
            echo "  → Preset schedule number ({$presetNumber}) will be IGNORED\n";
        }
    } else {
        echo "⚠ PRIORITY: Automatic forced number\n";
        if ($presetNumber !== null && $presetNumber != $forced['winning_number']) {
            echo "  → Preset schedule ({$presetNumber}) will override automatic forced number\n";
        } else {
            echo "  → Automatic forced number will be used\n";
        }
    }
} else {
    echo "No forced number set for draw #{$nextDraw}\n\n";
    if ($presetNumber !== null) {
        echo "✓ PRIORITY: Preset schedule will be used\n";
        echo "  → Draw #{$nextDraw} will use: {$presetNumber} (from preset schedule)\n";
    } else {
        echo "⚠ No preset or forced number - draw will be random\n";
    }
}

// Test the API
echo "\n=== Testing API Response ===\n";
$url = "http://localhost/slipp/api/direct_forced_number.php?draw_number={$nextDraw}";
$response = file_get_contents($url);
$data = json_decode($response, true);

if ($data && $data['status'] === 'success') {
    echo "API Response:\n";
    echo "  Has forced number: " . ($data['has_forced_number'] ? 'Yes' : 'No') . "\n";
    if ($data['has_forced_number']) {
        echo "  Number: {$data['forced_number']}\n";
        echo "  Color: {$data['forced_color']}\n";
        echo "  Source: {$data['source']}\n";
        echo "  Message: {$data['message']}\n";
        
        // Verify priority
        if ($forced && $forced['source'] === 'manual') {
            if ($data['source'] === 'manual' && $data['forced_number'] == $forced['winning_number']) {
                echo "\n✓ SUCCESS: Manual forced number correctly overrides preset!\n";
            } else {
                echo "\n✗ ERROR: Manual forced number should override preset but API returned different result!\n";
            }
        }
    }
} else {
    echo "API Error: " . ($data['message'] ?? 'Unknown error') . "\n";
}

