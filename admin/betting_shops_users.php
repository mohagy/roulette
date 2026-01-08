<?php
// Betting Shop Users Management
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Check if shop ID is provided
if (!isset($_GET['shop_id']) || !is_numeric($_GET['shop_id'])) {
    header('Location: betting_shops.php');
    exit;
}

$shop_id = intval($_GET['shop_id']);

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

// Get shop details
$shop = null;
$stmt = $conn->prepare("SELECT * FROM betting_shops WHERE shop_id = ?");
$stmt->bind_param("i", $shop_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: betting_shops.php');
    exit;
}

$shop = $result->fetch_assoc();

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['assign_user'])) {
            // Assign user to shop
            $user_id = intval($_POST['user_id']);
            $role = $_POST['role'];
            $assigned_by = $_SESSION['user_id'];

            // Check if user is already assigned to this shop
            $stmt = $conn->prepare("SELECT id FROM shop_users WHERE shop_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $shop_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                throw new Exception("User is already assigned to this shop.");
            }

            // Insert new assignment
            $stmt = $conn->prepare("INSERT INTO shop_users (shop_id, user_id, role, assigned_by) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iisi", $shop_id, $user_id, $role, $assigned_by);
            
            if ($stmt->execute()) {
                // Update user's shop_id in users table
                $stmt = $conn->prepare("UPDATE users SET shop_id = ? WHERE user_id = ?");
                $stmt->bind_param("ii", $shop_id, $user_id);
                $stmt->execute();

                $message = "User assigned to shop successfully!";
                $messageType = "success";
            } else {
                throw new Exception("Error assigning user: " . $stmt->error);
            }

        } elseif (isset($_POST['update_user'])) {
            // Update user role or status
            $assignment_id = intval($_POST['assignment_id']);
            $role = $_POST['role'];
            $status = $_POST['status'];

            $stmt = $conn->prepare("UPDATE shop_users SET role = ?, status = ? WHERE id = ? AND shop_id = ?");
            $stmt->bind_param("ssii", $role, $status, $assignment_id, $shop_id);
            
            if ($stmt->execute()) {
                $message = "User assignment updated successfully!";
                $messageType = "success";
            } else {
                throw new Exception("Error updating user: " . $stmt->error);
            }

        } elseif (isset($_POST['remove_user'])) {
            // Remove user from shop
            $assignment_id = intval($_POST['assignment_id']);
            $user_id = intval($_POST['user_id']);

            $stmt = $conn->prepare("DELETE FROM shop_users WHERE id = ? AND shop_id = ?");
            $stmt->bind_param("ii", $assignment_id, $shop_id);
            
            if ($stmt->execute()) {
                // Remove shop_id from users table
                $stmt = $conn->prepare("UPDATE users SET shop_id = NULL WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();

                $message = "User removed from shop successfully!";
                $messageType = "success";
            } else {
                throw new Exception("Error removing user: " . $stmt->error);
            }
        }

    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Get assigned users
$assigned_users = [];
$stmt = $conn->prepare("
    SELECT su.*, u.username, u.role as user_role, u.cash_balance, u.created_at as user_created, u.last_login
    FROM shop_users su
    JOIN users u ON su.user_id = u.user_id
    WHERE su.shop_id = ?
    ORDER BY su.assigned_at DESC
");
$stmt->bind_param("i", $shop_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $assigned_users[] = $row;
}

// Get available users (not assigned to any shop or assigned to this shop but inactive)
$available_users = [];
$stmt = $conn->prepare("
    SELECT u.user_id, u.username, u.role, u.cash_balance, u.created_at
    FROM users u
    LEFT JOIN shop_users su ON u.user_id = su.user_id AND su.status = 'active'
    WHERE u.role IN ('cashier', 'manager', 'supervisor') 
    AND (su.user_id IS NULL OR u.shop_id IS NULL OR u.shop_id = ?)
    AND u.user_id NOT IN (
        SELECT user_id FROM shop_users WHERE shop_id = ? AND status = 'active'
    )
    ORDER BY u.username
");
$stmt->bind_param("ii", $shop_id, $shop_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $available_users[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - <?php echo htmlspecialchars($shop['shop_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        .shop-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .user-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            transition: transform 0.2s ease;
        }
        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #4e73df;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-weight: 600;
        }
        .status-active { background: #28a745; color: white; }
        .status-inactive { background: #6c757d; color: white; }
        .role-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-weight: 600;
        }
        .role-manager { background: #e74a3b; color: white; }
        .role-supervisor { background: #f39c12; color: white; }
        .role-cashier { background: #3498db; color: white; }
        .assign-form {
            background: #f8f9fc;
            border-radius: 10px;
            padding: 1.5rem;
            border-left: 4px solid #28a745;
        }
        .quick-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .btn-sm {
            padding: 0.25rem 0.75rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
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

        <div class="content-wrapper">
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-users-cog"></i> Manage Shop Users
                </h1>
                <div class="breadcrumb">
                    <div class="breadcrumb-item"><a href="index.php">Admin</a></div>
                    <div class="breadcrumb-item"><a href="betting_shops.php">Betting Shops</a></div>
                    <div class="breadcrumb-item"><a href="betting_shops_view.php?id=<?php echo $shop['shop_id']; ?>"><?php echo htmlspecialchars($shop['shop_code']); ?></a></div>
                    <div class="breadcrumb-item active">Manage Users</div>
                </div>
                <div class="page-actions">
                    <a href="betting_shops_view.php?id=<?php echo $shop['shop_id']; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Shop
                    </a>
                </div>
            </div>

            <!-- Shop Header -->
            <div class="shop-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h3 class="mb-1"><?php echo htmlspecialchars($shop['shop_name']); ?></h3>
                        <p class="mb-0">
                            <strong>Code:</strong> <?php echo htmlspecialchars($shop['shop_code']); ?> |
                            <strong>Location:</strong> <?php echo htmlspecialchars($shop['city']); ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="h4 mb-0"><?php echo count($assigned_users); ?> Users Assigned</div>
                        <small>Active: <?php echo count(array_filter($assigned_users, function($u) { return $u['status'] === 'active'; })); ?></small>
                    </div>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="row">
                <!-- Assign New User -->
                <div class="col-lg-4">
                    <div class="card shadow">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold text-success">
                                <i class="fas fa-user-plus"></i> Assign New User
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($available_users)): ?>
                                <p class="text-muted text-center">No available users to assign.</p>
                                <div class="text-center">
                                    <a href="users.php" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus"></i> Create New User
                                    </a>
                                </div>
                            <?php else: ?>
                                <form method="post" action="">
                                    <div class="form-group mb-3">
                                        <label for="user_id" class="form-label">Select User</label>
                                        <select class="form-control" id="user_id" name="user_id" required>
                                            <option value="">Choose a user...</option>
                                            <?php foreach ($available_users as $user): ?>
                                            <option value="<?php echo $user['user_id']; ?>">
                                                <?php echo htmlspecialchars($user['username']); ?> 
                                                (<?php echo ucfirst($user['role']); ?>) - 
                                                $<?php echo number_format($user['cash_balance'], 2); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group mb-3">
                                        <label for="role" class="form-label">Shop Role</label>
                                        <select class="form-control" id="role" name="role" required>
                                            <option value="cashier">Cashier</option>
                                            <option value="supervisor">Supervisor</option>
                                            <option value="manager">Manager</option>
                                        </select>
                                    </div>
                                    <button type="submit" name="assign_user" class="btn btn-success btn-block">
                                        <i class="fas fa-user-plus"></i> Assign User
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Assigned Users -->
                <div class="col-lg-8">
                    <div class="card shadow">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-users"></i> Assigned Users (<?php echo count($assigned_users); ?>)
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($assigned_users)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No Users Assigned</h5>
                                    <p class="text-muted">Start by assigning users to this betting shop.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Shop Role</th>
                                                <th>Status</th>
                                                <th>Balance</th>
                                                <th>Assigned</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($assigned_users as $user): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="user-avatar me-3">
                                                            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                                        </div>
                                                        <div>
                                                            <div class="fw-bold"><?php echo htmlspecialchars($user['username']); ?></div>
                                                            <small class="text-muted">
                                                                System Role: <?php echo ucfirst($user['user_role']); ?>
                                                            </small>
                                                            <?php if ($user['last_login']): ?>
                                                            <br><small class="text-muted">
                                                                Last login: <?php echo date('M d, Y', strtotime($user['last_login'])); ?>
                                                            </small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="role-badge role-<?php echo $user['role']; ?>">
                                                        <?php echo ucfirst($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="status-badge status-<?php echo $user['status']; ?>">
                                                        <?php echo ucfirst($user['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong>$<?php echo number_format($user['cash_balance'], 2); ?></strong>
                                                </td>
                                                <td>
                                                    <small><?php echo date('M d, Y', strtotime($user['assigned_at'])); ?></small>
                                                </td>
                                                <td>
                                                    <div class="quick-actions">
                                                        <button class="btn btn-sm btn-warning" onclick="editUser(<?php echo $user['id']; ?>, '<?php echo $user['role']; ?>', '<?php echo $user['status']; ?>')">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-danger" onclick="removeUser(<?php echo $user['id']; ?>, <?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                            <i class="fas fa-trash"></i>
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
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User Assignment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <input type="hidden" id="edit_assignment_id" name="assignment_id">
                        <div class="form-group mb-3">
                            <label for="edit_role" class="form-label">Shop Role</label>
                            <select class="form-control" id="edit_role" name="role" required>
                                <option value="cashier">Cashier</option>
                                <option value="supervisor">Supervisor</option>
                                <option value="manager">Manager</option>
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label for="edit_status" class="form-label">Status</label>
                            <select class="form-control" id="edit_status" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_user" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Remove User Modal -->
    <div class="modal fade" id="removeUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Remove User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <input type="hidden" id="remove_assignment_id" name="assignment_id">
                        <input type="hidden" id="remove_user_id" name="user_id">
                        <p>Are you sure you want to remove <strong id="remove_username"></strong> from this shop?</p>
                        <p class="text-muted">This will unassign the user from the shop but will not delete their account.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="remove_user" class="btn btn-danger">Remove User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editUser(assignmentId, role, status) {
            document.getElementById('edit_assignment_id').value = assignmentId;
            document.getElementById('edit_role').value = role;
            document.getElementById('edit_status').value = status;
            
            const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
            modal.show();
        }

        function removeUser(assignmentId, userId, username) {
            document.getElementById('remove_assignment_id').value = assignmentId;
            document.getElementById('remove_user_id').value = userId;
            document.getElementById('remove_username').textContent = username;
            
            const modal = new bootstrap.Modal(document.getElementById('removeUserModal'));
            modal.show();
        }
    </script>
</body>
</html>
