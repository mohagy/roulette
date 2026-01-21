<?php
/**
 * Get Current Draw API
 *
 * This API endpoint gets the current draw number and status.
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
    'data' => []
];

try {
    // ⏰ CRITICAL: Calculate draw number based on SERVER TIME (Georgetown timezone)
    // This ensures all devices get the same draw number regardless of their local clock
    date_default_timezone_set('America/Guyana');
    $now = new DateTime('now', new DateTimeZone('America/Guyana'));
    $currentHour = (int)$now->format('H');
    $currentMinute = (int)$now->format('i');
    $currentSecond = (int)$now->format('s');
    
    // Calculate draw number based on 3-minute intervals starting at midnight
    // Draw #1 = 00:00-00:02:59, Draw #2 = 00:03-00:05:59, Draw #3 = 00:06-00:08:59, etc. (480 draws per day)
    // At 7:36, we're at the START of draw #153's time slot, but we want to show draw #152 as current
    // (the draw that just completed or is completing)
    $totalMinutesSinceMidnight = ($currentHour * 60) + $currentMinute;
    $drawIndex = floor($totalMinutesSinceMidnight / 3);
    
    // If we're at the exact start of a draw (minute is divisible by 3 and seconds are low),
    // show the previous draw as "current" (the one that just completed)
    if ($currentMinute % 3 == 0 && $currentSecond < 30) {
        // We're at the start of a new draw, so show the previous one as current
        $drawIndex = $drawIndex - 1;
    }
    
    $serverTimeBasedDrawNumber = $drawIndex + 1; // Convert to 1-based
    
    // Ensure draw number is at least 1
    if ($serverTimeBasedDrawNumber < 1) {
        $serverTimeBasedDrawNumber = 1;
    }
    
    // ⚠️ CRITICAL: Cap draw number at 480 (max draws per day)
    // After 23:57 (draw #480), it should reset to 1 the next day
    if ($serverTimeBasedDrawNumber > 480) {
        $serverTimeBasedDrawNumber = 480; // Cap at 480 for safety
    }
    
    // Also get stored draw number from database for reference
    $stmt = $pdo->prepare("
        SELECT current_draw_number FROM roulette_analytics LIMIT 1
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // Use server-time-based draw number as the source of truth
    // This ensures synchronization across all devices
    if ($result) {
        $storedDrawNumber = intval($result['current_draw_number']);
        // Use server time calculation as primary, database as fallback
        $currentDrawNumber = $serverTimeBasedDrawNumber;
        
        // If database draw number is significantly different, log it (might indicate issue)
        if (abs($currentDrawNumber - $storedDrawNumber) > 5) {
            error_log("Draw number mismatch: Server-time-based=$currentDrawNumber, Database=$storedDrawNumber");
        }

        // Get the forced number for the current draw
        $stmt = $pdo->prepare("
            SELECT winning_number, source, reason
            FROM next_draw_winning_number
            WHERE draw_number = ? LIMIT 1
        ");
        $stmt->execute([$currentDrawNumber]);
        $forcedNumber = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get the draw mode
        $stmt = $pdo->prepare("
            SELECT setting_value, automatic_mode
            FROM roulette_settings
            WHERE setting_name = 'draw_mode' LIMIT 1
        ");
        $stmt->execute();
        $drawMode = $stmt->fetch(PDO::FETCH_ASSOC);

        // Determine if automatic mode is enabled
        $isAutomatic = true;
        if ($drawMode) {
            $isAutomatic = $drawMode['automatic_mode'] == 1 || $drawMode['setting_value'] == 'automatic';
        }

        // Calculate next draw number
        $nextDrawNumber = ($currentDrawNumber >= 480) ? 1 : ($currentDrawNumber + 1);
        
        // Prepare response data
        $data = [
            'current_draw_number' => $currentDrawNumber,
            'next_draw_number' => $nextDrawNumber,
            'is_automatic' => $isAutomatic,
            'has_forced_number' => ($forcedNumber !== false),
            'forced_number' => $forcedNumber ? intval($forcedNumber['winning_number']) : null,
            'forced_number_source' => $forcedNumber ? $forcedNumber['source'] : null,
            'forced_number_reason' => $forcedNumber ? $forcedNumber['reason'] : null,
            // ⏰ CRITICAL: Include server time info so clients can sync
            'server_time' => [
                'formatted' => $now->format('Y-m-d H:i:s'),
                'timezone' => 'America/Guyana',
                'hour' => $currentHour,
                'minute' => $currentMinute,
                'second' => $currentSecond,
                'total_minutes_since_midnight' => $totalMinutesSinceMidnight
            ],
            'draw_number_source' => 'server_time_calculated' // Indicates this is from server time, not database
        ];

        // If there's a forced number, add the color
        if ($forcedNumber) {
            $number = intval($forcedNumber['winning_number']);
            if ($number === 0) {
                $data['forced_number_color'] = 'green';
            } else {
                $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
                $data['forced_number_color'] = in_array($number, $redNumbers) ? 'red' : 'black';
            }
        }

        // Set success response
        $response['status'] = 'success';
        $response['message'] = 'Current draw information retrieved successfully';
        $response['data'] = $data;
    } else {
        // No draw number found in database, use server-time-based calculation
        $response['status'] = 'success';
        $response['message'] = 'No draw number in database, using server-time-based calculation';
        // Calculate next draw number
        $nextDrawNumber = ($serverTimeBasedDrawNumber >= 480) ? 1 : ($serverTimeBasedDrawNumber + 1);
        
        $response['data'] = [
            'current_draw_number' => $serverTimeBasedDrawNumber,
            'next_draw_number' => $nextDrawNumber,
            'is_automatic' => true,
            'has_forced_number' => false,
            'forced_number' => null,
            'forced_number_source' => null,
            'forced_number_reason' => null,
            'server_time' => [
                'formatted' => $now->format('Y-m-d H:i:s'),
                'timezone' => 'America/Guyana',
                'hour' => $currentHour,
                'minute' => $currentMinute,
                'second' => $currentSecond,
                'total_minutes_since_midnight' => $totalMinutesSinceMidnight
            ],
            'draw_number_source' => 'server_time_calculated'
        ];
    }

} catch (PDOException $e) {
    // Set error response
    $response['message'] = 'Database error: ' . $e->getMessage();
}

// Return the response
echo json_encode($response);
