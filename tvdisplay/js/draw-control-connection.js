/**
 * Draw Control Connection
 *
 * This module connects the TV Display with the Draw Control management interface.
 * It checks for manually set winning numbers and enforces them during wheel spins.
 */

const DrawControlConnection = (function() {
    // Configuration
    const config = {
        checkInterval: 2000, // How often to check for manual winning numbers (ms)
        apiEndpoint: '../php/auto_winning_number.php', // Endpoint to check for winning numbers
        fallbackEndpoint: '/slipp/api/tv_sync.php', // Fallback endpoint if primary fails
        debug: true  // Enable debug logging
    };

    // State variables
    let manualWinningNumber = null;
    let winningColor = null;
    let checkIntervalId = null;
    let isInitialized = false;
    let lastDrawNumber = null;
    let isManualModeActive = false;

    // Event callbacks
    const callbacks = {
        onManualNumberDetected: null,
        onRevertToAutomatic: null,
        onError: null
    };

    /**
     * Initialize the connection
     * @param {Object} options - Configuration options
     */
    function initialize(options = {}) {
        if (isInitialized) {
            console.warn('Draw Control Connection already initialized');
            return;
        }

        // Merge options with defaults
        Object.assign(config, options);

        // Start the check interval
        startChecking();

        isInitialized = true;
        logDebug('Draw Control Connection initialized');

        // Expose methods and state to window for debugging and direct access
        window.drawControlConnection = {
            getManualWinningNumber,
            hasManualWinningNumber,
            isManualMode: () => isManualModeActive,
            state: {
                get manualWinningNumber() { return manualWinningNumber; },
                get isManualModeActive() { return isManualModeActive; },
                get winningColor() { return winningColor; }
            },
            // Allow forcing a check with option to save automatic selections
            forceCheck: (saveAutomatic = false) => checkForManualWinningNumber(saveAutomatic),
            // Allow saving an automatic selection when needed
            saveAutomaticSelection: () => checkForManualWinningNumber(true)
        };
    }

    /**
     * Start periodic checking for manual winning numbers
     * @param {boolean} saveAutomatic - Whether to save automatically selected numbers to the database
     */
    function startChecking(saveAutomatic = false) {
        if (checkIntervalId) {
            clearInterval(checkIntervalId);
        }

        // Check immediately - don't save automatic selections during regular checks
        checkForManualWinningNumber(false);

        // Set up interval for subsequent checks - never save automatic selections during interval checks
        checkIntervalId = setInterval(() => checkForManualWinningNumber(false), config.checkInterval);
        logDebug(`Started checking for manual winning numbers every ${config.checkInterval}ms (saveAutomatic=${saveAutomatic})`);
    }

    /**
     * Stop checking for manual winning numbers
     */
    function stopChecking() {
        if (checkIntervalId) {
            clearInterval(checkIntervalId);
            checkIntervalId = null;
            logDebug('Stopped checking for manual winning numbers');
        }
    }

    /**
     * Check for manually set winning numbers
     * @param {boolean} saveAutomatic - Whether to save automatically selected numbers to the database
     */
    function checkForManualWinningNumber(saveAutomatic = false) {
        // Only save automatic selections when explicitly requested
        const saveParam = saveAutomatic ? '&save=true' : '';
        fetch(config.apiEndpoint + '?t=' + new Date().getTime() + saveParam)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                logDebug(`API response received: manual=${data.is_manual}, num=${data.selected_number}, draw=${data.draw_number}`);

                if (data.status === 'success') {
                    // If this is a new draw and we're in manual mode, we might need to reset
                    if (lastDrawNumber !== null && lastDrawNumber !== data.draw_number && isManualModeActive) {
                        isManualModeActive = false;
                        manualWinningNumber = null;
                        winningColor = null;

                        if (callbacks.onRevertToAutomatic) {
                            callbacks.onRevertToAutomatic();
                        }

                        logDebug(`New draw detected (${data.draw_number}), reverted to automatic mode`);
                    }

                    lastDrawNumber = data.draw_number;

                    // Check if we have a manually set winning number
                    if (data.is_manual) {
                        // Only trigger event if the number has changed or we've switched to manual mode
                        const isNewNumber = manualWinningNumber !== data.selected_number;
                        const isActivationChange = !isManualModeActive;

                        if (isNewNumber || isActivationChange) {
                            manualWinningNumber = data.selected_number;
                            winningColor = data.winning_color;
                            isManualModeActive = true;

                            logDebug(`Manual winning number ${isNewNumber ? 'changed to' : 'set as'}: ${manualWinningNumber} (${winningColor})`);

                            // Call the callback if defined
                            if (callbacks.onManualNumberDetected) {
                                callbacks.onManualNumberDetected({
                                    number: manualWinningNumber,
                                    color: winningColor,
                                    drawNumber: data.draw_number
                                });
                            }
                        } else {
                            // Number is the same, just log
                            logDebug(`Manual mode still active: ${manualWinningNumber} (${winningColor})`);
                        }
                    } else if (isManualModeActive) {
                        // If we were in manual mode but the backend switched to automatic,
                        // revert to automatic mode locally as well
                        isManualModeActive = false;
                        manualWinningNumber = null;
                        winningColor = null;

                        if (callbacks.onRevertToAutomatic) {
                            callbacks.onRevertToAutomatic();
                        }

                        logDebug('Reverted to automatic mode');
                    } else {
                        // Still in automatic mode
                        logDebug('Automatic mode - system will select winning number');
                    }
                } else {
                    logError(`API error: ${data.message}`);
                    if (callbacks.onError) {
                        callbacks.onError(`API error: ${data.message}`);
                    }
                }
            })
            .catch(error => {
                logError(`Failed to check primary endpoint: ${error.message}. Trying fallback...`);

                // Try the fallback endpoint - pass the save parameter if it was set for the primary endpoint
                const saveParam = saveAutomatic ? '&save=true' : '';
                fetch(config.fallbackEndpoint + '?t=' + new Date().getTime() + saveParam)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.status === 'success') {
                            logDebug('Using fallback endpoint data');

                            // Process the tv_sync.php response format
                            const syncData = data.data;

                            // Update draw number
                            if (lastDrawNumber !== syncData.current_draw) {
                                lastDrawNumber = syncData.current_draw;
                                logDebug(`Draw number updated to ${syncData.current_draw} from fallback`);
                            }

                            // Check if we're in manual mode with a forced number
                            if (!syncData.is_automatic && syncData.has_forced_number) {
                                const isNewNumber = manualWinningNumber !== syncData.forced_number;
                                const isActivationChange = !isManualModeActive;

                                if (isNewNumber || isActivationChange) {
                                    manualWinningNumber = syncData.forced_number;
                                    winningColor = syncData.forced_color;
                                    isManualModeActive = true;

                                    logDebug(`Manual winning number from fallback: ${manualWinningNumber} (${winningColor})`);

                                    // Call the callback if defined
                                    if (callbacks.onManualNumberDetected) {
                                        callbacks.onManualNumberDetected({
                                            number: manualWinningNumber,
                                            color: winningColor,
                                            drawNumber: syncData.current_draw
                                        });
                                    }
                                }
                            } else if (isManualModeActive) {
                                // If we were in manual mode but now we're not
                                isManualModeActive = false;
                                manualWinningNumber = null;
                                winningColor = null;

                                if (callbacks.onRevertToAutomatic) {
                                    callbacks.onRevertToAutomatic();
                                }

                                logDebug('Reverted to automatic mode from fallback');
                            }
                        } else {
                            logError(`Fallback API error: ${data.message}`);
                            if (callbacks.onError) {
                                callbacks.onError(`Fallback API error: ${data.message}`);
                            }
                        }
                    })
                    .catch(fallbackError => {
                        logError(`Failed to check fallback endpoint: ${fallbackError.message}`);
                        if (callbacks.onError) {
                            callbacks.onError(`Connection error: ${error.message}. Fallback also failed: ${fallbackError.message}`);
                        }
                    });
            });
    }

    /**
     * Get the current manual winning number
     * @returns {number|null} The manual winning number, or null if not set
     */
    function getManualWinningNumber() {
        return manualWinningNumber;
    }

    /**
     * Check if a manual winning number is set
     * @returns {boolean} True if a manual winning number is set
     */
    function hasManualWinningNumber() {
        return isManualModeActive && manualWinningNumber !== null;
    }

    /**
     * Set callback for when a manual number is detected
     * @param {Function} callback - Function to call when a manual number is detected
     */
    function onManualNumberDetected(callback) {
        callbacks.onManualNumberDetected = callback;
    }

    /**
     * Set callback for when the system reverts to automatic mode
     * @param {Function} callback - Function to call when system reverts to automatic
     */
    function onRevertToAutomatic(callback) {
        callbacks.onRevertToAutomatic = callback;
    }

    /**
     * Set callback for when an error occurs
     * @param {Function} callback - Function to call when an error occurs
     */
    function onError(callback) {
        callbacks.onError = callback;
    }

    /**
     * Log debug messages
     * @param {string} message - Debug message
     */
    function logDebug(message) {
        if (config.debug) {
            console.log(`[DrawControl] ${message}`);
        }
    }

    /**
     * Log error messages
     * @param {string} message - Error message
     */
    function logError(message) {
        console.error(`[DrawControl] ${message}`);
    }

    // Public API
    return {
        initialize,
        startChecking,
        stopChecking,
        getManualWinningNumber,
        hasManualWinningNumber,
        onManualNumberDetected,
        onRevertToAutomatic,
        onError
    };
})();

// Export for ES modules
if (typeof module !== 'undefined' && typeof module.exports !== 'undefined') {
    module.exports = DrawControlConnection;
}