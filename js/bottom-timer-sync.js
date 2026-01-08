/**
 * Bottom Timer Synchronization
 *
 * This script specifically synchronizes the bottom "NEXT SPIN IN" timer with the Georgetown time.
 * It uses a direct approach to ensure the timer is always in sync with the Georgetown time display.
 */

(function() {
    // Configuration
    const config = {
        debug: true,
        updateInterval: 100, // Update every 100ms for smooth countdown
        bottomTimerId: 'countdown-timer', // ID of the bottom timer element
        georgetownTimeKey: 'georgetown_next_draw_time', // localStorage key for Georgetown time
        rouletteCountdownKey: 'roulette_countdown_end_time' // localStorage key for roulette countdown
    };

    // State
    let state = {
        bottomTimerElement: null,
        updateIntervalId: null,
        countdownEndTime: null,
        remainingSeconds: 0
    };

    // Logging function
    function log(message, data) {
        if (config.debug) {
            if (data !== undefined) {
                console.log(`[BottomTimerSync] ${message}`, data);
            } else {
                console.log(`[BottomTimerSync] ${message}`);
            }
        }
    }

    // Error logging function
    function error(message, err) {
        console.error(`[BottomTimerSync] ERROR: ${message}`, err);
    }

    /**
     * Initialize the bottom timer synchronization
     */
    function initialize() {
        log('Initializing Bottom Timer Synchronization');

        // Find the bottom timer element
        state.bottomTimerElement = document.getElementById(config.bottomTimerId);
        if (!state.bottomTimerElement) {
            error('Bottom timer element not found');
            return false;
        }

        log('Found bottom timer element', state.bottomTimerElement);

        // Add a class to indicate it's being synchronized
        state.bottomTimerElement.classList.add('synchronized');

        // Clear any existing content to prevent overlapping timers
        state.bottomTimerElement.textContent = '';

        // Load initial state from localStorage
        loadStateFromLocalStorage();

        // Start the update interval
        startUpdateInterval();

        // Listen for localStorage changes
        window.addEventListener('storage', handleStorageChange);

        // If GeorgetownTimeSync is available, register for updates
        if (window.GeorgetownTimeSync) {
            log('GeorgetownTimeSync detected, registering for updates');

            // Get initial time from Georgetown
            const secondsUntilNextDraw = window.GeorgetownTimeSync.getSecondsUntilNextDraw();
            if (secondsUntilNextDraw !== null) {
                updateTimerState(secondsUntilNextDraw);
            }

            // Register for time updates
            window.GeorgetownTimeSync.onTimeUpdate((georgetownTime, secondsRemaining) => {
                if (secondsRemaining !== null) {
                    updateTimerState(secondsRemaining);
                }
            });
        }

        return true;
    }

    /**
     * Load state from localStorage
     */
    function loadStateFromLocalStorage() {
        try {
            // Try to get the Georgetown time first
            let endTime = localStorage.getItem(config.georgetownTimeKey);

            // If not available, try the roulette countdown time
            if (!endTime) {
                endTime = localStorage.getItem(config.rouletteCountdownKey);
            }

            if (endTime) {
                const parsedEndTime = parseInt(endTime);
                const now = Date.now();

                // Only use the end time if it's in the future
                if (parsedEndTime > now) {
                    state.countdownEndTime = parsedEndTime;
                    state.remainingSeconds = Math.max(0, Math.floor((parsedEndTime - now) / 1000));

                    log('Loaded valid end time from localStorage', {
                        endTime: new Date(parsedEndTime),
                        remainingSeconds: state.remainingSeconds
                    });

                    // Update the timer display immediately
                    updateTimerDisplay();
                } else {
                    log('Stored end time is in the past, will not use it', new Date(parsedEndTime));
                }
            }
        } catch (err) {
            error('Failed to load state from localStorage', err);
        }
    }

    /**
     * Start the update interval
     */
    function startUpdateInterval() {
        if (state.updateIntervalId) {
            clearInterval(state.updateIntervalId);
        }

        state.updateIntervalId = setInterval(updateTimer, config.updateInterval);
        log(`Update interval started (every ${config.updateInterval}ms)`);
    }

    /**
     * Update the timer based on the current time
     */
    function updateTimer() {
        if (state.countdownEndTime) {
            const now = Date.now();
            state.remainingSeconds = Math.max(0, Math.floor((state.countdownEndTime - now) / 1000));
            updateTimerDisplay();
        }
    }

    /**
     * Update the timer state with new seconds remaining
     */
    function updateTimerState(secondsRemaining) {
        state.remainingSeconds = secondsRemaining;
        state.countdownEndTime = Date.now() + (secondsRemaining * 1000);

        log('Updated timer state', {
            secondsRemaining,
            endTime: new Date(state.countdownEndTime)
        });

        updateTimerDisplay();
    }

    /**
     * Update the timer display
     */
    function updateTimerDisplay() {
        if (state.bottomTimerElement) {
            const minutes = Math.floor(state.remainingSeconds / 60);
            const seconds = state.remainingSeconds % 60;
            const formattedTime = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

            // Only update if the display has changed
            if (state.bottomTimerElement.textContent !== formattedTime) {
                // Clear any existing content first to prevent overlapping
                state.bottomTimerElement.innerHTML = '';

                // Set the new content
                state.bottomTimerElement.textContent = formattedTime;

                // Make sure the synchronized class is applied
                if (!state.bottomTimerElement.classList.contains('synchronized')) {
                    state.bottomTimerElement.classList.add('synchronized');
                }

                log('Updated timer display', formattedTime);
            }
        }
    }

    /**
     * Handle localStorage changes
     */
    function handleStorageChange(event) {
        if (event.key === config.georgetownTimeKey || event.key === config.rouletteCountdownKey) {
            log('Detected localStorage change', {
                key: event.key,
                oldValue: event.oldValue,
                newValue: event.newValue
            });

            if (event.newValue) {
                const parsedEndTime = parseInt(event.newValue);
                const now = Date.now();

                // Only use the end time if it's in the future
                if (parsedEndTime > now) {
                    state.countdownEndTime = parsedEndTime;
                    state.remainingSeconds = Math.max(0, Math.floor((parsedEndTime - now) / 1000));

                    log('Updated timer state from localStorage change', {
                        endTime: new Date(parsedEndTime),
                        remainingSeconds: state.remainingSeconds
                    });

                    updateTimerDisplay();
                }
            }
        }
    }

    // Function to clean up any other timer implementations
    function cleanupOtherTimers() {
        // Clear any global intervals that might be updating the timer
        if (window.countdownInterval) {
            clearInterval(window.countdownInterval);
            window.countdownInterval = null;
            log('Cleared global countdown interval');
        }

        // Override global timer functions
        if (window.startCountdown) {
            window.startCountdown = function() {
                log('Blocked attempt to start global countdown');
                return false;
            };
        }

        if (window.updateCountdownDisplay) {
            window.updateCountdownDisplay = function() {
                log('Blocked attempt to update global countdown display');
                return false;
            };
        }

        // Clean up the timer element
        const bottomTimer = document.getElementById(config.bottomTimerId);
        if (bottomTimer) {
            // Remove any classes that might interfere
            const classesToRemove = ['timer-warning', 'timer-expired', 'timer-reset'];
            classesToRemove.forEach(className => {
                if (bottomTimer.classList.contains(className)) {
                    bottomTimer.classList.remove(className);
                    log(`Removed ${className} class from timer element`);
                }
            });

            // Ensure it has the synchronized class
            if (!bottomTimer.classList.contains('synchronized')) {
                bottomTimer.classList.add('synchronized');
                log('Added synchronized class to timer element');
            }

            // Clear any inline styles
            if (bottomTimer.hasAttribute('style')) {
                bottomTimer.removeAttribute('style');
                log('Removed inline styles from timer element');
            }

            // Force a refresh of the timer display
            updateTimerDisplay();
        }
    }

    // Initialize when the document is ready
    document.addEventListener('DOMContentLoaded', function() {
        // Clean up any other timer implementations first
        cleanupOtherTimers();

        // Initialize with a delay to ensure all other scripts have loaded
        setTimeout(function() {
            log('Initializing with delay to ensure no other timers are running');
            initialize();

            // Clean up again after initialization
            cleanupOtherTimers();

            // Force a refresh of the timer display after a short delay
            setTimeout(function() {
                const bottomTimer = document.getElementById(config.bottomTimerId);
                if (bottomTimer) {
                    // Clear any existing content to prevent overlapping timers
                    bottomTimer.innerHTML = '';

                    // Make sure the synchronized class is applied
                    bottomTimer.classList.add('synchronized');

                    // Force an update
                    updateTimerDisplay();

                    log('Forced refresh of timer display');
                }
            }, 200);
        }, 1000);

        // Run cleanup again after a longer delay to catch any late initializations
        setTimeout(cleanupOtherTimers, 2000);
        setTimeout(cleanupOtherTimers, 5000);
    });
})();
