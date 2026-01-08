/**
 * Shared Draw Timer
 *
 * This module provides a synchronized countdown timer for the roulette system
 * based on Georgetown, Guyana time (UTC-04:00). It ensures that both the betting
 * interface and TV display show the same countdown and draw numbers.
 */

const SharedDrawTimer = (function() {
    // Configuration
    const config = {
        debug: true,
        updateInterval: 100, // Update timer display every 100ms for smooth countdown
        syncInterval: 1000,  // Sync with other tabs every 1 second
        keys: {
            // Shared keys for localStorage
            countdownEndTime: 'roulette_countdown_end_time',
            drawInterval: 'roulette_draw_interval',
            currentDrawNumber: 'roulette_current_draw_number',
            nextDrawNumber: 'roulette_next_draw_number',
            lastSyncTime: 'roulette_timer_last_sync_time',
            upcomingDrawTimes: 'roulette_upcoming_draw_times'
        },
        defaultDrawInterval: 180, // 3 minutes in seconds
        channelName: 'roulette_timer_channel',
        minDrawNumber: 64 // Minimum draw number to ensure we're synchronized with TV display
    };

    // State
    let state = {
        initialized: false,
        countdownEndTime: null,
        remainingSeconds: 0,
        drawInterval: config.defaultDrawInterval,
        currentDrawNumber: null,
        nextDrawNumber: null,
        upcomingDrawTimes: [],
        updateIntervalId: null,
        syncIntervalId: null,
        broadcastChannel: null,
        timerUpdateCallbacks: [],
        drawCompleteCallbacks: []
    };

    // Logging function
    function log(message, data) {
        if (config.debug) {
            if (data !== undefined) {
                console.log(`[SharedDrawTimer] ${message}`, data);
            } else {
                console.log(`[SharedDrawTimer] ${message}`);
            }
        }
    }

    // Error logging function
    function error(message, err) {
        console.error(`[SharedDrawTimer] ERROR: ${message}`, err);
    }

    /**
     * Initialize the shared timer
     * @param {Object} options - Configuration options
     */
    function initialize(options = {}) {
        // Merge options with default config
        Object.assign(config, options);

        log('Initializing SharedDrawTimer');

        // Load state from localStorage
        loadStateFromLocalStorage();

        // Set up BroadcastChannel for real-time communication between tabs
        try {
            state.broadcastChannel = new BroadcastChannel(config.channelName);

            // Listen for messages from other tabs
            state.broadcastChannel.onmessage = function(event) {
                log('Received message from BroadcastChannel', event.data);

                if (event.data.type === 'timer_update') {
                    handleTimerUpdate(event.data);
                } else if (event.data.type === 'draw_complete') {
                    handleDrawComplete(event.data);
                }
            };

            log('BroadcastChannel initialized');
        } catch (err) {
            error('BroadcastChannel not supported, falling back to localStorage polling', err);
        }

        // Register for Georgetown time updates if available
        if (window.GeorgetownTimeSync) {
            log('GeorgetownTimeSync detected, registering for time updates');

            window.GeorgetownTimeSync.onTimeUpdate((georgetownTime, secondsRemaining) => {
                log('Received time update from GeorgetownTimeSync', {
                    georgetownTime,
                    secondsRemaining
                });

                if (secondsRemaining !== null) {
                    // Update our countdown based on Georgetown time
                    updateCountdownFromGeorgetownTime(secondsRemaining);
                }
            });

            window.GeorgetownTimeSync.onDrawComplete((drawNumber, winningNumber, transactionId) => {
                log('Received draw complete from GeorgetownTimeSync', {
                    drawNumber,
                    winningNumber,
                    transactionId
                });

                // Update our draw numbers based on Georgetown time
                updateDrawNumbersFromGeorgetownTime(drawNumber);

                // Get upcoming draw times from Georgetown time
                const upcomingDrawTimes = window.GeorgetownTimeSync.getUpcomingDrawTimes();
                if (upcomingDrawTimes) {
                    state.upcomingDrawTimes = upcomingDrawTimes;
                    log('Updated upcoming draw times from GeorgetownTimeSync', upcomingDrawTimes);
                }

                // Notify our callbacks
                notifyDrawComplete(drawNumber, winningNumber, transactionId);
            });

            // Get initial draw numbers from Georgetown time
            const currentDrawNumber = window.GeorgetownTimeSync.getCurrentDrawNumber();
            const nextDrawNumber = window.GeorgetownTimeSync.getNextDrawNumber();

            if (currentDrawNumber !== null && nextDrawNumber !== null) {
                state.currentDrawNumber = currentDrawNumber;
                state.nextDrawNumber = nextDrawNumber;
                log('Initialized draw numbers from GeorgetownTimeSync', {
                    currentDrawNumber,
                    nextDrawNumber
                });
            }

            // Get initial upcoming draw times from Georgetown time
            const upcomingDrawTimes = window.GeorgetownTimeSync.getUpcomingDrawTimes();
            if (upcomingDrawTimes) {
                state.upcomingDrawTimes = upcomingDrawTimes;
                log('Initialized upcoming draw times from GeorgetownTimeSync', upcomingDrawTimes);
            }

            // If we have a valid countdown end time from localStorage, use it
            // Otherwise, get the seconds until next draw from Georgetown time
            if (!state.countdownEndTime) {
                const secondsUntilNextDraw = window.GeorgetownTimeSync.getSecondsUntilNextDraw();
                if (secondsUntilNextDraw !== null) {
                    updateCountdownFromGeorgetownTime(secondsUntilNextDraw);
                    log('Initialized countdown from Georgetown time', {
                        secondsUntilNextDraw,
                        endTime: new Date(state.countdownEndTime)
                    });
                }
            }
        } else {
            log('GeorgetownTimeSync not detected, using local time');

            // If we don't have a valid countdown end time from localStorage, calculate it
            if (!state.countdownEndTime) {
                // Calculate the next draw time based on 3-minute intervals
                const now = new Date();
                const currentMinutes = now.getMinutes();
                const currentSeconds = now.getSeconds();
                const minutesUntilNextDraw = 3 - (currentMinutes % 3);
                let secondsUntilNextDraw = (minutesUntilNextDraw * 60) - currentSeconds;

                // If we're exactly at a 3-minute mark, set for the next one
                if (secondsUntilNextDraw === 0 || secondsUntilNextDraw === state.drawInterval) {
                    secondsUntilNextDraw = state.drawInterval;
                }

                // Set the countdown end time
                state.countdownEndTime = Date.now() + (secondsUntilNextDraw * 1000);
                state.remainingSeconds = secondsUntilNextDraw;

                // Store in localStorage for persistence
                localStorage.setItem(config.keys.countdownEndTime, state.countdownEndTime.toString());

                log('Calculated new countdown end time', {
                    secondsUntilNextDraw,
                    endTime: new Date(state.countdownEndTime)
                });
            }

            // Start update interval for local countdown
            startUpdateInterval();
        }

        // Start sync interval
        startSyncInterval();

        state.initialized = true;
        return true;
    }

    /**
     * Load state from localStorage
     */
    function loadStateFromLocalStorage() {
        try {
            // Load countdown end time
            const countdownEndTime = localStorage.getItem(config.keys.countdownEndTime);
            if (countdownEndTime) {
                state.countdownEndTime = parseInt(countdownEndTime);

                // Validate the loaded end time to ensure it's in the future
                const now = Date.now();
                if (state.countdownEndTime > now) {
                    // Calculate remaining seconds based on the stored end time
                    state.remainingSeconds = Math.max(0, Math.floor((state.countdownEndTime - now) / 1000));
                    log('Loaded valid countdown end time from localStorage', {
                        endTime: new Date(state.countdownEndTime),
                        remainingSeconds: state.remainingSeconds
                    });
                } else {
                    // If the end time is in the past, we'll recalculate it
                    log('Stored countdown end time is in the past, will recalculate', new Date(state.countdownEndTime));
                    state.countdownEndTime = null;
                }
            }

            // Load draw interval
            const drawInterval = localStorage.getItem(config.keys.drawInterval);
            if (drawInterval) {
                state.drawInterval = parseInt(drawInterval);
                log('Loaded draw interval from localStorage', state.drawInterval);
            }

            // Load current draw number
            const currentDrawNumber = localStorage.getItem(config.keys.currentDrawNumber);
            if (currentDrawNumber) {
                state.currentDrawNumber = parseInt(currentDrawNumber);
                log('Loaded current draw number from localStorage', state.currentDrawNumber);
            }

            // Load next draw number
            const nextDrawNumber = localStorage.getItem(config.keys.nextDrawNumber);
            if (nextDrawNumber) {
                state.nextDrawNumber = parseInt(nextDrawNumber);
                log('Loaded next draw number from localStorage', state.nextDrawNumber);
            }

            // Load upcoming draw times
            const upcomingDrawTimes = localStorage.getItem(config.keys.upcomingDrawTimes);
            if (upcomingDrawTimes) {
                try {
                    state.upcomingDrawTimes = JSON.parse(upcomingDrawTimes);
                    log('Loaded upcoming draw times from localStorage', state.upcomingDrawTimes);
                } catch (e) {
                    error('Failed to parse upcoming draw times from localStorage', e);
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

        state.updateIntervalId = setInterval(updateCountdown, config.updateInterval);
        log(`Update interval started (every ${config.updateInterval}ms)`);
    }

    /**
     * Start the sync interval
     */
    function startSyncInterval() {
        if (state.syncIntervalId) {
            clearInterval(state.syncIntervalId);
        }

        state.syncIntervalId = setInterval(syncWithOtherTabs, config.syncInterval);
        log(`Sync interval started (every ${config.syncInterval}ms)`);
    }

    /**
     * Update the countdown timer
     */
    function updateCountdown() {
        // Skip if we're using GeorgetownTimeSync
        if (window.GeorgetownTimeSync) {
            return;
        }

        // Calculate remaining seconds
        if (state.countdownEndTime) {
            const now = Date.now();
            state.remainingSeconds = Math.max(0, Math.floor((state.countdownEndTime - now) / 1000));

            // If the timer has reached zero, calculate the next draw time
            if (state.remainingSeconds === 0) {
                log('Countdown reached zero, calculating next draw time');

                // Calculate the next draw time based on 3-minute intervals
                const currentTime = new Date();
                const currentMinutes = currentTime.getMinutes();
                const currentSeconds = currentTime.getSeconds();
                const minutesUntilNextDraw = 3 - (currentMinutes % 3);
                let secondsUntilNextDraw = (minutesUntilNextDraw * 60) - currentSeconds;

                // If we're exactly at a 3-minute mark, set for the next one
                if (secondsUntilNextDraw === 0 || secondsUntilNextDraw === state.drawInterval) {
                    secondsUntilNextDraw = state.drawInterval;
                }

                // Set the new countdown end time
                state.countdownEndTime = Date.now() + (secondsUntilNextDraw * 1000);
                state.remainingSeconds = secondsUntilNextDraw;

                // Store in localStorage for persistence
                localStorage.setItem(config.keys.countdownEndTime, state.countdownEndTime.toString());

                log('Set new countdown end time', {
                    secondsUntilNextDraw,
                    endTime: new Date(state.countdownEndTime)
                });
            }

            // Notify all registered callbacks
            notifyTimerUpdate();
        }
    }

    /**
     * Update countdown based on Georgetown time
     */
    function updateCountdownFromGeorgetownTime(secondsRemaining) {
        // Update our state
        state.remainingSeconds = secondsRemaining;

        // Calculate the end time based on the current time plus remaining seconds
        state.countdownEndTime = Date.now() + (secondsRemaining * 1000);

        // Update localStorage with the precise end time for persistence across page refreshes
        localStorage.setItem(config.keys.countdownEndTime, state.countdownEndTime.toString());

        log('Updated countdown from Georgetown time', {
            secondsRemaining,
            endTime: new Date(state.countdownEndTime)
        });

        // Notify all registered callbacks
        notifyTimerUpdate();
    }

    /**
     * Update draw numbers based on Georgetown time
     */
    function updateDrawNumbersFromGeorgetownTime(drawNumber) {
        // Update our state
        state.currentDrawNumber = drawNumber;
        state.nextDrawNumber = drawNumber + 1;

        // Update localStorage
        localStorage.setItem(config.keys.currentDrawNumber, state.currentDrawNumber.toString());
        localStorage.setItem(config.keys.nextDrawNumber, state.nextDrawNumber.toString());

        // Broadcast to other tabs
        broadcastTimerUpdate();
    }

    /**
     * Sync with other tabs
     */
    function syncWithOtherTabs() {
        // Broadcast current state to other tabs
        broadcastTimerUpdate();
    }

    /**
     * Broadcast timer update to other tabs
     */
    function broadcastTimerUpdate() {
        if (state.broadcastChannel) {
            state.broadcastChannel.postMessage({
                type: 'timer_update',
                countdownEndTime: state.countdownEndTime,
                remainingSeconds: state.remainingSeconds,
                currentDrawNumber: state.currentDrawNumber,
                nextDrawNumber: state.nextDrawNumber,
                upcomingDrawTimes: state.upcomingDrawTimes,
                timestamp: Date.now()
            });
        }
    }

    /**
     * Handle timer update from other tabs
     */
    function handleTimerUpdate(data) {
        // Skip if we're using GeorgetownTimeSync
        if (window.GeorgetownTimeSync) {
            return;
        }

        // Update our state
        state.countdownEndTime = data.countdownEndTime;
        state.remainingSeconds = data.remainingSeconds;
        state.currentDrawNumber = data.currentDrawNumber;
        state.nextDrawNumber = data.nextDrawNumber;
        state.upcomingDrawTimes = data.upcomingDrawTimes;

        // Notify all registered callbacks
        notifyTimerUpdate();
    }

    /**
     * Handle draw complete from other tabs
     */
    function handleDrawComplete(data) {
        // Skip if we're using GeorgetownTimeSync
        if (window.GeorgetownTimeSync) {
            return;
        }

        // Update our state
        state.currentDrawNumber = data.drawNumber;
        state.nextDrawNumber = data.drawNumber + 1;
        state.countdownEndTime = Date.now() + (state.drawInterval * 1000);
        state.remainingSeconds = state.drawInterval;

        // Update localStorage
        localStorage.setItem(config.keys.countdownEndTime, state.countdownEndTime.toString());
        localStorage.setItem(config.keys.currentDrawNumber, state.currentDrawNumber.toString());
        localStorage.setItem(config.keys.nextDrawNumber, state.nextDrawNumber.toString());

        // Notify all registered callbacks
        notifyTimerUpdate();
        notifyDrawComplete(data.drawNumber, data.winningNumber, data.transactionId);
    }

    /**
     * Notify all registered timer update callbacks
     */
    function notifyTimerUpdate() {
        state.timerUpdateCallbacks.forEach(callback => {
            try {
                callback(state.remainingSeconds);
            } catch (err) {
                error('Error in timer update callback', err);
            }
        });
    }

    /**
     * Notify all registered draw complete callbacks
     */
    function notifyDrawComplete(drawNumber, winningNumber, transactionId) {
        state.drawCompleteCallbacks.forEach(callback => {
            try {
                callback(drawNumber, winningNumber, transactionId);
            } catch (err) {
                error('Error in draw complete callback', err);
            }
        });
    }

    // Return public API
    return {
        initialize,
        getCurrentDrawNumber: () => state.currentDrawNumber,
        getNextDrawNumber: () => state.nextDrawNumber,
        getUpcomingDrawTimes: () => state.upcomingDrawTimes,
        getRemainingSeconds: () => state.remainingSeconds,
        onTimerUpdate: (callback) => {
            if (typeof callback === 'function') {
                state.timerUpdateCallbacks.push(callback);

                // Immediately call with current value
                if (state.initialized) {
                    callback(state.remainingSeconds);
                }
            }
        },
        onDrawComplete: (callback) => {
            if (typeof callback === 'function') {
                state.drawCompleteCallbacks.push(callback);
            }
        }
    };
})();

// Initialize when the document is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the Shared Draw Timer
    SharedDrawTimer.initialize({
        debug: true
    });

    console.log('[SharedDrawTimer] Shared Draw Timer initialized');

    // Log the current timer state for debugging
    const countdownEndTime = localStorage.getItem('roulette_countdown_end_time');
    if (countdownEndTime) {
        const now = Date.now();
        const endTime = parseInt(countdownEndTime);
        const remainingMs = Math.max(0, endTime - now);
        const remainingSeconds = Math.floor(remainingMs / 1000);

        console.log('[SharedDrawTimer] Current timer state:', {
            endTime: new Date(endTime),
            remainingSeconds: remainingSeconds,
            formattedTime: `${Math.floor(remainingSeconds / 60).toString().padStart(2, '0')}:${(remainingSeconds % 60).toString().padStart(2, '0')}`
        });
    } else {
        console.log('[SharedDrawTimer] No stored countdown end time found');
    }
});
