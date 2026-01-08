/**
 * Forced Number Synchronization
 * This module checks for forced winning numbers from the control panel and updates the display accordingly
 */

const ForcedNumberSync = (function() {
    // Configuration
    const config = {
        syncInterval: 3000, // Check more frequently (every 3 seconds)
        apiEndpoint: '/slipp/api/tv_sync.php',
        debug: true
    };

    // State variables
    let timer = null;
    let lastForcedNumber = null;
    let isSyncActive = false;
    let lastDrawNumber = null;
    let onForcedNumberChangedCallbacks = [];
    let onModeChangedCallbacks = [];

    /**
     * Initialize the sync process
     */
    function init() {
        log('Initializing forced number sync');

        // Start checking for forced numbers
        startSync();

        // Set up a visual indicator if in debug mode
        if (config.debug) {
            createSyncIndicator();
        }

        // Create a display for the forced number information
        createForcedNumberDisplay();

        // Regularly check for forced number changes
        window.addEventListener('beforeunload', stopSync);

        log('Forced number sync initialized');
    }

    /**
     * Start the synchronization process
     */
    function startSync() {
        if (isSyncActive) return;

        log('Starting forced number sync');
        isSyncActive = true;

        // Do an immediate check
        checkForForcedNumber();

        // Set up regular checking
        timer = setInterval(checkForForcedNumber, config.syncInterval);
    }

    /**
     * Stop the synchronization process
     */
    function stopSync() {
        if (!isSyncActive) return;

        log('Stopping forced number sync');
        isSyncActive = false;

        if (timer !== null) {
            clearInterval(timer);
            timer = null;
        }
    }

    /**
     * Check for forced numbers from the API
     */
    function checkForForcedNumber() {
        fetch(config.apiEndpoint)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Network response was not ok: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    processSyncData(data.data);
                } else {
                    console.error('API error:', data.message);
                    updateSyncIndicator(false);
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                updateSyncIndicator(false);
            });
    }

    /**
     * Process data from the sync API
     */
    function processSyncData(data) {
        updateSyncIndicator(true);

        // Check if draw number has changed
        if (lastDrawNumber !== data.current_draw) {
            lastDrawNumber = data.current_draw;
            log(`Draw number changed to ${data.current_draw}`);

            // Only reset forced number when draw changes if we're in automatic mode
            if (data.is_automatic) {
                lastForcedNumber = null;
                log('Reset forced number due to draw change in automatic mode');
            }
        }

        // Check if mode has changed from automatic to manual or vice versa
        const currentMode = data.is_automatic ? 'automatic' : 'manual';
        const previousMode = getDisplayMode();

        if (currentMode !== previousMode) {
            updateDisplayMode(currentMode);
            notifyModeChanged(currentMode);
            log(`Mode changed from ${previousMode} to ${currentMode}`);

            // If switching to automatic mode, clear the forced number
            if (currentMode === 'automatic' && lastForcedNumber !== null) {
                lastForcedNumber = null;
                notifyForcedNumberChanged(null, null);
                log('Cleared forced number due to switch to automatic mode');
            }
        }

        // Check if forced number has changed
        if (data.has_forced_number) {
            if (lastForcedNumber !== data.forced_number) {
                lastForcedNumber = data.forced_number;
                notifyForcedNumberChanged(data.forced_number, data.forced_color);

                log(`Forced number updated to: ${data.forced_number} (${data.forced_color})`);
            }
        } else if (lastForcedNumber !== null) {
            // Reset if we had a forced number before but don't anymore
            lastForcedNumber = null;
            notifyForcedNumberChanged(null, null);

            log('Forced number cleared');
        }

        // Always update the forced number display with the latest data
        updateForcedNumberDisplay(data);
    }

    /**
     * Create a visual indicator for sync status (debug mode only)
     */
    function createSyncIndicator() {
        if (document.getElementById('sync-indicator')) return;

        const indicator = document.createElement('div');
        indicator.id = 'sync-indicator';
        indicator.style.position = 'fixed';
        indicator.style.bottom = '5px';
        indicator.style.right = '5px';
        indicator.style.width = '10px';
        indicator.style.height = '10px';
        indicator.style.borderRadius = '50%';
        indicator.style.backgroundColor = '#888';
        indicator.style.zIndex = '9999';
        indicator.title = 'Forced Number Sync Status';

        document.body.appendChild(indicator);
    }

    /**
     * Update the sync indicator status
     */
    function updateSyncIndicator(isConnected) {
        const indicator = document.getElementById('sync-indicator');
        if (!indicator) return;

        indicator.style.backgroundColor = isConnected ? '#2ecc71' : '#e74c3c';
        indicator.title = isConnected ? 'Sync Active' : 'Sync Error';
    }

    /**
     * Create the forced number display - now a stub function since we're using a single notification
     */
    function createForcedNumberDisplay() {
        // We're now using a single notification in the HTML
        // This function is kept as a stub to avoid breaking existing code
        log('Using single notification system instead of creating forced number display');
    }

    /**
     * Update the forced number display - now just triggers callbacks
     */
    function updateForcedNumberDisplay(data) {
        // We're now using a single notification system
        // Just trigger the callbacks to update the notification
        if (data.has_forced_number) {
            notifyForcedNumberChanged(data.forced_number, data.forced_color);
        } else if (lastForcedNumber !== null) {
            notifyForcedNumberChanged(null, null);
        }
    }

    /**
     * Get the current display mode (automatic or manual)
     */
    function getDisplayMode() {
        // This could be stored in a data attribute or other state mechanism
        return document.body.getAttribute('data-mode') || 'automatic';
    }

    /**
     * Update the display mode
     */
    function updateDisplayMode(mode) {
        document.body.setAttribute('data-mode', mode);

        // Update visual indicators if needed
        const display = document.getElementById('forced-number-display');
        if (display) {
            display.querySelector('.forced-number-mode').textContent = mode === 'automatic' ? 'Auto' : 'Manual';
        }
    }

    /**
     * Register a callback for forced number changes
     */
    function onForcedNumberChanged(callback) {
        if (typeof callback === 'function') {
            onForcedNumberChangedCallbacks.push(callback);
        }
    }

    /**
     * Register a callback for mode changes
     */
    function onModeChanged(callback) {
        if (typeof callback === 'function') {
            onModeChangedCallbacks.push(callback);
        }
    }

    /**
     * Notify all callbacks about forced number changes
     */
    function notifyForcedNumberChanged(number, color) {
        onForcedNumberChangedCallbacks.forEach(callback => {
            try {
                callback(number, color);
            } catch (e) {
                console.error('Error in forced number change callback:', e);
            }
        });
    }

    /**
     * Notify all callbacks about mode changes
     */
    function notifyModeChanged(mode) {
        onModeChangedCallbacks.forEach(callback => {
            try {
                callback(mode);
            } catch (e) {
                console.error('Error in mode change callback:', e);
            }
        });
    }

    /**
     * Logging function with prefix
     */
    function log(message) {
        if (config.debug) {
            console.log(`[ForcedNumberSync] ${message}`);
        }
    }

    // Public API
    return {
        init,
        startSync,
        stopSync,
        onForcedNumberChanged,
        onModeChanged,
        checkNow: checkForForcedNumber
    };
})();

// Initialize the module when the document is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Small delay to ensure the game is fully initialized
    setTimeout(function() {
        ForcedNumberSync.init();
    }, 1000);
});