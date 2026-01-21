<?php
/**
 * Save Draw Result API
 *
 * This API endpoint saves the draw result to the database.
 */

// Include database connection
require_once '../php/db_connect.php';

// Set headers
header('Content-Type: application/json');

// Initialize response
$response = [
    'status' => 'error',
    'message' => 'An error occurred',
    'data' => []
];

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

// Get the POST data
$drawNumber = isset($_POST['draw_number']) ? intval($_POST['draw_number']) : null;
$winningNumber = isset($_POST['winning_number']) ? intval($_POST['winning_number']) : null;
$winningColor = isset($_POST['winning_color']) ? $_POST['winning_color'] : null;
$isForced = isset($_POST['is_forced']) ? intval($_POST['is_forced']) : 0;
$source = isset($_POST['source']) ? $_POST['source'] : 'unknown';

// Validate the data
if ($drawNumber === null || $winningNumber === null || $winningColor === null) {
    $response['message'] = 'Missing required parameters';
    echo json_encode($response);
    exit;
}

// ⚠️ CRITICAL: Ensure draw number never exceeds 480 (max draws per day)
// Draw numbers reset daily: 1-480 (3-minute intervals = 480 draws per day)
if ($drawNumber > 480) {
    $response['message'] = 'Invalid draw number: Draw numbers must be between 1 and 480';
    echo json_encode($response);
    exit;
}

if ($drawNumber < 1) {
    $response['message'] = 'Invalid draw number: Draw numbers must be between 1 and 480';
    echo json_encode($response);
    exit;
}

// Validate the winning number
if ($winningNumber < 0 || $winningNumber > 36) {
    $response['message'] = 'Invalid winning number';
    echo json_encode($response);
    exit;
}

// Validate the winning color
if (!in_array($winningColor, ['red', 'black', 'green'])) {
    $response['message'] = 'Invalid winning color';
    echo json_encode($response);
    exit;
}

try {
    // Begin transaction
    $pdo->beginTransaction();

    // ⚠️ CRITICAL: Check for manually forced number in next_draw_winning_number
    // If a manually forced number exists for this draw, ALWAYS use it (user explicitly set it)
    // The user's explicit manual setting takes priority over everything
    $manualForcedStmt = $pdo->prepare("
        SELECT winning_number, source, reason
        FROM next_draw_winning_number
        WHERE draw_number = ? AND source = 'manual'
        LIMIT 1
    ");
    $manualForcedStmt->execute([$drawNumber]);
    $manualForced = $manualForcedStmt->fetch(PDO::FETCH_ASSOC);
    
    // If a manually forced number exists, ALWAYS use it (user explicitly set it)
    if ($manualForced) {
        $winningNumber = intval($manualForced['winning_number']);
        $source = 'manual';
        $isForced = 1;
        
        // Determine color based on the manually forced number
        if ($winningNumber === 0) {
            $winningColor = 'green';
        } else {
            $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
            $winningColor = in_array($winningNumber, $redNumbers) ? 'red' : 'black';
        }
    }

    // Generate a unique draw ID
    $drawId = 'DRAW-' . date('Ymd') . '-' . $drawNumber;

    // Set server timezone
    date_default_timezone_set('America/Guyana');
    $now = new DateTime('now', new DateTimeZone('America/Guyana'));
    $drawTime = $now->format('Y-m-d H:i:s');

    // Insert into detailed_draw_results
    $stmt = $pdo->prepare("
        INSERT INTO detailed_draw_results
        (draw_id, draw_number, winning_number, winning_color, notes)
        VALUES (?, ?, ?, ?, ?)
    ");

    $notes = $isForced ? "Forced number set by {$source}" : "Random number";
    $stmt->execute([$drawId, $drawNumber, $winningNumber, $winningColor, $notes]);
    
    // ⏰ CRITICAL: Also insert into analytics_history table (new analytics system)
    // Check if this draw is from preset_schedule (only if not manually forced)
    $presetScheduleId = null;
    $isPreset = 0;
    $patternType = null;
    
    // ⚠️ CRITICAL: Priority: Manual forced > Preset schedule > Random
    // If a user explicitly set a number manually, ALWAYS save it as 'manual' in analytics_history
    // The display logic in get_analytics_history.php will handle showing it as preset if it matches
    if ($manualForced) {
        // User explicitly set this number manually - always save as 'manual'
        $analyticsSource = 'manual';
        $isPreset = 0;
    } else if ($source === 'preset_schedule') {
        // Check if this draw matches a preset schedule
        $presetStmt = $pdo->prepare("
            SELECT id, pattern_type 
            FROM preset_schedule 
            WHERE start_draw_number <= ? AND end_draw_number >= ? AND is_active = 1 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $presetStmt->execute([$drawNumber, $drawNumber]);
        $preset = $presetStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($preset) {
            $presetScheduleId = $preset['id'];
            $isPreset = 1;
            $patternType = $preset['pattern_type'];
            $analyticsSource = 'preset_schedule';
        } else {
            $analyticsSource = $isForced ? 'manual' : 'random';
        }
    } else {
        $analyticsSource = $isForced ? 'manual' : 'random';
    }
    
    // Insert or update analytics_history
    try {
        $analyticsStmt = $pdo->prepare("
            INSERT INTO analytics_history 
            (draw_number, winning_number, winning_color, draw_time, source, preset_schedule_id, is_preset, pattern_type, server_timezone)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'America/Guyana')
            ON DUPLICATE KEY UPDATE
                winning_number = VALUES(winning_number),
                winning_color = VALUES(winning_color),
                draw_time = VALUES(draw_time),
                source = VALUES(source),
                preset_schedule_id = VALUES(preset_schedule_id),
                is_preset = VALUES(is_preset),
                pattern_type = VALUES(pattern_type),
                updated_at = CURRENT_TIMESTAMP
        ");
        
        $analyticsStmt->execute([
            $drawNumber,
            $winningNumber,
            $winningColor,
            $drawTime,
            $analyticsSource,
            $presetScheduleId,
            $isPreset,
            $patternType
        ]);
    } catch (PDOException $e) {
        // Log error but don't fail the transaction - analytics_history might not exist yet
        error_log("Warning: Could not save to analytics_history: " . $e->getMessage());
    }

    // NOTE: Removed redundant writes to game_history and roulette_draw_history
    // All draw results are now stored only in detailed_draw_results (primary source)
    // Aggregated analytics are stored in roulette_analytics

    // Get the most recent state record
    $stmt = $pdo->prepare("
        SELECT state_type, draw_number, next_draw_number, countdown_time, end_time,
               winning_number, next_winning_number, manual_mode, additional_data
        FROM roulette_state
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute();
    $state = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($state) {
        // Extract additional data
        $additionalData = json_decode($state['additional_data'] ?? '{}', true);

        // Update roll history
        $rollHistory = explode(',', $additionalData['roll_history'] ?? '');
        if (empty($rollHistory[0])) {
            $rollHistory = [];
        }
        array_unshift($rollHistory, $winningNumber);
        $rollHistory = array_slice($rollHistory, 0, 5);
        $newRollHistory = implode(',', $rollHistory);

        // Update roll colors
        $rollColors = explode(',', $additionalData['roll_colors'] ?? '');
        if (empty($rollColors[0])) {
            $rollColors = [];
        }
        array_unshift($rollColors, $winningColor);
        $rollColors = array_slice($rollColors, 0, 5);
        $newRollColors = implode(',', $rollColors);

        // Update or insert state with id=1 (single row pattern)
        $stmt = $pdo->prepare("
            INSERT INTO roulette_state
            (id, state_type, draw_number, next_draw_number, countdown_time, end_time,
             winning_number, next_winning_number, manual_mode, additional_data, updated_at)
            VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                state_type = VALUES(state_type),
                draw_number = VALUES(draw_number),
                next_draw_number = VALUES(next_draw_number),
                countdown_time = VALUES(countdown_time),
                end_time = VALUES(end_time),
                winning_number = VALUES(winning_number),
                next_winning_number = VALUES(next_winning_number),
                manual_mode = VALUES(manual_mode),
                additional_data = VALUES(additional_data),
                updated_at = NOW()
        ");

        $newAdditionalData = json_encode([
            'roll_history' => $newRollHistory,
            'roll_colors' => $newRollColors,
            'last_draw_formatted' => "#{$drawNumber}",
            'next_draw_formatted' => "#" . ($drawNumber + 1),
            'notes' => $notes,
            'source' => $source
        ]);

        $stmt->execute([
            'draw_result',
            $drawNumber,
            $drawNumber + 1,
            $state['countdown_time'],
            $state['end_time'],
            $winningNumber,
            $state['next_winning_number'],
            $state['manual_mode'],
            $newAdditionalData
        ]);
    } else {
        // Update or insert new state with id=1 (single row pattern)
        $stmt = $pdo->prepare("
            INSERT INTO roulette_state
            (id, state_type, draw_number, next_draw_number, countdown_time, winning_number, additional_data, updated_at)
            VALUES (1, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                state_type = VALUES(state_type),
                draw_number = VALUES(draw_number),
                next_draw_number = VALUES(next_draw_number),
                countdown_time = VALUES(countdown_time),
                winning_number = VALUES(winning_number),
                additional_data = VALUES(additional_data),
                updated_at = NOW()
        ");

        $initialRollHistory = $winningNumber . ',0,0,0,0';
        $initialRollColors = $winningColor . ',green,green,green,green';

        $newAdditionalData = json_encode([
            'roll_history' => $initialRollHistory,
            'roll_colors' => $initialRollColors,
            'last_draw_formatted' => "#{$drawNumber}",
            'next_draw_formatted' => "#" . ($drawNumber + 1),
            'notes' => $notes,
            'source' => $source
        ]);

        $stmt->execute([
            'draw_result',
            $drawNumber,
            $drawNumber + 1,
            180,
            $winningNumber,
            $newAdditionalData
        ]);
    }

    // Update roulette_analytics
    $stmt = $pdo->prepare("
        SELECT all_spins, number_frequency, current_draw_number FROM roulette_analytics LIMIT 1
    ");
    $stmt->execute();
    $analytics = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($analytics) {
        // Update all spins
        $allSpins = json_decode($analytics['all_spins'], true);
        if (!is_array($allSpins)) {
            $allSpins = [];
        }
        array_unshift($allSpins, $winningNumber);
        $allSpins = array_slice($allSpins, 0, 100); // Keep only the last 100 spins

        // Update number frequency
        $numberFrequency = json_decode($analytics['number_frequency'], true);
        if (!is_array($numberFrequency)) {
            $numberFrequency = array_fill(0, 37, 0);
        }
        $numberFrequency[$winningNumber]++;

        // Update the analytics
        $stmt = $pdo->prepare("
            UPDATE roulette_analytics
            SET all_spins = ?,
                number_frequency = ?,
                current_draw_number = ?
        ");

        $stmt->execute([
            json_encode($allSpins),
            json_encode($numberFrequency),
            $drawNumber + 1
        ]);
    } else {
        // Insert new analytics
        $allSpins = [$winningNumber];
        $numberFrequency = array_fill(0, 37, 0);
        $numberFrequency[$winningNumber] = 1;

        $stmt = $pdo->prepare("
            INSERT INTO roulette_analytics
            (all_spins, number_frequency, current_draw_number)
            VALUES (?, ?, ?)
        ");

        $stmt->execute([
            json_encode($allSpins),
            json_encode($numberFrequency),
            $drawNumber + 1
        ]);
    }

    // ⚠️ CRITICAL: Delete forced number for this draw after it's been completed
    // This ensures forced numbers are cleared after the draw passes
    // The system will then use preset schedule numbers for future draws
    try {
        $deleteStmt = $pdo->prepare("
            DELETE FROM next_draw_winning_number
            WHERE draw_number = ?
        ");
        $deleteStmt->execute([$drawNumber]);
        
        // Also clean up any forced numbers for draws that have already passed
        // This prevents stale forced numbers from lingering in the database
        $cleanupStmt = $pdo->prepare("
            DELETE FROM next_draw_winning_number
            WHERE draw_number < ?
        ");
        $cleanupStmt->execute([$drawNumber]);
    } catch (PDOException $e) {
        // Ignore if table doesn't exist or other error
    }

    // Commit transaction
    $pdo->commit();

    // Set success response
    $response['status'] = 'success';
    $response['message'] = 'Draw result saved successfully';
    $response['data'] = [
        'draw_id' => $drawId,
        'draw_number' => $drawNumber,
        'winning_number' => $winningNumber,
        'winning_color' => $winningColor,
        'next_draw_number' => $drawNumber + 1
    ];

} catch (PDOException $e) {
    // Rollback transaction
    $pdo->rollBack();

    // Set error response
    $response['message'] = 'Database error: ' . $e->getMessage();
}

// Return the response
echo json_encode($response);
