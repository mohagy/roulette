<?php
// Start session
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Return error JSON if not authorized
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if slip_id is provided
if (!isset($_GET['slip_id']) || !is_numeric($_GET['slip_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid slip ID']);
    exit;
}

$slipId = intval($_GET['slip_id']);

// Database connection parameters
$servername = "localhost";
$username = "root";  // Default XAMPP username
$password = "";      // Default XAMPP password (empty)
$dbname = "roulette";  // Using the roulette database

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

// Get slip details
$query = "SELECT bs.slip_id, bs.slip_number, bs.user_id, u.username, bs.total_stake, bs.potential_payout, 
          bs.created_at, bs.is_paid, bs.is_cancelled, bs.draw_number, bs.winning_number, bs.status,
          bs.paid_out_amount, bs.cashout_time
          FROM betting_slips bs
          JOIN users u ON bs.user_id = u.user_id
          WHERE bs.slip_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $slipId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Betting slip not found']);
    exit;
}

$slip = $result->fetch_assoc();

// Get bet details
$query = "SELECT b.bet_id, b.bet_type, b.bet_description, b.bet_amount, b.multiplier, b.potential_return
          FROM slip_details sd
          JOIN bets b ON sd.bet_id = b.bet_id
          WHERE sd.slip_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $slipId);
$stmt->execute();
$result = $stmt->get_result();

$bets = [];
while ($row = $result->fetch_assoc()) {
    $bets[] = $row;
}

// Close connection
$conn->close();

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'slip' => $slip,
    'bets' => $bets
]);
exit;
?>
