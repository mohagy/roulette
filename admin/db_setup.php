<?php
// Set error reporting to show all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start HTML output
echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - Roulette POS</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fc;
            padding: 20px;
            font-family: "Nunito", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            padding: 20px;
        }
        h1 {
            color: #4e73df;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e3e6f0;
        }
        .setup-log {
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f9fc;
            border-radius: 5px;
            border: 1px solid #e3e6f0;
            max-height: 400px;
            overflow-y: auto;
        }
        .setup-log div {
            margin: 5px 0;
            padding: 5px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-database"></i> Database Setup</h1>
        <p>This script will check for and create all required database tables for the Roulette POS system.</p>
        <div class="setup-log">';

// Function to log setup messages
function logSetup($message, $isError = false) {
    echo $isError ? "<div style='color: red;'>❌ $message</div>" : "<div style='color: green;'>✅ $message</div>";
}

// Database connection parameters
$servername = "localhost";
$username = "root";  // Default XAMPP username
$password = "";      // Default XAMPP password (empty)
$dbname = "roulette";  // Using the roulette database

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to log setup messages is defined above

// Check if database exists, if not create it
$result = $conn->query("SHOW DATABASES LIKE '$dbname'");
if ($result->num_rows == 0) {
    // Database doesn't exist, create it
    if (!$conn->query("CREATE DATABASE IF NOT EXISTS $dbname")) {
        logSetup("Error creating database: " . $conn->error, true);
        die();
    }
    logSetup("Created database '$dbname'");
} else {
    logSetup("Database '$dbname' already exists");
}

// Select the database
$conn->select_db($dbname);

// Check and create users table
$tableCheck = $conn->query("SHOW TABLES LIKE 'users'");
if ($tableCheck->num_rows == 0) {
    // Table doesn't exist, create it
    $createTable = "CREATE TABLE IF NOT EXISTS users (
        user_id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(12) NOT NULL UNIQUE COMMENT 'Cashier 12-digit username',
        password VARCHAR(255) NOT NULL COMMENT 'Hashed password (6-digit)',
        role VARCHAR(20) NOT NULL DEFAULT 'cashier' COMMENT 'User role (cashier, admin, etc.)',
        cash_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Current cash balance',
        last_login TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    if (!$conn->query($createTable)) {
        logSetup("Error creating users table: " . $conn->error, true);
    } else {
        logSetup("Created users table");

        // Create default admin user
        $adminUser = $conn->prepare("INSERT INTO users (username, password, role, cash_balance) VALUES (?, ?, ?, ?)");
        $adminUsername = "000000000000";
        $hashedPassword = password_hash("000000", PASSWORD_DEFAULT);
        $role = "admin";
        $initialBalance = 5000.00;
        $adminUser->bind_param("sssd", $adminUsername, $hashedPassword, $role, $initialBalance);

        if (!$adminUser->execute()) {
            logSetup("Error creating admin user: " . $adminUser->error, true);
        } else {
            logSetup("Created admin user (Username: 000000000000, Password: 000000)");
        }

        // Create default cashier user
        $cashierUser = $conn->prepare("INSERT INTO users (username, password, role, cash_balance) VALUES (?, ?, ?, ?)");
        $cashierUsername = "123456789012";
        $hashedPassword = password_hash("123456", PASSWORD_DEFAULT);
        $role = "cashier";
        $initialBalance = 1000.00;
        $cashierUser->bind_param("sssd", $cashierUsername, $hashedPassword, $role, $initialBalance);

        if (!$cashierUser->execute()) {
            logSetup("Error creating cashier user: " . $cashierUser->error, true);
        } else {
            logSetup("Created cashier user (Username: 123456789012, Password: 123456)");
        }
    }
} else {
    logSetup("Users table already exists");

    // Check if cash_balance column exists
    $columnCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'cash_balance'");
    if ($columnCheck->num_rows == 0) {
        // Add cash_balance column
        if (!$conn->query("ALTER TABLE users ADD COLUMN cash_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER role")) {
            logSetup("Error adding cash_balance column: " . $conn->error, true);
        } else {
            logSetup("Added cash_balance column to users table");
        }
    }
}

// Check and create transactions table
$tableCheck = $conn->query("SHOW TABLES LIKE 'transactions'");
if ($tableCheck->num_rows == 0) {
    // Table doesn't exist, create it
    $createTable = "CREATE TABLE IF NOT EXISTS transactions (
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

    if (!$conn->query($createTable)) {
        logSetup("Error creating transactions table: " . $conn->error, true);
    } else {
        logSetup("Created transactions table");
    }
} else {
    logSetup("Transactions table already exists");
}

// Check and create vouchers table
$tableCheck = $conn->query("SHOW TABLES LIKE 'vouchers'");
if ($tableCheck->num_rows == 0) {
    // Table doesn't exist, create it
    $createTable = "CREATE TABLE IF NOT EXISTS vouchers (
        voucher_id INT AUTO_INCREMENT PRIMARY KEY,
        voucher_code VARCHAR(20) NOT NULL UNIQUE,
        amount DECIMAL(10,2) NOT NULL,
        is_used TINYINT(1) NOT NULL DEFAULT 0,
        used_by INT NULL,
        used_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (used_by) REFERENCES users(user_id)
    )";

    if (!$conn->query($createTable)) {
        logSetup("Error creating vouchers table: " . $conn->error, true);
    } else {
        logSetup("Created vouchers table");

        // Create sample vouchers
        $sampleVouchers = [
            ['code' => 'BONUS100', 'amount' => 100.00],
            ['code' => 'BONUS200', 'amount' => 200.00],
            ['code' => 'BONUS500', 'amount' => 500.00],
            ['code' => 'WELCOME1000', 'amount' => 1000.00]
        ];

        $stmt = $conn->prepare("INSERT INTO vouchers (voucher_code, amount) VALUES (?, ?)");
        foreach ($sampleVouchers as $voucher) {
            $stmt->bind_param("sd", $voucher['code'], $voucher['amount']);
            if (!$stmt->execute()) {
                logSetup("Error creating voucher {$voucher['code']}: " . $stmt->error, true);
            } else {
                logSetup("Created voucher {$voucher['code']} worth \${$voucher['amount']}");
            }
        }
    }
} else {
    logSetup("Vouchers table already exists");
}

// Check and create commission table
$tableCheck = $conn->query("SHOW TABLES LIKE 'commission'");
if ($tableCheck->num_rows == 0) {
    // Table doesn't exist, create it
    $createTable = "CREATE TABLE IF NOT EXISTS commission (
        commission_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        bet_amount DECIMAL(10,2) NOT NULL,
        commission_amount DECIMAL(10,2) NOT NULL,
        date_created DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id)
    )";

    if (!$conn->query($createTable)) {
        logSetup("Error creating commission table: " . $conn->error, true);
    } else {
        logSetup("Created commission table");
    }
} else {
    logSetup("Commission table already exists");
}

// Check and create commission_summary table
$tableCheck = $conn->query("SHOW TABLES LIKE 'commission_summary'");
if ($tableCheck->num_rows == 0) {
    // Table doesn't exist, create it
    $createTable = "CREATE TABLE IF NOT EXISTS commission_summary (
        summary_id INT AUTO_INCREMENT PRIMARY KEY,
        date_created DATE NOT NULL UNIQUE,
        total_bets DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        total_commission DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    if (!$conn->query($createTable)) {
        logSetup("Error creating commission_summary table: " . $conn->error, true);
    } else {
        logSetup("Created commission_summary table");
    }
} else {
    logSetup("Commission_summary table already exists");
}

// Check and create settings table
$tableCheck = $conn->query("SHOW TABLES LIKE 'settings'");
if ($tableCheck->num_rows == 0) {
    // Table doesn't exist, create it
    $createTable = "CREATE TABLE IF NOT EXISTS settings (
        setting_id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(50) NOT NULL UNIQUE,
        setting_value TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    if (!$conn->query($createTable)) {
        logSetup("Error creating settings table: " . $conn->error, true);
    } else {
        logSetup("Created settings table");

        // Insert default settings
        $defaultSettings = [
            ['key' => 'commission_rate', 'value' => '4'],
            ['key' => 'max_bet', 'value' => '1000'],
            ['key' => 'min_bet', 'value' => '5']
        ];

        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
        foreach ($defaultSettings as $setting) {
            $stmt->bind_param("ss", $setting['key'], $setting['value']);
            if (!$stmt->execute()) {
                logSetup("Error creating setting {$setting['key']}: " . $stmt->error, true);
            } else {
                logSetup("Created setting {$setting['key']} with value {$setting['value']}");
            }
        }
    }
} else {
    logSetup("Settings table already exists");
}

// Check and create roulette_state table
$tableCheck = $conn->query("SHOW TABLES LIKE 'roulette_state'");
if ($tableCheck->num_rows == 0) {
    // Table doesn't exist, create it
    $createTable = "CREATE TABLE IF NOT EXISTS roulette_state (
        id INT AUTO_INCREMENT PRIMARY KEY,
        roll_history TEXT,
        roll_colors TEXT,
        last_draw VARCHAR(10),
        next_draw VARCHAR(10),
        countdown_time INT DEFAULT 120,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    if (!$conn->query($createTable)) {
        logSetup("Error creating roulette_state table: " . $conn->error, true);
    } else {
        logSetup("Created roulette_state table");

        // Insert initial record
        $insertSql = "INSERT INTO roulette_state (id, roll_history, roll_colors, last_draw, next_draw, countdown_time)
                      VALUES (1, '[]', '[]', '#0', '#1', 120)";
        if (!$conn->query($insertSql)) {
            logSetup("Error inserting initial record into roulette_state: " . $conn->error, true);
        } else {
            logSetup("Inserted initial record into roulette_state table");
        }
    }
} else {
    logSetup("Roulette_state table already exists");
}

// Create trigger to update commission_summary
$triggerCheck = $conn->query("SHOW TRIGGERS LIKE 'update_commission_summary'");
if ($triggerCheck->num_rows == 0) {
    $createTrigger = "
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

    if (!$conn->query($createTrigger)) {
        logSetup("Error creating update_commission_summary trigger: " . $conn->error, true);
    } else {
        logSetup("Created update_commission_summary trigger");
    }
} else {
    logSetup("Trigger update_commission_summary already exists");
}

// Close connection
$conn->close();

// Close the setup log div
echo '</div>';

// Success message
echo '<div class="alert alert-success mt-4">
    <h4><i class="fas fa-check-circle"></i> Database Setup Complete</h4>
    <p>All required tables have been created or verified.</p>
    <div class="mt-3">
        <a href="index.php" class="btn btn-primary"><i class="fas fa-tachometer-alt"></i> Go to Admin Dashboard</a>
        <a href="../index.html" class="btn btn-outline-primary ml-2"><i class="fas fa-gamepad"></i> Go to Game</a>
    </div>
</div>';

// Close the container and HTML
echo '</div>
</body>
</html>';
?>
