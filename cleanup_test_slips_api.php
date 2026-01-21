<?php
/**
 * API for cleaning up test slips
 */

header('Content-Type: application/json');

try {
    require_once 'php/db_connect.php';
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'fix_slip':
            $slipId = $input['slip_id'] ?? 0;
            $result = fixSlip($conn, $slipId);
            break;
            
        case 'delete_slip':
            $slipId = $input['slip_id'] ?? 0;
            $result = deleteSlip($conn, $slipId);
            break;
            
        case 'fix_all_slips':
            $result = fixAllSlips($conn);
            break;
            
        case 'delete_all_broken_slips':
            $result = deleteAllBrokenSlips($conn);
            break;
            
        default:
            $result = ['success' => false, 'message' => 'Invalid action'];
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function fixSlip($conn, $slipId) {
    try {
        // Check if slip exists and doesn't have bets
        $stmt = $conn->prepare("
            SELECT bs.slip_id, bs.slip_number, COUNT(sd.bet_id) as bet_count
            FROM betting_slips bs
            LEFT JOIN slip_details sd ON bs.slip_id = sd.slip_id
            WHERE bs.slip_id = ?
            GROUP BY bs.slip_id, bs.slip_number
        ");
        $stmt->bind_param("i", $slipId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            return ['success' => false, 'message' => 'Slip not found'];
        }
        
        $slip = $result->fetch_assoc();
        if ($slip['bet_count'] > 0) {
            return ['success' => false, 'message' => 'Slip already has bets'];
        }
        
        // Create a test bet
        $stmt2 = $conn->prepare("
            INSERT INTO bets (
                user_id, bet_type, bet_description, 
                bet_amount, multiplier, potential_return, created_at
            ) VALUES (1, 'straight', 'Straight Up on 7', 10.00, 35.00, 350.00, NOW())
        ");
        
        if ($stmt2->execute()) {
            $betId = $conn->insert_id;
            
            // Link the bet to the slip
            $stmt3 = $conn->prepare("INSERT INTO slip_details (slip_id, bet_id) VALUES (?, ?)");
            $stmt3->bind_param("ii", $slipId, $betId);
            
            if ($stmt3->execute()) {
                return ['success' => true, 'message' => 'Slip fixed successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to link bet to slip'];
            }
        } else {
            return ['success' => false, 'message' => 'Failed to create bet'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function deleteSlip($conn, $slipId) {
    try {
        // Delete the slip (this will cascade to slip_details if there are any)
        $stmt = $conn->prepare("DELETE FROM betting_slips WHERE slip_id = ?");
        $stmt->bind_param("i", $slipId);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Slip deleted successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to delete slip'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function fixAllSlips($conn) {
    try {
        // Find all test slips without bets
        $stmt = $conn->prepare("
            SELECT bs.slip_id
            FROM betting_slips bs
            LEFT JOIN slip_details sd ON bs.slip_id = sd.slip_id
            WHERE (bs.slip_number LIKE 'FUTURE_TEST_%' OR bs.slip_number LIKE 'COMPREHENSIVE_TEST_%')
            GROUP BY bs.slip_id
            HAVING COUNT(sd.bet_id) = 0
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $fixedCount = 0;
        $failedCount = 0;
        
        while ($slip = $result->fetch_assoc()) {
            $fixResult = fixSlip($conn, $slip['slip_id']);
            if ($fixResult['success']) {
                $fixedCount++;
            } else {
                $failedCount++;
            }
        }
        
        return [
            'success' => true, 
            'message' => "Fixed $fixedCount slips, $failedCount failed"
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function deleteAllBrokenSlips($conn) {
    try {
        // Delete all test slips without bets
        $stmt = $conn->prepare("
            DELETE bs FROM betting_slips bs
            LEFT JOIN slip_details sd ON bs.slip_id = sd.slip_id
            WHERE (bs.slip_number LIKE 'FUTURE_TEST_%' OR bs.slip_number LIKE 'COMPREHENSIVE_TEST_%')
            GROUP BY bs.slip_id
            HAVING COUNT(sd.bet_id) = 0
        ");
        
        if ($stmt->execute()) {
            $deletedCount = $stmt->affected_rows;
            return [
                'success' => true, 
                'message' => "Deleted $deletedCount broken test slips"
            ];
        } else {
            return ['success' => false, 'message' => 'Failed to delete slips'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
?>
