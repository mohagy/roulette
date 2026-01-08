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
    
    // Check if there's a manual winning number for this draw
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
    $winningNumberSource = null;
    $winningNumberReason = null;
    
    if ($manualResult->num_rows > 0) {
        $manualData = $manualResult->fetch_assoc();
        $manualWinningNumber = (int)$manualData['winning_number'];
        $winningNumberSource = $manualData['source'];
        $winningNumberReason = $manualData['reason'];
    }
    $stmt->close();
    
    // Find the best winning number from bet distribution if in automatic mode
    $bestAutoWinningNumber = null;
    $bestAutoWinningReason = null;
    
    if ($isAutomatic || $manualWinningNumber === null) {
        // Check if there are any bets for this draw
        $hasBets = hasBetsForDraw($conn, $currentDrawNumber);
        
        if ($hasBets) {
            // Get bet distribution
            $bestWinningInfo = findBestWinningNumber($conn, $currentDrawNumber);
            $bestAutoWinningNumber = $bestWinningInfo['number'];
            $bestAutoWinningReason = $bestWinningInfo['reason'];
        } else {
            // No bets, select a random number
            $bestAutoWinningNumber = mt_rand(0, 36);
            $bestAutoWinningReason = "Random selection (no bets)";
        }
    }
    
    // Determine the winning number based on mode
    $winningNumber = $isAutomatic ? $bestAutoWinningNumber : $manualWinningNumber;
    
    // If we're in manual mode but no manual number is set, use automatic as fallback
    if (!$isAutomatic && $manualWinningNumber === null) {
        $winningNumber = $bestAutoWinningNumber;
        $winningNumberSource = 'automatic (fallback)';
        $winningNumberReason = $bestAutoWinningReason;
    }
    
    // If we're in automatic mode, use the automatic source and reason
    if ($isAutomatic) {
        $winningNumberSource = 'automatic';
        $winningNumberReason = $bestAutoWinningReason;
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
    
    // Prepare the response
    $response = [
        'status' => 'success',
        'data' => [
            'current_draw' => (int)$currentDrawNumber,
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