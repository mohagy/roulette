<?php
// Betting Shops Database Setup Script
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "roulette";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_tables'])) {
    try {
        // Create betting_shops table
        $sql = "CREATE TABLE IF NOT EXISTS betting_shops (
            shop_id INT AUTO_INCREMENT PRIMARY KEY,
            shop_name VARCHAR(100) NOT NULL,
            shop_code VARCHAR(20) UNIQUE NOT NULL,
            address TEXT,
            city VARCHAR(50),
            state VARCHAR(50),
            postal_code VARCHAR(20),
            country VARCHAR(50) DEFAULT 'Guyana',
            phone VARCHAR(20),
            email VARCHAR(100),
            manager_name VARCHAR(100),
            manager_phone VARCHAR(20),
            manager_email VARCHAR(100),
            latitude DECIMAL(10, 8),
            longitude DECIMAL(11, 8),
            status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
            commission_rate DECIMAL(5,2) DEFAULT 5.00,
            opening_time TIME DEFAULT '08:00:00',
            closing_time TIME DEFAULT '22:00:00',
            timezone VARCHAR(50) DEFAULT 'America/Guyana',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        if (!$conn->query($sql)) {
            throw new Exception("Error creating betting_shops table: " . $conn->error);
        }

        // Create shop_users table (link users to shops)
        $sql = "CREATE TABLE IF NOT EXISTS shop_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            shop_id INT NOT NULL,
            user_id INT NOT NULL,
            role ENUM('manager', 'cashier', 'supervisor') DEFAULT 'cashier',
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            assigned_by INT,
            status ENUM('active', 'inactive') DEFAULT 'active',
            FOREIGN KEY (shop_id) REFERENCES betting_shops(shop_id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_by) REFERENCES users(user_id),
            UNIQUE KEY unique_shop_user (shop_id, user_id)
        )";
        
        if (!$conn->query($sql)) {
            throw new Exception("Error creating shop_users table: " . $conn->error);
        }

        // Create shop_transactions table (track shop-specific transactions)
        $sql = "CREATE TABLE IF NOT EXISTS shop_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            shop_id INT NOT NULL,
            transaction_id INT NOT NULL,
            commission_amount DECIMAL(10,2) DEFAULT 0.00,
            processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (shop_id) REFERENCES betting_shops(shop_id) ON DELETE CASCADE,
            FOREIGN KEY (transaction_id) REFERENCES transactions(transaction_id) ON DELETE CASCADE,
            UNIQUE KEY unique_shop_transaction (shop_id, transaction_id)
        )";
        
        if (!$conn->query($sql)) {
            throw new Exception("Error creating shop_transactions table: " . $conn->error);
        }

        // Create shop_performance table (daily/monthly performance tracking)
        $sql = "CREATE TABLE IF NOT EXISTS shop_performance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            shop_id INT NOT NULL,
            date DATE NOT NULL,
            total_bets DECIMAL(12,2) DEFAULT 0.00,
            total_wins DECIMAL(12,2) DEFAULT 0.00,
            total_commission DECIMAL(12,2) DEFAULT 0.00,
            total_transactions INT DEFAULT 0,
            active_users INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (shop_id) REFERENCES betting_shops(shop_id) ON DELETE CASCADE,
            UNIQUE KEY unique_shop_date (shop_id, date)
        )";
        
        if (!$conn->query($sql)) {
            throw new Exception("Error creating shop_performance table: " . $conn->error);
        }

        // Add shop_id column to users table if it doesn't exist
        $result = $conn->query("SHOW COLUMNS FROM users LIKE 'shop_id'");
        if ($result->num_rows == 0) {
            $sql = "ALTER TABLE users ADD COLUMN shop_id INT NULL, 
                    ADD FOREIGN KEY (shop_id) REFERENCES betting_shops(shop_id) ON DELETE SET NULL";
            if (!$conn->query($sql)) {
                throw new Exception("Error adding shop_id to users table: " . $conn->error);
            }
        }

        // Insert sample betting shops
        $sampleShops = [
            [
                'name' => 'Downtown Betting Center',
                'code' => 'DBC001',
                'address' => '123 Main Street',
                'city' => 'Georgetown',
                'phone' => '+592-123-4567',
                'manager' => 'John Smith'
            ],
            [
                'name' => 'Eastside Gaming Hub',
                'code' => 'EGH002',
                'address' => '456 East Avenue',
                'city' => 'Georgetown',
                'phone' => '+592-234-5678',
                'manager' => 'Sarah Johnson'
            ],
            [
                'name' => 'Westbank Betting Shop',
                'code' => 'WBS003',
                'address' => '789 West Road',
                'city' => 'Georgetown',
                'phone' => '+592-345-6789',
                'manager' => 'Michael Brown'
            ]
        ];

        $stmt = $conn->prepare("INSERT INTO betting_shops (shop_name, shop_code, address, city, phone, manager_name) VALUES (?, ?, ?, ?, ?, ?)");
        
        foreach ($sampleShops as $shop) {
            $stmt->bind_param("ssssss", $shop['name'], $shop['code'], $shop['address'], $shop['city'], $shop['phone'], $shop['manager']);
            $stmt->execute();
        }

        $message = "Betting shops database tables created successfully with sample data!";
        $messageType = "success";

    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Betting Shops Setup - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <nav class="top-navbar">
            <div class="navbar-search">
                <input type="text" class="search-input" placeholder="Search...">
            </div>
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a href="../logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </li>
            </ul>
        </nav>

        <div class="content-wrapper">
            <div class="page-header">
                <h1 class="page-title">Betting Shops Database Setup</h1>
                <div class="breadcrumb">
                    <div class="breadcrumb-item"><a href="index.php">Admin</a></div>
                    <div class="breadcrumb-item active">Betting Shops Setup</div>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-12">
                    <div class="card shadow">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold text-primary">Setup Betting Shops Database</h6>
                        </div>
                        <div class="card-body">
                            <p>This will create the necessary database tables for betting shops management:</p>
                            <ul>
                                <li><strong>betting_shops</strong> - Store shop information and details</li>
                                <li><strong>shop_users</strong> - Link users/cashiers to specific shops</li>
                                <li><strong>shop_transactions</strong> - Track shop-specific transactions</li>
                                <li><strong>shop_performance</strong> - Daily/monthly performance metrics</li>
                            </ul>
                            
                            <form method="post" action="">
                                <button type="submit" name="setup_tables" class="btn btn-primary">
                                    <i class="fas fa-database"></i> Create Tables & Sample Data
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
