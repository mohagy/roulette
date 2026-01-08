<?php
/**
 * TV Sync API - NO CACHE VERSION
 *
 * SECURITY: This API ensures no data is cached for security reasons.
 * All data is retrieved fresh from the database on every request.
 */

// Initialize comprehensive cache prevention FIRST
require_once '../php/cache_prevention.php';

// Set response header to JSON
header('Content-Type: application/json');

// Include database connection and helper functions
require_once '../php/db_connect.php';
require_once '../includes/helper_functions.php';

// Set timezone to UTC
date_default_timezone_set('UTC');

// Default response (will be overwritten on success)
$response = [
    'status' => 'error',
    'message' => 'Failed to fetch TV sync data',
    'timestamp' => time()
];

// Function to log messages
function logTvSync($message, $type = 'INFO') {
    $logFile = '../logs/tv_sync.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$type] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

try {
    // Ensure no cache is used for this request
    ensureNoCache();

    // Get current draw number with fresh data (no cache)
    $drawData = getFreshData("SELECT SQL_NO_CACHE current_draw_number FROM roulette_analytics LIMIT 1");

    if (empty($drawData)) {
        throw new Exception("Draw information not found");
    }

    $drawInfo = $drawData[0];

    if (!$drawInfo) {
        throw new Exception("Draw information not found");
    }

    $currentDrawNumber = $drawInfo['current_draw_number'];

    // Default to automatic mode if no setting found
    $isAutomatic = true;

    // Try to get automatic mode setting
    try {
        // First try the direct column approach
        $stmt = $pdo->prepare("
            SELECT automatic_mode
            FROM roulette_settings
            LIMIT 1
        ");
        $stmt->execute();
        $modeSetting = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($modeSetting) {
            $isAutomatic = (int)$modeSetting['automatic_mode'] === 1;
        } else {
            // Try the setting_name/setting_value approach
            $stmt = $pdo->prepare("
                SELECT setting_value
                FROM roulette_settings
                WHERE setting_name = 'automatic_mode'
                LIMIT 1
            ");
            $stmt->execute();
            $modeSetting = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($modeSetting) {
                $isAutomatic = (int)$modeSetting['setting_value'] === 1;
            }
        }
    } catch (PDOException $e) {
        // If there's an error, just use the default value
        logTvSync("Error checking automatic mode: " . $e->getMessage(), 'WARNING');
    }

    // Check if there's a manual winning number for this draw (fresh data, no cache)
    // First try the current draw number
    $manualData = getFreshData("SELECT SQL_NO_CACHE winning_number, source, reason FROM next_draw_winning_number WHERE draw_number = ? LIMIT 1", [$currentDrawNumber]);
    $manualData = !empty($manualData) ? $manualData[0] : null;

    // If no result, try the next draw number (fresh data, no cache)
    if (!$manualData) {
        $nextDrawNumber = $currentDrawNumber + 1;

        $nextDrawData = getFreshData("SELECT SQL_NO_CACHE winning_number, source, reason FROM next_draw_winning_number WHERE draw_number = ? LIMIT 1", [$nextDrawNumber]);
        $manualData = !empty($nextDrawData) ? $nextDrawData[0] : null;

        // Log that we're using the next draw number
        if ($manualData) {
            logTvSync("Using winning number from next draw #$nextDrawNumber instead of current draw #$currentDrawNumber");
        }
    }

    $manualWinningNumber = null;
    $winningNumberSource = null;
    $winningNumberReason = null;

    if ($manualData) {
        $manualWinningNumber = (int)$manualData['winning_number'];
        $winningNumberSource = $manualData['source'];
        $winningNumberReason = $manualData['reason'];
    }

    // Get the next draw number
    $nextDrawNumber = $currentDrawNumber + 1;

    // Use a default countdown value of 60 seconds
    // This is simpler than trying to determine which table/column has the timer
    $countdown = 60;

    // Prepare response
    $response = [
        'status' => 'success',
        'data' => [
            'current_draw' => $currentDrawNumber,
            'next_draw' => $nextDrawNumber,
            'is_automatic' => $isAutomatic,
            'countdown' => $countdown,
            'has_forced_number' => (!$isAutomatic && $manualWinningNumber !== null),
            'forced_number' => (!$isAutomatic && $manualWinningNumber !== null) ? $manualWinningNumber : null,
            'forced_color' => (!$isAutomatic && $manualWinningNumber !== null) ? getNumberColor($manualWinningNumber) : null,
            'source' => $winningNumberSource,
            'reason' => $winningNumberReason
        ],
        'timestamp' => time()
    ];

    // Log successful sync
    logTvSync("TV sync successful. Mode: " . ($isAutomatic ? "Automatic" : "Manual") .
              ($manualWinningNumber !== null ? ", Forced number: $manualWinningNumber" : ""));

} catch (Exception $e) {
    // Log error
    logTvSync("Error in TV sync: " . $e->getMessage(), 'ERROR');

    $response = [
        'status' => 'error',
        'message' => 'Error: ' . $e->getMessage(),
        'timestamp' => time()
    ];
}

// Output the response
echo json_encode($response, JSON_PRETTY_PRINT);
?>