<?php
/**
 * Get Analytics History API
 * 
 * Returns analytics data from analytics_history table
 * Uses preset_schedule and server time for accurate draw information
 */

require_once '../php/db_connect.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$response = [
    'status' => 'error',
    'message' => 'An error occurred',
    'data' => []
];

try {
    // Set server timezone
    date_default_timezone_set('America/Guyana');
    
    // Get limit parameter (default to 8 for last 8 spins)
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 8;
    $limit = max(1, min(50, $limit));
    
    // Calculate current draw number based on server time
    $now = new DateTime('now', new DateTimeZone('America/Guyana'));
    $currentHour = (int)$now->format('H');
    $currentMinute = (int)$now->format('i');
    $totalMinutesSinceMidnight = ($currentHour * 60) + $currentMinute;
    $drawIndex = floor($totalMinutesSinceMidnight / 3);
    $currentDrawNumber = $drawIndex + 1;
    
    // Get recent draws from analytics_history
    // This table contains draws with server time and preset schedule data
    // Check if table exists first
    $tableExists = false;
    try {
        $checkStmt = $pdo->query("SHOW TABLES LIKE 'analytics_history'");
        $tableExists = $checkStmt->rowCount() > 0;
    } catch (PDOException $e) {
        // Table doesn't exist yet
    }
    
    $draws = [];
    if ($tableExists) {
        // Get the most recent draws from analytics_history
        // ⚠️ CRITICAL: Only get draws from TODAY with draw_number <= 480 (max draws per day)
        // Draw numbers reset daily: 1-480 (3-minute intervals = 480 draws per day)
        // We need to filter by today's date to ensure we get today's draws, not yesterday's
        $today = $now->format('Y-m-d');
        $limitInt = intval($limit);
        $stmt = $pdo->prepare("
            SELECT 
                draw_number,
                winning_number,
                winning_color,
                draw_time,
                source,
                is_preset,
                pattern_type,
                scheduled_time
            FROM analytics_history
            WHERE DATE(draw_time) = ? 
              AND draw_number >= 1 
              AND draw_number <= 480
            ORDER BY draw_number DESC
            LIMIT {$limitInt}
        ");
        
        $stmt->execute([$today]);
        $draws = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If we don't have enough draws from today, it's okay - just return what we have
        // This ensures we only show today's draws that match the current server-time-based draw numbers
    }
    
    // ⚠️ CRITICAL: Check for manually forced numbers that might not be in analytics_history yet
    // This ensures manually set numbers are shown even if the draw hasn't been completed/saved yet
    // BUT: If a manually forced number matches the preset schedule, treat it as preset, not manual
    $existingDrawNumbers = array_column($draws, 'draw_number');
    $startDrawNumber = max(1, $currentDrawNumber - $limit + 1);
    
    // Get preset schedule data to check if manual numbers match
    $presetCheckStmt = $pdo->prepare("
        SELECT schedule_data, start_draw_number, id, pattern_type
        FROM preset_schedule
        WHERE is_active = 1
          AND start_draw_number <= ?
          AND end_draw_number >= ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $presetCheckStmt->execute([$currentDrawNumber, $startDrawNumber]);
    $presetCheck = $presetCheckStmt->fetch(PDO::FETCH_ASSOC);
    $presetScheduleData = null;
    $presetStartDraw = null;
    if ($presetCheck) {
        $presetScheduleData = json_decode($presetCheck['schedule_data'], true);
        $presetStartDraw = intval($presetCheck['start_draw_number']);
    }
    
    // Check for manually forced numbers in next_draw_winning_number
    $forcedStmt = $pdo->prepare("
        SELECT draw_number, winning_number, source, reason
        FROM next_draw_winning_number
        WHERE draw_number >= ? AND draw_number <= ?
          AND source = 'manual'
        ORDER BY draw_number DESC
    ");
    $forcedStmt->execute([$startDrawNumber, $currentDrawNumber]);
    $forcedNumbers = $forcedStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add or update draws with manually forced numbers
    foreach ($forcedNumbers as $forced) {
        $drawNum = intval($forced['draw_number']);
        $winningNumber = intval($forced['winning_number']);
        
        // Check if this manual number matches the preset schedule
        $matchesPreset = false;
        $presetScheduleId = null;
        $patternType = null;
        
        if ($presetCheck && $presetScheduleData && is_array($presetScheduleData)) {
            $index = $drawNum - $presetStartDraw;
            if ($index >= 0 && $index < count($presetScheduleData)) {
                $presetEntry = $presetScheduleData[$index];
                $presetNumber = is_array($presetEntry) ? intval($presetEntry['winning_number'] ?? $presetEntry) : intval($presetEntry);
                
                if ($winningNumber === $presetNumber) {
                    $matchesPreset = true;
                    $presetScheduleId = $presetCheck['id'];
                    $patternType = $presetCheck['pattern_type'];
                }
            }
        }
        
        // Determine color
        $winningColor = 'green';
        if ($winningNumber > 0) {
            $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
            $winningColor = in_array($winningNumber, $redNumbers) ? 'red' : 'black';
        }
        
        // Calculate draw time
        $drawMinutes = ($drawNum - 1) * 3;
        $drawHour = floor($drawMinutes / 60);
        $drawMin = $drawMinutes % 60;
        $drawTime = clone $now;
        $drawTime->setTime($drawHour, $drawMin, 0);
        
        // Check if this draw already exists in results
        $existingIndex = array_search($drawNum, $existingDrawNumbers);
        if ($existingIndex !== false) {
            // Update existing entry
            // If manual number matches preset, treat as preset; otherwise treat as manual
            $draws[$existingIndex]['winning_number'] = $winningNumber;
            $draws[$existingIndex]['winning_color'] = $winningColor;
            if ($matchesPreset) {
                $draws[$existingIndex]['source'] = 'preset_schedule';
                $draws[$existingIndex]['is_preset'] = 1;
                $draws[$existingIndex]['pattern_type'] = $patternType;
            } else {
                $draws[$existingIndex]['source'] = 'manual';
                $draws[$existingIndex]['is_preset'] = 0;
            }
        } else {
            // Add new entry
            $draws[] = [
                'draw_number' => $drawNum,
                'winning_number' => $winningNumber,
                'winning_color' => $winningColor,
                'draw_time' => $drawTime->format('Y-m-d H:i:s'),
                'source' => $matchesPreset ? 'preset_schedule' : 'manual',
                'is_preset' => $matchesPreset ? 1 : 0,
                'pattern_type' => $matchesPreset ? $patternType : null,
                'scheduled_time' => $matchesPreset ? $drawTime->format('Y-m-d H:i:s') : null,
                'pattern' => null
            ];
            $existingDrawNumbers[] = $drawNum;
        }
    }
    
    // Only check preset_schedule to fill in missing draws that haven't been completed yet
    // This ensures we show upcoming draws, but actual results take priority
    $presetStmt = $pdo->prepare("
        SELECT 
            ps.id as preset_id,
            ps.start_draw_number,
            ps.end_draw_number,
            ps.schedule_data,
            ps.pattern_type,
            ps.time_preset
        FROM preset_schedule ps
        WHERE ps.is_active = 1
          AND ps.start_draw_number <= ?
          AND ps.end_draw_number >= ?
        ORDER BY ps.created_at DESC
        LIMIT 1
    ");
    
    $presetStmt->execute([$currentDrawNumber, $startDrawNumber]);
    $preset = $presetStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($preset) {
        $scheduleData = json_decode($preset['schedule_data'], true);
        if (is_array($scheduleData)) {
            // Get draws from preset that aren't already in results (and haven't been manually forced)
            // Start from current draw and go backwards
            for ($drawNum = $currentDrawNumber; $drawNum >= $startDrawNumber && count($draws) < $limit; $drawNum--) {
                if (!in_array($drawNum, $existingDrawNumbers)) {
                    $index = $drawNum - $preset['start_draw_number'];
                    if ($index >= 0 && $index < count($scheduleData)) {
                        $scheduleItem = $scheduleData[$index];
                        $winningNumber = is_array($scheduleItem) 
                            ? intval($scheduleItem['winning_number'] ?? $scheduleItem)
                            : intval($scheduleItem);
                        
                        // Determine color
                        $winningColor = 'green';
                        if ($winningNumber > 0) {
                            $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
                            $winningColor = in_array($winningNumber, $redNumbers) ? 'red' : 'black';
                        }
                        
                        // Calculate draw time based on server time
                        $drawMinutes = ($drawNum - 1) * 3;
                        $drawHour = floor($drawMinutes / 60);
                        $drawMin = $drawMinutes % 60;
                        $drawTime = clone $now;
                        $drawTime->setTime($drawHour, $drawMin, 0);
                        
                        // Get pattern if available
                        $pattern = null;
                        if (is_array($scheduleItem) && isset($scheduleItem['pattern'])) {
                            $pattern = $scheduleItem['pattern'];
                        }
                        
                        // Add to results (insert at beginning to maintain DESC order)
                        array_unshift($draws, [
                            'draw_number' => $drawNum,
                            'winning_number' => $winningNumber,
                            'winning_color' => $winningColor,
                            'draw_time' => $drawTime->format('Y-m-d H:i:s'),
                            'source' => 'preset_schedule',
                            'is_preset' => 1,
                            'pattern_type' => $preset['pattern_type'],
                            'scheduled_time' => $drawTime->format('Y-m-d H:i:s'),
                            'pattern' => $pattern
                        ]);
                        $existingDrawNumbers[] = $drawNum; // Update existing list
                    }
                }
            }
        }
    }
    
    // Sort by draw_number DESC and limit
    usort($draws, function($a, $b) {
        return $b['draw_number'] - $a['draw_number'];
    });
    $draws = array_slice($draws, 0, $limit);
    
    // Format response
    $formattedDraws = [];
    foreach ($draws as $draw) {
        $formattedDraws[] = [
            'draw_number' => intval($draw['draw_number']),
            'winning_number' => intval($draw['winning_number']),
            'winning_color' => $draw['winning_color'],
            'draw_time' => $draw['draw_time'],
            'source' => $draw['source'] ?? 'unknown',
            'is_preset' => isset($draw['is_preset']) ? (bool)$draw['is_preset'] : false,
            'pattern_type' => $draw['pattern_type'] ?? null,
            'scheduled_time' => $draw['scheduled_time'] ?? null
        ];
    }
    
    $response['status'] = 'success';
    $response['message'] = 'Analytics history retrieved successfully';
    $response['data'] = [
        'draws' => $formattedDraws,
        'current_draw_number' => $currentDrawNumber,
        'server_time' => $now->format('Y-m-d H:i:s'),
        'server_timezone' => 'America/Guyana'
    ];
    
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);

