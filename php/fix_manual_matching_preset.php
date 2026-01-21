<?php
/**
 * Fix Analytics History: Update entries where manually forced numbers match preset schedule
 * 
 * This script checks analytics_history for entries with source='manual' and updates them
 * to source='preset_schedule' if the winning number matches the preset schedule.
 */

require_once 'db_connect.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Fix Manual Entries Matching Preset Schedule</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .warning { color: orange; }
    .error { color: red; }
    .info { color: blue; }
</style>";

try {
    // Get active preset schedule
    $presetStmt = $pdo->prepare("
        SELECT id, schedule_data, start_draw_number, end_draw_number, pattern_type
        FROM preset_schedule
        WHERE is_active = 1
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $presetStmt->execute();
    $preset = $presetStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$preset) {
        echo "<p class='warning'>⚠ No active preset schedule found.</p>";
        exit;
    }
    
    echo "<p class='info'>ℹ Found active preset schedule: Draws #{$preset['start_draw_number']} to #{$preset['end_draw_number']}</p>";
    
    $scheduleData = json_decode($preset['schedule_data'], true);
    if (!is_array($scheduleData)) {
        echo "<p class='error'>❌ Invalid schedule data format.</p>";
        exit;
    }
    
    $startDraw = intval($preset['start_draw_number']);
    $endDraw = intval($preset['end_draw_number']);
    
    // Get all manual entries from analytics_history within preset range
    $manualStmt = $pdo->prepare("
        SELECT id, draw_number, winning_number, winning_color, draw_time, source
        FROM analytics_history
        WHERE source = 'manual'
          AND draw_number >= ?
          AND draw_number <= ?
        ORDER BY draw_number ASC
    ");
    $manualStmt->execute([$startDraw, $endDraw]);
    $manualEntries = $manualStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p class='info'>ℹ Found " . count($manualEntries) . " manual entries in preset range.</p>";
    
    $updatedCount = 0;
    $skippedCount = 0;
    
    foreach ($manualEntries as $entry) {
        $drawNum = intval($entry['draw_number']);
        $winningNumber = intval($entry['winning_number']);
        $index = $drawNum - $startDraw;
        
        if ($index < 0 || $index >= count($scheduleData)) {
            echo "<p class='warning'>⚠ Draw #{$drawNum} is outside preset schedule range (index: {$index}).</p>";
            $skippedCount++;
            continue;
        }
        
        $presetEntry = $scheduleData[$index];
        $presetNumber = is_array($presetEntry) ? intval($presetEntry['winning_number'] ?? $presetEntry) : intval($presetEntry);
        
        if ($winningNumber === $presetNumber) {
            // Manual number matches preset - update to preset_schedule
            $updateStmt = $pdo->prepare("
                UPDATE analytics_history
                SET source = 'preset_schedule',
                    preset_schedule_id = ?,
                    is_preset = 1,
                    pattern_type = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([
                $preset['id'],
                $preset['pattern_type'],
                $entry['id']
            ]);
            
            echo "<p class='success'>✅ Updated Draw #{$drawNum}: Number {$winningNumber} matches preset → changed to preset_schedule</p>";
            $updatedCount++;
        } else {
            echo "<p class='info'>ℹ Draw #{$drawNum}: Number {$winningNumber} does NOT match preset ({$presetNumber}) → keeping as manual</p>";
            $skippedCount++;
        }
    }
    
    echo "<hr>";
    echo "<h2>Summary</h2>";
    echo "<p class='success'>✅ Updated: {$updatedCount} entries</p>";
    echo "<p class='info'>ℹ Skipped: {$skippedCount} entries (didn't match preset or outside range)</p>";
    echo "<p class='success'>✅ Done!</p>";
    
} catch (PDOException $e) {
    echo "<p class='error'>❌ Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

