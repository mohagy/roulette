<?php
/**
 * Load Preset Schedule API
 * Loads a preset schedule by date, draw number, or current draw
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
    // Get parameters
    $date = $_GET['date'] ?? null;
    $drawNumber = isset($_GET['draw_number']) ? (int)$_GET['draw_number'] : null;
    $currentDraw = isset($_GET['current_draw']) ? (int)$_GET['current_draw'] : null;
    
    $schedule = null;
    
    if ($date) {
        // Load by date
        $stmt = $conn->prepare("
            SELECT * FROM preset_schedule 
            WHERE schedule_date = ? AND is_active = 1 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $result = $stmt->get_result();
        $schedule = $result->fetch_assoc();
        $stmt->close();
    } elseif ($drawNumber !== null) {
        // Find schedule containing this draw number
        $stmt = $conn->prepare("
            SELECT * FROM preset_schedule 
            WHERE start_draw_number <= ? AND end_draw_number >= ? AND is_active = 1 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->bind_param("ii", $drawNumber, $drawNumber);
        $stmt->execute();
        $result = $stmt->get_result();
        $schedule = $result->fetch_assoc();
        $stmt->close();
    } elseif ($currentDraw !== null) {
        // Find active schedule for today containing current draw
        $today = date('Y-m-d');
        $stmt = $conn->prepare("
            SELECT * FROM preset_schedule 
            WHERE schedule_date = ? AND start_draw_number <= ? AND end_draw_number >= ? AND is_active = 1 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->bind_param("sii", $today, $currentDraw, $currentDraw);
        $stmt->execute();
        $result = $stmt->get_result();
        $schedule = $result->fetch_assoc();
        $stmt->close();
    } else {
        // Default: load today's schedule
        $today = date('Y-m-d');
        $stmt = $conn->prepare("
            SELECT * FROM preset_schedule 
            WHERE schedule_date = ? AND is_active = 1 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $schedule = $result->fetch_assoc();
        $stmt->close();
    }
    
    if ($schedule) {
        // Decode schedule_data JSON
        $scheduleData = json_decode($schedule['schedule_data'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Failed to decode schedule_data: ' . json_last_error_msg());
        }
        
        // Decode pattern_data JSON if available (handle NULL for old schedules)
        $patternData = null;
        if (!empty($schedule['pattern_data'])) {
            $patternData = json_decode($schedule['pattern_data'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Log warning but don't fail - pattern_data is optional
                error_log('Warning: Failed to decode pattern_data: ' . json_last_error_msg());
                $patternData = null;
            }
        }
        
        echo json_encode([
            'status' => 'success',
            'data' => [
                'id' => (int)$schedule['id'],
                'schedule_date' => $schedule['schedule_date'],
                'start_draw_number' => (int)$schedule['start_draw_number'],
                'end_draw_number' => (int)$schedule['end_draw_number'],
                'time_preset' => $schedule['time_preset'],
                'pattern_type' => $schedule['pattern_type'],
                'total_draws' => (int)$schedule['total_draws'],
                'is_active' => (bool)$schedule['is_active'],
                'schedule_data' => $scheduleData,
                'pattern_data' => $patternData, // Include pattern_data (can be null)
                'created_at' => $schedule['created_at'],
                'updated_at' => $schedule['updated_at']
            ]
        ]);
    } else {
        echo json_encode([
            'status' => 'not_found',
            'message' => 'No preset schedule found',
            'data' => null
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} finally {
    $conn->close();
}
?>

