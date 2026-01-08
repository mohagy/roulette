<?php
/**
 * Helper Functions for Roulette Application
 */

/**
 * Determine the color of a roulette number
 *
 * @param int $number The roulette number (0-36)
 * @return string The color (red, black, or green)
 */
function getNumberColor($number) {
    if ($number === 0) {
        return 'green';
    }
    
    // Define red numbers on the roulette wheel
    $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
    
    return in_array($number, $redNumbers) ? 'red' : 'black';
}

/**
 * Log a message to a file
 *
 * @param string $message The message to log
 * @param string $type The type of message (INFO, ERROR, WARNING)
 * @return void
 */
function logMessage($message, $type = 'INFO') {
    // Create logs directory if it doesn't exist
    if (!file_exists('../logs')) {
        mkdir('../logs', 0777, true);
    }
    
    $logFile = '../logs/app.log';
    $timestamp = date('Y-m-d H:i:s');
    $message = "[$timestamp] [$type] $message\n";
    
    // Append to log file
    file_put_contents($logFile, $message, FILE_APPEND);
    
    // For errors, also log to PHP error log
    if ($type === 'ERROR') {
        error_log($message);
    }
}

/**
 * Check if a number is a valid roulette number
 *
 * @param int $number The number to check
 * @return bool True if valid, false otherwise
 */
function isValidRouletteNumber($number) {
    return is_numeric($number) && $number >= 0 && $number <= 36;
}

/**
 * Check if there are any bets for a specific draw
 *
 * @param mysqli $conn Database connection
 * @param int $drawNumber The draw number to check
 * @return bool True if bets exist, false otherwise
 */
function hasBetsForDraw($conn, $drawNumber) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as bet_count 
        FROM betting_slips 
        WHERE draw_number = ?
    ");
    $stmt->bind_param('i', $drawNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row['bet_count'] > 0;
}

/**
 * Get the current draw number from the analytics table
 *
 * @param mysqli $conn Database connection
 * @return int|false The current draw number or false on failure
 */
function getCurrentDrawNumber($conn) {
    $stmt = $conn->prepare("
        SELECT current_draw_number 
        FROM roulette_analytics 
        WHERE id = 1
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false;
    }
    
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row['current_draw_number'];
}

/**
 * Find the best winning number based on bets
 *
 * @param mysqli $conn Database connection
 * @param int $drawNumber The draw number to analyze
 * @return array The best winning number and reason
 */
function findBestWinningNumber($conn, $drawNumber) {
    // Initialize array to track potential payouts for each number
    $numberPayouts = array_fill(0, 37, 0); // 0-36
    $numbersWithNoBets = range(0, 36);
    
    // Get all bets for this draw
    $stmt = $conn->prepare("
        SELECT b.bet_type, b.bet_description, b.bet_amount, b.potential_return
        FROM betting_slips bs
        JOIN slip_details sd ON bs.slip_id = sd.slip_id
        JOIN bets b ON sd.bet_id = b.bet_id
        WHERE bs.draw_number = ?
    ");
    $stmt->bind_param('i', $drawNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($bet = $result->fetch_assoc()) {
        processBet($bet['bet_type'], $bet['bet_description'], $bet['bet_amount'], $bet['potential_return'], $numberPayouts, $numbersWithNoBets);
    }
    $stmt->close();
    
    // First, check if there are numbers with no bets
    if (!empty($numbersWithNoBets)) {
        $randomIndex = array_rand($numbersWithNoBets);
        $bestNumber = $numbersWithNoBets[$randomIndex];
        return [
            'number' => $bestNumber,
            'reason' => 'No bets on this number'
        ];
    }
    
    // Find the number with the minimum payout
    $minPayout = INF;
    $bestNumber = 0;
    
    for ($i = 0; $i <= 36; $i++) {
        if ($numberPayouts[$i] < $minPayout) {
            $minPayout = $numberPayouts[$i];
            $bestNumber = $i;
        }
    }
    
    return [
        'number' => $bestNumber,
        'reason' => 'Lowest potential payout: $' . number_format($minPayout, 2)
    ];
}

/**
 * Set the winning number for the next draw
 *
 * @param mysqli $conn Database connection
 * @param int $drawNumber The draw number
 * @param int $winningNumber The winning number to set
 * @param string $source Source of the winning number (manual, automatic)
 * @param string $reason Optional reason for the selection
 * @return bool True on success, false on failure
 */
function setWinningNumber($conn, $drawNumber, $winningNumber, $source = 'manual', $reason = '') {
    // Validate inputs
    if (!isValidRouletteNumber($winningNumber)) {
        logMessage("Invalid winning number: $winningNumber", 'ERROR');
        return false;
    }
    
    // Check if a winning number already exists for this draw
    $stmt = $conn->prepare("
        SELECT id FROM next_draw_winning_number 
        WHERE draw_number = ?
    ");
    $stmt->bind_param('i', $drawNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    
    try {
        if ($exists) {
            // Update existing record
            $stmt = $conn->prepare("
                UPDATE next_draw_winning_number 
                SET winning_number = ?, 
                    source = ?,
                    reason = ?,
                    updated_at = NOW()
                WHERE draw_number = ?
            ");
            $stmt->bind_param('issi', $winningNumber, $source, $reason, $drawNumber);
        } else {
            // Insert new record
            $stmt = $conn->prepare("
                INSERT INTO next_draw_winning_number 
                (draw_number, winning_number, source, reason, created_at, updated_at) 
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->bind_param('iiss', $drawNumber, $winningNumber, $source, $reason);
        }
        
        $success = $stmt->execute();
        $stmt->close();
        
        if ($success) {
            logMessage("Winning number $winningNumber set for draw #$drawNumber ($source)", 'INFO');
        } else {
            logMessage("Failed to set winning number for draw #$drawNumber", 'ERROR');
        }
        
        return $success;
    } catch (Exception $e) {
        logMessage("Error setting winning number: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Process a bet to determine its impact on potential payouts
 *
 * @param string $betType The type of bet
 * @param string $betDescription Description of the bet
 * @param float $betAmount The amount bet
 * @param float $potentialReturn The potential return
 * @param array &$numberPayouts Array of potential payouts by number
 * @param array &$numbersWithNoBets Array of numbers with no bets
 * @return void
 */
function processBet($betType, $betDescription, $betAmount, $potentialReturn, &$numberPayouts, &$numbersWithNoBets) {
    switch ($betType) {
        case 'straight':
            // Straight up bet on a single number
            preg_match('/(\d+)/', $betDescription, $matches);
            if (isset($matches[1])) {
                $number = intval($matches[1]);
                $numberPayouts[$number] += $potentialReturn;
                // Remove from numbers with no bets
                $key = array_search($number, $numbersWithNoBets);
                if ($key !== false) {
                    unset($numbersWithNoBets[$key]);
                }
            }
            break;
            
        case 'split':
            // Split bet on two adjacent numbers
            preg_match('/\((\d+),(\d+)\)/', $betDescription, $matches);
            if (isset($matches[1]) && isset($matches[2])) {
                $numbers = [intval($matches[1]), intval($matches[2])];
                foreach ($numbers as $number) {
                    $numberPayouts[$number] += $potentialReturn;
                    // Remove from numbers with no bets
                    $key = array_search($number, $numbersWithNoBets);
                    if ($key !== false) {
                        unset($numbersWithNoBets[$key]);
                    }
                }
            }
            break;
            
        case 'street':
            // Street bet on three numbers in a row
            preg_match_all('/\d+/', $betDescription, $matches);
            if (isset($matches[0]) && count($matches[0]) >= 3) {
                $numbers = array_slice(array_map('intval', $matches[0]), 0, 3);
                foreach ($numbers as $number) {
                    $numberPayouts[$number] += $potentialReturn;
                    // Remove from numbers with no bets
                    $key = array_search($number, $numbersWithNoBets);
                    if ($key !== false) {
                        unset($numbersWithNoBets[$key]);
                    }
                }
            }
            break;
            
        case 'corner':
            // Corner bet on four numbers in a square
            preg_match_all('/\d+/', $betDescription, $matches);
            if (isset($matches[0]) && count($matches[0]) >= 4) {
                $numbers = array_slice(array_map('intval', $matches[0]), 0, 4);
                foreach ($numbers as $number) {
                    $numberPayouts[$number] += $potentialReturn;
                    // Remove from numbers with no bets
                    $key = array_search($number, $numbersWithNoBets);
                    if ($key !== false) {
                        unset($numbersWithNoBets[$key]);
                    }
                }
            }
            break;
            
        case 'sixline':
            // Six Line bet on six numbers
            preg_match_all('/\d+/', $betDescription, $matches);
            if (isset($matches[0]) && count($matches[0]) >= 6) {
                $numbers = array_slice(array_map('intval', $matches[0]), 0, 6);
                foreach ($numbers as $number) {
                    $numberPayouts[$number] += $potentialReturn;
                    // Remove from numbers with no bets
                    $key = array_search($number, $numbersWithNoBets);
                    if ($key !== false) {
                        unset($numbersWithNoBets[$key]);
                    }
                }
            }
            break;
            
        case 'dozen':
            // Dozen bets
            if (strpos($betDescription, '1st Dozen') !== false) {
                for ($i = 1; $i <= 12; $i++) {
                    $numberPayouts[$i] += $potentialReturn;
                    // Remove from numbers with no bets
                    $key = array_search($i, $numbersWithNoBets);
                    if ($key !== false) {
                        unset($numbersWithNoBets[$key]);
                    }
                }
            } else if (strpos($betDescription, '2nd Dozen') !== false) {
                for ($i = 13; $i <= 24; $i++) {
                    $numberPayouts[$i] += $potentialReturn;
                    // Remove from numbers with no bets
                    $key = array_search($i, $numbersWithNoBets);
                    if ($key !== false) {
                        unset($numbersWithNoBets[$key]);
                    }
                }
            } else if (strpos($betDescription, '3rd Dozen') !== false) {
                for ($i = 25; $i <= 36; $i++) {
                    $numberPayouts[$i] += $potentialReturn;
                    // Remove from numbers with no bets
                    $key = array_search($i, $numbersWithNoBets);
                    if ($key !== false) {
                        unset($numbersWithNoBets[$key]);
                    }
                }
            }
            break;
            
        case 'column':
            // Column bets
            if (strpos($betDescription, '1st Column') !== false) {
                for ($i = 1; $i <= 34; $i += 3) {
                    $numberPayouts[$i] += $potentialReturn;
                    // Remove from numbers with no bets
                    $key = array_search($i, $numbersWithNoBets);
                    if ($key !== false) {
                        unset($numbersWithNoBets[$key]);
                    }
                }
            } else if (strpos($betDescription, '2nd Column') !== false) {
                for ($i = 2; $i <= 35; $i += 3) {
                    $numberPayouts[$i] += $potentialReturn;
                    // Remove from numbers with no bets
                    $key = array_search($i, $numbersWithNoBets);
                    if ($key !== false) {
                        unset($numbersWithNoBets[$key]);
                    }
                }
            } else if (strpos($betDescription, '3rd Column') !== false) {
                for ($i = 3; $i <= 36; $i += 3) {
                    $numberPayouts[$i] += $potentialReturn;
                    // Remove from numbers with no bets
                    $key = array_search($i, $numbersWithNoBets);
                    if ($key !== false) {
                        unset($numbersWithNoBets[$key]);
                    }
                }
            }
            break;
            
        case 'low':
            // Low numbers (1-18)
            for ($i = 1; $i <= 18; $i++) {
                $numberPayouts[$i] += $potentialReturn;
                // Remove from numbers with no bets
                $key = array_search($i, $numbersWithNoBets);
                if ($key !== false) {
                    unset($numbersWithNoBets[$key]);
                }
            }
            break;
            
        case 'high':
            // High numbers (19-36)
            for ($i = 19; $i <= 36; $i++) {
                $numberPayouts[$i] += $potentialReturn;
                // Remove from numbers with no bets
                $key = array_search($i, $numbersWithNoBets);
                if ($key !== false) {
                    unset($numbersWithNoBets[$key]);
                }
            }
            break;
            
        case 'even':
            // Even numbers
            for ($i = 2; $i <= 36; $i += 2) {
                $numberPayouts[$i] += $potentialReturn;
                // Remove from numbers with no bets
                $key = array_search($i, $numbersWithNoBets);
                if ($key !== false) {
                    unset($numbersWithNoBets[$key]);
                }
            }
            break;
            
        case 'odd':
            // Odd numbers
            for ($i = 1; $i <= 35; $i += 2) {
                $numberPayouts[$i] += $potentialReturn;
                // Remove from numbers with no bets
                $key = array_search($i, $numbersWithNoBets);
                if ($key !== false) {
                    unset($numbersWithNoBets[$key]);
                }
            }
            break;
            
        case 'red':
            // Red numbers
            $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
            foreach ($redNumbers as $number) {
                $numberPayouts[$number] += $potentialReturn;
                // Remove from numbers with no bets
                $key = array_search($number, $numbersWithNoBets);
                if ($key !== false) {
                    unset($numbersWithNoBets[$key]);
                }
            }
            break;
            
        case 'black':
            // Black numbers
            $blackNumbers = [2, 4, 6, 8, 10, 11, 13, 15, 17, 20, 22, 24, 26, 28, 29, 31, 33, 35];
            foreach ($blackNumbers as $number) {
                $numberPayouts[$number] += $potentialReturn;
                // Remove from numbers with no bets
                $key = array_search($number, $numbersWithNoBets);
                if ($key !== false) {
                    unset($numbersWithNoBets[$key]);
                }
            }
            break;
    }
} 