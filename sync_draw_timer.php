<?php
/**
 * Sync Draw Timer API
 * Returns the current draw information and countdown timer
 * Uses the new multi-row database structure for analytics
 */

// Set headers for JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Add a unique request ID for debugging
$requestId = uniqid();
error_log("Processing sync_draw_timer.php request: $requestId");

// Include the analytics handler
require_once 'php/roulette_analytics.php';

// Default response
$response = [
    'success' => false,
    'message' => 'Unknown error',
    'countdownTime' => 0,
    'lastDrawNumber' => 0,
    'currentDrawNumber' => 0,
    'upcomingDraws' => [],
    'upcomingDrawTimes' => [],
    'lastWinningNumber' => null,
    'lastWinningColor' => null
];

try {
    // Get the display data using the new analytics handler
    $displayData = getTVDisplayData();

    // Map the data to the response format
    $response['lastDrawNumber'] = $displayData['lastDrawNumber'] ?? 14;
    $response['currentDrawNumber'] = $displayData['currentDrawNumber'] ?? 14;
    $response['lastWinningNumber'] = $displayData['lastWinningNumber'] ?? null;
    $response['lastWinningColor'] = $displayData['lastWinningColor'] ?? null;
    $response['countdownTime'] = $displayData['countdownTime'] ?? 180;

    // Log the actual values from the database for debugging
    error_log("Display data from database: " . json_encode($displayData));

    // Get the next draw number (current + 1)
    $nextDrawNumber = $response['currentDrawNumber'] + 1;

    // Force the upcoming draws to start with the correct next draw number
    $forcedUpcomingDraws = [];
    $forcedUpcomingTimes = [];

    // Generate 10 draws starting from the next draw number
    for ($i = 0; $i < 10; $i++) {
        $drawNumber = $nextDrawNumber + $i;
        $forcedUpcomingDraws[] = $drawNumber;
        $drawTime = date('H:i:s', time() + ($i * 180));
        $forcedUpcomingTimes[] = $drawTime;
    }

    $response['upcomingDraws'] = $forcedUpcomingDraws;
    $response['upcomingDrawTimes'] = $forcedUpcomingTimes;

    // Log the forced draw numbers
    error_log("Current draw: {$response['currentDrawNumber']}, Next draw: {$nextDrawNumber}");
    error_log("Forced upcoming draws: " . json_encode($forcedUpcomingDraws));

    // Get recent draws for history display
    $recentDraws = getRecentDraws(10);
    $formattedRecentDraws = [];

    foreach ($recentDraws as $draw) {
        $formattedRecentDraws[] = [
            'drawNumber' => (int)$draw['draw_number'],
            'winningNumber' => (int)$draw['winning_number'],
            'winningColor' => $draw['winning_color'],
            'drawTime' => $draw['draw_time']
        ];
    }

    $response['recentDraws'] = $formattedRecentDraws;

    // Set success
    $response['success'] = true;
    $response['message'] = 'Data retrieved successfully';

} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

// Output JSON response
echo json_encode($response);
