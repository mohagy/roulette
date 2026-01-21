<?php
// Set error reporting to show all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Fix Tool</h1>";

// Database connection parameters
$servername = "localhost";
$username = "root";  // Default XAMPP username
$password = "";      // Default XAMPP password (empty)
$dbname = "roulette";  // Using the roulette database

echo "<h2>Step 1: Testing MySQL Connection</h2>";

// First connect without specifying a database
try {
    $conn = new mysqli($servername, $username, $password);

    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    echo "<p style='color:green'>✓ Connected to MySQL server successfully.</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>✗ " . $e->getMessage() . "</p>";
    echo "<p>Possible solutions:</p>";
    echo "<ul>";
    echo "<li>Make sure XAMPP is running and MySQL service is started</li>";
    echo "<li>Check if the MySQL port (usually 3306) is not blocked by a firewall</li>";
    echo "<li>Verify that the MySQL username and password are correct</li>";
    echo "</ul>";
    die("<p>Please fix these issues and try again.</p>");
}

echo "<h2>Step 2: Checking/Creating Database</h2>";

// Check if database exists
$result = $conn->query("SHOW DATABASES LIKE '$dbname'");
if ($result->num_rows == 0) {
    echo "<p>Database '$dbname' does not exist. Creating it now...</p>";

    // Create database
    if ($conn->query("CREATE DATABASE IF NOT EXISTS $dbname")) {
        echo "<p style='color:green'>✓ Database created successfully.</p>";
    } else {
        echo "<p style='color:red'>✗ Error creating database: " . $conn->error . "</p>";
        die("<p>Please fix this issue and try again.</p>");
    }
} else {
    echo "<p style='color:green'>✓ Database '$dbname' exists.</p>";
}

// Select the database
$conn->select_db($dbname);

echo "<h2>Step 3: Checking/Creating Users Table</h2>";

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
        echo "<p style='color:green'>✓ Table 'users' created successfully.</p>";
    } else {
        echo "<p style='color:red'>✗ Error creating table: " . $conn->error . "</p>";
        die("<p>Please fix this issue and try again.</p>");
    }
} else {
    echo "<p style='color:green'>✓ Table 'users' exists.</p>";
}

echo "<h2>Step 4: Checking/Creating Default User</h2>";

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
        echo "<p style='color:green'>✓ User created successfully with password: $defaultPassword</p>";
    } else {
        echo "<p style='color:red'>✗ Error creating user: " . $insertStmt->error . "</p>";
    }

    $insertStmt->close();
} else {
    // User exists, reset password
    echo "<p>User '$defaultUsername' exists. Resetting password...</p>";

    $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
    $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
    $updateStmt->bind_param("ss", $hashedPassword, $defaultUsername);

    if ($updateStmt->execute()) {
        echo "<p style='color:green'>✓ Password reset successfully to: $defaultPassword</p>";
    } else {
        echo "<p style='color:red'>✗ Error resetting password: " . $updateStmt->error . "</p>";
    }

    $updateStmt->close();
}

$stmt->close();

echo "<h2>Step 5: Testing Login</h2>";

// Test login with default credentials
$testUsername = "123456789012";
$testPassword = "123456";

$stmt = $conn->prepare("SELECT user_id, username, password, role FROM users WHERE username = ?");
$stmt->bind_param("s", $testUsername);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    // Verify password
    if (password_verify($testPassword, $user['password'])) {
        echo "<p style='color:green'>✓ Login test successful with default credentials!</p>";
    } else {
        echo "<p style='color:red'>✗ Login test failed: Invalid password.</p>";
    }
} else {
    echo "<p style='color:red'>✗ Login test failed: User not found.</p>";
}

$stmt->close();
$conn->close();

echo "<h2>Next Steps</h2>";
echo "<p>All database issues should now be fixed. Try logging in using one of these options:</p>";
echo "<div style='display: flex; gap: 10px; margin-bottom: 20px;'>";
echo "<a href='simple_login.php' style='padding: 10px 15px; background: #457b9d; color: white; text-decoration: none; border-radius: 5px;'>Simple Login (No AJAX)</a>";
echo "<a href='login.php' style='padding: 10px 15px; background: #1d3557; color: white; text-decoration: none; border-radius: 5px;'>Regular Login</a>";
echo "<a href='setup_login.php' style='padding: 10px 15px; background: #2a9d8f; color: white; text-decoration: none; border-radius: 5px;'>Setup & Troubleshooting</a>";
echo "</div>";

echo "<p>Login credentials:</p>";
echo "<ul>";
echo "<li><strong>Username:</strong> 123456789012</li>";
echo "<li><strong>Password:</strong> 123456</li>";
echo "</ul>";
?>
