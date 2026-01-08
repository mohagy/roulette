<?php
// Set error reporting to show all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

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

// Check if database exists, if not create it
$result = $conn->query("SHOW DATABASES LIKE '$dbname'");
if ($result->num_rows == 0) {
    // Database doesn't exist, create it
    if (!$conn->query("CREATE DATABASE IF NOT EXISTS $dbname")) {
        die("Error creating database: " . $conn->error);
    }
    echo "Created database $dbname<br>";
}

// Select the database
$conn->select_db($dbname);

// Check if users table exists
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    if (!$conn->query($createTable)) {
        die("Error creating users table: " . $conn->error);
    }
    
    // Create default user with initial cash balance
    $defaultUser = $conn->prepare("INSERT INTO users (username, password, role, cash_balance) VALUES (?, ?, ?, ?)");
    $defaultUsername = "123456789012";
    $hashedPassword = password_hash("123456", PASSWORD_DEFAULT);
    $role = "cashier";
    $initialBalance = 1000.00;
    $defaultUser->bind_param("sssd", $defaultUsername, $hashedPassword, $role, $initialBalance);
    
    if (!$defaultUser->execute()) {
        die("Error creating default user: " . $defaultUser->error);
    }
    
    echo "Created users table and default user with $initialBalance cash balance<br>";
} else {
    // Check if cash_balance column exists in users table
    $columnCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'cash_balance'");
    if ($columnCheck->num_rows == 0) {
        // Add cash_balance column to users table
        if (!$conn->query("ALTER TABLE users ADD COLUMN cash_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Current cash balance' AFTER role")) {
            die("Error adding cash_balance column: " . $conn->error);
        }
        echo "Added cash_balance column to users table<br>";
        
        // Update default user with initial cash balance
        if (!$conn->query("UPDATE users SET cash_balance = 1000.00 WHERE username = '123456789012'")) {
            die("Error updating default user cash balance: " . $conn->error);
        }
        echo "Updated default user with 1000.00 cash balance<br>";
    }
}

// Check if transactions table exists
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    if (!$conn->query($createTable)) {
        die("Error creating transactions table: " . $conn->error);
    }
    
    echo "Created transactions table<br>";
}

// Close connection
$conn->close();

// Success message
echo '<div style="background-color: #d4edda; color: #155724; padding: 15px; margin-top: 20px; border-radius: 5px;">
    <h3>Cash System Setup Complete</h3>
    <p>The cash system has been successfully set up. You can now use the cash balance feature.</p>
    <p><a href="index.html" style="color: #155724; text-decoration: underline;">Return to Game</a></p>
</div>';
?>
