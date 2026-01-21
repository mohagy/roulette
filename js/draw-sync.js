/**
 * DrawSync Module
 * Provides real-time synchronization of draw numbers between the main game interface and TV display
 * Manages database interactions for current and next draw numbers
 */

const DrawSync = (function() {
    // Configuration
    const config = {
        fetchInterval: 5000,         // Poll for updates every 5 seconds
        drawSyncEndpoint: 'api/get_current_draw.php',  // Use correct API endpoint
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
        retryCount: 0
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
     * Fetch the current draw info from the database
     * @returns {Promise} Promise that resolves with the draw data
     */
    function fetchDrawInfo() {
        log('Fetching draw information from database');

        return fetch(config.drawSyncEndpoint + '?_cb=' + Date.now())
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Server responded with status ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                log('Received draw data:', data);

                // Handle api/get_current_draw.php response format
                if (data.status === 'success' && data.data) {
                    const currentDraw = data.data.current_draw_number || data.data.currentDraw || 1;
                    const nextDraw = (currentDraw + 1) > 480 ? 480 : (currentDraw + 1); // Calculate next draw, cap at 480
                    
                    console.log('[DrawSync] 📊 API Response:', {
                        current_draw_number: data.data.current_draw_number,
                        calculated_next: nextDraw,
                        server_time: data.data.server_time
                    });
                    
                    state.currentDraw = currentDraw;
                    state.nextDraw = nextDraw;
                    state.isInitialized = true;
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

                    return { success: true, currentDraw: currentDraw, nextDraw: nextDraw };
                } else if (data.success) {
                    // Fallback for old format
                    state.currentDraw = data.currentDraw;
                    state.nextDraw = data.nextDraw;
                    state.isInitialized = true;
                    state.retryCount = 0;

                    const event = new CustomEvent('drawSync:updated', {
                        detail: {
                            currentDraw: data.currentDraw,
                            nextDraw: data.nextDraw,
                            suppressDrawHeader: false
                        }
                    });
                    document.dispatchEvent(event);

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
     * Start polling for draw updates
     */
    function startPolling() {
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
    function updateDrawNumbers(currentDraw, nextDraw) {
        log(`Updating draw numbers: current=${currentDraw}, next=${nextDraw}`);

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

        // Remove hardcoded fallback values - use actual server values
        // Cap draw numbers at 480 (max draws per day)
        if (nextDraw > 480) {
            nextDraw = 480;
            log('Capped next draw at 480');
        }
        if (currentDraw > 480) {
            currentDraw = 480;
            log('Capped current draw at 480');
        }

        // Update the next draw number in the main UI
        const nextDrawElement = document.getElementById('next-draw-number');
        if (nextDrawElement) {
            nextDrawElement.textContent = `#${nextDraw}`;
            console.log('[DrawSync] ✅ Updated next-draw-number element to:', `#${nextDraw}`);
            log('Updated next-draw-number element');
        } else {
            console.warn('[DrawSync] ⚠️ next-draw-number element not found in DOM');
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
                startPolling();
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

    // Initialize on load
    if (document.readyState === 'loading') {
        window.addEventListener('DOMContentLoaded', init);
    } else {
        // DOM already loaded, initialize immediately
        setTimeout(init, 100);
    }
    
    // Also try to initialize after a short delay to ensure other scripts are loaded
    setTimeout(() => {
        if (!state.isInitialized) {
            console.log('[DrawSync] Retrying initialization...');
            init();
        }
    }, 2000);

    // Public API
    return {
        getCurrentDraw: () => state.currentDraw,
        getNextDraw: () => state.nextDraw,
        fetchDrawInfo: fetchDrawInfo,
        updateDrawNumbers: updateDrawNumbers,
        advanceToNextDraw: advanceToNextDraw,
        startPolling: startPolling,
        stopPolling: stopPolling,
        getConfig: () => ({...config}),
        setConfig: (newConfig) => {
            Object.assign(config, newConfig);
            log('Updated configuration', config);

            // Restart polling if interval changed
            if (state.pollingTimer) {
                stopPolling();
                startPolling();
            }
        }
    };
})();