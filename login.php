<?php
// Start session
session_start();

// If already logged in, redirect to index.html
if (isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit;
}

// Database connection parameters
$servername = "localhost";
$username = "root";  // Default XAMPP username
$password = "";      // Default XAMPP password (empty)
$dbname = "roulette";  // Using the roulette database

// Check if the database exists, if not create it
$conn_check = new mysqli($servername, $username, $password);
if (!$conn_check->connect_error) {
    $result = $conn_check->query("SHOW DATABASES LIKE '$dbname'");
    if ($result->num_rows == 0) {
        // Database doesn't exist, create it
        $conn_check->query("CREATE DATABASE IF NOT EXISTS $dbname");
        log_message("Created database $dbname", 'INFO');
    }
    $conn_check->close();
}

// Create logs directory if it doesn't exist
$logDir = __DIR__ . '/logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}

// Log file for login attempts
$logFile = $logDir . '/login.log';

/**
 * Log message to file
 */
function log_message($message, $level = 'INFO') {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Process login form submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // First connect without specifying a database
        $conn = new mysqli($servername, $username, $password);

        // Check connection
        if ($conn->connect_error) {
            throw new Exception("Database connection failed: " . $conn->connect_error);
        }

        // Check if database exists, if not create it
        $result = $conn->query("SHOW DATABASES LIKE '$dbname'");
        if ($result->num_rows == 0) {
            // Database doesn't exist, create it
            if (!$conn->query("CREATE DATABASE IF NOT EXISTS $dbname")) {
                throw new Exception("Error creating database: " . $conn->error);
            }
            log_message("Created database $dbname", 'INFO');
        }

        // Select the database
        $conn->select_db($dbname);

        // Get username and password from form
        $username = isset($_POST['username']) ? $_POST['username'] : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        // Validate input
        if (strlen($username) !== 12 || !ctype_digit($username)) {
            throw new Exception("Username must be exactly 12 digits");
        } elseif (strlen($password) !== 6 || !ctype_digit($password)) {
            throw new Exception("Password must be exactly 6 digits");
        }

        // Check if users table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'users'");
        if ($tableCheck->num_rows == 0) {
            // Table doesn't exist, create it
            $createTable = "CREATE TABLE IF NOT EXISTS users (
                user_id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(12) NOT NULL UNIQUE COMMENT 'Cashier 12-digit username',
                password VARCHAR(255) NOT NULL COMMENT 'Hashed password (6-digit)',
                role VARCHAR(20) NOT NULL DEFAULT 'cashier' COMMENT 'User role (cashier, admin, etc.)',
                last_login TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

            if (!$conn->query($createTable)) {
                throw new Exception("Error creating users table: " . $conn->error);
            }

            // Create default user
            $defaultUser = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $defaultUsername = "123456789012";
            $hashedPassword = password_hash("123456", PASSWORD_DEFAULT);
            $role = "cashier";
            $defaultUser->bind_param("sss", $defaultUsername, $hashedPassword, $role);

            if (!$defaultUser->execute()) {
                throw new Exception("Error creating default user: " . $defaultUser->error);
            }

            log_message("Created users table and default user", 'INFO');
        }

        // Prepare SQL statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT user_id, username, password, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Verify password
            if (password_verify($password, $user['password'])) {
                // Password is correct, start a new session
                session_start();

                // Store data in session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                // Update last login time
                $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                $updateStmt->bind_param("i", $user['user_id']);
                $updateStmt->execute();
                $updateStmt->close();

                log_message("User logged in successfully: $username", 'INFO');

                // Redirect to index page
                header("Location: index.html");
                exit;
            } else {
                throw new Exception("Invalid password");
            }
        } else {
            throw new Exception("User not found");
        }

        $stmt->close();
        $conn->close();

    } catch (Exception $e) {
        $error = $e->getMessage();
        log_message("Login error: " . $error, 'WARNING');
    }
}

// Always return JSON for POST requests (assuming they're AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!empty($error)) {
        echo json_encode(['status' => 'error', 'message' => $error]);
    } else {
        echo json_encode(['status' => 'success', 'redirect' => 'index.html']);
    }
    exit;
}

// Otherwise, include the login HTML page
include('login.html');
?>
