<?php
// This script fixes transaction types in the database

// Database connection parameters
$servername = "localhost";
$username = "root";  // Default XAMPP username
$password = "";      // Default XAMPP password (empty)
$dbname = "roulette";  // Using the roulette database

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Start transaction
$conn->begin_transaction();

try {
    // Find transactions with empty or invalid transaction_type
    $stmt = $conn->prepare("
        SELECT transaction_id, reference_id, description, amount 
        FROM transactions 
        WHERE transaction_type = '' OR transaction_type IS NULL
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $updatedCount = 0;
    $transactions = [];
    
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
    
    echo "<h1>Transaction Type Fix</h1>";
    
    if (count($transactions) > 0) {
        echo "<p>Found " . count($transactions) . " transactions with empty or invalid transaction types.</p>";
        
        // Update each transaction with the appropriate type
        foreach ($transactions as $transaction) {
            $transactionId = $transaction['transaction_id'];
            $description = $transaction['description'];
            $amount = $transaction['amount'];
            $referenceId = $transaction['reference_id'];
            
            // Determine the appropriate transaction type
            $transactionType = 'bet'; // Default
            
            if (strpos($description, 'Betting slip sold') !== false) {
                $transactionType = 'bet';
            } elseif (strpos($description, 'Refund') !== false) {
                $transactionType = 'refund';
            } elseif (strpos($description, 'Win') !== false || strpos($description, 'Payout') !== false) {
                $transactionType = 'win';
            } elseif (strpos($description, 'Voucher') !== false) {
                $transactionType = 'voucher';
            } elseif (strpos($description, 'Admin') !== false || strpos($description, 'Update') !== false) {
                $transactionType = 'admin';
            } elseif ($amount < 0) {
                $transactionType = 'bet';
            } elseif ($amount > 0) {
                $transactionType = 'win';
            }
            
            // Update the transaction
            $updateStmt = $conn->prepare("
                UPDATE transactions 
                SET transaction_type = ? 
                WHERE transaction_id = ?
            ");
            $updateStmt->bind_param("si", $transactionType, $transactionId);
            $updateStmt->execute();
            
            echo "<p>Updated transaction #$transactionId: Set type to '$transactionType' (Description: $description)</p>";
            $updatedCount++;
        }
        
        echo "<p>Successfully updated $updatedCount transactions.</p>";
    } else {
        echo "<p>No transactions with empty or invalid transaction types found.</p>";
    }
    
    // Commit transaction
    $conn->commit();
    
    echo "<p><a href='index.html'>Return to Game</a></p>";
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo "<h1>Error</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
}

// Close connection
$conn->close();
?>
