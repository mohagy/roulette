<?php
/**
 * Get Commission Data API
 * 
 * This script retrieves commission data for display in the commission dashboard.
 */

// Include database connection
require_once 'db_connect.php';

// Start session to get user information
session_start();

// Set headers for JSON response
header('Content-Type: application/json');

// Check if user is logged in - commented out for testing
// if (!isset($_SESSION['user_id'])) {
//     echo json_encode([
//         'status' => 'error',
//         'message' => 'User not authenticated. Please log in.'
//     ]);
//     exit;
// }

// Get date range parameters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-7 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Validate dates
if (!validateDate($startDate) || !validateDate($endDate)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid date format. Please use YYYY-MM-DD.'
    ]);
    exit;
}

try {
    // Get commission rate from settings
    $commissionRateStmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'commission_rate'");
    $commissionRateStmt->execute();
    $commissionRateResult = $commissionRateStmt->get_result();
    $commissionRate = 4; // Default to 4% if not found
    
    if ($commissionRateResult->num_rows > 0) {
        $commissionRateRow = $commissionRateResult->fetch_assoc();
        $commissionRate = floatval($commissionRateRow['setting_value']);
    }
    $commissionRateStmt->close();
    
    // Get commission summary
    $summaryStmt = $conn->prepare("
        SELECT 
            SUM(bet_amount) as total_bets,
            SUM(commission_amount) as total_commission,
            COUNT(*) as total_transactions
        FROM commission
        WHERE date_created BETWEEN ? AND ?
    ");
    $summaryStmt->bind_param("ss", $startDate, $endDate);
    $summaryStmt->execute();
    $summaryResult = $summaryStmt->get_result();
    $summary = $summaryResult->fetch_assoc();
    $summaryStmt->close();
    
    // If no data found, initialize with zeros
    if (!$summary['total_bets']) {
        $summary = [
            'total_bets' => 0,
            'total_commission' => 0,
            'total_transactions' => 0
        ];
    }
    
    // Add commission rate to summary
    $summary['commission_rate'] = $commissionRate;
    
    // Get detailed commission data
    $dataStmt = $conn->prepare("
        SELECT 
            c.commission_id,
            c.user_id,
            u.username,
            c.bet_amount,
            c.commission_amount,
            c.slip_number,
            c.transaction_id,
            c.date_created
        FROM commission c
        JOIN users u ON c.user_id = u.user_id
        WHERE c.date_created BETWEEN ? AND ?
        ORDER BY c.date_created DESC
        LIMIT 100
    ");
    $dataStmt->bind_param("ss", $startDate, $endDate);
    $dataStmt->execute();
    $dataResult = $dataStmt->get_result();
    
    $data = [];
    while ($row = $dataResult->fetch_assoc()) {
        $data[] = $row;
    }
    $dataStmt->close();
    
    // Return success response
    echo json_encode([
        'status' => 'success',
        'summary' => $summary,
        'data' => $data
    ]);
    
} catch (Exception $e) {
    // Return error response
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to get commission data: ' . $e->getMessage()
    ]);
}

// Close the database connection
$conn->close();

/**
 * Validate date format (YYYY-MM-DD)
 */
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}
?>
