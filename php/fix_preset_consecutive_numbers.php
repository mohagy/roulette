<?php
/**
 * Fix Preset Schedule: Remove 3+ Consecutive Identical Numbers
 * 
 * This script ensures no more than 2 consecutive draws have the same winning number
 * in the preset schedule, which looks abnormal to live audiences.
 */

require_once 'db_connect.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Fix Preset Schedule: Remove 3+ Consecutive Identical Numbers</h1>";
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
    
    $originalSchedule = $scheduleData;
    $fixedCount = 0;
    $fixedDraws = [];
    
    echo "<p class='info'>ℹ Processing {$preset['end_draw_number']} draws...</p>";
    
    // Check for 3+ consecutive identical numbers and fix them
    for ($i = 2; $i < count($scheduleData); $i++) {
        $current = is_array($scheduleData[$i]) ? intval($scheduleData[$i]['winning_number'] ?? $scheduleData[$i]) : intval($scheduleData[$i]);
        $prev1 = is_array($scheduleData[$i - 1]) ? intval($scheduleData[$i - 1]['winning_number'] ?? $scheduleData[$i - 1]) : intval($scheduleData[$i - 1]);
        $prev2 = is_array($scheduleData[$i - 2]) ? intval($scheduleData[$i - 2]['winning_number'] ?? $scheduleData[$i - 2]) : intval($scheduleData[$i - 2]);
        
        // Check if we have 3+ consecutive identical numbers
        if ($current === $prev1 && $prev1 === $prev2) {
            $drawNumber = $preset['start_draw_number'] + $i;
            
            // Find a different number that's not the same as current/prev1
            $rouletteNumbers = range(0, 36);
            $alternativeNumbers = array_filter($rouletteNumbers, function($n) use ($current, $prev1) {
                return $n !== $current && $n !== $prev1;
            });
            
            if (empty($alternativeNumbers)) {
                // Fallback: use any number except current
                $alternativeNumbers = array_filter($rouletteNumbers, function($n) use ($current) {
                    return $n !== $current;
                });
            }
            
            // Try to maintain color if possible
            $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
            $isCurrentRed = in_array($current, $redNumbers);
            $sameColorNumbers = $isCurrentRed ? $redNumbers : ($current === 0 ? [0] : array_diff($rouletteNumbers, array_merge($redNumbers, [0])));
            
            $sameColorAlternatives = array_intersect($alternativeNumbers, $sameColorNumbers);
            if (!empty($sameColorAlternatives)) {
                // Use same color but different number
                $newNumber = array_values($sameColorAlternatives)[($i * 3) % count($sameColorAlternatives)];
            } else {
                // Use any alternative number
                $newNumber = array_values($alternativeNumbers)[($i * 7) % count($alternativeNumbers)];
            }
            
            // Update the schedule data
            if (is_array($scheduleData[$i])) {
                $scheduleData[$i]['winning_number'] = $newNumber;
                if (isset($scheduleData[$i]['color'])) {
                    $scheduleData[$i]['color'] = $newNumber === 0 ? 'green' : (in_array($newNumber, $redNumbers) ? 'red' : 'black');
                }
            } else {
                $scheduleData[$i] = $newNumber;
            }
            
            $fixedCount++;
            $fixedDraws[] = [
                'draw' => $drawNumber,
                'old' => $current,
                'new' => $newNumber
            ];
            
            echo "<p class='warning'>⚠ Draw #{$drawNumber}: Changed {$current} → {$newNumber} (prevented 3+ consecutive)</p>";
        }
    }
    
    if ($fixedCount > 0) {
        // Update the preset schedule in database
        $updateStmt = $pdo->prepare("
            UPDATE preset_schedule
            SET schedule_data = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        // Convert schedule data back to simple array if needed (for API compatibility)
        $scheduleDataSimple = array_map(function($item) {
            return is_array($item) ? intval($item['winning_number'] ?? $item) : intval($item);
        }, $scheduleData);
        
        $updateStmt->execute([
            json_encode($scheduleDataSimple),
            $preset['id']
        ]);
        
        echo "<hr>";
        echo "<h2>Summary</h2>";
        echo "<p class='success'>✅ Fixed: {$fixedCount} draws</p>";
        echo "<p class='info'>ℹ Updated preset schedule in database.</p>";
        
        if (count($fixedDraws) > 0) {
            echo "<h3>Fixed Draws:</h3>";
            echo "<ul>";
            foreach (array_slice($fixedDraws, 0, 20) as $fixed) {
                echo "<li>Draw #{$fixed['draw']}: {$fixed['old']} → {$fixed['new']}</li>";
            }
            if (count($fixedDraws) > 20) {
                echo "<li>... and " . (count($fixedDraws) - 20) . " more</li>";
            }
            echo "</ul>";
        }
        
        echo "<p class='success'>✅ Done! Preset schedule now has no more than 2 consecutive identical numbers.</p>";
    } else {
        echo "<p class='success'>✅ No issues found. Preset schedule already complies with the 2-consecutive rule.</p>";
    }
    
} catch (PDOException $e) {
    echo "<p class='error'>❌ Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

