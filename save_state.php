<?php
header('Content-Type: application/json');

// Enable more detailed error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include database configuration
require_once 'db_config.php';
require_once 'php/firebase-helper.php';

// Log all requests for debugging
error_log("save_state.php called with method: " . $_SERVER['REQUEST_METHOD']);

// Get data from frontend
$input = file_get_contents('php://input');
error_log("Raw input: " . $input);

$data = json_decode($input, true);

// Validate the data
if (!isset($data['numbers']) || !isset($data['colors']) ||
    !isset($data['lastDraw']) || !isset($data['nextDraw']) ||
    !isset($data['timer'])) {
    error_log("Missing required data in request");
    echo json_encode(['status' => 'error', 'message' => 'Missing required data']);
    exit;
}

// Set default values for empty fields
$numbers = !empty($data['numbers']) ? $data['numbers'] : '';
$colors = !empty($data['colors']) ? $data['colors'] : '';
$lastDraw = !empty($data['lastDraw']) ? $data['lastDraw'] : '#0';
$nextDraw = !empty($data['nextDraw']) ? $data['nextDraw'] : '#1';
$timer = isset($data['timer']) ? intval($data['timer']) : 180; // Changed default from 120 to 180 seconds (3 minutes)
$endTime = isset($data['endTime']) ? $data['endTime'] : (time() * 1000 + ($timer * 1000));

// Log the cleaned data for debugging
error_log("Cleaned numbers: " . $numbers);
error_log("Cleaned colors: " . $colors);
error_log("Last draw: " . $lastDraw);
error_log("Next draw: " . $nextDraw);
error_log("Timer: " . $timer);
error_log("End Time: " . $endTime);

try {
    // Extract draw numbers from the string format
    $currentDrawNumber = intval(str_replace('#', '', $lastDraw));
    $nextDrawNumber = intval(str_replace('#', '', $nextDraw));

    // Always insert a new record for each state update using the normalized structure
    $insertSql = "INSERT INTO roulette_state (
                    state_type,
                    draw_number,
                    next_draw_number,
                    countdown_time,
                    end_time,
                    additional_data
                  ) VALUES (
                    :state_type,
                    :draw_number,
                    :next_draw_number,
                    :countdown_time,
                    :end_time,
                    :additional_data
                  )";
    $stmt = $pdo->prepare($insertSql);

    // Store roll history and colors in the additional_data JSON field
    $additionalData = json_encode([
        'roll_history' => $numbers,
        'roll_colors' => $colors,
        'last_draw_formatted' => $lastDraw,
        'next_draw_formatted' => $nextDraw
    ]);

    $result = $stmt->execute([
        ':state_type' => 'timer_update',
        ':draw_number' => $currentDrawNumber,
        ':next_draw_number' => $nextDrawNumber,
        ':countdown_time' => $timer,
        ':end_time' => $endTime,
        ':additional_data' => $additionalData
    ]);

    if (!$result) {
        error_log("Database operation failed: " . implode(", ", $stmt->errorInfo()));
        echo json_encode(['status' => 'error', 'message' => 'Database operation failed']);
        exit;
    }

    // Get the ID of the newly inserted record
    $newId = $pdo->lastInsertId();

    // Verify the data was saved by retrieving it
    $verifyStmt = $pdo->prepare("SELECT * FROM roulette_state WHERE id = :id");
    $verifyStmt->execute([':id' => $newId]);
    $result = $verifyStmt->fetch();

    error_log("Data verified in database: " . json_encode($result));

    // Extract additional data from JSON
    $additionalData = json_decode($result['additional_data'], true);
    
    // 🔥 ALSO SAVE TO FIREBASE (Primary database)
    try {
        // Parse roll history and colors
        $rollHistory = [];
        $rollColors = [];
        if (!empty($numbers)) {
            $rollHistory = array_map('intval', array_filter(explode(',', $numbers)));
        }
        if (!empty($colors)) {
            $rollColors = array_filter(explode(',', $colors));
        }
        
        $gameState = [
            'drawNumber' => $currentDrawNumber,
            'nextDrawNumber' => $nextDrawNumber,
            'rollHistory' => array_slice($rollHistory, 0, 5),
            'rollColors' => array_slice($rollColors, 0, 5),
            'lastDrawFormatted' => $lastDraw,
            'nextDrawFormatted' => $nextDraw,
            'updatedAt' => date('Y-m-d\TH:i:s.000\Z')
        ];
        
        firebaseUpdate('gameState/current', $gameState);
        
        // Update drawInfo
        $drawInfo = [
            'currentDraw' => $currentDrawNumber,
            'nextDraw' => $nextDrawNumber
        ];
        firebaseWrite('gameState/drawInfo', $drawInfo);
        
        error_log("✅ Game state saved to Firebase: Draw #{$currentDrawNumber}");
    } catch (Exception $e) {
        error_log("❌ Error saving to Firebase: " . $e->getMessage());
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Game state saved successfully',
        'saved_data' => [
            'roll_history' => $additionalData['roll_history'] ?? '',
            'roll_colors' => $additionalData['roll_colors'] ?? '',
            'last_draw' => $additionalData['last_draw_formatted'] ?? ('#' . $result['draw_number']),
            'next_draw' => $additionalData['next_draw_formatted'] ?? ('#' . $result['next_draw_number']),
            'countdown_time' => $result['countdown_time'],
            'end_time' => $result['end_time'],
            'state_type' => $result['state_type'],
            'draw_number' => $result['draw_number'],
            'next_draw_number' => $result['next_draw_number']
        ]
    ]);
} catch (PDOException $e) {
    error_log("PDO Exception: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>