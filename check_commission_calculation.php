<?php
// Set response header to JSON
header('Content-Type: application/json');

// Start session to get user information
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Not authenticated'
    ]);
    exit;
}

// Database connection parameters
$servername = "localhost";
$username = "root";  // Default XAMPP username
$password = "";      // Default XAMPP password (empty)
$dbname = "roulette";  // Using the roulette database

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]);
    exit;
}

// Get user ID from session
$userId = $_SESSION['user_id'];

// Get today's date
$today = date('Y-m-d');

// Get the most recent date from commission_summary for this cashier
$mostRecentDate = $today; // Default to today
$recentDateQuery = "SELECT MAX(date_created) as latest_date FROM commission_summary WHERE user_id = ?";
$stmt = $conn->prepare($recentDateQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$recentDateResult = $stmt->get_result();
if ($recentDateResult && $recentDateResult->num_rows > 0) {
    $latestDateRow = $recentDateResult->fetch_assoc();
    if ($latestDateRow['latest_date']) {
        $mostRecentDate = $latestDateRow['latest_date'];
    }
}

// Get commission summary for the most recent day for this cashier
$commissionSummary = null;
$stmt = $conn->prepare("SELECT * FROM commission_summary WHERE date_created = ? AND user_id = ?");
$stmt->bind_param("si", $mostRecentDate, $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $commissionSummary = $result->fetch_assoc();
} else {
    // Create empty summary
    $commissionSummary = [
        'user_id' => $userId,
        'date_created' => $mostRecentDate,
        'total_bets' => 0,
        'total_commission' => 0
    ];
}

// Get commission history (last 30 days) for this cashier
$commissionHistory = [];
$sql = "SELECT date_created, total_bets, total_commission
        FROM commission_summary
        WHERE user_id = ? AND date_created >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY date_created DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $commissionHistory[] = $row;
    }
}

// Get all commission records for this user
$commissionRecords = [];
$stmt = $conn->prepare("SELECT * FROM commission WHERE user_id = ? ORDER BY commission_id DESC LIMIT 50");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $commissionRecords[] = $row;
    }
}

// Get all betting slips for this user
$bettingSlips = [];
$stmt = $conn->prepare("SELECT * FROM betting_slips WHERE user_id = ? ORDER BY slip_id DESC LIMIT 50");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $bettingSlips[] = $row;
    }
}

// Check if there are betting slips without corresponding commission records
$slipsWithoutCommission = [];
foreach ($bettingSlips as $slip) {
    $found = false;
    foreach ($commissionRecords as $commission) {
        if (isset($commission['slip_number']) && $commission['slip_number'] == $slip['slip_number']) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $slipsWithoutCommission[] = $slip;
    }
}

// Calculate what the commission should be based on betting slips
$calculatedCommission = 0;
$calculatedBets = 0;
foreach ($bettingSlips as $slip) {
    if (substr($slip['date_created'], 0, 10) == $today) {
        $calculatedBets += floatval($slip['total_stake']);
        $calculatedCommission += floatval($slip['total_stake']) * 0.04; // 4% commission
    }
}

// Prepare response
$response = [
    'status' => 'success',
    'user_id' => $userId,
    'today' => $today,
    'most_recent_date' => $mostRecentDate,
    'commission_summary' => $commissionSummary,
    'commission_history' => $commissionHistory,
    'commission_records_count' => count($commissionRecords),
    'betting_slips_count' => count($bettingSlips),
    'slips_without_commission_count' => count($slipsWithoutCommission),
    'slips_without_commission' => $slipsWithoutCommission,
    'calculated_commission' => [
        'total_bets' => $calculatedBets,
        'total_commission' => $calculatedCommission
    ],
    'discrepancy' => [
        'total_bets' => $calculatedBets - ($commissionSummary['total_bets'] ?? 0),
        'total_commission' => $calculatedCommission - ($commissionSummary['total_commission'] ?? 0)
    ]
];

// Close connection
$conn->close();

// Return response
echo json_encode($response, JSON_PRETTY_PRINT);
?>
