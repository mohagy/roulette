<?php
// Set response header to JSON
header('Content-Type: application/json');

// Include database connection and helper functions
require_once '../includes/db_connection.php';
require_once '../includes/helper_functions.php';

// Set timezone to UTC
date_default_timezone_set('UTC');

// Default response (will be overwritten on success)
$response = [
    'status' => 'error',
    'message' => 'Failed to fetch draw information',
    'timestamp' => time()
];

// Function to log error to file for debugging
function logError($message) {
    $logFile = '../logs/api_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

try {
    // Get current draw information
    // Check if roulette_state has the old columns (roll_history, last_draw) or new structure
    $checkColumns = $conn->query("SHOW COLUMNS FROM roulette_state LIKE 'roll_history'");
    $hasOldColumns = ($checkColumns && $checkColumns->num_rows > 0);
    
    if ($hasOldColumns) {
        // Old structure with roll_history, last_draw, etc.
        $stmt = $conn->prepare("
            SELECT ra.current_draw_number, 
                   rs.roll_history, 
                   rs.roll_colors, 
                   rs.countdown_time, 
                   rs.last_draw, 
                   rs.next_draw
            FROM roulette_analytics ra
            LEFT JOIN roulette_state rs ON rs.id = 1
            WHERE ra.id = 1
            LIMIT 1
        ");
    } else {
        // New structure - get most recent state record
        $stmt = $conn->prepare("
            SELECT ra.current_draw_number,
                   rs.countdown_time,
                   NULL as roll_history,
                   NULL as roll_colors,
                   CONCAT('#', rs.draw_number) as last_draw,
                   CONCAT('#', rs.next_draw_number) as next_draw
            FROM roulette_analytics ra
            LEFT JOIN (
                SELECT draw_number, next_draw_number, countdown_time
                FROM roulette_state
                ORDER BY created_at DESC
                LIMIT 1
            ) rs ON 1=1
            WHERE ra.id = 1
            LIMIT 1
        ");
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("No draw information found");
    }
    
    $drawInfo = $result->fetch_assoc();
    $stmt->close();
    
    // Check if automatic_mode column exists
    $checkColumnQuery = "SHOW COLUMNS FROM roulette_settings LIKE 'automatic_mode'";
    $columnResult = $conn->query($checkColumnQuery);
    $hasAutomaticModeColumn = ($columnResult->num_rows > 0);
    
    // Default to automatic mode if no setting found
    $isAutomatic = true;
    
    if ($hasAutomaticModeColumn) {
        // Use direct column approach
        $stmt = $conn->prepare("
            SELECT automatic_mode 
            FROM roulette_settings 
            WHERE id = 1
            LIMIT 1
        ");
        $stmt->execute();
        $modeResult = $stmt->get_result();
        
        if ($modeResult->num_rows > 0) {
            $modeSetting = $modeResult->fetch_assoc();
            $isAutomatic = (int)$modeSetting['automatic_mode'] === 1;
        }
        $stmt->close();
    } else {
        // Use setting_name/setting_value approach
        $stmt = $conn->prepare("
            SELECT setting_value 
            FROM roulette_settings 
            WHERE setting_name = 'automatic_mode'
            LIMIT 1
        ");
        $stmt->execute();
        $modeResult = $stmt->get_result();
        
        if ($modeResult->num_rows > 0) {
            $modeSetting = $modeResult->fetch_assoc();
            $isAutomatic = (int)$modeSetting['setting_value'] === 1;
        }
        $stmt->close();
    }
    
    // Get current draw number
    $currentDrawNumber = $drawInfo['current_draw_number'];
    
    // Initialize variables
    $winningNumber = null;
    $winningNumberSource = null;
    $winningNumberReason = null;
    
    // IMPORTANT: In automatic mode, ALWAYS use smart selection (completely ignore manual numbers)
    // Only check for manual numbers if we're in manual mode
    if ($isAutomatic) {
        // AUTO MODE: Always use smart selection with time-based presets and patterns
        // This ignores any manual numbers that might be set
        $bestWinningInfo = findBestWinningNumber($conn, $currentDrawNumber, true); // true = use smart selection
        $winningNumber = $bestWinningInfo['number'];
        $winningNumberSource = 'automatic (smart selection)';
        $winningNumberReason = $bestWinningInfo['reason'];
    } else {
        // MANUAL MODE: Check for manual winning number
        $stmt = $conn->prepare("
            SELECT winning_number, source, reason 
            FROM next_draw_winning_number 
            WHERE draw_number = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $currentDrawNumber);
        $stmt->execute();
        $manualResult = $stmt->get_result();
        
        $manualWinningNumber = null;
        if ($manualResult->num_rows > 0) {
            $manualData = $manualResult->fetch_assoc();
            $manualWinningNumber = (int)$manualData['winning_number'];
            $winningNumberSource = $manualData['source'];
            $winningNumberReason = $manualData['reason'];
        }
        $stmt->close();
        
        if ($manualWinningNumber !== null) {
            // Manual mode with manual number set
            $winningNumber = $manualWinningNumber;
        } else {
            // Manual mode but no manual number - use smart selection as fallback
            $bestWinningInfo = findBestWinningNumber($conn, $currentDrawNumber, true);
            $winningNumber = $bestWinningInfo['number'];
            $winningNumberSource = 'automatic (fallback - no manual number)';
            $winningNumberReason = $bestWinningInfo['reason'];
        }
    }
    
    // Process roll history
    $rollHistory = [];
    $rollColors = [];
    
    if (!empty($drawInfo['roll_history'])) {
        $rollHistory = explode(',', $drawInfo['roll_history']);
        $rollColors = !empty($drawInfo['roll_colors']) ? explode(',', $drawInfo['roll_colors']) : [];
        
        // If we don't have colors, calculate them
        if (empty($rollColors)) {
            foreach ($rollHistory as $num) {
                $rollColors[] = getNumberColor((int)$num);
            }
        }
    }
    
    // Get the countdown time from the database or use a default value
    $countdown = isset($drawInfo['countdown_time']) ? (int)$drawInfo['countdown_time'] : 60;
    
    // Calculate expected draw number based on time (for validation)
    // Use Guyana timezone (UTC-4)
    date_default_timezone_set('America/Guyana');
    $now = new DateTime('now', new DateTimeZone('America/Guyana'));
    $currentDate = $now->format('Y-m-d');
    $currentHour = (int)$now->format('H');
    $currentMinute = (int)$now->format('i');
    
    // Check if we need to reset (new day)
    $stmt = $conn->prepare("SELECT last_reset_date FROM roulette_analytics WHERE id = 1 LIMIT 1");
    $stmt->execute();
    $resetResult = $stmt->get_result();
    $lastResetDate = null;
    if ($resetResult->num_rows > 0) {
        $resetRow = $resetResult->fetch_assoc();
        $lastResetDate = $resetRow['last_reset_date'];
    }
    $stmt->close();
    
    // If it's a new day, reset draw number to 1
    $needsReset = (!$lastResetDate || $lastResetDate < $currentDate);
    
    if ($needsReset) {
        // Reset to draw #1 for new day
        $expectedDrawNumber = 1;
        
        // Update database with reset
        try {
            $updateStmt = $conn->prepare("UPDATE roulette_analytics SET current_draw_number = 1, last_reset_date = ? WHERE id = 1");
            $updateStmt->bind_param("s", $currentDate);
            $updateStmt->execute();
            $updateStmt->close();
            
            $currentDrawNumber = 1;
            logError("Reset draw number to 1 for new day: $currentDate");
        } catch (Exception $e) {
            logError("Failed to reset draw number: " . $e->getMessage());
        }
    } else {
        // Calculate draw number based on time (480 draws per day, one every 3 minutes)
        $totalMinutes = ($currentHour * 60) + $currentMinute;
        $completedIntervals = floor($totalMinutes / 3);
        $expectedDrawNumber = $completedIntervals + 1;
        
        // Ensure draw number doesn't exceed 480 (max draws per day)
        if ($expectedDrawNumber > 480) {
            $expectedDrawNumber = 480;
        }
        
        // Auto-update draw number if it's wrong (but only if mismatch is significant)
        $drawNumberMismatch = ((int)$currentDrawNumber !== (int)$expectedDrawNumber);
        
        // If draw number is significantly behind (more than 1 draw), auto-correct it
        if ($drawNumberMismatch && $expectedDrawNumber > $currentDrawNumber) {
            try {
                $oldDrawNumber = $currentDrawNumber;
                $updateStmt = $conn->prepare("UPDATE roulette_analytics SET current_draw_number = ? WHERE id = 1");
                $updateStmt->bind_param("i", $expectedDrawNumber);
                $updateStmt->execute();
                $updateStmt->close();
                
                // Update current draw number variable
                $currentDrawNumber = $expectedDrawNumber;
                logError("Auto-corrected draw number from $oldDrawNumber to $expectedDrawNumber");
            } catch (Exception $e) {
                logError("Failed to auto-correct draw number: " . $e->getMessage());
            }
        }
    }
    
    // Prepare the response
    $response = [
        'status' => 'success',
        'data' => [
            'current_draw' => (int)$currentDrawNumber,
            'expected_draw' => $expectedDrawNumber,
            'draw_number_match' => !$drawNumberMismatch,
            'last_draw' => $drawInfo['last_draw'],
            'next_draw' => $drawInfo['next_draw'],
            'is_automatic' => $isAutomatic,
            'countdown' => $countdown,
            'timer_seconds' => (int)$drawInfo['countdown_time'],
            'winning_number' => $winningNumber !== null ? (int)$winningNumber : null,
            'winning_color' => $winningNumber !== null ? getNumberColor((int)$winningNumber) : null,
            'winning_number_source' => $winningNumberSource,
            'winning_number_reason' => $winningNumberReason,
            'recent_rolls' => array_map('intval', $rollHistory),
            'recent_colors' => $rollColors
        ],
        'timestamp' => time()
    ];
    
    // Add warning if draw number mismatch
    if ($drawNumberMismatch) {
        $response['warning'] = "Draw number mismatch: Current=$currentDrawNumber, Expected=$expectedDrawNumber";
    }
    
} catch (Exception $e) {
    // Log error for debugging
    logError("draw_info.php error: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    
    $response = [
        'status' => 'error',
        'message' => 'Error: ' . $e->getMessage(),
        'timestamp' => time()
    ];
}

// Output the response
echo json_encode($response, JSON_PRETTY_PRINT);
?> 