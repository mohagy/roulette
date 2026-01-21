<?php
/**
 * Sync Draw Number to Firestore
 * This endpoint syncs the current draw number from MySQL to Firestore for real-time updates
 */

header('Content-Type: application/json');
require_once '../includes/db_connection.php';
require_once '../includes/helper_functions.php';

$response = [
    'status' => 'error',
    'message' => 'An error occurred',
    'timestamp' => time()
];

try {
    // Allow override from POST parameters (for direct sync calls)
    $currentDrawNumber = null;
    $nextDrawNumber = null;
    
    if (isset($_POST['current_draw']) && isset($_POST['next_draw'])) {
        $currentDrawNumber = (int)$_POST['current_draw'];
        $nextDrawNumber = (int)$_POST['next_draw'];
    } else {
        // Get current draw number from database
        $stmt = $conn->prepare("
            SELECT current_draw_number, last_updated, last_reset_date
            FROM roulette_analytics 
            WHERE id = 1
            LIMIT 1
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("No roulette_analytics record found");
        }
        
        $row = $result->fetch_assoc();
        $currentDrawNumber = (int)$row['current_draw_number'];
        $nextDrawNumber = $currentDrawNumber + 1;
        $lastUpdated = $row['last_updated'];
        $lastResetDate = $row['last_reset_date'] ?? null;
        
        $stmt->close();
    }
    
    // Calculate expected draw number based on time (for validation)
    date_default_timezone_set('America/La_Paz');
    $now = new DateTime('now', new DateTimeZone('America/La_Paz'));
    $currentDate = $now->format('Y-m-d');
    $currentHour = (int)$now->format('H');
    $currentMinute = (int)$now->format('i');
    
    $totalMinutes = ($currentHour * 60) + $currentMinute;
    $completedIntervals = floor($totalMinutes / 3);
    $expectedDrawNumber = $completedIntervals + 1;
    
    // Check if reset is needed
    $needsReset = ($lastResetDate === null || $lastResetDate < $currentDate);
    
    // Prepare game state data for Firestore
    $gameStateData = [
        'currentDrawNumber' => $currentDrawNumber,
        'nextDrawNumber' => $nextDrawNumber,
        'expectedDrawNumber' => $expectedDrawNumber,
        'lastUpdated' => $lastUpdated,
        'lastResetDate' => $lastResetDate,
        'needsReset' => $needsReset,
        'syncTimestamp' => time(),
        'timezone' => 'America/La_Paz'
    ];
    
    // Log the sync
    error_log("✅ Draw number sync: Current=$currentDrawNumber, Next=$nextDrawNumber, Expected=$expectedDrawNumber");
    
    $response = [
        'status' => 'success',
        'message' => 'Draw number synced to Firestore',
        'data' => $gameStateData,
        'timestamp' => time()
    ];
    
} catch (Exception $e) {
    error_log("❌ Error syncing draw number to Firestore: " . $e->getMessage());
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>

