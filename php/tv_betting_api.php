<?php
/**
 * TV Betting API
 * Handles saving betting data from the TV display
 */

// Set the response header to JSON
header('Content-Type: application/json');

// Include the database configuration
require_once 'config.php';
date_default_timezone_set('UTC');

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Log file path
$logFile = '../logs/tv_betting_api.log';

/**
 * Log a message to the log file
 */
function logMessage($message, $level = 'INFO') {
    global $logFile;

    // Create logs directory if it doesn't exist
    $logsDir = dirname($logFile);
    if (!is_dir($logsDir)) {
        mkdir($logsDir, 0777, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;

    // Append to log file
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

/**
 * Return a JSON response
 */
function sendResponse($status, $message, $data = null) {
    $response = [
        'status' => $status,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response);
    exit;
}

/**
 * Handle saving betting data
 */
function handleSaveBettingData($db) {
    // Get the raw POST data
    $rawData = file_get_contents('php://input');
    $data = json_decode($rawData, true);

    if (!$data) {
        logMessage("Invalid JSON data received: $rawData", 'ERROR');
        sendResponse('error', 'Invalid JSON data received');
    }

    // Validate required fields
    if (!isset($data['bets']) || !is_array($data['bets']) || empty($data['bets'])) {
        logMessage("Missing or invalid bets data", 'ERROR');
        sendResponse('error', 'Missing or invalid bets data');
    }

    if (!isset($data['draw_number']) || !is_numeric($data['draw_number'])) {
        logMessage("Missing or invalid draw number", 'ERROR');
        sendResponse('error', 'Missing or invalid draw number');
    }

    if (!isset($data['slip_number']) || empty($data['slip_number'])) {
        // Generate a slip number if not provided
        $data['slip_number'] = generateSlipNumber();
    }

    // Start a transaction
    $db->begin_transaction();

    try {
        // Calculate totals
        $totalStake = 0;
        $totalPotentialPayout = 0;

        foreach ($data['bets'] as $bet) {
            $totalStake += isset($bet['stake']) ? floatval($bet['stake']) : 0;
            $totalPotentialPayout += isset($bet['potential_payout']) ? floatval($bet['potential_payout']) : 0;
        }

        // Ensure the player exists
        $playerId = ensurePlayerExists($db, 'TV Display');

        // Insert betting slip
        $stmt = $db->prepare("INSERT INTO betting_slips (slip_number, player_id, draw_number, timestamp,
                             total_stake, total_potential_payout, status)
                             VALUES (?, ?, ?, NOW(), ?, ?, 'active')");

        $stmt->bind_param('siiddd',
            $data['slip_number'],
            $playerId,
            $data['draw_number'],
            $totalStake,
            $totalPotentialPayout
        );

        if (!$stmt->execute()) {
            throw new Exception("Failed to insert betting slip: " . $stmt->error);
        }

        $slipId = $db->insert_id;
        logMessage("Created betting slip #$slipId with slip number {$data['slip_number']}", 'INFO');

        // Insert each bet
        foreach ($data['bets'] as $bet) {
            // Validate bet data
            if (!isset($bet['bet_type']) || !isset($bet['stake']) || !isset($bet['odds'])) {
                logMessage("Invalid bet data: " . json_encode($bet), 'WARNING');
                continue;
            }

            $betType = $bet['bet_type'];
            $betValue = isset($bet['bet_value']) ? $bet['bet_value'] : '';
            $stake = floatval($bet['stake']);
            $odds = floatval($bet['odds']);
            $potentialPayout = isset($bet['potential_payout']) ? floatval($bet['potential_payout']) : ($stake * $odds);

            // Insert bet
            $stmt = $db->prepare("INSERT INTO bets (bet_type, bet_value, stake, odds, potential_payout, created_at)
                                 VALUES (?, ?, ?, ?, ?, NOW())");

            $stmt->bind_param('ssddd',
                $betType,
                $betValue,
                $stake,
                $odds,
                $potentialPayout
            );

            if (!$stmt->execute()) {
                throw new Exception("Failed to insert bet: " . $stmt->error);
            }

            $betId = $db->insert_id;
            logMessage("Created bet #$betId of type $betType with stake $stake", 'INFO');

            // Insert slip_details (junction table)
            $stmt = $db->prepare("INSERT INTO slip_details (slip_id, bet_id) VALUES (?, ?)");
            $stmt->bind_param('ii', $slipId, $betId);

            if (!$stmt->execute()) {
                throw new Exception("Failed to insert slip details: " . $stmt->error);
            }
        }

        // Update analytics
        updateAnalytics($db, $data['draw_number'], $totalStake);

        // Commit the transaction
        $db->commit();

        // Send success response
        sendResponse('success', 'Betting data saved successfully', [
            'slip_id' => $slipId,
            'slip_number' => $data['slip_number'],
            'bets_saved' => count($data['bets']),
            'total_stake' => $totalStake,
            'total_potential_payout' => $totalPotentialPayout
        ]);

    } catch (Exception $e) {
        // Roll back the transaction
        $db->rollback();

        logMessage("Error saving betting data: " . $e->getMessage(), 'ERROR');
        sendResponse('error', 'Error saving betting data: ' . $e->getMessage());
    }
}

/**
 * Handle saving state data
 */
function handleSaveState($db) {
    // Get the raw POST data
    $rawData = file_get_contents('php://input');
    $data = json_decode($rawData, true);

    if (!$data) {
        logMessage("Invalid JSON state data received: $rawData", 'ERROR');
        sendResponse('error', 'Invalid JSON state data received');
    }

    // Validate required fields
    if (!isset($data['roll_history']) || !is_array($data['roll_history'])) {
        logMessage("Missing roll history in state data", 'ERROR');
        sendResponse('error', 'Missing roll history in state data');
    }

    // Get the current draw number
    $drawNumber = isset($data['next_draw']) ?
        (int) preg_replace('/[^0-9]/', '', $data['next_draw']) :
        getCurrentDrawNumber($db);

    // Start a transaction
    $db->begin_transaction();

    try {
        // Get the most recent state record
        $getLatestQuery = "SELECT * FROM roulette_state ORDER BY id DESC LIMIT 1";
        $latestResult = $db->query($getLatestQuery);
        $latestState = $latestResult->fetch_assoc();

        // Insert a new record with updated values
        $stmt = $db->prepare("INSERT INTO roulette_state
                             (roll_history, roll_colors, last_draw, next_draw, countdown_time, end_time,
                              current_draw_number, winning_number, next_draw_winning_number, manual_mode,
                              last_updated, current_countdown, last_draw_number, next_draw_number)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)");

        $countdown = isset($data['countdown_time']) ? $data['countdown_time'] : 120;
        $lastDrawNumber = $drawNumber - 1;

        $stmt->bind_param('ssssiiiiiiiii',
            $latestState['roll_history'],
            $latestState['roll_colors'],
            $latestState['last_draw'],
            $latestState['next_draw'],
            $latestState['countdown_time'],
            $latestState['end_time'],
            $latestState['current_draw_number'],
            $latestState['winning_number'],
            $latestState['next_draw_winning_number'],
            $latestState['manual_mode'],
            $countdown,
            $lastDrawNumber,
            $drawNumber
        );

        if (!$stmt->execute()) {
            throw new Exception("Failed to insert new roulette state record: " . $stmt->error);
        }

        // Update game_history with roll history if available
        if (!empty($data['roll_history']) && !empty($data['roll_colors'])) {
            // Check if we need to insert or update - get the latest draw in game_history
            $stmt = $db->prepare("SELECT * FROM game_history WHERE draw_number = ? LIMIT 1");
            $stmt->bind_param('i', $lastDrawNumber);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                // Update existing record
                $lastNumber = end($data['roll_history']);
                $lastColor = end($data['roll_colors']);

                $stmt = $db->prepare("UPDATE game_history SET
                                     number = ?,
                                     color = ?,
                                     timestamp = NOW()
                                     WHERE draw_number = ?");

                $stmt->bind_param('isi',
                    $lastNumber,
                    $lastColor,
                    $lastDrawNumber
                );

                if (!$stmt->execute()) {
                    throw new Exception("Failed to update game history: " . $stmt->error);
                }
            } else {
                // Insert new record
                $lastNumber = end($data['roll_history']);
                $lastColor = end($data['roll_colors']);

                $stmt = $db->prepare("INSERT INTO game_history (draw_number, number, color, timestamp)
                                     VALUES (?, ?, ?, NOW())");

                $stmt->bind_param('iis',
                    $lastDrawNumber,
                    $lastNumber,
                    $lastColor
                );

                if (!$stmt->execute()) {
                    throw new Exception("Failed to insert game history: " . $stmt->error);
                }
            }

            // Also update detailed_draw_results (if the table exists)
            $tableCheckStmt = $db->prepare("SHOW TABLES LIKE 'detailed_draw_results'");
            $tableCheckStmt->execute();
            if ($tableCheckStmt->get_result()->num_rows > 0) {
                // The detailed_draw_results table exists, so update it
                foreach ($data['roll_history'] as $index => $number) {
                    $color = isset($data['roll_colors'][$index]) ? $data['roll_colors'][$index] : 'black';
                    $position = $index + 1;

                    // Check if the record already exists
                    $stmt = $db->prepare("SELECT * FROM detailed_draw_results
                                         WHERE draw_number = ? AND position = ? LIMIT 1");
                    $stmt->bind_param('ii', $lastDrawNumber, $position);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                        // Update existing record
                        $stmt = $db->prepare("UPDATE detailed_draw_results SET
                                             number = ?,
                                             color = ?
                                             WHERE draw_number = ? AND position = ?");

                        $stmt->bind_param('isii',
                            $number,
                            $color,
                            $lastDrawNumber,
                            $position
                        );
                    } else {
                        // Insert new record
                        $stmt = $db->prepare("INSERT INTO detailed_draw_results
                                             (draw_number, position, number, color)
                                             VALUES (?, ?, ?, ?)");

                        $stmt->bind_param('iiis',
                            $lastDrawNumber,
                            $position,
                            $number,
                            $color
                        );
                    }

                    if (!$stmt->execute()) {
                        logMessage("Failed to update detailed draw results for position $position: " . $stmt->error, 'WARNING');
                    }
                }
            }
        }

        // Commit the transaction
        $db->commit();

        // Send success response
        sendResponse('success', 'State data saved successfully', [
            'draw_number' => $drawNumber,
            'last_draw_number' => $lastDrawNumber,
            'countdown' => $countdown
        ]);

    } catch (Exception $e) {
        // Roll back the transaction
        $db->rollback();

        logMessage("Error saving state data: " . $e->getMessage(), 'ERROR');
        sendResponse('error', 'Error saving state data: ' . $e->getMessage());
    }
}

/**
 * Generate a unique slip number
 */
function generateSlipNumber() {
    $timestamp = substr(time(), -10);
    $random = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
    return $timestamp . $random;
}

/**
 * Ensure a player exists in the database
 */
function ensurePlayerExists($db, $playerName) {
    // Check if the player exists
    $stmt = $db->prepare("SELECT id FROM players WHERE name = ? LIMIT 1");
    $stmt->bind_param('s', $playerName);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $player = $result->fetch_assoc();
        return $player['id'];
    }

    // Create the player
    $stmt = $db->prepare("INSERT INTO players (name, balance, created_at) VALUES (?, 1000, NOW())");
    $stmt->bind_param('s', $playerName);

    if (!$stmt->execute()) {
        throw new Exception("Failed to create player: " . $stmt->error);
    }

    return $db->insert_id;
}

/**
 * Get the current draw number
 */
function getCurrentDrawNumber($db) {
    $stmt = $db->prepare("SELECT next_draw_number FROM roulette_state ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $state = $result->fetch_assoc();
        return $state['next_draw_number'];
    }

    // Default to 1 if no state exists
    return 1;
}

/**
 * Update analytics data
 */
function updateAnalytics($db, $drawNumber, $totalStake) {
    // Check if analytics record exists for this draw
    $stmt = $db->prepare("SELECT * FROM roulette_analytics WHERE draw_number = ? LIMIT 1");
    $stmt->bind_param('i', $drawNumber);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Update existing record
        $analytics = $result->fetch_assoc();
        $newTotalBets = $analytics['total_bets'] + 1;
        $newTotalStake = $analytics['total_stake'] + $totalStake;

        $stmt = $db->prepare("UPDATE roulette_analytics SET
                             total_bets = ?,
                             total_stake = ?,
                             last_updated = NOW()
                             WHERE draw_number = ?");

        $stmt->bind_param('idi',
            $newTotalBets,
            $newTotalStake,
            $drawNumber
        );

        if (!$stmt->execute()) {
            logMessage("Failed to update analytics: " . $stmt->error, 'WARNING');
        }
    } else {
        // Insert new record
        $stmt = $db->prepare("INSERT INTO roulette_analytics
                             (draw_number, total_bets, total_stake, created_at, last_updated)
                             VALUES (?, 1, ?, NOW(), NOW())");

        $stmt->bind_param('id',
            $drawNumber,
            $totalStake
        );

        if (!$stmt->execute()) {
            logMessage("Failed to insert analytics: " . $stmt->error, 'WARNING');
        }
    }
}

/**
 * Handle getting betting slips
 */
function handleGetBettingSlips($db) {
    // Get the draw number from the request
    $drawNumber = isset($_GET['draw_number']) ? intval($_GET['draw_number']) : getCurrentDrawNumber($db);

    // Get the slip number to filter after (optional)
    $afterSlipNumber = isset($_GET['after_slip_number']) ? $_GET['after_slip_number'] : null;

    // Log the request
    logMessage("Getting betting slips for draw #$drawNumber" . ($afterSlipNumber ? " after slip #$afterSlipNumber" : ""), 'INFO');

    try {
        // Build the query
        $sql = "SELECT bs.* FROM betting_slips bs WHERE bs.draw_number = ?";
        $params = [$drawNumber];

        // Add filter for slip number if provided
        if ($afterSlipNumber) {
            $sql .= " AND bs.slip_number > ?";
            $params[] = $afterSlipNumber;
        }

        // Order by slip number
        $sql .= " ORDER BY bs.slip_number ASC LIMIT 50";

        // Execute the query
        $stmt = $db->prepare($sql);

        // Bind parameters dynamically
        if (count($params) === 1) {
            $stmt->bind_param('i', $params[0]);
        } else if (count($params) === 2) {
            $stmt->bind_param('is', $params[0], $params[1]);
        }

        if (!$stmt->execute()) {
            throw new Exception("Failed to fetch betting slips: " . $stmt->error);
        }

        $result = $stmt->get_result();
        $slips = [];

        while ($row = $result->fetch_assoc()) {
            $slips[] = $row;
        }

        logMessage("Found " . count($slips) . " betting slips for draw #$drawNumber", 'INFO');

        // Send success response
        sendResponse('success', 'Betting slips retrieved successfully', [
            'draw_number' => $drawNumber,
            'betting_slips' => $slips,
            'count' => count($slips)
        ]);

    } catch (Exception $e) {
        logMessage("Error getting betting slips: " . $e->getMessage(), 'ERROR');
        sendResponse('error', 'Error getting betting slips: ' . $e->getMessage());
    }
}

/**
 * Handle getting bets for a slip
 */
function handleGetSlipBets($db) {
    // Get the slip ID from the request
    $slipId = isset($_GET['slip_id']) ? intval($_GET['slip_id']) : 0;

    if ($slipId <= 0) {
        logMessage("Invalid slip ID: $slipId", 'ERROR');
        sendResponse('error', 'Invalid slip ID');
    }

    logMessage("Getting bets for slip #$slipId", 'INFO');

    try {
        // Get the slip details
        $stmt = $db->prepare("
            SELECT b.*
            FROM bets b
            JOIN slip_details sd ON b.id = sd.bet_id
            WHERE sd.slip_id = ?
        ");

        $stmt->bind_param('i', $slipId);

        if (!$stmt->execute()) {
            throw new Exception("Failed to fetch bets: " . $stmt->error);
        }

        $result = $stmt->get_result();
        $bets = [];

        while ($row = $result->fetch_assoc()) {
            $bets[] = $row;
        }

        logMessage("Found " . count($bets) . " bets for slip #$slipId", 'INFO');

        // Send success response
        sendResponse('success', 'Bets retrieved successfully', [
            'slip_id' => $slipId,
            'bets' => $bets,
            'count' => count($bets)
        ]);

    } catch (Exception $e) {
        logMessage("Error getting bets for slip: " . $e->getMessage(), 'ERROR');
        sendResponse('error', 'Error getting bets for slip: ' . $e->getMessage());
    }
}

// Connect to the database
try {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($db->connect_error) {
        throw new Exception("Database connection failed: " . $db->connect_error);
    }

    // Handle different actions
    $action = isset($_GET['action']) ? $_GET['action'] : '';

    switch ($action) {
        case 'save_betting_data':
            handleSaveBettingData($db);
            break;

        case 'save_state':
            handleSaveState($db);
            break;

        case 'get_betting_slips':
            handleGetBettingSlips($db);
            break;

        case 'get_slip_bets':
            handleGetSlipBets($db);
            break;

        default:
            sendResponse('error', 'Invalid action specified');
    }
} catch (Exception $e) {
    logMessage("General error: " . $e->getMessage(), 'ERROR');
    sendResponse('error', 'Error: ' . $e->getMessage());
}
?>