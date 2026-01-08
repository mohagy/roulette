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
require_once '../db_config.php';

// Include system log functions
require_once '../includes/system_log.php';

// Create logs directory if it doesn't exist
if (!file_exists('../logs')) {
    mkdir('../logs', 0777, true);
}

// Log file for factory reset operations
$logFile = '../logs/factory_reset.log';

// Function to log messages
function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    return $message;
}

// Function to create database backup
function createDatabaseBackup($pdo, $dbName) {
    try {
        // Create backups directory if it doesn't exist
        if (!file_exists('../backups')) {
            mkdir('../backups', 0777, true);
        }

        // Generate backup filename with timestamp
        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = "../backups/roulette_backup_$timestamp.sql";

        // Use localhost as the default host for XAMPP
        $host = 'localhost';

        // Use mysqldump to create backup
        $command = "mysqldump --host=$host --user=root --no-tablespaces $dbName > $backupFile";
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            throw new Exception("Database backup failed with error code: $returnVar");
        }

        logMessage("Database backup created successfully: $backupFile");
        return $backupFile;
    } catch (Exception $e) {
        logMessage("Error creating database backup: " . $e->getMessage());
        return false;
    }
}

// Function to reset a table to its default state
function resetTable($pdo, $table, $defaultValues = null) {
    try {
        // Truncate the table
        $pdo->exec("TRUNCATE TABLE $table");

        // If default values are provided, insert them
        if ($defaultValues) {
            $pdo->exec("INSERT INTO $table $defaultValues");
        }

        // Reset auto-increment value
        $pdo->exec("ALTER TABLE $table AUTO_INCREMENT = 1");

        return true;
    } catch (PDOException $e) {
        logMessage("Error resetting table $table: " . $e->getMessage());
        return false;
    }
}

// Process form submission
$message = '';
$messageType = '';
$backupFile = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify confirmation text
    if (!isset($_POST['confirmation_text']) || $_POST['confirmation_text'] !== 'FACTORY RESET') {
        $message = "Confirmation text is incorrect. Please type 'FACTORY RESET' to proceed.";
        $messageType = 'danger';
    }
    // Verify admin password
    else if (!isset($_POST['admin_password']) || empty($_POST['admin_password'])) {
        $message = "Admin password is required.";
        $messageType = 'danger';
    }
    // Verify final confirmation checkbox
    else if (!isset($_POST['final_confirmation']) || $_POST['final_confirmation'] !== 'on') {
        $message = "You must check the final confirmation checkbox to proceed.";
        $messageType = 'danger';
    }
    else {
        // Verify admin password
        try {
            $stmt = $pdo->prepare("SELECT user_id, password FROM users WHERE user_id = ? AND role = 'admin'");
            $stmt->execute([$_SESSION['user_id']]);
            $admin = $stmt->fetch();

            // For debugging
            logMessage("Verifying password for admin ID: " . $_SESSION['user_id']);

            // Check if the password matches directly (in case it's not hashed)
            // or if it matches using password_verify (in case it is hashed)
            if (!$admin) {
                $message = "Admin user not found.";
                $messageType = 'danger';
            } else if ($_POST['admin_password'] === $admin['password'] ||
                      (function_exists('password_verify') && password_verify($_POST['admin_password'], $admin['password']))) {
                // Password is correct
                // Password verified, proceed with factory reset
                logMessage("Factory reset initiated by admin user ID: " . $_SESSION['user_id']);

                // Create database backup
                $backupFile = createDatabaseBackup($pdo, 'roulette');

                if (!$backupFile) {
                    $message = "Factory reset aborted: Failed to create database backup.";
                    $messageType = 'danger';
                } else {
                    try {
                        // Reset tables one by one without using a transaction
                        // This avoids issues with transaction management

                        // 1. Game state tables
                        // Special handling for roulette_state to ensure it's properly reset
                        try {
                            // First drop the table to ensure it's completely reset
                            $pdo->exec("DROP TABLE IF EXISTS roulette_state");

                            // Then recreate it with the correct structure
                            $pdo->exec("CREATE TABLE roulette_state (
                                id int(11) NOT NULL AUTO_INCREMENT,
                                roll_history text DEFAULT NULL,
                                roll_colors text DEFAULT NULL,
                                last_draw varchar(10) DEFAULT '#0',
                                next_draw varchar(10) DEFAULT '#1',
                                current_draw int(11) DEFAULT 0,
                                countdown_time int(11) DEFAULT 180,
                                end_time varchar(20) DEFAULT NULL,
                                updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                                current_draw_number int(11) DEFAULT 0,
                                winning_number int(11) DEFAULT 0,
                                next_draw_winning_number int(11) DEFAULT 0,
                                manual_mode tinyint(1) DEFAULT 0,
                                PRIMARY KEY (id)
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

                            // Insert the initial empty record
                            $pdo->exec("INSERT INTO roulette_state (id, roll_history, roll_colors, last_draw, next_draw, countdown_time, current_draw, winning_number, next_draw_winning_number, manual_mode)
                                       VALUES (1, '[]', '[]', '#0', '#1', 180, 0, 0, 0, 0)");

                            logMessage("roulette_state table completely rebuilt with default values");
                        } catch (Exception $e) {
                            logMessage("Error rebuilding roulette_state table: " . $e->getMessage());
                            throw $e;
                        }

                        // Special handling for roulette_analytics to ensure it's properly reset
                        try {
                            // First drop the table to ensure it's completely reset
                            $pdo->exec("DROP TABLE IF EXISTS roulette_analytics");

                            // Then recreate it with the correct structure
                            $pdo->exec("CREATE TABLE roulette_analytics (
                                id int(11) NOT NULL AUTO_INCREMENT,
                                all_spins text NOT NULL COMMENT 'JSON array of all recorded spins (newest first)',
                                number_frequency text NOT NULL COMMENT 'JSON object with count of each number',
                                current_draw_number int(11) NOT NULL DEFAULT 0 COMMENT 'Current draw number counter',
                                last_updated timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                                created_at timestamp NOT NULL DEFAULT current_timestamp(),
                                PRIMARY KEY (id)
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

                            // Insert the initial empty record
                            $pdo->exec("INSERT INTO roulette_analytics (id, all_spins, number_frequency, current_draw_number)
                                       VALUES (1, '[]', '{}', 0)");

                            logMessage("roulette_analytics table completely rebuilt with empty data");
                        } catch (Exception $e) {
                            logMessage("Error rebuilding roulette_analytics table: " . $e->getMessage());
                            throw $e;
                        }
                        resetTable($pdo, 'roulette_settings', "(id, automatic_mode) VALUES (1, 1)");

                        // Ensure next_draw_winning_number table is completely reset
                        try {
                            // First delete all records
                            $pdo->exec("DELETE FROM next_draw_winning_number");

                            // Then truncate the table to reset auto-increment
                            $pdo->exec("TRUNCATE TABLE next_draw_winning_number");

                            // Reset auto-increment value
                            $pdo->exec("ALTER TABLE next_draw_winning_number AUTO_INCREMENT = 1");

                            logMessage("next_draw_winning_number table completely reset");
                        } catch (Exception $e) {
                            logMessage("Error resetting next_draw_winning_number table: " . $e->getMessage());
                        }

                        resetTable($pdo, 'draw_history');
                        resetTable($pdo, 'detailed_draw_results');
                        resetTable($pdo, 'roulette_draws');
                        resetTable($pdo, 'roulette_game_state', "(id, current_draw_number, next_draw_number, next_draw_time, is_auto_draw, draw_interval_seconds) VALUES (1, 0, 1, NOW() + INTERVAL 3 MINUTE, 1, 180)");
                        resetTable($pdo, 'roulette_number_stats');
                        resetTable($pdo, 'roulette_color_stats');

                        // Ensure roulette_state table is properly reset
                        resetTable($pdo, 'roulette_state', "(roll_history, roll_colors, last_draw, next_draw, countdown_time, end_time, current_draw_number) VALUES ('', '', '#0', '#1', 120, '" . (time() + 120) . "', 1)");

                        // 2. Betting tables
                        resetTable($pdo, 'betting_slips');
                        resetTable($pdo, 'bets');
                        resetTable($pdo, 'slip_details');

                        // 3. Financial tables
                        resetTable($pdo, 'vouchers');
                        resetTable($pdo, 'commission');
                        resetTable($pdo, 'commission_summary');

                        // 4. Reset user balances (preserve admin accounts)
                        $stmt = $pdo->prepare("UPDATE users SET cash_balance = 0 WHERE role != 'admin'");
                        $stmt->execute();

                        // 5. Reset transactions
                        resetTable($pdo, 'transactions');

                        // Log successful reset
                        logMessage("Factory reset completed successfully");

                        // Log to system audit log
                        log_system_event(
                            'factory_reset',
                            'Factory reset performed successfully',
                            [
                                'backup_file' => basename($backupFile),
                                'tables_reset' => [
                                    'roulette_state', 'roulette_analytics', 'roulette_settings',
                                    'next_draw_winning_number', 'draw_history', 'detailed_draw_results',
                                    'roulette_draws', 'roulette_game_state', 'roulette_number_stats',
                                    'roulette_color_stats', 'betting_slips', 'bets', 'slip_details',
                                    'vouchers', 'commission', 'commission_summary', 'transactions'
                                ]
                            ],
                            $_SESSION['user_id']
                        );

                        // Set success message
                        $message = "Factory reset completed successfully. All game data has been reset to default values.";
                        $messageType = 'success';

                        // Invalidate all sessions except current admin
                        if (session_status() === PHP_SESSION_ACTIVE) {
                            // Keep current session data
                            $admin_id = $_SESSION['user_id'];
                            $admin_role = $_SESSION['role'];

                            // Regenerate session ID
                            session_regenerate_id(true);

                            // Restore admin session data
                            $_SESSION['user_id'] = $admin_id;
                            $_SESSION['role'] = $admin_role;
                        }

                    } catch (Exception $e) {
                        // Log the error
                        logMessage("Factory reset failed: " . $e->getMessage());
                        $message = "Factory reset failed: " . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
            }
        } catch (PDOException $e) {
            $message = "Error verifying admin credentials: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factory Reset - Roulette POS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        .factory-reset-section {
            background-color: #f8d7da;
            border: 1px solid #f5c2c7;
            border-radius: 0.35rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .warning-icon {
            color: #dc3545;
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .confirmation-input {
            font-weight: bold;
            text-transform: uppercase;
        }

        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background-color: #bb2d3b;
            border-color: #b02a37;
        }

        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 0.35rem;
        }

        .alert-success {
            background-color: #d1e7dd;
            border: 1px solid #badbcc;
            color: #0f5132;
        }

        .alert-danger {
            background-color: #f8d7da;
            border: 1px solid #f5c2c7;
            color: #842029;
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

        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">Factory Reset</h1>
                <div class="breadcrumb">
                    <div class="breadcrumb-item"><a href="index.php">Admin</a></div>
                    <div class="breadcrumb-item"><a href="game_settings.php">Game Settings</a></div>
                    <div class="breadcrumb-item active">Factory Reset</div>
                </div>
            </div>

            <!-- Alert Message -->
            <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo $message; ?>
                <?php if ($messageType === 'success' && !empty($backupFile)): ?>
                    <p>A backup of the database was created before the reset: <?php echo basename($backupFile); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Factory Reset Section -->
            <div class="factory-reset-section">
                <div class="text-center">
                    <i class="fas fa-exclamation-triangle warning-icon"></i>
                    <h2>Factory Reset</h2>
                    <p>This action will completely reset the roulette system to its initial state.</p>
                </div>

                <div class="alert alert-danger">
                    <h4><i class="fas fa-exclamation-circle"></i> Warning!</h4>
                    <p>The following data will be permanently deleted:</p>
                    <ul>
                        <li>All betting slips and bet records</li>
                        <li>All transaction history</li>
                        <li>All voucher records</li>
                        <li>All draw history and statistics</li>
                        <li>All user balances (except admin accounts)</li>
                    </ul>
                    <p><strong>This action cannot be undone!</strong></p>
                </div>

                <form method="post" action="" id="factoryResetForm">
                    <div class="form-group">
                        <label for="confirmation_text">Type "FACTORY RESET" to confirm:</label>
                        <input type="text" class="form-control confirmation-input" id="confirmation_text" name="confirmation_text" required>
                    </div>

                    <div class="form-group">
                        <label for="admin_password">Enter your admin password:</label>
                        <input type="password" class="form-control" id="admin_password" name="admin_password" required>
                    </div>

                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="final_confirmation" name="final_confirmation">
                            <label class="custom-control-label" for="final_confirmation">I understand this action cannot be undone and will permanently delete all game data.</label>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <a href="game_settings.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-danger" id="resetButton">
                            <i class="fas fa-exclamation-triangle"></i> Perform Factory Reset
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Add confirmation dialog
        document.getElementById('factoryResetForm').addEventListener('submit', function(e) {
            if (!confirm('WARNING: You are about to perform a factory reset which will delete ALL game data. This action CANNOT be undone. Are you absolutely sure you want to continue?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
