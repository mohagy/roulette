<?php
// Set error reporting to show all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Reset Default User Password</h1>";

// Database connection parameters
$servername = "localhost";
$username = "root";  // Default XAMPP username
$password = "";      // Default XAMPP password (empty)
$dbname = "roulette";  // Using the roulette database

// First connect without specifying a database
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("<p style='color:red'>Connection failed: " . $conn->connect_error . "</p>");
}

echo "<p style='color:green'>Connected to MySQL server successfully.</p>";

// Check if database exists
$result = $conn->query("SHOW DATABASES LIKE '$dbname'");
if ($result->num_rows == 0) {
    echo "<p>Database '$dbname' does not exist. Creating it now...</p>";
    
    // Create database
    if ($conn->query("CREATE DATABASE IF NOT EXISTS $dbname")) {
        echo "<p style='color:green'>Database created successfully.</p>";
    } else {
        die("<p style='color:red'>Error creating database: " . $conn->error . "</p>");
    }
} else {
    echo "<p style='color:green'>Database '$dbname' exists.</p>";
}

// Select the database
$conn->select_db($dbname);

// Check if users table exists
$result = $conn->query("SHOW TABLES LIKE 'users'");
if ($result->num_rows == 0) {
    echo "<p>Table 'users' does not exist. Creating it now...</p>";
    
    // Create users table
    $sql = "CREATE TABLE IF NOT EXISTS users (
        user_id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(12) NOT NULL UNIQUE COMMENT 'Cashier 12-digit username',
        password VARCHAR(255) NOT NULL COMMENT 'Hashed password (6-digit)',
        role VARCHAR(20) NOT NULL DEFAULT 'cashier' COMMENT 'User role (cashier, admin, etc.)',
        last_login TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    if ($conn->query($sql)) {
        echo "<p style='color:green'>Table 'users' created successfully.</p>";
    } else {
        die("<p style='color:red'>Error creating table: " . $conn->error . "</p>");
    }
}

// Default username and password
$defaultUsername = "123456789012";
$defaultPassword = "123456";

// Check if user exists
$stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
$stmt->bind_param("s", $defaultUsername);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    // User doesn't exist, create it
    echo "<p>User '$defaultUsername' does not exist. Creating it now...</p>";
    
    $insertStmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
    $role = "cashier";
    $insertStmt->bind_param("sss", $defaultUsername, $hashedPassword, $role);
    
    if ($insertStmt->execute()) {
        echo "<p style='color:green'>User created successfully with password: $defaultPassword</p>";
    } else {
        echo "<p style='color:red'>Error creating user: " . $insertStmt->error . "</p>";
    }
    
    $insertStmt->close();
} else {
    // User exists, reset password
    echo "<p>User '$defaultUsername' exists. Resetting password...</p>";
    
    $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
    $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
    $updateStmt->bind_param("ss", $hashedPassword, $defaultUsername);
    
    if ($updateStmt->execute()) {
        echo "<p style='color:green'>Password reset successfully to: $defaultPassword</p>";
    } else {
        echo "<p style='color:red'>Error resetting password: " . $updateStmt->error . "</p>";
    }
    
    $updateStmt->close();
}

$stmt->close();
$conn->close();

echo "<p>You can now try to <a href='login.php'>login</a> with:</p>";
echo "<ul>";
echo "<li>Username: $defaultUsername</li>";
echo "<li>Password: $defaultPassword</li>";
echo "</ul>";

echo "<p>Or try the <a href='direct_login.php'>direct login</a> page which bypasses AJAX.</p>";
?>
