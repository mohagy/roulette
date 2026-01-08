/**
 * Draw Number Synchronization
 *
 * This module synchronizes draw numbers from the TV display to the main cashier interface.
 * It uses localStorage as the synchronization mechanism.
 */

const DrawNumberSync = (function() {
    // Configuration
    const config = {
        debug: true,
        syncInterval: 2000, // Check for updates every 2 seconds
        keys: {
            // Shared keys for localStorage
            previousDraw: 'tv_display_previous_draw',
            currentDraw: 'tv_display_current_draw',
            lastSyncTime: 'tv_display_sync_time',
            upcomingDraws: 'tv_display_upcoming_draws',
            upcomingDrawTimes: 'tv_display_upcoming_draw_times',
            georgetownNextDraw: 'georgetown_next_draw_number' // Added for Georgetown time sync
        }
    };

    // State
    let state = {
        initialized: false,
        previousDraw: null,
        currentDraw: null,
        upcomingDraws: [],
        upcomingDrawTimes: [],
        syncIntervalId: null,
        lastSyncTime: null,
        georgetownNextDraw: null // Added for Georgetown time sync
    };

    // Logging function
    function log(message, data) {
        if (config.debug) {
            if (data !== undefined) {
                console.log(`[DrawNumberSync] ${message}`, data);
            } else {
                console.log(`[DrawNumberSync] ${message}`);
            }
        }
    }

    // Error logging function
    function error(message, err) {
        console.error(`[DrawNumberSync] ERROR: ${message}`, err);
    }

    /**
     * Initialize the draw number synchronization
     * @param {Object} options - Configuration options
     */
    function initialize(options = {}) {
        // Merge options with default config
        Object.assign(config, options);

        log('Initializing DrawNumberSync');

        // Check if we're on the TV display or main cashier interface
        const isTVDisplay = window.location.pathname.includes('/tvdisplay/');

        if (isTVDisplay) {
            log('Running on TV display, will monitor draw numbers and update localStorage');

            // Start monitoring draw numbers
            startMonitoringDrawNumbers();
        } else {
            log('Running on main cashier interface, will sync from TV display');

            // Load state from localStorage
            loadStateFromLocalStorage();

            // Start sync interval
            startSyncInterval();

            // Initial UI update
            updateUI();
        }

        state.initialized = true;
        return true;
    }

    /**
     * Start monitoring draw numbers on the TV display
     */
    function startMonitoringDrawNumbers() {
        // Initial capture of draw numbers
        captureDrawNumbers();

        // Set up a MutationObserver to detect changes to the draw number elements
        const lastDrawElement = document.getElementById('last-draw-number');
        const nextDrawElement = document.getElementById('next-draw-number');

        if (lastDrawElement && nextDrawElement) {
            const observer = new MutationObserver(function(mutations) {
                captureDrawNumbers();
            });

            // Observe both elements for changes to their text content
            observer.observe(lastDrawElement, { childList: true, characterData: true, subtree: true });
            observer.observe(nextDrawElement, { childList: true, characterData: true, subtree: true });

            log('MutationObserver set up to monitor draw number changes');
        }

        // Also set up an interval as a fallback
        setInterval(captureDrawNumbers, config.syncInterval);
    }

    /**
     * Capture draw numbers from the TV display and save to localStorage
     */
    function captureDrawNumbers() {
        const lastDrawElement = document.getElementById('last-draw-number');
        const nextDrawElement = document.getElementById('next-draw-number');

        if (lastDrawElement && nextDrawElement) {
            const lastDrawText = lastDrawElement.textContent;
            const nextDrawText = nextDrawElement.textContent;

            // Extract numbers from text (format: #N or -)
            let previousDraw = lastDrawText === '-' ? null : parseInt(lastDrawText.replace('#', ''));
            let currentDraw = parseInt(nextDrawText.replace('#', ''));

            if (!isNaN(currentDraw)) {
                // Check if values have changed
                if (state.currentDraw !== currentDraw ||
                    (previousDraw !== null && state.previousDraw !== previousDraw)) {

                    // Update state
                    state.previousDraw = previousDraw;
                    state.currentDraw = currentDraw;
                    state.lastSyncTime = Date.now();

                    // Save to localStorage
                    if (previousDraw !== null) {
                        localStorage.setItem(config.keys.previousDraw, previousDraw.toString());
                    } else {
                        localStorage.removeItem(config.keys.previousDraw);
                    }
                    localStorage.setItem(config.keys.currentDraw, currentDraw.toString());
                    localStorage.setItem(config.keys.lastSyncTime, state.lastSyncTime.toString());

                    // Check for Georgetown next draw number and update if needed
                    const georgetownNextDraw = localStorage.getItem(config.keys.georgetownNextDraw);
                    if (georgetownNextDraw) {
                        const parsedGeorgetownNextDraw = parseInt(georgetownNextDraw);
                        if (parsedGeorgetownNextDraw > 0) {
                            state.georgetownNextDraw = parsedGeorgetownNextDraw;

                            // If our current draw is higher than Georgetown's, update Georgetown
                            if (currentDraw >= parsedGeorgetownNextDraw) {
                                localStorage.setItem(config.keys.georgetownNextDraw, (currentDraw + 1).toString());
                                log('Updated Georgetown next draw based on TV display:', currentDraw + 1);
                            }
                        }
                    }

                    log('Saved draw numbers to localStorage', {
                        previousDraw,
                        currentDraw,
                        timestamp: new Date(state.lastSyncTime)
                    });

                    // Generate upcoming draws
                    generateUpcomingDraws(currentDraw);
                }
            }
        }
    }

    /**
     * Generate upcoming draws and save to localStorage
     * @param {number} currentDraw - The current draw number
     */
    function generateUpcomingDraws(currentDraw) {
        // Generate 10 upcoming draws starting from the current draw
        const upcomingDraws = [];
        const upcomingDrawTimes = [];

        // Try to use Georgetown time if available
        let useGeorgetownTime = false;
        let georgetownTimeOffset = 0;
        let nextDrawSeconds = 0;

        // Check if we have Georgetown time sync data
        const georgetownSecondsUntilNextDraw = localStorage.getItem('georgetown_seconds_until_next_draw');
        if (georgetownSecondsUntilNextDraw) {
            nextDrawSeconds = parseInt(georgetownSecondsUntilNextDraw);
            useGeorgetownTime = true;
            log('Using Georgetown time for draw times, seconds until next draw:', nextDrawSeconds);
        }

        // Calculate Georgetown time offset (UTC-4)
        try {
            // Direct calculation for Georgetown time (UTC-04:00)
            const now = new Date();
            // Convert to UTC time
            const utcTime = now.getTime() + (now.getTimezoneOffset() * 60000);
            // Convert to Georgetown time (UTC-04:00)
            const georgetownTime = new Date(utcTime - (4 * 3600000));
            georgetownTimeOffset = georgetownTime.getTime() - now.getTime();
            log('Georgetown time offset calculated:', georgetownTimeOffset);
        } catch (err) {
            error('Error calculating Georgetown time offset', err);
            useGeorgetownTime = false;
        }

        for (let i = 0; i < 10; i++) {
            upcomingDraws.push(currentDraw + i);

            // Calculate the time for this draw
            let drawTime;
            if (useGeorgetownTime) {
                // Use Georgetown time with proper intervals
                drawTime = new Date();
                // Add Georgetown offset
                drawTime.setTime(drawTime.getTime() + georgetownTimeOffset);
                // Add time until next draw plus additional intervals
                drawTime.setSeconds(drawTime.getSeconds() + nextDrawSeconds + (i > 0 ? (i * 180) : 0));
            } else {
                // Fallback to simple 3-minute intervals
                drawTime = new Date();
                drawTime.setSeconds(drawTime.getSeconds() + (i * 180));
            }

            // Format time as HH:MM:SS
            const hours = drawTime.getHours().toString().padStart(2, '0');
            const minutes = drawTime.getMinutes().toString().padStart(2, '0');
            const seconds = drawTime.getSeconds().toString().padStart(2, '0');
            upcomingDrawTimes.push(`${hours}:${minutes}:${seconds}`);
        }

        // Update state
        state.upcomingDraws = upcomingDraws;
        state.upcomingDrawTimes = upcomingDrawTimes;

        // Save to localStorage
        localStorage.setItem(config.keys.upcomingDraws, JSON.stringify(upcomingDraws));
        localStorage.setItem(config.keys.upcomingDrawTimes, JSON.stringify(upcomingDrawTimes));

        log('Generated and saved upcoming draws to localStorage', {
            upcomingDraws,
            upcomingDrawTimes,
            usingGeorgetownTime: useGeorgetownTime
        });
    }

    /**
     * Load state from localStorage
     */
    function loadStateFromLocalStorage() {
        try {
            // Load previous draw
            const previousDraw = localStorage.getItem(config.keys.previousDraw);
            if (previousDraw) {
                state.previousDraw = parseInt(previousDraw);
                log('Loaded previous draw from localStorage:', state.previousDraw);
            }

            // Load current draw
            const currentDraw = localStorage.getItem(config.keys.currentDraw);
            if (currentDraw) {
                state.currentDraw = parseInt(currentDraw);
                log('Loaded current draw from localStorage:', state.currentDraw);
            }

            // Check for Georgetown next draw number (higher priority)
            const georgetownNextDraw = localStorage.getItem(config.keys.georgetownNextDraw);
            if (georgetownNextDraw) {
                state.georgetownNextDraw = parseInt(georgetownNextDraw);
                log('Loaded Georgetown next draw from localStorage:', state.georgetownNextDraw);

                // If Georgetown draw number is available and higher than current draw,
                // update current draw to match Georgetown (minus 1 since Georgetown is "next")
                if (state.georgetownNextDraw > 1 && (!state.currentDraw || state.georgetownNextDraw - 1 > state.currentDraw)) {
                    state.currentDraw = state.georgetownNextDraw - 1;
                    log('Updated current draw based on Georgetown next draw:', state.currentDraw);
                }
            }

            // Load last sync time
            const lastSyncTime = localStorage.getItem(config.keys.lastSyncTime);
            if (lastSyncTime) {
                state.lastSyncTime = parseInt(lastSyncTime);
                log('Loaded last sync time from localStorage:', new Date(state.lastSyncTime));
            }

            // Load upcoming draws
            const upcomingDraws = localStorage.getItem(config.keys.upcomingDraws);
            if (upcomingDraws) {
                state.upcomingDraws = JSON.parse(upcomingDraws);
                log('Loaded upcoming draws from localStorage:', state.upcomingDraws);
            }

            // Load upcoming draw times
            const upcomingDrawTimes = localStorage.getItem(config.keys.upcomingDrawTimes);
            if (upcomingDrawTimes) {
                state.upcomingDrawTimes = JSON.parse(upcomingDrawTimes);
                log('Loaded upcoming draw times from localStorage:', state.upcomingDrawTimes);
            }
        } catch (err) {
            error('Failed to load state from localStorage', err);
        }
    }

    /**
     * Start the sync interval for the main cashier interface
     */
    function startSyncInterval() {
        if (state.syncIntervalId) {
            clearInterval(state.syncIntervalId);
        }

        state.syncIntervalId = setInterval(checkForDrawNumberUpdates, config.syncInterval);
        log(`Sync interval started (every ${config.syncInterval}ms)`);

        // Also listen for storage events to get immediate updates
        window.addEventListener('storage', handleStorageEvent);
        log('Added storage event listener');
    }

    /**
     * Handle storage events for immediate updates
     */
    function handleStorageEvent(event) {
        if (event.key === config.keys.previousDraw ||
            event.key === config.keys.currentDraw ||
            event.key === config.keys.lastSyncTime ||
            event.key === config.keys.upcomingDraws ||
            event.key === config.keys.upcomingDrawTimes ||
            event.key === config.keys.georgetownNextDraw) {

            log('Storage event detected', {
                key: event.key,
                oldValue: event.oldValue,
                newValue: event.newValue
            });

            // Reload state and update UI
            loadStateFromLocalStorage();
            updateUI();
        }
    }

    /**
     * Check for updates to draw numbers in localStorage
     */
    function checkForDrawNumberUpdates() {
        try {
            // Check if localStorage has been updated
            const lastSyncTime = localStorage.getItem(config.keys.lastSyncTime);
            const previousDraw = localStorage.getItem(config.keys.previousDraw);
            const currentDraw = localStorage.getItem(config.keys.currentDraw);
            const upcomingDraws = localStorage.getItem(config.keys.upcomingDraws);
            const upcomingDrawTimes = localStorage.getItem(config.keys.upcomingDrawTimes);
            const georgetownNextDraw = localStorage.getItem(config.keys.georgetownNextDraw);

            let hasChanges = false;

            // Check if Georgetown next draw has changed (highest priority)
            if (georgetownNextDraw) {
                const parsedGeorgetownNextDraw = parseInt(georgetownNextDraw);
                if (!state.georgetownNextDraw || parsedGeorgetownNextDraw !== state.georgetownNextDraw) {
                    state.georgetownNextDraw = parsedGeorgetownNextDraw;

                    // If Georgetown draw number is available and higher than current draw,
                    // update current draw to match Georgetown (minus 1 since Georgetown is "next")
                    if (state.georgetownNextDraw > 1 && (!state.currentDraw || state.georgetownNextDraw - 1 > state.currentDraw)) {
                        state.currentDraw = state.georgetownNextDraw - 1;
                        hasChanges = true;
                        log('Current draw updated from Georgetown next draw:', state.currentDraw);
                    }
                }
            }

            // Check if previous draw has changed
            if (previousDraw && (!state.previousDraw || parseInt(previousDraw) !== state.previousDraw)) {
                state.previousDraw = parseInt(previousDraw);
                hasChanges = true;
                log('Previous draw updated from localStorage:', state.previousDraw);
            }

            // Check if current draw has changed
            if (currentDraw && (!state.currentDraw || parseInt(currentDraw) !== state.currentDraw)) {
                state.currentDraw = parseInt(currentDraw);
                hasChanges = true;
                log('Current draw updated from localStorage:', state.currentDraw);
            }

            // Check if upcoming draws have changed
            if (upcomingDraws) {
                const parsedUpcomingDraws = JSON.parse(upcomingDraws);
                if (!state.upcomingDraws || !arraysEqual(parsedUpcomingDraws, state.upcomingDraws)) {
                    state.upcomingDraws = parsedUpcomingDraws;
                    hasChanges = true;
                    log('Upcoming draws updated from localStorage:', state.upcomingDraws);
                }
            }

            // Check if upcoming draw times have changed
            if (upcomingDrawTimes) {
                const parsedUpcomingDrawTimes = JSON.parse(upcomingDrawTimes);
                if (!state.upcomingDrawTimes || !arraysEqual(parsedUpcomingDrawTimes, state.upcomingDrawTimes)) {
                    state.upcomingDrawTimes = parsedUpcomingDrawTimes;
                    hasChanges = true;
                    log('Upcoming draw times updated from localStorage:', state.upcomingDrawTimes);
                }
            }

            // Update the UI if changes were detected
            if (hasChanges) {
                updateUI();
            }

            // Update last sync time
            if (lastSyncTime) {
                state.lastSyncTime = parseInt(lastSyncTime);
            }
        } catch (err) {
            error('Failed to check for draw number updates', err);
        }
    }

    /**
     * Compare two arrays for equality
     * @param {Array} arr1 - First array
     * @param {Array} arr2 - Second array
     * @returns {boolean} - Whether the arrays are equal
     */
    function arraysEqual(arr1, arr2) {
        if (arr1.length !== arr2.length) return false;
        for (let i = 0; i < arr1.length; i++) {
            if (arr1[i] !== arr2[i]) return false;
        }
        return true;
    }

    /**
     * Update the UI with current draw numbers
     */
    function updateUI() {
        // Update the main interface draw numbers
        const lastDrawElement = document.getElementById('last-draw-number');
        const nextDrawElement = document.getElementById('next-draw-number');

        if (lastDrawElement && state.previousDraw !== null) {
            const formattedPreviousDraw = `#${state.previousDraw}`;
            if (lastDrawElement.textContent !== formattedPreviousDraw) {
                lastDrawElement.textContent = formattedPreviousDraw;
                log('Updated last-draw-number element:', formattedPreviousDraw);
            }
        }

        if (nextDrawElement && state.currentDraw !== null) {
            const formattedCurrentDraw = `#${state.currentDraw}`;
            if (nextDrawElement.textContent !== formattedCurrentDraw) {
                nextDrawElement.textContent = formattedCurrentDraw;
                log('Updated next-draw-number element:', formattedCurrentDraw);
            }
        }

        // Make sure the draw container is visible
        const drawContainer = document.querySelector('.draw-container');
        if (drawContainer) {
            drawContainer.style.display = 'block';
            drawContainer.style.visibility = 'visible';
            drawContainer.style.opacity = '1';
        }
    }

    // Return public API
    return {
        initialize,
        getPreviousDraw: () => state.previousDraw,
        getCurrentDraw: () => state.currentDraw,
        getUpcomingDraws: () => state.upcomingDraws,
        getUpcomingDrawTimes: () => state.upcomingDrawTimes,
        forceSync: checkForDrawNumberUpdates
    };
})();

// Initialize when the document is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the Draw Number Sync
    DrawNumberSync.initialize({
        debug: true,
        syncInterval: 2000 // Check for updates every 2 seconds
    });
});
