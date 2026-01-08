/**
 * Firebase Authentication Service
 * 
 * Handles user authentication using Firebase Realtime Database
 * Works on static hosting (no PHP required)
 */

const FirebaseAuth = (function() {
    let database = null;
    let currentUser = null;

    /**
     * Initialize Firebase Auth
     */
    function initialize() {
        if (typeof firebase === 'undefined' || !window.firebaseDatabase) {
            console.error('Firebase not initialized');
            return false;
        }

        database = window.firebaseDatabase;
        
        // Load current user from localStorage
        loadUserFromStorage();
        
        console.log('FirebaseAuth initialized');
        return true;
    }

    /**
     * Load user from localStorage
     */
    function loadUserFromStorage() {
        try {
            const stored = localStorage.getItem('roulette_user');
            if (stored) {
                currentUser = JSON.parse(stored);
                // Check if session is still valid (24 hours)
                const sessionTime = localStorage.getItem('roulette_session_time');
                if (sessionTime && Date.now() - parseInt(sessionTime) < 24 * 60 * 60 * 1000) {
                    return currentUser;
                } else {
                    // Session expired
                    logout();
                }
            }
        } catch (e) {
            console.error('Error loading user from storage:', e);
        }
        return null;
    }

    /**
     * Save user to localStorage
     */
    function saveUserToStorage(user) {
        try {
            localStorage.setItem('roulette_user', JSON.stringify(user));
            localStorage.setItem('roulette_session_time', Date.now().toString());
        } catch (e) {
            console.error('Error saving user to storage:', e);
        }
    }

    /**
     * Initialize default users in Firebase (if not exists)
     */
    async function initializeDefaultUsers() {
        if (!database) return;

        try {
            const usersRef = database.ref('users');
            const snapshot = await usersRef.once('value');
            
            if (!snapshot.exists() || snapshot.numChildren() === 0) {
                // Create default users
                const defaultUsers = {
                    '123456789012': {
                        username: '123456789012',
                        password: '123456', // In production, this should be hashed
                        role: 'cashier',
                        created_at: new Date().toISOString()
                    },
                    '123456789013': {
                        username: '123456789013',
                        password: '123456',
                        role: 'cashier',
                        created_at: new Date().toISOString()
                    }
                };

                await usersRef.set(defaultUsers);
                console.log('✅ Default users created in Firebase');
            }
        } catch (error) {
            console.error('Error initializing default users:', error);
        }
    }

    /**
     * Authenticate user
     */
    async function login(username, password) {
        if (!database) {
            throw new Error('Firebase not initialized');
        }

        try {
            // Initialize default users if needed
            await initializeDefaultUsers();

            // Get user from Firebase
            const userRef = database.ref(`users/${username}`);
            const snapshot = await userRef.once('value');
            
            if (!snapshot.exists()) {
                throw new Error('User not found');
            }

            const user = snapshot.val();
            
            // Simple password check (in production, use hashed passwords)
            // For now, we'll do a simple comparison since Firebase doesn't support server-side hashing
            if (user.password !== password) {
                throw new Error('Invalid password');
            }

            // Update last login
            await userRef.update({
                last_login: new Date().toISOString()
            });

            // Create session
            currentUser = {
                user_id: username,
                username: user.username,
                role: user.role || 'cashier',
                last_login: new Date().toISOString()
            };

            saveUserToStorage(currentUser);

            console.log('✅ User logged in:', currentUser.username);
            return currentUser;

        } catch (error) {
            console.error('Login error:', error);
            throw error;
        }
    }

    /**
     * Logout user
     */
    function logout() {
        currentUser = null;
        localStorage.removeItem('roulette_user');
        localStorage.removeItem('roulette_session_time');
        console.log('User logged out');
    }

    /**
     * Get current user
     */
    function getCurrentUser() {
        if (!currentUser) {
            loadUserFromStorage();
        }
        return currentUser;
    }

    /**
     * Check if user is authenticated
     */
    function isAuthenticated() {
        const user = getCurrentUser();
        if (!user) return false;
        
        // Check session expiry
        const sessionTime = localStorage.getItem('roulette_session_time');
        if (sessionTime && Date.now() - parseInt(sessionTime) > 24 * 60 * 60 * 1000) {
            logout();
            return false;
        }
        
        return true;
    }

    /**
     * Require authentication (redirect to login if not authenticated)
     */
    function requireAuth() {
        if (!isAuthenticated()) {
            window.location.href = 'login.html';
            return false;
        }
        return true;
    }

    return {
        initialize,
        login,
        logout,
        getCurrentUser,
        isAuthenticated,
        requireAuth,
        initializeDefaultUsers
    };
})();

// Auto-initialize when Firebase is ready
if (typeof window !== 'undefined') {
    // Wait for Firebase to be ready
    const initAuth = () => {
        if (window.firebaseDatabase) {
            FirebaseAuth.initialize();
        } else {
            setTimeout(initAuth, 100);
        }
    };
    
    // Start initialization
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAuth);
    } else {
        initAuth();
    }
}

// Export for use in other scripts
if (typeof window !== 'undefined') {
    window.FirebaseAuth = FirebaseAuth;
}

