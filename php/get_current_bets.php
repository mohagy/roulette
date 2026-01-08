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

// Get current draw number from analytics table (if exists) or latest betting slip
try {
    // Check if detailed_draw_results table exists
    $tableCheckResult = $conn->query("SHOW TABLES LIKE 'detailed_draw_results'");
    $detailedDrawTableExists = $tableCheckResult && $tableCheckResult->num_rows > 0;

    // Check if roulette_analytics table exists
    $analyticsTableResult = $conn->query("SHOW TABLES LIKE 'roulette_analytics'");
    $analyticsTableExists = $analyticsTableResult && $analyticsTableResult->num_rows > 0;

    // Check if roulette_state table exists
    $stateTableResult = $conn->query("SHOW TABLES LIKE 'roulette_state'");
    $stateTableExists = $stateTableResult && $stateTableResult->num_rows > 0;

    // Get current draw number
    $draw_number = null;
    $draw_history = [];
    $next_draws = [];

    if ($analyticsTableExists) {
        $stmt = $conn->prepare("SELECT current_draw_number, all_spins, number_frequency FROM roulette_analytics WHERE id = 1 LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $draw_number = $row['current_draw_number'];

            // Get recent draw history
            $all_spins = json_decode($row['all_spins'], true);
            $number_frequency = json_decode($row['number_frequency'], true);

            // Get the 10 most recent draws (or fewer if there aren't 10)
            $recent_spins = array_slice($all_spins, 0, min(10, count($all_spins)));

            // Format the draw history data
            for ($i = 0; $i < count($recent_spins); $i++) {
                $number = $recent_spins[$i];
                $color = getNumberColor($number);
                $draw_history[] = [
                    'number' => $number,
                    'color' => $color,
                    'draw_number' => $draw_number - $i,
                ];
            }
        }
    }

    if (!$draw_number && $detailedDrawTableExists) {
        $stmt = $conn->prepare("SELECT MAX(draw_number) as latest_draw FROM detailed_draw_results");
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $draw_number = $row['latest_draw'];
        }
    }

    if (!$draw_number) {
        $stmt = $conn->prepare("SELECT MAX(draw_number) as latest_draw FROM betting_slips");
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $draw_number = $row['latest_draw'] ?? 1;
        } else {
            $draw_number = 1; // Default if no data
        }
    }

    // Get state information for next draw if available
    if ($stateTableExists) {
        $stmt = $conn->prepare("SELECT last_draw, next_draw, countdown_time FROM roulette_state WHERE id = 1 LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();

            // Extract numbers from the draw strings (e.g., "#150" -> 150)
            $lastDrawNumber = intval(str_replace('#', '', $row['last_draw']));
            $nextDrawNumber = intval(str_replace('#', '', $row['next_draw']));

            // Generate upcoming 10 draws
            for ($i = 0; $i < 10; $i++) {
                $next_draws[] = [
                    'draw_number' => $nextDrawNumber + $i,
                    'countdown' => $i === 0 ? $row['countdown_time'] : null
                ];
            }
        }
    }

    // Get all bets for the current draw
    $bets = [];

    // Correct query to join tables properly using slip_details junction table
    $query = "
        SELECT b.*, bs.user_id
        FROM bets b
        JOIN slip_details sd ON b.bet_id = sd.bet_id
        JOIN betting_slips bs ON sd.slip_id = bs.slip_id
        WHERE bs.draw_number = ?
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $draw_number);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        // For straight bets, extract the number from the description
        if ($row['bet_type'] === 'straight') {
            preg_match('/(\d+)/', $row['bet_description'], $matches);
            if (isset($matches[1])) {
                $row['number'] = intval($matches[1]);
            }
        }
        $bets[] = $row;
    }

    // Check if there's bet information stored for this draw
    if (!empty($slipStatuses)) {
        // Some bets already exist for this draw
        $has_bets = true;

        // For historical draws, also determine won/lost/cashout status
        if ($draw_number < $current_draw) {
            $has_won_bets = false;
            $has_lost_bets = false;
            $has_pending_cashout = false;
            $has_cashed_out = false;

            foreach ($slipStatuses as $slip) {
                if ($slip['won'] === true) {
                    $has_won_bets = true;

                    if ($slip['is_paid'] == 1) {
                        $has_cashed_out = true;
                    } else {
                        $has_pending_cashout = true;
                    }
                } elseif ($slip['won'] === false) {
                    $has_lost_bets = true;
                }
            }

            $drawInfo['won_bets'] = $has_won_bets;
            $drawInfo['lost_bets'] = $has_lost_bets;
            $drawInfo['pending_cashout'] = $has_pending_cashout;
            $drawInfo['cashed_out'] = $has_cashed_out;
        }
    }

    $drawInfo['has_bets'] = $has_bets;
    $drawHistory[] = $drawInfo;

    // Prepare the response
    $response = [
        'status' => 'success',
        'draw_number' => $draw_number,
        'bets' => $bets,
        'draw_history' => $draw_history,
        'next_draws' => $next_draws
    ];

    echo json_encode($response);

} catch (Exception $e) {
    $response = [
        'status' => 'error',
        'message' => 'Failed to fetch betting data: ' . $e->getMessage()
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