<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // User is not logged in, redirect to login page
    header("Location: simple_login.php");
    exit;
}

// Get user information
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Handle logout
if (isset($_GET['logout'])) {
    // Destroy the session
    session_destroy();
    
    // Redirect to login page
    header("Location: simple_login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Roulette POS - Dashboard</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background: #f5f5f5;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .header {
            background: linear-gradient(135deg, #1d3557, #457b9d);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            display: flex;
            align-items: center;
        }
        .logo span {
            margin-left: 10px;
        }
        .user-info {
            display: flex;
            align-items: center;
        }
        .user-name {
            margin-right: 20px;
        }
        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
        }
        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .dashboard {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-top: 20px;
        }
        h1 {
            color: #1d3557;
            margin-top: 0;
        }
        .card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        .card h2 {
            color: #457b9d;
            margin-top: 0;
        }
        .button {
            background: #457b9d;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
            display: inline-block;
            margin-top: 10px;
        }
        .button:hover {
            background: #1d3557;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            🎲 <span>Roulette POS</span>
        </div>
        <div class="user-info">
            <div class="user-name">
                <strong>Cashier:</strong> <?php echo htmlspecialchars($username); ?>
            </div>
            <a href="?logout=1" class="logout-btn">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="dashboard">
            <h1>Welcome to Roulette POS System</h1>
            
            <div class="card">
                <h2>Quick Actions</h2>
                <p>You are now logged in as a cashier. You can access the following features:</p>
                <a href="index.php" class="button">Go to Main Application</a>
                <a href="setup_login.php" class="button">Setup & Troubleshooting</a>
            </div>
            
            <div class="card">
                <h2>Your Account</h2>
                <p><strong>User ID:</strong> <?php echo htmlspecialchars($userId); ?></p>
                <p><strong>Username:</strong> <?php echo htmlspecialchars($username); ?></p>
                <p><strong>Role:</strong> <?php echo htmlspecialchars($role); ?></p>
            </div>
        </div>
    </div>
</body>
</html>
