<?php
// Set error reporting to show all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection parameters
$host = 'localhost';
$username = 'root';
$password = '';
$charset = 'utf8mb4';
$dbname = 'roulette';

echo "<h1>Roulette System Setup</h1>";
echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto;'>";

try {
    // Connect to the server without specifying a database
    $pdo = new PDO("mysql:host=$host;charset=$charset", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create the database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<p>✅ Database <strong>$dbname</strong> created or already exists.</p>";

    // Select the database
    $pdo->exec("USE $dbname");

    // Create the roulette_state table
    $sql = "CREATE TABLE IF NOT EXISTS roulette_state (
        id INT AUTO_INCREMENT PRIMARY KEY,
        roll_history TEXT,
        roll_colors TEXT,
        last_draw VARCHAR(10),
        next_draw VARCHAR(10),
        countdown_time INT DEFAULT 120,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    $pdo->exec($sql);
    echo "<p>✅ Table <strong>roulette_state</strong> created or already exists.</p>";

    // Check if there's already a record in roulette_state
    $stmt = $pdo->query("SELECT COUNT(*) FROM roulette_state");
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        // Insert initial record
        $insertSql = "INSERT INTO roulette_state (id, roll_history, roll_colors, last_draw, next_draw, countdown_time)
                      VALUES (1, '[]', '[]', '#0', '#1', 120)";
        $pdo->exec($insertSql);
        echo "<p>✅ Initial record inserted into roulette_state.</p>";
    } else {
        echo "<p>✅ Record already exists in roulette_state.</p>";
    }

    // Create users table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS users (
        user_id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(12) NOT NULL UNIQUE COMMENT 'Cashier 12-digit username',
        password VARCHAR(255) NOT NULL COMMENT 'Hashed password (6-digit)',
        role VARCHAR(20) NOT NULL DEFAULT 'cashier' COMMENT 'User role (cashier, admin, etc.)',
        cash_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Current cash balance',
        last_login TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    $pdo->exec($sql);
    echo "<p>✅ Table <strong>users</strong> created or already exists.</p>";

    // Check if there are any users
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        // Create default admin user
        $adminUsername = "000000000000";
        $adminPassword = password_hash("000000", PASSWORD_DEFAULT);
        $adminRole = "admin";
        $adminBalance = 5000.00;

        $insertSql = "INSERT INTO users (username, password, role, cash_balance)
                      VALUES (:username, :password, :role, :balance)";
        $stmt = $pdo->prepare($insertSql);
        $stmt->execute([
            ':username' => $adminUsername,
            ':password' => $adminPassword,
            ':role' => $adminRole,
            ':balance' => $adminBalance
        ]);
        echo "<p>✅ Created admin user (Username: 000000000000, Password: 000000)</p>";

        // Create default cashier user
        $cashierUsername = "123456789012";
        $cashierPassword = password_hash("123456", PASSWORD_DEFAULT);
        $cashierRole = "cashier";
        $cashierBalance = 1000.00;

        $stmt = $pdo->prepare($insertSql);
        $stmt->execute([
            ':username' => $cashierUsername,
            ':password' => $cashierPassword,
            ':role' => $cashierRole,
            ':balance' => $cashierBalance
        ]);
        echo "<p>✅ Created cashier user (Username: 123456789012, Password: 123456)</p>";
    } else {
        echo "<p>✅ Users already exist in the database.</p>";
    }

    // Create transactions table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS transactions (
        transaction_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL COMMENT 'Positive for credits, negative for debits',
        balance_after DECIMAL(10,2) NOT NULL COMMENT 'Balance after transaction',
        transaction_type ENUM('bet', 'win', 'voucher', 'admin', 'refund') NOT NULL,
        reference_id VARCHAR(50) NULL COMMENT 'Reference to related entity (bet_id, voucher_id, etc.)',
        description TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id)
    )";

    $pdo->exec($sql);
    echo "<p>✅ Table <strong>transactions</strong> created or already exists.</p>";

    // Create vouchers table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS vouchers (
        voucher_id INT AUTO_INCREMENT PRIMARY KEY,
        voucher_code VARCHAR(20) NOT NULL UNIQUE,
        amount DECIMAL(10,2) NOT NULL,
        is_used TINYINT(1) NOT NULL DEFAULT 0,
        used_by INT NULL,
        used_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (used_by) REFERENCES users(user_id)
    )";

    $pdo->exec($sql);
    echo "<p>✅ Table <strong>vouchers</strong> created or already exists.</p>";

    // Check if there are any vouchers
    $stmt = $pdo->query("SELECT COUNT(*) FROM vouchers");
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        // Create sample vouchers
        $sampleVouchers = [
            ['code' => 'BONUS100', 'amount' => 100.00],
            ['code' => 'BONUS200', 'amount' => 200.00],
            ['code' => 'BONUS500', 'amount' => 500.00],
            ['code' => 'WELCOME1000', 'amount' => 1000.00]
        ];

        $insertSql = "INSERT INTO vouchers (voucher_code, amount) VALUES (:code, :amount)";
        $stmt = $pdo->prepare($insertSql);

        foreach ($sampleVouchers as $voucher) {
            $stmt->execute([
                ':code' => $voucher['code'],
                ':amount' => $voucher['amount']
            ]);
            echo "<p>✅ Created voucher <strong>{$voucher['code']}</strong> worth \${$voucher['amount']}</p>";
        }
    } else {
        echo "<p>✅ Vouchers already exist in the database.</p>";
    }

    // Create commission table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS commission (
        commission_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        bet_amount DECIMAL(10,2) NOT NULL,
        commission_amount DECIMAL(10,2) NOT NULL,
        date_created DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id)
    )";

    $pdo->exec($sql);
    echo "<p>✅ Table <strong>commission</strong> created or already exists.</p>";

    // Create commission_summary table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS commission_summary (
        summary_id INT AUTO_INCREMENT PRIMARY KEY,
        date_created DATE NOT NULL UNIQUE,
        total_bets DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        total_commission DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    $pdo->exec($sql);
    echo "<p>✅ Table <strong>commission_summary</strong> created or already exists.</p>";

    // Check if the trigger exists
    $stmt = $pdo->query("SHOW TRIGGERS LIKE 'update_commission_summary'");
    $triggerExists = $stmt->rowCount() > 0;

    if (!$triggerExists) {
        // Create trigger to update commission_summary when a new commission is added
        $sql = "
        CREATE TRIGGER update_commission_summary
        AFTER INSERT ON commission
        FOR EACH ROW
        BEGIN
            INSERT INTO commission_summary (date_created, total_bets, total_commission)
            VALUES (NEW.date_created, NEW.bet_amount, NEW.commission_amount)
            ON DUPLICATE KEY UPDATE
            total_bets = total_bets + NEW.bet_amount,
            total_commission = total_commission + NEW.commission_amount;
        END;
        ";

        $pdo->exec($sql);
        echo "<p>✅ Created <strong>update_commission_summary</strong> trigger.</p>";
    } else {
        echo "<p>✅ Trigger <strong>update_commission_summary</strong> already exists.</p>";
    }

    echo "<div style='margin-top: 30px; padding: 20px; background-color: #d4edda; color: #155724; border-radius: 5px;'>
        <h2>Setup Complete</h2>
        <p>The system has been successfully set up. You can now use the following features:</p>
        <ul>
            <li>Cash balance management</li>
            <li>Transaction tracking</li>
            <li>Voucher redemption</li>
            <li>Commission tracking</li>
        </ul>
        <p><strong>Admin User:</strong> 000000000000 / 000000</p>
        <p><strong>Cashier User:</strong> 123456789012 / 123456</p>
        <p><a href='index.html' style='color: #155724; text-decoration: underline;'>Go to Game</a> |
           <a href='login.html' style='color: #155724; text-decoration: underline;'>Go to Login</a></p>
    </div>";

} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "</div>";
?>