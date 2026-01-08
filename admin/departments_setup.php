<?php
// Departmental Systems Database Setup
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_departments'])) {
    try {
        // Create departments table
        $sql = "CREATE TABLE IF NOT EXISTS departments (
            dept_id INT AUTO_INCREMENT PRIMARY KEY,
            dept_name VARCHAR(50) NOT NULL UNIQUE,
            dept_code VARCHAR(10) NOT NULL UNIQUE,
            description TEXT,
            manager_id INT,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (manager_id) REFERENCES users(user_id)
        )";
        $conn->query($sql);

        // Create department_users table
        $sql = "CREATE TABLE IF NOT EXISTS department_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            dept_id INT NOT NULL,
            user_id INT NOT NULL,
            role ENUM('manager', 'supervisor', 'staff') DEFAULT 'staff',
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('active', 'inactive') DEFAULT 'active',
            FOREIGN KEY (dept_id) REFERENCES departments(dept_id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            UNIQUE KEY unique_dept_user (dept_id, user_id)
        )";
        $conn->query($sql);

        // SALES DEPARTMENT TABLES
        
        // Marketing campaigns
        $sql = "CREATE TABLE IF NOT EXISTS marketing_campaigns (
            campaign_id INT AUTO_INCREMENT PRIMARY KEY,
            campaign_name VARCHAR(100) NOT NULL,
            campaign_type ENUM('promotion', 'advertising', 'event', 'loyalty') DEFAULT 'promotion',
            description TEXT,
            start_date DATE,
            end_date DATE,
            budget DECIMAL(12,2) DEFAULT 0.00,
            actual_cost DECIMAL(12,2) DEFAULT 0.00,
            target_shops TEXT,
            status ENUM('planning', 'active', 'completed', 'cancelled') DEFAULT 'planning',
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(user_id)
        )";
        $conn->query($sql);

        // Inventory management
        $sql = "CREATE TABLE IF NOT EXISTS inventory_items (
            item_id INT AUTO_INCREMENT PRIMARY KEY,
            item_name VARCHAR(100) NOT NULL,
            item_code VARCHAR(50) UNIQUE NOT NULL,
            category ENUM('receipt_rolls', 'stationery', 'equipment', 'promotional') DEFAULT 'receipt_rolls',
            unit_cost DECIMAL(10,2) DEFAULT 0.00,
            reorder_level INT DEFAULT 10,
            description TEXT,
            supplier VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $conn->query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS shop_inventory (
            id INT AUTO_INCREMENT PRIMARY KEY,
            shop_id INT NOT NULL,
            item_id INT NOT NULL,
            current_stock INT DEFAULT 0,
            last_restock_date DATE,
            last_restock_qty INT DEFAULT 0,
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (shop_id) REFERENCES betting_shops(shop_id) ON DELETE CASCADE,
            FOREIGN KEY (item_id) REFERENCES inventory_items(item_id) ON DELETE CASCADE,
            UNIQUE KEY unique_shop_item (shop_id, item_id)
        )";
        $conn->query($sql);

        // Equipment management
        $sql = "CREATE TABLE IF NOT EXISTS equipment (
            equipment_id INT AUTO_INCREMENT PRIMARY KEY,
            equipment_name VARCHAR(100) NOT NULL,
            equipment_type ENUM('pos_system', 'printer', 'display', 'computer', 'other') DEFAULT 'other',
            serial_number VARCHAR(100),
            shop_id INT,
            purchase_date DATE,
            warranty_expiry DATE,
            status ENUM('active', 'maintenance', 'repair', 'retired') DEFAULT 'active',
            last_maintenance DATE,
            next_maintenance DATE,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (shop_id) REFERENCES betting_shops(shop_id)
        )";
        $conn->query($sql);

        // Service requests
        $sql = "CREATE TABLE IF NOT EXISTS service_requests (
            request_id INT AUTO_INCREMENT PRIMARY KEY,
            shop_id INT NOT NULL,
            request_type ENUM('maintenance', 'repair', 'cleaning', 'delivery', 'other') DEFAULT 'maintenance',
            priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
            description TEXT NOT NULL,
            requested_by INT,
            assigned_to INT,
            status ENUM('pending', 'assigned', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
            requested_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            scheduled_date DATETIME,
            completed_date DATETIME,
            cost DECIMAL(10,2) DEFAULT 0.00,
            notes TEXT,
            FOREIGN KEY (shop_id) REFERENCES betting_shops(shop_id),
            FOREIGN KEY (requested_by) REFERENCES users(user_id),
            FOREIGN KEY (assigned_to) REFERENCES users(user_id)
        )";
        $conn->query($sql);

        // IT DEPARTMENT TABLES
        
        // IT tickets
        $sql = "CREATE TABLE IF NOT EXISTS it_tickets (
            ticket_id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_number VARCHAR(20) UNIQUE NOT NULL,
            shop_id INT,
            equipment_id INT,
            title VARCHAR(200) NOT NULL,
            description TEXT NOT NULL,
            category ENUM('hardware', 'software', 'network', 'security', 'other') DEFAULT 'other',
            priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
            status ENUM('open', 'assigned', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
            reported_by INT,
            assigned_to INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            resolved_at DATETIME,
            resolution_notes TEXT,
            estimated_cost DECIMAL(10,2) DEFAULT 0.00,
            actual_cost DECIMAL(10,2) DEFAULT 0.00,
            FOREIGN KEY (shop_id) REFERENCES betting_shops(shop_id),
            FOREIGN KEY (equipment_id) REFERENCES equipment(equipment_id),
            FOREIGN KEY (reported_by) REFERENCES users(user_id),
            FOREIGN KEY (assigned_to) REFERENCES users(user_id)
        )";
        $conn->query($sql);

        // IT projects
        $sql = "CREATE TABLE IF NOT EXISTS it_projects (
            project_id INT AUTO_INCREMENT PRIMARY KEY,
            project_name VARCHAR(200) NOT NULL,
            project_type ENUM('installation', 'upgrade', 'migration', 'maintenance', 'other') DEFAULT 'installation',
            description TEXT,
            shop_id INT,
            start_date DATE,
            end_date DATE,
            budget DECIMAL(12,2) DEFAULT 0.00,
            actual_cost DECIMAL(12,2) DEFAULT 0.00,
            status ENUM('planning', 'approved', 'in_progress', 'completed', 'cancelled') DEFAULT 'planning',
            project_manager INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (shop_id) REFERENCES betting_shops(shop_id),
            FOREIGN KEY (project_manager) REFERENCES users(user_id)
        )";
        $conn->query($sql);

        // FINANCE DEPARTMENT TABLES
        
        // Credit management
        $sql = "CREATE TABLE IF NOT EXISTS user_credit (
            credit_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            credit_limit DECIMAL(12,2) DEFAULT 0.00,
            current_balance DECIMAL(12,2) DEFAULT 0.00,
            available_credit DECIMAL(12,2) DEFAULT 0.00,
            payment_terms INT DEFAULT 30,
            credit_score INT DEFAULT 0,
            last_payment_date DATE,
            status ENUM('active', 'suspended', 'closed') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_credit (user_id)
        )";
        $conn->query($sql);

        // Business expenses
        $sql = "CREATE TABLE IF NOT EXISTS business_expenses (
            expense_id INT AUTO_INCREMENT PRIMARY KEY,
            shop_id INT,
            category ENUM('rent', 'utilities', 'salaries', 'equipment', 'marketing', 'maintenance', 'other') DEFAULT 'other',
            description VARCHAR(200) NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            expense_date DATE NOT NULL,
            payment_method ENUM('cash', 'bank_transfer', 'check', 'card') DEFAULT 'bank_transfer',
            vendor VARCHAR(100),
            receipt_number VARCHAR(100),
            approved_by INT,
            status ENUM('pending', 'approved', 'paid', 'rejected') DEFAULT 'pending',
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (shop_id) REFERENCES betting_shops(shop_id),
            FOREIGN KEY (approved_by) REFERENCES users(user_id),
            FOREIGN KEY (created_by) REFERENCES users(user_id)
        )";
        $conn->query($sql);

        // Financial reports
        $sql = "CREATE TABLE IF NOT EXISTS financial_reports (
            report_id INT AUTO_INCREMENT PRIMARY KEY,
            report_name VARCHAR(200) NOT NULL,
            report_type ENUM('daily', 'weekly', 'monthly', 'quarterly', 'yearly') DEFAULT 'monthly',
            shop_id INT,
            report_period_start DATE NOT NULL,
            report_period_end DATE NOT NULL,
            total_revenue DECIMAL(15,2) DEFAULT 0.00,
            total_expenses DECIMAL(15,2) DEFAULT 0.00,
            net_profit DECIMAL(15,2) DEFAULT 0.00,
            commission_paid DECIMAL(12,2) DEFAULT 0.00,
            generated_by INT,
            generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (shop_id) REFERENCES betting_shops(shop_id),
            FOREIGN KEY (generated_by) REFERENCES users(user_id)
        )";
        $conn->query($sql);

        // Insert default departments
        $departments = [
            ['Sales', 'SALES', 'Sales and Marketing Department'],
            ['IT', 'IT', 'Information Technology Department'],
            ['Finance', 'FIN', 'Finance and Accounting Department']
        ];

        $stmt = $conn->prepare("INSERT IGNORE INTO departments (dept_name, dept_code, description) VALUES (?, ?, ?)");
        foreach ($departments as $dept) {
            $stmt->bind_param("sss", $dept[0], $dept[1], $dept[2]);
            $stmt->execute();
        }

        // Insert sample inventory items
        $items = [
            ['Receipt Paper Rolls', 'RPR001', 'receipt_rolls', 2.50, 20, 'Thermal receipt paper rolls for POS printers'],
            ['Betting Slip Forms', 'BSF001', 'stationery', 0.05, 500, 'Pre-printed betting slip forms'],
            ['Promotional Banners', 'PB001', 'promotional', 25.00, 5, 'Large promotional banners for shop windows'],
            ['Cleaning Supplies', 'CS001', 'equipment', 15.00, 10, 'General cleaning supplies for shops']
        ];

        $stmt = $conn->prepare("INSERT IGNORE INTO inventory_items (item_name, item_code, category, unit_cost, reorder_level, description) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($items as $item) {
            $stmt->bind_param("sssdis", $item[0], $item[1], $item[2], $item[3], $item[4], $item[5]);
            $stmt->execute();
        }

        $message = "All departmental systems database tables created successfully!";
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
    <title>Departmental Systems Setup - Admin</title>
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
                <h1 class="page-title">Departmental Systems Setup</h1>
                <div class="breadcrumb">
                    <div class="breadcrumb-item"><a href="index.php">Admin</a></div>
                    <div class="breadcrumb-item active">Departments Setup</div>
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
                            <h6 class="m-0 font-weight-bold text-primary">Setup Departmental Management Systems</h6>
                        </div>
                        <div class="card-body">
                            <p>This will create comprehensive database tables for three departmental systems:</p>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="card border-left-success">
                                        <div class="card-body">
                                            <h5 class="text-success"><i class="fas fa-chart-line"></i> Sales Department</h5>
                                            <ul class="list-unstyled">
                                                <li>• Marketing Campaigns</li>
                                                <li>• Inventory Management</li>
                                                <li>• Equipment Tracking</li>
                                                <li>• Service Requests</li>
                                                <li>• Performance Analytics</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="card border-left-info">
                                        <div class="card-body">
                                            <h5 class="text-info"><i class="fas fa-laptop"></i> IT Department</h5>
                                            <ul class="list-unstyled">
                                                <li>• Help Desk Tickets</li>
                                                <li>• Equipment Repair</li>
                                                <li>• Installation Projects</li>
                                                <li>• Asset Management</li>
                                                <li>• Maintenance Scheduling</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="card border-left-warning">
                                        <div class="card-body">
                                            <h5 class="text-warning"><i class="fas fa-dollar-sign"></i> Finance Department</h5>
                                            <ul class="list-unstyled">
                                                <li>• Credit Management</li>
                                                <li>• Expense Tracking</li>
                                                <li>• Financial Reports</li>
                                                <li>• Budget Management</li>
                                                <li>• P&L Analysis</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center mt-4">
                                <form method="post" action="">
                                    <button type="submit" name="setup_departments" class="btn btn-primary btn-lg">
                                        <i class="fas fa-database"></i> Create All Department Tables
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
