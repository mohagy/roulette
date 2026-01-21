<?php
// This script creates missing transactions for existing betting slips

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
    // Get all betting slips that don't have corresponding transactions
    $stmt = $conn->prepare("
        SELECT bs.slip_id, bs.slip_number, bs.player_id, bs.total_stake, bs.created_at
        FROM betting_slips bs
        WHERE bs.slip_number NOT IN (SELECT reference_id FROM transactions WHERE reference_id IS NOT NULL)
        ORDER BY bs.created_at DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();

    $missingSlips = [];
    while ($row = $result->fetch_assoc()) {
        $missingSlips[] = $row;
    }

    echo "<h1>Creating Missing Transactions</h1>";

    if (count($missingSlips) > 0) {
        echo "<p>Found " . count($missingSlips) . " betting slips without transactions.</p>";

        // Get current cash balance
        $stmt = $conn->prepare("SELECT cash_balance FROM users WHERE user_id = 1 FOR UPDATE");
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $currentBalance = floatval($row['cash_balance']);

        echo "<p>Current cash balance: $currentBalance</p>";

        $totalDeduction = 0;
        $transactionsCreated = 0;

        // Create transactions for each missing slip
        foreach ($missingSlips as $slip) {
            $slipId = $slip['slip_id'];
            $slipNumber = $slip['slip_number'];
            $betAmount = floatval($slip['total_stake']);
            $userId = 1; // Default cashier

            // Calculate new balance
            $newBalance = $currentBalance - $betAmount;
            $currentBalance = $newBalance; // Update for next iteration
            $totalDeduction += $betAmount;

            // Create transaction
            $stmt = $conn->prepare("
                INSERT INTO transactions
                (user_id, amount, balance_after, transaction_type, reference_id, description)
                VALUES (?, ?, ?, 'bet', ?, ?)
            ");
            $negativeAmount = -$betAmount; // Make the amount negative for deductions
            $description = "Betting slip sold #" . $slipNumber;
            $stmt->bind_param("idsss", $userId, $negativeAmount, $newBalance, $slipNumber, $description);

            if ($stmt->execute()) {
                $transactionId = $conn->insert_id;
                echo "<p>Created transaction #$transactionId for slip #$slipNumber: -$betAmount</p>";
                $transactionsCreated++;
            } else {
                throw new Exception("Failed to create transaction for slip #$slipNumber: " . $stmt->error);
            }
        }

        // Update user's cash balance
        $stmt = $conn->prepare("UPDATE users SET cash_balance = ? WHERE user_id = 1");
        $stmt->bind_param("d", $currentBalance);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update cash balance: " . $stmt->error);
        }

        echo "<p>Successfully created $transactionsCreated transactions.</p>";
        echo "<p>Total deduction: $totalDeduction</p>";
        echo "<p>New cash balance: $currentBalance</p>";

        // Commit transaction
        $conn->commit();

        echo "<p style='color: green; font-weight: bold;'>All changes committed to database.</p>";
    } else {
        echo "<p>No missing transactions found. All betting slips have corresponding transactions.</p>";
    }

    echo "<p><a href='index.html'>Return to Game</a></p>";

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo "<h1>Error</h1>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
    echo "<p><a href='index.html'>Return to Game</a></p>";
}

// Close connection
$conn->close();
?>
