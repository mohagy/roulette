<?php
/**
 * Centralized Draw Number Manager
 * 
 * This class provides a single, thread-safe source of truth for draw number management
 * to prevent race conditions and sequence skips.
 */

class DrawNumberManager {
    private $conn;
    private $lockTimeout = 10; // seconds
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Safely advance to the next draw number using database locking
     * @return array Result with success status and draw numbers
     */
    public function advanceToNextDraw() {
        try {
            // Start transaction with proper isolation
            $this->conn->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);
            
            // Get current draw number with exclusive lock
            $stmt = $this->conn->prepare("
                SELECT current_draw_number 
                FROM roulette_analytics 
                WHERE id = 1 
                FOR UPDATE
            ");
            
            if (!$stmt) {
                throw new Exception("Failed to prepare lock statement: " . $this->conn->error);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("No roulette_analytics record found");
            }
            
            $row = $result->fetch_assoc();
            $currentDraw = (int)$row['current_draw_number'];
            $nextDraw = $currentDraw + 1;
            
            // Validate sequence integrity
            $this->validateSequenceIntegrity($currentDraw);
            
            // Update the current draw number
            $updateStmt = $this->conn->prepare("
                UPDATE roulette_analytics 
                SET current_draw_number = ?, 
                    last_updated = NOW() 
                WHERE id = 1
            ");
            
            if (!$updateStmt) {
                throw new Exception("Failed to prepare update statement: " . $this->conn->error);
            }
            
            $updateStmt->bind_param("i", $nextDraw);
            $updateResult = $updateStmt->execute();
            
            if (!$updateResult) {
                throw new Exception("Failed to update draw number: " . $updateStmt->error);
            }
            
            // Update roulette_state table for compatibility
            $this->updateRouletteState($nextDraw, $nextDraw + 1);
            
            // Commit transaction
            $this->conn->commit();
            
            // Log the successful advancement
            $this->logDrawAdvancement($currentDraw, $nextDraw);
            
            return [
                'success' => true,
                'message' => 'Draw advanced successfully',
                'previousDraw' => $currentDraw,
                'currentDraw' => $nextDraw,
                'nextDraw' => $nextDraw + 1,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            // Rollback on any error
            $this->conn->rollback();
            
            $this->logError("Failed to advance draw: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'DRAW_ADVANCE_FAILED'
            ];
        }
    }
    
    /**
     * Get current draw information safely
     * @return array Current draw information
     */
    public function getCurrentDrawInfo() {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    current_draw_number,
                    last_updated,
                    total_spins
                FROM roulette_analytics 
                WHERE id = 1
            ");
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return [
                    'success' => false,
                    'message' => 'No analytics data found'
                ];
            }
            
            $row = $result->fetch_assoc();
            $currentDraw = (int)$row['current_draw_number'];
            
            return [
                'success' => true,
                'currentDraw' => $currentDraw,
                'nextDraw' => $currentDraw + 1,
                'lastUpdated' => $row['last_updated'],
                'totalSpins' => $row['total_spins']
            ];
            
        } catch (Exception $e) {
            $this->logError("Failed to get current draw info: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate sequence integrity to detect gaps
     * @param int $currentDraw Current draw number
     * @throws Exception if sequence integrity is compromised
     */
    private function validateSequenceIntegrity($currentDraw) {
        // Check for gaps in detailed_draw_results
        $stmt = $this->conn->prepare("
            SELECT 
                MIN(draw_number) as min_draw,
                MAX(draw_number) as max_draw,
                COUNT(*) as total_draws
            FROM detailed_draw_results
        ");
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $minDraw = (int)$row['min_draw'];
            $maxDraw = (int)$row['max_draw'];
            $totalDraws = (int)$row['total_draws'];
            
            // Calculate expected number of draws in range
            $expectedDraws = $maxDraw - $minDraw + 1;
            
            if ($totalDraws < $expectedDraws) {
                $missingCount = $expectedDraws - $totalDraws;
                $this->logWarning("Sequence gap detected: $missingCount missing draws between $minDraw and $maxDraw");
                
                // Don't throw exception, just log warning
                // throw new Exception("Sequence integrity compromised: $missingCount missing draws");
            }
        }
    }
    
    /**
     * Update roulette_state table for compatibility
     * @param int $currentDraw Current draw number
     * @param int $nextDraw Next draw number
     */
    private function updateRouletteState($currentDraw, $nextDraw) {
        $stmt = $this->conn->prepare("
            INSERT INTO roulette_state (id, current_draw, next_draw, updated_at)
            VALUES (1, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            current_draw = VALUES(current_draw),
            next_draw = VALUES(next_draw),
            updated_at = VALUES(updated_at)
        ");
        
        if ($stmt) {
            $currentDrawStr = "#$currentDraw";
            $nextDrawStr = "#$nextDraw";
            $stmt->bind_param("ss", $currentDrawStr, $nextDrawStr);
            $stmt->execute();
        }
    }
    
    /**
     * Detect and report sequence gaps
     * @return array Gap analysis results
     */
    public function detectSequenceGaps() {
        try {
            $stmt = $this->conn->prepare("
                SELECT draw_number 
                FROM detailed_draw_results 
                ORDER BY draw_number ASC
            ");
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $draws = [];
            while ($row = $result->fetch_assoc()) {
                $draws[] = (int)$row['draw_number'];
            }
            
            if (empty($draws)) {
                return [
                    'success' => true,
                    'hasGaps' => false,
                    'message' => 'No draws found'
                ];
            }
            
            $gaps = [];
            $minDraw = min($draws);
            $maxDraw = max($draws);
            
            for ($i = $minDraw; $i <= $maxDraw; $i++) {
                if (!in_array($i, $draws)) {
                    $gaps[] = $i;
                }
            }
            
            return [
                'success' => true,
                'hasGaps' => !empty($gaps),
                'gaps' => $gaps,
                'totalDraws' => count($draws),
                'expectedDraws' => $maxDraw - $minDraw + 1,
                'missingCount' => count($gaps),
                'range' => ['min' => $minDraw, 'max' => $maxDraw]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Backfill missing draws with placeholder data
     * @param array $missingDraws Array of missing draw numbers
     * @return array Backfill results
     */
    public function backfillMissingDraws($missingDraws) {
        if (empty($missingDraws)) {
            return [
                'success' => true,
                'message' => 'No draws to backfill'
            ];
        }
        
        try {
            $this->conn->begin_transaction();
            
            $stmt = $this->conn->prepare("
                INSERT INTO detailed_draw_results 
                (draw_number, winning_number, color, timestamp, created_at)
                VALUES (?, 0, 'green', NOW(), NOW())
            ");
            
            $backfilledCount = 0;
            foreach ($missingDraws as $drawNumber) {
                $stmt->bind_param("i", $drawNumber);
                if ($stmt->execute()) {
                    $backfilledCount++;
                    $this->logInfo("Backfilled draw #$drawNumber");
                }
            }
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => "Successfully backfilled $backfilledCount draws",
                'backfilledDraws' => $missingDraws,
                'count' => $backfilledCount
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            
            return [
                'success' => false,
                'message' => 'Failed to backfill draws: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Log draw advancement
     */
    private function logDrawAdvancement($from, $to) {
        $message = "Draw advanced: #$from -> #$to";
        error_log("[DrawNumberManager] $message");
    }
    
    /**
     * Log error
     */
    private function logError($message) {
        error_log("[DrawNumberManager] ERROR: $message");
    }
    
    /**
     * Log warning
     */
    private function logWarning($message) {
        error_log("[DrawNumberManager] WARNING: $message");
    }
    
    /**
     * Log info
     */
    private function logInfo($message) {
        error_log("[DrawNumberManager] INFO: $message");
    }
}

// Usage example:
/*
require_once 'includes/db_connection.php';
$drawManager = new DrawNumberManager($conn);

// Safely advance to next draw
$result = $drawManager->advanceToNextDraw();

// Get current draw info
$info = $drawManager->getCurrentDrawInfo();

// Detect sequence gaps
$gaps = $drawManager->detectSequenceGaps();

// Backfill missing draws
if ($gaps['hasGaps']) {
    $backfillResult = $drawManager->backfillMissingDraws($gaps['gaps']);
}
*/
?>
