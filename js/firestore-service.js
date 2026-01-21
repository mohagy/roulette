/**
 * Firestore Service Layer
 * 
 * Provides a unified interface for all Firestore operations
 * Handles real-time synchronization for TV displays
 * Includes timestamp-based synchronization for simultaneous execution
 */

const FirestoreService = (function() {
    let firestore = null;
    let listeners = {};
    let isOnline = navigator.onLine;
    let connectionStatusListeners = [];
    let processedCommandIds = new Set(); // Track processed commands to prevent duplicates

    /**
     * Initialize the Firestore service
     */
    function initialize() {
        console.log('🔥 FirestoreService.initialize() called');
        console.log('  - firebase defined:', typeof firebase !== 'undefined');
        console.log('  - window.firebaseFirestore:', window.firebaseFirestore);
        console.log('  - firebase.firestore type:', typeof firebase?.firestore);
        
        // Try to get Firestore from window first
        if (window.firebaseFirestore) {
            firestore = window.firebaseFirestore;
            console.log('✅ Using window.firebaseFirestore');
        } else if (typeof firebase !== 'undefined' && typeof firebase.firestore === 'function') {
            // Try to initialize directly
            try {
                firestore = firebase.firestore();
                window.firebaseFirestore = firestore; // Also set it on window
                console.log('✅ Initialized Firestore directly from firebase.firestore()');
            } catch (error) {
                console.error('❌ Failed to initialize Firestore:', error);
                console.warn('⚠️ Firestore not available. Falling back to MySQL polling.');
                return false;
            }
        } else {
            console.warn('⚠️ Firestore not available. Falling back to MySQL polling.');
            console.log('Debug info:', {
                firebase: typeof firebase,
                firebaseFirestore: window.firebaseFirestore,
                firebaseFirestoreType: typeof firebase?.firestore
            });
            return false;
        }

        console.log('🔥 Firestore instance:', firestore);
        console.log('🔥 Firestore type:', typeof firestore);
        console.log('🔥 Firestore.collection type:', typeof firestore?.collection);
        
        // Monitor connection status
        firestore.enableNetwork().then(() => {
            isOnline = true;
            notifyConnectionStatus(true);
            console.log('✅ Firestore connected and network enabled');
        }).catch((error) => {
            console.warn('⚠️ Firestore connection issue:', error);
            // Still try to proceed - network might be enabled already
            isOnline = true;
            notifyConnectionStatus(true);
        });

        // Monitor online/offline events
        window.addEventListener('online', () => {
            if (firestore) {
                firestore.enableNetwork().then(() => {
                    isOnline = true;
                    notifyConnectionStatus(true);
                });
            }
        });

        window.addEventListener('offline', () => {
            if (firestore) {
                firestore.disableNetwork().then(() => {
                    isOnline = false;
                    notifyConnectionStatus(false);
                });
            }
        });

        console.log('FirestoreService initialized');
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
     * Write a spin command to Firestore
     * @param {number} winningNumber - The winning number (0-36)
     * @param {number} drawNumber - Current draw number
     * @param {Date} syncTimestamp - Server timestamp for synchronized execution
     * @param {string} source - Source of the command ("admin" | "master" | "auto")
     * @returns {Promise<string>} Command ID
     */
    async function writeSpinCommand(winningNumber, drawNumber, syncTimestamp, source = 'admin') {
        if (!firestore) {
            throw new Error('Firestore not initialized');
        }

        const commandId = `cmd_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
        
        // Ensure syncTimestamp is a Date object
        const syncDate = syncTimestamp instanceof Date ? syncTimestamp : new Date(syncTimestamp);
        
        const commandData = {
            winningNumber: winningNumber,
            drawNumber: drawNumber,
            syncTimestamp: firebase.firestore.Timestamp.fromDate(syncDate),
            createdAt: firebase.firestore.FieldValue.serverTimestamp(),
            commandId: commandId,
            source: source
        };

        try {
            await firestore.collection('spinCommands').doc(commandId).set(commandData);
            console.log('✅ Spin command written to Firestore:', commandId, 'Sync time:', syncDate.toISOString());
            return commandId;
        } catch (error) {
            console.error('❌ Error writing spin command to Firestore:', error);
            throw error;
        }
    }

    /**
     * Listen to spin commands in real-time
     * @param {Function} callback - Callback function that receives command data
     * @returns {Function} Unsubscribe function
     */
    function listenToSpinCommands(callback) {
        if (!firestore) {
            console.warn('⚠️ Firestore not available, cannot listen to spin commands');
            return () => {}; // Return no-op unsubscribe function
        }

        const listenerKey = 'spinCommands';
        
        console.log('🔥 Setting up spin commands listener...');
        
        // Query for recent commands and listen for new ones
        // Note: We listen to all documents and filter client-side to avoid index requirements
        const unsubscribe = firestore.collection('spinCommands')
            .onSnapshot((snapshot) => {
                console.log('🔥 Spin commands snapshot received, changes:', snapshot.docChanges().length);
                
                if (snapshot.empty) {
                    console.log('ℹ️ No spin commands in Firestore yet');
                }
                
                // Filter to only process new documents (added in this snapshot)
                const newDocuments = snapshot.docChanges().filter(change => change.type === 'added');
                
                if (newDocuments.length === 0 && snapshot.docChanges().length > 0) {
                    console.log('ℹ️ Received document changes but no new documents (might be initial load)');
                }
                
                newDocuments.forEach((change) => {
                    console.log('🔥 Document change:', change.type, change.doc.id);
                    
                    if (change.type === 'added') {
                        const commandData = change.doc.data();
                        const commandId = commandData.commandId || change.doc.id;

                        // Prevent duplicate processing
                        if (processedCommandIds.has(commandId)) {
                            console.log('⏭️ Skipping already processed command:', commandId);
                            return;
                        }

                        processedCommandIds.add(commandId);
                        
                        // Clean up old command IDs (keep last 100)
                        if (processedCommandIds.size > 100) {
                            const firstId = Array.from(processedCommandIds)[0];
                            processedCommandIds.delete(firstId);
                        }

                        // Convert Firestore timestamp to Date
                        let syncTimestamp;
                        if (commandData.syncTimestamp) {
                            if (commandData.syncTimestamp.toDate) {
                                syncTimestamp = commandData.syncTimestamp.toDate();
                            } else if (commandData.syncTimestamp instanceof Date) {
                                syncTimestamp = commandData.syncTimestamp;
                            } else {
                                syncTimestamp = new Date(commandData.syncTimestamp);
                            }
                        } else {
                            syncTimestamp = new Date(Date.now() + 1000); // Default: 1 second from now
                        }
                        
                        console.log('🔥 New spin command received:', {
                            commandId: commandId,
                            winningNumber: commandData.winningNumber,
                            drawNumber: commandData.drawNumber,
                            syncTimestamp: syncTimestamp.toISOString(),
                            source: commandData.source || 'admin',
                            fullData: commandData
                        });
                        
                        callback({
                            commandId: commandId,
                            winningNumber: commandData.winningNumber,
                            drawNumber: commandData.drawNumber,
                            syncTimestamp: syncTimestamp,
                            source: commandData.source || 'admin'
                        });
                    }
                });
            }, (error) => {
                console.error('❌ Error listening to spin commands:', error);
                console.error('Error details:', {
                    code: error.code,
                    message: error.message,
                    stack: error.stack
                });
            });

        listeners[listenerKey] = unsubscribe;
        return unsubscribe;
    }

    /**
     * Write game state to Firestore
     * @param {Object} gameState - Game state object
     */
    async function writeGameState(gameState) {
        if (!firestore) {
            throw new Error('Firestore not initialized');
        }

        const stateData = {
            ...gameState,
            lastUpdated: firebase.firestore.FieldValue.serverTimestamp()
        };

        try {
            await firestore.collection('gameState').doc('current').set(stateData, { merge: true });
            console.log('✅ Game state written to Firestore');
        } catch (error) {
            console.error('❌ Error writing game state to Firestore:', error);
            throw error;
        }
    }

    /**
     * Listen to game state changes in real-time
     * @param {Function} callback - Callback function that receives game state
     * @returns {Function} Unsubscribe function
     */
    function listenToGameState(callback) {
        if (!firestore) {
            console.warn('⚠️ Firestore not available, cannot listen to game state');
            return () => {}; // Return no-op unsubscribe function
        }

        const listenerKey = 'gameState';
        
        const unsubscribe = firestore.collection('gameState').doc('current')
            .onSnapshot((docSnapshot) => {
                try {
                    // Check if document exists - handle different Firebase versions
                    const exists = docSnapshot.exists !== undefined ? docSnapshot.exists : 
                                  (typeof docSnapshot.exists === 'function' ? docSnapshot.exists() : false);
                    
                    if (exists) {
                        // Get data - handle different Firebase versions
                        const data = typeof docSnapshot.data === 'function' ? docSnapshot.data() : docSnapshot.data;
                        if (data) {
                            console.log('🔥 Game state updated from Firestore:', data);
                            callback(data);
                        } else {
                            console.warn('⚠️ Game state document exists but has no data');
                        }
                    } else {
                        console.warn('⚠️ Game state document does not exist');
                    }
                } catch (error) {
                    console.error('❌ Error processing game state snapshot:', error);
                }
            }, (error) => {
                console.error('❌ Error listening to game state:', error);
            });

        listeners[listenerKey] = unsubscribe;
        return unsubscribe;
    }

    /**
     * Write winning number to Firestore
     * @param {number} drawNumber - Draw number
     * @param {number} winningNumber - Winning number (0-36)
     * @param {string} winningColor - Color of the winning number
     * @param {string} source - Source ("manual" | "auto")
     */
    async function writeWinningNumber(drawNumber, winningNumber, winningColor, source = 'manual') {
        if (!firestore) {
            throw new Error('Firestore not initialized');
        }

        const winningData = {
            winningNumber: winningNumber,
            winningColor: winningColor,
            drawNumber: drawNumber,
            source: source,
            setAt: firebase.firestore.FieldValue.serverTimestamp(),
            syncedFromMySQL: true
        };

        try {
            await firestore.collection('winningNumbers').doc(drawNumber.toString()).set(winningData, { merge: true });
            console.log('✅ Winning number written to Firestore:', drawNumber, winningNumber);
        } catch (error) {
            console.error('❌ Error writing winning number to Firestore:', error);
            throw error;
        }
    }

    /**
     * Get winning number from Firestore
     * @param {number} drawNumber - Draw number
     * @returns {Promise<Object|null>} Winning number data or null
     */
    async function getWinningNumber(drawNumber) {
        if (!firestore) {
            return null;
        }

        try {
            const doc = await firestore.collection('winningNumbers').doc(drawNumber.toString()).get();
            if (doc.exists()) {
                return doc.data();
            }
            return null;
        } catch (error) {
            console.error('❌ Error getting winning number from Firestore:', error);
            return null;
        }
    }

    /**
     * Write timer state to Firestore
     * @param {number} countdownEndTime - Timestamp when countdown ends (milliseconds)
     * @param {number} countdownTime - Current countdown time in seconds
     * @returns {Promise<boolean>} Success status
     */
    async function writeTimerState(countdownEndTime, countdownTime) {
        if (!firestore) {
            return false;
        }

        try {
            const timerData = {
                countdownEndTime: countdownEndTime,
                countdownTime: countdownTime,
                lastUpdated: firebase.firestore.FieldValue.serverTimestamp()
            };

            await firestore.collection('gameState').doc('current').set({
                timer: timerData
            }, { merge: true });

            console.log('✅ Timer state written to Firestore:', { countdownEndTime, countdownTime });
            return true;
        } catch (error) {
            console.error('❌ Error writing timer state to Firestore:', error);
            return false;
        }
    }

    /**
     * Listen to timer state changes in real-time
     * @param {Function} callback - Callback function that receives timer data
     * @returns {Function} Unsubscribe function
     */
    function listenToTimerState(callback) {
        if (!firestore) {
            console.warn('⚠️ Firestore not available, cannot listen to timer state');
            return () => {}; // Return no-op unsubscribe function
        }

        const listenerKey = 'timerState';
        
        console.log('🔥 Setting up timer state listener...');
        
        const unsubscribe = firestore.collection('gameState').doc('current')
            .onSnapshot((docSnapshot) => {
                try {
                    // Check if document exists - handle different Firebase versions
                    const exists = docSnapshot.exists !== undefined ? docSnapshot.exists : 
                                  (typeof docSnapshot.exists === 'function' ? docSnapshot.exists() : false);
                    
                    if (exists) {
                        // Get data - handle different Firebase versions
                        const data = typeof docSnapshot.data === 'function' ? docSnapshot.data() : docSnapshot.data;
                        if (data && data.timer) {
                            const timerData = data.timer;
                            console.log('🔥 Timer state updated from Firestore:', timerData);
                            
                            // Convert Firestore timestamp to milliseconds if needed
                            let countdownEndTime = timerData.countdownEndTime;
                            if (countdownEndTime && typeof countdownEndTime === 'object' && countdownEndTime.toMillis) {
                                countdownEndTime = countdownEndTime.toMillis();
                            }
                            
                            callback({
                                countdownEndTime: countdownEndTime,
                                countdownTime: timerData.countdownTime,
                                lastUpdated: timerData.lastUpdated
                            });
                        }
                    }
                } catch (error) {
                    console.error('❌ Error processing timer state snapshot:', error);
                }
            }, (error) => {
                console.error('❌ Error listening to timer state:', error);
            });

        listeners[listenerKey] = unsubscribe;
        return unsubscribe;
    }

    /**
     * Unsubscribe from a listener
     * @param {string} listenerKey - Key of the listener to unsubscribe
     */
    function unsubscribe(listenerKey) {
        if (listeners[listenerKey]) {
            listeners[listenerKey]();
            delete listeners[listenerKey];
            console.log('✅ Unsubscribed from:', listenerKey);
        }
    }

    /**
     * Unsubscribe from all listeners
     */
    function unsubscribeAll() {
        Object.keys(listeners).forEach(key => {
            unsubscribe(key);
        });
    }

    // Initialize when Firebase is ready
    function tryInitialize() {
        console.log('🔥 FirestoreService: Attempting initialization...');
        console.log('  - window.firebase:', typeof window.firebase);
        console.log('  - window.firebaseFirestore:', window.firebaseFirestore);
        console.log('  - firebase.firestore type:', typeof window.firebase?.firestore);
        
        // Try multiple ways to get Firestore
        if (window.firebaseFirestore) {
            console.log('✅ firebaseFirestore found on window, initializing...');
            const result = initialize();
            if (result) {
                console.log('✅ FirestoreService initialized successfully');
                window.dispatchEvent(new CustomEvent('firestore-service-ready'));
            }
            return result;
        } else if (typeof window.firebase !== 'undefined' && typeof window.firebase.firestore === 'function') {
            // Try to initialize directly
            console.log('✅ firebase.firestore() available, initializing directly...');
            try {
                const firestoreInstance = window.firebase.firestore();
                window.firebaseFirestore = firestoreInstance; // Set it for future use
                console.log('✅ Firestore instance created:', firestoreInstance);
                const result = initialize();
                if (result) {
                    console.log('✅ FirestoreService initialized successfully');
                    window.dispatchEvent(new CustomEvent('firestore-service-ready'));
                }
                return result;
            } catch (error) {
                console.error('❌ Failed to initialize Firestore directly:', error);
                console.error('Error details:', {
                    message: error.message,
                    name: error.name,
                    stack: error.stack
                });
                return false;
            }
        } else {
            console.log('⏳ firebaseFirestore not ready yet');
            console.log('Debug info:', {
                firebaseExists: typeof window.firebase !== 'undefined',
                firestoreExists: typeof window.firebase?.firestore !== 'undefined',
                firestoreType: typeof window.firebase?.firestore,
                firebaseFirestoreOnWindow: !!window.firebaseFirestore
            });
            return false;
        }
    }
    
    // Try to initialize immediately
    if (!tryInitialize()) {
        // Wait for firestore-ready event
        window.addEventListener('firestore-ready', () => {
            console.log('🔥 FirestoreService: firestore-ready event received');
            tryInitialize();
        });
        
        // Also wait for firebase-ready event (in case firestore-ready wasn't dispatched)
        window.addEventListener('firebase-ready', () => {
            console.log('🔥 FirestoreService: firebase-ready event received, retrying...');
            setTimeout(() => tryInitialize(), 500);
        });
        
        // Also try periodically
        let attempts = 0;
        const maxAttempts = 10;
        const retryInterval = setInterval(() => {
            attempts++;
            if (tryInitialize() || attempts >= maxAttempts) {
                clearInterval(retryInterval);
                if (attempts >= maxAttempts) {
                    console.warn('⚠️ FirestoreService: Failed to initialize after', maxAttempts, 'attempts');
                }
            }
        }, 500);
    }

    // Public API
    return {
        initialize,
        writeSpinCommand,
        listenToSpinCommands,
        writeGameState,
        listenToGameState,
        writeWinningNumber,
        getWinningNumber,
        writeTimerState,
        listenToTimerState,
        onConnectionStatusChange,
        unsubscribe,
        unsubscribeAll,
        isAvailable: () => firestore !== null,
        isOnline: () => isOnline
    };
})();

// Export for use in other files
window.FirestoreService = FirestoreService;

// Auto-initialize when Firebase is ready
(function autoInitializeFirestore() {
    function tryInitialize() {
        if (typeof firebase !== 'undefined' && window.FirestoreService) {
            console.log('🔥 Auto-initializing FirestoreService...');
            const initialized = window.FirestoreService.initialize();
            if (initialized) {
                console.log('✅ FirestoreService auto-initialized successfully');
                window.dispatchEvent(new CustomEvent('firestore-ready'));
            } else {
                console.warn('⚠️ FirestoreService auto-initialization failed, will retry...');
                setTimeout(tryInitialize, 500);
            }
        } else {
            // Retry after a short delay
            setTimeout(tryInitialize, 100);
        }
    }
    
    // Start trying to initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', tryInitialize);
    } else {
        tryInitialize();
    }
    
    // Also listen for firebase-ready event
    window.addEventListener('firebase-ready', () => {
        console.log('🔥 Firebase ready event received, initializing FirestoreService...');
        setTimeout(() => {
            if (window.FirestoreService) {
                const initialized = window.FirestoreService.initialize();
                if (initialized) {
                    console.log('✅ FirestoreService initialized after firebase-ready');
                    window.dispatchEvent(new CustomEvent('firestore-ready'));
                }
            }
        }, 200);
    });
})();

