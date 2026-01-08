<?php
/**
 * Automatic Betting Slip Printing API
 * Handles server-side printing requests
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

/**
 * Get betting slip data from database
 */
function getBettingSlipData($pdo, $slipId) {
    try {
        // Get slip information
        $stmt = $pdo->prepare("
            SELECT 
                slip_number,
                draw_number,
                total_stake,
                potential_payout as potential_win,
                created_at as date
            FROM betting_slips 
            WHERE slip_id = ?
        ");
        $stmt->execute([$slipId]);
        $slip = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$slip) {
            return null;
        }
        
        // Get bet details
        $stmt = $pdo->prepare("
            SELECT 
                b.bet_type as type,
                b.bet_description as description,
                b.bet_amount as amount,
                CONCAT(b.multiplier, ':1') as odds,
                b.potential_return
            FROM slip_details sd
            JOIN bets b ON sd.bet_id = b.bet_id
            WHERE sd.slip_id = ?
            ORDER BY b.bet_id
        ");
        $stmt->execute([$slipId]);
        $bets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $slip['bets'] = $bets;
        
        return $slip;
        
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Print betting slip using Python backend
 */
function printBettingSlip($slipData, $printerName = null) {
    try {
        // Prepare command
        $pythonScript = __DIR__ . '/print_slip.py';
        $slipJson = json_encode($slipData, JSON_UNESCAPED_SLASHES);

        // Create temporary file for JSON data to avoid command line escaping issues
        $tempFile = tempnam(sys_get_temp_dir(), 'slip_data_');
        file_put_contents($tempFile, $slipJson);

        // Build command using temp file with proper Python executable
        $pythonExe = 'python';

        // Try to find Python executable on Windows
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Try common Python paths on Windows
            $possiblePaths = [
                'python.exe',
                'python3.exe',
                'C:\\Python39\\python.exe',
                'C:\\Python38\\python.exe',
                'C:\\Python37\\python.exe',
                'C:\\Users\\' . get_current_user() . '\\AppData\\Local\\Programs\\Python\\Python39\\python.exe',
                'C:\\Users\\' . get_current_user() . '\\AppData\\Local\\Programs\\Python\\Python38\\python.exe'
            ];

            foreach ($possiblePaths as $path) {
                $testCommand = "where $path 2>nul";
                if ($path[1] === ':') { // Full path
                    if (file_exists($path)) {
                        $pythonExe = $path;
                        break;
                    }
                } else { // Command name
                    $result = shell_exec($testCommand);
                    if ($result !== null && trim($result) !== '') {
                        $pythonExe = $path;
                        break;
                    }
                }
            }
        }

        $command = "\"$pythonExe\" \"$pythonScript\" \"@$tempFile\"";
        if ($printerName) {
            $command .= " " . escapeshellarg($printerName);
        }

        // Execute Python script with extended timeout
        $output = shell_exec($command . " 2>&1");

        // Clean up temp file
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }

        if ($output === null) {
            return ['success' => false, 'error' => 'Failed to execute print command'];
        }
        
        // Parse JSON response from Python script
        $result = json_decode(trim($output), true);
        
        if ($result === null) {
            return ['success' => false, 'error' => 'Invalid response from print service', 'output' => $output];
        }
        
        return $result;
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Get available printers
 */
function getAvailablePrinters() {
    try {
        $command = 'powershell "Get-Printer | Select-Object Name | ConvertTo-Json"';
        $output = shell_exec($command);
        
        if ($output) {
            $printers = json_decode($output, true);
            if (is_array($printers)) {
                return array_map(function($printer) {
                    return $printer['Name'];
                }, $printers);
            }
        }
        
        return [];
    } catch (Exception $e) {
        return [];
    }
}

// Handle different actions
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'print_slip_data':
        // Print slip using provided data (from DOM extraction)
        $slipDataJson = $_POST['slip_data'] ?? null;
        $printerName = $_POST['printer_name'] ?? null;

        if (!$slipDataJson) {
            echo json_encode(['success' => false, 'error' => 'Slip data required']);
            exit;
        }

        // Parse slip data
        $slipData = json_decode($slipDataJson, true);
        if (!$slipData) {
            echo json_encode(['success' => false, 'error' => 'Invalid slip data format']);
            exit;
        }

        // Print the slip
        $result = printBettingSlip($slipData, $printerName);
        echo json_encode($result);
        break;

    case 'print_slip':
        // Print slip using database lookup (legacy method)
        $slipId = $_POST['slip_id'] ?? null;
        $printerName = $_POST['printer_name'] ?? null;

        if (!$slipId) {
            echo json_encode(['success' => false, 'error' => 'Slip ID required']);
            exit;
        }

        // Get slip data from database
        $slipData = getBettingSlipData($pdo, $slipId);

        if (!$slipData) {
            echo json_encode(['success' => false, 'error' => 'Betting slip not found']);
            exit;
        }

        // Print the slip
        $result = printBettingSlip($slipData, $printerName);
        echo json_encode($result);
        break;
        
    case 'get_printers':
        $printers = getAvailablePrinters();
        echo json_encode(['success' => true, 'printers' => $printers]);
        break;
        
    case 'test_print':
        // Test print with sample data
        $testSlip = [
            'slip_number' => 'TEST-' . date('YmdHis'),
            'date' => date('Y-m-d H:i:s'),
            'draw_number' => '999',
            'total_stake' => '100.00',
            'potential_win' => '3600.00',
            'bets' => [
                [
                    'type' => 'straight',
                    'description' => 'Straight Up on 7',
                    'amount' => '100.00',
                    'odds' => '35:1',
                    'potential_return' => '3600.00'
                ]
            ]
        ];
        
        $printerName = $_POST['printer_name'] ?? null;
        $result = printBettingSlip($testSlip, $printerName);
        echo json_encode($result);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
?>
