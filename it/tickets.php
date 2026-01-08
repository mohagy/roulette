<?php
// IT Help Desk Tickets Management
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Check if user has IT department access
$user_role = $_SESSION['role'] ?? '';
$allowed_roles = ['admin', 'it_manager', 'it_staff'];

// For now, allow admin access - later implement proper department role checking
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
        if (isset($_POST['create_ticket'])) {
            // Create new ticket
            $shop_id = !empty($_POST['shop_id']) ? intval($_POST['shop_id']) : null;
            $equipment_id = !empty($_POST['equipment_id']) ? intval($_POST['equipment_id']) : null;
            $title = trim($_POST['title']);
            $description = trim($_POST['description']);
            $category = $_POST['category'];
            $priority = $_POST['priority'];
            $reported_by = $_SESSION['user_id'];

            // Generate ticket number
            $ticket_number = 'IT' . date('Ymd') . sprintf('%04d', rand(1, 9999));

            // Check if ticket number exists
            $stmt = $conn->prepare("SELECT ticket_id FROM it_tickets WHERE ticket_number = ?");
            $stmt->bind_param("s", $ticket_number);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $ticket_number = 'IT' . date('Ymd') . sprintf('%04d', rand(1, 9999));
            }

            // Insert new ticket
            $stmt = $conn->prepare("INSERT INTO it_tickets (
                ticket_number, shop_id, equipment_id, title, description, 
                category, priority, reported_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->bind_param("siissssi", 
                $ticket_number, $shop_id, $equipment_id, $title, $description,
                $category, $priority, $reported_by
            );

            if ($stmt->execute()) {
                $message = "Ticket #{$ticket_number} created successfully!";
                $messageType = "success";
            } else {
                throw new Exception("Error creating ticket: " . $stmt->error);
            }

        } elseif (isset($_POST['update_ticket'])) {
            // Update ticket status/assignment
            $ticket_id = intval($_POST['ticket_id']);
            $status = $_POST['status'];
            $assigned_to = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null;
            $resolution_notes = trim($_POST['resolution_notes']);

            $update_fields = "status = ?, assigned_to = ?";
            $params = [$status, $assigned_to];
            $types = "si";

            if ($status === 'resolved' || $status === 'closed') {
                $update_fields .= ", resolved_at = NOW(), resolution_notes = ?";
                $params[] = $resolution_notes;
                $types .= "s";
            }

            $stmt = $conn->prepare("UPDATE it_tickets SET {$update_fields} WHERE ticket_id = ?");
            $params[] = $ticket_id;
            $types .= "i";
            
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                $message = "Ticket updated successfully!";
                $messageType = "success";
            } else {
                throw new Exception("Error updating ticket: " . $stmt->error);
            }
        }

    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Get filter parameters
$filter_status = $_GET['status'] ?? 'all';
$filter_priority = $_GET['priority'] ?? 'all';
$filter_category = $_GET['category'] ?? 'all';

// Build WHERE clause for filters
$where_conditions = [];
$params = [];
$types = "";

if ($filter_status !== 'all') {
    $where_conditions[] = "t.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if ($filter_priority !== 'all') {
    $where_conditions[] = "t.priority = ?";
    $params[] = $filter_priority;
    $types .= "s";
}

if ($filter_category !== 'all') {
    $where_conditions[] = "t.category = ?";
    $params[] = $filter_category;
    $types .= "s";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get all tickets with filters
$tickets = [];
$sql = "SELECT 
            t.*,
            bs.shop_name,
            e.equipment_name,
            reporter.username as reporter_name,
            assignee.username as assignee_name
        FROM it_tickets t
        LEFT JOIN betting_shops bs ON t.shop_id = bs.shop_id
        LEFT JOIN equipment e ON t.equipment_id = e.equipment_id
        LEFT JOIN users reporter ON t.reported_by = reporter.user_id
        LEFT JOIN users assignee ON t.assigned_to = assignee.user_id
        {$where_clause}
        ORDER BY 
            CASE t.priority 
                WHEN 'critical' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                WHEN 'low' THEN 4 
            END,
            t.created_at DESC";

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
        $tickets[] = $row;
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

// Get equipment for dropdown
$equipment = [];
$result = $conn->query("SELECT equipment_id, equipment_name, equipment_type FROM equipment WHERE status != 'retired' ORDER BY equipment_name");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $equipment[] = $row;
    }
}

// Get IT staff for assignment
$it_staff = [];
$result = $conn->query("SELECT user_id, username FROM users WHERE role IN ('admin', 'it_manager', 'it_staff') ORDER BY username");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $it_staff[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Help Desk Tickets</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
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
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
        }
        .priority-critical { 
            background: #dc3545; 
            color: white; 
            padding: 4px 8px; 
            border-radius: 12px; 
            font-size: 0.75rem;
            font-weight: bold;
        }
        .priority-high { 
            background: #fd7e14; 
            color: white; 
            padding: 4px 8px; 
            border-radius: 12px; 
            font-size: 0.75rem;
            font-weight: bold;
        }
        .priority-medium { 
            background: #ffc107; 
            color: #212529; 
            padding: 4px 8px; 
            border-radius: 12px; 
            font-size: 0.75rem;
            font-weight: bold;
        }
        .priority-low { 
            background: #28a745; 
            color: white; 
            padding: 4px 8px; 
            border-radius: 12px; 
            font-size: 0.75rem;
            font-weight: bold;
        }
        .status-open { color: #dc3545; font-weight: bold; }
        .status-assigned { color: #fd7e14; font-weight: bold; }
        .status-in_progress { color: #007bff; font-weight: bold; }
        .status-resolved { color: #28a745; font-weight: bold; }
        .status-closed { color: #6c757d; font-weight: bold; }
        .ticket-row {
            transition: all 0.3s ease;
        }
        .ticket-row:hover {
            background: rgba(30, 60, 114, 0.05);
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
            <h4 class="text-primary"><i class="fas fa-laptop"></i> IT Dept</h4>
        </div>
        <nav class="nav flex-column px-3">
            <a class="nav-link" href="index.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a class="nav-link active" href="tickets.php">
                <i class="fas fa-ticket-alt"></i> Help Desk Tickets
            </a>
            <a class="nav-link" href="equipment.php">
                <i class="fas fa-desktop"></i> Equipment Management
            </a>
            <a class="nav-link" href="maintenance.php">
                <i class="fas fa-tools"></i> Maintenance Schedule
            </a>
            <a class="nav-link" href="projects.php">
                <i class="fas fa-project-diagram"></i> IT Projects
            </a>
            <a class="nav-link" href="staff.php">
                <i class="fas fa-users"></i> Staff Management
            </a>
            <a class="nav-link" href="assets.php">
                <i class="fas fa-inventory"></i> Asset Management
            </a>
            <a class="nav-link" href="monitoring.php">
                <i class="fas fa-chart-line"></i> Network Monitoring
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
                <h1 class="text-white">IT Help Desk Tickets</h1>
                <p class="text-white-50 mb-0">Manage support tickets and technical issues</p>
            </div>
            <div>
                <button class="btn btn-create" data-bs-toggle="modal" data-bs-target="#createTicketModal">
                    <i class="fas fa-plus"></i> Create New Ticket
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
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="open" <?php echo $filter_status === 'open' ? 'selected' : ''; ?>>Open</option>
                        <option value="assigned" <?php echo $filter_status === 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                        <option value="in_progress" <?php echo $filter_status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="resolved" <?php echo $filter_status === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                        <option value="closed" <?php echo $filter_status === 'closed' ? 'selected' : ''; ?>>Closed</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="priority" class="form-label">Priority</label>
                    <select class="form-select" id="priority" name="priority">
                        <option value="all" <?php echo $filter_priority === 'all' ? 'selected' : ''; ?>>All Priorities</option>
                        <option value="critical" <?php echo $filter_priority === 'critical' ? 'selected' : ''; ?>>Critical</option>
                        <option value="high" <?php echo $filter_priority === 'high' ? 'selected' : ''; ?>>High</option>
                        <option value="medium" <?php echo $filter_priority === 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="low" <?php echo $filter_priority === 'low' ? 'selected' : ''; ?>>Low</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="category" class="form-label">Category</label>
                    <select class="form-select" id="category" name="category">
                        <option value="all" <?php echo $filter_category === 'all' ? 'selected' : ''; ?>>All Categories</option>
                        <option value="hardware" <?php echo $filter_category === 'hardware' ? 'selected' : ''; ?>>Hardware</option>
                        <option value="software" <?php echo $filter_category === 'software' ? 'selected' : ''; ?>>Software</option>
                        <option value="network" <?php echo $filter_category === 'network' ? 'selected' : ''; ?>>Network</option>
                        <option value="security" <?php echo $filter_category === 'security' ? 'selected' : ''; ?>>Security</option>
                        <option value="other" <?php echo $filter_category === 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="tickets.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Tickets Table -->
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-list"></i> Support Tickets (<?php echo count($tickets); ?>)
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($tickets)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-ticket-alt fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Tickets Found</h5>
                        <p class="text-muted">No tickets match your current filters.</p>
                        <button class="btn btn-create" data-bs-toggle="modal" data-bs-target="#createTicketModal">
                            <i class="fas fa-plus"></i> Create First Ticket
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Ticket #</th>
                                    <th>Title</th>
                                    <th>Shop/Equipment</th>
                                    <th>Category</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Assigned To</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tickets as $ticket): ?>
                                <tr class="ticket-row">
                                    <td>
                                        <strong><?php echo htmlspecialchars($ticket['ticket_number']); ?></strong>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($ticket['title']); ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars(substr($ticket['description'], 0, 50)) . (strlen($ticket['description']) > 50 ? '...' : ''); ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($ticket['shop_name']): ?>
                                            <div><i class="fas fa-store text-primary"></i> <?php echo htmlspecialchars($ticket['shop_name']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($ticket['equipment_name']): ?>
                                            <div><i class="fas fa-desktop text-info"></i> <?php echo htmlspecialchars($ticket['equipment_name']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!$ticket['shop_name'] && !$ticket['equipment_name']): ?>
                                            <span class="text-muted">General</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo ucfirst($ticket['category']); ?></span>
                                    </td>
                                    <td>
                                        <span class="priority-<?php echo $ticket['priority']; ?>">
                                            <?php echo ucfirst($ticket['priority']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-<?php echo $ticket['status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($ticket['assignee_name']): ?>
                                            <i class="fas fa-user text-success"></i> <?php echo htmlspecialchars($ticket['assignee_name']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small>
                                            <?php echo date('M d, Y', strtotime($ticket['created_at'])); ?>
                                            <br>
                                            <?php echo date('g:i A', strtotime($ticket['created_at'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewTicket(<?php echo $ticket['ticket_id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-warning" onclick="editTicket(<?php echo $ticket['ticket_id']; ?>)">
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

    <!-- Create Ticket Modal -->
    <div class="modal fade" id="createTicketModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Support Ticket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="title" name="title" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="category" class="form-label">Category</label>
                                    <select class="form-control" id="category" name="category" required>
                                        <option value="hardware">Hardware</option>
                                        <option value="software">Software</option>
                                        <option value="network">Network</option>
                                        <option value="security">Security</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="priority" class="form-label">Priority</label>
                                    <select class="form-control" id="priority" name="priority" required>
                                        <option value="low">Low</option>
                                        <option value="medium" selected>Medium</option>
                                        <option value="high">High</option>
                                        <option value="critical">Critical</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="shop_id" class="form-label">Shop (Optional)</label>
                                    <select class="form-control" id="shop_id" name="shop_id">
                                        <option value="">Select Shop...</option>
                                        <?php foreach ($shops as $shop): ?>
                                        <option value="<?php echo $shop['shop_id']; ?>">
                                            <?php echo htmlspecialchars($shop['shop_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group mb-3">
                            <label for="equipment_id" class="form-label">Equipment (Optional)</label>
                            <select class="form-control" id="equipment_id" name="equipment_id">
                                <option value="">Select Equipment...</option>
                                <?php foreach ($equipment as $eq): ?>
                                <option value="<?php echo $eq['equipment_id']; ?>">
                                    <?php echo htmlspecialchars($eq['equipment_name'] . ' (' . $eq['equipment_type'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="4" required 
                                      placeholder="Please provide detailed information about the issue..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_ticket" class="btn btn-create">
                            <i class="fas fa-plus"></i> Create Ticket
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewTicket(ticketId) {
            // Implement view ticket functionality
            window.location.href = 'ticket_view.php?id=' + ticketId;
        }

        function editTicket(ticketId) {
            // Implement edit ticket functionality
            window.location.href = 'ticket_edit.php?id=' + ticketId;
        }

        // Auto-refresh tickets every 60 seconds
        setInterval(function() {
            if (!document.querySelector('.modal.show')) {
                location.reload();
            }
        }, 60000);
    </script>
</body>
</html>
