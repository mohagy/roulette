<?php
/**
 * Save Preset Schedule API
 * Saves a preset schedule (480 numbers for 24 hours) to the database
 */

header('Content-Type: application/json');
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Include database configuration
require_once '../php/db_config.php';

// Database connection
$servername = DB_HOST;
$username = DB_USER;
$password = DB_PASS;
$dbname = DB_NAME;

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]);
    exit;
}

try {
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    // If JSON input is empty, try POST parameters
    if (empty($input)) {
        $input = $_POST;
    }
    
    // Validate required fields
    $scheduleDate = $input['schedule_date'] ?? date('Y-m-d');
    $startDrawNumber = isset($input['start_draw_number']) ? (int)$input['start_draw_number'] : null;
    $endDrawNumber = isset($input['end_draw_number']) ? (int)$input['end_draw_number'] : null;
    $timePreset = $input['time_preset'] ?? 'auto';
    $patternType = $input['pattern_type'] ?? 'smart';
    $scheduleData = $input['schedule_data'] ?? null;
    $patternData = $input['pattern_data'] ?? null;
    
    // Validate schedule_data
    if ($scheduleData === null) {
        throw new Exception('schedule_data is required');
    }
    
    // If schedule_data is a string, decode it
    if (is_string($scheduleData)) {
        $scheduleData = json_decode($scheduleData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON in schedule_data: ' . json_last_error_msg());
        }
    }
    
    // Validate schedule_data is an array
    if (!is_array($scheduleData)) {
        throw new Exception('schedule_data must be an array');
    }
    
    // Validate we have 480 entries (or at least some entries)
    $totalDraws = count($scheduleData);
    if ($totalDraws === 0) {
        throw new Exception('schedule_data cannot be empty');
    }
    
    // Calculate start and end draw numbers from schedule_data if not provided
    if ($startDrawNumber === null || $endDrawNumber === null) {
        // Handle both array of numbers and array of objects
        if (isset($scheduleData[0]) && is_array($scheduleData[0])) {
            // Array of objects with draw_number
            $drawNumbers = array_column($scheduleData, 'draw_number');
            if (empty($drawNumbers)) {
                throw new Exception('schedule_data must contain draw_number fields');
            }
            $startDrawNumber = min($drawNumbers);
            $endDrawNumber = max($drawNumbers);
        } else {
            // Simple array of numbers - use provided start_draw_number or calculate
            if ($startDrawNumber === null) {
                // Try to get from first element if it's an object
                if (isset($scheduleData[0]) && is_array($scheduleData[0]) && isset($scheduleData[0]['draw_number'])) {
                    $startDrawNumber = $scheduleData[0]['draw_number'];
                    $endDrawNumber = $startDrawNumber + count($scheduleData) - 1;
                } else {
                    // Default: start from 1
                    $startDrawNumber = 1;
                    $endDrawNumber = count($scheduleData);
                }
            } else {
                $endDrawNumber = $startDrawNumber + count($scheduleData) - 1;
            }
        }
    }
    
    // Encode schedule_data as JSON
    $scheduleDataJson = json_encode($scheduleData);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Failed to encode schedule_data: ' . json_last_error_msg());
    }
    
    // Process pattern_data if provided
    $patternDataJson = null;
    if ($patternData !== null) {
        // If pattern_data is a string, decode it first
        if (is_string($patternData)) {
            $patternData = json_decode($patternData, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON in pattern_data: ' . json_last_error_msg());
            }
        }
        
        // Validate pattern_data is an array
        if (!is_array($patternData)) {
            throw new Exception('pattern_data must be an array');
        }
        
        // Encode pattern_data as JSON
        $patternDataJson = json_encode($patternData);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Failed to encode pattern_data: ' . json_last_error_msg());
        }
    }
    
    // Check if schedule for this date already exists
    $stmt = $conn->prepare("SELECT id FROM preset_schedule WHERE schedule_date = ?");
    $stmt->bind_param("s", $scheduleDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $existingSchedule = $result->fetch_assoc();
    $stmt->close();
    
    if ($existingSchedule) {
        // Update existing schedule
        // First, deactivate all schedules for this date
        $stmt = $conn->prepare("UPDATE preset_schedule SET is_active = 0 WHERE schedule_date = ?");
        $stmt->bind_param("s", $scheduleDate);
        $stmt->execute();
        $stmt->close();
        
        // Update the schedule
        if ($patternDataJson !== null) {
            // Update with pattern_data
            $stmt = $conn->prepare("
                UPDATE preset_schedule 
                SET start_draw_number = ?, 
                    end_draw_number = ?, 
                    time_preset = ?, 
                    pattern_type = ?, 
                    schedule_data = ?, 
                    pattern_data = ?,
                    total_draws = ?, 
                    is_active = 1,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->bind_param("iissssii", $startDrawNumber, $endDrawNumber, $timePreset, $patternType, $scheduleDataJson, $patternDataJson, $totalDraws, $existingSchedule['id']);
        } else {
            // Update without pattern_data (keep existing pattern_data)
            $stmt = $conn->prepare("
                UPDATE preset_schedule 
                SET start_draw_number = ?, 
                    end_draw_number = ?, 
                    time_preset = ?, 
                    pattern_type = ?, 
                    schedule_data = ?, 
                    total_draws = ?, 
                    is_active = 1,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->bind_param("iisssii", $startDrawNumber, $endDrawNumber, $timePreset, $patternType, $scheduleDataJson, $totalDraws, $existingSchedule['id']);
        }
        
        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Preset schedule updated successfully',
                'data' => [
                    'schedule_date' => $scheduleDate,
                    'start_draw_number' => $startDrawNumber,
                    'end_draw_number' => $endDrawNumber,
                    'total_draws' => $totalDraws,
                    'id' => $existingSchedule['id']
                ]
            ]);
        } else {
            throw new Exception('Failed to update preset schedule: ' . $stmt->error);
        }
        $stmt->close();
    } else {
        // Insert new schedule
        // First, deactivate any old schedules for this date (shouldn't happen, but safety check)
        $stmt = $conn->prepare("UPDATE preset_schedule SET is_active = 0 WHERE schedule_date = ?");
        $stmt->bind_param("s", $scheduleDate);
        $stmt->execute();
        $stmt->close();
        
        // Insert new schedule
        if ($patternDataJson !== null) {
            // Insert with pattern_data
            $stmt = $conn->prepare("
                INSERT INTO preset_schedule 
                (schedule_date, start_draw_number, end_draw_number, time_preset, pattern_type, schedule_data, pattern_data, total_draws, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->bind_param("siissssi", $scheduleDate, $startDrawNumber, $endDrawNumber, $timePreset, $patternType, $scheduleDataJson, $patternDataJson, $totalDraws);
        } else {
            // Insert without pattern_data
            $stmt = $conn->prepare("
                INSERT INTO preset_schedule 
                (schedule_date, start_draw_number, end_draw_number, time_preset, pattern_type, schedule_data, total_draws, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->bind_param("siisssi", $scheduleDate, $startDrawNumber, $endDrawNumber, $timePreset, $patternType, $scheduleDataJson, $totalDraws);
        }
        
        if ($stmt->execute()) {
            $newId = $conn->insert_id;
            echo json_encode([
                'status' => 'success',
                'message' => 'Preset schedule saved successfully',
                'data' => [
                    'schedule_date' => $scheduleDate,
                    'start_draw_number' => $startDrawNumber,
                    'end_draw_number' => $endDrawNumber,
                    'total_draws' => $totalDraws,
                    'id' => $newId
                ]
            ]);
        } else {
            throw new Exception('Failed to insert preset schedule: ' . $stmt->error);
        }
        $stmt->close();
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} finally {
    $conn->close();
}
?>

