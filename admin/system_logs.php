<?php
// Start session
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Redirect to login page if not logged in or not an admin
    header('Location: ../login.php');
    exit;
}

// Include system log functions
require_once '../includes/system_log.php';

// Database connection parameters
require_once '../db_config.php';

// Get filter parameters
$event_type = isset($_GET['event_type']) ? $_GET['event_type'] : null;
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;

// Get log entries
$log_entries = get_system_log_entries($limit, $event_type, $user_id);

// Get list of users for filter dropdown
$users = [];
try {
    $stmt = $pdo->query("SELECT user_id, username FROM users ORDER BY username");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Handle error
}

// Get list of event types for filter dropdown
$event_types = [];
foreach ($log_entries as $entry) {
    if (!in_array($entry['event_type'], $event_types)) {
        $event_types[] = $entry['event_type'];
    }
}
sort($event_types);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs - Roulette POS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        .log-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .log-table th, .log-table td {
            padding: 0.75rem;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .log-table th {
            background-color: #f8f9fc;
            color: #4e73df;
            font-weight: 700;
            text-align: left;
        }
        
        .log-table tr:hover {
            background-color: #f8f9fc;
        }
        
        .event-type {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .event-type-login {
            background-color: #e3f2fd;
            color: #0d6efd;
        }
        
        .event-type-logout {
            background-color: #e2e3e5;
            color: #383d41;
        }
        
        .event-type-factory_reset {
            background-color: #f8d7da;
            color: #dc3545;
        }
        
        .event-type-reset {
            background-color: #fff3cd;
            color: #ffc107;
        }
        
        .event-type-error {
            background-color: #f8d7da;
            color: #dc3545;
        }
        
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background-color: #f8f9fc;
            border-radius: 0.35rem;
        }
        
        .filter-form .form-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-form label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .filter-form select, .filter-form input {
            width: 100%;
            padding: 0.375rem 0.75rem;
            border: 1px solid #d1d3e2;
            border-radius: 0.35rem;
        }
        
        .filter-form button {
            align-self: flex-end;
            padding: 0.375rem 0.75rem;
            background-color: #4e73df;
            color: white;
            border: none;
            border-radius: 0.35rem;
            cursor: pointer;
        }
        
        .filter-form button:hover {
            background-color: #2e59d9;
        }
        
        .log-details {
            margin-top: 0.5rem;
            padding: 0.5rem;
            background-color: #f8f9fc;
            border-radius: 0.25rem;
            font-family: monospace;
            white-space: pre-wrap;
            font-size: 0.85rem;
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
                <input type="text" class="search-input" placeholder="Search logs...">
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
                <h1 class="page-title">System Logs</h1>
                <div class="breadcrumb">
                    <div class="breadcrumb-item"><a href="index.php">Admin</a></div>
                    <div class="breadcrumb-item active">System Logs</div>
                </div>
            </div>

            <!-- Filter Form -->
            <form class="filter-form" method="get" action="">
                <div class="form-group">
                    <label for="event_type">Event Type:</label>
                    <select id="event_type" name="event_type">
                        <option value="">All Events</option>
                        <?php foreach ($event_types as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $event_type === $type ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $type))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="user_id">User:</label>
                    <select id="user_id" name="user_id">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['user_id']; ?>" <?php echo $user_id === intval($user['user_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="limit">Limit:</label>
                    <select id="limit" name="limit">
                        <option value="50" <?php echo $limit === 50 ? 'selected' : ''; ?>>50 entries</option>
                        <option value="100" <?php echo $limit === 100 ? 'selected' : ''; ?>>100 entries</option>
                        <option value="250" <?php echo $limit === 250 ? 'selected' : ''; ?>>250 entries</option>
                        <option value="500" <?php echo $limit === 500 ? 'selected' : ''; ?>>500 entries</option>
                    </select>
                </div>
                
                <button type="submit">Apply Filters</button>
            </form>

            <!-- Log Entries Table -->
            <div class="table-responsive">
                <table class="log-table">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Event Type</th>
                            <th>User</th>
                            <th>IP Address</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($log_entries)): ?>
                            <tr>
                                <td colspan="5" class="text-center">No log entries found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($log_entries as $entry): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($entry['timestamp']); ?></td>
                                    <td>
                                        <span class="event-type event-type-<?php echo htmlspecialchars($entry['event_type']); ?>">
                                            <?php echo htmlspecialchars(str_replace('_', ' ', $entry['event_type'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $user_found = false;
                                        foreach ($users as $user) {
                                            if ($user['user_id'] == $entry['user_id']) {
                                                echo htmlspecialchars($user['username']);
                                                $user_found = true;
                                                break;
                                            }
                                        }
                                        if (!$user_found) {
                                            echo $entry['user_id'] ? "User ID: " . $entry['user_id'] : "System";
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($entry['ip_address']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($entry['message']); ?>
                                        <?php if (!empty($entry['data'])): ?>
                                            <div class="log-details"><?php echo htmlspecialchars(json_encode($entry['data'], JSON_PRETTY_PRINT)); ?></div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
