<?php
/**
 * Upcoming Draws Statistics API
 * Provides upcoming draw numbers with betting slip counts and stake amounts
 * for the cashier interface upcoming draws panel
 */

// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json');

// Include database connection
require_once '../php/db_connect.php';

// Default response
$response = [
    'status' => 'error',
    'message' => 'Failed to fetch upcoming draws information',
    'timestamp' => time()
];

/**
 * Log messages for debugging
 */
function logUpcomingDraws($message, $type = 'INFO') {
    $logFile = '../logs/upcoming_draws_stats.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$type] $message\n";

    // Ensure logs directory exists
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

/**
 * Get the last completed draw number from database
 */
function getLastCompletedDraw($conn) {
    try {
        $stmt = $conn->prepare("SELECT MAX(draw_number) as max_completed_draw FROM detailed_draw_results");
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return (int)($row['max_completed_draw'] ?? 0);
        }

        return 0;
    } catch (Exception $e) {
        logUpcomingDraws("Error getting last completed draw: " . $e->getMessage(), 'ERROR');
        return 0;
    }
}

/**
 * Get betting slip statistics for a specific draw number
 */
function getDrawSlipStats($conn, $drawNumber) {
    $stats = [
        'betting_slips_count' => 0,
        'total_stake_amount' => 0.00,
        'total_potential_payout' => 0.00
    ];

    try {
        // Get betting slip counts and amounts for this draw
        $stmt = $conn->prepare("
            SELECT
                COUNT(*) as slip_count,
                COALESCE(SUM(total_stake), 0) as total_stake,
                COALESCE(SUM(potential_payout), 0) as total_potential_payout
            FROM betting_slips
            WHERE draw_number = ?
            AND is_cancelled = 0
        ");

        $stmt->bind_param("i", $drawNumber);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stats['betting_slips_count'] = (int)$row['slip_count'];
            $stats['total_stake_amount'] = (float)$row['total_stake'];
            $stats['total_potential_payout'] = (float)$row['total_potential_payout'];
        }

        $stmt->close();

    } catch (Exception $e) {
        logUpcomingDraws("Error getting slip stats for draw $drawNumber: " . $e->getMessage(), 'ERROR');
    }

    return $stats;
}

/**
 * Get scheduled time for a draw number from preset schedule
 */
function getScheduledTimeForDraw($conn, $drawNumber) {
    try {
        // Use Guyana timezone (UTC-4)
        date_default_timezone_set('America/Guyana');
        $today = date('Y-m-d');
        
        // Check if preset schedule exists for today
        $stmt = $conn->prepare("
            SELECT schedule_data, start_draw_number, end_draw_number
            FROM preset_schedule
            WHERE schedule_date = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $schedule = $result->fetch_assoc();
            $startDraw = (int)$schedule['start_draw_number'];
            $endDraw = (int)$schedule['end_draw_number'];
            
            // Check if draw number is in this schedule's range
            if ($drawNumber >= $startDraw && $drawNumber <= $endDraw) {
                // Calculate time based on draw number position
                // Each draw is 3 minutes apart, starting from midnight
                $drawIndex = $drawNumber - $startDraw;
                $totalMinutes = $drawIndex * 3;
                $hours = floor($totalMinutes / 60);
                $minutes = $totalMinutes % 60;
                
                // Create time string
                $timeString = sprintf('%02d:%02d', $hours, $minutes);
                
                $stmt->close();
                return $timeString;
            }
        }
        $stmt->close();
    } catch (Exception $e) {
        logUpcomingDraws("Error getting scheduled time: " . $e->getMessage(), 'ERROR');
    }
    
    // Fallback: Calculate based on draw number (assuming draws start at midnight, every 3 minutes)
    // Draw #1 = 00:00, Draw #2 = 00:03, etc. (max 480 draws per day)
    $drawNumberInDay = (($drawNumber - 1) % 480) + 1;
    $totalMinutes = ($drawNumberInDay - 1) * 3;
    $hours = floor($totalMinutes / 60) % 24;
    $minutes = $totalMinutes % 60;
    return sprintf('%02d:%02d', $hours, $minutes);
}

/**
 * Generate upcoming draws with statistics
 */
function generateUpcomingDrawsWithStats($conn, $baseDrawNumber, $count = 10) {
    $upcomingDraws = [];
    // Use Guyana timezone (UTC-4)
    date_default_timezone_set('America/Guyana');
    $currentTime = new DateTime('now', new DateTimeZone('America/Guyana'));
    $currentDate = $currentTime->format('Y-m-d');
    
    // Normalize baseDrawNumber to be within 1-480 (daily draw range)
    // If baseDrawNumber > 480, it means we're past midnight, so wrap it
    $baseDrawInDay = (($baseDrawNumber - 1) % 480) + 1;

    for ($i = 0; $i < $count; $i++) {
        // Calculate draw number with wrap-around at 480
        // Start from baseDrawNumber (i=0), then baseDrawNumber+1 (i=1), etc.
        $rawDrawNumber = $baseDrawInDay + $i;
        
        // If we exceed 480, wrap to next day (starts at 1)
        if ($rawDrawNumber > 480) {
            $drawNumber = $rawDrawNumber - 480; // Wrap to 1-480 for next day
            $isNextDay = true;
        } else {
            $drawNumber = $rawDrawNumber; // Still in current day (1-480)
            $isNextDay = false;
        }

        // Calculate time based on draw number (draw #1 = 00:00, draw #480 = 23:57)
        $totalMinutes = ($drawNumber - 1) * 3;
        $hours = floor($totalMinutes / 60) % 24;
        $minutes = $totalMinutes % 60;
        $scheduledTime = sprintf('%02d:%02d', $hours, $minutes);
        
        // Create scheduled datetime
        $scheduledDate = $isNextDay ? 
            (clone $currentTime)->modify('+1 day')->format('Y-m-d') : 
            $currentDate;
        $scheduledDateTime = new DateTime($scheduledDate . ' ' . $scheduledTime . ':00', new DateTimeZone('America/Guyana'));
        
        // Calculate minutes from now
        $diff = $currentTime->diff($scheduledDateTime);
        $minutesFromNow = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
        
        // If negative (in the past), it's at least tomorrow
        if ($minutesFromNow < 0) {
            $scheduledDateTime->modify('+1 day');
            $diff = $currentTime->diff($scheduledDateTime);
            $minutesFromNow = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
        }

        // Get betting slip statistics for this draw
        // Use the actual draw number for database queries (may be > 480 for historical queries)
        $stats = getDrawSlipStats($conn, $drawNumber);

        $upcomingDraws[] = [
            'draw_number' => $drawNumber, // Always 1-480 for display
            'estimated_time' => $scheduledTime,
            'estimated_datetime' => $scheduledDateTime->format('Y-m-d H:i:s'),
            'betting_slips_count' => $stats['betting_slips_count'],
            'total_stake_amount' => $stats['total_stake_amount'],
            'total_potential_payout' => $stats['total_potential_payout'],
            'is_next' => ($i === 0), // First draw (i=0) is the next draw
            'minutes_from_now' => $minutesFromNow,
            'is_next_day' => $isNextDay
        ];
    }

    return $upcomingDraws;
}

/**
 * Get system statistics
 */
function getSystemStats($conn) {
    $stats = [
        'total_active_slips' => 0,
        'total_active_stake' => 0.00,
        'last_draw_time' => null,
        'system_time' => date('Y-m-d H:i:s')
    ];

    try {
        // Get total active betting slips
        $stmt = $conn->prepare("
            SELECT
                COUNT(*) as active_slips,
                COALESCE(SUM(total_stake), 0) as active_stake
            FROM betting_slips
            WHERE is_paid = 0
            AND is_cancelled = 0
        ");

        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stats['total_active_slips'] = (int)$row['active_slips'];
            $stats['total_active_stake'] = (float)$row['active_stake'];
        }

        $stmt->close();

        // Get last draw time
        $timeColumn = 'timestamp'; // Default

        // Check which time column exists
        $columnCheck = $conn->prepare("SHOW COLUMNS FROM detailed_draw_results LIKE 'timestamp'");
        $columnCheck->execute();
        $columnResult = $columnCheck->get_result();

        if ($columnResult->num_rows === 0) {
            $columnCheck = $conn->prepare("SHOW COLUMNS FROM detailed_draw_results LIKE 'draw_time'");
            $columnCheck->execute();
            $columnResult = $columnCheck->get_result();

            if ($columnResult->num_rows > 0) {
                $timeColumn = 'draw_time';
            }
        }

        $stmt = $conn->prepare("SELECT $timeColumn FROM detailed_draw_results ORDER BY draw_number DESC LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stats['last_draw_time'] = $row[$timeColumn];
        }

        $stmt->close();

    } catch (Exception $e) {
        logUpcomingDraws("Error getting system stats: " . $e->getMessage(), 'ERROR');
    }

    return $stats;
}

try {
    logUpcomingDraws("Upcoming draws stats request received");
    
    // ⏰ CRITICAL: Calculate draw number based on SERVER TIME (Georgetown timezone)
    // This ensures all devices get the same draw number regardless of their local clock
    date_default_timezone_set('America/Guyana');
    $currentTime = new DateTime('now', new DateTimeZone('America/Guyana'));
    $today = $currentTime->format('Y-m-d');
    $currentHour = (int)$currentTime->format('H');
    $currentMinute = (int)$currentTime->format('i');
    $currentSecond = (int)$currentTime->format('s');
    
    // Calculate draw number based on 3-minute intervals starting at midnight
    // Draw #1 = 00:00-00:02:59, Draw #2 = 00:03-00:05:59, Draw #3 = 00:06-00:08:59, etc. (480 draws per day)
    // At 7:36, we're at the START of draw #153's time slot, but we want to show draw #152 as current
    // (the draw that just completed or is completing)
    $totalMinutesSinceMidnight = ($currentHour * 60) + $currentMinute;
    
    // Subtract 1 minute before calculating to get the draw that's ending/completing
    // At 7:36: floor((456-1)/3) = floor(455/3) = 151, +1 = 152 ✓
    // At 7:33: floor((453-1)/3) = floor(452/3) = 150, +1 = 151 (but should be 152)
    // Actually, we need a different approach: if we're at a 3-minute boundary, use previous draw
    $drawIndex = floor($totalMinutesSinceMidnight / 3);
    
    // If we're at the exact start of a draw (minute is divisible by 3 and seconds are low),
    // show the previous draw as "current" (the one that just completed)
    if ($currentMinute % 3 == 0 && $currentSecond < 30) {
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
    
    // The NEXT draw is the one users should place bets on
    $currentDrawNumber = $serverTimeBasedDrawNumber;
    $nextDrawNumber = ($currentDrawNumber >= 480) ? 1 : ($currentDrawNumber + 1);
    
    logUpcomingDraws("Server time-based current draw: $currentDrawNumber, next draw: $nextDrawNumber (calculated from server time $currentHour:$currentMinute:$currentSecond)", 'INFO');

    // Generate upcoming draws with statistics (starting from NEXT draw, not current)
    // Users place bets on the NEXT draw, not the current one
    $drawCount = isset($_GET['count']) ? min(20, max(1, (int)$_GET['count'])) : 10;
    $upcomingDraws = generateUpcomingDrawsWithStats($conn, $nextDrawNumber, $drawCount);

    // Get system statistics
    $systemStats = getSystemStats($conn);

    // Get last completed draw for backward compatibility
    $lastCompletedDraw = getLastCompletedDraw($conn);
    
    // Prepare successful response
    $response = [
        'status' => 'success',
        'data' => [
            'upcoming_draws' => $upcomingDraws,
            'current_draw_number' => $currentDrawNumber,
            'next_draw_number' => $nextDrawNumber,
            'base_draw' => $nextDrawNumber, // Starting draw for upcoming draws list
            'last_completed_draw' => $lastCompletedDraw, // Keep for backward compatibility
            'draw_count' => count($upcomingDraws),
            'system_stats' => $systemStats
        ],
        'message' => 'Upcoming draws with statistics retrieved successfully',
        'timestamp' => time(),
        'server_time' => date('Y-m-d H:i:s')
    ];

    logUpcomingDraws("Successfully retrieved " . count($upcomingDraws) . " upcoming draws with stats");

} catch (Exception $e) {
    logUpcomingDraws("Error in upcoming draws stats: " . $e->getMessage(), 'ERROR');

    $response = [
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage(),
        'timestamp' => time()
    ];
}

// Close database connection
if (isset($conn)) {
    $conn->close();
}

// Output the response
echo json_encode($response, JSON_PRETTY_PRINT);
?>
