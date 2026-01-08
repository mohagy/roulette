<?php
// Suppress display of errors, but still log them
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set response header to JSON
header('Content-Type: application/json');

// Include database connection
require_once 'db_connect.php';

// Create logs directory if it doesn't exist
$logDir = __DIR__ . '/../logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}

// Log file for auto selection
$logFile = $logDir . '/auto_selection.log';

/**
 * Log message to file
 */
function log_message($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

// Check if database connection is successful
if (!isset($conn) || $conn->connect_error) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed'
    ]);
    exit;
}

try {
    // Get the current draw number
    $stmt = $conn->prepare("SELECT current_draw_number FROM roulette_analytics WHERE id = 1");
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result->num_rows) {
        throw new Exception("Could not find current draw number");
    }

    $row = $result->fetch_assoc();
    $currentDrawNumber = $row['current_draw_number'];
    $nextDrawNumber = $currentDrawNumber + 1;

    // Check if we have a manually set winning number
    $stmt = $conn->prepare("
        SELECT winning_number
        FROM next_draw_winning_number
        WHERE draw_number = ?
    ");
    $stmt->bind_param("i", $nextDrawNumber);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $manualSetting = $result->fetch_assoc();
        $selectedNumber = $manualSetting['winning_number'];
        $selectionReason = 'manual';
        log_message("Using manually set winning number: $selectedNumber for draw #$nextDrawNumber");

        $color = getNumberColor($selectedNumber);

        echo json_encode([
            'status' => 'success',
            'selected_number' => $selectedNumber,
            'winning_color' => $color,
            'selection_reason' => $selectionReason,
            'draw_number' => $nextDrawNumber,
            'is_manual' => true
        ]);
        exit;
    }

    // Check if automatic mode is enabled
    $stmt = $conn->prepare("
        SELECT setting_value
        FROM roulette_settings
        WHERE setting_name = 'automatic_mode'
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute();
    $result = $stmt->get_result();

    $autoMode = true; // Default to automatic mode

    if ($result->num_rows > 0) {
        $setting = $result->fetch_assoc();
        $autoMode = $setting['setting_value'] == '1';
    }

    if (!$autoMode) {
        // Automatic mode is disabled but no manual number set
        // We'll still pick a number automatically but log a warning
        log_message("WARNING: Automatic mode is disabled but no manual number set for draw #$nextDrawNumber");
    }

    // Get bet distribution for the upcoming draw
    $stmt = $conn->prepare("
        SELECT b.*, sd.slip_id
        FROM bets b
        JOIN slip_details sd ON b.bet_id = sd.bet_id
        JOIN betting_slips bs ON sd.slip_id = bs.slip_id
        WHERE bs.draw_number = ?
    ");
    $stmt->bind_param("i", $nextDrawNumber);
    $stmt->execute();
    $betsResult = $stmt->get_result();

    $hasBets = $betsResult->num_rows > 0;

    // Initialize number payout arrays
    $numberPayouts = [];
    $numbersWithNoBets = [];

    for ($i = 0; $i <= 36; $i++) {
        $numberPayouts[$i] = 0;
        $numbersWithNoBets[] = $i;
    }

    // If there are bets, calculate payouts for each number
    if ($hasBets) {
        // Process each bet to determine potential payouts
        while ($bet = $betsResult->fetch_assoc()) {
            $betType = $bet['bet_type'];
            $betAmount = $bet['bet_amount'];
            $potentialReturn = $bet['potential_return'];

            // Update number payouts based on bet type
            processBet($betType, $bet['bet_description'], $betAmount, $potentialReturn, $numberPayouts, $numbersWithNoBets);
        }
    }

    $selectionReason = '';
    $selectedNumber = null;
    $lowestPayout = null;

    if (!$hasBets) {
        // No bets placed, select a random number
        $selectedNumber = rand(0, 36);
        $selectionReason = 'random';
        log_message("No bets placed - random selection: $selectedNumber for draw #$nextDrawNumber");
    } elseif (count($numbersWithNoBets) > 0) {
        // Some numbers have no bets, select one of them
        $selectedNumber = $numbersWithNoBets[array_rand($numbersWithNoBets)];
        $selectionReason = 'no_bets';
        log_message("Selected number with no bets: $selectedNumber for draw #$nextDrawNumber");
    } else {
        // All numbers have bets, select the one with lowest payout
        $lowestPayout = PHP_FLOAT_MAX;

        foreach ($numberPayouts as $number => $payout) {
            if ($payout < $lowestPayout) {
                $lowestPayout = $payout;
                $selectedNumber = $number;
            }
        }

        $selectionReason = 'lowest_payout';
        log_message("Selected number with lowest payout: $selectedNumber (payout: $lowestPayout) for draw #$nextDrawNumber");
    }

    // Only save the automatically selected number to the database if explicitly requested
    // This prevents creating phantom draws when just checking for winning numbers
    $saveToDatabase = isset($_GET['save']) && $_GET['save'] == 'true';

    if ($saveToDatabase) {
        $stmt = $conn->prepare("
            INSERT INTO next_draw_winning_number (draw_number, winning_number)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE winning_number = ?
        ");
        $stmt->bind_param("iii", $nextDrawNumber, $selectedNumber, $selectedNumber);
        $stmt->execute();
        log_message("Saved auto-selected winning number $selectedNumber for draw #$nextDrawNumber to database");
    } else {
        log_message("Auto-selected winning number $selectedNumber for draw #$nextDrawNumber (not saved to database)");
    }

    $color = getNumberColor($selectedNumber);

    // Return the selected number
    $response = [
        'status' => 'success',
        'selected_number' => $selectedNumber,
        'winning_color' => $color,
        'selection_reason' => $selectionReason,
        'draw_number' => $nextDrawNumber,
        'is_manual' => false
    ];

    if ($selectionReason === 'lowest_payout') {
        $response['lowest_payout'] = $lowestPayout;
    }

    echo json_encode($response);

} catch (Exception $e) {
    log_message("Error: " . $e->getMessage());

    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

/**
 * Process a bet to update number payouts and track numbers with no bets
 */
function processBet($betType, $betDescription, $betAmount, $potentialReturn, &$numberPayouts, &$numbersWithNoBets) {
    switch ($betType) {
        case 'straight':
            // Extract the number from the description (e.g., "Straight Up on 12")
            preg_match('/(\d+)/', $betDescription, $matches);
            if (isset($matches[1])) {
                $number = intval($matches[1]);
                if ($number >= 0 && $number <= 36) {
                    $numberPayouts[$number] += $potentialReturn;
                    // Remove from no bets array
                    $key = array_search($number, $numbersWithNoBets);
                    if ($key !== false) {
                        unset($numbersWithNoBets[$key]);
                    }
                }
            }
            break;

        case 'split':
            // Extract numbers from description (e.g., "Split (11,12)")
            preg_match('/\((\d+),(\d+)\)/', $betDescription, $matches);
            if (isset($matches[1]) && isset($matches[2])) {
                $number1 = intval($matches[1]);
                $number2 = intval($matches[2]);

                // Split the payout between the two numbers
                if ($number1 >= 0 && $number1 <= 36) {
                    $numberPayouts[$number1] += $potentialReturn;
                    $key = array_search($number1, $numbersWithNoBets);
                    if ($key !== false) {
                        unset($numbersWithNoBets[$key]);
                    }
                }

                if ($number2 >= 0 && $number2 <= 36) {
                    $numberPayouts[$number2] += $potentialReturn;
                    $key = array_search($number2, $numbersWithNoBets);
                    if ($key !== false) {
                        unset($numbersWithNoBets[$key]);
                    }
                }
            }
            break;

        case 'corner':
            // Extract numbers from description (e.g., "Corner (8,9,11,12)")
            preg_match('/\((\d+),(\d+),(\d+),(\d+)\)/', $betDescription, $matches);
            if (count($matches) >= 5) {
                for ($i = 1; $i <= 4; $i++) {
                    $number = intval($matches[$i]);
                    if ($number >= 0 && $number <= 36) {
                        $numberPayouts[$number] += $potentialReturn;
                        $key = array_search($number, $numbersWithNoBets);
                        if ($key !== false) {
                            unset($numbersWithNoBets[$key]);
                        }
                    }
                }
            }
            break;

        case 'street':
            // Extract numbers from description (e.g., "Street (10,11,12)")
            preg_match('/\((\d+),(\d+),(\d+)\)/', $betDescription, $matches);
            if (count($matches) >= 4) {
                for ($i = 1; $i <= 3; $i++) {
                    $number = intval($matches[$i]);
                    if ($number >= 0 && $number <= 36) {
                        $numberPayouts[$number] += $potentialReturn;
                        $key = array_search($number, $numbersWithNoBets);
                        if ($key !== false) {
                            unset($numbersWithNoBets[$key]);
                        }
                    }
                }
            }
            break;

        case 'line':
        case 'sixline':
            // Extract numbers from description (e.g., "Six Line (7,8,9,10,11,12)")
            preg_match('/\((\d+),(\d+),(\d+),(\d+),(\d+),(\d+)\)/', $betDescription, $matches);
            if (count($matches) >= 7) {
                for ($i = 1; $i <= 6; $i++) {
                    $number = intval($matches[$i]);
                    if ($number >= 0 && $number <= 36) {
                        $numberPayouts[$number] += $potentialReturn;
                        $key = array_search($number, $numbersWithNoBets);
                        if ($key !== false) {
                            unset($numbersWithNoBets[$key]);
                        }
                    }
                }
            }
            break;

        case 'dozen':
            if (strpos($betDescription, "1st Dozen") !== false) {
                for ($i = 1; $i <= 12; $i++) {
                    $numberPayouts[$i] += $potentialReturn;
                    $key = array_search($i, $numbersWithNoBets);
                    if ($key !== false) {
                        unset($numbersWithNoBets[$key]);
                    }
                }
            } else if (strpos($betDescription, "2nd Dozen") !== false) {
                for ($i = 13; $i <= 24; $i++) {
                    $numberPayouts[$i] += $potentialReturn;
                    $key = array_search($i, $numbersWithNoBets);
                    if ($key !== false) {
                        unset($numbersWithNoBets[$key]);
                    }
                }
            } else if (strpos($betDescription, "3rd Dozen") !== false) {
                for ($i = 25; $i <= 36; $i++) {
                    $numberPayouts[$i] += $potentialReturn;
                    $key = array_search($i, $numbersWithNoBets);
                    if ($key !== false) {
                        unset($numbersWithNoBets[$key]);
                    }
                }
            }
            break;

        case 'column':
            if (strpos($betDescription, "1st Column") !== false) {
                for ($i = 1; $i <= 34; $i += 3) {
                    $numberPayouts[$i] += $potentialReturn;
                    $key = array_search($i, $numbersWithNoBets);
                    if ($key !== false) {
                        unset($numbersWithNoBets[$key]);
                    }
                }
            } else if (strpos($betDescription, "2nd Column") !== false) {
                for ($i = 2; $i <= 35; $i += 3) {
                    $numberPayouts[$i] += $potentialReturn;
                    $key = array_search($i, $numbersWithNoBets);
                    if ($key !== false) {
                        unset($numbersWithNoBets[$key]);
                    }
                }
            } else if (strpos($betDescription, "3rd Column") !== false) {
                for ($i = 3; $i <= 36; $i += 3) {
                    $numberPayouts[$i] += $potentialReturn;
                    $key = array_search($i, $numbersWithNoBets);
                    if ($key !== false) {
                        unset($numbersWithNoBets[$key]);
                    }
                }
            }
            break;

        case 'red':
        case 'black':
            // Define red and black numbers
            $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
            $blackNumbers = [2, 4, 6, 8, 10, 11, 13, 15, 17, 20, 22, 24, 26, 28, 29, 31, 33, 35];

            $targetNumbers = ($betType === 'red') ? $redNumbers : $blackNumbers;

            foreach ($targetNumbers as $number) {
                $numberPayouts[$number] += $potentialReturn;
                $key = array_search($number, $numbersWithNoBets);
                if ($key !== false) {
                    unset($numbersWithNoBets[$key]);
                }
            }
            break;

        case 'even':
        case 'odd':
        case 'even-money':
            // Check description to determine if it's an even/odd bet
            $isEven = ($betType === 'even') ||
                      (strpos($betDescription, 'Even') !== false);

            $isOdd = ($betType === 'odd') ||
                     (strpos($betDescription, 'Odd') !== false);

            if ($isEven || $isOdd) {
                $startNumber = $isOdd ? 1 : 2;

                for ($i = $startNumber; $i <= 36; $i += 2) {
                    $numberPayouts[$i] += $potentialReturn;
                    $key = array_search($i, $numbersWithNoBets);
                    if ($key !== false) {
                        unset($numbersWithNoBets[$key]);
                    }
                }
            }
            break;

        case 'low':
        case 'high':
            // Check description to determine if it's a low/high bet
            $isLow = ($betType === 'low') ||
                     (strpos($betDescription, '1-18') !== false);

            $isHigh = ($betType === 'high') ||
                      (strpos($betDescription, '19-36') !== false);

            if ($isLow) {
                for ($i = 1; $i <= 18; $i++) {
                    $numberPayouts[$i] += $potentialReturn;
                    $key = array_search($i, $numbersWithNoBets);
                    if ($key !== false) {
                        unset($numbersWithNoBets[$key]);
                    }
                }
            } elseif ($isHigh) {
                for ($i = 19; $i <= 36; $i++) {
                    $numberPayouts[$i] += $potentialReturn;
                    $key = array_search($i, $numbersWithNoBets);
                    if ($key !== false) {
                        unset($numbersWithNoBets[$key]);
                    }
                }
            }
            break;
    }

    // Reset array keys to make it a sequential array
    $numbersWithNoBets = array_values($numbersWithNoBets);
}

/**
 * Get the color of a number
 */
function getNumberColor($number) {
    if ($number === 0) {
        return 'green';
    }

    $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];

    return in_array($number, $redNumbers) ? 'red' : 'black';
}