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
        if (window.FirebaseAuth && window.FirebaseAuth.isInitialized()) {
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
                    const userInfo = $('<div id="user-info" class="user-info left-toggle-showing" style="display: flex !important; z-index: 10000 !important; left: 20px; top: 20px;">' +
                        '<div class="user-info-drag-handle"><i class="fas fa-grip-lines"></i><span>Cashier Info</span><i class="fas fa-arrows-alt"></i></div>' +
                        '<div class="user-info-content">' +
                        '<div class="user-info-header"><i class="fas fa-user-circle"></i>' +
                        '<span>Cashier: <span class="username">' + user.username + '</span></span></div>' +
                        '<a href="my_transactions_new.html" class="transactions-link"><i class="fas fa-history"></i> Transactions</a>' +
                        '<a href="redeem_voucher.html" class="transactions-link"><i class="fas fa-ticket-alt"></i> Redeem Voucher</a>' +
                        '<a href="commission.html" class="transactions-link"><i class="fas fa-percentage"></i> Commission</a>' +
                        (user.role === 'admin' ? '<a href="admin.html" class="transactions-link admin-link"><i class="fas fa-cogs"></i> Admin Panel</a>' : '') +
                        '</div>' +
                        '</div>');

                    // Add user info to the body
                    $('body').append(userInfo);
                    
                    // Ensure panel is visible and positioned
                    setTimeout(function() {
                        const panel = document.getElementById('user-info');
                        if (panel) {
                            panel.style.display = 'flex';
                            panel.style.visibility = 'visible';
                            panel.style.opacity = '1';
                            panel.style.left = '20px';
                            panel.style.top = '20px';
                            panel.classList.add('left-toggle-showing');
                            panel.classList.remove('left-toggle-hiding');
                            panel.classList.remove('completely-hidden');
                            console.log('✅ User info panel created and made visible');
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
    function tryCheckAuth() {
        if (window.FirebaseAuth && window.FirebaseAuth.isInitialized()) {
            checkAuth();
        } else if (window.FirebaseAuth) {
            // FirebaseAuth exists but not initialized yet, wait a bit
            setTimeout(tryCheckAuth, 200);
        } else {
            // FirebaseAuth not loaded yet, wait longer
            setTimeout(tryCheckAuth, 500);
        }
    }
    
    // Start checking
    tryCheckAuth();
});
