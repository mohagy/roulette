<?php
/**
 * Get Current Preset API
 * Gets the preset number for a specific draw number
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
    // Get draw_number parameter (required)
    $drawNumber = isset($_GET['draw_number']) ? (int)$_GET['draw_number'] : null;
    
    if ($drawNumber === null) {
        throw new Exception('draw_number parameter is required');
    }
    
    // Find active schedule containing this draw number
    // Try today's date first
    $today = date('Y-m-d');
    $stmt = $conn->prepare("
        SELECT * FROM preset_schedule 
        WHERE schedule_date = ? AND start_draw_number <= ? AND end_draw_number >= ? AND is_active = 1 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->bind_param("sii", $today, $drawNumber, $drawNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    $schedule = $result->fetch_assoc();
    $stmt->close();
    
    // If not found for today, try any active schedule
    if (!$schedule) {
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
    }
    
    if ($schedule) {
        // Decode schedule_data JSON
        $scheduleData = json_decode($schedule['schedule_data'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Failed to decode schedule_data: ' . json_last_error_msg());
        }
        
        // Helper function to get roulette number color
        function getNumberColor($number) {
            if ($number == 0) {
                return 'green';
            }
            $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
            return in_array($number, $redNumbers) ? 'red' : 'black';
        }
        
        // Check if schedule_data is a simple array of numbers or array of objects
        if (!empty($scheduleData) && is_numeric($scheduleData[0])) {
            // Simple array format: [15, 23, 7, ...]
            // Calculate index based on draw_number
            $startDrawNumber = (int)$schedule['start_draw_number'];
            $index = $drawNumber - $startDrawNumber;
            
            if ($index >= 0 && $index < count($scheduleData)) {
                $presetNumber = (int)$scheduleData[$index];
                
                // Get pattern if available
                $pattern = 'Base pattern';
                if (!empty($schedule['pattern_data'])) {
                    $patternData = json_decode($schedule['pattern_data'], true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($patternData) && isset($patternData[$index])) {
                        $pattern = $patternData[$index];
                    }
                }
                
                // Calculate scheduled time for this draw number
                // Each draw is 3 minutes apart, starting from midnight
                $totalMinutes = ($drawNumber - $startDrawNumber) * 3;
                $hours = floor($totalMinutes / 60) % 24;
                $minutes = $totalMinutes % 60;
                $scheduledTime = sprintf('%02d:%02d', $hours, $minutes);
                
                // ⏰ TIME-BASED VALIDATION: Use SERVER TIME (Georgetown) for validation
                // This ensures all devices get the same validation result regardless of their local clock
                date_default_timezone_set('America/Guyana'); // Ensure correct timezone
                $serverNow = new DateTime('now', new DateTimeZone('America/Guyana'));
                $serverHour = (int)$serverNow->format('H');
                $serverMinute = (int)$serverNow->format('i');
                
                $scheduledMinutes = $hours * 60 + $minutes;
                $serverCurrentMinutes = $serverHour * 60 + $serverMinute;
                
                // Calculate if we're within the 3-minute window for this draw
                // A draw is valid from its scheduled time until 3 minutes after (the next draw's start)
                // Example: Draw 150 at 07:27 is valid from 07:27:00 to 07:29:59
                // Draw 149 at 07:24 is valid from 07:24:00 to 07:26:59
                $drawStartMinutes = $scheduledMinutes;
                $drawEndMinutes = $scheduledMinutes + 3; // 3-minute window
                
                // Check if current server time is within this draw's 3-minute window
                $isTimeValid = ($serverCurrentMinutes >= $drawStartMinutes && $serverCurrentMinutes < $drawEndMinutes);
                
                // IMPORTANT: Always return the preset number if it exists, even if time has passed
                // The time_valid flag allows clients to decide whether to USE it (for TV display) or just DISPLAY it (for admin panel)
                // Don't block admin panel from seeing preset numbers - they need to see them regardless of time
                
                // Get color
                $color = getNumberColor($presetNumber);
                
                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'draw_number' => $drawNumber,
                        'winning_number' => $presetNumber,
                        'color' => $color,
                        'pattern' => $pattern,
                        'scheduled_time' => $scheduledTime,
                        'time_valid' => $isTimeValid, // Flag to indicate if this preset can be used (not expired)
                        'message' => $isTimeValid ? 'Preset number available' : 'Preset number found but scheduled time has passed (scheduled: ' . $scheduledTime . ', current: ' . date('H:i') . ')'
                    ]
                ]);
            } else {
                echo json_encode([
                    'status' => 'not_found',
                    'message' => 'Preset number not found for draw #' . $drawNumber . ' (index out of range)',
                    'data' => null
                ]);
            }
        } else {
            // Array of objects format: [{draw_number: 1, winning_number: 15, ...}, ...]
            // Find the preset entry for this draw number
            $presetEntry = null;
            foreach ($scheduleData as $entry) {
                if (is_array($entry) && isset($entry['draw_number']) && (int)$entry['draw_number'] === $drawNumber) {
                    $presetEntry = $entry;
                    break;
                }
            }
            
            if ($presetEntry) {
                // Handle both 'number' and 'winning_number' field names for backward compatibility
                $winningNumber = isset($presetEntry['winning_number']) ? (int)$presetEntry['winning_number'] : 
                                 (isset($presetEntry['number']) ? (int)$presetEntry['number'] : null);
                
                if ($winningNumber !== null) {
                    // Get scheduled time if available
                    $scheduledTime = $presetEntry['scheduled_time'] ?? null;
                    
                    // ⏰ TIME-BASED VALIDATION: Preset numbers can only be used if scheduled time hasn't passed
                    $isTimeValid = true;
                    if ($scheduledTime) {
                        // Parse scheduled time (format: HH:MM)
                        $timeParts = explode(':', $scheduledTime);
                        if (count($timeParts) === 2) {
                            $scheduledHour = (int)$timeParts[0];
                            $scheduledMinute = (int)$timeParts[1];
                            
                            // Get current time
                            $currentHour = (int)date('H');
                            $currentMinute = (int)date('i');
                            
                            // Calculate scheduled time in minutes since midnight
                            $scheduledMinutes = $scheduledHour * 60 + $scheduledMinute;
                            
                            // Use SERVER TIME (Georgetown) for validation - ensures all devices get same result
                            date_default_timezone_set('America/Guyana');
                            $serverNow = new DateTime('now', new DateTimeZone('America/Guyana'));
                            $serverHour = (int)$serverNow->format('H');
                            $serverMinute = (int)$serverNow->format('i');
                            $serverCurrentMinutes = $serverHour * 60 + $serverMinute;
                            
                            // Check if current server time is within this draw's 3-minute window
                            $drawStartMinutes = $scheduledMinutes;
                            $drawEndMinutes = $scheduledMinutes + 3; // 3-minute window
                            $isTimeValid = ($serverCurrentMinutes >= $drawStartMinutes && $serverCurrentMinutes < $drawEndMinutes);
                        }
                    }
                    
                    // IMPORTANT: Always return the preset number if it exists, even if time has passed
                    // The time_valid flag allows clients to decide whether to USE it (for TV display) or just DISPLAY it (for admin panel)
                    // Don't block admin panel from seeing preset numbers - they need to see them regardless of time
                    
                    // Get color if not provided
                    $color = isset($presetEntry['color']) ? $presetEntry['color'] : getNumberColor($winningNumber);
                    
                    echo json_encode([
                        'status' => 'success',
                        'data' => [
                            'draw_number' => (int)$presetEntry['draw_number'],
                            'winning_number' => $winningNumber,
                            'color' => $color,
                            'scheduled_time' => $scheduledTime,
                            'pattern' => $presetEntry['pattern'] ?? null,
                            'time_valid' => $isTimeValid, // Flag to indicate if this preset can be used (not expired)
                            'message' => $isTimeValid ? 'Preset number available' : 'Preset number found but scheduled time has passed (scheduled: ' . $scheduledTime . ', current: ' . date('H:i') . ')'
                        ]
                    ]);
                } else {
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Invalid preset entry: missing winning_number',
                        'data' => null
                    ]);
                }
            } else {
                echo json_encode([
                    'status' => 'not_found',
                    'message' => 'Preset number not found for draw #' . $drawNumber,
                    'data' => null
                ]);
            }
        }
    } else {
        echo json_encode([
            'status' => 'not_found',
            'message' => 'No active preset schedule found containing draw #' . $drawNumber,
            'data' => null
        ]);
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

