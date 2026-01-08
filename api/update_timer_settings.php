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
    'message' => 'Failed to update timer settings',
    'timestamp' => time()
];

// Function to log timer setting changes
function logTimerChange($message, $type = 'INFO') {
    $logFile = '../logs/timer_changes.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$type] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

try {
    // Check if timer duration is provided
    if (!isset($_POST['duration'])) {
        throw new Exception("No duration parameter provided");
    }

    // Get and validate the duration
    $duration = intval($_POST['duration']);

    // Validate duration (minimum 10 seconds, maximum 300 seconds / 5 minutes)
    if ($duration < 10 || $duration > 300) {
        throw new Exception("Invalid duration. Must be between 10 and 300 seconds");
    }

    // Check if timer_duration column exists directly
    $checkColumnQuery = "SHOW COLUMNS FROM roulette_settings LIKE 'timer_duration'";
    $columnResult = $conn->query($checkColumnQuery);
    $hasTimerDurationColumn = ($columnResult->num_rows > 0);

    if ($hasTimerDurationColumn) {
        // Using direct column approach
        $stmt = $conn->prepare("
            UPDATE roulette_settings
            SET timer_duration = ?,
                updated_at = NOW()
            WHERE id = 1
        ");
        $stmt->bind_param("i", $duration);
    } else {
        // Using setting_name/setting_value approach
        $durationStr = (string)$duration;
        $stmt = $conn->prepare("
            UPDATE roulette_settings
            SET setting_value = ?,
                updated_at = NOW()
            WHERE setting_name = 'timer_duration'
        ");
        $stmt->bind_param("s", $durationStr);

        // Check if setting exists
        $checkSettingQuery = "SELECT COUNT(*) as count FROM roulette_settings WHERE setting_name = 'timer_duration'";
        $checkResult = $conn->query($checkSettingQuery);
        $row = $checkResult->fetch_assoc();

        // If setting doesn't exist, insert it
        if ($row['count'] == 0) {
            $stmt->close();
            $stmt = $conn->prepare("
                INSERT INTO roulette_settings
                (setting_name, setting_value)
                VALUES ('timer_duration', ?)
            ");
            $stmt->bind_param("s", $durationStr);
        }
    }

    $success = $stmt->execute();
    $stmt->close();

    if (!$success) {
        throw new Exception("Failed to update timer settings: " . $conn->error);
    }

    // Log the timer change
    logTimerChange("Timer duration updated to $duration seconds", 'INFO');

    // Also update next_draw_time in roulette_state table
    // First, get current time
    $currentTime = time();

    // Calculate new next draw time
    $nextDrawTime = $currentTime + $duration;

    // Get the most recent state record
    $getLatestQuery = "SELECT * FROM roulette_state ORDER BY id DESC LIMIT 1";
    $latestResult = $conn->query($getLatestQuery);
    $latestState = $latestResult->fetch_assoc();

    // Insert a new record with updated next_draw_time
    $stmt = $conn->prepare("
        INSERT INTO roulette_state
        (roll_history, roll_colors, last_draw, next_draw, countdown_time, end_time, current_draw_number, winning_number, next_draw_winning_number, manual_mode, next_draw_time)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param("ssssiiiiiii",
        $latestState['roll_history'],
        $latestState['roll_colors'],
        $latestState['last_draw'],
        $latestState['next_draw'],
        $latestState['countdown_time'],
        $latestState['end_time'],
        $latestState['current_draw_number'],
        $latestState['winning_number'],
        $latestState['next_draw_winning_number'],
        $latestState['manual_mode'],
        $nextDrawTime
    );

    $stmt->execute();
    $stmt->close();

    // Prepare success response
    $response = [
        'status' => 'success',
        'message' => "Timer duration updated to $duration seconds",
        'data' => [
            'duration' => $duration,
            'next_draw_time' => $nextDrawTime,
            'current_time' => $currentTime
        ],
        'timestamp' => time()
    ];

} catch (Exception $e) {
    logTimerChange("Error updating timer: " . $e->getMessage(), 'ERROR');

    $response = [
        'status' => 'error',
        'message' => "Error: " . $e->getMessage(),
        'timestamp' => time()
    ];
}

// Output the response
echo json_encode($response, JSON_PRETTY_PRINT);
?>
