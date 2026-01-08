<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Redirect to login page if not logged in or not an admin
    header('Location: ../login.php');
    exit;
}

// Get current page filename
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Mobile Menu Toggle Button (outside sidebar) -->
<button id="mobile-menu-toggle" class="sidebar-toggle">
    <i class="fas fa-bars"></i>
</button>

<div class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-dice"></i>
            <span>Roulette Admin</span>
        </div>
        <button id="sidebar-toggle" class="sidebar-toggle">
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar">
            <i class="fas fa-user-shield"></i>
        </div>
        <div class="user-info">
            <div class="user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
            <div class="user-role">Administrator</div>
        </div>
    </div>

    <ul class="sidebar-menu">
        <li class="menu-header">Main</li>
        <li class="<?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
            <a href="index.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <li class="menu-header">User Management</li>
        <li class="<?php echo $current_page === 'users.php' ? 'active' : ''; ?>">
            <a href="users.php">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
        </li>
        <li class="<?php echo in_array($current_page, ['betting_shops.php', 'betting_shops_add.php', 'betting_shops_edit.php', 'betting_shops_view.php', 'betting_shops_users.php']) ? 'active' : ''; ?>">
            <a href="betting_shops.php">
                <i class="fas fa-store"></i>
                <span>Betting Shops</span>
            </a>
        </li>

        <li class="menu-header">Departments</li>
        <li class="<?php echo $current_page === 'departments_setup.php' ? 'active' : ''; ?>">
            <a href="departments_setup.php">
                <i class="fas fa-database"></i>
                <span>Setup Departments</span>
            </a>
        </li>
        <li class="<?php echo $current_page === 'stock_accounting_setup.php' ? 'active' : ''; ?>">
            <a href="stock_accounting_setup.php">
                <i class="fas fa-database"></i>
                <span>Setup Stock & Accounting</span>
            </a>
        </li>
        <li class="<?php echo $current_page === 'hr_setup.php' ? 'active' : ''; ?>">
            <a href="hr_setup.php">
                <i class="fas fa-database"></i>
                <span>Setup HR Department</span>
            </a>
        </li>
        <li class="<?php echo $current_page === 'remote_setup.php' ? 'active' : ''; ?>">
            <a href="remote_setup.php">
                <i class="fas fa-database"></i>
                <span>Setup Remote Monitoring</span>
            </a>
        </li>
        <li>
            <a href="../sales/index.php" target="_blank">
                <i class="fas fa-chart-line"></i>
                <span>Sales Department</span>
            </a>
        </li>
        <li>
            <a href="../it/index.php" target="_blank">
                <i class="fas fa-laptop"></i>
                <span>IT Department</span>
            </a>
        </li>
        <li>
            <a href="../finance/index.php" target="_blank">
                <i class="fas fa-dollar-sign"></i>
                <span>Finance Department</span>
            </a>
        </li>
        <li>
            <a href="../stock/index.php" target="_blank">
                <i class="fas fa-boxes"></i>
                <span>Stock Department</span>
            </a>
        </li>
        <li>
            <a href="../accounting/index.php" target="_blank">
                <i class="fas fa-calculator"></i>
                <span>Accounting Department</span>
            </a>
        </li>
        <li>
            <a href="../hr/index.php" target="_blank">
                <i class="fas fa-users"></i>
                <span>HR Department</span>
            </a>
        </li>

        <li class="menu-header">Remote Monitoring</li>
        <li>
            <a href="../remote/bet_distribution.php" target="_blank">
                <i class="fas fa-desktop"></i>
                <span>Remote Bet Distribution</span>
            </a>
        </li>
        <li>
            <a href="remote_dashboard.php">
                <i class="fas fa-chart-line"></i>
                <span>Remote Employee Dashboard</span>
            </a>
        </li>

        <li class="menu-header">Finance</li>
        <li class="<?php echo $current_page === 'cash.php' ? 'active' : ''; ?>">
            <a href="cash.php">
                <i class="fas fa-money-bill-wave"></i>
                <span>Cash Management</span>
            </a>
        </li>
        <li class="<?php echo $current_page === 'vouchers.php' ? 'active' : ''; ?>">
            <a href="vouchers.php">
                <i class="fas fa-ticket-alt"></i>
                <span>Vouchers</span>
            </a>
        </li>
        <li class="<?php echo $current_page === 'commission.php' ? 'active' : ''; ?>">
            <a href="commission.php">
                <i class="fas fa-percentage"></i>
                <span>Commission</span>
            </a>
        </li>
        <li class="<?php echo $current_page === 'transactions.php' ? 'active' : ''; ?>">
            <a href="transactions.php">
                <i class="fas fa-exchange-alt"></i>
                <span>Transactions</span>
            </a>
        </li>

        <li class="menu-header">Game Management</li>
        <li class="<?php echo $current_page === 'game_settings.php' ? 'active' : ''; ?>">
            <a href="game_settings.php">
                <i class="fas fa-cogs"></i>
                <span>Game Settings</span>
            </a>
        </li>
        <li class="<?php echo $current_page === 'betting_history.php' ? 'active' : ''; ?>">
            <a href="betting_history.php">
                <i class="fas fa-history"></i>
                <span>Betting History</span>
            </a>
        </li>
        <li class="<?php echo $current_page === 'bet_distribution.php' ? 'active' : ''; ?>">
            <a href="bet_distribution.php">
                <i class="fas fa-chart-bar"></i>
                <span>Bet Distribution & Draw Control</span>
            </a>
        </li>

        <li class="menu-header">System</li>
        <li class="<?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">
            <a href="settings.php">
                <i class="fas fa-sliders-h"></i>
                <span>Settings</span>
            </a>
        </li>
        <li class="<?php echo $current_page === 'logs.php' ? 'active' : ''; ?>">
            <a href="logs.php">
                <i class="fas fa-clipboard-list"></i>
                <span>Logs</span>
            </a>
        </li>
        <li class="<?php echo $current_page === 'system_logs.php' ? 'active' : ''; ?>">
            <a href="system_logs.php">
                <i class="fas fa-shield-alt"></i>
                <span>System Audit Logs</span>
            </a>
        </li>
        <li class="<?php echo $current_page === 'db_setup.php' ? 'active' : ''; ?>">
            <a href="db_setup.php">
                <i class="fas fa-database"></i>
                <span>Database Setup</span>
            </a>
        </li>
    </ul>

    <div class="sidebar-footer">
        <a href="../index.html" class="footer-link" title="Go to Game">
            <i class="fas fa-gamepad"></i>
        </a>
        <a href="settings.php" class="footer-link" title="Settings">
            <i class="fas fa-cog"></i>
        </a>
        <a href="../logout.php" class="footer-link" title="Logout">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</div>

<script>
    // Mobile menu toggle
    document.getElementById('mobile-menu-toggle').addEventListener('click', function() {
        document.querySelector('.sidebar').classList.toggle('active');
    });

    // Sidebar toggle (for tablet view)
    document.getElementById('sidebar-toggle').addEventListener('click', function() {
        document.querySelector('.sidebar').classList.toggle('active');
    });

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        const sidebar = document.querySelector('.sidebar');
        const mobileToggle = document.getElementById('mobile-menu-toggle');

        // If sidebar is active on mobile and click is outside sidebar and not on the toggle button
        if (window.innerWidth <= 768 &&
            sidebar.classList.contains('active') &&
            !sidebar.contains(event.target) &&
            event.target !== mobileToggle &&
            !mobileToggle.contains(event.target)) {
            sidebar.classList.remove('active');
        }
    });

    // Handle responsive behavior based on screen size
    function handleResponsiveLayout() {
        const sidebar = document.querySelector('.sidebar');

        if (window.innerWidth <= 768) {
            // Mobile view
            sidebar.classList.remove('active');
        } else if (window.innerWidth <= 1024) {
            // Tablet view - collapsed sidebar by default
            sidebar.classList.remove('active');
        } else {
            // Desktop view - expanded sidebar
            sidebar.classList.remove('active');
        }
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', handleResponsiveLayout);

    // Update on window resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(handleResponsiveLayout, 250);
    });
</script>
