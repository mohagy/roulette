<?php
// Include database connection
require_once 'php/db_connect.php';

echo "CHECKING SAVED BETTING SLIP AND BETS\n";
echo "===================================\n\n";

// Get the latest betting slip
$slipResult = $conn->query("SELECT * FROM betting_slips ORDER BY slip_id DESC LIMIT 1");
if ($slipResult->num_rows > 0) {
    $slip = $slipResult->fetch_assoc();
    echo "LATEST BETTING SLIP:\n";
    echo "- Slip ID: " . $slip['slip_id'] . "\n";
    echo "- Slip Number: " . $slip['slip_number'] . "\n";
    echo "- Player ID: " . $slip['player_id'] . "\n";
    echo "- Draw Number: " . $slip['draw_number'] . "\n";
    echo "- Total Stake: $" . $slip['total_stake'] . "\n";
    echo "- Potential Payout: $" . $slip['potential_payout'] . "\n";
    echo "- Created: " . $slip['created_at'] . "\n";
    
    // Get bets for this slip
    $betsQuery = "
        SELECT b.* 
        FROM bets b
        JOIN slip_details sd ON b.bet_id = sd.bet_id
        WHERE sd.slip_id = " . $slip['slip_id'];
    
    $betsResult = $conn->query($betsQuery);
    
    if ($betsResult->num_rows > 0) {
        echo "\nBETS ON THIS SLIP:\n";
        $totalCount = $betsResult->num_rows;
        echo "Total bets: $totalCount\n\n";
        
        while ($bet = $betsResult->fetch_assoc()) {
            echo "- Bet ID: " . $bet['bet_id'] . "\n";
            echo "  Type: " . $bet['bet_type'] . "\n";
            echo "  Description: " . $bet['bet_description'] . "\n";
            echo "  Amount: $" . $bet['bet_amount'] . "\n";
            echo "  Multiplier: " . $bet['multiplier'] . "x\n";
            echo "  Potential Return: $" . $bet['potential_return'] . "\n";
            echo "  Player ID: " . $bet['player_id'] . "\n";
            echo "  Created: " . $bet['created_at'] . "\n\n";
        }
    } else {
        echo "\nNO BETS FOUND FOR THIS SLIP!\n";
    }
} else {
    echo "NO BETTING SLIPS FOUND IN THE DATABASE!\n";
}

// Close connection
$conn->close();
?> 