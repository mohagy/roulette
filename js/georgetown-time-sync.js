/**
 * Georgetown Time Synchronization Module
 *
 * This module provides time synchronization based on Georgetown, Guyana time (UTC-04:00)
 * for the roulette system. It ensures all time-based operations are synchronized
 * across interfaces.
 */

const GeorgetownTimeSync = (function() {
    // Configuration
    const config = {
        debug: true,
        timeZone: 'America/Guyana', // Georgetown, Guyana (UTC-04:00)
        drawInterval: 180,  // 3 minutes in seconds
        syncInterval: 1000, // Sync every second
        broadcastChannel: 'georgetown_time_sync_channel',
        keys: {
            lastSyncTime: 'georgetown_last_sync_time',
            nextDrawTime: 'georgetown_next_draw_time',
            currentDrawNumber: 'georgetown_current_draw_number',
            nextDrawNumber: 'georgetown_next_draw_number',
            upcomingDrawTimes: 'georgetown_upcoming_draw_times'
        }
    };

    // State
    let state = {
        initialized: false,
        syncIntervalId: null,
        timeOffset: 0, // Offset between local time and server time
        nextDrawTime: null,
        currentDrawNumber: 67,
        nextDrawNumber: 68,
        upcomingDrawTimes: [],
        callbacks: {
            timeUpdate: [],
            drawComplete: []
        },
        broadcastChannel: null
    };

    // Logging function
    function log(message, data) {
        if (config.debug) {
            console.log(`[GeorgetownTimeSync] ${message}`, data !== undefined ? data : '');
        }
    }

    // Error logging function
    function error(message, err) {
        console.error(`[GeorgetownTimeSync] ERROR: ${message}`, err);
    }

    /**
     * Initialize the time synchronization
     * @param {Object} options - Configuration options
     */
    function initialize(options = {}) {
        // Merge options with default config
        Object.assign(config, options);

        log('Initializing Georgetown Time Synchronization');

        // Load state from localStorage
        loadStateFromLocalStorage();

        // Check for TV display draw numbers and use them if they're higher
        const tvDisplayCurrentDraw = localStorage.getItem('tv_display_current_draw');
        if (tvDisplayCurrentDraw) {
            const parsedTvDisplayCurrentDraw = parseInt(tvDisplayCurrentDraw);
            if (parsedTvDisplayCurrentDraw > state.currentDrawNumber) {
                state.currentDrawNumber = parsedTvDisplayCurrentDraw;
                state.nextDrawNumber = state.currentDrawNumber + 1;
                log('Updated draw numbers from TV display:', {
                    currentDrawNumber: state.currentDrawNumber,
                    nextDrawNumber: state.nextDrawNumber
                });

                // Save the updated state
                saveStateToLocalStorage();
            }
        }

        // Set up BroadcastChannel for real-time communication between tabs
        try {
            state.broadcastChannel = new BroadcastChannel(config.broadcastChannel);

            // Listen for messages from other tabs
            state.broadcastChannel.onmessage = function(event) {
                log('Received message from BroadcastChannel', event.data);

                if (event.data.type === 'draw_complete') {
                    handleDrawComplete(event.data);
                } else if (event.data.type === 'time_sync') {
                    handleTimeSync(event.data);
                }
            };

            log('BroadcastChannel initialized');
        } catch (err) {
            error('BroadcastChannel not supported, falling back to localStorage polling', err);
        }

        // Calculate the next draw time based on Georgetown time
        calculateNextDrawTime();

        // Generate upcoming draw times
        generateUpcomingDrawTimes(10);

        // Start the sync interval
        startSyncInterval();

        // Update any Georgetown time displays on the page
        updateGeorgetownTimeDisplays();

        // Directly update the bottom timer
        if (state.nextDrawTime) {
            const georgetownTime = getGeorgetownTime();
            const secondsUntilNextDraw = Math.max(0, Math.floor((state.nextDrawTime - georgetownTime.getTime()) / 1000));

            const bottomTimer = document.getElementById('countdown-timer');
            if (bottomTimer) {
                const minutes = Math.floor(secondsUntilNextDraw / 60);
                const seconds = secondsUntilNextDraw % 60;
                bottomTimer.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

                // Add the synchronized class to indicate it's synchronized
                bottomTimer.classList.add('synchronized');

                log('Initialized bottom timer', `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`);
            }
        }

        state.initialized = true;
        return true;
    }

    /**
     * Update any Georgetown time displays on the page
     */
    function updateGeorgetownTimeDisplays() {
        // Find any Georgetown time displays
        const timeDisplays = document.querySelectorAll('#georgetown-time-display');

        if (timeDisplays.length > 0) {
            log(`Found ${timeDisplays.length} Georgetown time displays to update`);

            // Get the current Georgetown time
            const georgetownTime = getGeorgetownTime();
            const secondsUntilNextDraw = state.nextDrawTime ?
                Math.max(0, Math.floor((state.nextDrawTime - georgetownTime.getTime()) / 1000)) : null;

            // Format the time
            const hours = georgetownTime.getHours().toString().padStart(2, '0');
            const minutes = georgetownTime.getMinutes().toString().padStart(2, '0');
            const seconds = georgetownTime.getSeconds().toString().padStart(2, '0');

            // Format the countdown
            const countdownMinutes = secondsUntilNextDraw ?
                Math.floor(secondsUntilNextDraw / 60).toString().padStart(2, '0') : '00';
            const countdownSeconds = secondsUntilNextDraw ?
                (secondsUntilNextDraw % 60).toString().padStart(2, '0') : '00';

            // Update each display
            timeDisplays.forEach(display => {
                display.innerHTML = `Georgetown Time: ${hours}:${minutes}:${seconds}<br>Next Draw: ${countdownMinutes}:${countdownSeconds}`;
            });

            // Also update the bottom timer directly
            const bottomTimer = document.getElementById('countdown-timer');
            if (bottomTimer && secondsUntilNextDraw !== null) {
                bottomTimer.textContent = `${countdownMinutes}:${countdownSeconds}`;
                log('Directly updated bottom timer', `${countdownMinutes}:${countdownSeconds}`);
            }
        }
    }

    /**
     * Load state from localStorage
     */
    function loadStateFromLocalStorage() {
        try {
            // Load next draw time
            const nextDrawTime = localStorage.getItem(config.keys.nextDrawTime);
            if (nextDrawTime) {
                state.nextDrawTime = parseInt(nextDrawTime);
                log('Loaded next draw time from localStorage', new Date(state.nextDrawTime));
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
     * Save state to localStorage
     */
    function saveStateToLocalStorage() {
        try {
            // Save next draw time
            if (state.nextDrawTime) {
                localStorage.setItem(config.keys.nextDrawTime, state.nextDrawTime.toString());
            }

            // Save current draw number
            localStorage.setItem(config.keys.currentDrawNumber, state.currentDrawNumber.toString());

            // Save next draw number
            localStorage.setItem(config.keys.nextDrawNumber, state.nextDrawNumber.toString());

            // Save upcoming draw times
            if (state.upcomingDrawTimes && state.upcomingDrawTimes.length > 0) {
                localStorage.setItem(config.keys.upcomingDrawTimes, JSON.stringify(state.upcomingDrawTimes));
            }

            log('Saved state to localStorage');
        } catch (err) {
            error('Failed to save state to localStorage', err);
        }
    }

    /**
     * Start the sync interval
     */
    function startSyncInterval() {
        if (state.syncIntervalId) {
            clearInterval(state.syncIntervalId);
        }

        state.syncIntervalId = setInterval(syncTime, config.syncInterval);
        log(`Sync interval started (every ${config.syncInterval}ms)`);
    }

    /**
     * Sync time with Georgetown time and check for draw completion
     */
    function syncTime() {
        // Get current Georgetown time
        const georgetownTime = getGeorgetownTime();

        // Check if it's time for a new draw
        if (state.nextDrawTime && georgetownTime.getTime() >= state.nextDrawTime) {
            log('Draw time reached, completing current draw');
            completeCurrentDraw();
        } else if (state.nextDrawTime) {
            // Update the countdown end time in localStorage for other timers to use
            localStorage.setItem('roulette_countdown_end_time', state.nextDrawTime.toString());

            // Calculate and store seconds until next draw for direct access
            const secondsUntilNextDraw = Math.max(0, Math.floor((state.nextDrawTime - georgetownTime.getTime()) / 1000));
            localStorage.setItem('georgetown_seconds_until_next_draw', secondsUntilNextDraw.toString());

            // Directly update the bottom timer
            const bottomTimer = document.getElementById('countdown-timer');
            if (bottomTimer) {
                const minutes = Math.floor(secondsUntilNextDraw / 60);
                const seconds = secondsUntilNextDraw % 60;
                bottomTimer.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

                // Add the synchronized class to indicate it's synchronized
                if (!bottomTimer.classList.contains('synchronized')) {
                    bottomTimer.classList.add('synchronized');
                }
            }
        }

        // Notify all time update callbacks
        notifyTimeUpdateCallbacks(georgetownTime);

        // Update any Georgetown time displays on the page
        updateGeorgetownTimeDisplays();

        // Broadcast current time to other tabs
        broadcastTimeSync(georgetownTime);
    }

    /**
     * Get the current Georgetown time
     * @returns {Date} Current Georgetown time
     */
    function getGeorgetownTime() {
        try {
            // Direct calculation for Georgetown time (UTC-04:00)
            // This is more reliable than using timeZone option which may not be supported in all browsers
            const now = new Date();

            // Convert to UTC time
            const utcTime = now.getTime() + (now.getTimezoneOffset() * 60000);

            // Convert to Georgetown time (UTC-04:00)
            const georgetownTime = new Date(utcTime - (4 * 3600000));

            log('Georgetown time calculated', {
                localTime: now.toISOString(),
                georgetownTime: georgetownTime.toISOString()
            });

            return georgetownTime;
        } catch (err) {
            error('Error getting Georgetown time', err);

            // If all else fails, just return the current time
            // This is not ideal but prevents the system from breaking completely
            return new Date();
        }
    }

    /**
     * Calculate the next draw time based on Georgetown time
     */
    function calculateNextDrawTime() {
        // Get current Georgetown time
        const now = getGeorgetownTime();

        // Calculate minutes until next 3-minute interval
        // We want draws to happen every 3 minutes: at :00, :03, :06, :09, etc.
        const currentMinutes = now.getMinutes();
        const currentSeconds = now.getSeconds();
        const minutesUntilNextDraw = 3 - (currentMinutes % 3);
        let secondsUntilNextDraw = (minutesUntilNextDraw * 60) - currentSeconds;

        // If we're exactly at a 3-minute mark, set for the next one
        if (secondsUntilNextDraw === 0 || secondsUntilNextDraw === config.drawInterval) {
            secondsUntilNextDraw = config.drawInterval;
        }

        // Calculate the exact timestamp for the next draw
        const nextDrawTime = new Date(now.getTime() + (secondsUntilNextDraw * 1000));
        state.nextDrawTime = nextDrawTime.getTime();

        log('Next draw time calculated', {
            georgetownTime: now,
            nextDrawTime: nextDrawTime,
            secondsUntilNextDraw: secondsUntilNextDraw
        });

        // Save to localStorage - this is critical for timer synchronization
        saveStateToLocalStorage();

        // Also save the countdown end time in the format expected by other timers
        localStorage.setItem('roulette_countdown_end_time', state.nextDrawTime.toString());

        return {
            timestamp: state.nextDrawTime,
            secondsRemaining: secondsUntilNextDraw
        };
    }

    /**
     * Generate upcoming draw times
     * @param {number} count - Number of upcoming draw times to generate
     * @returns {Array} Array of objects with timestamp and formattedTime
     */
    function generateUpcomingDrawTimes(count = 10) {
        const result = [];
        const upcomingDraws = [];
        const upcomingDrawTimes = [];

        // Get current Georgetown time
        const now = getGeorgetownTime();

        // Calculate the base time for the next draw
        const nextDrawTime = state.nextDrawTime ? new Date(state.nextDrawTime) : calculateNextDrawTime().timestamp;
        const baseTime = new Date(nextDrawTime);

        // Generate the requested number of draw times
        for (let i = 0; i < count; i++) {
            const drawTime = new Date(baseTime.getTime() + (i * config.drawInterval * 1000));

            // Format the time as HH:MM:SS
            const hours = drawTime.getHours().toString().padStart(2, '0');
            const minutes = drawTime.getMinutes().toString().padStart(2, '0');
            const seconds = drawTime.getSeconds().toString().padStart(2, '0');
            const formattedTime = `${hours}:${minutes}:${seconds}`;

            result.push({
                timestamp: drawTime.getTime(),
                formattedTime: formattedTime
            });

            upcomingDraws.push(state.nextDrawNumber + i);
            upcomingDrawTimes.push(formattedTime);
        }

        // Store the upcoming draw times
        state.upcomingDrawTimes = result;

        // Save to localStorage
        saveStateToLocalStorage();

        // Return both the detailed result and the simple arrays
        return {
            detailed: result,
            upcomingDraws: upcomingDraws,
            upcomingDrawTimes: upcomingDrawTimes
        };
    }

    /**
     * Complete the current draw and prepare for the next one
     */
    function completeCurrentDraw() {
        log('Completing current draw', state.currentDrawNumber);

        // Generate a random winning number (0-36)
        const winningNumber = Math.floor(Math.random() * 37);

        // Create a transaction ID for this atomic update
        const transactionId = Date.now() + '-' + Math.random().toString(36).substring(2, 15);

        // Update draw numbers
        state.currentDrawNumber = state.nextDrawNumber;
        state.nextDrawNumber = state.currentDrawNumber + 1;

        // Calculate next draw time
        calculateNextDrawTime();

        // Generate new upcoming draw times
        generateUpcomingDrawTimes(10);

        // Create an atomic update object with all the data
        const atomicUpdate = {
            transactionId: transactionId,
            currentDrawNumber: state.currentDrawNumber,
            nextDrawNumber: state.nextDrawNumber,
            winningNumber: winningNumber,
            nextDrawTime: state.nextDrawTime,
            upcomingDrawTimes: state.upcomingDrawTimes,
            timestamp: Date.now()
        };

        // Save the atomic update to localStorage
        try {
            // Save all values in a single atomic operation
            localStorage.setItem('georgetown_atomic_update', JSON.stringify(atomicUpdate));

            // Then update individual values
            saveStateToLocalStorage();

            // Also save the countdown end time in the format expected by other timers
            localStorage.setItem('roulette_countdown_end_time', state.nextDrawTime.toString());

            // Calculate and store seconds until next draw for direct access
            const secondsUntilNextDraw = Math.max(0, Math.floor((state.nextDrawTime - Date.now()) / 1000));
            localStorage.setItem('georgetown_seconds_until_next_draw', secondsUntilNextDraw.toString());

            // Update TV display draw numbers
            // This ensures the TV display and main cashier interface stay in sync
            localStorage.setItem('tv_display_previous_draw', (state.currentDrawNumber - 1).toString());
            localStorage.setItem('tv_display_current_draw', state.currentDrawNumber.toString());

            log('Saved atomic update to localStorage and updated TV display draw numbers', atomicUpdate);
        } catch (err) {
            error('Failed to save atomic update to localStorage', err);
        }

        // Directly update the bottom timer
        const bottomTimer = document.getElementById('countdown-timer');
        if (bottomTimer) {
            const secondsUntilNextDraw = Math.max(0, Math.floor((state.nextDrawTime - Date.now()) / 1000));
            const minutes = Math.floor(secondsUntilNextDraw / 60);
            const seconds = secondsUntilNextDraw % 60;
            bottomTimer.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

            // Add the synchronized class to indicate it's synchronized
            if (!bottomTimer.classList.contains('synchronized')) {
                bottomTimer.classList.add('synchronized');
            }

            log('Updated bottom timer after draw completion', `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`);
        }

        // Broadcast draw completion to other tabs
        broadcastDrawComplete(winningNumber, transactionId);

        // Notify all draw complete callbacks
        notifyDrawCompleteCallbacks(state.currentDrawNumber, winningNumber, transactionId);
    }

    /**
     * Broadcast draw completion to other tabs
     */
    function broadcastDrawComplete(winningNumber, transactionId) {
        if (state.broadcastChannel) {
            state.broadcastChannel.postMessage({
                type: 'draw_complete',
                currentDrawNumber: state.currentDrawNumber,
                nextDrawNumber: state.nextDrawNumber,
                winningNumber: winningNumber,
                nextDrawTime: state.nextDrawTime,
                upcomingDrawTimes: state.upcomingDrawTimes,
                transactionId: transactionId || Date.now() + '-' + Math.random().toString(36).substring(2, 15),
                timestamp: Date.now()
            });
        }
    }

    /**
     * Broadcast time sync to other tabs
     */
    function broadcastTimeSync(georgetownTime) {
        if (state.broadcastChannel) {
            state.broadcastChannel.postMessage({
                type: 'time_sync',
                georgetownTime: georgetownTime.getTime(),
                currentDrawNumber: state.currentDrawNumber,
                nextDrawNumber: state.nextDrawNumber,
                nextDrawTime: state.nextDrawTime,
                upcomingDrawTimes: state.upcomingDrawTimes,
                timestamp: Date.now()
            });
        }
    }

    /**
     * Handle draw complete message from other tabs
     */
    function handleDrawComplete(data) {
        log('Handling draw complete message', data);

        // Check if we've already processed this transaction
        const lastProcessedTransaction = localStorage.getItem('georgetown_last_processed_transaction');
        if (lastProcessedTransaction === data.transactionId) {
            log('Already processed this transaction, skipping', data.transactionId);
            return;
        }

        // Update state
        state.currentDrawNumber = data.currentDrawNumber;
        state.nextDrawNumber = data.nextDrawNumber;
        state.nextDrawTime = data.nextDrawTime;
        state.upcomingDrawTimes = data.upcomingDrawTimes;

        // Create an atomic update object with all the data
        const atomicUpdate = {
            transactionId: data.transactionId,
            currentDrawNumber: state.currentDrawNumber,
            nextDrawNumber: state.nextDrawNumber,
            winningNumber: data.winningNumber,
            nextDrawTime: state.nextDrawTime,
            upcomingDrawTimes: state.upcomingDrawTimes,
            timestamp: Date.now()
        };

        // Save the atomic update to localStorage
        try {
            // Save all values in a single atomic operation
            localStorage.setItem('georgetown_atomic_update', JSON.stringify(atomicUpdate));
            localStorage.setItem('georgetown_last_processed_transaction', data.transactionId);

            // Then update individual values
            saveStateToLocalStorage();

            // Also save the countdown end time in the format expected by other timers
            localStorage.setItem('roulette_countdown_end_time', state.nextDrawTime.toString());

            // Calculate and store seconds until next draw for direct access
            const secondsUntilNextDraw = Math.max(0, Math.floor((state.nextDrawTime - Date.now()) / 1000));
            localStorage.setItem('georgetown_seconds_until_next_draw', secondsUntilNextDraw.toString());

            // Update TV display draw numbers
            // This ensures the TV display and main cashier interface stay in sync
            localStorage.setItem('tv_display_previous_draw', (state.currentDrawNumber - 1).toString());
            localStorage.setItem('tv_display_current_draw', state.currentDrawNumber.toString());

            log('Saved atomic update from broadcast to localStorage and updated TV display draw numbers', atomicUpdate);
        } catch (err) {
            error('Failed to save atomic update to localStorage', err);
        }

        // Directly update the bottom timer
        const bottomTimer = document.getElementById('countdown-timer');
        if (bottomTimer) {
            const secondsUntilNextDraw = Math.max(0, Math.floor((state.nextDrawTime - Date.now()) / 1000));
            const minutes = Math.floor(secondsUntilNextDraw / 60);
            const seconds = secondsUntilNextDraw % 60;
            bottomTimer.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

            // Add the synchronized class to indicate it's synchronized
            if (!bottomTimer.classList.contains('synchronized')) {
                bottomTimer.classList.add('synchronized');
            }

            log('Updated bottom timer after receiving draw complete message', `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`);
        }

        // Notify all draw complete callbacks
        notifyDrawCompleteCallbacks(state.currentDrawNumber, data.winningNumber, data.transactionId);
    }

    /**
     * Handle time sync message from other tabs
     */
    function handleTimeSync(data) {
        // Only update if the message is newer than our current state
        if (data.timestamp > (state.lastSyncTime || 0)) {
            log('Handling time sync message', data);

            // Update state
            state.currentDrawNumber = data.currentDrawNumber;
            state.nextDrawNumber = data.nextDrawNumber;
            state.nextDrawTime = data.nextDrawTime;
            state.upcomingDrawTimes = data.upcomingDrawTimes;
            state.lastSyncTime = data.timestamp;

            // Save state to localStorage
            saveStateToLocalStorage();

            // Directly update the bottom timer
            const bottomTimer = document.getElementById('countdown-timer');
            if (bottomTimer && state.nextDrawTime) {
                const georgetownTime = new Date(data.georgetownTime);
                const secondsUntilNextDraw = Math.max(0, Math.floor((state.nextDrawTime - georgetownTime.getTime()) / 1000));
                const minutes = Math.floor(secondsUntilNextDraw / 60);
                const seconds = secondsUntilNextDraw % 60;
                bottomTimer.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

                // Add the synchronized class to indicate it's synchronized
                if (!bottomTimer.classList.contains('synchronized')) {
                    bottomTimer.classList.add('synchronized');
                }

                log('Updated bottom timer after receiving time sync message', `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`);
            }

            // Notify all time update callbacks
            notifyTimeUpdateCallbacks(new Date(data.georgetownTime));
        }
    }

    /**
     * Notify all time update callbacks
     */
    function notifyTimeUpdateCallbacks(georgetownTime) {
        state.callbacks.timeUpdate.forEach(callback => {
            try {
                callback(georgetownTime, state.nextDrawTime ? Math.max(0, Math.floor((state.nextDrawTime - georgetownTime.getTime()) / 1000)) : null);
            } catch (err) {
                error('Error in time update callback', err);
            }
        });
    }

    /**
     * Notify all draw complete callbacks
     */
    function notifyDrawCompleteCallbacks(drawNumber, winningNumber, transactionId) {
        state.callbacks.drawComplete.forEach(callback => {
            try {
                callback(drawNumber, winningNumber, transactionId);
            } catch (err) {
                error('Error in draw complete callback', err);
            }
        });
    }

    /**
     * Register a callback for time updates
     * @param {Function} callback - Function to call on time update
     */
    function onTimeUpdate(callback) {
        if (typeof callback === 'function') {
            state.callbacks.timeUpdate.push(callback);

            // Immediately call with current values
            const georgetownTime = getGeorgetownTime();
            const secondsRemaining = state.nextDrawTime ? Math.max(0, Math.floor((state.nextDrawTime - georgetownTime.getTime()) / 1000)) : null;
            callback(georgetownTime, secondsRemaining);
        }
    }

    /**
     * Register a callback for draw completion
     * @param {Function} callback - Function to call on draw completion
     */
    function onDrawComplete(callback) {
        if (typeof callback === 'function') {
            state.callbacks.drawComplete.push(callback);
        }
    }

    // Return public API
    return {
        initialize,
        getGeorgetownTime,
        calculateNextDrawTime,
        generateUpcomingDrawTimes,
        onTimeUpdate,
        onDrawComplete,
        getCurrentDrawNumber: () => state.currentDrawNumber,
        getNextDrawNumber: () => state.nextDrawNumber,
        getUpcomingDrawTimes: () => state.upcomingDrawTimes,
        getSecondsUntilNextDraw: () => {
            const georgetownTime = getGeorgetownTime();
            const secondsRemaining = state.nextDrawTime ? Math.max(0, Math.floor((state.nextDrawTime - georgetownTime.getTime()) / 1000)) : null;

            // Store the value in localStorage for other components to use
            if (secondsRemaining !== null) {
                localStorage.setItem('georgetown_seconds_until_next_draw', secondsRemaining.toString());
            }

            return secondsRemaining;
        }
    };
})();

// Initialize when the document is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the Georgetown Time Synchronization
    GeorgetownTimeSync.initialize({
        debug: true
    });

    console.log('[GeorgetownTimeSync] Georgetown Time Synchronization initialized');
});
