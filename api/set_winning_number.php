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
    // Create logs directory if it doesn't exist (try multiple paths)
    $logDir = __DIR__ . '/../logs';
    if (!file_exists($logDir)) {
        @mkdir($logDir, 0777, true);
    }
    
    // Also try relative path
    if (!file_exists('../logs')) {
        @mkdir('../logs', 0777, true);
    }

    $logFile = $logDir . '/manual_winning_number.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$type] $message\n";
    @file_put_contents($logFile, $logMessage, FILE_APPEND);

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
    
    // Check if we should keep automatic mode (for auto-draw functionality)
    // ⚠️ CRITICAL: Only set to true if explicitly 'true' (string) or true (boolean)
    // Default to false (manual) if not explicitly set to true
    $keepAutoMode = false;
    if (isset($_POST['keep_auto_mode'])) {
        $keepAutoModeValue = $_POST['keep_auto_mode'];
        // Only set to true if explicitly 'true' (string) or true (boolean) or '1' (string)
        // Everything else (including 'false', false, '0', 0, empty string) = false (manual)
        $keepAutoMode = ($keepAutoModeValue === 'true' || $keepAutoModeValue === true || $keepAutoModeValue === '1' || $keepAutoModeValue === 1);
    }
    
    // ⚠️ ADDITIONAL SAFEGUARD: If keep_auto_mode is explicitly 'false', force it to false
    if (isset($_POST['keep_auto_mode']) && ($_POST['keep_auto_mode'] === 'false' || $_POST['keep_auto_mode'] === false)) {
        $keepAutoMode = false;
    }

    // Log the received parameters for debugging
    logSetWinningNumber("Received parameters: " . json_encode($_POST) . ", keep_auto_mode parsed: " . ($keepAutoMode ? 'true' : 'false'), 'INFO');

    if (!isValidRouletteNumber($winningNumber)) {
        throw new Exception("Invalid winning number. Must be between 0 and 36");
    }

    // Get draw number - use server-time-based calculation for NEXT draw
    // ⚠️ CRITICAL: When setting a winning number, it should be for the NEXT draw, not the current one
    $targetDrawNumber = null;
    
    // Check if draw_number is provided in POST (for explicit draw selection)
    if (isset($_POST['draw_number']) && !empty($_POST['draw_number'])) {
        $targetDrawNumber = (int)$_POST['draw_number'];
        logSetWinningNumber("Using draw_number from POST: $targetDrawNumber", 'INFO');
    } else {
        // ⏰ CRITICAL: Calculate NEXT draw number based on SERVER TIME
        // This ensures the winning number is set for the upcoming draw, not the current one
        date_default_timezone_set('America/Guyana');
        $now = new DateTime('now', new DateTimeZone('America/Guyana'));
        $currentHour = (int)$now->format('H');
        $currentMinute = (int)$now->format('i');
        $totalMinutesSinceMidnight = ($currentHour * 60) + $currentMinute;
        $drawIndex = floor($totalMinutesSinceMidnight / 3);
        $currentDrawNumber = $drawIndex + 1;
        
        // Cap current draw at 480
        if ($currentDrawNumber > 480) {
            $currentDrawNumber = 480;
        }
        
        // Set for NEXT draw (current + 1)
        $targetDrawNumber = $currentDrawNumber + 1;
        
        // If we're at the last draw of the day (480), next draw should be 1 (next day)
        // But for safety, cap at 480
        if ($targetDrawNumber > 480) {
            $targetDrawNumber = 480;
        }
        
        logSetWinningNumber("Calculated NEXT draw number from server time: $targetDrawNumber (current: $currentDrawNumber, time: " . $now->format('H:i:s') . ")", 'INFO');
        }
        
    // Validate draw number
    if ($targetDrawNumber === null || $targetDrawNumber === 0 || $targetDrawNumber > 480) {
        throw new Exception("Invalid draw number: $targetDrawNumber. Draw numbers must be between 1 and 480.");
                }
    
    // Use targetDrawNumber for the rest of the function
    $currentDrawNumber = $targetDrawNumber;

    // Log the current draw number for debugging
    logSetWinningNumber("Current draw number: $currentDrawNumber, Setting winning number: $winningNumber", 'INFO');

    // Ensure next_draw_winning_number table exists
    $checkTableQuery = "SHOW TABLES LIKE 'next_draw_winning_number'";
    $tableResult = $conn->query($checkTableQuery);
    
    if ($tableResult->num_rows === 0) {
        logSetWinningNumber("Creating next_draw_winning_number table...", 'INFO');
        
        $createTableSQL = "CREATE TABLE IF NOT EXISTS `next_draw_winning_number` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `draw_number` int(11) NOT NULL,
            `winning_number` int(11) NOT NULL,
            `source` varchar(50) DEFAULT 'manual',
            `reason` varchar(255) DEFAULT 'Set by administrator',
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_draw` (`draw_number`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($conn->query($createTableSQL)) {
            logSetWinningNumber("Table next_draw_winning_number created successfully", 'INFO');
        } else {
            throw new Exception("Failed to create next_draw_winning_number table: " . $conn->error);
        }
    }

    // Check if there's already a manual winning number for this draw
    $stmt = $conn->prepare("
        SELECT id, source
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
    $existingSource = null;

    if ($exists) {
        $existingRow = $existingResult->fetch_assoc();
        $existingSource = $existingRow['source'] ?? null;
        logSetWinningNumber("Found existing record for draw #$currentDrawNumber with source='{$existingSource}'", 'INFO');
    } else {
        logSetWinningNumber("No existing record found for draw #$currentDrawNumber, will create new", 'INFO');
    }

    $stmt->close();

    // Determine source and reason based on keep_auto_mode
    // ⚠️ CRITICAL: Default to 'manual' unless explicitly set to automatic
    // This ensures manual settings are never accidentally saved as automatic
    $source = ($keepAutoMode === true) ? 'automatic' : 'manual';
    $reason = ($keepAutoMode === true) ? 'Auto-selected by smart system' : 'Set by administrator';
    
    // ⚠️ CRITICAL PROTECTION: If there's an existing manual entry, NEVER overwrite it with automatic
    // Manual entries should only be overwritten by another manual entry (user explicitly changing it)
    if ($exists && $existingSource === 'manual' && $source === 'automatic') {
        logSetWinningNumber("⚠️ PROTECTION: Attempted to overwrite manual entry with automatic - BLOCKED. Keeping existing manual entry.", 'WARNING');
        $source = 'manual'; // Force to manual to protect existing manual entry
        $reason = 'Set by administrator'; // Keep original reason
    }
    
    // Log the decision
    logSetWinningNumber("Source decision: keepAutoMode=" . ($keepAutoMode ? 'true' : 'false') . ", source='{$source}', reason='{$reason}'", 'INFO');
    
    if ($exists) {
        // Update the existing record
        $stmt = $conn->prepare("
            UPDATE next_draw_winning_number
            SET winning_number = ?,
                source = ?,
                reason = ?,
                updated_at = NOW()
            WHERE draw_number = ?
        ");

        if (!$stmt) {
            throw new Exception("Failed to prepare update statement: " . $conn->error);
        }

        $stmt->bind_param("issi", $winningNumber, $source, $reason, $currentDrawNumber);
        logSetWinningNumber("Updating record with winning number $winningNumber for draw #$currentDrawNumber (source: $source)", 'INFO');
    } else {
        // Insert a new record
        $stmt = $conn->prepare("
            INSERT INTO next_draw_winning_number
            (draw_number, winning_number, source, reason, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ");

        if (!$stmt) {
            throw new Exception("Failed to prepare insert statement: " . $conn->error);
        }

        $stmt->bind_param("iiss", $currentDrawNumber, $winningNumber, $source, $reason);
        logSetWinningNumber("Inserting new record with winning number $winningNumber for draw #$currentDrawNumber (source: $source)", 'INFO');
    }

    $success = $stmt->execute();

    if (!$success) {
        throw new Exception("Failed to " . ($exists ? "update" : "insert") . " winning number: " . $stmt->error);
    }

    logSetWinningNumber(($exists ? "Updated" : "Inserted") . " winning number record successfully", 'INFO');
    $winningColor = getNumberColor($winningNumber);

    // Prepare success response
    $response = [
        'status' => 'success',
        'message' => "Winning number set to $winningNumber",
        'data' => [
            'draw_number' => $currentDrawNumber,
            'winning_number' => $winningNumber,
            'winning_color' => $winningColor,
            'source' => $keepAutoMode ? 'automatic' : 'manual',
            'reason' => $keepAutoMode ? 'Auto-selected by smart system' : 'Set by administrator',
            'is_automatic' => $keepAutoMode
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