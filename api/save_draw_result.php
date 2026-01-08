<?php
/**
 * Save Draw Result API
 *
 * This API endpoint saves the draw result to the database.
 */

// Include database connection
require_once '../php/db_connect.php';
require_once '../php/firebase-helper.php';

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

    // Generate a unique draw ID
    $drawId = 'DRAW-' . date('Ymd') . '-' . $drawNumber;

    // Insert into detailed_draw_results
    $stmt = $pdo->prepare("
        INSERT INTO detailed_draw_results
        (draw_id, draw_number, winning_number, winning_color, notes)
        VALUES (?, ?, ?, ?, ?)
    ");

    $notes = $isForced ? "Forced number set by {$source}" : "Random number";
    $stmt->execute([$drawId, $drawNumber, $winningNumber, $winningColor, $notes]);

    // Insert into game_history
    $stmt = $pdo->prepare("
        INSERT INTO game_history
        (winning_number, winning_color, draw_id)
        VALUES (?, ?, ?)
    ");

    $stmt->execute([$winningNumber, $winningColor, $drawId]);

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

    // Insert into roulette_draw_history
    $stmt = $pdo->prepare("
        INSERT INTO roulette_draw_history
        (draw_number, winning_number, winning_color, is_manual)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$drawNumber, $winningNumber, $winningColor, $isForced ? 1 : 0]);

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

        // Insert a new state record
        $stmt = $pdo->prepare("
            INSERT INTO roulette_state
            (state_type, draw_number, next_draw_number, countdown_time, end_time,
             winning_number, next_winning_number, manual_mode, additional_data)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
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
        // Insert new state
        $stmt = $pdo->prepare("
            INSERT INTO roulette_state
            (state_type, draw_number, next_draw_number, countdown_time, winning_number, additional_data)
            VALUES (?, ?, ?, ?, ?, ?)
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

    // Delete from next_draw_winning_number if it exists
    $stmt = $pdo->prepare("
        DELETE FROM next_draw_winning_number
        WHERE draw_number = ?
    ");

    $stmt->execute([$drawNumber]);

    // 🔥 SAVE TO FIREBASE FIRST (Primary storage)
    try {
        $firebaseSuccess = firebaseSaveDrawResult($drawNumber, $winningNumber, $winningColor, $isForced, $source);
        firebaseUpdateAnalytics($winningNumber, $drawNumber);
        error_log("✅ Draw result saved to Firebase: Draw #{$drawNumber}, Number: {$winningNumber}");
        
        if ($firebaseSuccess) {
            // If Firebase save succeeds, commit MySQL transaction (for backup only)
            $pdo->commit();
        } else {
            // If Firebase fails, still commit MySQL as fallback
            $pdo->commit();
            error_log("⚠️ Firebase save failed, using MySQL as fallback");
        }
    } catch (Exception $e) {
        error_log("❌ Error saving to Firebase: " . $e->getMessage());
        // Commit MySQL transaction as fallback
        $pdo->commit();
    }

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
