<?php
// Suppress display of errors, but still log them
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set response header to JSON
header('Content-Type: application/json');

// Include database connection
@require_once 'db_connect.php';

// Check if database connection is successful
if (!isset($conn) || $conn->connect_error) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed'
    ]);
    exit;
}

// Get draw ID from request
$draw_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($draw_id <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid draw ID'
    ]);
    exit;
}

try {
    // Check if necessary tables exist
    $tableCheckResult = $conn->query("SHOW TABLES LIKE 'detailed_draw_results'");
    $detailedDrawTableExists = $tableCheckResult && $tableCheckResult->num_rows > 0;

    $analyticsTableResult = $conn->query("SHOW TABLES LIKE 'roulette_analytics'");
    $analyticsTableExists = $analyticsTableResult && $analyticsTableResult->num_rows > 0;

    // First, get draw details if it's a completed draw
    $draw_details = null;
    $winning_number = null;
    $winning_color = null;
    $draw_time = null;

    if ($detailedDrawTableExists) {
        $stmt = $conn->prepare("
            SELECT draw_number, winning_number, winning_color
            FROM detailed_draw_results
            WHERE draw_number = ?
        ");
        $stmt->bind_param('i', $draw_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $draw_details = $result->fetch_assoc();
            $winning_number = $draw_details['winning_number'];
            $winning_color = $draw_details['winning_color'];
        }
    }

    // If we don't have detailed results, check roulette_analytics
    if (!$draw_details && $analyticsTableExists) {
        $stmt = $conn->prepare("SELECT current_draw_number, all_spins FROM roulette_analytics WHERE id = 1");
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $current_draw_number = $row['current_draw_number'];
            $all_spins = json_decode($row['all_spins'], true) ?: [];

            // If this draw is in the past, try to get the result from all_spins
            if ($draw_id < $current_draw_number) {
                $spin_index = $current_draw_number - $draw_id - 1;
                if ($spin_index >= 0 && $spin_index < count($all_spins)) {
                    $winning_number = $all_spins[$spin_index];
                    $winning_color = getNumberColor($winning_number);
                }
            }
        }
    }

    // Get all betting slips for this draw
    $stmt = $conn->prepare("
        SELECT bs.*, u.username, u.user_id
        FROM betting_slips bs
        JOIN users u ON bs.user_id = u.user_id
        WHERE bs.draw_number = ?
    ");
    $stmt->bind_param('i', $draw_id);
    $stmt->execute();
    $slips_result = $stmt->get_result();

    $slips = [];
    $bets = [];
    $total_bets = 0;
    $total_stake = 0;
    $total_potential_payout = 0;
    $total_actual_payout = 0;

    while ($slip = $slips_result->fetch_assoc()) {
        $slips[] = $slip;

        // Get bets for this slip
        $betStmt = $conn->prepare("
            SELECT b.*, sd.slip_id
            FROM slip_details sd
            JOIN bets b ON sd.bet_id = b.bet_id
            WHERE sd.slip_id = ?
        ");
        $betStmt->bind_param('i', $slip['slip_id']);
        $betStmt->execute();
        $betResult = $betStmt->get_result();

        while ($bet = $betResult->fetch_assoc()) {
            // Add user info to the bet
            $bet['user_id'] = $slip['user_id'];
            $bet['username'] = $slip['username'];
            $bet['slip_number'] = $slip['slip_number'];
            $bet['is_paid'] = $slip['is_paid'];

            // Calculate if bet is a winner (if we have a result)
            if ($winning_number !== null) {
                $bet['is_winner'] = checkIfBetIsWinner($bet, $winning_number, $winning_color);

                if ($bet['is_winner']) {
                    if ($slip['is_paid'] == 1) {
                        $bet['status'] = 'cashed';
                    } else {
                        $bet['status'] = 'pending';
                    }
                    $total_actual_payout += $bet['potential_return'];
                } else {
                    $bet['status'] = 'lost';
                }
            } else {
                $bet['status'] = 'betting';
            }

            $bets[] = $bet;
            $total_bets++;
            $total_stake += $bet['bet_amount'];
            $total_potential_payout += $bet['potential_return'];
        }
    }

    // Determine draw status
    $current_time = new DateTime();
    $draw_status = 'upcoming';
    $analytics_stmt = $conn->prepare("SELECT current_draw_number FROM roulette_analytics WHERE id = 1");
    $analytics_stmt->execute();
    $analytics_result = $analytics_stmt->get_result();
    $current_draw = 1;

    if ($analytics_result->num_rows > 0) {
        $analytics_row = $analytics_result->fetch_assoc();
        $current_draw = $analytics_row['current_draw_number'];
    }

    if ($draw_id == $current_draw) {
        $draw_status = 'current';
    } else if ($draw_id < $current_draw) {
        $draw_status = 'completed';
    }

    // Analyze bet statuses for better dashboard filtering
    $has_won_bets = false;
    $has_lost_bets = false;
    $has_pending_cashout = false;
    $has_cashed_out = false;

    foreach ($bets as $bet) {
        if (isset($bet['is_winner']) && $bet['is_winner']) {
            if ($bet['is_paid'] == 1) {
                $has_cashed_out = true;
            } else {
                $has_pending_cashout = true;
            }
            $has_won_bets = true;
        } else if (isset($bet['is_winner']) && !$bet['is_winner']) {
            $has_lost_bets = true;
        }
    }

    // Prepare response
    $response = [
        'status' => 'success',
        'draw_number' => $draw_id,
        'draw_status' => $draw_status,
        'has_bets' => count($bets) > 0,
        'has_won_bets' => $has_won_bets,
        'has_lost_bets' => $has_lost_bets,
        'has_pending_cashout' => $has_pending_cashout,
        'has_cashed_out' => $has_cashed_out,
        'total_bets' => $total_bets,
        'total_stake' => $total_stake,
        'total_potential_payout' => $total_potential_payout,
        'total_actual_payout' => $total_actual_payout,
        'bets' => $bets
    ];

    // Add draw details if available
    if ($winning_number !== null) {
        $response['winning_number'] = $winning_number;
        $response['winning_color'] = $winning_color;
    }

    echo json_encode($response);

} catch (Exception $e) {
    // Log the error to server error log but don't expose in response
    error_log('Draw details error: ' . $e->getMessage());

    echo json_encode([
        'status' => 'error',
        'message' => 'Error retrieving draw details. Please try again later.'
    ]);
}

// Function to check if a bet is a winner
function checkIfBetIsWinner($bet, $winning_number, $winning_color) {
    $bet_type = $bet['bet_type'];
    $bet_description = $bet['bet_description'];

    switch ($bet_type) {
        case 'straight':
            // Check for a straight up bet (e.g., "Straight Up on 12")
            preg_match('/(\d+)/', $bet_description, $matches);
            if (isset($matches[1])) {
                return intval($matches[1]) === $winning_number;
            }
            break;

        case 'split':
            // Check for a split bet (e.g., "Split (11,12)")
            preg_match('/\((\d+),(\d+)\)/', $bet_description, $matches);
            if (isset($matches[1]) && isset($matches[2])) {
                return $winning_number == intval($matches[1]) || $winning_number == intval($matches[2]);
            }
            break;

        case 'corner':
            // Check for a corner bet (e.g., "Corner (8,9,11,12)")
            preg_match('/\((\d+),(\d+),(\d+),(\d+)\)/', $bet_description, $matches);
            if (count($matches) >= 5) {
                return $winning_number == intval($matches[1]) ||
                       $winning_number == intval($matches[2]) ||
                       $winning_number == intval($matches[3]) ||
                       $winning_number == intval($matches[4]);
            }
            break;

        case 'street':
            // Check for a street bet (e.g., "Street (10,11,12)")
            preg_match('/\((\d+),(\d+),(\d+)\)/', $bet_description, $matches);
            if (count($matches) >= 4) {
                return $winning_number == intval($matches[1]) ||
                       $winning_number == intval($matches[2]) ||
                       $winning_number == intval($matches[3]);
            }
            break;

        case 'sixline':
            // Check for a six line bet (e.g., "Six Line (7,8,9,10,11,12)")
            preg_match('/\((\d+),(\d+),(\d+),(\d+),(\d+),(\d+)\)/', $bet_description, $matches);
            if (count($matches) >= 7) {
                return $winning_number == intval($matches[1]) ||
                       $winning_number == intval($matches[2]) ||
                       $winning_number == intval($matches[3]) ||
                       $winning_number == intval($matches[4]) ||
                       $winning_number == intval($matches[5]) ||
                       $winning_number == intval($matches[6]);
            }
            break;

        case 'dozen':
            // Check for a dozen bet (e.g., "1st Dozen (1-12)")
            if (strpos($bet_description, "1st Dozen") !== false) {
                return $winning_number >= 1 && $winning_number <= 12;
            } else if (strpos($bet_description, "2nd Dozen") !== false) {
                return $winning_number >= 13 && $winning_number <= 24;
            } else if (strpos($bet_description, "3rd Dozen") !== false) {
                return $winning_number >= 25 && $winning_number <= 36;
            }
            break;

        case 'column':
            // Check for a column bet
            if (strpos($bet_description, "1st Column") !== false) {
                return $winning_number % 3 == 1;
            } else if (strpos($bet_description, "2nd Column") !== false) {
                return $winning_number % 3 == 2;
            } else if (strpos($bet_description, "3rd Column") !== false) {
                return $winning_number % 3 == 0 && $winning_number != 0;
            }
            break;

        case 'low':
            // Check for low numbers (1-18)
            return $winning_number >= 1 && $winning_number <= 18;

        case 'high':
            // Check for high numbers (19-36)
            return $winning_number >= 19 && $winning_number <= 36;

        case 'even':
            // Check for even numbers
            return $winning_number != 0 && $winning_number % 2 == 0;

        case 'odd':
            // Check for odd numbers
            return $winning_number % 2 == 1;

        case 'red':
            // Check for red numbers
            return $winning_color == 'red';

        case 'black':
            // Check for black numbers
            return $winning_color == 'black';

        case 'even-money':
            // Handle even-money bets based on description
            if (strpos($bet_description, "Low Numbers") !== false) {
                // Low numbers (1-18)
                return $winning_number >= 1 && $winning_number <= 18;
            } else if (strpos($bet_description, "High Numbers") !== false) {
                // High numbers (19-36)
                return $winning_number >= 19 && $winning_number <= 36;
            } else if (strpos($bet_description, "Even Numbers") !== false) {
                // Even numbers
                return $winning_number != 0 && $winning_number % 2 == 0;
            } else if (strpos($bet_description, "Odd Numbers") !== false) {
                // Odd numbers
                return $winning_number % 2 == 1;
            } else if (strpos($bet_description, "Red Numbers") !== false) {
                // Red numbers
                return $winning_color == 'red';
            } else if (strpos($bet_description, "Black Numbers") !== false) {
                // Black numbers
                return $winning_color == 'black';
            }
            break;
    }

    return false;
}

// Helper function to determine color of a roulette number
function getNumberColor($number) {
    if ($number == 0) {
        return 'green';
    }

    $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
    return in_array($number, $redNumbers) ? 'red' : 'black';
}