<?php
// HR Department Database Setup
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_hr'])) {
    try {
        // HR DEPARTMENT TABLES
        
        // Employees (extends users table with HR-specific info)
        $sql = "CREATE TABLE IF NOT EXISTS employees (
            employee_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNIQUE,
            employee_number VARCHAR(20) UNIQUE NOT NULL,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            phone VARCHAR(20),
            address TEXT,
            city VARCHAR(50) DEFAULT 'Georgetown',
            emergency_contact_name VARCHAR(100),
            emergency_contact_phone VARCHAR(20),
            date_of_birth DATE,
            hire_date DATE NOT NULL,
            termination_date DATE,
            job_title VARCHAR(100) NOT NULL,
            department VARCHAR(50) NOT NULL,
            shop_id INT,
            supervisor_id INT,
            employment_status ENUM('active', 'inactive', 'terminated', 'suspended') DEFAULT 'active',
            employment_type ENUM('full_time', 'part_time', 'contract', 'temporary') DEFAULT 'full_time',
            salary DECIMAL(10,2) DEFAULT 0.00,
            hourly_rate DECIMAL(8,2) DEFAULT 0.00,
            commission_rate DECIMAL(5,2) DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id),
            FOREIGN KEY (shop_id) REFERENCES betting_shops(shop_id),
            FOREIGN KEY (supervisor_id) REFERENCES employees(employee_id)
        )";
        $conn->query($sql);

        // Payroll Records
        $sql = "CREATE TABLE IF NOT EXISTS payroll_records (
            payroll_id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            pay_period_start DATE NOT NULL,
            pay_period_end DATE NOT NULL,
            gross_salary DECIMAL(10,2) NOT NULL,
            overtime_hours DECIMAL(5,2) DEFAULT 0.00,
            overtime_pay DECIMAL(8,2) DEFAULT 0.00,
            commission DECIMAL(8,2) DEFAULT 0.00,
            bonuses DECIMAL(8,2) DEFAULT 0.00,
            gross_pay DECIMAL(10,2) NOT NULL,
            tax_deduction DECIMAL(8,2) DEFAULT 0.00,
            insurance_deduction DECIMAL(8,2) DEFAULT 0.00,
            other_deductions DECIMAL(8,2) DEFAULT 0.00,
            total_deductions DECIMAL(8,2) DEFAULT 0.00,
            net_pay DECIMAL(10,2) NOT NULL,
            payment_date DATE,
            payment_method ENUM('bank_transfer', 'cash', 'check') DEFAULT 'bank_transfer',
            status ENUM('draft', 'approved', 'paid', 'cancelled') DEFAULT 'draft',
            processed_by INT,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
            FOREIGN KEY (processed_by) REFERENCES users(user_id)
        )";
        $conn->query($sql);

        // Attendance Records
        $sql = "CREATE TABLE IF NOT EXISTS attendance_records (
            attendance_id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            date DATE NOT NULL,
            clock_in_time TIME,
            clock_out_time TIME,
            break_start_time TIME,
            break_end_time TIME,
            total_hours DECIMAL(4,2) DEFAULT 0.00,
            overtime_hours DECIMAL(4,2) DEFAULT 0.00,
            status ENUM('present', 'absent', 'late', 'half_day', 'sick', 'vacation') DEFAULT 'present',
            notes TEXT,
            approved_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
            FOREIGN KEY (approved_by) REFERENCES users(user_id),
            UNIQUE KEY unique_employee_date (employee_id, date)
        )";
        $conn->query($sql);

        // Leave Requests
        $sql = "CREATE TABLE IF NOT EXISTS leave_requests (
            leave_id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            leave_type ENUM('vacation', 'sick', 'personal', 'maternity', 'paternity', 'emergency') NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            days_requested INT NOT NULL,
            reason TEXT,
            status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
            approved_by INT,
            approved_at DATETIME,
            rejection_reason TEXT,
            requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
            FOREIGN KEY (approved_by) REFERENCES users(user_id)
        )";
        $conn->query($sql);

        // Performance Reviews
        $sql = "CREATE TABLE IF NOT EXISTS performance_reviews (
            review_id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            reviewer_id INT NOT NULL,
            review_period_start DATE NOT NULL,
            review_period_end DATE NOT NULL,
            overall_rating ENUM('excellent', 'good', 'satisfactory', 'needs_improvement', 'unsatisfactory') NOT NULL,
            goals_achievement DECIMAL(3,1) DEFAULT 0.0,
            communication_skills DECIMAL(3,1) DEFAULT 0.0,
            teamwork DECIMAL(3,1) DEFAULT 0.0,
            punctuality DECIMAL(3,1) DEFAULT 0.0,
            job_knowledge DECIMAL(3,1) DEFAULT 0.0,
            strengths TEXT,
            areas_for_improvement TEXT,
            goals_next_period TEXT,
            employee_comments TEXT,
            reviewer_comments TEXT,
            status ENUM('draft', 'completed', 'acknowledged') DEFAULT 'draft',
            review_date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
            FOREIGN KEY (reviewer_id) REFERENCES employees(employee_id)
        )";
        $conn->query($sql);

        // Job Applications
        $sql = "CREATE TABLE IF NOT EXISTS job_applications (
            application_id INT AUTO_INCREMENT PRIMARY KEY,
            job_title VARCHAR(100) NOT NULL,
            department VARCHAR(50) NOT NULL,
            shop_id INT,
            applicant_name VARCHAR(100) NOT NULL,
            applicant_email VARCHAR(100) NOT NULL,
            applicant_phone VARCHAR(20),
            resume_file VARCHAR(255),
            cover_letter TEXT,
            experience_years INT DEFAULT 0,
            education_level ENUM('high_school', 'associate', 'bachelor', 'master', 'doctorate', 'other') DEFAULT 'high_school',
            status ENUM('applied', 'screening', 'interview', 'offer', 'hired', 'rejected') DEFAULT 'applied',
            interview_date DATETIME,
            interviewer_id INT,
            interview_notes TEXT,
            salary_offered DECIMAL(10,2),
            start_date DATE,
            rejection_reason TEXT,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (shop_id) REFERENCES betting_shops(shop_id),
            FOREIGN KEY (interviewer_id) REFERENCES employees(employee_id)
        )";
        $conn->query($sql);

        // Training Records
        $sql = "CREATE TABLE IF NOT EXISTS training_records (
            training_id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            training_name VARCHAR(200) NOT NULL,
            training_type ENUM('orientation', 'skills', 'safety', 'compliance', 'leadership', 'technical') NOT NULL,
            trainer_name VARCHAR(100),
            training_date DATE NOT NULL,
            completion_date DATE,
            duration_hours DECIMAL(4,1) DEFAULT 0.0,
            status ENUM('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
            score DECIMAL(5,2),
            certification_earned BOOLEAN DEFAULT FALSE,
            certification_expiry DATE,
            cost DECIMAL(8,2) DEFAULT 0.00,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(employee_id)
        )";
        $conn->query($sql);

        // Employee Benefits
        $sql = "CREATE TABLE IF NOT EXISTS employee_benefits (
            benefit_id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            benefit_type ENUM('health_insurance', 'dental', 'vision', 'retirement', 'life_insurance', 'vacation', 'sick_leave') NOT NULL,
            benefit_name VARCHAR(100) NOT NULL,
            coverage_start DATE NOT NULL,
            coverage_end DATE,
            employee_contribution DECIMAL(8,2) DEFAULT 0.00,
            employer_contribution DECIMAL(8,2) DEFAULT 0.00,
            total_value DECIMAL(10,2) DEFAULT 0.00,
            status ENUM('active', 'inactive', 'pending', 'expired') DEFAULT 'active',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(employee_id)
        )";
        $conn->query($sql);

        // Disciplinary Actions
        $sql = "CREATE TABLE IF NOT EXISTS disciplinary_actions (
            action_id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            action_type ENUM('verbal_warning', 'written_warning', 'suspension', 'termination', 'counseling') NOT NULL,
            incident_date DATE NOT NULL,
            description TEXT NOT NULL,
            severity ENUM('minor', 'moderate', 'major', 'severe') NOT NULL,
            action_taken TEXT NOT NULL,
            issued_by INT NOT NULL,
            witness_1 VARCHAR(100),
            witness_2 VARCHAR(100),
            employee_response TEXT,
            follow_up_required BOOLEAN DEFAULT FALSE,
            follow_up_date DATE,
            status ENUM('active', 'resolved', 'appealed', 'overturned') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
            FOREIGN KEY (issued_by) REFERENCES employees(employee_id)
        )";
        $conn->query($sql);

        // Insert HR department
        $stmt = $conn->prepare("INSERT IGNORE INTO departments (dept_name, dept_code, description) VALUES (?, ?, ?)");
        $dept_data = ['Human Resources', 'HR', 'Human Resources and Personnel Management Department'];
        $stmt->bind_param("sss", $dept_data[0], $dept_data[1], $dept_data[2]);
        $stmt->execute();

        // Insert sample employees
        $employees = [
            ['EMP001', 'John', 'Doe', 'john.doe@company.gy', '+592-123-4567', '2023-01-15', 'HR Manager', 'Human Resources', 75000.00],
            ['EMP002', 'Jane', 'Smith', 'jane.smith@company.gy', '+592-234-5678', '2023-02-01', 'Cashier', 'Operations', 35000.00],
            ['EMP003', 'Mike', 'Johnson', 'mike.johnson@company.gy', '+592-345-6789', '2023-03-10', 'IT Specialist', 'Information Technology', 55000.00],
            ['EMP004', 'Sarah', 'Williams', 'sarah.williams@company.gy', '+592-456-7890', '2023-04-05', 'Accountant', 'Finance', 45000.00]
        ];

        $stmt = $conn->prepare("INSERT IGNORE INTO employees (employee_number, first_name, last_name, email, phone, hire_date, job_title, department, salary) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($employees as $emp) {
            $stmt->bind_param("ssssssssd", $emp[0], $emp[1], $emp[2], $emp[3], $emp[4], $emp[5], $emp[6], $emp[7], $emp[8]);
            $stmt->execute();
        }

        // Insert sample attendance records for current month
        $stmt = $conn->prepare("INSERT IGNORE INTO attendance_records (employee_id, date, clock_in_time, clock_out_time, total_hours, status) VALUES (?, ?, ?, ?, ?, ?)");
        
        for ($i = 1; $i <= 4; $i++) {
            for ($day = 1; $day <= 20; $day++) {
                $date = date('Y-m-' . sprintf('%02d', $day));
                $clock_in = '08:00:00';
                $clock_out = '17:00:00';
                $hours = 8.0;
                $status = 'present';
                
                // Add some variation
                if ($day % 7 == 0) continue; // Skip some days
                if ($day % 10 == 0) {
                    $status = 'sick';
                    $clock_in = null;
                    $clock_out = null;
                    $hours = 0.0;
                }
                
                $stmt->bind_param("isssds", $i, $date, $clock_in, $clock_out, $hours, $status);
                $stmt->execute();
            }
        }

        $message = "HR Department database tables created successfully with sample data!";
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
    <title>HR Department Setup - Admin</title>
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
                <h1 class="page-title">HR Department Setup</h1>
                <div class="breadcrumb">
                    <div class="breadcrumb-item"><a href="index.php">Admin</a></div>
                    <div class="breadcrumb-item active">HR Setup</div>
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
                            <h6 class="m-0 font-weight-bold text-primary">Setup Human Resources Department</h6>
                        </div>
                        <div class="card-body">
                            <p>This will create comprehensive database tables for the Human Resources department:</p>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card border-left-info">
                                        <div class="card-body">
                                            <h5 class="text-info"><i class="fas fa-users"></i> Employee Management</h5>
                                            <ul class="list-unstyled">
                                                <li>• Employee Database</li>
                                                <li>• Payroll Processing</li>
                                                <li>• Attendance Tracking</li>
                                                <li>• Performance Reviews</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="card border-left-info">
                                        <div class="card-body">
                                            <h5 class="text-info"><i class="fas fa-clipboard-list"></i> HR Operations</h5>
                                            <ul class="list-unstyled">
                                                <li>• Recruitment & Hiring</li>
                                                <li>• Training & Development</li>
                                                <li>• Benefits Administration</li>
                                                <li>• Disciplinary Actions</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center mt-4">
                                <form method="post" action="">
                                    <button type="submit" name="setup_hr" class="btn btn-info btn-lg">
                                        <i class="fas fa-database"></i> Create HR Department Tables
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
