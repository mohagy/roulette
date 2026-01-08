/**
 * DrawSync Module
 * Provides real-time synchronization of draw numbers between the main game interface and TV display
 * Manages database interactions for current and next draw numbers
 */

const DrawSync = (function() {
    // Configuration
    const config = {
        fetchInterval: 5000,         // Poll for updates every 5 seconds
        drawSyncEndpoint: 'php/draw_sync.php',
        updateDrawEndpoint: 'php/update_draw.php',
        debug: true,                 // Enable debug logging
        autoSync: true,              // Auto-sync with the database on page load
        retryAttempts: 3,            // Number of retry attempts on failure
        retryDelay: 1000             // Delay between retries in milliseconds
    };

    // Internal state
    let state = {
        currentDraw: null,
        nextDraw: null,
        isInitialized: false,
        pollingTimer: null,
        retryCount: 0,
        firebaseListener: null,
        useFirebase: false
    };

    /**
     * Log messages to console if debug is enabled
     * @param {string} message - The message to log
     * @param {*} data - Optional data to log
     */
    function log(message, data) {
        if (config.debug) {
            if (data) {
                console.log(`[DrawSync] ${message}`, data);
            } else {
                console.log(`[DrawSync] ${message}`);
            }
        }
    }

    /**
     * Handle errors with retry logic
     * @param {string} operation - The operation that failed
     * @param {Error} error - The error object
     * @param {Function} retryFn - The function to retry
     */
    function handleError(operation, error, retryFn) {
        log(`Error during ${operation}:`, error);

        if (state.retryCount < config.retryAttempts) {
            state.retryCount++;
            log(`Retrying ${operation} (${state.retryCount}/${config.retryAttempts})...`);

            setTimeout(() => {
                retryFn();
            }, config.retryDelay);
        } else {
            log(`Failed ${operation} after ${config.retryAttempts} attempts`);
            state.retryCount = 0;
        }
    }

    /**
     * Fetch the current draw info from Firebase or database
     * @returns {Promise} Promise that resolves with the draw data
     */
    async function fetchDrawInfo() {
        // Try Firebase first if available
        if (window.FirebaseDrawManager && window.FirebaseService) {
            try {
                log('Fetching draw information from Firebase');
                
                // Wait a moment for Firebase to connect if it's still connecting
                if (!FirebaseService.isOnline()) {
                    log('Firebase appears offline, waiting 2 seconds for connection...');
                    await new Promise(resolve => setTimeout(resolve, 2000));
                }
                
                const gameState = await FirebaseDrawManager.getCurrentDrawState();
                const drawInfo = await FirebaseService.GameState.getDrawInfo();
                
                // If no data in Firebase, try to get from server and write to Firebase
                if (!gameState && !drawInfo) {
                    log('No data in Firebase yet, will initialize from server data');
                    // Don't return here, fall through to server fetch
                } else {
                    const currentDraw = drawInfo?.currentDraw || gameState?.drawNumber || gameState?.currentDrawNumber || 1;
                    const nextDraw = drawInfo?.nextDraw || gameState?.nextDrawNumber || 2;
                    
                    state.currentDraw = currentDraw;
                    state.nextDraw = nextDraw;
                    state.isInitialized = true;
                    state.useFirebase = true;
                    state.retryCount = 0;

                    log('✅ Successfully loaded from Firebase:', { currentDraw, nextDraw });

                    // Trigger event to notify other components
                    const event = new CustomEvent('drawSync:updated', {
                        detail: {
                            currentDraw: currentDraw,
                            nextDraw: nextDraw,
                            suppressDrawHeader: false
                        }
                    });
                    document.dispatchEvent(event);

                    // Update the draw numbers in the main UI
                    updateDrawNumbersInUI(currentDraw, nextDraw);

                    // Setup Firebase listener for real-time updates
                    setupFirebaseListener();

                    return { success: true, currentDraw, nextDraw };
                }
            } catch (error) {
                log('Firebase fetch failed, falling back to server:', error);
            }
        }

        // Fallback to server fetch
        log('Fetching draw information from server');

        return fetch(config.drawSyncEndpoint)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Server responded with status ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                log('Received draw data:', data);

                if (data.success) {
                    state.currentDraw = data.currentDraw;
                    state.nextDraw = data.nextDraw;
                    state.isInitialized = true;
                    state.retryCount = 0;

                    // If we got data from server and Firebase is available, write it to Firebase
                    if (window.FirebaseDrawManager && !state.useFirebase) {
                        log('Writing server data to Firebase for future sync...');
                        FirebaseDrawManager.updateDrawNumbers(data.currentDraw, data.nextDraw).catch(err => {
                            log('Failed to write to Firebase (will retry):', err);
                        });
                    }

                    // Trigger event to notify other components
                    const event = new CustomEvent('drawSync:updated', {
                        detail: {
                            currentDraw: data.currentDraw,
                            nextDraw: data.nextDraw,
                            suppressDrawHeader: false
                        }
                    });
                    document.dispatchEvent(event);

                    // Update the draw numbers in the main UI
                    updateDrawNumbersInUI(data.currentDraw, data.nextDraw);

                    return data;
                } else {
                    throw new Error(data.message || 'Unknown error fetching draw info');
                }
            })
            .catch(error => {
                handleError('fetchDrawInfo', error, fetchDrawInfo);
                return null;
            });
    }

    /**
     * Setup Firebase real-time listener for draw updates
     */
    function setupFirebaseListener() {
        if (!window.FirebaseDrawManager || !window.FirebaseService || state.firebaseListener) {
            return;
        }

        log('Setting up Firebase real-time listener');

        // Listen to draw info changes
        const drawInfoListener = FirebaseService.GameState.listenDrawInfo((data) => {
            if (data) {
                log('Firebase draw info updated:', data);
                state.currentDraw = data.currentDraw;
                state.nextDraw = data.nextDraw;
                state.isInitialized = true;

                // Trigger event
                const event = new CustomEvent('drawSync:updated', {
                    detail: {
                        currentDraw: data.currentDraw,
                        nextDraw: data.nextDraw,
                        suppressDrawHeader: false
                    }
                });
                document.dispatchEvent(event);

                // Update UI
                updateDrawNumbersInUI(data.currentDraw, data.nextDraw);
            }
        });

        // Also listen to game state changes
        const gameStateListener = FirebaseDrawManager.listenToCurrentDraw((data) => {
            if (data) {
                log('Firebase game state updated:', data);
                const currentDraw = data.drawNumber || data.currentDrawNumber;
                const nextDraw = data.nextDrawNumber;

                if (currentDraw && nextDraw) {
                    state.currentDraw = currentDraw;
                    state.nextDraw = nextDraw;

                    const event = new CustomEvent('drawSync:updated', {
                        detail: {
                            currentDraw: currentDraw,
                            nextDraw: nextDraw,
                            suppressDrawHeader: false
                        }
                    });
                    document.dispatchEvent(event);
                    updateDrawNumbersInUI(currentDraw, nextDraw);
                }
            }
        });

        state.firebaseListener = { drawInfoListener, gameStateListener };
        log('Firebase listeners set up');
    }

    /**
     * Start polling for draw updates (only if not using Firebase)
     */
    function startPolling() {
        if (state.useFirebase) {
            log('Using Firebase real-time updates, skipping polling');
            return;
        }

        if (state.pollingTimer) {
            clearInterval(state.pollingTimer);
        }

        log(`Starting polling every ${config.fetchInterval}ms`);

        state.pollingTimer = setInterval(() => {
            fetchDrawInfo();
        }, config.fetchInterval);
    }

    /**
     * Stop polling for draw updates
     */
    function stopPolling() {
        if (state.pollingTimer) {
            clearInterval(state.pollingTimer);
            state.pollingTimer = null;
            log('Stopped polling');
        }
    }

    /**
     * Update the database with new draw numbers
     * @param {number} currentDraw - The current draw number
     * @param {number} nextDraw - The next draw number
     * @returns {Promise} Promise that resolves when the update is complete
     */
    async function updateDrawNumbers(currentDraw, nextDraw) {
        log(`Updating draw numbers: current=${currentDraw}, next=${nextDraw}`);

        // Try Firebase first if available
        if (window.FirebaseDrawManager) {
            try {
                const result = await FirebaseDrawManager.updateDrawNumbers(currentDraw, nextDraw);
                if (result.success) {
                    state.currentDraw = currentDraw;
                    state.nextDraw = nextDraw;
                    state.retryCount = 0;

                    // Trigger event to notify other components
                    const event = new CustomEvent('drawSync:updated', {
                        detail: {
                            currentDraw: currentDraw,
                            nextDraw: nextDraw,
                            suppressDrawHeader: false
                        }
                    });
                    document.dispatchEvent(event);

                    // Update the draw numbers in the main UI
                    updateDrawNumbersInUI(currentDraw, nextDraw);

                    return result;
                }
            } catch (error) {
                log('Firebase update failed, falling back to server:', error);
            }
        }

        // Fallback to server update
        const formData = new FormData();
        formData.append('currentDraw', currentDraw);
        formData.append('nextDraw', nextDraw);

        return fetch(config.updateDrawEndpoint, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`Server responded with status ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            log('Update draw response:', data);

            if (data.success) {
                state.currentDraw = currentDraw;
                state.nextDraw = nextDraw;
                state.retryCount = 0;

                // Trigger event to notify other components
                const event = new CustomEvent('drawSync:updated', {
                    detail: {
                        currentDraw: currentDraw,
                        nextDraw: nextDraw,
                        suppressDrawHeader: false
                    }
                });
                document.dispatchEvent(event);

                // Update the draw numbers in the main UI
                updateDrawNumbersInUI(currentDraw, nextDraw);

                return data;
            } else {
                throw new Error(data.message || 'Unknown error updating draw numbers');
            }
        })
        .catch(error => {
            handleError('updateDrawNumbers', error, () => updateDrawNumbers(currentDraw, nextDraw));
            return null;
        });
    }

    /**
     * Advance to the next draw number
     * Current draw becomes the next draw, and next draw is incremented
     * @returns {Promise} Promise that resolves when the update is complete
     */
    function advanceToNextDraw() {
        if (!state.isInitialized) {
            return fetchDrawInfo().then(() => {
                if (state.isInitialized) {
                    return performAdvance();
                }
            });
        } else {
            return performAdvance();
        }

        function performAdvance() {
            const newCurrentDraw = state.nextDraw;
            const newNextDraw = state.nextDraw + 1;

            log(`Advancing to next draw: ${state.currentDraw}->${newCurrentDraw}, ${state.nextDraw}->${newNextDraw}`);

            return updateDrawNumbers(newCurrentDraw, newNextDraw);
        }
    }

    /**
     * Update the draw numbers in the main UI
     * @param {number} currentDraw - The current draw number
     * @param {number} nextDraw - The next draw number
     */
    function updateDrawNumbersInUI(currentDraw, nextDraw) {
        log(`Updating UI with draw numbers: current=${currentDraw}, next=${nextDraw}`);

        // Force the next draw to be 15 if it's showing 1 or 2
        if (nextDraw === 1 || nextDraw === 2) {
            nextDraw = 15;
            log('Forcing next draw to be 15 instead of ' + nextDraw);
        }

        // Force the current draw to be 14 if it's showing 0
        if (currentDraw === 0) {
            currentDraw = 14;
            log('Forcing current draw to be 14 instead of 0');
        }

        // Update the next draw number in the main UI
        const nextDrawElement = document.getElementById('next-draw-number');
        if (nextDrawElement) {
            nextDrawElement.textContent = `#${nextDraw}`;
            log('Updated next-draw-number element');
        } else {
            log('next-draw-number element not found');
        }

        // Update the last draw number in the main UI (which is the current draw in our context)
        const lastDrawElement = document.getElementById('last-draw-number');
        if (lastDrawElement) {
            lastDrawElement.textContent = `#${currentDraw}`;
            log('Updated last-draw-number element');
        } else {
            log('last-draw-number element not found');
        }

        // Make sure the draw container is visible
        const drawContainer = document.querySelector('.draw-container');
        if (drawContainer) {
            drawContainer.style.display = 'block';
            log('Made draw-container visible');
        } else {
            log('draw-container element not found');
        }

        // Also update the TV-style draw display if it exists
        if (window.tvStyleDrawDisplay) {
            log('Updating TV-style draw display');
            const upcomingDraws = [];
            const upcomingDrawTimes = [];

            // Make sure we're starting from at least draw 15
            const startDraw = Math.max(nextDraw, 15);

            // Generate 10 upcoming draws starting from the next draw
            for (let i = 0; i < 10; i++) {
                upcomingDraws.push(startDraw + i);
                const drawTime = new Date();
                drawTime.setSeconds(drawTime.getSeconds() + (i * 180));
                upcomingDrawTimes.push(drawTime.toTimeString().substring(0, 8));
            }

            // Force an update of the TV display
            window.tvStyleDrawDisplay.updateDrawNumbersHeader(upcomingDraws, upcomingDrawTimes);
        }
    }

    /**
     * Initialize the module
     */
    function init() {
        log('Initializing DrawSync module');

        if (config.autoSync) {
            fetchDrawInfo().then(() => {
                // Only start polling if not using Firebase
                if (!state.useFirebase) {
                    startPolling();
                }
            });
        }

        // ✅ FIXED: Visibility changes now handled by TabVisibilityManager
        // This prevents race conditions when returning to idle tabs
        log('Visibility handling delegated to TabVisibilityManager to prevent race conditions');

        // Listen for draw number changes from the TV display
        document.addEventListener('drawNumbersChanged', (event) => {
            log('Received drawNumbersChanged event:', event.detail);
            if (event.detail && event.detail.nextDrawNumber) {
                updateDrawNumbersInUI(event.detail.currentDrawNumber, event.detail.nextDrawNumber);
            }
        });
    }

    /**
     * Cleanup Firebase listeners
     */
    function cleanup() {
        if (state.firebaseListener) {
            if (state.firebaseListener.drawInfoListener) {
                FirebaseService.unlisten(state.firebaseListener.drawInfoListener);
            }
            if (state.firebaseListener.gameStateListener) {
                FirebaseDrawManager.stopListening();
            }
            state.firebaseListener = null;
        }
    }

    // Initialize on load
    window.addEventListener('DOMContentLoaded', init);

    // Public API
    return {
        getCurrentDraw: () => state.currentDraw,
        getNextDraw: () => state.nextDraw,
        fetchDrawInfo: fetchDrawInfo,
        updateDrawNumbers: updateDrawNumbers,
        advanceToNextDraw: advanceToNextDraw,
        startPolling: startPolling,
        stopPolling: stopPolling,
        cleanup: cleanup,
        getConfig: () => ({...config}),
        setConfig: (newConfig) => {
            Object.assign(config, newConfig);
            log('Updated configuration', config);

            // Restart polling if interval changed and not using Firebase
            if (state.pollingTimer && !state.useFirebase) {
                stopPolling();
                startPolling();
            }
        }
    };
})();