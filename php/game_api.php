<?php
// Include database connection
require_once 'db_connect.php';

// Set response header to JSON
header('Content-Type: application/json');

// Get the HTTP method and action from the request
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Default response
$response = array(
    'status' => 'error',
    'message' => 'Invalid request'
);

// Process based on request method and action
if ($method === 'POST') {
    // Decode the JSON request body
    $json_data = json_decode(file_get_contents('php://input'), true);
    
    if ($action === 'place_bet') {
        if (isset($json_data['player_id']) && isset($json_data['bet_type']) && 
            isset($json_data['bet_numbers']) && isset($json_data['bet_amount'])) {
            
            $player_id = intval($json_data['player_id']);
            $bet_type = $json_data['bet_type'];
            $bet_numbers = $json_data['bet_numbers'];
            $bet_amount = floatval($json_data['bet_amount']);
            
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Check if player has enough balance
                $stmt = $conn->prepare("SELECT cash_balance FROM players WHERE player_id = ?");
                $stmt->bind_param("i", $player_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $player = $result->fetch_assoc();
                
                if ($player['cash_balance'] < $bet_amount) {
                    throw new Exception("Insufficient funds");
                }
                
                // Deduct the bet amount from player's balance
                $stmt = $conn->prepare("UPDATE players SET cash_balance = cash_balance - ? WHERE player_id = ?");
                $stmt->bind_param("di", $bet_amount, $player_id);
                $stmt->execute();
                
                // Insert the bet
                $stmt = $conn->prepare("INSERT INTO bets (player_id, bet_type, bet_numbers, bet_amount) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("issd", $player_id, $bet_type, $bet_numbers, $bet_amount);
                $stmt->execute();
                $bet_id = $conn->insert_id;
                
                // Commit the transaction
                $conn->commit();
                
                $response = array(
                    'status' => 'success',
                    'message' => 'Bet placed successfully',
                    'bet_id' => $bet_id
                );
                
            } catch (Exception $e) {
                // Rollback the transaction on error
                $conn->rollback();
                
                $response = array(
                    'status' => 'error',
                    'message' => $e->getMessage()
                );
            }
        } else {
            $response = array(
                'status' => 'error',
                'message' => 'Missing required bet parameters'
            );
        }
    } elseif ($action === 'record_result') {
        if (isset($json_data['winning_number']) && isset($json_data['winning_color'])) {
            $winning_number = intval($json_data['winning_number']);
            $winning_color = $json_data['winning_color'];
            
            // Insert game result
            $stmt = $conn->prepare("INSERT INTO game_history (winning_number, winning_color) VALUES (?, ?)");
            $stmt->bind_param("is", $winning_number, $winning_color);
            
            if ($stmt->execute()) {
                $game_id = $conn->insert_id;
                
                $response = array(
                    'status' => 'success',
                    'message' => 'Game result recorded',
                    'game_id' => $game_id
                );
                
                // Process all pending bets and update player balances
                // This will be complex logic to check which bets won
                processWinningBets($conn, $winning_number, $winning_color);
                
            } else {
                $response = array(
                    'status' => 'error',
                    'message' => 'Failed to record game result'
                );
            }
            
            $stmt->close();
        } else {
            $response = array(
                'status' => 'error',
                'message' => 'Missing winning number or color'
            );
        }
    } elseif ($action === 'complete_bets') {
        if (isset($json_data['player_id']) && isset($json_data['bets']) && isset($json_data['total_stake'])) {
            $player_id = intval($json_data['player_id']);
            $bets = $json_data['bets'];
            $total_stake = floatval($json_data['total_stake']);
            $potential_payout = floatval($json_data['potential_payout']);
            
            // Generate a unique 8-digit slip number
            $slip_number = generateSlipNumber();
            
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Insert the betting slip
                $stmt = $conn->prepare("INSERT INTO betting_slips (slip_number, player_id, total_stake, potential_payout) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("sidd", $slip_number, $player_id, $total_stake, $potential_payout);
                $stmt->execute();
                $slip_id = $conn->insert_id;
                
                // Insert each bet detail
                foreach ($bets as $bet) {
                    $bet_id = intval($bet['bet_id']);
                    
                    $stmt = $conn->prepare("INSERT INTO slip_details (slip_id, bet_id) VALUES (?, ?)");
                    $stmt->bind_param("ii", $slip_id, $bet_id);
                    $stmt->execute();
                }
                
                // Commit the transaction
                $conn->commit();
                
                $response = array(
                    'status' => 'success',
                    'message' => 'Betting slip created successfully',
                    'slip_id' => $slip_id,
                    'slip_number' => $slip_number
                );
                
            } catch (Exception $e) {
                // Rollback the transaction on error
                $conn->rollback();
                
                $response = array(
                    'status' => 'error',
                    'message' => $e->getMessage()
                );
            }
        } else {
            $response = array(
                'status' => 'error',
                'message' => 'Missing required slip parameters'
            );
        }
    }
} elseif ($method === 'GET') {
    if ($action === 'get_history') {
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
        
        $stmt = $conn->prepare("SELECT * FROM game_history ORDER BY played_at DESC LIMIT ?");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $history = array();
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
        
        $response = array(
            'status' => 'success',
            'history' => $history
        );
        
        $stmt->close();
    } elseif ($action === 'verify_slip') {
        if (isset($_GET['slip_number'])) {
            $slip_number = $_GET['slip_number'];
            
            $stmt = $conn->prepare("
                SELECT bs.*, p.username 
                FROM betting_slips bs
                JOIN players p ON bs.player_id = p.player_id
                WHERE bs.slip_number = ?
            ");
            $stmt->bind_param("s", $slip_number);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $slip = $result->fetch_assoc();
                
                $response = array(
                    'status' => 'success',
                    'slip' => $slip
                );
            } else {
                $response = array(
                    'status' => 'error',
                    'message' => 'Betting slip not found'
                );
            }
            
            $stmt->close();
        } else {
            $response = array(
                'status' => 'error',
                'message' => 'Missing slip number'
            );
        }
    }
}

// Helper function to process winning bets
function processWinningBets($conn, $winning_number, $winning_color) {
    // This is a simplified implementation
    // In a real app, you'd have more complex logic based on bet types
    
    // Get all active bets
    $stmt = $conn->prepare("
        SELECT b.*, p.player_id 
        FROM bets b
        JOIN players p ON b.player_id = p.player_id
        WHERE b.placed_at >= NOW() - INTERVAL 5 MINUTE
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($bet = $result->fetch_assoc()) {
        // Check if bet won based on bet type and winning number/color
        $won = checkIfBetWon($bet, $winning_number, $winning_color);
        
        if ($won) {
            // Calculate winnings
            $winnings = calculateWinnings($bet);
            
            // Update player balance
            $updateStmt = $conn->prepare("UPDATE players SET cash_balance = cash_balance + ? WHERE player_id = ?");
            $updateStmt->bind_param("di", $winnings, $bet['player_id']);
            $updateStmt->execute();
            $updateStmt->close();
        }
    }
    
    $stmt->close();
}

// Helper function to check if a bet won
function checkIfBetWon($bet, $winning_number, $winning_color) {
    $bet_type = $bet['bet_type'];
    $bet_numbers = explode(',', $bet['bet_numbers']);
    
    // Check based on bet type
    switch ($bet_type) {
        case 'straight':
            return in_array($winning_number, $bet_numbers);
        case 'color':
            return $bet_numbers[0] === $winning_color;
        case 'even':
            return $winning_number % 2 === 0 && $winning_number !== 0;
        case 'odd':
            return $winning_number % 2 === 1;
        case 'high':
            return $winning_number >= 19 && $winning_number <= 36;
        case 'low':
            return $winning_number >= 1 && $winning_number <= 18;
        case 'dozen':
            if ($bet_numbers[0] === '1st12')
                return $winning_number >= 1 && $winning_number <= 12;
            else if ($bet_numbers[0] === '2nd12')
                return $winning_number >= 13 && $winning_number <= 24;
            else if ($bet_numbers[0] === '3rd12')
                return $winning_number >= 25 && $winning_number <= 36;
            return false;
        case 'column':
            $column = intval($bet_numbers[0]);
            return $winning_number % 3 === $column;
        default:
            return false;
    }
}

// Helper function to calculate winnings
function calculateWinnings($bet) {
    $bet_type = $bet['bet_type'];
    $bet_amount = $bet['bet_amount'];
    
    // Multipliers based on bet type
    $multipliers = [
        'straight' => 35,
        'split' => 17,
        'street' => 11,
        'corner' => 8,
        'color' => 1,
        'even' => 1,
        'odd' => 1,
        'high' => 1,
        'low' => 1,
        'dozen' => 2,
        'column' => 2
    ];
    
    $multiplier = isset($multipliers[$bet_type]) ? $multipliers[$bet_type] : 0;
    return $bet_amount * (1 + $multiplier); // Original bet + winnings
}

// Helper function to generate a unique slip number
function generateSlipNumber() {
    return str_pad(mt_rand(1, 99999999), 8, '0', STR_PAD_LEFT);
}

// Output the response as JSON
echo json_encode($response);

// Close the database connection
$conn->close();
?> 