<?php
// Start session
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Redirect to login page if not logged in or not an admin
    header('Location: ../login.php');
    exit;
}

// Database connection parameters
$servername = "localhost";
$username = "root";  // Default XAMPP username
$password = "";      // Default XAMPP password (empty)
$dbname = "roulette";  // Using the roulette database

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if required tables exist
$requiredTables = ['users', 'transactions'];
$missingTables = [];

foreach ($requiredTables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows == 0) {
        $missingTables[] = $table;
    }
}

// If any tables are missing, redirect to the setup script
if (!empty($missingTables)) {
    header("Location: db_setup.php");
    exit;
}

// Process form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Add new user
        if ($_POST['action'] === 'add_user') {
            $newUsername = isset($_POST['username']) ? trim($_POST['username']) : '';
            $newPassword = isset($_POST['password']) ? trim($_POST['password']) : '';
            $newRole = isset($_POST['role']) ? trim($_POST['role']) : 'cashier';
            $initialBalance = isset($_POST['initial_balance']) ? floatval($_POST['initial_balance']) : 0;

            // Validate input
            if (strlen($newUsername) !== 12 || !ctype_digit($newUsername)) {
                $message = "Username must be exactly 12 digits.";
                $messageType = 'danger';
            } elseif (strlen($newPassword) !== 6 || !ctype_digit($newPassword)) {
                $message = "Password must be exactly 6 digits.";
                $messageType = 'danger';
            } else {
                // Check if username already exists
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
                $stmt->bind_param("s", $newUsername);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $message = "Username already exists.";
                    $messageType = 'danger';
                } else {
                    // Hash password
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

                    // Insert new user
                    $stmt = $conn->prepare("INSERT INTO users (username, password, role, cash_balance) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("sssd", $newUsername, $hashedPassword, $newRole, $initialBalance);

                    if ($stmt->execute()) {
                        $message = "User added successfully.";
                        $messageType = 'success';
                    } else {
                        $message = "Error adding user: " . $stmt->error;
                        $messageType = 'danger';
                    }
                }
            }
        }
        // Update user
        elseif ($_POST['action'] === 'update_user' && isset($_POST['user_id'])) {
            $userId = intval($_POST['user_id']);
            $newRole = isset($_POST['role']) ? trim($_POST['role']) : '';
            $newPassword = isset($_POST['password']) ? trim($_POST['password']) : '';

            // Update role
            if (!empty($newRole)) {
                $stmt = $conn->prepare("UPDATE users SET role = ? WHERE user_id = ?");
                $stmt->bind_param("si", $newRole, $userId);

                if (!$stmt->execute()) {
                    $message = "Error updating role: " . $stmt->error;
                    $messageType = 'danger';
                } else {
                    $message = "User updated successfully.";
                    $messageType = 'success';
                }
            }

            // Update password if provided
            if (!empty($newPassword)) {
                if (strlen($newPassword) !== 6 || !ctype_digit($newPassword)) {
                    $message = "Password must be exactly 6 digits.";
                    $messageType = 'danger';
                } else {
                    // Hash password
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

                    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                    $stmt->bind_param("si", $hashedPassword, $userId);

                    if (!$stmt->execute()) {
                        $message = "Error updating password: " . $stmt->error;
                        $messageType = 'danger';
                    } else {
                        $message = "User updated successfully.";
                        $messageType = 'success';
                    }
                }
            }
        }
        // Delete user
        elseif ($_POST['action'] === 'delete_user' && isset($_POST['user_id'])) {
            $userId = intval($_POST['user_id']);

            // Check if user has transactions
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM transactions WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();

            if ($row['count'] > 0) {
                $message = "Cannot delete user with transactions. Consider deactivating instead.";
                $messageType = 'warning';
            } else {
                // Delete user
                $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->bind_param("i", $userId);

                if ($stmt->execute()) {
                    $message = "User deleted successfully.";
                    $messageType = 'success';
                } else {
                    $message = "Error deleting user: " . $stmt->error;
                    $messageType = 'danger';
                }
            }
        }
    }
}

// Get all users
$users = [];
$result = $conn->query("SELECT user_id, username, role, cash_balance, last_login, created_at FROM users ORDER BY created_at DESC");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Roulette POS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        /* Additional styles for this page */
        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th, .table td {
            padding: 0.75rem;
            vertical-align: middle;
            border-top: 1px solid #e3e6f0;
        }

        .table thead th {
            vertical-align: bottom;
            border-bottom: 2px solid #e3e6f0;
            background-color: #f8f9fc;
            color: #4e73df;
            font-weight: 700;
        }

        .table tbody tr:hover {
            background-color: #f8f9fc;
        }

        .btn {
            display: inline-block;
            font-weight: 400;
            color: #858796;
            text-align: center;
            vertical-align: middle;
            user-select: none;
            background-color: transparent;
            border: 1px solid transparent;
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            line-height: 1.5;
            border-radius: 0.25rem;
            transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            cursor: pointer;
        }

        .btn-primary {
            color: #fff;
            background-color: #4e73df;
            border-color: #4e73df;
        }

        .btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2653d4;
        }

        .btn-danger {
            color: #fff;
            background-color: #e74a3b;
            border-color: #e74a3b;
        }

        .btn-danger:hover {
            background-color: #be3c2d;
            border-color: #be3c2d;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            line-height: 1.5;
            border-radius: 0.2rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-control {
            display: block;
            width: 100%;
            height: calc(1.5em + 0.75rem + 2px);
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            font-weight: 400;
            line-height: 1.5;
            color: #6e707e;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid #d1d3e2;
            border-radius: 0.25rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .form-control:focus {
            color: #6e707e;
            background-color: #fff;
            border-color: #bac8f3;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }

        .alert {
            position: relative;
            padding: 0.75rem 1.25rem;
            margin-bottom: 1rem;
            border: 1px solid transparent;
            border-radius: 0.25rem;
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

        .alert-warning {
            color: #664d03;
            background-color: #fff3cd;
            border-color: #ffecb5;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 50%;
            border-radius: 0.3rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e3e6f0;
        }

        .modal-title {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 700;
            color: #4e73df;
        }

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
        }

        .modal-body {
            padding: 1rem 0;
        }

        .modal-footer {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-top: 1rem;
            border-top: 1px solid #e3e6f0;
        }

        .modal-footer .btn {
            margin-left: 0.5rem;
        }

        .badge {
            display: inline-block;
            padding: 0.25em 0.4em;
            font-size: 75%;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
        }

        .badge-primary {
            color: #fff;
            background-color: #4e73df;
        }

        .badge-success {
            color: #fff;
            background-color: #1cc88a;
        }

        .badge-warning {
            color: #fff;
            background-color: #f6c23e;
        }

        .badge-danger {
            color: #fff;
            background-color: #e74a3b;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <nav class="top-navbar">
            <div class="navbar-search">
                <input type="text" class="search-input" placeholder="Search users...">
            </div>

            <ul class="navbar-nav">
                <li class="nav-item">
                    <a href="../logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">User Management</h1>
                <div class="breadcrumb">
                    <div class="breadcrumb-item"><a href="index.php">Admin</a></div>
                    <div class="breadcrumb-item active">Users</div>
                </div>
            </div>

            <!-- Alert Message -->
            <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <!-- Add User Button -->
            <div style="margin-bottom: 1rem;">
                <button class="btn btn-primary" onclick="openAddUserModal()">
                    <i class="fas fa-user-plus"></i> Add New User
                </button>
            </div>

            <!-- Users Table -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">All Users</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Cash Balance</th>
                                    <th>Last Login</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['user_id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td>
                                        <?php if ($user['role'] === 'admin'): ?>
                                        <span class="badge badge-primary">Admin</span>
                                        <?php elseif ($user['role'] === 'cashier'): ?>
                                        <span class="badge badge-success">Cashier</span>
                                        <?php else: ?>
                                        <span class="badge badge-secondary"><?php echo htmlspecialchars($user['role']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>$<?php echo number_format($user['cash_balance'], 2); ?></td>
                                    <td><?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-primary btn-sm" onclick="openEditUserModal(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo htmlspecialchars($user['role']); ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="openDeleteUserModal(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Add New User</h2>
                <span class="close" onclick="closeAddUserModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="addUserForm" method="post" action="">
                    <input type="hidden" name="action" value="add_user">
                    <div class="form-group">
                        <label for="username">Username (12 digits):</label>
                        <input type="text" class="form-control" id="username" name="username" maxlength="12" pattern="\d{12}" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password (6 digits):</label>
                        <input type="password" class="form-control" id="password" name="password" maxlength="6" pattern="\d{6}" required>
                    </div>
                    <div class="form-group">
                        <label for="role">Role:</label>
                        <select class="form-control" id="role" name="role" required>
                            <option value="cashier">Cashier</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="initial_balance">Initial Cash Balance:</label>
                        <input type="number" class="form-control" id="initial_balance" name="initial_balance" min="0" step="0.01" value="0.00">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddUserModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="document.getElementById('addUserForm').submit()">Add User</button>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Edit User</h2>
                <span class="close" onclick="closeEditUserModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="editUserForm" method="post" action="">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" id="edit_user_id" name="user_id" value="">
                    <div class="form-group">
                        <label for="edit_username">Username:</label>
                        <input type="text" class="form-control" id="edit_username" disabled>
                    </div>
                    <div class="form-group">
                        <label for="edit_password">New Password (6 digits, leave empty to keep current):</label>
                        <input type="password" class="form-control" id="edit_password" name="password" maxlength="6" pattern="\d{6}">
                    </div>
                    <div class="form-group">
                        <label for="edit_role">Role:</label>
                        <select class="form-control" id="edit_role" name="role" required>
                            <option value="cashier">Cashier</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditUserModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="document.getElementById('editUserForm').submit()">Update User</button>
            </div>
        </div>
    </div>

    <!-- Delete User Modal -->
    <div id="deleteUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Delete User</h2>
                <span class="close" onclick="closeDeleteUserModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete user <span id="delete_username"></span>?</p>
                <p class="text-danger">This action cannot be undone.</p>
                <form id="deleteUserForm" method="post" action="">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" id="delete_user_id" name="user_id" value="">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteUserModal()">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="document.getElementById('deleteUserForm').submit()">Delete User</button>
            </div>
        </div>
    </div>

    <script>
        // Add User Modal
        function openAddUserModal() {
            document.getElementById('addUserModal').style.display = 'block';
        }

        function closeAddUserModal() {
            document.getElementById('addUserModal').style.display = 'none';
        }

        // Edit User Modal
        function openEditUserModal(userId, username, role) {
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_role').value = role;
            document.getElementById('edit_password').value = '';
            document.getElementById('editUserModal').style.display = 'block';
        }

        function closeEditUserModal() {
            document.getElementById('editUserModal').style.display = 'none';
        }

        // Delete User Modal
        function openDeleteUserModal(userId, username) {
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('delete_username').textContent = username;
            document.getElementById('deleteUserModal').style.display = 'block';
        }

        function closeDeleteUserModal() {
            document.getElementById('deleteUserModal').style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target == document.getElementById('addUserModal')) {
                closeAddUserModal();
            }
            if (event.target == document.getElementById('editUserModal')) {
                closeEditUserModal();
            }
            if (event.target == document.getElementById('deleteUserModal')) {
                closeDeleteUserModal();
            }
        }
    </script>
</body>
</html>
