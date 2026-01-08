<?php
// Stock & Accounting Departments Database Setup
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
        // STOCK/INVENTORY DEPARTMENT TABLES
        
        // Vendors/Suppliers
        $sql = "CREATE TABLE IF NOT EXISTS vendors (
            vendor_id INT AUTO_INCREMENT PRIMARY KEY,
            vendor_name VARCHAR(100) NOT NULL,
            vendor_code VARCHAR(20) UNIQUE NOT NULL,
            contact_person VARCHAR(100),
            email VARCHAR(100),
            phone VARCHAR(20),
            address TEXT,
            city VARCHAR(50),
            country VARCHAR(50) DEFAULT 'Guyana',
            payment_terms INT DEFAULT 30,
            credit_limit DECIMAL(12,2) DEFAULT 0.00,
            status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
            rating DECIMAL(3,2) DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $conn->query($sql);

        // Purchase Orders
        $sql = "CREATE TABLE IF NOT EXISTS purchase_orders (
            po_id INT AUTO_INCREMENT PRIMARY KEY,
            po_number VARCHAR(20) UNIQUE NOT NULL,
            vendor_id INT NOT NULL,
            shop_id INT,
            order_date DATE NOT NULL,
            expected_delivery DATE,
            actual_delivery DATE,
            total_amount DECIMAL(12,2) DEFAULT 0.00,
            status ENUM('draft', 'sent', 'confirmed', 'shipped', 'delivered', 'cancelled') DEFAULT 'draft',
            priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
            created_by INT,
            approved_by INT,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id),
            FOREIGN KEY (shop_id) REFERENCES betting_shops(shop_id),
            FOREIGN KEY (created_by) REFERENCES users(user_id),
            FOREIGN KEY (approved_by) REFERENCES users(user_id)
        )";
        $conn->query($sql);

        // Purchase Order Items
        $sql = "CREATE TABLE IF NOT EXISTS purchase_order_items (
            poi_id INT AUTO_INCREMENT PRIMARY KEY,
            po_id INT NOT NULL,
            item_id INT NOT NULL,
            quantity INT NOT NULL,
            unit_price DECIMAL(10,2) NOT NULL,
            total_price DECIMAL(12,2) NOT NULL,
            received_quantity INT DEFAULT 0,
            status ENUM('pending', 'partial', 'received', 'cancelled') DEFAULT 'pending',
            FOREIGN KEY (po_id) REFERENCES purchase_orders(po_id) ON DELETE CASCADE,
            FOREIGN KEY (item_id) REFERENCES inventory_items(item_id)
        )";
        $conn->query($sql);

        // Stock Movements
        $sql = "CREATE TABLE IF NOT EXISTS stock_movements (
            movement_id INT AUTO_INCREMENT PRIMARY KEY,
            shop_id INT NOT NULL,
            item_id INT NOT NULL,
            movement_type ENUM('in', 'out', 'transfer', 'adjustment') NOT NULL,
            quantity INT NOT NULL,
            reference_type ENUM('purchase', 'sale', 'transfer', 'adjustment', 'return') NOT NULL,
            reference_id INT,
            notes TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (shop_id) REFERENCES betting_shops(shop_id),
            FOREIGN KEY (item_id) REFERENCES inventory_items(item_id),
            FOREIGN KEY (created_by) REFERENCES users(user_id)
        )";
        $conn->query($sql);

        // Equipment Issues/Problems
        $sql = "CREATE TABLE IF NOT EXISTS equipment_issues (
            issue_id INT AUTO_INCREMENT PRIMARY KEY,
            equipment_id INT NOT NULL,
            issue_type ENUM('malfunction', 'damage', 'replacement_needed', 'maintenance', 'other') NOT NULL,
            severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
            description TEXT NOT NULL,
            reported_by INT,
            assigned_to INT,
            status ENUM('open', 'assigned', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
            resolution_notes TEXT,
            estimated_cost DECIMAL(10,2) DEFAULT 0.00,
            actual_cost DECIMAL(10,2) DEFAULT 0.00,
            reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            resolved_at DATETIME,
            FOREIGN KEY (equipment_id) REFERENCES equipment(equipment_id),
            FOREIGN KEY (reported_by) REFERENCES users(user_id),
            FOREIGN KEY (assigned_to) REFERENCES users(user_id)
        )";
        $conn->query($sql);

        // Stock Audits
        $sql = "CREATE TABLE IF NOT EXISTS stock_audits (
            audit_id INT AUTO_INCREMENT PRIMARY KEY,
            audit_number VARCHAR(20) UNIQUE NOT NULL,
            shop_id INT NOT NULL,
            audit_date DATE NOT NULL,
            audit_type ENUM('scheduled', 'random', 'investigation') DEFAULT 'scheduled',
            status ENUM('planned', 'in_progress', 'completed', 'cancelled') DEFAULT 'planned',
            conducted_by INT,
            total_items_checked INT DEFAULT 0,
            discrepancies_found INT DEFAULT 0,
            total_value_variance DECIMAL(12,2) DEFAULT 0.00,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME,
            FOREIGN KEY (shop_id) REFERENCES betting_shops(shop_id),
            FOREIGN KEY (conducted_by) REFERENCES users(user_id)
        )";
        $conn->query($sql);

        // ACCOUNTING DEPARTMENT TABLES
        
        // Chart of Accounts
        $sql = "CREATE TABLE IF NOT EXISTS chart_of_accounts (
            account_id INT AUTO_INCREMENT PRIMARY KEY,
            account_code VARCHAR(20) UNIQUE NOT NULL,
            account_name VARCHAR(100) NOT NULL,
            account_type ENUM('asset', 'liability', 'equity', 'revenue', 'expense') NOT NULL,
            parent_account_id INT,
            is_active BOOLEAN DEFAULT TRUE,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (parent_account_id) REFERENCES chart_of_accounts(account_id)
        )";
        $conn->query($sql);

        // Accounts Payable
        $sql = "CREATE TABLE IF NOT EXISTS accounts_payable (
            ap_id INT AUTO_INCREMENT PRIMARY KEY,
            vendor_id INT NOT NULL,
            invoice_number VARCHAR(50) NOT NULL,
            invoice_date DATE NOT NULL,
            due_date DATE NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            paid_amount DECIMAL(12,2) DEFAULT 0.00,
            balance DECIMAL(12,2) NOT NULL,
            status ENUM('pending', 'partial', 'paid', 'overdue', 'cancelled') DEFAULT 'pending',
            payment_terms INT DEFAULT 30,
            description TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id),
            FOREIGN KEY (created_by) REFERENCES users(user_id)
        )";
        $conn->query($sql);

        // Accounts Receivable
        $sql = "CREATE TABLE IF NOT EXISTS accounts_receivable (
            ar_id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            invoice_number VARCHAR(50) NOT NULL,
            invoice_date DATE NOT NULL,
            due_date DATE NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            paid_amount DECIMAL(12,2) DEFAULT 0.00,
            balance DECIMAL(12,2) NOT NULL,
            status ENUM('pending', 'partial', 'paid', 'overdue', 'written_off') DEFAULT 'pending',
            payment_terms INT DEFAULT 30,
            description TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES users(user_id),
            FOREIGN KEY (created_by) REFERENCES users(user_id)
        )";
        $conn->query($sql);

        // Fixed Assets
        $sql = "CREATE TABLE IF NOT EXISTS fixed_assets (
            asset_id INT AUTO_INCREMENT PRIMARY KEY,
            asset_code VARCHAR(20) UNIQUE NOT NULL,
            asset_name VARCHAR(100) NOT NULL,
            category ENUM('equipment', 'furniture', 'vehicle', 'building', 'other') NOT NULL,
            shop_id INT,
            purchase_date DATE NOT NULL,
            purchase_cost DECIMAL(12,2) NOT NULL,
            useful_life_years INT DEFAULT 5,
            salvage_value DECIMAL(12,2) DEFAULT 0.00,
            accumulated_depreciation DECIMAL(12,2) DEFAULT 0.00,
            current_value DECIMAL(12,2) NOT NULL,
            status ENUM('active', 'disposed', 'sold', 'written_off') DEFAULT 'active',
            location VARCHAR(100),
            serial_number VARCHAR(100),
            vendor_id INT,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (shop_id) REFERENCES betting_shops(shop_id),
            FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id)
        )";
        $conn->query($sql);

        // Liabilities
        $sql = "CREATE TABLE IF NOT EXISTS liabilities (
            liability_id INT AUTO_INCREMENT PRIMARY KEY,
            liability_type ENUM('loan', 'credit_line', 'mortgage', 'lease', 'other') NOT NULL,
            creditor_name VARCHAR(100) NOT NULL,
            principal_amount DECIMAL(12,2) NOT NULL,
            current_balance DECIMAL(12,2) NOT NULL,
            interest_rate DECIMAL(5,2) DEFAULT 0.00,
            start_date DATE NOT NULL,
            maturity_date DATE,
            payment_frequency ENUM('monthly', 'quarterly', 'annually') DEFAULT 'monthly',
            monthly_payment DECIMAL(10,2) DEFAULT 0.00,
            status ENUM('active', 'paid_off', 'defaulted', 'restructured') DEFAULT 'active',
            collateral TEXT,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $conn->query($sql);

        // Journal Entries
        $sql = "CREATE TABLE IF NOT EXISTS journal_entries (
            entry_id INT AUTO_INCREMENT PRIMARY KEY,
            entry_number VARCHAR(20) UNIQUE NOT NULL,
            entry_date DATE NOT NULL,
            description TEXT NOT NULL,
            reference_type ENUM('manual', 'automatic', 'adjustment', 'closing') DEFAULT 'manual',
            reference_id INT,
            total_debit DECIMAL(12,2) NOT NULL,
            total_credit DECIMAL(12,2) NOT NULL,
            status ENUM('draft', 'posted', 'reversed') DEFAULT 'draft',
            created_by INT,
            posted_by INT,
            posted_at DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(user_id),
            FOREIGN KEY (posted_by) REFERENCES users(user_id)
        )";
        $conn->query($sql);

        // Journal Entry Details
        $sql = "CREATE TABLE IF NOT EXISTS journal_entry_details (
            detail_id INT AUTO_INCREMENT PRIMARY KEY,
            entry_id INT NOT NULL,
            account_id INT NOT NULL,
            debit_amount DECIMAL(12,2) DEFAULT 0.00,
            credit_amount DECIMAL(12,2) DEFAULT 0.00,
            description TEXT,
            FOREIGN KEY (entry_id) REFERENCES journal_entries(entry_id) ON DELETE CASCADE,
            FOREIGN KEY (account_id) REFERENCES chart_of_accounts(account_id)
        )";
        $conn->query($sql);

        // Insert additional departments
        $departments = [
            ['Stock', 'STOCK', 'Stock and Inventory Management Department'],
            ['Accounting', 'ACC', 'Accounting and Financial Records Department']
        ];

        $stmt = $conn->prepare("INSERT IGNORE INTO departments (dept_name, dept_code, description) VALUES (?, ?, ?)");
        foreach ($departments as $dept) {
            $stmt->bind_param("sss", $dept[0], $dept[1], $dept[2]);
            $stmt->execute();
        }

        // Insert sample vendors
        $vendors = [
            ['TechSupply Co.', 'TECH001', 'John Smith', 'john@techsupply.gy', '+592-123-4567', '123 Tech Street, Georgetown'],
            ['Office Solutions', 'OFF001', 'Sarah Johnson', 'sarah@officesolutions.gy', '+592-234-5678', '456 Business Ave, Georgetown'],
            ['Equipment Plus', 'EQP001', 'Mike Brown', 'mike@equipmentplus.gy', '+592-345-6789', '789 Industrial Rd, Georgetown']
        ];

        $stmt = $conn->prepare("INSERT IGNORE INTO vendors (vendor_name, vendor_code, contact_person, email, phone, address) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($vendors as $vendor) {
            $stmt->bind_param("ssssss", $vendor[0], $vendor[1], $vendor[2], $vendor[3], $vendor[4], $vendor[5]);
            $stmt->execute();
        }

        // Insert sample chart of accounts
        $accounts = [
            ['1000', 'Cash', 'asset'],
            ['1100', 'Accounts Receivable', 'asset'],
            ['1200', 'Inventory', 'asset'],
            ['1500', 'Equipment', 'asset'],
            ['2000', 'Accounts Payable', 'liability'],
            ['2100', 'Loans Payable', 'liability'],
            ['3000', 'Owner Equity', 'equity'],
            ['4000', 'Revenue', 'revenue'],
            ['5000', 'Operating Expenses', 'expense'],
            ['5100', 'Rent Expense', 'expense'],
            ['5200', 'Utilities Expense', 'expense']
        ];

        $stmt = $conn->prepare("INSERT IGNORE INTO chart_of_accounts (account_code, account_name, account_type) VALUES (?, ?, ?)");
        foreach ($accounts as $account) {
            $stmt->bind_param("sss", $account[0], $account[1], $account[2]);
            $stmt->execute();
        }

        $message = "Stock and Accounting departments database tables created successfully!";
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
    <title>Stock & Accounting Setup - Admin</title>
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
                <h1 class="page-title">Stock & Accounting Departments Setup</h1>
                <div class="breadcrumb">
                    <div class="breadcrumb-item"><a href="index.php">Admin</a></div>
                    <div class="breadcrumb-item active">Stock & Accounting Setup</div>
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
                            <h6 class="m-0 font-weight-bold text-primary">Setup Additional Departmental Systems</h6>
                        </div>
                        <div class="card-body">
                            <p>This will create comprehensive database tables for two additional departmental systems:</p>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card border-left-warning">
                                        <div class="card-body">
                                            <h5 class="text-warning"><i class="fas fa-boxes"></i> Stock/Inventory Department</h5>
                                            <ul class="list-unstyled">
                                                <li>• Vendor Management</li>
                                                <li>• Purchase Orders</li>
                                                <li>• Stock Movements</li>
                                                <li>• Equipment Issues</li>
                                                <li>• Stock Audits</li>
                                                <li>• Distribution Tracking</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="card border-left-dark">
                                        <div class="card-body">
                                            <h5 class="text-dark"><i class="fas fa-calculator"></i> Accounting Department</h5>
                                            <ul class="list-unstyled">
                                                <li>• Chart of Accounts</li>
                                                <li>• Accounts Payable/Receivable</li>
                                                <li>• Fixed Assets</li>
                                                <li>• Liabilities</li>
                                                <li>• Journal Entries</li>
                                                <li>• Financial Reporting</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center mt-4">
                                <form method="post" action="">
                                    <button type="submit" name="setup_departments" class="btn btn-primary btn-lg">
                                        <i class="fas fa-database"></i> Create Stock & Accounting Tables
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
