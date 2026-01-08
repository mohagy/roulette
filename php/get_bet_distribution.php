<?php
// Set error reporting settings for production environment
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set response header to JSON
header('Content-Type: application/json');

// Include database connection
require_once 'db_connect.php';

// Default response is error
$response = array(
    'status' => 'error',
    'message' => 'An error occurred while fetching bet distribution data'
);

try {
    // Get the current draw number
    $query = "SELECT current_draw_number FROM roulette_analytics WHERE id = 1";
    $result = $conn->query($query);

    if (!$result) {
        throw new Exception("Error fetching current draw number: " . $conn->error);
    }

    $row = $result->fetch_assoc();
    $currentDrawNumber = $row['current_draw_number'];

    // Determine which draw to fetch data for
    $drawNumber = $currentDrawNumber;
    $upcomingDraw = isset($_GET['upcoming']) && $_GET['upcoming'] == 1;

    if (isset($_GET['draw'])) {
        // If a specific draw is requested, use that
        $drawNumber = intval($_GET['draw']);
    } else if ($upcomingDraw) {
        // Use the current draw number instead of incrementing to next draw
        $drawNumber = $currentDrawNumber;
    }

    // Initialize the numbers array (0-36)
    $numbers = array();
    for ($i = 0; $i <= 36; $i++) {
        $numbers[$i] = array(
            'bet_count' => 0,
            'total_stake' => 0,
            'total_payout' => 0
        );
    }

    // Initialize bet types array
    $betTypes = array(
        'straight' => array('bet_count' => 0, 'total_stake' => 0, 'total_payout' => 0),
        'split' => array('bet_count' => 0, 'total_stake' => 0, 'total_payout' => 0),
        'street' => array('bet_count' => 0, 'total_stake' => 0, 'total_payout' => 0),
        'corner' => array('bet_count' => 0, 'total_stake' => 0, 'total_payout' => 0),
        'line' => array('bet_count' => 0, 'total_stake' => 0, 'total_payout' => 0),
        'dozen' => array('bet_count' => 0, 'total_stake' => 0, 'total_payout' => 0),
        'column' => array('bet_count' => 0, 'total_stake' => 0, 'total_payout' => 0),
        'red' => array('bet_count' => 0, 'total_stake' => 0, 'total_payout' => 0),
        'black' => array('bet_count' => 0, 'total_stake' => 0, 'total_payout' => 0),
        'even' => array('bet_count' => 0, 'total_stake' => 0, 'total_payout' => 0),
        'odd' => array('bet_count' => 0, 'total_stake' => 0, 'total_payout' => 0),
        'low' => array('bet_count' => 0, 'total_stake' => 0, 'total_payout' => 0),
        'high' => array('bet_count' => 0, 'total_stake' => 0, 'total_payout' => 0)
    );

    // Get all bets for this draw
    $betQuery = "
        SELECT b.*, sd.slip_id
        FROM bets b
        JOIN slip_details sd ON b.bet_id = sd.bet_id
        JOIN betting_slips bs ON sd.slip_id = bs.slip_id
        WHERE bs.draw_number = ?
    ";

    $stmt = $conn->prepare($betQuery);
    if (!$stmt) {
        throw new Exception("Error preparing statement: " . $conn->error);
    }

    $stmt->bind_param("i", $drawNumber);
    $stmt->execute();
    $betResult = $stmt->get_result();

    $totalBets = 0;
    $bets = array();

    while ($bet = $betResult->fetch_assoc()) {
        $bets[] = $bet;
        $totalBets++;

        // Process by bet type
        $betType = $bet['bet_type'];

        // Ensure the bet type exists in our tracking array
        if (!isset($betTypes[$betType])) {
            $betTypes[$betType] = array('bet_count' => 0, 'total_stake' => 0, 'total_payout' => 0);
        }

        // Update bet type stats
        $betTypes[$betType]['bet_count']++;
        $betTypes[$betType]['total_stake'] += $bet['bet_amount'];
        $betTypes[$betType]['total_payout'] += $bet['potential_return'];

        // Process straight bets (directly on a single number)
        if ($betType === 'straight') {
            // Extract the number from the description (e.g., "Straight Up on 12")
            preg_match('/(\d+)/', $bet['bet_description'], $matches);
            if (isset($matches[1])) {
                $number = intval($matches[1]);
                if ($number >= 0 && $number <= 36) {
                    $numbers[$number]['bet_count']++;
                    $numbers[$number]['total_stake'] += $bet['bet_amount'];
                    $numbers[$number]['total_payout'] += $bet['potential_return'];
                }
            }
        }
        // Process split bets (bets on two adjacent numbers)
        else if ($betType === 'split') {
            // Extract numbers from description (e.g., "Split (11,12)")
            preg_match('/\((\d+),(\d+)\)/', $bet['bet_description'], $matches);
            if (isset($matches[1]) && isset($matches[2])) {
                $number1 = intval($matches[1]);
                $number2 = intval($matches[2]);

                // Split the bet between the two numbers
                if ($number1 >= 0 && $number1 <= 36) {
                    $numbers[$number1]['bet_count']++;
                    $numbers[$number1]['total_stake'] += $bet['bet_amount'] / 2;
                    $numbers[$number1]['total_payout'] += $bet['potential_return'] / 2;
                }

                if ($number2 >= 0 && $number2 <= 36) {
                    $numbers[$number2]['bet_count']++;
                    $numbers[$number2]['total_stake'] += $bet['bet_amount'] / 2;
                    $numbers[$number2]['total_payout'] += $bet['potential_return'] / 2;
                }
            }
        }
        // Process corner bets (bets on four adjacent numbers)
        else if ($betType === 'corner') {
            // Extract numbers from description (e.g., "Corner (8,9,11,12)")
            preg_match('/\((\d+),(\d+),(\d+),(\d+)\)/', $bet['bet_description'], $matches);
            if (count($matches) >= 5) {
                $betAmount = $bet['bet_amount'] / 4;
                $payoutAmount = $bet['potential_return'] / 4;

                for ($i = 1; $i <= 4; $i++) {
                    $number = intval($matches[$i]);
                    if ($number >= 0 && $number <= 36) {
                        $numbers[$number]['bet_count']++;
                        $numbers[$number]['total_stake'] += $betAmount;
                        $numbers[$number]['total_payout'] += $payoutAmount;
                    }
                }
            }
        }
        // Process street bets (bets on three consecutive numbers)
        else if ($betType === 'street') {
            // Extract numbers from description (e.g., "Street (10,11,12)")
            preg_match('/\((\d+),(\d+),(\d+)\)/', $bet['bet_description'], $matches);
            if (count($matches) >= 4) {
                $betAmount = $bet['bet_amount'] / 3;
                $payoutAmount = $bet['potential_return'] / 3;

                for ($i = 1; $i <= 3; $i++) {
                    $number = intval($matches[$i]);
                    if ($number >= 0 && $number <= 36) {
                        $numbers[$number]['bet_count']++;
                        $numbers[$number]['total_stake'] += $betAmount;
                        $numbers[$number]['total_payout'] += $payoutAmount;
                    }
                }
            }
        }
        // Process line bets (six lines)
        else if ($betType === 'line' || $betType === 'sixline') {
            // Extract numbers from description (e.g., "Six Line (7,8,9,10,11,12)")
            preg_match('/\((\d+),(\d+),(\d+),(\d+),(\d+),(\d+)\)/', $bet['bet_description'], $matches);
            if (count($matches) >= 7) {
                $betAmount = $bet['bet_amount'] / 6;
                $payoutAmount = $bet['potential_return'] / 6;

                for ($i = 1; $i <= 6; $i++) {
                    $number = intval($matches[$i]);
                    if ($number >= 0 && $number <= 36) {
                        $numbers[$number]['bet_count']++;
                        $numbers[$number]['total_stake'] += $betAmount;
                        $numbers[$number]['total_payout'] += $payoutAmount;
                    }
                }
            }
        }
        // Process dozen bets (1-12, 13-24, 25-36)
        else if ($betType === 'dozen') {
            $betAmount = $bet['bet_amount'] / 12;
            $payoutAmount = $bet['potential_return'] / 12;

            if (strpos($bet['bet_description'], "1st Dozen") !== false) {
                for ($i = 1; $i <= 12; $i++) {
                    $numbers[$i]['bet_count']++;
                    $numbers[$i]['total_stake'] += $betAmount;
                    $numbers[$i]['total_payout'] += $payoutAmount;
                }
            } else if (strpos($bet['bet_description'], "2nd Dozen") !== false) {
                for ($i = 13; $i <= 24; $i++) {
                    $numbers[$i]['bet_count']++;
                    $numbers[$i]['total_stake'] += $betAmount;
                    $numbers[$i]['total_payout'] += $payoutAmount;
                }
            } else if (strpos($bet['bet_description'], "3rd Dozen") !== false) {
                for ($i = 25; $i <= 36; $i++) {
                    $numbers[$i]['bet_count']++;
                    $numbers[$i]['total_stake'] += $betAmount;
                    $numbers[$i]['total_payout'] += $payoutAmount;
                }
            }
        }
        // Process column bets
        else if ($betType === 'column') {
            $betAmount = $bet['bet_amount'] / 12;
            $payoutAmount = $bet['potential_return'] / 12;

            if (strpos($bet['bet_description'], "1st Column") !== false) {
                for ($i = 1; $i <= 34; $i += 3) {
                    $numbers[$i]['bet_count']++;
                    $numbers[$i]['total_stake'] += $betAmount;
                    $numbers[$i]['total_payout'] += $payoutAmount;
                }
            } else if (strpos($bet['bet_description'], "2nd Column") !== false) {
                for ($i = 2; $i <= 35; $i += 3) {
                    $numbers[$i]['bet_count']++;
                    $numbers[$i]['total_stake'] += $betAmount;
                    $numbers[$i]['total_payout'] += $payoutAmount;
                }
            } else if (strpos($bet['bet_description'], "3rd Column") !== false) {
                for ($i = 3; $i <= 36; $i += 3) {
                    $numbers[$i]['bet_count']++;
                    $numbers[$i]['total_stake'] += $betAmount;
                    $numbers[$i]['total_payout'] += $payoutAmount;
                }
            }
        }
        // Process red/black bets
        else if ($betType === 'red' || $betType === 'black') {
            $betAmount = $bet['bet_amount'] / 18;
            $payoutAmount = $bet['potential_return'] / 18;

            // Define red and black numbers
            $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
            $blackNumbers = [2, 4, 6, 8, 10, 11, 13, 15, 17, 20, 22, 24, 26, 28, 29, 31, 33, 35];

            $targetNumbers = ($betType === 'red') ? $redNumbers : $blackNumbers;

            foreach ($targetNumbers as $number) {
                $numbers[$number]['bet_count']++;
                $numbers[$number]['total_stake'] += $betAmount;
                $numbers[$number]['total_payout'] += $payoutAmount;
            }
        }
        // Process even/odd bets
        else if ($betType === 'even' || $betType === 'odd') {
            $betAmount = $bet['bet_amount'] / 18;
            $payoutAmount = $bet['potential_return'] / 18;

            $startNumber = ($betType === 'even') ? 2 : 1;

            for ($i = $startNumber; $i <= 36; $i += 2) {
                $numbers[$i]['bet_count']++;
                $numbers[$i]['total_stake'] += $betAmount;
                $numbers[$i]['total_payout'] += $payoutAmount;
            }
        }
        // Process low/high bets
        else if ($betType === 'low' || $betType === 'high') {
            $betAmount = $bet['bet_amount'] / 18;
            $payoutAmount = $bet['potential_return'] / 18;

            $startNumber = ($betType === 'low') ? 1 : 19;
            $endNumber = ($betType === 'low') ? 18 : 36;

            for ($i = $startNumber; $i <= $endNumber; $i++) {
                $numbers[$i]['bet_count']++;
                $numbers[$i]['total_stake'] += $betAmount;
                $numbers[$i]['total_payout'] += $payoutAmount;
            }
        }
        // Process even-money bets (red/black, even/odd, low/high)
        else if ($betType === 'even-money') {
            $betAmount = $bet['bet_amount'] / 18; // Even money bets cover 18 numbers
            $payoutAmount = $bet['potential_return'] / 18;

            // Determine which type of even-money bet this is based on the description
            if (strpos($bet['bet_description'], "Red") !== false) {
                // Red numbers
                $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
                foreach ($redNumbers as $number) {
                    $numbers[$number]['bet_count']++;
                    $numbers[$number]['total_stake'] += $betAmount;
                    $numbers[$number]['total_payout'] += $payoutAmount;
                }

                // Update the 'red' bet type stats instead of 'even-money'
                $betTypes['red']['bet_count']++;
                $betTypes['red']['total_stake'] += $bet['bet_amount'];
                $betTypes['red']['total_payout'] += $bet['potential_return'];

                // Subtract from even-money stats
                $betTypes['even-money']['bet_count']--;
                $betTypes['even-money']['total_stake'] -= $bet['bet_amount'];
                $betTypes['even-money']['total_payout'] -= $bet['potential_return'];
            }
            else if (strpos($bet['bet_description'], "Black") !== false) {
                // Black numbers
                $blackNumbers = [2, 4, 6, 8, 10, 11, 13, 15, 17, 20, 22, 24, 26, 28, 29, 31, 33, 35];
                foreach ($blackNumbers as $number) {
                    $numbers[$number]['bet_count']++;
                    $numbers[$number]['total_stake'] += $betAmount;
                    $numbers[$number]['total_payout'] += $payoutAmount;
                }

                // Update the 'black' bet type stats instead of 'even-money'
                $betTypes['black']['bet_count']++;
                $betTypes['black']['total_stake'] += $bet['bet_amount'];
                $betTypes['black']['total_payout'] += $bet['potential_return'];

                // Subtract from even-money stats
                $betTypes['even-money']['bet_count']--;
                $betTypes['even-money']['total_stake'] -= $bet['bet_amount'];
                $betTypes['even-money']['total_payout'] -= $bet['potential_return'];
            }
            else if (strpos($bet['bet_description'], "Even") !== false) {
                // Even numbers
                for ($i = 2; $i <= 36; $i += 2) {
                    $numbers[$i]['bet_count']++;
                    $numbers[$i]['total_stake'] += $betAmount;
                    $numbers[$i]['total_payout'] += $payoutAmount;
                }

                // Update the 'even' bet type stats instead of 'even-money'
                $betTypes['even']['bet_count']++;
                $betTypes['even']['total_stake'] += $bet['bet_amount'];
                $betTypes['even']['total_payout'] += $bet['potential_return'];

                // Subtract from even-money stats
                $betTypes['even-money']['bet_count']--;
                $betTypes['even-money']['total_stake'] -= $bet['bet_amount'];
                $betTypes['even-money']['total_payout'] -= $bet['potential_return'];
            }
            else if (strpos($bet['bet_description'], "Odd") !== false) {
                // Odd numbers
                for ($i = 1; $i <= 35; $i += 2) {
                    $numbers[$i]['bet_count']++;
                    $numbers[$i]['total_stake'] += $betAmount;
                    $numbers[$i]['total_payout'] += $payoutAmount;
                }

                // Update the 'odd' bet type stats instead of 'even-money'
                $betTypes['odd']['bet_count']++;
                $betTypes['odd']['total_stake'] += $bet['bet_amount'];
                $betTypes['odd']['total_payout'] += $bet['potential_return'];

                // Subtract from even-money stats
                $betTypes['even-money']['bet_count']--;
                $betTypes['even-money']['total_stake'] -= $bet['bet_amount'];
                $betTypes['even-money']['total_payout'] -= $bet['potential_return'];
            }
            else if (strpos($bet['bet_description'], "Low") !== false) {
                // Low numbers (1-18)
                for ($i = 1; $i <= 18; $i++) {
                    $numbers[$i]['bet_count']++;
                    $numbers[$i]['total_stake'] += $betAmount;
                    $numbers[$i]['total_payout'] += $payoutAmount;
                }

                // Update the 'low' bet type stats instead of 'even-money'
                $betTypes['low']['bet_count']++;
                $betTypes['low']['total_stake'] += $bet['bet_amount'];
                $betTypes['low']['total_payout'] += $bet['potential_return'];

                // Subtract from even-money stats
                $betTypes['even-money']['bet_count']--;
                $betTypes['even-money']['total_stake'] -= $bet['bet_amount'];
                $betTypes['even-money']['total_payout'] -= $bet['potential_return'];
            }
            else if (strpos($bet['bet_description'], "High") !== false) {
                // High numbers (19-36)
                for ($i = 19; $i <= 36; $i++) {
                    $numbers[$i]['bet_count']++;
                    $numbers[$i]['total_stake'] += $betAmount;
                    $numbers[$i]['total_payout'] += $payoutAmount;
                }

                // Update the 'high' bet type stats instead of 'even-money'
                $betTypes['high']['bet_count']++;
                $betTypes['high']['total_stake'] += $bet['bet_amount'];
                $betTypes['high']['total_payout'] += $bet['potential_return'];

                // Subtract from even-money stats
                $betTypes['even-money']['bet_count']--;
                $betTypes['even-money']['total_stake'] -= $bet['bet_amount'];
                $betTypes['even-money']['total_payout'] -= $bet['potential_return'];
            }
        }
    }

    $stmt->close();

    // Remove even-money bet type if it has zero or negative count
    if (isset($betTypes['even-money']) && $betTypes['even-money']['bet_count'] <= 0) {
        unset($betTypes['even-money']);
    }

    // Prepare the successful response
    $response = array(
        'status' => 'success',
        'draw_number' => $drawNumber,
        'total_bets' => $totalBets,
        'numbers' => $numbers,
        'bet_types' => $betTypes
    );

} catch (Exception $e) {
    // Log the error for server-side debugging
    error_log("Error in get_bet_distribution.php: " . $e->getMessage());

    // Update the error response
    $response['message'] = "Error fetching bet distribution: " . $e->getMessage();
}

// Send JSON response
echo json_encode($response);