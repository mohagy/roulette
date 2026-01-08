<?php
// Set error reporting to show all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// If already logged in, redirect to index.php
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Database connection parameters
$servername = "localhost";
$username = "root";  // Default XAMPP username
$password = "";      // Default XAMPP password (empty)
$dbname = "roulette";  // Using the roulette database

// Create logs directory if it doesn't exist
$logDir = __DIR__ . '/logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}

// Log file for login attempts
$logFile = $logDir . '/direct_login.log';

/**
 * Log message to file
 */
function log_message($message, $level = 'INFO') {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

$error = '';
$success = '';

// Process form submission
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
        $formUsername = isset($_POST['username']) ? $_POST['username'] : '';
        $formPassword = isset($_POST['password']) ? $_POST['password'] : '';

        // Validate input
        if (strlen($formUsername) !== 12 || !ctype_digit($formUsername)) {
            throw new Exception("Username must be exactly 12 digits");
        } elseif (strlen($formPassword) !== 6 || !ctype_digit($formPassword)) {
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
        $stmt->bind_param("s", $formUsername);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($formPassword, $user['password'])) {
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
                
                log_message("User logged in successfully: $formUsername", 'INFO');
                
                $success = "Login successful! Redirecting...";
                
                // Redirect after a short delay
                header("refresh:2;url=index.php");
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Direct Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 500px;
            margin: 0 auto;
            padding: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
        }
        input {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
        }
        button {
            padding: 10px 15px;
            background-color: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
        }
        .error {
            color: red;
            margin-bottom: 15px;
        }
        .success {
            color: green;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <h1>Direct Login Form</h1>
    <p>This form bypasses AJAX to help troubleshoot login issues.</p>
    
    <?php if (!empty($error)): ?>
        <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <form method="post" action="">
        <div class="form-group">
            <label for="username">Username (12 digits):</label>
            <input type="text" id="username" name="username" value="123456789012" maxlength="12" required>
        </div>
        
        <div class="form-group">
            <label for="password">Password (6 digits):</label>
            <input type="password" id="password" name="password" value="123456" maxlength="6" required>
        </div>
        
        <button type="submit">Login</button>
    </form>
    
    <p><a href="login.php">Back to regular login</a></p>
    <p><a href="test_login.php">Run login system test</a></p>
</body>
</html>
