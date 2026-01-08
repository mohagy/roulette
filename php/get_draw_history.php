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

// Get draw history information
try {
    // Check if tables exist
    $tableCheckResult = $conn->query("SHOW TABLES LIKE 'detailed_draw_results'");
    $detailedDrawTableExists = $tableCheckResult && $tableCheckResult->num_rows > 0;

    $analyticsTableResult = $conn->query("SHOW TABLES LIKE 'roulette_analytics'");
    $analyticsTableExists = $analyticsTableResult && $analyticsTableResult->num_rows > 0;

    $stateTableResult = $conn->query("SHOW TABLES LIKE 'roulette_state'");
    $stateTableExists = $stateTableResult && $stateTableResult->num_rows > 0;

    // Get current draw number
    $current_draw_number = 1;
    $draw_history = [];

    if ($analyticsTableExists) {
        $stmt = $conn->prepare("SELECT current_draw_number, all_spins FROM roulette_analytics WHERE id = 1 LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $current_draw_number = $row['current_draw_number'];
            $all_spins = json_decode($row['all_spins'], true) ?: [];
        }
    }

    // Fetch detailed draw results where available
    $completed_draws = [];
    if ($detailedDrawTableExists) {
        $stmt = $conn->prepare("
            SELECT draw_number, winning_number, color as winning_color
            FROM detailed_draw_results
            ORDER BY draw_number DESC
            LIMIT 20
        ");
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $completed_draws[$row['draw_number']] = $row;
        }
    }

    // Get bet information for each draw
    $draw_bets = [];

    // Query for all draw numbers in betting_slips
    $stmt = $conn->prepare("
        SELECT DISTINCT draw_number
        FROM betting_slips
        ORDER BY draw_number DESC
        LIMIT 30
    ");
    $stmt->execute();
    $result = $stmt->get_result();

    $draw_numbers_with_bets = [];
    while ($row = $result->fetch_assoc()) {
        $draw_numbers_with_bets[] = $row['draw_number'];
    }

    // For each draw with bets, determine its status
    foreach ($draw_numbers_with_bets as $draw_number) {
        // Get all slips for this draw
        $stmt = $conn->prepare("
            SELECT bs.*, COUNT(sd.bet_id) as bet_count, SUM(b.bet_amount) as total_stake
            FROM betting_slips bs
            JOIN slip_details sd ON bs.slip_id = sd.slip_id
            JOIN bets b ON sd.bet_id = b.bet_id
            WHERE bs.draw_number = ?
            GROUP BY bs.slip_id
        ");
        $stmt->bind_param('i', $draw_number);
        $stmt->execute();
        $result = $stmt->get_result();

        $has_bets = false;
        $has_won_bets = false;
        $has_lost_bets = false;
        $has_pending_cashout = false;
        $has_cashed_out = false;
        $total_bets = 0;
        $total_stake = 0;

        while ($slip = $result->fetch_assoc()) {
            $has_bets = true;
            $total_bets += $slip['bet_count'];
            $total_stake += $slip['total_stake'];

            // If draw is completed, determine win/loss status
            if ($draw_number < $current_draw_number) {
                // Get winning number for this draw
                $winning_number = null;
                $winning_color = null;

                if (isset($completed_draws[$draw_number])) {
                    $winning_number = $completed_draws[$draw_number]['winning_number'];
                    $winning_color = $completed_draws[$draw_number]['winning_color'];
                } else if (isset($all_spins)) {
                    // Calculate the correct index in all_spins
                    $spin_index = $current_draw_number - $draw_number - 1;
                    if ($spin_index >= 0 && $spin_index < count($all_spins)) {
                        $winning_number = $all_spins[$spin_index];
                        $winning_color = getNumberColor($winning_number);
                    }
                }

                if ($winning_number !== null) {
                    // Get bets for this slip
                    $betStmt = $conn->prepare("
                        SELECT b.*
                        FROM slip_details sd
                        JOIN bets b ON sd.bet_id = b.bet_id
                        WHERE sd.slip_id = ?
                    ");
                    $betStmt->bind_param('i', $slip['slip_id']);
                    $betStmt->execute();
                    $betResult = $betStmt->get_result();

                    $has_winning_bet = false;

                    while ($bet = $betResult->fetch_assoc()) {
                        // Check if bet is winner
                        $is_winner = checkIfBetIsWinner($bet, $winning_number, $winning_color);
                        if ($is_winner) {
                            $has_winning_bet = true;
                            $has_won_bets = true;

                            // Check if it's cashed out or pending
                            if ($slip['is_paid'] == 1) {
                                $has_cashed_out = true;
                            } else {
                                $has_pending_cashout = true;
                            }
                            break;
                        }
                    }

                    if (!$has_winning_bet) {
                        $has_lost_bets = true;
                    }
                }
            }
        }

        $draw_bets[$draw_number] = [
            'has_bets' => $has_bets,
            'has_won_bets' => $has_won_bets,
            'has_lost_bets' => $has_lost_bets,
            'has_pending_cashout' => $has_pending_cashout,
            'has_cashed_out' => $has_cashed_out,
            'total_bets' => $total_bets,
            'total_stake' => $total_stake
        ];
    }

    // Build draw history
    // Recent draws (already completed)
    $recent_draws = [];
    for ($i = $current_draw_number - 1; $i > $current_draw_number - 11; $i--) {
        if ($i <= 0) break;

        $draw_info = [
            'draw_number' => $i,
            'type' => 'recent',
            'has_bets' => isset($draw_bets[$i]) && $draw_bets[$i]['has_bets'],
            'has_won_bets' => isset($draw_bets[$i]) && $draw_bets[$i]['has_won_bets'],
            'has_lost_bets' => isset($draw_bets[$i]) && $draw_bets[$i]['has_lost_bets'],
            'has_pending_cashout' => isset($draw_bets[$i]) && $draw_bets[$i]['has_pending_cashout'],
            'has_cashed_out' => isset($draw_bets[$i]) && $draw_bets[$i]['has_cashed_out'],
            'total_bets' => isset($draw_bets[$i]) ? $draw_bets[$i]['total_bets'] : 0,
            'total_stake' => isset($draw_bets[$i]) ? $draw_bets[$i]['total_stake'] : 0
        ];

        // Add result if available
        if (isset($completed_draws[$i])) {
            $draw_info['winning_number'] = $completed_draws[$i]['winning_number'];
            $draw_info['winning_color'] = $completed_draws[$i]['winning_color'];
        } else if (isset($all_spins)) {
            $spin_index = $current_draw_number - $i - 1;
            if ($spin_index >= 0 && $spin_index < count($all_spins)) {
                $winning_number = $all_spins[$spin_index];
                $draw_info['winning_number'] = $winning_number;
                $draw_info['winning_color'] = getNumberColor($winning_number);
            }
        }

        $recent_draws[] = $draw_info;
    }

    // Current draw
    $current_draw = [
        'draw_number' => $current_draw_number,
        'type' => 'current',
        'has_bets' => isset($draw_bets[$current_draw_number]) && $draw_bets[$current_draw_number]['has_bets'],
        'total_bets' => isset($draw_bets[$current_draw_number]) ? $draw_bets[$current_draw_number]['total_bets'] : 0,
        'total_stake' => isset($draw_bets[$current_draw_number]) ? $draw_bets[$current_draw_number]['total_stake'] : 0
    ];

    // Upcoming draws
    $upcoming_draws = [];
    for ($i = $current_draw_number + 1; $i < $current_draw_number + 11; $i++) {
        $draw_info = [
            'draw_number' => $i,
            'type' => 'upcoming',
            'has_bets' => isset($draw_bets[$i]) && $draw_bets[$i]['has_bets'],
            'total_bets' => isset($draw_bets[$i]) ? $draw_bets[$i]['total_bets'] : 0,
            'total_stake' => isset($draw_bets[$i]) ? $draw_bets[$i]['total_stake'] : 0
        ];

        $upcoming_draws[] = $draw_info;
    }

    // Combine all draws for full history
    $draw_history = array_merge($recent_draws, [$current_draw], $upcoming_draws);

    // Prepare the response
    $response = [
        'status' => 'success',
        'current_draw' => $current_draw_number,
        'draw_history' => $draw_history
    ];

    echo json_encode($response);

} catch (Exception $e) {
    // Log the error to server error log but don't expose in response
    error_log('Draw history error: ' . $e->getMessage());

    $response = [
        'status' => 'error',
        'message' => 'Failed to fetch draw history. Please try again later.'
    ];

    echo json_encode($response);
}

// Helper function to determine color of a roulette number
function getNumberColor($number) {
    if ($number == 0) {
        return 'green';
    }

    $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
    return in_array($number, $redNumbers) ? 'red' : 'black';
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