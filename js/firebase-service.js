/**
 * Firebase Service Layer
 * 
 * Provides a unified interface for all Firebase Realtime Database operations
 * Handles CRUD operations, real-time listeners, and offline support
 */

const FirebaseService = (function() {
    let database = null;
    let listeners = {};
    let offlineQueue = [];
    let isOnline = navigator.onLine;
    let connectionStatusListeners = [];

    /**
     * Initialize the Firebase service
     */
    function initialize() {
        if (typeof firebase === 'undefined' || !window.firebaseDatabase) {
            console.error('Firebase not initialized. Make sure firebase-config.js is loaded first.');
            return false;
        }

        database = window.firebaseDatabase;
        
        // Monitor connection status
        const connectedRef = database.ref('.info/connected');
        connectedRef.on('value', (snapshot) => {
            const wasOnline = isOnline;
            isOnline = snapshot.val() === true;
            
            console.log('🔥 Firebase connection status:', isOnline ? 'ONLINE' : 'OFFLINE');
            
            if (wasOnline !== isOnline) {
                notifyConnectionStatus(isOnline);
                
                if (isOnline) {
                    console.log('✅ Firebase connected! Processing queued operations...');
                    processOfflineQueue();
                } else {
                    console.warn('⚠️ Firebase disconnected');
                }
            }
        });
        
        // Also check server time offset to verify connection
        const serverTimeOffsetRef = database.ref('.info/serverTimeOffset');
        serverTimeOffsetRef.on('value', (snapshot) => {
            const offset = snapshot.val();
            if (offset !== null) {
                console.log('🔥 Firebase server time offset:', offset, 'ms - Connection verified');
                // If we can get server time, we're connected
                if (!isOnline) {
                    isOnline = true;
                    notifyConnectionStatus(true);
                }
            }
        });

        // Monitor online/offline events
        window.addEventListener('online', () => {
            isOnline = true;
            notifyConnectionStatus(true);
        });

        window.addEventListener('offline', () => {
            isOnline = false;
            notifyConnectionStatus(false);
        });

        console.log('FirebaseService initialized');
        return true;
    }

    /**
     * Notify all connection status listeners
     */
    function notifyConnectionStatus(online) {
        connectionStatusListeners.forEach(listener => {
            try {
                listener(online);
            } catch (error) {
                console.error('Error in connection status listener:', error);
            }
        });
    }

    /**
     * Add a connection status listener
     */
    function onConnectionStatusChange(callback) {
        connectionStatusListeners.push(callback);
        // Immediately notify with current status
        callback(isOnline);
    }

    /**
     * Process offline queue when connection is restored
     */
    function processOfflineQueue() {
        if (offlineQueue.length === 0) return;

        console.log(`Processing ${offlineQueue.length} queued operations`);
        const queue = [...offlineQueue];
        offlineQueue = [];

        queue.forEach(operation => {
            try {
                executeOperation(operation);
            } catch (error) {
                console.error('Error processing queued operation:', error);
                // Re-queue if it fails
                offlineQueue.push(operation);
            }
        });
    }

    /**
     * Execute a database operation
     */
    function executeOperation(operation) {
        const { type, path, data, method } = operation;
        const ref = database.ref(path);

        switch (method) {
            case 'set':
                return ref.set(data);
            case 'update':
                return ref.update(data);
            case 'push':
                return ref.push(data);
            case 'remove':
                return ref.remove();
            default:
                throw new Error(`Unknown operation method: ${method}`);
        }
    }

    /**
     * Queue an operation for later execution
     */
    function queueOperation(operation) {
        offlineQueue.push({
            ...operation,
            timestamp: Date.now()
        });
        console.log('Operation queued for offline execution:', operation);
    }

    /**
     * Write data to Firebase (with offline support)
     */
    function write(path, data, method = 'set') {
        if (!database) {
            console.error('❌ FirebaseService not initialized');
            return Promise.reject(new Error('FirebaseService not initialized'));
        }

        const operation = { type: 'write', path, data, method };
        
        console.log('🔥 FirebaseService.write:', { path, method, dataKeys: Object.keys(data || {}) });

        if (isOnline) {
            try {
                const result = executeOperation(operation);
                console.log('✅ FirebaseService.write successful:', path);
                return result;
            } catch (error) {
                console.error('❌ Error writing to Firebase:', error);
                console.error('Error details:', {
                    path,
                    method,
                    errorMessage: error.message,
                    errorCode: error.code
                });
                queueOperation(operation);
                return Promise.reject(error);
            }
        } else {
            console.warn('⚠️ Firebase offline, queuing operation:', path);
            queueOperation(operation);
            return Promise.resolve({ queued: true });
        }
    }

    /**
     * Read data from Firebase
     */
    function read(path) {
        if (!database) {
            console.error('FirebaseService not initialized');
            return Promise.reject(new Error('FirebaseService not initialized'));
        }

        return database.ref(path).once('value')
            .then(snapshot => snapshot.val())
            .catch(error => {
                console.error('Error reading from Firebase:', error);
                throw error;
            });
    }

    /**
     * Update data in Firebase
     */
    function update(path, data) {
        return write(path, data, 'update');
    }

    /**
     * Push data to Firebase (creates new child with auto-generated key)
     */
    function push(path, data) {
        return write(path, data, 'push');
    }

    /**
     * Delete data from Firebase
     */
    function remove(path) {
        return write(path, null, 'remove');
    }

    /**
     * Listen to real-time changes at a path
     */
    function listen(path, callback, eventType = 'value') {
        if (!database) {
            console.error('FirebaseService not initialized');
            return null;
        }

        const ref = database.ref(path);
        const listenerKey = `${path}_${eventType}_${Date.now()}`;

        const handler = (snapshot) => {
            try {
                callback(snapshot.val(), snapshot);
            } catch (error) {
                console.error('Error in Firebase listener callback:', error);
            }
        };

        ref.on(eventType, handler);
        
        listeners[listenerKey] = {
            ref: ref,
            eventType: eventType,
            handler: handler,
            path: path
        };

        console.log(`Listening to ${path} (${eventType})`);
        
        return listenerKey;
    }

    /**
     * Stop listening to a path
     */
    function unlisten(listenerKey) {
        if (listeners[listenerKey]) {
            const listener = listeners[listenerKey];
            listener.ref.off(listener.eventType, listener.handler);
            delete listeners[listenerKey];
            console.log(`Stopped listening to ${listener.path}`);
            return true;
        }
        return false;
    }

    /**
     * Stop all listeners
     */
    function unlistenAll() {
        Object.keys(listeners).forEach(key => {
            unlisten(key);
        });
        console.log('All Firebase listeners stopped');
    }

    /**
     * Game State Operations
     */
    const GameState = {
        getCurrent: () => read('gameState/current'),
        updateCurrent: (data) => update('gameState/current', data),
        setCurrent: (data) => write('gameState/current', data),
        listen: (callback) => listen('gameState/current', callback),
        
        getDrawInfo: () => read('gameState/drawInfo'),
        updateDrawInfo: (data) => update('gameState/drawInfo', data),
        listenDrawInfo: (callback) => listen('gameState/drawInfo', callback)
    };

    /**
     * Draw Operations
     */
    const Draws = {
        get: (drawNumber) => read(`draws/${drawNumber}`),
        set: (drawNumber, data) => write(`draws/${drawNumber}`, data),
        update: (drawNumber, data) => update(`draws/${drawNumber}`, data),
        listen: (drawNumber, callback) => listen(`draws/${drawNumber}`, callback),
        listenAll: (callback) => listen('draws', callback, 'child_added'),
        getRecent: (limit = 10) => {
            return database.ref('draws')
                .orderByKey()
                .limitToLast(limit)
                .once('value')
                .then(snapshot => {
                    const draws = [];
                    snapshot.forEach(child => {
                        draws.push({
                            drawNumber: child.key,
                            ...child.val()
                        });
                    });
                    return draws.reverse();
                });
        }
    };

    /**
     * Analytics Operations
     */
    const Analytics = {
        getCurrent: () => read('analytics/current'),
        updateCurrent: (data) => update('analytics/current', data),
        setCurrent: (data) => write('analytics/current', data),
        listen: (callback) => listen('analytics/current', callback)
    };

    /**
     * Betting Slips Operations
     */
    const BettingSlips = {
        get: (slipId) => read(`bettingSlips/${slipId}`),
        set: (slipId, data) => write(`bettingSlips/${slipId}`, data),
        update: (slipId, data) => update(`bettingSlips/${slipId}`, data),
        push: (data) => push('bettingSlips', data),
        listen: (slipId, callback) => listen(`bettingSlips/${slipId}`, callback)
    };

    /**
     * Bets Operations
     */
    const Bets = {
        get: (betId) => read(`bets/${betId}`),
        set: (betId, data) => write(`bets/${betId}`, data),
        push: (data) => push('bets', data),
        listen: (betId, callback) => listen(`bets/${betId}`, callback)
    };

    /**
     * Users Operations
     */
    const Users = {
        get: (userId) => read(`users/${userId}`),
        set: (userId, data) => write(`users/${userId}`, data),
        update: (userId, data) => update(`users/${userId}`, data),
        listen: (userId, callback) => listen(`users/${userId}`, callback)
    };

    /**
     * Transactions Operations
     */
    const Transactions = {
        get: (transactionId) => read(`transactions/${transactionId}`),
        push: (data) => push('transactions', data),
        listen: (transactionId, callback) => listen(`transactions/${transactionId}`, callback)
    };

    // Public API
    return {
        initialize,
        write,
        read,
        update,
        push,
        remove,
        listen,
        unlisten,
        unlistenAll,
        onConnectionStatusChange,
        isOnline: () => isOnline,
        isInitialized: () => database !== null,
        
        // Namespaced operations
        GameState,
        Draws,
        Analytics,
        BettingSlips,
        Bets,
        Users,
        Transactions
    };
})();

// Auto-initialize when Firebase is ready
function initFirebaseService() {
    console.log('🔥 FirebaseService: Attempting to initialize...');
    
    if (FirebaseService.initialize()) {
        console.log('✅ FirebaseService initialized successfully');
    } else {
        console.warn('⚠️ FirebaseService: Firebase not ready, waiting for firebase-ready event...');
        window.addEventListener('firebase-ready', () => {
            console.log('🔥 FirebaseService: firebase-ready event received, initializing...');
            if (FirebaseService.initialize()) {
                console.log('✅ FirebaseService initialized successfully');
            }
        }, { once: true });
    }
}

// Try to initialize immediately
initFirebaseService();

// Also try when DOM is ready as fallback
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(initFirebaseService, 100);
    });
} else {
    setTimeout(initFirebaseService, 100);
}

// Export for global use
window.FirebaseService = FirebaseService;

