/**
 * Authentication Check Script
 * Verifies if user is logged in and redirects to login page if not
 */

$(document).ready(function() {
    // Check if we're already on the login page to prevent redirect loops
    if (window.location.pathname.includes('login.html') || window.location.pathname.includes('login.php')) {
        return; // Skip authentication check if already on login page
    }

    // Set a flag in sessionStorage to prevent redirect loops
    if (sessionStorage.getItem('redirecting')) {
        // Clear the flag after 2 seconds to allow future redirects
        setTimeout(function() {
            sessionStorage.removeItem('redirecting');
        }, 2000);
        return;
    }

    // Check authentication using Firebase (for static hosting) or PHP (for local)
    function checkAuth() {
        // Try Firebase authentication first
        if (window.FirebaseAuth && window.FirebaseAuth.isAuthenticated()) {
            const user = window.FirebaseAuth.getCurrentUser();
            if (user) {
                // User is authenticated, add logout button and user info if they don't exist
                if ($('#logout-button').length === 0) {
                    // Create logout button
                    const logoutButton = $('<div id="logout-button" class="logout-button">' +
                        '<i class="fas fa-sign-out-alt"></i>' +
                        '<span>Logout</span>' +
                        '</div>');

                    // Add logout button to the top-right corner
                    $('body').append(logoutButton);

                    // Create user info display with movable structure
                    const userInfo = $('<div id="user-info" class="user-info left-toggle-showing" style="display: flex !important; z-index: 10000 !important;">' +
                        '<div class="user-info-drag-handle"><i class="fas fa-grip-lines"></i><span>Cashier Info</span><i class="fas fa-arrows-alt"></i></div>' +
                        '<div class="user-info-content">' +
                        '<div class="user-info-header"><i class="fas fa-user-circle"></i>' +
                        '<span>Cashier: <span class="username">' + user.username + '</span></span></div>' +
                        '<a href="my_transactions_new.php" class="transactions-link"><i class="fas fa-history"></i> Transactions</a>' +
                        '<a href="redeem_voucher.php" class="transactions-link"><i class="fas fa-ticket-alt"></i> Redeem Voucher</a>' +
                        '<a href="commission.php" class="transactions-link"><i class="fas fa-percentage"></i> Commission</a>' +
                        (user.role === 'admin' ? '<a href="admin.php" class="transactions-link admin-link"><i class="fas fa-cogs"></i> Admin Panel</a>' : '') +
                        '</div>' +
                        '</div>');

                    // Add user info to the body
                    $('body').append(userInfo);
                    
                    // Ensure panel is visible
                    setTimeout(function() {
                        const panel = document.getElementById('user-info');
                        if (panel) {
                            panel.style.display = 'flex';
                            panel.classList.add('left-toggle-showing');
                            panel.classList.remove('left-toggle-hiding');
                        }
                    }, 100);

                    // Add click handler for logout
                    logoutButton.on('click', function() {
                        // Show confirmation dialog
                        if (confirm('Are you sure you want to logout?')) {
                            // Perform logout using Firebase
                            if (window.FirebaseAuth) {
                                window.FirebaseAuth.logout();
                            }
                            sessionStorage.setItem('redirecting', 'true');
                            window.location.href = 'login.html';
                        }
                    });
                }
                return; // User is authenticated
            }
        }

        // Fallback to PHP authentication (for local development)
        $.ajax({
            url: 'check_auth.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (!response.authenticated) {
                    sessionStorage.setItem('redirecting', 'true');
                    window.location.href = 'login.html';
                } else {
                    // Handle PHP authentication (same as Firebase above)
                    if ($('#logout-button').length === 0) {
                        const logoutButton = $('<div id="logout-button" class="logout-button">' +
                            '<i class="fas fa-sign-out-alt"></i>' +
                            '<span>Logout</span>' +
                            '</div>');
                        $('body').append(logoutButton);
                        // ... (same user info code as above)
                        logoutButton.on('click', function() {
                            if (confirm('Are you sure you want to logout?')) {
                                $.ajax({
                                    url: 'logout.php',
                                    type: 'POST',
                                    success: function() {
                                        sessionStorage.setItem('redirecting', 'true');
                                        window.location.href = 'login.html';
                                    }
                                });
                            }
                        });
                    }
                }
            },
            error: function(xhr, status, error) {
                // If PHP check fails and Firebase is not available, redirect to login
                if (!window.FirebaseAuth || !window.FirebaseAuth.isAuthenticated()) {
                    if (xhr.status === 401 || xhr.status === 403 || xhr.status === 404) {
                        sessionStorage.setItem('redirecting', 'true');
                        window.location.href = 'login.html';
                    }
                }
            }
        });
    }

    // Wait for Firebase to initialize, then check auth
    if (window.FirebaseAuth) {
        checkAuth();
    } else {
        // Wait a bit for Firebase to load
        setTimeout(function() {
            checkAuth();
        }, 500);
    }
});
