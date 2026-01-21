<?php
/**
 * Check existing users in the database
 * This script shows usernames and roles (passwords are hashed and cannot be retrieved)
 */
require_once 'includes/db_connection.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>User Credentials Check</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .user-card {
            background: white;
            padding: 20px;
            margin: 10px 0;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .username {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        .role {
            color: #666;
            margin-top: 5px;
        }
        .default-password {
            background: #fff3cd;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            border-left: 4px solid #ffc107;
        }
        .info {
            background: #d1ecf1;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #0c5460;
        }
    </style>
</head>
<body>
    <h1>🔐 User Credentials</h1>
    
    <?php
    try {
        // Check if users table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'users'");
        
        if ($tableCheck->num_rows == 0) {
            echo '<div class="info">';
            echo '<strong>⚠️ Users table does not exist yet.</strong><br>';
            echo 'The table will be created automatically when you first log in.';
            echo '</div>';
        } else {
            // Get all users
            $result = $conn->query("SELECT user_id, username, role, last_login, created_at FROM users ORDER BY user_id");
            
            if ($result->num_rows > 0) {
                echo '<div class="info">';
                echo '<strong>ℹ️ Note:</strong> Passwords are stored as hashed values and cannot be retrieved. ';
                echo 'If you forgot your password, you can reset it in the database or create a new user.';
                echo '</div>';
                
                echo '<h2>Existing Users (' . $result->num_rows . '):</h2>';
                
                while ($user = $result->fetch_assoc()) {
                    echo '<div class="user-card">';
                    echo '<div class="username">👤 Username: ' . htmlspecialchars($user['username']) . '</div>';
                    echo '<div class="role">Role: ' . htmlspecialchars($user['role']) . '</div>';
                    echo '<div class="role">User ID: ' . $user['user_id'] . '</div>';
                    if ($user['last_login']) {
                        echo '<div class="role">Last Login: ' . $user['last_login'] . '</div>';
                    }
                    echo '<div class="role">Created: ' . $user['created_at'] . '</div>';
                    
                    // Show default password hint for default user
                    if ($user['username'] === '123456789012') {
                        echo '<div class="default-password">';
                        echo '<strong>🔑 Default Password:</strong> <code>123456</code><br>';
                        echo '<small>This is the default password for the initial user. Change it after first login!</small>';
                        echo '</div>';
                    }
                    
                    echo '</div>';
                }
            } else {
                echo '<div class="info">';
                echo '<strong>⚠️ No users found in the database.</strong><br>';
                echo 'The default user will be created automatically when you first log in:<br>';
                echo '<strong>Username:</strong> 123456789012<br>';
                echo '<strong>Password:</strong> 123456<br>';
                echo '<strong>Role:</strong> cashier';
                echo '</div>';
            }
        }
    } catch (Exception $e) {
        echo '<div style="background: #f8d7da; padding: 15px; border-radius: 5px; color: #721c24;">';
        echo '<strong>❌ Error:</strong> ' . htmlspecialchars($e->getMessage());
        echo '</div>';
    }
    ?>
    
    <hr>
    <h2>📝 How to Login:</h2>
    <ol>
        <li><strong>Main Admin Login:</strong> <a href="login.php">http://localhost/slipp/login.php</a></li>
        <li><strong>TV Display Login:</strong> <a href="tvdisplay/login.php">http://localhost/slipp/tvdisplay/login.php</a></li>
    </ol>
    
    <h2>🔧 Reset Password (if needed):</h2>
    <p>If you need to reset a password, you can run this SQL in phpMyAdmin:</p>
    <pre style="background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto;">
UPDATE users 
SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' 
WHERE username = 'YOUR_USERNAME';
-- This sets password to: password123
-- Or use: password_hash('your_new_password', PASSWORD_DEFAULT) in PHP
    </pre>
</body>
</html>

