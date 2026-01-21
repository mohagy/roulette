<?php
/**
 * Auto Fix Transactions Script
 *
 * This script automatically ensures that:
 * 1. All betting slips have corresponding transactions
 * 2. The cash balance is correctly updated based on all transactions
 * 3. Any missing transactions are created
 *
 * It can be run manually or automatically via a cron job
 */

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
    // Step 1: Find all betting slips without corresponding transactions
    // Use CAST to ensure consistent collation
    $stmt = $conn->prepare("
        SELECT bs.slip_id, bs.slip_number, bs.player_id, bs.total_stake, bs.created_at
        FROM betting_slips bs
        WHERE CAST(bs.slip_number AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
            NOT IN (SELECT CAST(reference_id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
                    FROM transactions
                    WHERE reference_id IS NOT NULL)
        ORDER BY bs.created_at DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();

    $missingSlips = [];
    while ($row = $result->fetch_assoc()) {
        $missingSlips[] = $row;
    }

    echo "<h1>Auto Fix Transactions</h1>";

    // Step 2: Create missing transactions
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
        $stmt = $conn->prepare("UPDATE users SET cash_balance = ?, updated_at = NOW() WHERE user_id = 1");
        $stmt->bind_param("d", $currentBalance);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update cash balance: " . $stmt->error);
        }

        echo "<p>Successfully created $transactionsCreated transactions.</p>";
        echo "<p>Total deduction: $totalDeduction</p>";
        echo "<p>New cash balance: $currentBalance</p>";
    } else {
        echo "<p>No missing transactions found. All betting slips have corresponding transactions.</p>";
    }

    // Step 3: Verify that the cash balance matches the sum of all transactions
    $stmt = $conn->prepare("
        SELECT SUM(amount) as total_amount
        FROM transactions
        WHERE user_id = 1
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $totalTransactionAmount = floatval($row['total_amount'] ?? 0);

    // Get the current cash balance
    $stmt = $conn->prepare("SELECT cash_balance FROM users WHERE user_id = 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $currentBalance = floatval($row['cash_balance']);

    // Calculate what the balance should be (starting from initial balance)
    $initialBalance = 100000.00; // Default initial balance
    $calculatedBalance = $initialBalance + $totalTransactionAmount;

    echo "<h2>Cash Balance Verification</h2>";
    echo "<p>Initial balance: $initialBalance</p>";
    echo "<p>Sum of all transactions: $totalTransactionAmount</p>";
    echo "<p>Calculated balance: $calculatedBalance</p>";
    echo "<p>Current balance in database: $currentBalance</p>";

    // If there's a discrepancy, fix it
    if (abs($calculatedBalance - $currentBalance) > 0.01) { // Using small epsilon for float comparison
        echo "<p>Discrepancy detected. Fixing cash balance...</p>";

        // Update the cash balance
        $stmt = $conn->prepare("UPDATE users SET cash_balance = ?, updated_at = NOW() WHERE user_id = 1");
        $stmt->bind_param("d", $calculatedBalance);
        if (!$stmt->execute()) {
            throw new Exception("Failed to fix cash balance: " . $stmt->error);
        }

        // Record an adjustment transaction
        $adjustmentAmount = $calculatedBalance - $currentBalance;
        $stmt = $conn->prepare("
            INSERT INTO transactions
            (user_id, amount, balance_after, transaction_type, reference_id, description)
            VALUES (?, ?, ?, 'admin', 'ADJUSTMENT', 'Balance adjustment to match transaction history')
        ");
        $stmt->bind_param("idd", $userId, $adjustmentAmount, $calculatedBalance);
        if (!$stmt->execute()) {
            throw new Exception("Failed to record adjustment transaction: " . $stmt->error);
        }

        echo "<p>Cash balance fixed. New balance: $calculatedBalance</p>";
    } else {
        echo "<p>Cash balance is correct.</p>";
    }

    // Commit transaction
    $conn->commit();

    echo "<p style='color: green; font-weight: bold;'>All fixes applied successfully.</p>";
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
