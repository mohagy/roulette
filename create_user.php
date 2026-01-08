<?php
// Set error reporting to show all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize variables
$message = '';
$messageType = '';
$accessKey = 'roulette_setup_2023'; // Simple access key for basic security
$accessGranted = false;

// Check if access key is provided in URL or POST
if (isset($_GET['key']) && $_GET['key'] === $accessKey) {
    $accessGranted = true;
} elseif (isset($_POST['access_key']) && $_POST['access_key'] === $accessKey) {
    $accessGranted = true;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accessGranted) {
    // Get form data
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $role = isset($_POST['role']) ? trim($_POST['role']) : 'cashier';
    $initialBalance = isset($_POST['initial_balance']) ? floatval($_POST['initial_balance']) : 0;

    // Validate input
    $errors = [];

    if (empty($username)) {
        $errors[] = "Username is required.";
    } elseif (strlen($username) !== 12 || !ctype_digit($username)) {
        $errors[] = "Username must be exactly 12 digits.";
    }

    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) !== 6 || !ctype_digit($password)) {
        $errors[] = "Password must be exactly 6 digits.";
    }

    if (!in_array($role, ['cashier', 'admin'])) {
        $errors[] = "Invalid role selected.";
    }

    if ($initialBalance < 0) {
        $errors[] = "Initial balance cannot be negative.";
    }

    // If no errors, proceed with user creation
    if (empty($errors)) {
        // Database connection parameters
        $servername = "localhost";
        $dbUsername = "root";  // Default XAMPP username
        $dbPassword = "";      // Default XAMPP password (empty)
        $dbname = "roulette";  // Using the roulette database

        try {
            // Create connection
            $conn = new mysqli($servername, $dbUsername, $dbPassword);

            // Check connection
            if ($conn->connect_error) {
                throw new Exception("Connection failed: " . $conn->connect_error);
            }

            // Check if database exists, if not create it
            $result = $conn->query("SHOW DATABASES LIKE '$dbname'");
            if ($result->num_rows == 0) {
                // Database doesn't exist, create it
                if (!$conn->query("CREATE DATABASE IF NOT EXISTS $dbname")) {
                    throw new Exception("Error creating database: " . $conn->error);
                }
            }

            // Select the database
            $conn->select_db($dbname);

            // Check if users table exists
            $tableCheck = $conn->query("SHOW TABLES LIKE 'users'");
            if ($tableCheck->num_rows == 0) {
                // Table doesn't exist, create it
                $createTable = "CREATE TABLE IF NOT EXISTS users (
                    user_id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(12) NOT NULL UNIQUE COMMENT 'Cashier 12-digit username',
                    password VARCHAR(255) NOT NULL COMMENT 'Hashed password (6-digit)',
                    role VARCHAR(20) NOT NULL DEFAULT 'cashier' COMMENT 'User role (cashier, admin, etc.)',
                    cash_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Current cash balance',
                    last_login TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )";

                if (!$conn->query($createTable)) {
                    throw new Exception("Error creating users table: " . $conn->error);
                }
            } else {
                // Check if cash_balance column exists
                $columnCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'cash_balance'");
                if ($columnCheck->num_rows == 0) {
                    // Add cash_balance column
                    if (!$conn->query("ALTER TABLE users ADD COLUMN cash_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER role")) {
                        throw new Exception("Error adding cash_balance column: " . $conn->error);
                    }
                }
            }

            // Check if username already exists
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                throw new Exception("Username already exists. Please choose a different username.");
            }

            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Insert new user
            $stmt = $conn->prepare("INSERT INTO users (username, password, role, cash_balance) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssd", $username, $hashedPassword, $role, $initialBalance);

            if ($stmt->execute()) {
                $message = "User created successfully!";
                $messageType = 'success';

                // Clear form data after successful submission
                $username = '';
                $password = '';
                $role = 'cashier';
                $initialBalance = 0;
            } else {
                throw new Exception("Error creating user: " . $stmt->error);
            }

            // Close connection
            $stmt->close();
            $conn->close();

        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = 'danger';
        }
    } else {
        $message = "Please fix the following errors:<br>" . implode("<br>", $errors);
        $messageType = 'danger';
    }
}

// Get existing users if access is granted
$users = [];
if ($accessGranted) {
    try {
        // Database connection
        $conn = new mysqli("localhost", "root", "", "roulette");

        // Check connection
        if (!$conn->connect_error) {
            // Check if users table exists
            $tableCheck = $conn->query("SHOW TABLES LIKE 'users'");
            if ($tableCheck->num_rows > 0) {
                // Get users
                $result = $conn->query("SELECT user_id, username, role, cash_balance, created_at FROM users ORDER BY created_at DESC");
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $users[] = $row;
                    }
                }
            }
            $conn->close();
        }
    } catch (Exception $e) {
        // Silently fail - we'll just show an empty user list
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create User - Roulette POS</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 30px;
        }
        .card-header {
            background-color: #4e73df;
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 1rem 1.25rem;
            font-weight: 700;
        }
        .card-body {
            padding: 1.5rem;
        }
        .form-group label {
            font-weight: 600;
            color: #5a5c69;
        }
        .btn-primary {
            background-color: #4e73df;
            border-color: #4e73df;
        }
        .btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2e59d9;
        }
        .alert-success {
            color: #0f6848;
            background-color: #d1e7dd;
            border-color: #badbcc;
        }
        .alert-danger {
            color: #842029;
            background-color: #f8d7da;
            border-color: #f5c2c7;
        }
        .table {
            margin-bottom: 0;
        }
        .table th {
            background-color: #f8f9fc;
            color: #4e73df;
            font-weight: 700;
        }
        .badge-primary {
            background-color: #4e73df;
        }
        .badge-success {
            background-color: #1cc88a;
        }
        .access-form {
            max-width: 500px;
            margin: 50px auto;
            text-align: center;
        }
        .logo {
            margin-bottom: 20px;
            font-size: 24px;
            font-weight: 700;
            color: #4e73df;
        }
        .logo i {
            margin-right: 10px;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #4e73df;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!$accessGranted): ?>
        <!-- Access Form -->
        <div class="access-form">
            <div class="logo">
                <i class="fas fa-dice"></i> Roulette POS System
            </div>
            <div class="card">
                <div class="card-header">
                    Access Required
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="access_key">Access Key:</label>
                            <input type="password" class="form-control" id="access_key" name="access_key" required>
                            <small class="form-text text-muted">Enter the access key to create users.</small>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">Access User Creation</button>
                    </form>
                </div>
            </div>
            <a href="index.html" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Game
            </a>
        </div>
        <?php else: ?>
        <!-- User Creation Form -->
        <div class="row">
            <div class="col-lg-12">
                <h1 class="mb-4">
                    <i class="fas fa-user-plus"></i> Create User
                    <a href="index.html" class="btn btn-outline-primary btn-sm float-right">
                        <i class="fas fa-arrow-left"></i> Back to Game
                    </a>
                </h1>

                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-user-plus"></i> Create New User
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <input type="hidden" name="access_key" value="<?php echo htmlspecialchars($accessKey); ?>">

                            <div class="form-group">
                                <label for="username">Username (12 digits):</label>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username ?? ''); ?>" maxlength="12" pattern="\d{12}" required>
                                <small class="form-text text-muted">Enter a 12-digit username for the user.</small>
                            </div>

                            <div class="form-group">
                                <label for="password">Password (6 digits):</label>
                                <input type="password" class="form-control" id="password" name="password" maxlength="6" pattern="\d{6}" required>
                                <small class="form-text text-muted">Enter a 6-digit password for the user.</small>
                            </div>

                            <div class="form-group">
                                <label for="role">Role:</label>
                                <select class="form-control" id="role" name="role">
                                    <option value="cashier" <?php echo (isset($role) && $role === 'cashier') ? 'selected' : ''; ?>>Cashier</option>
                                    <option value="admin" <?php echo (isset($role) && $role === 'admin') ? 'selected' : ''; ?>>Admin</option>
                                </select>
                                <small class="form-text text-muted">Select the user's role.</small>
                            </div>

                            <div class="form-group">
                                <label for="initial_balance">Initial Cash Balance:</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">$</span>
                                    </div>
                                    <input type="number" class="form-control" id="initial_balance" name="initial_balance" value="<?php echo htmlspecialchars($initialBalance ?? 0); ?>" min="0" step="0.01">
                                </div>
                                <small class="form-text text-muted">Enter the initial cash balance for the user.</small>
                            </div>

                            <button type="submit" class="btn btn-primary">Create User</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-users"></i> Existing Users
                    </div>
                    <div class="card-body">
                        <?php if (empty($users)): ?>
                        <div class="alert alert-info">No users found. Create your first user using the form.</div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Role</th>
                                        <th>Balance</th>
                                        <th>Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td>
                                            <?php if ($user['role'] === 'admin'): ?>
                                            <span class="badge badge-primary">Admin</span>
                                            <?php else: ?>
                                            <span class="badge badge-success">Cashier</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>$<?php echo number_format($user['cash_balance'], 2); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <i class="fas fa-info-circle"></i> Instructions
                    </div>
                    <div class="card-body">
                        <h5>User Creation Guidelines:</h5>
                        <ul>
                            <li>Username must be exactly 12 digits</li>
                            <li>Password must be exactly 6 digits</li>
                            <li>Cashier role is for regular users</li>
                            <li>Admin role has access to the admin panel</li>
                            <li>Initial balance is the starting cash amount</li>
                        </ul>
                        <p class="mb-0">
                            <strong>Note:</strong> This page is for initial setup or emergency user creation.
                            For regular user management, please use the admin panel.
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
