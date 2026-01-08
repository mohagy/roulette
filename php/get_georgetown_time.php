<?php
/**
 * Georgetown Time Server Endpoint
 * Provides accurate Georgetown, Guyana time (GMT-4/UTC-4) for countdown timer synchronization
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Set Georgetown timezone (GMT-4/UTC-4)
    date_default_timezone_set('America/Guyana');

    // Get current Georgetown time
    $georgetown_time = new DateTime();

    // Georgetown 3-minute cycle calculation
    // Cycles start at :00, :03, :06, :09, :12, :15, :18, etc. minutes past each hour
    $current_minute = (int)$georgetown_time->format('i');
    $current_second = (int)$georgetown_time->format('s');

    // Calculate current position in 3-minute cycle
    $current_cycle_minute = $current_minute % 3;
    $seconds_into_cycle = ($current_cycle_minute * 60) + $current_second;

    // Calculate remaining seconds until next 3-minute mark
    $total_remaining_seconds = 180 - $seconds_into_cycle; // 180 seconds = 3 minutes

    // If we're exactly at a 3-minute mark, start new cycle
    if ($total_remaining_seconds >= 180) {
        $total_remaining_seconds = 180;
    }

    // Calculate next cycle start time
    $next_cycle_start = clone $georgetown_time;
    $next_cycle_start->add(new DateInterval('PT' . $total_remaining_seconds . 'S'));

    // Format times
    $current_time_formatted = $georgetown_time->format('Y-m-d H:i:s');
    $current_time_iso = $georgetown_time->format('c');
    $current_timestamp = $georgetown_time->getTimestamp();
    $next_cycle_formatted = $next_cycle_start->format('Y-m-d H:i:s');

    // Calculate minutes and seconds remaining for display
    $minutes_remaining = floor($total_remaining_seconds / 60);
    $seconds_remaining_display = $total_remaining_seconds % 60;
    $countdown_display = sprintf('%02d:%02d', $minutes_remaining, $seconds_remaining_display);

    // Calculate cycle information
    $cycle_duration = 180; // 3 minutes
    $cycle_position = $seconds_into_cycle;

    // Response data
    $response = [
        'status' => 'success',
        'georgetown_time' => [
            'formatted' => $current_time_formatted,
            'iso' => $current_time_iso,
            'timestamp' => $current_timestamp,
            'timezone' => 'America/Guyana',
            'offset' => '-04:00'
        ],
        'countdown' => [
            'total_seconds_remaining' => $total_remaining_seconds,
            'minutes_remaining' => $minutes_remaining,
            'seconds_remaining' => $seconds_remaining_display,
            'display_format' => $countdown_display,
            'cycle_position' => $cycle_position,
            'cycle_duration' => $cycle_duration
        ],
        'next_cycle' => [
            'start_time' => $next_cycle_formatted,
            'start_timestamp' => $next_cycle_start->getTimestamp()
        ],
        'server_info' => [
            'php_timezone' => date_default_timezone_get(),
            'server_time' => date('Y-m-d H:i:s'),
            'utc_time' => gmdate('Y-m-d H:i:s')
        ]
    ];

    // Add debug information if requested
    if (isset($_GET['debug']) && $_GET['debug'] === 'true') {
        $response['debug'] = [
            'calculation_steps' => [
                'current_minute' => $current_minute,
                'current_second' => $current_second,
                'current_cycle_minute' => $current_cycle_minute,
                'seconds_into_cycle' => $seconds_into_cycle,
                'total_remaining_seconds' => $total_remaining_seconds,
                'calculation' => "180 - $seconds_into_cycle = $total_remaining_seconds"
            ],
            'time_breakdown' => [
                'hours' => $georgetown_time->format('H'),
                'minutes' => $georgetown_time->format('i'),
                'seconds' => $georgetown_time->format('s'),
                'georgetown_timestamp' => $current_timestamp,
                'next_3min_mark' => $next_cycle_formatted
            ]
        ];
    }

    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    // Error response
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to get Georgetown time',
        'error' => $e->getMessage(),
        'fallback' => [
            'server_time' => date('Y-m-d H:i:s'),
            'utc_time' => gmdate('Y-m-d H:i:s'),
            'suggested_action' => 'Use client-side fallback with UTC-4 offset'
        ]
    ], JSON_PRETTY_PRINT);
}
?>
