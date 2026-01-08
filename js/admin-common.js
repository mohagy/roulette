/**
 * Admin Common Functions
 * Reusable functions for all admin pages
 */

/**
 * Create admin sidebar with current page highlighting
 * @param {string} currentPage - Current page filename (e.g., 'users.html', 'vouchers.html')
 */
function createAdminSidebar(currentPage = 'index.html') {
    // Check if sidebar already exists
    if (document.querySelector('.sidebar')) {
        return; // Sidebar already created
    }
    
    // Get username from Firebase Auth if available, otherwise use default
    let username = 'Admin';
    if (window.FirebaseAuth && window.FirebaseAuth.isInitialized()) {
        const currentUser = window.FirebaseAuth.getCurrentUser();
        if (currentUser) {
            username = currentUser.username;
        }
    }
    
    // Map page names for active highlighting
    const pageMap = {
        'admin.html': 'index.php',
        'users.html': 'users.php',
        'vouchers.html': 'vouchers.php',
        'transactions.html': 'transactions.php',
        'cash.html': 'cash.php',
        'commission.html': 'commission.php',
        'betting_history.html': 'betting_history.php',
        'game_settings.html': 'game_settings.php',
        'bet_distribution.html': 'bet_distribution.php'
    };
    
    const currentPageKey = pageMap[currentPage] || currentPage;
    
    const sidebarHTML = `
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
                    <div class="user-name">${username}</div>
                    <div class="user-role">Administrator</div>
                </div>
            </div>

            <ul class="sidebar-menu">
                <li class="menu-header">Main</li>
                <li class="${currentPageKey === 'index.php' || currentPage === 'admin.html' ? 'active' : ''}">
                    <a href="admin.html">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>

                <li class="menu-header">User Management</li>
                <li class="${currentPageKey === 'users.php' || currentPage === 'users.html' ? 'active' : ''}">
                    <a href="admin/users.html">
                        <i class="fas fa-users"></i>
                        <span>Users</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-store"></i>
                        <span>Betting Shops</span>
                    </a>
                </li>

                <li class="menu-header">Departments</li>
                <li>
                    <a href="#">
                        <i class="fas fa-database"></i>
                        <span>Setup Departments</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-database"></i>
                        <span>Setup Stock & Accounting</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-database"></i>
                        <span>Setup HR Department</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-database"></i>
                        <span>Setup Remote Monitoring</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-chart-line"></i>
                        <span>Sales Department</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-laptop"></i>
                        <span>IT Department</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-dollar-sign"></i>
                        <span>Finance Department</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-boxes"></i>
                        <span>Stock Department</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-calculator"></i>
                        <span>Accounting Department</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-users"></i>
                        <span>HR Department</span>
                    </a>
                </li>

                <li class="menu-header">Remote Monitoring</li>
                <li>
                    <a href="#">
                        <i class="fas fa-desktop"></i>
                        <span>Remote Bet Distribution</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-chart-line"></i>
                        <span>Remote Employee Dashboard</span>
                    </a>
                </li>

                <li class="menu-header">Finance</li>
                <li class="${currentPageKey === 'cash.php' || currentPage === 'cash.html' ? 'active' : ''}">
                    <a href="admin/cash.html">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Cash Management</span>
                    </a>
                </li>
                <li class="${currentPageKey === 'vouchers.php' || currentPage === 'vouchers.html' ? 'active' : ''}">
                    <a href="admin/vouchers.html">
                        <i class="fas fa-ticket-alt"></i>
                        <span>Vouchers</span>
                    </a>
                </li>
                <li class="${currentPageKey === 'commission.php' || currentPage === 'commission.html' ? 'active' : ''}">
                    <a href="commission.html">
                        <i class="fas fa-percentage"></i>
                        <span>Commission</span>
                    </a>
                </li>
                <li class="${currentPageKey === 'transactions.php' || currentPage === 'transactions.html' ? 'active' : ''}">
                    <a href="admin/transactions.html">
                        <i class="fas fa-exchange-alt"></i>
                        <span>Transactions</span>
                    </a>
                </li>

                <li class="menu-header">Game Management</li>
                <li class="${currentPageKey === 'game_settings.php' || currentPage === 'game_settings.html' ? 'active' : ''}">
                    <a href="admin/game_settings.html">
                        <i class="fas fa-cogs"></i>
                        <span>Game Settings</span>
                    </a>
                </li>
                <li class="${currentPageKey === 'betting_history.php' || currentPage === 'betting_history.html' ? 'active' : ''}">
                    <a href="admin/betting_history.html">
                        <i class="fas fa-history"></i>
                        <span>Betting History</span>
                    </a>
                </li>
                <li class="${currentPageKey === 'bet_distribution.php' || currentPage === 'bet_distribution.html' ? 'active' : ''}">
                    <a href="admin/bet_distribution.html">
                        <i class="fas fa-chart-bar"></i>
                        <span>Bet Distribution & Draw Control</span>
                    </a>
                </li>

                <li class="menu-header">System</li>
                <li>
                    <a href="#">
                        <i class="fas fa-sliders-h"></i>
                        <span>Settings</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Logs</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-shield-alt"></i>
                        <span>System Audit Logs</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-database"></i>
                        <span>Database Setup</span>
                    </a>
                </li>
            </ul>

            <div class="sidebar-footer">
                <a href="index.html" class="footer-link" title="Go to Game">
                    <i class="fas fa-gamepad"></i>
                </a>
                <a href="#" class="footer-link" title="Settings">
                    <i class="fas fa-cog"></i>
                </a>
                <a href="#" id="sidebar-logout-link" class="footer-link" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    `;
    
    // Insert sidebar before body content
    const body = document.body;
    body.insertAdjacentHTML('afterbegin', sidebarHTML);
    
    // Setup sidebar interactions
    setupSidebarInteractions();
}

/**
 * Setup sidebar interactions (mobile toggle, collapse, etc.)
 */
function setupSidebarInteractions() {
    // Mobile menu toggle
    const mobileToggle = document.getElementById('mobile-menu-toggle');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (mobileToggle && sidebar) {
        mobileToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
    }
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            const mainContent = document.querySelector('.main-content');
            if (mainContent) {
                mainContent.classList.toggle('expanded');
            }
        });
    }
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        if (sidebar && window.innerWidth <= 768 && sidebar.classList.contains('active')) {
            if (!sidebar.contains(event.target) && 
                event.target !== mobileToggle && 
                !mobileToggle.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        }
    });
    
    // Logout link
    const sidebarLogoutLink = document.getElementById('sidebar-logout-link');
    if (sidebarLogoutLink) {
        sidebarLogoutLink.addEventListener('click', function(e) {
            e.preventDefault();
            handleAdminLogout();
        });
    }
    
    // Handle responsive behavior
    function handleResponsiveLayout() {
        if (!sidebar) return;
        
        if (window.innerWidth <= 768) {
            sidebar.classList.remove('active');
        } else if (window.innerWidth <= 1024) {
            sidebar.classList.remove('active');
        } else {
            sidebar.classList.remove('active');
        }
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', handleResponsiveLayout);
    } else {
        handleResponsiveLayout();
    }
    
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(handleResponsiveLayout, 250);
    });
}

/**
 * Check admin authentication
 */
async function checkAdminAuth() {
    try {
        if (!window.FirebaseAuth || !window.FirebaseAuth.isInitialized()) {
            window.location.href = 'login.html';
            return false;
        }

        const currentUser = window.FirebaseAuth.getCurrentUser();
        if (!currentUser) {
            window.location.href = 'login.html';
            return false;
        }

        // Check if user is admin
        if (currentUser.role !== 'admin') {
            alert('Access denied. Admin privileges required.');
            window.location.href = 'index.html';
            return false;
        }

        return true;
    } catch (error) {
        console.error('Error checking admin auth:', error);
        window.location.href = 'login.html';
        return false;
    }
}

/**
 * Handle admin logout
 */
function handleAdminLogout() {
    if (confirm('Are you sure you want to logout?')) {
        if (window.FirebaseAuth) {
            window.FirebaseAuth.logout();
        }
        window.location.href = 'login.html';
    }
}

/**
 * Show alert message
 */
function showAlert(message, type = 'info') {
    const alertHTML = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    `;
    
    // Find or create alert container
    let alertContainer = document.getElementById('alert-container');
    if (!alertContainer) {
        alertContainer = document.createElement('div');
        alertContainer.id = 'alert-container';
        const contentWrapper = document.querySelector('.content-wrapper');
        if (contentWrapper) {
            contentWrapper.insertBefore(alertContainer, contentWrapper.firstChild);
        }
    }
    
    alertContainer.innerHTML = alertHTML;
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        const alert = alertContainer.querySelector('.alert');
        if (alert) {
            alert.classList.remove('show');
            setTimeout(() => alert.remove(), 150);
        }
    }, 5000);
}

// Initialize sidebar when DOM is ready
function initAdminSidebar() {
    // Get current page from URL
    const currentPage = window.location.pathname.split('/').pop() || 'admin.html';
    createAdminSidebar(currentPage);
}

// Try to initialize immediately
initAdminSidebar();

// Also initialize when DOM is ready as fallback
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAdminSidebar);
} else {
    // DOM already ready, initialize again after a short delay to ensure Firebase is loaded
    setTimeout(initAdminSidebar, 500);
}

