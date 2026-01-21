/**
 * Authentication System for Monitoring App
 * Uses Firebase Auth for staff login
 */

const MonitoringAuth = {
    currentUser: null,
    
    /**
     * Initialize auth
     */
    init() {
        // Check if user is already logged in
        const storedUser = localStorage.getItem('monitoring_user');
        if (storedUser) {
            try {
                this.currentUser = JSON.parse(storedUser);
                // Check session expiry (24 hours)
                const sessionTime = localStorage.getItem('monitoring_session_time');
                if (sessionTime && Date.now() - parseInt(sessionTime) < 24 * 60 * 60 * 1000) {
                    return true;
                } else {
                    this.logout();
                }
            } catch (e) {
                console.error('Error loading user:', e);
                this.logout();
            }
        }
        return false;
    },
    
    /**
     * Login with username and password
     * For now, using simple authentication (can be enhanced with Firebase Auth)
     */
    async login(username, password) {
        // Simple authentication - in production, use Firebase Auth or your API
        // For monitoring staff, we'll use a simple check
        const monitoringStaff = {
            'monitor001': { password: 'monitor123', role: 'viewer', name: 'Monitoring Viewer' },
            'monitor002': { password: 'monitor123', role: 'analyst', name: 'Monitoring Analyst' },
            'monitor003': { password: 'monitor123', role: 'auditor', name: 'Monitoring Auditor' },
            'supervisor': { password: 'super123', role: 'supervisor', name: 'Monitoring Supervisor' }
        };
        
        if (monitoringStaff[username] && monitoringStaff[username].password === password) {
            this.currentUser = {
                username: username,
                role: monitoringStaff[username].role,
                name: monitoringStaff[username].name
            };
            
            localStorage.setItem('monitoring_user', JSON.stringify(this.currentUser));
            localStorage.setItem('monitoring_session_time', Date.now().toString());
            
            return { success: true, user: this.currentUser };
        }
        
        return { success: false, message: 'Invalid username or password' };
    },
    
    /**
     * Logout
     */
    logout() {
        this.currentUser = null;
        localStorage.removeItem('monitoring_user');
        localStorage.removeItem('monitoring_session_time');
        window.location.href = 'login.html';
    },
    
    /**
     * Check if user is authenticated
     */
    isAuthenticated() {
        return this.currentUser !== null;
    },
    
    /**
     * Check if user has required role
     */
    hasRole(requiredRole) {
        if (!this.currentUser) return false;
        
        const roleHierarchy = {
            'viewer': 1,
            'analyst': 2,
            'auditor': 3,
            'supervisor': 4
        };
        
        const userLevel = roleHierarchy[this.currentUser.role] || 0;
        const requiredLevel = roleHierarchy[requiredRole] || 0;
        
        return userLevel >= requiredLevel;
    },
    
    /**
     * Get current user
     */
    getCurrentUser() {
        return this.currentUser;
    }
};

// Initialize on load
if (typeof window !== 'undefined') {
    window.MonitoringAuth = MonitoringAuth;
}

