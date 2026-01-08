/**
 * User Role Manager
 * Detects user authentication status and role for role-based UI controls
 */

(function() {
    'use strict';

    console.log('👤 User Role Manager - Initializing...');

    let userRole = null;
    let isAuthenticated = false;
    let userInfo = null;
    let roleCheckCallbacks = [];

    /**
     * Initialize the user role manager
     */
    function init() {
        console.log('👤 Initializing user role manager...');
        
        // Check user authentication and role
        checkUserRole();
        
        console.log('👤 User role manager initialized');
    }

    /**
     * Check user authentication and role via AJAX
     */
    function checkUserRole() {
        console.log('👤 Checking user authentication and role...');
        
        fetch('check_auth.php', {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.authenticated && data.user) {
                isAuthenticated = true;
                userRole = data.user.role;
                userInfo = data.user;
                
                console.log('👤 User authenticated:', {
                    id: userInfo.id,
                    username: userInfo.username,
                    role: userRole
                });
                
                // Trigger role-based callbacks
                triggerRoleCallbacks();
            } else {
                isAuthenticated = false;
                userRole = null;
                userInfo = null;
                
                console.log('👤 User not authenticated');
                
                // Trigger role-based callbacks for unauthenticated state
                triggerRoleCallbacks();
            }
        })
        .catch(error => {
            console.error('👤 Error checking user role:', error);
            
            // Fallback: assume not authenticated
            isAuthenticated = false;
            userRole = null;
            userInfo = null;
            
            // Trigger role-based callbacks for error state
            triggerRoleCallbacks();
        });
    }

    /**
     * Trigger all registered role-based callbacks
     */
    function triggerRoleCallbacks() {
        console.log('👤 Triggering role-based callbacks for role:', userRole);
        
        roleCheckCallbacks.forEach(callback => {
            try {
                callback({
                    isAuthenticated: isAuthenticated,
                    role: userRole,
                    userInfo: userInfo,
                    isAdmin: isAdmin(),
                    isRegularUser: isRegularUser()
                });
            } catch (error) {
                console.error('👤 Error in role callback:', error);
            }
        });
    }

    /**
     * Check if current user is an admin
     */
    function isAdmin() {
        return isAuthenticated && userRole === 'admin';
    }

    /**
     * Check if current user is a regular user (not admin)
     */
    function isRegularUser() {
        return isAuthenticated && userRole !== 'admin';
    }

    /**
     * Check if user has specific role
     */
    function hasRole(role) {
        return isAuthenticated && userRole === role;
    }

    /**
     * Check if user has any of the specified roles
     */
    function hasAnyRole(roles) {
        return isAuthenticated && roles.includes(userRole);
    }

    /**
     * Register a callback to be called when role information is available
     */
    function onRoleCheck(callback) {
        if (typeof callback === 'function') {
            roleCheckCallbacks.push(callback);
            
            // If role is already determined, call immediately
            if (userRole !== null || !isAuthenticated) {
                setTimeout(() => {
                    callback({
                        isAuthenticated: isAuthenticated,
                        role: userRole,
                        userInfo: userInfo,
                        isAdmin: isAdmin(),
                        isRegularUser: isRegularUser()
                    });
                }, 0);
            }
        }
    }

    /**
     * Get current user role
     */
    function getCurrentRole() {
        return userRole;
    }

    /**
     * Get current user info
     */
    function getCurrentUserInfo() {
        return userInfo;
    }

    /**
     * Check if user is authenticated
     */
    function getAuthenticationStatus() {
        return isAuthenticated;
    }

    /**
     * Refresh user role information
     */
    function refreshRole() {
        console.log('👤 Refreshing user role information...');
        checkUserRole();
    }

    /**
     * Add CSS class to body based on user role
     */
    function addRoleClassToBody() {
        // Remove existing role classes
        document.body.classList.remove('user-admin', 'user-regular', 'user-unauthenticated');
        
        if (isAdmin()) {
            document.body.classList.add('user-admin');
            console.log('👤 Added admin class to body');
        } else if (isRegularUser()) {
            document.body.classList.add('user-regular');
            console.log('👤 Added regular user class to body');
        } else {
            document.body.classList.add('user-unauthenticated');
            console.log('👤 Added unauthenticated class to body');
        }
    }

    /**
     * Initialize when DOM is ready
     */
    function initialize() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                setTimeout(init, 100);
            });
        } else {
            setTimeout(init, 100);
        }
    }

    // Register callback to add role class to body
    onRoleCheck((roleInfo) => {
        addRoleClassToBody();
    });

    // Initialize
    initialize();

    // Export public API
    window.UserRoleManager = {
        isAdmin,
        isRegularUser,
        hasRole,
        hasAnyRole,
        getCurrentRole,
        getCurrentUserInfo,
        getAuthenticationStatus,
        onRoleCheck,
        refreshRole
    };

    console.log('👤 User Role Manager - Loaded');

})();
