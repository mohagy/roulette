<?php
/**
 * PHP-Only Print Solution
 * Direct printing without Python dependency
 */

header('Content-Type: application/json');

class PHPPrinter {
    
    public function getAvailablePrinters() {
        $printers = [];
        
        try {
            // Method 1: Use Windows wmic command
            $output = shell_exec('wmic printer get name /format:csv 2>nul');
            if ($output) {
                $lines = explode("\n", $output);
                foreach ($lines as $line) {
                    $parts = explode(',', $line);
                    if (count($parts) >= 2 && !empty(trim($parts[1]))) {
                        $printerName = trim($parts[1]);
                        if ($printerName !== 'Name' && !empty($printerName)) {
                            $printers[] = $printerName;
                        }
                    }
                }
            }
            
            // Method 2: Fallback - PowerShell
            if (empty($printers)) {
                $psOutput = shell_exec('powershell.exe -Command "Get-Printer | Select-Object Name | Format-Table -HideTableHeaders" 2>nul');
                if ($psOutput) {
                    $lines = explode("\n", $psOutput);
                    foreach ($lines as $line) {
                        $printerName = trim($line);
                        if (!empty($printerName) && $printerName !== 'Name') {
                            $printers[] = $printerName;
                        }
                    }
                }
            }
            
            // Method 3: Add common default printers if none found
            if (empty($printers)) {
                $printers = [
                    'Microsoft Print to PDF',
                    'Microsoft XPS Document Writer'
                ];
            }
            
        } catch (Exception $e) {
            $printers = ['Microsoft Print to PDF'];
        }
        
        return array_unique($printers);
    }
    
    public function printSlip($slipData, $printerName = null) {
        try {
            // Get available printers
            $printers = $this->getAvailablePrinters();
            
            if (empty($printers)) {
                return [
                    'success' => false,
                    'error' => 'No printers available'
                ];
            }
            
            // Use specified printer or first available
            if (!$printerName || !in_array($printerName, $printers)) {
                $printerName = $printers[0];
            }
            
            // Create text content for printing
            $textContent = $this->createPrintableText($slipData);
            
            // Create temporary text file
            $tempFile = tempnam(sys_get_temp_dir(), 'betting_slip_') . '.txt';
            file_put_contents($tempFile, $textContent);
            
            $success = false;
            $error = '';
            
            try {
                // Method 1: Direct print to printer (Windows)
                if ($this->printToWindowsPrinter($tempFile, $printerName)) {
                    $success = true;
                } else {
                    // Method 2: Save to desktop if direct print fails
                    if ($printerName === 'Microsoft Print to PDF' || strpos($printerName, 'PDF') !== false) {
                        $success = $this->saveToDesktop($tempFile, $slipData);
                    } else {
                        // Method 3: Try PowerShell print
                        $success = $this->printWithPowerShell($tempFile, $printerName);
                    }
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
            
            // Clean up
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            
            if ($success) {
                return [
                    'success' => true,
                    'message' => "Slip printed to $printerName"
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $error ?: 'Print operation failed'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function createPrintableText($slipData) {
        $text = str_repeat('=', 50) . "\n";
        $text .= str_pad('BETTING SLIP', 50, ' ', STR_PAD_BOTH) . "\n";
        $text .= str_repeat('=', 50) . "\n\n";
        
        $text .= "Date: " . ($slipData['date'] ?? date('d/m/Y H:i:s')) . "\n";
        $text .= "Player ID: " . ($slipData['player_id'] ?? 'GUEST') . "\n";
        $text .= "Draw #: " . ($slipData['draw_number'] ?? 'N/A') . "\n\n";
        
        $text .= str_repeat('-', 50) . "\n";
        $text .= "BETS:\n";
        $text .= str_repeat('-', 50) . "\n";
        
        if (isset($slipData['bets']) && is_array($slipData['bets'])) {
            foreach ($slipData['bets'] as $i => $bet) {
                $text .= ($i + 1) . ". " . strtoupper($bet['type'] ?? 'UNKNOWN') . ": " . ($bet['description'] ?? 'N/A') . "\n";
                $text .= "   Stake: $" . ($bet['amount'] ?? '0.00') . "\n";
                $text .= "   Pays: " . ($bet['odds'] ?? '1:1') . "\n";
                $text .= "   Return: $" . ($bet['potential_return'] ?? '0.00') . "\n\n";
            }
        } else {
            $text .= "No bets found\n\n";
        }
        
        $text .= str_repeat('-', 50) . "\n";
        $text .= "Total Stakes: $" . ($slipData['total_stake'] ?? '0.00') . "\n";
        $text .= "Potential Win: $" . ($slipData['potential_win'] ?? '0.00') . "\n";
        $text .= str_repeat('=', 50) . "\n";
        $text .= "Slip #: " . ($slipData['slip_number'] ?? 'N/A') . "\n";
        $text .= str_repeat('=', 50) . "\n\n";
        
        $text .= "Good luck!\n";
        $text .= "This betting slip is for entertainment purposes only.\n";
        
        return $text;
    }
    
    private function printToWindowsPrinter($textFile, $printerName) {
        try {
            // Use Windows copy command to print directly
            $command = "copy \"$textFile\" \"$printerName\" 2>nul";
            $result = shell_exec($command);
            return $result !== null;
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function printWithPowerShell($textFile, $printerName) {
        try {
            $command = "powershell.exe -Command \"Get-Content '$textFile' | Out-Printer -Name '$printerName'\" 2>nul";
            $result = shell_exec($command);
            return $result !== null;
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function saveToDesktop($textFile, $slipData) {
        try {
            $desktop = getenv('USERPROFILE') . '\\Desktop';
            $filename = 'betting_slip_' . ($slipData['slip_number'] ?? date('YmdHis')) . '.txt';
            $outputFile = $desktop . '\\' . $filename;
            
            return copy($textFile, $outputFile);
        } catch (Exception $e) {
            return false;
        }
    }
}

// Handle requests
$action = $_POST['action'] ?? $_GET['action'] ?? '';

$printer = new PHPPrinter();

switch ($action) {
    case 'get_printers':
        $printers = $printer->getAvailablePrinters();
        echo json_encode([
            'success' => true,
            'printers' => $printers
        ]);
        break;
        
    case 'print_slip_data':
        $slipDataJson = $_POST['slip_data'] ?? null;
        $printerName = $_POST['printer_name'] ?? null;
        
        if (!$slipDataJson) {
            echo json_encode(['success' => false, 'error' => 'Slip data required']);
            exit;
        }
        
        $slipData = json_decode($slipDataJson, true);
        if (!$slipData) {
            echo json_encode(['success' => false, 'error' => 'Invalid slip data format']);
            exit;
        }
        
        $result = $printer->printSlip($slipData, $printerName);
        echo json_encode($result);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
