/**
 * Admin Sidebar - Firebase Version
 * Creates the sidebar navigation matching the original design
 */

function createAdminSidebar() {
    // Wait for Firebase Auth to be available
    if (!window.FirebaseAuth || !window.FirebaseAuth.isInitialized()) {
        setTimeout(createAdminSidebar, 200);
        return;
    }
    
    const currentUser = window.FirebaseAuth.getCurrentUser();
    const username = currentUser ? currentUser.username : 'Admin';
    
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
                <li class="active">
                    <a href="admin.html">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>

                <li class="menu-header">User Management</li>
                <li>
                    <a href="#">
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
                <li>
                    <a href="#">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Cash Management</span>
                    </a>
                </li>
                <li>
                    <a href="redeem_voucher.html">
                        <i class="fas fa-ticket-alt"></i>
                        <span>Vouchers</span>
                    </a>
                </li>
                <li>
                    <a href="commission.html">
                        <i class="fas fa-percentage"></i>
                        <span>Commission</span>
                    </a>
                </li>
                <li>
                    <a href="my_transactions_new.html">
                        <i class="fas fa-exchange-alt"></i>
                        <span>Transactions</span>
                    </a>
                </li>

                <li class="menu-header">Game Management</li>
                <li>
                    <a href="#">
                        <i class="fas fa-cogs"></i>
                        <span>Game Settings</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-history"></i>
                        <span>Betting History</span>
                    </a>
                </li>
                <li>
                    <a href="#">
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

function setupSidebarInteractions() {
    // Mobile menu toggle
    const mobileToggle = document.getElementById('mobile-menu-toggle');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (mobileToggle) {
        mobileToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
    }
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            document.querySelector('.main-content').classList.toggle('expanded');
        });
    }
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('active')) {
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
            if (confirm('Are you sure you want to logout?')) {
                if (window.FirebaseAuth) {
                    window.FirebaseAuth.logout();
                }
                window.location.href = 'login.html';
            }
        });
    }
    
    // Handle responsive behavior
    function handleResponsiveLayout() {
        if (window.innerWidth <= 768) {
            sidebar.classList.remove('active');
        } else if (window.innerWidth <= 1024) {
            sidebar.classList.remove('active');
        } else {
            sidebar.classList.remove('active');
        }
    }
    
    document.addEventListener('DOMContentLoaded', handleResponsiveLayout);
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(handleResponsiveLayout, 250);
    });
}

// Initialize sidebar immediately (will retry if Firebase not ready)
createAdminSidebar();

