<?php
header('Content-Type: application/json');

// Enable more detailed error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include database configuration
require_once 'db_config.php';

// Log all requests for debugging
error_log("save_analytics.php called with method: " . $_SERVER['REQUEST_METHOD']);

// Get data from frontend
$input = file_get_contents('php://input');
error_log("Raw input: " . $input);

$data = json_decode($input, true);

// Validate the data
if (!isset($data['allSpins']) || !isset($data['numberFrequency']) || !isset($data['currentDrawNumber'])) {
    error_log("Missing required data in request");
    echo json_encode(['status' => 'error', 'message' => 'Missing required data']);
    exit;
}

// Encode arrays as JSON strings for storage
$allSpins = json_encode($data['allSpins']);
$numberFrequency = json_encode($data['numberFrequency']);
$currentDrawNumber = intval($data['currentDrawNumber']);

// Log the data for debugging
error_log("Saving allSpins: " . $allSpins);
error_log("Saving numberFrequency: " . $numberFrequency);
error_log("Saving currentDrawNumber: " . $currentDrawNumber);

try {
    // First ensure the record exists
    $checkStmt = $pdo->query("SELECT COUNT(*) FROM roulette_analytics WHERE id = 1");
    $exists = $checkStmt->fetchColumn();
    
    if (!$exists) {
        // Insert initial record if it doesn't exist
        $insertSql = "INSERT INTO roulette_analytics (id, all_spins, number_frequency, current_draw_number) 
                      VALUES (1, :all_spins, :number_frequency, :current_draw_number)";
        $stmt = $pdo->prepare($insertSql);
    } else {
        // Update existing record
        $stmt = $pdo->prepare("UPDATE roulette_analytics 
            SET all_spins = :all_spins,
                number_frequency = :number_frequency,
                current_draw_number = :current_draw_number
            WHERE id = 1");
    }

    $result = $stmt->execute([
        ':all_spins' => $allSpins,
        ':number_frequency' => $numberFrequency,
        ':current_draw_number' => $currentDrawNumber
    ]);
    
    if (!$result) {
        error_log("Database operation failed: " . implode(", ", $stmt->errorInfo()));
        echo json_encode(['status' => 'error', 'message' => 'Database operation failed']);
        exit;
    }
    
    // Verify the data was saved by retrieving it
    $verifyStmt = $pdo->query("SELECT * FROM roulette_analytics WHERE id = 1");
    $result = $verifyStmt->fetch(PDO::FETCH_ASSOC);
    
    error_log("Data verified in database: " . json_encode($result));
    
    echo json_encode([
        'status' => 'success', 
        'message' => 'Analytics data saved successfully',
        'saved_data' => [
            'all_spins' => $result['all_spins'],
            'number_frequency' => $result['number_frequency'],
            'current_draw_number' => $result['current_draw_number']
        ]
    ]);
} catch (PDOException $e) {
    error_log("PDO Exception: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 