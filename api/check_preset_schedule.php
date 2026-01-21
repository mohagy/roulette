<?php
/**
 * Check Preset Schedule API
 * Checks if a preset schedule exists for today
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
    // Get date parameter (optional, defaults to today)
    $date = $_GET['date'] ?? date('Y-m-d');
    
    // Query for active schedule with this date
    $stmt = $conn->prepare("
        SELECT id, schedule_date, start_draw_number, end_draw_number, total_draws, is_active, created_at
        FROM preset_schedule 
        WHERE schedule_date = ? AND is_active = 1 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $schedule = $result->fetch_assoc();
    $stmt->close();
    
    if ($schedule) {
        echo json_encode([
            'status' => 'success',
            'exists' => true,
            'data' => [
                'schedule_date' => $schedule['schedule_date'],
                'start_draw_number' => (int)$schedule['start_draw_number'],
                'end_draw_number' => (int)$schedule['end_draw_number'],
                'total_draws' => (int)$schedule['total_draws'],
                'is_active' => (bool)$schedule['is_active'],
                'created_at' => $schedule['created_at']
            ]
        ]);
    } else {
        echo json_encode([
            'status' => 'success',
            'exists' => false,
            'data' => null,
            'message' => 'No preset schedule found for ' . $date
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

