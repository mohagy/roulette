<?php
/**
 * Direct Forced Number API
 * 
 * This API endpoint directly checks for forced numbers in the next_draw_winning_number table
 * and returns them in a simple format for the wheel to use.
 */

// Include database connection
require_once '../php/db_connect.php';

// Set headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Initialize response
$response = [
    'status' => 'error',
    'message' => 'An error occurred',
    'has_forced_number' => false,
    'forced_number' => null,
    'forced_color' => null,
    'draw_number' => null
];

// Function to get the color for a number
function getNumberColor($number) {
    $number = intval($number);
    
    if ($number === 0) {
        return 'green';
    }
    
    $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
    
    if (in_array($number, $redNumbers)) {
        return 'red';
    }
    
    return 'black';
}

try {
    // Check if a specific draw_number was requested
    $requestedDrawNumber = isset($_GET['draw_number']) ? (int)$_GET['draw_number'] : null;
    
    // ⏰ CRITICAL: Calculate current draw number based on SERVER TIME (same as get_current_draw.php)
    // This ensures consistency across all API endpoints
    date_default_timezone_set('America/Guyana');
    $now = new DateTime('now', new DateTimeZone('America/Guyana'));
    $currentHour = (int)$now->format('H');
    $currentMinute = (int)$now->format('i');
    $totalMinutesSinceMidnight = ($currentHour * 60) + $currentMinute;
    $drawIndex = floor($totalMinutesSinceMidnight / 3);
    $currentDrawNumber = $drawIndex + 1;
    
    // Cap at 480
    if ($currentDrawNumber > 480) {
        $currentDrawNumber = 480;
    }
    
    // If no specific draw was requested, check for next draw (current + 1)
    // This is because forced numbers are typically set for the upcoming draw
    if ($requestedDrawNumber === null) {
        $requestedDrawNumber = $currentDrawNumber + 1;
        if ($requestedDrawNumber > 480) {
            $requestedDrawNumber = 480;
        }
    }
    
    // Determine which draw number to check
    // If a specific draw_number was requested, use that; otherwise use the calculated next draw
    $checkDrawNumber = $requestedDrawNumber !== null ? $requestedDrawNumber : ($currentDrawNumber + 1);
    
    // Cap at 480
    if ($checkDrawNumber > 480) {
        $checkDrawNumber = 480;
    }
    
    // First check if there's a forced number for the requested/specified draw
    // Include source field to determine priority
    $stmt = $pdo->prepare("
        SELECT draw_number, winning_number, source, reason
        FROM next_draw_winning_number
        WHERE draw_number = ?
        LIMIT 1
    ");
    $stmt->execute([$checkDrawNumber]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Note: We already check the next draw by default (line 75), so no need for additional fallback
    
    // ⏰ CRITICAL: Always check preset schedule, even if database has a forced number
    // If preset schedule number differs from database, use preset (auto-apply should keep them in sync)
    $presetNumber = null;
    $checkPresetDraw = ($requestedDrawNumber !== null) ? $requestedDrawNumber : (($result) ? intval($result['draw_number']) : $checkDrawNumber);
    
    try {
        $presetStmt = $pdo->prepare("
            SELECT * FROM preset_schedule 
            WHERE start_draw_number <= ? AND end_draw_number >= ? AND is_active = 1 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $presetStmt->execute([$checkPresetDraw, $checkPresetDraw]);
        $presetSchedule = $presetStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($presetSchedule) {
            $scheduleData = json_decode($presetSchedule['schedule_data'], true);
            if (is_array($scheduleData) && !empty($scheduleData) && is_numeric($scheduleData[0])) {
                $startDrawNumber = (int)$presetSchedule['start_draw_number'];
                $index = $checkPresetDraw - $startDrawNumber;
                
                if ($index >= 0 && $index < count($scheduleData)) {
                    $presetNumber = (int)$scheduleData[$index];
                }
            }
        }
    } catch (Exception $e) {
        // Silently fail - preset is optional
    }
    
    // ⚠️ PRIORITY LOGIC:
    // 1. Manual forced numbers (source='manual') ALWAYS override preset schedule
    // 2. Preset schedule numbers ALWAYS override automatic forced numbers
    // 3. Automatic forced numbers only used if no preset schedule exists
    // 4. If no forced number and no preset, return no forced number
    
    $isManualForced = $result && isset($result['source']) && $result['source'] === 'manual';
    $isAutomaticForced = $result && isset($result['source']) && $result['source'] === 'automatic';
    $forcedNumber = $result ? intval($result['winning_number']) : null;
    
    // If manual forced number exists, it takes priority over everything
    if ($isManualForced && $forcedNumber !== null) {
        $response = [
            'status' => 'success',
            'message' => 'Manual forced number (overrides preset schedule)',
            'has_forced_number' => true,
            'forced_number' => $forcedNumber,
            'forced_color' => getNumberColor($forcedNumber),
            'draw_number' => $checkDrawNumber,
            'source' => 'manual',
            'forced_number_reason' => $result['reason'] ?? 'Set by administrator'
        ];
    } 
    // If preset exists, use it (preset overrides automatic forced numbers)
    // Only skip preset if it's a manual forced number (already handled above)
    else if ($presetNumber !== null && !$isManualForced) {
        // Use preset schedule number (this overrides automatic forced numbers)
        $response = [
            'status' => 'success',
            'message' => 'Preset number from schedule' . ($isAutomaticForced ? ' (overriding automatic forced number)' : ''),
            'has_forced_number' => true,
            'forced_number' => $presetNumber,
            'forced_color' => getNumberColor($presetNumber),
            'draw_number' => ($requestedDrawNumber !== null ? $requestedDrawNumber : $checkDrawNumber),
            'source' => 'preset_schedule'
        ];
    } 
    // Use automatic forced number only if no preset schedule exists
    else if ($result && !$isManualForced) {
        // Use database forced number
        $forcedNumber = intval($result['winning_number']);
        $drawNumber = intval($result['draw_number']);
        
        // If a specific draw was requested, only return if it matches
        // Otherwise, check if it's for current or upcoming draw (not old draws)
        if ($requestedDrawNumber !== null) {
            // Specific draw requested - return if it matches
            if ($drawNumber === $requestedDrawNumber) {
                $response = [
                    'status' => 'success',
                    'message' => 'Forced number found for requested draw',
                    'has_forced_number' => true,
                    'forced_number' => $forcedNumber,
                    'forced_color' => getNumberColor($forcedNumber),
                    'draw_number' => $drawNumber
                ];
            } else {
                // Draw number doesn't match requested - don't return it
                $response = [
                    'status' => 'success',
                    'message' => 'No forced number found for requested draw',
                    'has_forced_number' => false,
                    'forced_number' => null,
                    'forced_color' => null,
                    'draw_number' => $checkDrawNumber
                ];
            }
        } else {
            // No specific draw requested - check if it's for current or upcoming draw
            $nextDrawNumber = $currentDrawNumber + 1;
            $maxAllowedDraw = $currentDrawNumber + 2;
            
            if ($drawNumber >= $currentDrawNumber && $drawNumber <= $maxAllowedDraw) {
                $response = [
                    'status' => 'success',
                    'message' => 'Forced number found',
                    'has_forced_number' => true,
                    'forced_number' => $forcedNumber,
                    'forced_color' => getNumberColor($forcedNumber),
                    'draw_number' => $drawNumber
                ];
            } else {
                // Forced number exists but is for an old draw - don't show it
                $response = [
                    'status' => 'success',
                    'message' => 'No forced number found for current draw',
                    'has_forced_number' => false,
                    'forced_number' => null,
                    'forced_color' => null,
                    'draw_number' => $currentDrawNumber
                ];
            }
        }
    } else {
        // ✅ FALLBACK: Check preset schedule if no forced number found in next_draw_winning_number
        // This ensures presets work even when admin panel is closed (auto-apply not running)
        $presetNumber = null;
        try {
            // Try to get preset from preset_schedule
            $presetStmt = $pdo->prepare("
                SELECT * FROM preset_schedule 
                WHERE start_draw_number <= ? AND end_draw_number >= ? AND is_active = 1 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $presetStmt->execute([$checkDrawNumber, $checkDrawNumber]);
            $presetSchedule = $presetStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($presetSchedule) {
                // Decode schedule_data
                $scheduleData = json_decode($presetSchedule['schedule_data'], true);
                if (is_array($scheduleData) && !empty($scheduleData) && is_numeric($scheduleData[0])) {
                    $startDrawNumber = (int)$presetSchedule['start_draw_number'];
                    $index = $checkDrawNumber - $startDrawNumber;
                    
                    if ($index >= 0 && $index < count($scheduleData)) {
                        $presetNumber = (int)$scheduleData[$index];
                    }
                }
            }
        } catch (Exception $e) {
            // Silently fail - preset is optional fallback
        }
        
        if ($presetNumber !== null) {
            // Found preset number from schedule - always return it
            // This ensures preset numbers are always shown, even when no forced number exists
            $response = [
                'status' => 'success',
                'message' => 'Preset number from schedule',
                'has_forced_number' => true,
                'forced_number' => $presetNumber,
                'forced_color' => getNumberColor($presetNumber),
                'draw_number' => $checkDrawNumber,
                'source' => 'preset_schedule'
            ];
        } else {
            // No preset or forced number found
            $response = [
                'status' => 'success',
                'message' => 'No preset schedule or forced number found',
                'has_forced_number' => false,
                'forced_number' => null,
                'forced_color' => null,
                'draw_number' => $checkDrawNumber
            ];
        }
    }
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

// Return the response
echo json_encode($response);
