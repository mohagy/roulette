<?php
// HR Employee Management
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Check if user has HR department access
$user_role = $_SESSION['role'] ?? '';
$allowed_roles = ['admin', 'hr_manager', 'hr_staff'];

if (!in_array($user_role, $allowed_roles) && $user_role !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "roulette";

date_default_timezone_set('America/Guyana');

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_employee'])) {
            // Generate employee number
            $employee_number = 'EMP' . date('Y') . sprintf('%03d', rand(1, 999));
            
            // Check if employee number exists
            $stmt = $conn->prepare("SELECT employee_id FROM employees WHERE employee_number = ?");
            $stmt->bind_param("s", $employee_number);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $employee_number = 'EMP' . date('Y') . sprintf('%03d', rand(1, 999));
            }

            // Insert new employee
            $stmt = $conn->prepare("INSERT INTO employees (
                employee_number, first_name, last_name, email, phone, address, 
                date_of_birth, hire_date, job_title, department, shop_id, 
                employment_type, salary, hourly_rate, commission_rate
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $shop_id = !empty($_POST['shop_id']) ? intval($_POST['shop_id']) : null;
            
            $stmt->bind_param("ssssssssssissdd", 
                $employee_number,
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['email'],
                $_POST['phone'],
                $_POST['address'],
                $_POST['date_of_birth'],
                $_POST['hire_date'],
                $_POST['job_title'],
                $_POST['department'],
                $shop_id,
                $_POST['employment_type'],
                $_POST['salary'],
                $_POST['hourly_rate'],
                $_POST['commission_rate']
            );

            if ($stmt->execute()) {
                $message = "Employee {$employee_number} added successfully!";
                $messageType = "success";
            } else {
                throw new Exception("Error adding employee: " . $stmt->error);
            }

        } elseif (isset($_POST['update_employee'])) {
            // Update employee
            $stmt = $conn->prepare("UPDATE employees SET 
                first_name = ?, last_name = ?, email = ?, phone = ?, address = ?,
                date_of_birth = ?, job_title = ?, department = ?, shop_id = ?,
                employment_status = ?, employment_type = ?, salary = ?, 
                hourly_rate = ?, commission_rate = ?
                WHERE employee_id = ?");
            
            $shop_id = !empty($_POST['shop_id']) ? intval($_POST['shop_id']) : null;
            
            $stmt->bind_param("ssssssssissdddi", 
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['email'],
                $_POST['phone'],
                $_POST['address'],
                $_POST['date_of_birth'],
                $_POST['job_title'],
                $_POST['department'],
                $shop_id,
                $_POST['employment_status'],
                $_POST['employment_type'],
                $_POST['salary'],
                $_POST['hourly_rate'],
                $_POST['commission_rate'],
                $_POST['employee_id']
            );
            
            if ($stmt->execute()) {
                $message = "Employee updated successfully!";
                $messageType = "success";
            } else {
                throw new Exception("Error updating employee: " . $stmt->error);
            }
        }

    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Get filter parameters
$filter_status = $_GET['status'] ?? 'all';
$filter_department = $_GET['department'] ?? 'all';

// Build WHERE clause for filters
$where_conditions = [];
$params = [];
$types = "";

if ($filter_status !== 'all') {
    $where_conditions[] = "e.employment_status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if ($filter_department !== 'all') {
    $where_conditions[] = "e.department = ?";
    $params[] = $filter_department;
    $types .= "s";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get all employees with filters
$employees = [];
$sql = "SELECT 
            e.*,
            bs.shop_name
        FROM employees e
        LEFT JOIN betting_shops bs ON e.shop_id = bs.shop_id
        {$where_clause}
        ORDER BY e.last_name, e.first_name";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }
}

// Get shops for dropdown
$shops = [];
$result = $conn->query("SELECT shop_id, shop_name FROM betting_shops WHERE status = 'active' ORDER BY shop_name");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $shops[] = $row;
    }
}

// Get departments for filter
$departments = [];
$result = $conn->query("SELECT DISTINCT department FROM employees ORDER BY department");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $departments[] = $row['department'];
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management - HR</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #008B8B 0%, #20B2AA 100%);
            min-height: 100vh;
            font-family: 'Nunito', sans-serif;
        }
        .sidebar {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            min-height: 100vh;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            background: rgba(255,255,255,0.95);
            margin-bottom: 20px;
        }
        .nav-link {
            color: #5a5c69;
            padding: 12px 20px;
            border-radius: 10px;
            margin: 2px 0;
            transition: all 0.3s ease;
        }
        .nav-link:hover, .nav-link.active {
            background: linear-gradient(135deg, #008B8B 0%, #20B2AA 100%);
            color: white;
        }
        .status-active { color: #28a745; font-weight: bold; }
        .status-inactive { color: #6c757d; font-weight: bold; }
        .status-terminated { color: #dc3545; font-weight: bold; }
        .status-suspended { color: #fd7e14; font-weight: bold; }
        .employee-row {
            transition: all 0.3s ease;
        }
        .employee-row:hover {
            background: rgba(0, 139, 139, 0.05);
            transform: translateY(-1px);
        }
        .filter-card {
            background: rgba(255,255,255,0.9);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .btn-create {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            color: white;
            font-weight: 600;
        }
        .btn-create:hover {
            background: linear-gradient(135deg, #20c997 0%, #28a745 100%);
            color: white;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar position-fixed">
        <div class="p-4">
            <h4 class="text-info"><i class="fas fa-users"></i> HR Dept</h4>
        </div>
        <nav class="nav flex-column px-3">
            <a class="nav-link" href="index.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a class="nav-link active" href="employees.php">
                <i class="fas fa-users"></i> Employee Management
            </a>
            <a class="nav-link" href="payroll.php">
                <i class="fas fa-money-check-alt"></i> Payroll System
            </a>
            <a class="nav-link" href="attendance.php">
                <i class="fas fa-clock"></i> Attendance Tracking
            </a>
            <a class="nav-link" href="performance.php">
                <i class="fas fa-chart-line"></i> Performance Management
            </a>
            <a class="nav-link" href="recruitment.php">
                <i class="fas fa-user-plus"></i> Recruitment & Hiring
            </a>
            <a class="nav-link" href="training.php">
                <i class="fas fa-graduation-cap"></i> Training & Development
            </a>
            <a class="nav-link" href="benefits.php">
                <i class="fas fa-heart"></i> Benefits Administration
            </a>
            <a class="nav-link" href="disciplinary.php">
                <i class="fas fa-gavel"></i> Disciplinary Actions
            </a>
            <hr>
            <a class="nav-link" href="../admin/index.php">
                <i class="fas fa-cog"></i> Admin Panel
            </a>
            <a class="nav-link" href="../logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="text-white">Employee Management</h1>
                <p class="text-white-50 mb-0">Manage employee records and information</p>
            </div>
            <div>
                <button class="btn btn-create" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                    <i class="fas fa-user-plus"></i> Add New Employee
                </button>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="filter-card">
            <form method="get" action="" class="row g-3">
                <div class="col-md-4">
                    <label for="status" class="form-label">Employment Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="terminated" <?php echo $filter_status === 'terminated' ? 'selected' : ''; ?>>Terminated</option>
                        <option value="suspended" <?php echo $filter_status === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="department" class="form-label">Department</label>
                    <select class="form-select" id="department" name="department">
                        <option value="all" <?php echo $filter_department === 'all' ? 'selected' : ''; ?>>All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo $filter_department === $dept ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="employees.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Employees Table -->
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-users"></i> Employee Records (<?php echo count($employees); ?>)
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($employees)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Employees Found</h5>
                        <p class="text-muted">No employees match your current filters.</p>
                        <button class="btn btn-create" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                            <i class="fas fa-user-plus"></i> Add First Employee
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Employee #</th>
                                    <th>Name</th>
                                    <th>Job Title</th>
                                    <th>Department</th>
                                    <th>Shop</th>
                                    <th>Employment Type</th>
                                    <th>Status</th>
                                    <th>Hire Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($employees as $employee): ?>
                                <tr class="employee-row">
                                    <td>
                                        <strong><?php echo htmlspecialchars($employee['employee_number']); ?></strong>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($employee['email']); ?></small>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($employee['job_title']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['department']); ?></td>
                                    <td>
                                        <?php if ($employee['shop_name']): ?>
                                            <i class="fas fa-store text-primary"></i> <?php echo htmlspecialchars($employee['shop_name']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Head Office</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo ucfirst(str_replace('_', ' ', $employee['employment_type'])); ?></span>
                                    </td>
                                    <td>
                                        <span class="status-<?php echo $employee['employment_status']; ?>">
                                            <?php echo ucfirst($employee['employment_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($employee['hire_date'])); ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewEmployee(<?php echo $employee['employee_id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-warning" onclick="editEmployee(<?php echo $employee['employee_id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Employee Modal -->
    <div class="modal fade" id="addEmployeeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="phone" class="form-label">Phone</label>
                                    <input type="text" class="form-control" id="phone" name="phone">
                                </div>
                            </div>
                        </div>
                        <div class="form-group mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="date_of_birth" class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="hire_date" class="form-label">Hire Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="hire_date" name="hire_date" required value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="job_title" class="form-label">Job Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="job_title" name="job_title" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="department" class="form-label">Department <span class="text-danger">*</span></label>
                                    <select class="form-control" id="department" name="department" required>
                                        <option value="">Select Department...</option>
                                        <option value="Human Resources">Human Resources</option>
                                        <option value="Operations">Operations</option>
                                        <option value="Information Technology">Information Technology</option>
                                        <option value="Finance">Finance</option>
                                        <option value="Sales">Sales</option>
                                        <option value="Marketing">Marketing</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="shop_id" class="form-label">Shop Assignment</label>
                                    <select class="form-control" id="shop_id" name="shop_id">
                                        <option value="">Head Office</option>
                                        <?php foreach ($shops as $shop): ?>
                                        <option value="<?php echo $shop['shop_id']; ?>">
                                            <?php echo htmlspecialchars($shop['shop_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="employment_type" class="form-label">Employment Type</label>
                                    <select class="form-control" id="employment_type" name="employment_type" required>
                                        <option value="full_time">Full Time</option>
                                        <option value="part_time">Part Time</option>
                                        <option value="contract">Contract</option>
                                        <option value="temporary">Temporary</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group mb-3">
                                    <label for="salary" class="form-label">Annual Salary ($)</label>
                                    <input type="number" class="form-control" id="salary" name="salary" step="0.01" min="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-3">
                                    <label for="hourly_rate" class="form-label">Hourly Rate ($)</label>
                                    <input type="number" class="form-control" id="hourly_rate" name="hourly_rate" step="0.01" min="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-3">
                                    <label for="commission_rate" class="form-label">Commission Rate (%)</label>
                                    <input type="number" class="form-control" id="commission_rate" name="commission_rate" step="0.01" min="0" max="100">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_employee" class="btn btn-create">
                            <i class="fas fa-user-plus"></i> Add Employee
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewEmployee(employeeId) {
            // Implement view employee functionality
            window.location.href = 'employee_view.php?id=' + employeeId;
        }

        function editEmployee(employeeId) {
            // Implement edit employee functionality
            window.location.href = 'employee_edit.php?id=' + employeeId;
        }

        // Auto-refresh employees every 60 seconds
        setInterval(function() {
            if (!document.querySelector('.modal.show')) {
                location.reload();
            }
        }, 60000);
    </script>
</body>
</html>
