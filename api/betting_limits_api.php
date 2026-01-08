<?php
/**
 * Betting Limits API
 * Handles all betting limits operations for the admin interface
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "roulette";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Get action from request
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_current_draw':
        getCurrentDraw($pdo);
        break;
        
    case 'get_realtime_data':
        getRealtimeData($pdo);
        break;
        
    case 'update_limit':
        updateLimit($pdo);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

/**
 * Get current draw number
 */
function getCurrentDraw($pdo) {
    try {
        // Get current draw - use detailed_draw_results as the authoritative source
        try {
            // Get the latest completed draw and add 1 for current draw
            $stmt = $pdo->query("SELECT draw_number FROM detailed_draw_results ORDER BY id DESC LIMIT 1");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $currentDraw = $result['draw_number'] + 1; // Next draw after latest completed
            } else {
                // Fallback to roulette_state if no completed draws
                $stmt = $pdo->query("SELECT draw_number FROM roulette_state ORDER BY id DESC LIMIT 1");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $currentDraw = $result ? $result['draw_number'] : 84; // Default to 84
            }
        } catch (Exception $e) {
            // If tables don't exist, use current default
            $currentDraw = 84;
        }

        echo json_encode([
            'success' => true,
            'current_draw' => (int)$currentDraw
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Get real-time betting limits data for a specific draw
 */
function getRealtimeData($pdo) {
    try {
        $drawNumber = $_GET['draw_number'] ?? 72;

        // Initialize data for all numbers 0-36
        $data = [];
        for ($i = 0; $i <= 36; $i++) {
            $data[] = [
                'roulette_number' => $i,
                'max_limit' => 1000000, // Default 1M limit
                'current_total' => 0,
                'remaining_limit' => 1000000,
                'usage_percentage' => 0,
                'bet_count' => 0, // Number of individual bets
                'is_sold_out' => false,
                'is_manually_sold_out' => false
            ];
        }
        
        // Ensure betting_limits table exists
        try {
            $createTable = "
                CREATE TABLE IF NOT EXISTS betting_limits (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    draw_number INT NOT NULL,
                    roulette_number INT NOT NULL,
                    max_limit DECIMAL(15,2) DEFAULT 1000000.00,
                    is_manually_sold_out BOOLEAN DEFAULT FALSE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_draw_number (draw_number, roulette_number)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ";
            $pdo->exec($createTable);
        } catch (Exception $e) {
            // Table might already exist, continue
        }
        
        // Get betting limits for this draw
        try {
            $stmt = $pdo->prepare("
                SELECT roulette_number, max_limit, is_manually_sold_out
                FROM betting_limits
                WHERE draw_number = ?
            ");
            $stmt->execute([$drawNumber]);
            $limits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // If there's an error, use empty limits
            $limits = [];
        }
        
        // Apply limits to data
        foreach ($limits as $limit) {
            $number = $limit['roulette_number'];
            if ($number >= 0 && $number <= 36) {
                $data[$number]['max_limit'] = (float)$limit['max_limit'];
                $data[$number]['is_manually_sold_out'] = (bool)$limit['is_manually_sold_out'];
                $data[$number]['remaining_limit'] = $data[$number]['max_limit'] - $data[$number]['current_total'];
                
                // Check if sold out
                if ($data[$number]['is_manually_sold_out'] || $data[$number]['current_total'] >= $data[$number]['max_limit']) {
                    $data[$number]['is_sold_out'] = true;
                }
                
                // Calculate usage percentage
                if ($data[$number]['max_limit'] > 0) {
                    $data[$number]['usage_percentage'] = ($data[$number]['current_total'] / $data[$number]['max_limit']) * 100;
                }
            }
        }
        
        // Get ALL betting totals from bets table - calculate complete exposure per number
        try {
            $stmt = $pdo->prepare("
                SELECT
                    b.bet_type,
                    b.bet_description,
                    b.bet_amount,
                    COUNT(*) as bet_count
                FROM betting_slips bs
                JOIN slip_details sd ON bs.slip_id = sd.slip_id
                JOIN bets b ON sd.bet_id = b.bet_id
                WHERE bs.draw_number = ?
                GROUP BY b.bet_type, b.bet_description, b.bet_amount
            ");
            $stmt->execute([$drawNumber]);
            $bets = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate complete exposure per number from all bet types
            foreach ($bets as $bet) {
                $betAmount = $bet['bet_amount'] * $bet['bet_count'];
                $description = $bet['bet_description'];
                $type = $bet['bet_type'];

                switch ($type) {
                    case 'straight':
                        // Straight Up on X
                        if (preg_match('/Straight Up on (\d+)/', $description, $matches)) {
                            $number = (int)$matches[1];
                            if ($number >= 0 && $number <= 36) {
                                $data[$number]['current_total'] += $betAmount;
                                $data[$number]['bet_count'] += $bet['bet_count'];
                            }
                        }
                        break;

                    case 'split':
                        // Split (X,Y) - divide bet amount by 2
                        if (preg_match('/Split \((\d+),(\d+)\)/', $description, $matches)) {
                            $exposurePerNumber = $betAmount / 2;
                            $numbers = [(int)$matches[1], (int)$matches[2]];

                            foreach ($numbers as $number) {
                                if ($number >= 0 && $number <= 36) {
                                    $data[$number]['current_total'] += $exposurePerNumber;
                                    $data[$number]['bet_count'] += $bet['bet_count'];
                                }
                            }
                        }
                        break;

                    case 'corner':
                        // Corner (W,X,Y,Z) - divide bet amount by 4
                        if (preg_match('/Corner \((\d+),(\d+),(\d+),(\d+)\)/', $description, $matches)) {
                            $exposurePerNumber = $betAmount / 4;
                            $numbers = [(int)$matches[1], (int)$matches[2], (int)$matches[3], (int)$matches[4]];

                            foreach ($numbers as $number) {
                                if ($number >= 0 && $number <= 36) {
                                    $data[$number]['current_total'] += $exposurePerNumber;
                                    $data[$number]['bet_count'] += $bet['bet_count'];
                                }
                            }
                        }
                        break;

                    case 'street':
                        // Street (X,Y,Z) - divide bet amount by 3
                        if (preg_match('/Street \((\d+),(\d+),(\d+)\)/', $description, $matches)) {
                            $exposurePerNumber = $betAmount / 3;
                            $numbers = [(int)$matches[1], (int)$matches[2], (int)$matches[3]];

                            foreach ($numbers as $number) {
                                if ($number >= 0 && $number <= 36) {
                                    $data[$number]['current_total'] += $exposurePerNumber;
                                    $data[$number]['bet_count'] += $bet['bet_count'];
                                }
                            }
                        }
                        break;

                    case 'sixline':
                        // Six Line (A,B,C,D,E,F) - divide bet amount by 6
                        if (preg_match('/Six Line \((\d+),(\d+),(\d+),(\d+),(\d+),(\d+)\)/', $description, $matches)) {
                            $exposurePerNumber = $betAmount / 6;
                            $numbers = [(int)$matches[1], (int)$matches[2], (int)$matches[3], (int)$matches[4], (int)$matches[5], (int)$matches[6]];

                            foreach ($numbers as $number) {
                                if ($number >= 0 && $number <= 36) {
                                    $data[$number]['current_total'] += $exposurePerNumber;
                                    $data[$number]['bet_count'] += $bet['bet_count'];
                                }
                            }
                        }
                        break;

                    case 'dozen':
                        // First 12, Second 12, Third 12 - divide bet amount by 12
                        if (preg_match('/First 12/', $description)) {
                            $exposurePerNumber = $betAmount / 12;
                            for ($number = 1; $number <= 12; $number++) {
                                $data[$number]['current_total'] += $exposurePerNumber;
                                $data[$number]['bet_count'] += $bet['bet_count'];
                            }
                        } elseif (preg_match('/Second 12/', $description)) {
                            $exposurePerNumber = $betAmount / 12;
                            for ($number = 13; $number <= 24; $number++) {
                                $data[$number]['current_total'] += $exposurePerNumber;
                                $data[$number]['bet_count'] += $bet['bet_count'];
                            }
                        } elseif (preg_match('/Third 12/', $description)) {
                            $exposurePerNumber = $betAmount / 12;
                            for ($number = 25; $number <= 36; $number++) {
                                $data[$number]['current_total'] += $exposurePerNumber;
                                $data[$number]['bet_count'] += $bet['bet_count'];
                            }
                        }
                        break;

                    case 'column':
                        // Column bets - divide bet amount by 12
                        if (preg_match('/Column 1/', $description)) {
                            $exposurePerNumber = $betAmount / 12;
                            for ($number = 1; $number <= 34; $number += 3) {
                                $data[$number]['current_total'] += $exposurePerNumber;
                                $data[$number]['bet_count'] += $bet['bet_count'];
                            }
                        } elseif (preg_match('/Column 2/', $description)) {
                            $exposurePerNumber = $betAmount / 12;
                            for ($number = 2; $number <= 35; $number += 3) {
                                $data[$number]['current_total'] += $exposurePerNumber;
                                $data[$number]['bet_count'] += $bet['bet_count'];
                            }
                        } elseif (preg_match('/Column 3/', $description)) {
                            $exposurePerNumber = $betAmount / 12;
                            for ($number = 3; $number <= 36; $number += 3) {
                                $data[$number]['current_total'] += $exposurePerNumber;
                                $data[$number]['bet_count'] += $bet['bet_count'];
                            }
                        }
                        break;

                    case 'even_odd':
                    case 'color':
                        // Red/Black, Even/Odd - divide bet amount by 18
                        $redNumbers = [1,3,5,7,9,12,14,16,18,19,21,23,25,27,30,32,34,36];
                        $blackNumbers = [2,4,6,8,10,11,13,15,17,20,22,24,26,28,29,31,33,35];

                        if (preg_match('/Red/', $description)) {
                            $exposurePerNumber = $betAmount / 18;
                            foreach ($redNumbers as $number) {
                                $data[$number]['current_total'] += $exposurePerNumber;
                                $data[$number]['bet_count'] += $bet['bet_count'];
                            }
                        } elseif (preg_match('/Black/', $description)) {
                            $exposurePerNumber = $betAmount / 18;
                            foreach ($blackNumbers as $number) {
                                $data[$number]['current_total'] += $exposurePerNumber;
                                $data[$number]['bet_count'] += $bet['bet_count'];
                            }
                        } elseif (preg_match('/Even/', $description)) {
                            $exposurePerNumber = $betAmount / 18;
                            for ($number = 2; $number <= 36; $number += 2) {
                                $data[$number]['current_total'] += $exposurePerNumber;
                                $data[$number]['bet_count'] += $bet['bet_count'];
                            }
                        } elseif (preg_match('/Odd/', $description)) {
                            $exposurePerNumber = $betAmount / 18;
                            for ($number = 1; $number <= 35; $number += 2) {
                                $data[$number]['current_total'] += $exposurePerNumber;
                                $data[$number]['bet_count'] += $bet['bet_count'];
                            }
                        }
                        break;

                    case 'high_low':
                        // 1-18 (Low) or 19-36 (High) - divide bet amount by 18
                        if (preg_match('/1-18|Low/', $description)) {
                            $exposurePerNumber = $betAmount / 18;
                            for ($number = 1; $number <= 18; $number++) {
                                $data[$number]['current_total'] += $exposurePerNumber;
                                $data[$number]['bet_count'] += $bet['bet_count'];
                            }
                        } elseif (preg_match('/19-36|High/', $description)) {
                            $exposurePerNumber = $betAmount / 18;
                            for ($number = 19; $number <= 36; $number++) {
                                $data[$number]['current_total'] += $exposurePerNumber;
                                $data[$number]['bet_count'] += $bet['bet_count'];
                            }
                        }
                        break;
                }
            }

            // Recalculate limits and percentages for all numbers
            for ($i = 0; $i <= 36; $i++) {
                $data[$i]['remaining_limit'] = $data[$i]['max_limit'] - $data[$i]['current_total'];

                // Recalculate usage percentage
                if ($data[$i]['max_limit'] > 0) {
                    $data[$i]['usage_percentage'] = ($data[$i]['current_total'] / $data[$i]['max_limit']) * 100;
                }

                // Check if now sold out due to actual bets
                if ($data[$i]['current_total'] >= $data[$i]['max_limit']) {
                    $data[$i]['is_sold_out'] = true;
                }
            }
        } catch (Exception $e) {
            // If betting_slips query fails, continue with zero betting amounts
            // This ensures clean data for upcoming draws
        }
        
        // Calculate summary statistics
        $totalBets = 0;
        $totalAmount = 0;
        $activeNumbers = 0;
        $soldOutCount = 0;

        foreach ($data as $number) {
            if ($number['current_total'] > 0) {
                $totalBets++;
                $totalAmount += $number['current_total'];
            }
            if (!$number['is_sold_out']) {
                $activeNumbers++;
            } else {
                $soldOutCount++;
            }
        }

        $summary = [
            'total_bets' => $totalBets,
            'total_amount' => $totalAmount,
            'active_numbers' => $activeNumbers,
            'sold_out_count' => $soldOutCount,
            'draw_number' => (int)$drawNumber
        ];

        echo json_encode([
            'success' => true,
            'draw_number' => (int)$drawNumber,
            'data' => $data,
            'summary' => $summary
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Update betting limit for a specific number
 */
function updateLimit($pdo) {
    try {
        $rouletteNumber = $_POST['roulette_number'] ?? null;
        $maxLimit = $_POST['max_limit'] ?? null;
        $soldOut = $_POST['sold_out'] ?? '0';
        
        if ($rouletteNumber === null || $maxLimit === null) {
            throw new Exception('Missing required parameters');
        }
        
        // Get current draw - use detailed_draw_results as the authoritative source
        try {
            // Get the latest completed draw and add 1 for current draw
            $stmt = $pdo->query("SELECT draw_number FROM detailed_draw_results ORDER BY id DESC LIMIT 1");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $currentDraw = $result['draw_number'] + 1; // Next draw after latest completed
            } else {
                // Fallback to roulette_state if no completed draws
                $stmt = $pdo->query("SELECT draw_number FROM roulette_state ORDER BY id DESC LIMIT 1");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $currentDraw = $result ? $result['draw_number'] : 84; // Default to 84
            }
        } catch (Exception $e) {
            // If tables don't exist, use current default
            $currentDraw = 84;
        }
        
        // Insert or update betting limit
        $stmt = $pdo->prepare("
            INSERT INTO betting_limits (draw_number, roulette_number, max_limit, is_manually_sold_out)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                max_limit = VALUES(max_limit),
                is_manually_sold_out = VALUES(is_manually_sold_out),
                updated_at = CURRENT_TIMESTAMP
        ");
        
        $stmt->execute([
            $currentDraw,
            (int)$rouletteNumber,
            (float)$maxLimit,
            $soldOut === '1' ? 1 : 0
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => "Updated limit for number $rouletteNumber",
            'draw_number' => $currentDraw
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>
