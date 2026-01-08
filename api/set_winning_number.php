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
    'message' => 'Failed to set winning number',
    'timestamp' => time()
];

// Function to log messages
function logSetWinningNumber($message, $type = 'INFO') {
    // Create logs directory if it doesn't exist
    if (!file_exists('../logs')) {
        mkdir('../logs', 0777, true);
    }

    $logFile = '../logs/manual_winning_number.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$type] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);

    // Also log to PHP error log for critical issues
    if ($type === 'ERROR') {
        error_log("Manual Winning Number: $message");
    }
}

try {
    // Check if number is provided (accept both 'number' and 'winning_number' for backward compatibility)
    if (!isset($_POST['number']) && !isset($_POST['winning_number'])) {
        throw new Exception("No winning number provided");
    }

    // Parse and validate the number
    $winningNumber = isset($_POST['winning_number']) ? intval($_POST['winning_number']) : intval($_POST['number']);

    // Log the received parameters for debugging
    logSetWinningNumber("Received parameters: " . json_encode($_POST), 'INFO');

    if (!isValidRouletteNumber($winningNumber)) {
        throw new Exception("Invalid winning number. Must be between 0 and 36");
    }

    // Get current draw number
    $stmt = $conn->prepare("
        SELECT current_draw_number
        FROM roulette_analytics
        WHERE id = 1
        LIMIT 1
    ");
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Draw information not found");
    }

    $drawData = $result->fetch_assoc();
    $currentDrawNumber = $drawData['current_draw_number'];
    $stmt->close();

    // Log the current draw number for debugging
    logSetWinningNumber("Current draw number: $currentDrawNumber, Setting winning number: $winningNumber", 'INFO');

    // Check if there's already a manual winning number for this draw
    $stmt = $conn->prepare("
        SELECT id
        FROM next_draw_winning_number
        WHERE draw_number = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }

    $stmt->bind_param("i", $currentDrawNumber);
    $execResult = $stmt->execute();

    if (!$execResult) {
        throw new Exception("Failed to execute statement: " . $stmt->error);
    }

    $existingResult = $stmt->get_result();
    $exists = $existingResult->num_rows > 0;

    if ($exists) {
        logSetWinningNumber("Found existing record for draw #$currentDrawNumber", 'INFO');
    } else {
        logSetWinningNumber("No existing record found for draw #$currentDrawNumber, will create new", 'INFO');
    }

    $stmt->close();

    if ($exists) {
        // Update the existing record
        $stmt = $conn->prepare("
            UPDATE next_draw_winning_number
            SET winning_number = ?,
                source = 'manual',
                reason = 'Set by administrator',
                updated_at = NOW()
            WHERE draw_number = ?
        ");

        if (!$stmt) {
            throw new Exception("Failed to prepare update statement: " . $conn->error);
        }

        $stmt->bind_param("ii", $winningNumber, $currentDrawNumber);
        logSetWinningNumber("Updating record with winning number $winningNumber for draw #$currentDrawNumber", 'INFO');
    } else {
        // Insert a new record
        $stmt = $conn->prepare("
            INSERT INTO next_draw_winning_number
            (draw_number, winning_number, source, reason, created_at, updated_at)
            VALUES (?, ?, 'manual', 'Set by administrator', NOW(), NOW())
        ");

        if (!$stmt) {
            throw new Exception("Failed to prepare insert statement: " . $conn->error);
        }

        $stmt->bind_param("ii", $currentDrawNumber, $winningNumber);
        logSetWinningNumber("Inserting new record with winning number $winningNumber for draw #$currentDrawNumber", 'INFO');
    }

    $success = $stmt->execute();

    if (!$success) {
        throw new Exception("Failed to " . ($exists ? "update" : "insert") . " winning number: " . $stmt->error);
    }

    logSetWinningNumber(($exists ? "Updated" : "Inserted") . " winning number record successfully", 'INFO');
    $stmt->close();

    if (!$success) {
        throw new Exception("Failed to set winning number: " . $conn->error);
    }

    // Also turn off automatic mode if it's currently on
    // Check if automatic_mode column exists
    $checkColumnQuery = "SHOW COLUMNS FROM roulette_settings LIKE 'automatic_mode'";
    $columnResult = $conn->query($checkColumnQuery);
    $hasAutomaticModeColumn = ($columnResult->num_rows > 0);

    logSetWinningNumber("Turning off automatic mode", 'INFO');

    // First check if automatic mode is already off
    $isAutomatic = true;

    if ($hasAutomaticModeColumn) {
        $checkStmt = $conn->prepare("SELECT automatic_mode FROM roulette_settings WHERE id = 1 LIMIT 1");
        $checkStmt->execute();
        $modeResult = $checkStmt->get_result();

        if ($modeResult->num_rows > 0) {
            $modeSetting = $modeResult->fetch_assoc();
            $isAutomatic = (int)$modeSetting['automatic_mode'] === 1;
        }
        $checkStmt->close();
    } else {
        $checkStmt = $conn->prepare("SELECT setting_value FROM roulette_settings WHERE setting_name = 'automatic_mode' LIMIT 1");
        $checkStmt->execute();
        $modeResult = $checkStmt->get_result();

        if ($modeResult->num_rows > 0) {
            $modeSetting = $modeResult->fetch_assoc();
            $isAutomatic = (int)$modeSetting['setting_value'] === 1;
        }
        $checkStmt->close();
    }

    if ($isAutomatic) {
        logSetWinningNumber("Automatic mode is currently ON, turning it OFF", 'INFO');

        if ($hasAutomaticModeColumn) {
            // Using direct column approach
            $stmt = $conn->prepare("
                UPDATE roulette_settings
                SET automatic_mode = 0,
                    updated_at = NOW()
                WHERE id = 1
            ");
        } else {
            // Using setting_name/setting_value approach
            $stmt = $conn->prepare("
                UPDATE roulette_settings
                SET setting_value = '0',
                    updated_at = NOW()
                WHERE setting_name = 'automatic_mode'
            ");
        }

        if (!$stmt) {
            throw new Exception("Failed to prepare automatic mode update statement: " . $conn->error);
        }

        $modeSuccess = $stmt->execute();

        if (!$modeSuccess) {
            throw new Exception("Failed to turn off automatic mode: " . $stmt->error);
        }

        logSetWinningNumber("Successfully turned off automatic mode", 'INFO');
        $stmt->close();
    } else {
        logSetWinningNumber("Automatic mode is already OFF, no need to update", 'INFO');
    }

    // Log the action
    logSetWinningNumber("Winning number set to $winningNumber for draw #$currentDrawNumber", 'INFO');

    // Get winning number color
    $winningColor = getNumberColor($winningNumber);

    // Prepare success response
    $response = [
        'status' => 'success',
        'message' => "Winning number set to $winningNumber",
        'data' => [
            'draw_number' => $currentDrawNumber,
            'winning_number' => $winningNumber,
            'winning_color' => $winningColor,
            'source' => 'manual',
            'reason' => 'Set by administrator',
            'is_automatic' => false
        ],
        'timestamp' => time()
    ];

} catch (Exception $e) {
    logSetWinningNumber("Error setting winning number: " . $e->getMessage(), 'ERROR');

    $response = [
        'status' => 'error',
        'message' => "Error: " . $e->getMessage(),
        'timestamp' => time()
    ];
}

// Output the response
echo json_encode($response, JSON_PRETTY_PRINT);
?>