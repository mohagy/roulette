/**
 * Synchronized Timer
 * 
 * This module ensures that all timers on the page are synchronized with the Georgetown time.
 * It specifically focuses on synchronizing the bottom "NEXT SPIN IN" timer with the
 * Georgetown time display in the top right corner.
 */

const SynchronizedTimer = (function() {
    // Configuration
    const config = {
        debug: true,
        updateInterval: 100, // Update timer display every 100ms for smooth countdown
        syncInterval: 1000,  // Sync with Georgetown time every 1 second
        keys: {
            // Shared keys for localStorage
            countdownEndTime: 'georgetown_next_draw_time', // Use the same key as Georgetown time
            lastSyncTime: 'synchronized_timer_last_sync_time'
        }
    };

    // State
    let state = {
        initialized: false,
        countdownEndTime: null,
        remainingSeconds: 0,
        updateIntervalId: null,
        syncIntervalId: null,
        timerElements: []
    };

    // Logging function
    function log(message, data) {
        if (config.debug) {
            if (data !== undefined) {
                console.log(`[SynchronizedTimer] ${message}`, data);
            } else {
                console.log(`[SynchronizedTimer] ${message}`);
            }
        }
    }

    // Error logging function
    function error(message, err) {
        console.error(`[SynchronizedTimer] ERROR: ${message}`, err);
    }

    /**
     * Initialize the synchronized timer
     * @param {Object} options - Configuration options
     */
    function initialize(options = {}) {
        // Merge options with default config
        Object.assign(config, options);

        log('Initializing SynchronizedTimer');

        // Find all timer elements that should be synchronized with Georgetown time
        state.timerElements = document.querySelectorAll('[data-sync-with="georgetown"]');
        log(`Found ${state.timerElements.length} timer elements to synchronize`);

        // Load initial state from Georgetown time
        if (window.GeorgetownTimeSync) {
            log('GeorgetownTimeSync detected, using it for time synchronization');
            
            // Get the seconds until next draw from Georgetown time
            const secondsUntilNextDraw = window.GeorgetownTimeSync.getSecondsUntilNextDraw();
            if (secondsUntilNextDraw !== null) {
                // Calculate the end time based on the current time plus remaining seconds
                state.countdownEndTime = Date.now() + (secondsUntilNextDraw * 1000);
                state.remainingSeconds = secondsUntilNextDraw;
                
                log('Initialized countdown from Georgetown time', {
                    secondsUntilNextDraw,
                    endTime: new Date(state.countdownEndTime)
                });
                
                // Update all timer elements immediately
                updateTimerDisplays();
            }
            
            // Register for Georgetown time updates
            window.GeorgetownTimeSync.onTimeUpdate((georgetownTime, secondsRemaining) => {
                log('Received time update from GeorgetownTimeSync', { 
                    georgetownTime: georgetownTime.toISOString(), 
                    secondsRemaining 
                });
                
                if (secondsRemaining !== null) {
                    // Update our countdown based on Georgetown time
                    state.remainingSeconds = secondsRemaining;
                    state.countdownEndTime = Date.now() + (secondsRemaining * 1000);
                    
                    // Update all timer elements
                    updateTimerDisplays();
                }
            });
            
            // Register for draw completion events
            window.GeorgetownTimeSync.onDrawComplete((drawNumber, winningNumber, transactionId) => {
                log('Received draw complete from GeorgetownTimeSync', { 
                    drawNumber, 
                    winningNumber,
                    transactionId
                });
                
                // Get the seconds until next draw from Georgetown time
                const secondsUntilNextDraw = window.GeorgetownTimeSync.getSecondsUntilNextDraw();
                if (secondsUntilNextDraw !== null) {
                    // Calculate the end time based on the current time plus remaining seconds
                    state.countdownEndTime = Date.now() + (secondsUntilNextDraw * 1000);
                    state.remainingSeconds = secondsUntilNextDraw;
                    
                    log('Updated countdown after draw completion', {
                        secondsUntilNextDraw,
                        endTime: new Date(state.countdownEndTime)
                    });
                    
                    // Update all timer elements
                    updateTimerDisplays();
                }
            });
        } else {
            error('GeorgetownTimeSync not detected, synchronized timer will not work properly');
        }

        // Start the update interval for smooth countdown
        startUpdateInterval();

        state.initialized = true;
        return true;
    }

    /**
     * Start the update interval for smooth countdown
     */
    function startUpdateInterval() {
        if (state.updateIntervalId) {
            clearInterval(state.updateIntervalId);
        }

        state.updateIntervalId = setInterval(updateCountdown, config.updateInterval);
        log(`Update interval started (every ${config.updateInterval}ms)`);
    }

    /**
     * Update the countdown based on the current time
     */
    function updateCountdown() {
        if (state.countdownEndTime) {
            const now = Date.now();
            state.remainingSeconds = Math.max(0, Math.floor((state.countdownEndTime - now) / 1000));
            
            // Update all timer elements
            updateTimerDisplays();
        }
    }

    /**
     * Update all timer displays with the current remaining time
     */
    function updateTimerDisplays() {
        if (state.timerElements.length > 0) {
            const minutes = Math.floor(state.remainingSeconds / 60);
            const seconds = state.remainingSeconds % 60;
            const formattedTime = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            state.timerElements.forEach(element => {
                element.textContent = formattedTime;
            });
        }
    }

    // Return public API
    return {
        initialize,
        getRemainingSeconds: () => state.remainingSeconds
    };
})();

// Initialize when the document is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the Synchronized Timer
    SynchronizedTimer.initialize({
        debug: true
    });
    
    console.log('[SynchronizedTimer] Synchronized Timer initialized');
});
