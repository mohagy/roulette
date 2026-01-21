<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if not logged in
    header('Location: login.html');
    exit;
}

// Database connection parameters
$servername = "localhost";
$username = "root";  // Default XAMPP username
$password = "";      // Default XAMPP password (empty)
$dbname = "roulette";  // Using the roulette database

/**
 * Check if a bet is a winner based on the winning number and color
 *
 * @param array $bet The bet information
 * @param int $winningNumber The winning number
 * @param string $winningColor The winning color (red, black, green)
 * @return bool True if the bet is a winner, false otherwise
 */
function checkIfBetIsWinner($bet, $winningNumber, $winningColor) {
    $betType = $bet['bet_type'];
    $betDescription = $bet['bet_description'];

    switch ($betType) {
        case 'straight':
            // Check for a straight up bet (e.g., "Straight Up on 12")
            preg_match('/(\d+)/', $betDescription, $matches);
            if (isset($matches[1])) {
                return intval($matches[1]) === $winningNumber;
            }
            break;

        case 'split':
            // Check for a split bet (e.g., "Split (11,12)")
            preg_match('/\((\d+),(\d+)\)/', $betDescription, $matches);
            if (isset($matches[1]) && isset($matches[2])) {
                return $winningNumber == intval($matches[1]) || $winningNumber == intval($matches[2]);
            }
            break;

        case 'corner':
            // Check for a corner bet (e.g., "Corner (8,9,11,12)")
            preg_match('/\((\d+),(\d+),(\d+),(\d+)\)/', $betDescription, $matches);
            if (count($matches) >= 5) {
                return $winningNumber == intval($matches[1]) ||
                       $winningNumber == intval($matches[2]) ||
                       $winningNumber == intval($matches[3]) ||
                       $winningNumber == intval($matches[4]);
            }
            break;

        case 'street':
            // Check for a street bet (e.g., "Street (10,11,12)")
            preg_match('/\((\d+),(\d+),(\d+)\)/', $betDescription, $matches);
            if (count($matches) >= 4) {
                return $winningNumber == intval($matches[1]) ||
                       $winningNumber == intval($matches[2]) ||
                       $winningNumber == intval($matches[3]);
            }
            break;

        case 'dozen':
            // Check for a dozen bet (e.g., "1st Dozen (1-12)")
            if (strpos($betDescription, "1st Dozen") !== false) {
                return $winningNumber >= 1 && $winningNumber <= 12;
            } else if (strpos($betDescription, "2nd Dozen") !== false) {
                return $winningNumber >= 13 && $winningNumber <= 24;
            } else if (strpos($betDescription, "3rd Dozen") !== false) {
                return $winningNumber >= 25 && $winningNumber <= 36;
            }
            break;

        case 'column':
            // Check for a column bet
            if (strpos($betDescription, "1st Column") !== false) {
                return $winningNumber % 3 == 1;
            } else if (strpos($betDescription, "2nd Column") !== false) {
                return $winningNumber % 3 == 2;
            } else if (strpos($betDescription, "3rd Column") !== false) {
                return $winningNumber % 3 == 0 && $winningNumber != 0;
            }
            break;

        case 'even-money':
            // Handle even-money bets based on description
            if (strpos($betDescription, "Low Numbers") !== false) {
                // Low numbers (1-18)
                return $winningNumber >= 1 && $winningNumber <= 18;
            } else if (strpos($betDescription, "High Numbers") !== false) {
                // High numbers (19-36)
                return $winningNumber >= 19 && $winningNumber <= 36;
            } else if (strpos($betDescription, "Even Numbers") !== false) {
                // Even numbers
                return $winningNumber != 0 && $winningNumber % 2 == 0;
            } else if (strpos($betDescription, "Odd Numbers") !== false) {
                // Odd numbers
                return $winningNumber % 2 == 1;
            } else if (strpos($betDescription, "Red Numbers") !== false) {
                // Red numbers
                return $winningColor == 'red';
            } else if (strpos($betDescription, "Black Numbers") !== false) {
                // Black numbers
                return $winningColor == 'black';
            }
            break;

        case 'sixline':
            // Check for a six line bet (e.g., "Six Line (10,11,12,13,14,15)")
            preg_match('/\((\d+),(\d+),(\d+),(\d+),(\d+),(\d+)\)/', $betDescription, $matches);
            if (count($matches) >= 7) {
                return $winningNumber == intval($matches[1]) ||
                       $winningNumber == intval($matches[2]) ||
                       $winningNumber == intval($matches[3]) ||
                       $winningNumber == intval($matches[4]) ||
                       $winningNumber == intval($matches[5]) ||
                       $winningNumber == intval($matches[6]);
            }
            break;

        case 'line':
            // Check for a line bet (e.g., "Line (1,2,3,4,5,6)")
            preg_match_all('/\d+/', $betDescription, $matches);
            if (!empty($matches[0])) {
                foreach ($matches[0] as $number) {
                    if (intval($number) == $winningNumber) {
                        return true;
                    }
                }
            }
            break;

        default:
            // For any other bet types, check if the description contains the winning number
            // BUT: Only check as a whole word to avoid false matches (e.g., "5" matching "15")
            // Use word boundaries to ensure exact number matches
            $pattern = '/\b' . preg_quote((string)$winningNumber, '/') . '\b/';
            return preg_match($pattern, $betDescription) === 1;
    }

    return false;
}

/**
 * Get winning information for a draw from multiple sources
 *
 * @param mysqli $conn Database connection
 * @param int $drawNumber The draw number to get information for
 * @return array|null Winning information or null if not found
 */
function getWinningInformation($conn, $drawNumber) {
    // FIRST: Try analytics_history (this is what TV display uses - stores completed draw results!)
    // This is the SAME source that shows Draw #53 = 28 in the analytics display
    // The API get_analytics_history.php queries this table the same way
    $tableCheck = $conn->query("SHOW TABLES LIKE 'analytics_history'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        // Query exactly like the API does - no date restriction, just by draw_number
        // The API uses: WHERE DATE(draw_time) = ? AND draw_number >= 1 AND draw_number <= 480
        // But for historical lookups, we query by draw_number only (no date filter)
        $stmt = $conn->prepare("
            SELECT winning_number, winning_color, draw_time, draw_number
            FROM analytics_history
            WHERE draw_number = ?
            ORDER BY id DESC, draw_time DESC
            LIMIT 1
        ");
        if ($stmt) {
            $stmt->bind_param("i", $drawNumber);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $winningNumber = intval($row['winning_number']);
                
                // Return the winning number if it's valid (0-36)
                // Note: 0 is a valid winning number (green), so we check >= 0
                if ($winningNumber >= 0 && $winningNumber <= 36) {
                    $winningColor = $row['winning_color'];
                    
                    if (empty($winningColor)) {
                        $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
                        $winningColor = ($winningNumber == 0) ? 'green' : (in_array($winningNumber, $redNumbers) ? 'red' : 'black');
                    }

                    return [
                        'winning_number' => $winningNumber,
                        'winning_color' => $winningColor,
                        'draw_time' => $row['draw_time'] ?? date('Y-m-d H:i:s')
                    ];
                }
            }
        }
    }
    
    // SECOND: Try preset_schedule (this is what the API uses as fallback when analytics_history is empty!)
    // The TV display gets data from preset_schedule if analytics_history doesn't have it
    $tableCheck = $conn->query("SHOW TABLES LIKE 'preset_schedule'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        // Check all preset schedules (active and inactive) for historical data
        $stmt = $conn->prepare("
            SELECT schedule_data, start_draw_number, end_draw_number, pattern_type
            FROM preset_schedule
            WHERE start_draw_number <= ? AND end_draw_number >= ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        if ($stmt) {
            $stmt->bind_param("ii", $drawNumber, $drawNumber);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $preset = $result->fetch_assoc();
                $scheduleData = json_decode($preset['schedule_data'], true);
                if (is_array($scheduleData) && !empty($scheduleData)) {
                    $index = $drawNumber - $preset['start_draw_number'];
                    if ($index >= 0 && $index < count($scheduleData)) {
                        $scheduleItem = $scheduleData[$index];
                        $winningNumber = is_array($scheduleItem) 
                            ? intval($scheduleItem['winning_number'] ?? $scheduleItem)
                            : intval($scheduleItem);
                        
                        if ($winningNumber >= 0 && $winningNumber <= 36) {
                            $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
                            $winningColor = ($winningNumber == 0) ? 'green' : (in_array($winningNumber, $redNumbers) ? 'red' : 'black');
                            
                            // Calculate draw time based on draw number (3 minutes per draw)
                            $drawMinutes = ($drawNumber - 1) * 3;
                            $drawHour = floor($drawMinutes / 60);
                            $drawMin = $drawMinutes % 60;
                            $drawTime = date('Y-m-d') . ' ' . str_pad($drawHour, 2, '0', STR_PAD_LEFT) . ':' . str_pad($drawMin, 2, '0', STR_PAD_LEFT) . ':00';
                            
                            return [
                                'winning_number' => $winningNumber,
                                'winning_color' => $winningColor,
                                'draw_time' => $drawTime
                            ];
                        }
                    }
                }
            }
        }
    }
    
    // THIRD: Try next_draw_winning_number (only for FUTURE draws - forced numbers get deleted after draw completes!)
    // This is what admin panel shows for upcoming draws, but gets deleted when draw completes
    $stmt = $conn->prepare("
        SELECT winning_number, source, reason
        FROM next_draw_winning_number
        WHERE draw_number = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param("i", $drawNumber);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $winningNumber = intval($row['winning_number']);

            if ($winningNumber >= 0 && $winningNumber <= 36) {
                $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
                $winningColor = ($winningNumber == 0) ? 'green' : (in_array($winningNumber, $redNumbers) ? 'red' : 'black');

                // Try to get draw time from detailed_draw_results or analytics_history
                $timeStmt = $conn->prepare("
                    SELECT timestamp as draw_time
                    FROM detailed_draw_results
                    WHERE draw_number = ?
                    LIMIT 1
                ");
                $timeStmt->bind_param("i", $drawNumber);
                $timeStmt->execute();
                $timeResult = $timeStmt->get_result();
                $drawTime = date('Y-m-d H:i:s');
                if ($timeResult->num_rows > 0) {
                    $timeRow = $timeResult->fetch_assoc();
                    $drawTime = $timeRow['draw_time'];
                } else {
                    // Try analytics_history for time
                    $timeStmt2 = $conn->prepare("
                        SELECT draw_time
                        FROM analytics_history
                        WHERE draw_number = ?
                        LIMIT 1
                    ");
                    $timeStmt2->bind_param("i", $drawNumber);
                    $timeStmt2->execute();
                    $timeResult2 = $timeStmt2->get_result();
                    if ($timeResult2->num_rows > 0) {
                        $timeRow2 = $timeResult2->fetch_assoc();
                        $drawTime = $timeRow2['draw_time'];
                    }
                }

                return [
                    'winning_number' => $winningNumber,
                    'winning_color' => $winningColor,
                    'draw_time' => $drawTime
                ];
            }
        }
    }

    // FOURTH: Try to get from roulette_analytics all_spins
    $stmt = $conn->prepare("
        SELECT all_spins, number_frequency, current_draw_number
        FROM roulette_analytics
        WHERE id = 1
    ");
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $analytics = $result->fetch_assoc();

        // Check if all_spins contains our draw
        if (!empty($analytics['all_spins'])) {
            $allSpins = json_decode($analytics['all_spins'], true);

            // Look for the draw in the spins array
            foreach ($allSpins as $spin) {
                if (isset($spin['draw_number']) && $spin['draw_number'] == $drawNumber) {
                    // Define red numbers
                    $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
                    $winningNumber = $spin['number'];
                    $winningColor = in_array($winningNumber, $redNumbers) ? 'red' : 'black';
                    if ($winningNumber == 0) {
                        $winningColor = 'green';
                    }

                    return [
                        'winning_number' => $winningNumber,
                        'winning_color' => $winningColor,
                        'draw_time' => isset($spin['timestamp']) ? $spin['timestamp'] : date('Y-m-d H:i:s')
                    ];
                }
            }
        }
    }

    // If still not found, try to get from roulette_state
    $stmt = $conn->prepare("
        SELECT draw_number, next_draw_number, winning_number, next_winning_number
        FROM roulette_state
        WHERE id = 1
    ");
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $state = $result->fetch_assoc();

        // If we're looking for the current draw
        if ($drawNumber == $state['draw_number']) {
            // Define red numbers
            $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
            $winningNumber = $state['winning_number'];
            $winningColor = in_array($winningNumber, $redNumbers) ? 'red' : 'black';
            if ($winningNumber == 0) {
                $winningColor = 'green';
            }

            return [
                'winning_number' => $winningNumber,
                'winning_color' => $winningColor,
                'draw_time' => date('Y-m-d H:i:s', strtotime('-3 minutes'))
            ];
        }
    }

    // Not found in any source
    return null;
}

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get user ID from session
$userId = $_SESSION['user_id'];

// Get user details with improved query
$user = null;
$stmt = $conn->prepare("
    SELECT
        u.username,
        u.role,
        u.cash_balance,
        u.created_at,
        u.last_login,
        (SELECT COUNT(*) FROM betting_slips bs JOIN transactions t ON bs.transaction_id = t.transaction_id WHERE t.user_id = u.user_id) as total_bets,
        (SELECT COUNT(*) FROM betting_slips bs JOIN transactions t ON bs.transaction_id = t.transaction_id WHERE t.user_id = u.user_id AND bs.status = 'won') as winning_bets
    FROM users u
    WHERE u.user_id = ?
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    // Calculate win rate
    $user['win_rate'] = $user['total_bets'] > 0 ? round(($user['winning_bets'] / $user['total_bets']) * 100, 1) : 0;
}

// Get user's transactions with pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
$offset = ($page - 1) * $limit;

$transactions = [];
$stmt = $conn->prepare("
    SELECT
        transaction_id,
        amount,
        balance_after,
        transaction_type,
        reference_id,
        description,
        created_at
    FROM transactions
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT ?, ?
");
$stmt->bind_param("iii", $userId, $offset, $limit);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
}

// Get total transaction count for pagination
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM transactions WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$totalTransactions = $result->fetch_assoc()['total'];
$totalPages = ceil($totalTransactions / $limit);

// Get user's betting slips with improved query
$bettingSlips = [];
$stmt = $conn->prepare("
    SELECT
        bs.slip_id,
        bs.slip_number,
        bs.draw_number,
        bs.total_stake,
        bs.potential_payout,
        bs.created_at,
        bs.is_paid,
        bs.is_cancelled,
        bs.status,
        bs.winning_number,
        (bs.status = 'won') as is_winner,
        bs.paid_out_amount as winning_amount,
        bs.transaction_id,
        ah.winning_number AS ah_winning_number,
        ah.winning_color AS ah_winning_color,
        ah.draw_time AS ah_draw_time,
        UNIX_TIMESTAMP(ah.draw_time) as draw_timestamp,
        UNIX_TIMESTAMP(NOW()) as current_time_ts
    FROM betting_slips bs
    LEFT JOIN transactions t ON bs.transaction_id = t.transaction_id
    LEFT JOIN analytics_history ah ON bs.draw_number = ah.draw_number
    WHERE t.user_id = ?
    ORDER BY bs.created_at DESC
    LIMIT 50
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Initialize actual_winning_number - prioritize analytics_history (same as TV display)
        $row['actual_winning_number'] = null;
        $row['winning_color'] = null;
        $row['draw_time'] = null;
        
        // FIRST: Try analytics_history (this is what TV display uses - most reliable!)
        // Always try direct query first (more reliable than JOIN for historical data)
        $ahStmt = $conn->prepare("
            SELECT winning_number, winning_color, draw_time, draw_number
            FROM analytics_history
            WHERE draw_number = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        if ($ahStmt) {
            $drawNum = intval($row['draw_number']);
            $ahStmt->bind_param("i", $drawNum);
            $ahStmt->execute();
            $ahResult = $ahStmt->get_result();
            if ($ahResult->num_rows > 0) {
                $ahRow = $ahResult->fetch_assoc();
                $ahNumber = intval($ahRow['winning_number']);
                if ($ahNumber >= 0 && $ahNumber <= 36) {
                    $row['actual_winning_number'] = $ahNumber;
                    $row['winning_color'] = $ahRow['winning_color'];
                    $row['draw_time'] = $ahRow['draw_time'];
                    if ($row['draw_time']) {
                        $row['draw_timestamp'] = strtotime($row['draw_time']);
                    }
                }
            }
        }
        
        // Fallback: If direct query didn't work, try LEFT JOIN result
        if ($row['actual_winning_number'] === null && $row['ah_winning_number'] !== null && $row['ah_winning_number'] !== '') {
            $ahNumber = intval($row['ah_winning_number']);
            if ($ahNumber >= 0 && $ahNumber <= 36) {
                $row['actual_winning_number'] = $ahNumber;
                $row['winning_color'] = $row['ah_winning_color'];
                $row['draw_time'] = $row['ah_draw_time'];
                if ($row['draw_time']) {
                    $row['draw_timestamp'] = strtotime($row['draw_time']);
                }
            }
        }
        
        // SECOND: If analytics_history is empty, try preset_schedule (same as API fallback!)
        // This is what the TV display uses when analytics_history is empty
        // Check both active and inactive schedules (for historical data)
        if ($row['actual_winning_number'] === null) {
            // First try active schedules
            $presetStmt = $conn->prepare("
                SELECT schedule_data, start_draw_number, end_draw_number, pattern_type
                FROM preset_schedule
                WHERE start_draw_number <= ? AND end_draw_number >= ? AND is_active = 1
                ORDER BY created_at DESC
                LIMIT 1
            ");
            if ($presetStmt) {
                $drawNum = intval($row['draw_number']);
                $presetStmt->bind_param("ii", $drawNum, $drawNum);
                $presetStmt->execute();
                $presetResult = $presetStmt->get_result();
                
                // If no active schedule found, try inactive ones (for historical data)
                if ($presetResult->num_rows == 0) {
                    $presetStmt = $conn->prepare("
                        SELECT schedule_data, start_draw_number, end_draw_number, pattern_type
                        FROM preset_schedule
                        WHERE start_draw_number <= ? AND end_draw_number >= ?
                        ORDER BY created_at DESC
                        LIMIT 1
                    ");
                    if ($presetStmt) {
                        $presetStmt->bind_param("ii", $drawNum, $drawNum);
                        $presetStmt->execute();
                        $presetResult = $presetStmt->get_result();
                    }
                }
            } else {
                $presetResult = null;
            }
            
            if ($presetResult && $presetResult->num_rows > 0) {
                $preset = $presetResult->fetch_assoc();
                $scheduleData = json_decode($preset['schedule_data'], true);
                if (is_array($scheduleData) && !empty($scheduleData)) {
                    $index = $drawNum - $preset['start_draw_number'];
                    if ($index >= 0 && $index < count($scheduleData)) {
                        $scheduleItem = $scheduleData[$index];
                        $winningNumber = is_array($scheduleItem) 
                            ? intval($scheduleItem['winning_number'] ?? $scheduleItem)
                            : intval($scheduleItem);
                        
                        if ($winningNumber >= 0 && $winningNumber <= 36) {
                            $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
                            $winningColor = ($winningNumber == 0) ? 'green' : (in_array($winningNumber, $redNumbers) ? 'red' : 'black');
                            
                            // Calculate draw time based on draw number (3 minutes per draw, starting at 00:00)
                            $drawMinutes = ($drawNum - 1) * 3;
                            $drawHour = floor($drawMinutes / 60);
                            $drawMin = $drawMinutes % 60;
                            $drawTime = date('Y-m-d') . ' ' . str_pad($drawHour, 2, '0', STR_PAD_LEFT) . ':' . str_pad($drawMin, 2, '0', STR_PAD_LEFT) . ':00';
                            
                            $row['actual_winning_number'] = $winningNumber;
                            $row['winning_color'] = $winningColor;
                            $row['draw_time'] = $drawTime;
                            $row['draw_timestamp'] = strtotime($drawTime);
                        }
                    }
                }
            }
        }
        
        // THIRD: If still not found, try getWinningInformation function (checks other sources)
        if ($row['actual_winning_number'] === null) {
            $winningInfo = getWinningInformation($conn, $row['draw_number']);
            if ($winningInfo && isset($winningInfo['winning_number']) && $winningInfo['winning_number'] !== null && $winningInfo['winning_number'] !== '') {
                $row['actual_winning_number'] = intval($winningInfo['winning_number']);
                $row['winning_color'] = $winningInfo['winning_color'];
                $row['draw_time'] = $winningInfo['draw_time'];
                if ($row['draw_time']) {
                    $row['draw_timestamp'] = strtotime($row['draw_time']);
                }
            }
        }
        
        // FOURTH: Fallback to betting_slips.winning_number if available
        // BUT: Skip 0 from betting_slips as it might be a default/placeholder value
        // Only use it if we've exhausted all other sources
        if ($row['actual_winning_number'] === null && $row['winning_number'] !== null && $row['winning_number'] !== '') {
            $bsNumber = intval($row['winning_number']);
            // Only use if it's a valid non-zero number, or if it's 0 and we're sure it's correct
            if ($bsNumber > 0 && $bsNumber <= 36) {
                $row['actual_winning_number'] = $bsNumber;
                $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
                $row['winning_color'] = in_array($bsNumber, $redNumbers) ? 'red' : 'black';
            }
            // Don't use 0 from betting_slips - it's likely a placeholder
        }
        
        // Ensure color is set if we have a winning number
        if ($row['actual_winning_number'] !== null && empty($row['winning_color'])) {
            $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
            $row['winning_color'] = ($row['actual_winning_number'] == 0) ? 'green' : (in_array($row['actual_winning_number'], $redNumbers) ? 'red' : 'black');
        }

        // If we have winning information, process the bets to determine winners
        if ($row['actual_winning_number'] !== null) {
            $winningNumber = $row['actual_winning_number'];
            $winningColor = $row['winning_color'];

            // Get the bets for this slip
            $slipBets = [];
            $betStmt = $conn->prepare("
                SELECT
                    sd.detail_id,
                    b.bet_id,
                    b.bet_type,
                    b.bet_description,
                    b.bet_amount,
                    b.multiplier,
                    b.potential_return
                FROM slip_details sd
                JOIN bets b ON sd.bet_id = b.bet_id
                WHERE sd.slip_id = ?
            ");
            $betStmt->bind_param("i", $row['slip_id']);
            $betStmt->execute();
            $betResult = $betStmt->get_result();

            $totalWinnings = 0;
            $hasWinningBets = false;

            while ($bet = $betResult->fetch_assoc()) {
                // Check if this bet is a winner
                $isWinner = checkIfBetIsWinner($bet, $winningNumber, $winningColor);

                $bet['is_winner'] = $isWinner;
                $bet['winning_amount'] = $isWinner ? $bet['potential_return'] : 0;

                if ($isWinner) {
                    $hasWinningBets = true;
                    $totalWinnings += $bet['potential_return'];
                }

                $slipBets[] = $bet;
            }

            // Update the slip status based on the bets
            if ($row['status'] === 'pending' || $row['status'] === 'active') {
                if ($hasWinningBets) {
                    $row['is_winner'] = 1;
                    $row['status'] = 'won';
                    $row['winning_amount'] = $totalWinnings;
                } else {
                    $row['status'] = 'lost';
                    $row['winning_amount'] = 0;
                }
            }

            $row['bets'] = $slipBets;
        }

        // Calculate time remaining until draw
        $row['time_remaining'] = null;
        if ($row['draw_timestamp'] && $row['draw_timestamp'] > $row['current_time_ts']) {
            $row['time_remaining'] = $row['draw_timestamp'] - $row['current_time_ts'];
        }

        // If we don't have bets yet (because we don't have winning info), get them now
        if (!isset($row['bets'])) {
            // Get the bets for this slip
            $slipBets = [];
            $betStmt = $conn->prepare("
                SELECT
                    sd.detail_id,
                    0 as is_winner,
                    0 as winning_amount,
                    b.bet_id,
                    b.bet_type,
                    b.bet_description,
                    b.bet_amount,
                    b.multiplier,
                    b.potential_return
                FROM slip_details sd
                JOIN bets b ON sd.bet_id = b.bet_id
                WHERE sd.slip_id = ?
            ");
            $betStmt->bind_param("i", $row['slip_id']);
            $betStmt->execute();
            $betResult = $betStmt->get_result();
            while ($bet = $betResult->fetch_assoc()) {
                $slipBets[] = $bet;
            }
            $row['bets'] = $slipBets;
        }

        $bettingSlips[] = $row;
    }
}

// Calculate summary statistics
$totalBets = 0;
$totalWins = 0; // From transactions (legacy)
$totalActualWins = 0; // From betting slips (actual wins)
$totalPotentialWins = 0;
$betsByType = [];
$winsByType = [];
$monthlyData = [];

// Process transactions for summary data
foreach ($transactions as $transaction) {
    $amount = floatval($transaction['amount']);
    $month = date('M Y', strtotime($transaction['created_at']));

    if (!isset($monthlyData[$month])) {
        $monthlyData[$month] = [
            'bets' => 0,
            'wins' => 0,
            'net' => 0
        ];
    }

    if ($transaction['transaction_type'] === 'bet') {
        $totalBets += abs($amount);
        $monthlyData[$month]['bets'] += abs($amount);
        $monthlyData[$month]['net'] -= abs($amount);
    } else if ($transaction['transaction_type'] === 'win') {
        $totalWins += $amount; // Legacy transaction-based wins
        $monthlyData[$month]['wins'] += $amount;
        $monthlyData[$month]['net'] += $amount;
    }
}

// Process betting slips for detailed statistics
foreach ($bettingSlips as $slip) {
    $totalPotentialWins += $slip['potential_payout'];

    // Calculate actual wins from betting slips
    // Only count slips that actually have winnings (paid_out_amount > 0 or calculated winning_amount > 0)
    $slipWinAmount = 0;
    
    // Get the actual winning amount from paid_out_amount first (most reliable - what was actually paid)
    if (isset($slip['paid_out_amount']) && $slip['paid_out_amount'] > 0) {
        $slipWinAmount = $slip['paid_out_amount'];
    } else if (isset($slip['winning_amount']) && $slip['winning_amount'] > 0) {
        $slipWinAmount = $slip['winning_amount'];
    } else if (($slip['is_winner'] || $slip['status'] === 'won') && isset($slip['bets'])) {
        // Calculate from bets if no paid_out_amount is set
        $calculatedWin = 0;
        foreach ($slip['bets'] as $bet) {
            if (isset($bet['is_winner']) && $bet['is_winner'] && isset($bet['winning_amount'])) {
                $calculatedWin += $bet['winning_amount'];
            }
        }
        $slipWinAmount = $calculatedWin;
    }
    
    // Only add to total if there are actual winnings
    if ($slipWinAmount > 0) {
        $totalActualWins += $slipWinAmount;

        // Add to monthly data for wins
        $month = date('M Y', strtotime($slip['created_at']));
        if (!isset($monthlyData[$month])) {
            $monthlyData[$month] = [
                'bets' => 0,
                'wins' => 0,
                'net' => 0
            ];
        }
        $monthlyData[$month]['wins'] += $slipWinAmount;
        $monthlyData[$month]['net'] += $slipWinAmount;
    }

    // Collect bet type statistics
    foreach ($slip['bets'] as $bet) {
        $betType = $bet['bet_type'];

        if (!isset($betsByType[$betType])) {
            $betsByType[$betType] = [
                'count' => 0,
                'amount' => 0,
                'wins' => 0,
                'win_amount' => 0
            ];
        }

        $betsByType[$betType]['count']++;
        $betsByType[$betType]['amount'] += $bet['bet_amount'];

        if (isset($bet['is_winner']) && $bet['is_winner']) {
            $betsByType[$betType]['wins']++;
            $betsByType[$betType]['win_amount'] += isset($bet['winning_amount']) ? $bet['winning_amount'] : 0;
        }
    }
}

// Sort monthly data by date
ksort($monthlyData);

// Sort bet types by popularity
uasort($betsByType, function($a, $b) {
    return $b['count'] - $a['count'];
});

// Calculate overall statistics using actual wins from betting slips
$netProfit = $totalActualWins - abs($totalBets);
$roi = $totalBets > 0 ? ($netProfit / abs($totalBets)) * 100 : 0;

// Use actual wins for display - ONLY count betting slip wins with paid_out_amount > 0
// Don't fall back to transaction wins as they may be outdated or incorrect
$displayTotalWins = $totalActualWins; // Always use actual wins from betting slips, even if 0

// Prepare data for charts
$chartData = [
    'monthly' => $monthlyData,
    'betTypes' => $betsByType,
    'summary' => [
        'totalBets' => $totalBets,
        'totalWins' => $displayTotalWins, // Use actual wins from betting slips
        'totalActualWins' => $totalActualWins, // Separate field for debugging
        'totalTransactionWins' => $totalWins, // Legacy transaction wins
        'netProfit' => $netProfit,
        'roi' => $roi
    ]
];

// Close connection
$conn->close();

// Convert chart data to JSON for JavaScript
$chartDataJson = json_encode($chartData);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Transactions - Roulette</title>

    <!-- Modern CSS Framework -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Chart.js for data visualization -->
    <link rel="stylesheet" href="css/my_transactions_new.css">
</head>
<body>
    <div id="app-container">
        <!-- Loading Overlay -->
        <div id="loading-overlay">
            <div class="spinner-container">
                <div class="spinner-border text-light" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 loading-text">Loading your transactions...</p>
            </div>
        </div>

        <!-- Header -->
        <header class="app-header">
            <div class="container">
                <nav class="navbar navbar-expand-lg navbar-dark">
                    <div class="container-fluid">
                        <a class="navbar-brand" href="#">
                            <i class="fas fa-dice"></i> Roulette
                        </a>
                        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                            <span class="navbar-toggler-icon"></span>
                        </button>
                        <div class="collapse navbar-collapse" id="navbarNav">
                            <ul class="navbar-nav">
                                <li class="nav-item">
                                    <a class="nav-link" href="index.html">
                                        <i class="fas fa-home"></i> Game
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link active" href="my_transactions_new.php">
                                        <i class="fas fa-history"></i> My Transactions
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="redeem_voucher.php">
                                        <i class="fas fa-ticket-alt"></i> Redeem Voucher
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="commission.php">
                                        <i class="fas fa-percentage"></i> Commission
                                    </a>
                                </li>
                            </ul>
                            <ul class="navbar-nav ms-auto">
                                <li class="nav-item">
                                    <a class="nav-link" href="logout.php">
                                        <i class="fas fa-sign-out-alt"></i> Logout
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </nav>
            </div>
        </header>

        <!-- Main Content -->
        <main class="main-content">
            <div class="container">
                <h1 class="page-title fade-in">My Transactions</h1>

                <!-- User Balance Card -->
                <?php if ($user): ?>
                <div class="balance-card fade-in">
                    <div class="row">
                        <div class="col-md-6 user-info">
                            <h5>Welcome, <?php echo htmlspecialchars($user['username']); ?></h5>
                            <p class="mb-0">Role: <?php echo htmlspecialchars($user['role']); ?></p>
                            <p class="mb-0">Win Rate: <span class="badge bg-light text-dark"><?php echo $user['win_rate']; ?>%</span></p>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <div class="balance-label">
                                Current Balance
                                <button class="btn btn-sm btn-link p-0 ms-2" id="refresh-balance" title="Refresh Balance" style="color: inherit; text-decoration: none;">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                            <div class="balance-amount" id="balance-amount" style="cursor: pointer;" title="Click to refresh balance">$<?php echo number_format($user['cash_balance'], 2); ?></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Stats Summary -->
                <div class="row mb-4">
                    <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                        <div class="stats-card primary fade-in delay-1">
                            <i class="fas fa-coins stats-icon"></i>
                            <div class="stats-label">Total Bets</div>
                            <div class="stats-value" id="total-bets">$<?php echo number_format(abs($totalBets), 2); ?></div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                        <div class="stats-card success fade-in delay-2">
                            <i class="fas fa-trophy stats-icon"></i>
                            <div class="stats-label">Total Wins</div>
                            <div class="stats-value" id="total-wins">$<?php echo number_format($displayTotalWins, 2); ?></div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                        <div class="stats-card warning fade-in delay-3">
                            <i class="fas fa-chart-line stats-icon"></i>
                            <div class="stats-label">Net Profit/Loss</div>
                            <div class="stats-value" id="net-profit">$<?php echo number_format($netProfit, 2); ?></div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stats-card info fade-in delay-4">
                            <i class="fas fa-percent stats-icon"></i>
                            <div class="stats-label">ROI</div>
                            <div class="stats-value" id="roi"><?php echo number_format($roi, 1); ?>%</div>
                        </div>
                    </div>
                </div>

                <!-- Real-time Status -->
                <div class="alert alert-info d-flex align-items-center fade-in" role="alert" id="real-time-status">
                    <i class="fas fa-sync-alt fa-spin me-2"></i>
                    <div>
                        Real-time updates active. Last updated: <span id="last-updated">Just now</span>
                    </div>
                </div>

                <!-- Tabs for different sections -->
                <ul class="nav nav-tabs mb-4 fade-in" id="myTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="betting-slips-tab" data-bs-toggle="tab" data-bs-target="#betting-slips" type="button" role="tab">
                            <i class="fas fa-receipt"></i> Betting Slips
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="transactions-tab" data-bs-toggle="tab" data-bs-target="#transactions" type="button" role="tab">
                            <i class="fas fa-exchange-alt"></i> Transactions
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="analytics-tab" data-bs-toggle="tab" data-bs-target="#analytics" type="button" role="tab">
                            <i class="fas fa-chart-bar"></i> Analytics
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content fade-in" id="myTabContent">
                    <!-- Betting Slips Tab -->
                    <div class="tab-pane fade show active" id="betting-slips" role="tabpanel">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <div>
                                    <i class="fas fa-receipt"></i> Your Betting Slips
                                </div>
                                <div>
                                    <button class="btn btn-sm btn-light" id="refresh-slips">
                                        <i class="fas fa-sync-alt"></i> Refresh
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($bettingSlips)): ?>
                                <div class="alert alert-info m-3">No betting slips found.</div>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped" id="betting-slips-table">
                                        <thead>
                                            <tr>
                                                <th>Slip #</th>
                                                <th>Date</th>
                                                <th>Draw #</th>
                                                <th>Draw Time</th>
                                                <th>Total Stake</th>
                                                <th>Potential Win</th>
                                                <th>Winning Number</th>
                                                <th>Result</th>
                                                <th>Actual Win</th>
                                                <th>Details</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($bettingSlips as $slip): ?>
                                            <tr data-slip-number="<?php echo $slip['slip_number']; ?>" data-slip-id="<?php echo $slip['slip_id']; ?>">
                                                <td><?php echo $slip['slip_number']; ?></td>
                                                <td><?php echo date('Y-m-d H:i', strtotime($slip['created_at'])); ?></td>
                                                <td><?php echo $slip['draw_number']; ?></td>
                                                <td>
                                                    <?php if ($slip['draw_time']): ?>
                                                        <?php if ($slip['time_remaining'] > 0): ?>
                                                            <span class="countdown" data-time="<?php echo $slip['time_remaining']; ?>">
                                                                <?php echo gmdate("i:s", $slip['time_remaining']); ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <?php echo date('Y-m-d H:i', strtotime($slip['draw_time'])); ?>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Pending</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>$<?php echo number_format($slip['total_stake'], 2); ?></td>
                                                <td>$<?php echo number_format($slip['potential_payout'], 2); ?></td>
                                                <td>
                                                    <?php if ($slip['actual_winning_number'] !== null): ?>
                                                        <span class="badge bg-<?php echo $slip['winning_color'] === 'red' ? 'danger' : ($slip['winning_color'] === 'black' ? 'dark' : 'success'); ?>">
                                                            <?php echo $slip['actual_winning_number']; ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Pending</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="result-cell">
                                                    <?php if ($slip['is_paid'] == 1): ?>
                                                        <span class="badge bg-info">PROCESSED</span>
                                                    <?php elseif ($slip['actual_winning_number'] === null): ?>
                                                        <span class="badge badge-pending">Pending</span>
                                                    <?php elseif ($slip['is_winner'] || $slip['status'] === 'won'): ?>
                                                        <span class="badge badge-win">WIN</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-loss">LOSS</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($slip['is_winner'] || $slip['status'] === 'won'): ?>
                                                        <span class="text-success fw-bold">$<?php echo number_format($slip['winning_amount'], 2); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">$0.00</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#slip-<?php echo $slip['slip_id']; ?>">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                </td>
                                            </tr>
                                            <tr class="collapse" id="slip-<?php echo $slip['slip_id']; ?>">
                                                <td colspan="10">
                                                    <div class="card card-body bg-light m-2">
                                                        <h6 class="mb-3">Bets for Slip #<?php echo $slip['slip_number']; ?> (Draw #<?php echo $slip['draw_number']; ?>)</h6>
                                                        <div class="table-responsive">
                                                            <table class="table table-sm">
                                                                <thead>
                                                                    <tr>
                                                                        <th>Type</th>
                                                                        <th>Description</th>
                                                                        <th>Amount</th>
                                                                        <th>Multiplier</th>
                                                                        <th>Potential Return</th>
                                                                        <th>Result</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php foreach ($slip['bets'] as $bet):
                                                                        // Determine if this bet is a winner
                                                                        $isWinningBet = isset($bet['is_winner']) ? $bet['is_winner'] : false;
                                                                    ?>
                                                                    <tr>
                                                                        <td><?php echo ucfirst($bet['bet_type']); ?></td>
                                                                        <td><?php echo $bet['bet_description']; ?></td>
                                                                        <td>$<?php echo number_format($bet['bet_amount'], 2); ?></td>
                                                                        <td><?php echo $bet['multiplier']; ?>:1</td>
                                                                        <td>$<?php echo number_format($bet['potential_return'], 2); ?></td>
                                                                        <td>
                                                                            <?php if ($slip['actual_winning_number'] === null): ?>
                                                                                <span class="badge badge-pending">Pending</span>
                                                                            <?php elseif ($isWinningBet): ?>
                                                                                <span class="badge badge-win">WIN</span>
                                                                            <?php else: ?>
                                                                                <span class="badge badge-loss">LOSS</span>
                                                                            <?php endif; ?>
                                                                        </td>
                                                                    </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Transactions Tab -->
                    <div class="tab-pane fade" id="transactions" role="tabpanel">
                        <!-- Transactions content will be added in Part 3 -->
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <div>
                                    <i class="fas fa-exchange-alt"></i> Transaction History
                                </div>
                                <div>
                                    <button class="btn btn-sm btn-light" id="refresh-transactions">
                                        <i class="fas fa-sync-alt"></i> Refresh
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($transactions)): ?>
                                <div class="alert alert-info m-3">No transactions found.</div>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped" id="transactions-table">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Type</th>
                                                <th>Amount</th>
                                                <th>Balance After</th>
                                                <th>Description</th>
                                                <th>Date/Time</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($transactions as $transaction): ?>
                                            <tr>
                                                <td><?php echo $transaction['transaction_id']; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php
                                                        echo $transaction['transaction_type'] === 'bet' ? 'danger' :
                                                            ($transaction['transaction_type'] === 'win' ? 'success' :
                                                            ($transaction['transaction_type'] === 'refund' ? 'info' :
                                                            ($transaction['transaction_type'] === 'voucher' ? 'primary' : 'warning')));
                                                    ?>">
                                                        <?php echo ucfirst($transaction['transaction_type']); ?>
                                                    </span>
                                                </td>
                                                <td class="<?php echo $transaction['amount'] >= 0 ? 'text-success' : 'text-danger'; ?> fw-bold">
                                                    $<?php echo number_format($transaction['amount'], 2); ?>
                                                </td>
                                                <td>$<?php echo number_format($transaction['balance_after'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                                <td><?php echo $transaction['created_at']; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Analytics Tab -->
                    <div class="tab-pane fade" id="analytics" role="tabpanel">
                        <!-- Analytics content will be added in Part 4 -->
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-header bg-primary text-white">
                                        <i class="fas fa-chart-line"></i> Monthly Performance
                                    </div>
                                    <div class="card-body">
                                        <canvas id="monthlyChart" height="300"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-header bg-primary text-white">
                                        <i class="fas fa-chart-pie"></i> Bet Type Distribution
                                    </div>
                                    <div class="card-body">
                                        <canvas id="betTypeChart" height="300"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header bg-primary text-white">
                                        <i class="fas fa-trophy"></i> Performance Metrics
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-3 col-sm-6 mb-3">
                                                <div class="card bg-light">
                                                    <div class="card-body text-center">
                                                        <h6 class="card-title">Win Rate</h6>
                                                        <h3 class="mb-0"><?php echo number_format($user['win_rate'], 1); ?>%</h3>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-3 col-sm-6 mb-3">
                                                <div class="card bg-light">
                                                    <div class="card-body text-center">
                                                        <h6 class="card-title">Average Bet</h6>
                                                        <h3 class="mb-0">$<?php echo $user['total_bets'] > 0 ? number_format($totalBets / $user['total_bets'], 2) : '0.00'; ?></h3>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-3 col-sm-6 mb-3">
                                                <div class="card bg-light">
                                                    <div class="card-body text-center">
                                                        <h6 class="card-title">Average Win</h6>
                                                        <h3 class="mb-0">$<?php echo $user['winning_bets'] > 0 ? number_format($displayTotalWins / $user['winning_bets'], 2) : '0.00'; ?></h3>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-3 col-sm-6 mb-3">
                                                <div class="card bg-light">
                                                    <div class="card-body text-center">
                                                        <h6 class="card-title">Best Bet Type</h6>
                                                        <h3 class="mb-0">
                                                            <?php
                                                                $bestType = 'N/A';
                                                                $bestRatio = 0;
                                                                foreach ($betsByType as $type => $data) {
                                                                    if ($data['count'] > 5) { // Minimum sample size
                                                                        $ratio = $data['win_amount'] / $data['amount'];
                                                                        if ($ratio > $bestRatio) {
                                                                            $bestRatio = $ratio;
                                                                            $bestType = ucfirst($type);
                                                                        }
                                                                    }
                                                                }
                                                                echo $bestType;
                                                            ?>
                                                        </h3>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Notifications Container -->
        <div class="toast-container position-fixed bottom-0 end-0 p-3">
            <div class="toast align-items-center text-white bg-primary border-0" role="alert" aria-live="assertive" aria-atomic="true" id="update-toast">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-info-circle me-2"></i>
                        <span id="toast-message">Your data has been updated.</span>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Custom JavaScript -->
    <script>
        // Store the chart data from PHP
        const chartData = <?php echo $chartDataJson; ?>;
    </script>
    <script src="js/my_transactions_new.js"></script>
</body>
</html>
