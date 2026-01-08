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
$requiredTables = ['roulette_state', 'settings'];
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
        // Update countdown time
        if ($_POST['action'] === 'update_countdown' && isset($_POST['countdown_time'])) {
            $countdownTime = intval($_POST['countdown_time']);

            // Validate input
            if ($countdownTime < 30 || $countdownTime > 300) {
                $message = "Countdown time must be between 30 and 300 seconds.";
                $messageType = 'danger';
            } else {
                // Get the most recent state record
                $getLatestQuery = "SELECT * FROM roulette_state ORDER BY id DESC LIMIT 1";
                $latestResult = $conn->query($getLatestQuery);
                $latestState = $latestResult->fetch_assoc();

                // Insert a new record with updated countdown time
                $stmt = $conn->prepare("INSERT INTO roulette_state
                          (roll_history, roll_colors, last_draw, next_draw, countdown_time, end_time, current_draw_number, winning_number, next_draw_winning_number, manual_mode)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $stmt->bind_param("ssssiiiii",
                    $latestState['roll_history'],
                    $latestState['roll_colors'],
                    $latestState['last_draw'],
                    $latestState['next_draw'],
                    $countdownTime,
                    $latestState['end_time'],
                    $latestState['current_draw_number'],
                    $latestState['winning_number'],
                    $latestState['next_draw_winning_number'],
                    $latestState['manual_mode']
                );

                if ($stmt->execute()) {
                    $message = "Countdown time updated successfully.";
                    $messageType = 'success';
                } else {
                    $message = "Error updating countdown time: " . $stmt->error;
                    $messageType = 'danger';
                }
            }
        }
        // Reset game state
        elseif ($_POST['action'] === 'reset_game') {
            // Insert a new record with reset values
            $stmt = $conn->prepare("INSERT INTO roulette_state
                      (roll_history, roll_colors, last_draw, next_draw, countdown_time, end_time, current_draw_number, winning_number, next_draw_winning_number, manual_mode)
                      VALUES ('[]', '[]', '#0', '#1', 180, ?, 1, NULL, NULL, 0)");

            $endTime = time() + 180;
            $stmt->bind_param("i", $endTime);

            if ($stmt->execute()) {
                $message = "Game state reset successfully.";
                $messageType = 'success';
            } else {
                $message = "Error resetting game state: " . $stmt->error;
                $messageType = 'danger';
            }
        }
        // Update commission rate
        elseif ($_POST['action'] === 'update_commission' && isset($_POST['commission_rate'])) {
            $commissionRate = floatval($_POST['commission_rate']);

            // Validate input
            if ($commissionRate < 0 || $commissionRate > 20) {
                $message = "Commission rate must be between 0 and 20 percent.";
                $messageType = 'danger';
            } else {
                // Check if settings table exists
                $tableCheck = $conn->query("SHOW TABLES LIKE 'settings'");
                if ($tableCheck->num_rows == 0) {
                    // Create settings table
                    $createTable = "CREATE TABLE IF NOT EXISTS settings (
                        setting_id INT AUTO_INCREMENT PRIMARY KEY,
                        setting_key VARCHAR(50) NOT NULL UNIQUE,
                        setting_value TEXT NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    )";

                    if (!$conn->query($createTable)) {
                        $message = "Error creating settings table: " . $conn->error;
                        $messageType = 'danger';
                    }
                }

                // Check if commission_rate setting exists
                $stmt = $conn->prepare("SELECT setting_id FROM settings WHERE setting_key = 'commission_rate'");
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 0) {
                    // Insert new setting
                    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('commission_rate', ?)");
                    $commissionRateStr = strval($commissionRate);
                    $stmt->bind_param("s", $commissionRateStr);
                } else {
                    // Update existing setting
                    $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'commission_rate'");
                    $commissionRateStr = strval($commissionRate);
                    $stmt->bind_param("s", $commissionRateStr);
                }

                if ($stmt->execute()) {
                    $message = "Commission rate updated successfully.";
                    $messageType = 'success';
                } else {
                    $message = "Error updating commission rate: " . $stmt->error;
                    $messageType = 'danger';
                }
            }
        }
        // Update max bet
        elseif ($_POST['action'] === 'update_max_bet' && isset($_POST['max_bet'])) {
            $maxBet = floatval($_POST['max_bet']);

            // Validate input
            if ($maxBet < 10 || $maxBet > 10000) {
                $message = "Max bet must be between 10 and 10,000.";
                $messageType = 'danger';
            } else {
                // Check if settings table exists
                $tableCheck = $conn->query("SHOW TABLES LIKE 'settings'");
                if ($tableCheck->num_rows == 0) {
                    // Create settings table
                    $createTable = "CREATE TABLE IF NOT EXISTS settings (
                        setting_id INT AUTO_INCREMENT PRIMARY KEY,
                        setting_key VARCHAR(50) NOT NULL UNIQUE,
                        setting_value TEXT NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    )";

                    if (!$conn->query($createTable)) {
                        $message = "Error creating settings table: " . $conn->error;
                        $messageType = 'danger';
                    }
                }

                // Check if max_bet setting exists
                $stmt = $conn->prepare("SELECT setting_id FROM settings WHERE setting_key = 'max_bet'");
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 0) {
                    // Insert new setting
                    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('max_bet', ?)");
                    $maxBetStr = strval($maxBet);
                    $stmt->bind_param("s", $maxBetStr);
                } else {
                    // Update existing setting
                    $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'max_bet'");
                    $maxBetStr = strval($maxBet);
                    $stmt->bind_param("s", $maxBetStr);
                }

                if ($stmt->execute()) {
                    $message = "Max bet updated successfully.";
                    $messageType = 'success';
                } else {
                    $message = "Error updating max bet: " . $stmt->error;
                    $messageType = 'danger';
                }
            }
        }
    }
}

// Get current game settings
$settings = [
    'countdown_time' => 120,
    'commission_rate' => 4,
    'max_bet' => 1000,
    'min_bet' => 5
];

// Get countdown time from the most recent roulette_state record
$result = $conn->query("SELECT countdown_time FROM roulette_state ORDER BY id DESC LIMIT 1");
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $settings['countdown_time'] = intval($row['countdown_time']);
}

// Get other settings from settings table
$tableCheck = $conn->query("SHOW TABLES LIKE 'settings'");
if ($tableCheck->num_rows > 0) {
    $result = $conn->query("SELECT setting_key, setting_value FROM settings");
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            if ($row['setting_key'] === 'commission_rate') {
                $settings['commission_rate'] = floatval($row['setting_value']);
            } elseif ($row['setting_key'] === 'max_bet') {
                $settings['max_bet'] = floatval($row['setting_value']);
            } elseif ($row['setting_key'] === 'min_bet') {
                $settings['min_bet'] = floatval($row['setting_value']);
            }
        }
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
    <title>Game Settings - Roulette POS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        /* Additional styles for this page */
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

        .settings-card {
            background-color: #fff;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            margin-bottom: 1.5rem;
        }

        .settings-card-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #e3e6f0;
            background-color: #f8f9fc;
            border-top-left-radius: 0.35rem;
            border-top-right-radius: 0.35rem;
        }

        .settings-card-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            color: #4e73df;
        }

        .settings-card-body {
            padding: 1.25rem;
        }

        .settings-card-footer {
            padding: 1rem 1.25rem;
            border-top: 1px solid #e3e6f0;
            background-color: #f8f9fc;
            border-bottom-left-radius: 0.35rem;
            border-bottom-right-radius: 0.35rem;
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
                <input type="text" class="search-input" placeholder="Search settings...">
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
                <h1 class="page-title">Game Settings</h1>
                <div class="breadcrumb">
                    <div class="breadcrumb-item"><a href="index.php">Admin</a></div>
                    <div class="breadcrumb-item active">Game Settings</div>
                </div>
            </div>

            <!-- Alert Message -->
            <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-6">
                    <!-- Countdown Settings -->
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <h6 class="settings-card-title">
                                <i class="fas fa-clock"></i> Countdown Timer Settings
                            </h6>
                        </div>
                        <div class="settings-card-body">
                            <form method="post" action="">
                                <input type="hidden" name="action" value="update_countdown">
                                <div class="form-group">
                                    <label for="countdown_time">Countdown Time (seconds):</label>
                                    <input type="number" class="form-control" id="countdown_time" name="countdown_time" min="30" max="300" value="<?php echo $settings['countdown_time']; ?>" required>
                                    <small class="text-muted">Set the countdown time between draws (30-300 seconds).</small>
                                </div>
                                <button type="submit" class="btn btn-primary">Update Countdown Time</button>
                            </form>
                        </div>
                    </div>

                    <!-- Commission Settings -->
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <h6 class="settings-card-title">
                                <i class="fas fa-percentage"></i> Commission Settings
                            </h6>
                        </div>
                        <div class="settings-card-body">
                            <form method="post" action="">
                                <input type="hidden" name="action" value="update_commission">
                                <div class="form-group">
                                    <label for="commission_rate">Commission Rate (%):</label>
                                    <input type="number" class="form-control" id="commission_rate" name="commission_rate" min="0" max="20" step="0.1" value="<?php echo $settings['commission_rate']; ?>" required>
                                    <small class="text-muted">Set the commission rate for bets (0-20%).</small>
                                </div>
                                <button type="submit" class="btn btn-primary">Update Commission Rate</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <!-- Betting Settings -->
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <h6 class="settings-card-title">
                                <i class="fas fa-money-bill-wave"></i> Betting Settings
                            </h6>
                        </div>
                        <div class="settings-card-body">
                            <form method="post" action="">
                                <input type="hidden" name="action" value="update_max_bet">
                                <div class="form-group">
                                    <label for="max_bet">Maximum Bet Amount ($):</label>
                                    <input type="number" class="form-control" id="max_bet" name="max_bet" min="10" max="10000" step="1" value="<?php echo $settings['max_bet']; ?>" required>
                                    <small class="text-muted">Set the maximum bet amount (10-10,000).</small>
                                </div>
                                <button type="submit" class="btn btn-primary">Update Max Bet</button>
                            </form>
                        </div>
                    </div>

                    <!-- Game Reset -->
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <h6 class="settings-card-title">
                                <i class="fas fa-redo"></i> Game Reset
                            </h6>
                        </div>
                        <div class="settings-card-body">
                            <p>Reset the game state to clear all roll history and start fresh.</p>
                            <form method="post" action="" onsubmit="return confirm('Are you sure you want to reset the game state? This will clear all roll history.');">
                                <input type="hidden" name="action" value="reset_game">
                                <button type="submit" class="btn btn-danger">Reset Game State</button>
                            </form>
                        </div>
                    </div>

                    <!-- System Maintenance -->
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <h6 class="settings-card-title">
                                <i class="fas fa-tools"></i> System Maintenance
                            </h6>
                        </div>
                        <div class="settings-card-body">
                            <p><strong>Factory Reset:</strong> Completely reset the system to its initial state.</p>
                            <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> Warning: This will delete all betting slips, transactions, and game history.</p>
                            <a href="factory_reset.php" class="btn btn-danger">
                                <i class="fas fa-exclamation-triangle"></i> Factory Reset
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Current Settings Summary -->
            <div class="settings-card">
                <div class="settings-card-header">
                    <h6 class="settings-card-title">
                        <i class="fas fa-info-circle"></i> Current Settings Summary
                    </h6>
                </div>
                <div class="settings-card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Setting</th>
                                    <th>Value</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Countdown Time</td>
                                    <td><?php echo $settings['countdown_time']; ?> seconds</td>
                                    <td>Time between draws</td>
                                </tr>
                                <tr>
                                    <td>Commission Rate</td>
                                    <td><?php echo $settings['commission_rate']; ?>%</td>
                                    <td>Commission on bets</td>
                                </tr>
                                <tr>
                                    <td>Maximum Bet</td>
                                    <td>$<?php echo number_format($settings['max_bet'], 2); ?></td>
                                    <td>Maximum bet amount</td>
                                </tr>
                                <tr>
                                    <td>Minimum Bet</td>
                                    <td>$<?php echo number_format($settings['min_bet'], 2); ?></td>
                                    <td>Minimum bet amount</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
