/**
 * Upcoming Draw Display
 *
 * This module creates a floating and movable display for upcoming draws.
 * It synchronizes with the TV display to show the same upcoming draw information.
 * It allows selecting a specific future draw for placing bets.
 */

const UpcomingDrawDisplay = (function() {
    // Configuration
    const config = {
        debug: true,
        syncInterval: 3000, // Check for updates every 3 seconds
        maxDraws: 10, // Maximum number of upcoming draws to display
        betCountsInterval: 10000, // Check for bet counts every 10 seconds
        keys: {
            // Shared keys for localStorage
            upcomingDraws: 'tv_display_upcoming_draws',
            upcomingDrawTimes: 'tv_display_upcoming_draw_times',
            position: 'upcoming_draw_display_position',
            minimized: 'upcoming_draw_display_minimized',
            selectedDraw: 'selected_draw_number'
        }
    };

    // State
    let state = {
        initialized: false,
        upcomingDraws: [],
        upcomingDrawTimes: [],
        betCounts: {}, // Map of draw number to bet count
        container: null,
        isDragging: false,
        dragOffset: { x: 0, y: 0 },
        position: { x: null, y: null },
        minimized: false,
        syncIntervalId: null,
        betCountsIntervalId: null,
        selectedDrawNumber: null, // Currently selected draw number
        currentDrawNumber: null, // Current draw number (from TV display)
        selectionIndicator: null // DOM element for the selection indicator
    };

    // Logging function
    function log(message, data) {
        if (config.debug) {
            if (data !== undefined) {
                console.log(`[UpcomingDrawDisplay] ${message}`, data);
            } else {
                console.log(`[UpcomingDrawDisplay] ${message}`);
            }
        }
    }

    // Error logging function
    function error(message, err) {
        console.error(`[UpcomingDrawDisplay] ERROR: ${message}`, err);
    }

    /**
     * Initialize the upcoming draw display
     * @param {Object} options - Configuration options
     */
    function initialize(options = {}) {
        // Merge options with default config
        Object.assign(config, options);

        log('Initializing UpcomingDrawDisplay');

        // Create the container if it doesn't exist
        if (!state.container) {
            createContainer();
        }

        // Create selection indicator if it doesn't exist
        if (!state.selectionIndicator) {
            createSelectionIndicator();
        }

        // Load position from localStorage
        loadPositionFromLocalStorage();

        // Load minimized state from localStorage
        loadMinimizedStateFromLocalStorage();

        // Load selected draw from localStorage
        loadSelectedDrawFromLocalStorage();

        // Apply position and minimized state
        applyPositionAndState();

        // Check for Georgetown time sync
        if (window.GeorgetownTimeSync) {
            log('GeorgetownTimeSync detected, will use it for draw time synchronization');

            // Register for draw completion events
            window.GeorgetownTimeSync.onDrawComplete((drawNumber, winningNumber, transactionId) => {
                log('Received draw complete from GeorgetownTimeSync', {
                    drawNumber,
                    winningNumber,
                    transactionId
                });

                // Get upcoming draw times from Georgetown time
                const upcomingDrawTimes = window.GeorgetownTimeSync.getUpcomingDrawTimes();
                if (upcomingDrawTimes && upcomingDrawTimes.detailed) {
                    // Generate upcoming draws based on the current draw number
                    const upcomingDraws = [];
                    const formattedTimes = [];

                    for (let i = 0; i < Math.min(config.maxDraws, upcomingDrawTimes.detailed.length); i++) {
                        upcomingDraws.push(drawNumber + i);
                        formattedTimes.push(upcomingDrawTimes.detailed[i].formattedTime);
                    }

                    // Update our display with the new data
                    updateDraws(upcomingDraws, formattedTimes);
                }
            });
        }

        // Start sync interval
        startSyncInterval();

        // Start bet counts interval
        startBetCountsInterval();

        // Initial data load
        loadUpcomingDrawsFromLocalStorage();
        updateDisplay();

        // Initial bet counts load
        fetchBetCounts();

        // Set up event listener for storage changes
        window.addEventListener('storage', handleStorageEvent);

        state.initialized = true;
        return true;
    }

    /**
     * Create the selection indicator for the main interface
     */
    function createSelectionIndicator() {
        const indicator = document.createElement('div');
        indicator.className = 'draw-selection-indicator';
        indicator.id = 'draw-selection-indicator';

        // Add icon
        const icon = document.createElement('i');
        icon.className = 'fas fa-calendar-check';
        indicator.appendChild(icon);

        // Add text
        const text = document.createElement('span');
        text.className = 'indicator-text';
        text.textContent = 'Betting for Draw #0';
        indicator.appendChild(text);

        // Add close button
        const closeBtn = document.createElement('span');
        closeBtn.className = 'close-btn';
        closeBtn.innerHTML = '&times;';
        closeBtn.title = 'Reset to current draw';
        closeBtn.addEventListener('click', resetToCurrentDraw);
        indicator.appendChild(closeBtn);

        // Add to document
        document.body.appendChild(indicator);

        // Store in state
        state.selectionIndicator = indicator;

        log('Created selection indicator');
    }

    /**
     * Load selected draw from localStorage
     */
    function loadSelectedDrawFromLocalStorage() {
        try {
            const selectedDraw = localStorage.getItem(config.keys.selectedDraw);
            if (selectedDraw) {
                state.selectedDrawNumber = parseInt(selectedDraw);
                log('Loaded selected draw from localStorage:', state.selectedDrawNumber);

                // Set the global selected draw number
                window.selectedDrawNumber = state.selectedDrawNumber;

                // Show the selection indicator
                updateSelectionIndicator();
            }
        } catch (err) {
            error('Failed to load selected draw from localStorage', err);
        }
    }

    /**
     * Create the container for the upcoming draw display
     */
    function createContainer() {
        // Create container
        const container = document.createElement('div');
        container.className = 'upcoming-draw-container';
        container.id = 'upcoming-draw-display';

        // Create header
        const header = document.createElement('div');
        header.className = 'upcoming-draw-header';

        // Create title
        const title = document.createElement('div');
        title.className = 'upcoming-draw-title';
        title.textContent = 'Upcoming Draws';

        // Create controls
        const controls = document.createElement('div');
        controls.className = 'upcoming-draw-controls';

        // Create minimize button
        const minimizeBtn = document.createElement('div');
        minimizeBtn.className = 'upcoming-draw-control minimize-btn';
        minimizeBtn.innerHTML = '&minus;';
        minimizeBtn.title = 'Minimize';
        minimizeBtn.addEventListener('click', toggleMinimize);

        // Create close button
        const closeBtn = document.createElement('div');
        closeBtn.className = 'upcoming-draw-control close-btn';
        closeBtn.innerHTML = '&times;';
        closeBtn.title = 'Close';
        closeBtn.addEventListener('click', hideDisplay);

        // Add controls to header
        controls.appendChild(minimizeBtn);
        controls.appendChild(closeBtn);

        // Add title and controls to header
        header.appendChild(title);
        header.appendChild(controls);

        // Create content
        const content = document.createElement('div');
        content.className = 'upcoming-draw-content';

        // Create list
        const list = document.createElement('ul');
        list.className = 'upcoming-draw-list';
        content.appendChild(list);

        // Add header and content to container
        container.appendChild(header);
        container.appendChild(content);

        // Add container to document
        document.body.appendChild(container);

        // Store container in state
        state.container = container;

        // Setup drag functionality
        setupDragFunctionality(header);

        log('Created upcoming draw display container');
    }

    /**
     * Setup drag functionality for the container
     * @param {HTMLElement} dragHandle - The element to use as the drag handle
     */
    function setupDragFunctionality(dragHandle) {
        dragHandle.addEventListener('mousedown', startDrag);
        dragHandle.addEventListener('touchstart', startDrag, { passive: false });

        function startDrag(e) {
            e.preventDefault();

            state.isDragging = true;
            dragHandle.classList.add('dragging');
            state.container.classList.add('dragging');

            const rect = state.container.getBoundingClientRect();

            if (e.type === 'mousedown') {
                state.dragOffset.x = e.clientX - rect.left;
                state.dragOffset.y = e.clientY - rect.top;
            } else if (e.type === 'touchstart') {
                state.dragOffset.x = e.touches[0].clientX - rect.left;
                state.dragOffset.y = e.touches[0].clientY - rect.top;
            }

            document.addEventListener('mousemove', doDrag);
            document.addEventListener('touchmove', doDrag, { passive: false });
            document.addEventListener('mouseup', stopDrag);
            document.addEventListener('touchend', stopDrag);

            log('Started dragging upcoming draw display');
        }

        function doDrag(e) {
            if (!state.isDragging) return;
            e.preventDefault();

            let clientX, clientY;

            if (e.type === 'touchmove') {
                clientX = e.touches[0].clientX;
                clientY = e.touches[0].clientY;
            } else {
                clientX = e.clientX;
                clientY = e.clientY;
            }

            // Calculate new position
            const left = clientX - state.dragOffset.x;
            const top = clientY - state.dragOffset.y;

            // Update position
            state.container.style.left = left + 'px';
            state.container.style.top = top + 'px';

            // Store position for later use
            state.position.x = left;
            state.position.y = top;
        }

        function stopDrag() {
            if (!state.isDragging) return;

            state.isDragging = false;
            dragHandle.classList.remove('dragging');
            state.container.classList.remove('dragging');

            document.removeEventListener('mousemove', doDrag);
            document.removeEventListener('touchmove', doDrag);
            document.removeEventListener('mouseup', stopDrag);
            document.removeEventListener('touchend', stopDrag);

            // Save position to localStorage
            savePositionToLocalStorage();

            log('Stopped dragging upcoming draw display');
        }
    }

    /**
     * Toggle minimize state
     */
    function toggleMinimize() {
        state.minimized = !state.minimized;

        if (state.minimized) {
            state.container.classList.add('minimized');
        } else {
            state.container.classList.remove('minimized');
        }

        // Save minimized state to localStorage
        localStorage.setItem(config.keys.minimized, state.minimized.toString());

        log('Toggled minimize state:', state.minimized);
    }

    /**
     * Hide the display
     */
    function hideDisplay() {
        state.container.style.display = 'none';
        log('Hid upcoming draw display');
    }

    /**
     * Show the display
     */
    function showDisplay() {
        state.container.style.display = 'block';
        log('Showed upcoming draw display');
    }

    /**
     * Load position from localStorage
     */
    function loadPositionFromLocalStorage() {
        try {
            const positionStr = localStorage.getItem(config.keys.position);
            if (positionStr) {
                state.position = JSON.parse(positionStr);
                log('Loaded position from localStorage:', state.position);
            }
        } catch (err) {
            error('Failed to load position from localStorage', err);
        }
    }

    /**
     * Save position to localStorage
     */
    function savePositionToLocalStorage() {
        try {
            localStorage.setItem(config.keys.position, JSON.stringify(state.position));
            log('Saved position to localStorage:', state.position);
        } catch (err) {
            error('Failed to save position to localStorage', err);
        }
    }

    /**
     * Load minimized state from localStorage
     */
    function loadMinimizedStateFromLocalStorage() {
        try {
            const minimizedStr = localStorage.getItem(config.keys.minimized);
            if (minimizedStr) {
                state.minimized = minimizedStr === 'true';
                log('Loaded minimized state from localStorage:', state.minimized);
            }
        } catch (err) {
            error('Failed to load minimized state from localStorage', err);
        }
    }

    /**
     * Apply position and minimized state
     */
    function applyPositionAndState() {
        // Apply position
        if (state.position.x !== null && state.position.y !== null) {
            state.container.style.left = state.position.x + 'px';
            state.container.style.top = state.position.y + 'px';
        }

        // Apply minimized state
        if (state.minimized) {
            state.container.classList.add('minimized');
        } else {
            state.container.classList.remove('minimized');
        }
    }

    /**
     * Start the sync interval
     */
    function startSyncInterval() {
        if (state.syncIntervalId) {
            clearInterval(state.syncIntervalId);
        }

        state.syncIntervalId = setInterval(syncUpcomingDraws, config.syncInterval);
        log(`Sync interval started (every ${config.syncInterval}ms)`);
    }

    /**
     * Start the bet counts interval
     */
    function startBetCountsInterval() {
        if (state.betCountsIntervalId) {
            clearInterval(state.betCountsIntervalId);
        }

        state.betCountsIntervalId = setInterval(fetchBetCounts, config.betCountsInterval);
        log(`Bet counts interval started (every ${config.betCountsInterval}ms)`);
    }

    /**
     * Handle storage events for immediate updates
     */
    function handleStorageEvent(event) {
        if (event.key === config.keys.upcomingDraws ||
            event.key === config.keys.upcomingDrawTimes) {

            log('Storage event detected', {
                key: event.key,
                oldValue: event.oldValue,
                newValue: event.newValue
            });

            // Reload data and update UI
            loadUpcomingDrawsFromLocalStorage();
            updateDisplay();
        } else if (event.key === config.keys.selectedDraw) {
            log('Selected draw changed in another tab', {
                oldValue: event.oldValue,
                newValue: event.newValue
            });

            // Update selected draw
            if (event.newValue) {
                state.selectedDrawNumber = parseInt(event.newValue);
                window.selectedDrawNumber = state.selectedDrawNumber;
            } else {
                state.selectedDrawNumber = null;
                window.selectedDrawNumber = null;
            }

            // Update UI
            updateDisplay();
            updateSelectionIndicator();
        }
    }

    /**
     * Fetch bet counts for upcoming draws
     */
    function fetchBetCounts() {
        if (!state.upcomingDraws || state.upcomingDraws.length === 0) {
            return;
        }

        // Prepare the request
        const drawNumbers = state.upcomingDraws.slice(0, config.maxDraws);

        // Make the request
        fetch(`php/get_draw_bet_counts.php?draw_numbers=${drawNumbers.join(',')}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Update bet counts
                    const betCounts = {};
                    data.data.forEach(item => {
                        betCounts[item.draw_number] = item.bet_count;
                    });

                    state.betCounts = betCounts;
                    log('Updated bet counts', betCounts);

                    // Update display
                    updateDisplay();
                } else {
                    error('Failed to fetch bet counts', data.message);
                }
            })
            .catch(err => {
                error('Error fetching bet counts', err);
            });
    }

    /**
     * Select a draw for betting
     * @param {number} drawNumber - The draw number to select
     */
    function selectDraw(drawNumber) {
        if (drawNumber === state.selectedDrawNumber) {
            // Already selected, deselect it
            resetToCurrentDraw();
            return;
        }

        // Update state
        state.selectedDrawNumber = drawNumber;

        // Save to localStorage
        localStorage.setItem(config.keys.selectedDraw, drawNumber.toString());

        // Set global variable for other scripts
        window.selectedDrawNumber = drawNumber;

        // Update UI
        updateDisplay();
        updateSelectionIndicator();

        // Dispatch event for other components
        const event = new CustomEvent('drawNumberSelected', {
            detail: { drawNumber }
        });
        document.dispatchEvent(event);

        log(`Selected draw #${drawNumber} for betting`);
    }

    /**
     * Reset to current draw
     */
    function resetToCurrentDraw() {
        // Update state
        state.selectedDrawNumber = null;

        // Remove from localStorage
        localStorage.removeItem(config.keys.selectedDraw);

        // Reset global variable
        window.selectedDrawNumber = null;

        // Update UI
        updateDisplay();
        updateSelectionIndicator();

        // Dispatch event for other components
        const event = new CustomEvent('drawNumberSelected', {
            detail: { drawNumber: null }
        });
        document.dispatchEvent(event);

        log('Reset to current draw');
    }

    /**
     * Update the selection indicator
     */
    function updateSelectionIndicator() {
        if (!state.selectionIndicator) return;

        if (state.selectedDrawNumber) {
            // Update text
            const textElement = state.selectionIndicator.querySelector('.indicator-text');
            if (textElement) {
                textElement.textContent = `Betting for Draw #${state.selectedDrawNumber}`;
            }

            // Show indicator
            state.selectionIndicator.classList.add('visible');
        } else {
            // Hide indicator
            state.selectionIndicator.classList.remove('visible');
        }
    }

    /**
     * Sync upcoming draws from localStorage
     */
    function syncUpcomingDraws() {
        // Check if we should use Georgetown time sync first
        if (window.GeorgetownTimeSync) {
            const currentDrawNumber = window.GeorgetownTimeSync.getCurrentDrawNumber();
            const nextDrawNumber = window.GeorgetownTimeSync.getNextDrawNumber();
            const upcomingDrawTimes = window.GeorgetownTimeSync.getUpcomingDrawTimes();

            if (currentDrawNumber && nextDrawNumber && upcomingDrawTimes && upcomingDrawTimes.detailed) {
                log('Using Georgetown time sync for upcoming draws', {
                    currentDrawNumber,
                    nextDrawNumber,
                    upcomingDrawTimes
                });

                // Generate upcoming draws based on the current draw number
                const upcomingDraws = [];
                const formattedTimes = [];

                for (let i = 0; i < Math.min(config.maxDraws, upcomingDrawTimes.detailed.length); i++) {
                    upcomingDraws.push(nextDrawNumber + i - 1); // Adjust to match current draw
                    formattedTimes.push(upcomingDrawTimes.detailed[i].formattedTime);
                }

                // Update our display with the new data
                updateDraws(upcomingDraws, formattedTimes);
                return;
            }
        }

        // Fall back to localStorage if Georgetown time sync is not available
        loadUpcomingDrawsFromLocalStorage();
        updateDisplay();
    }

    /**
     * Load upcoming draws from localStorage
     */
    function loadUpcomingDrawsFromLocalStorage() {
        try {
            // Load upcoming draws
            const upcomingDrawsStr = localStorage.getItem(config.keys.upcomingDraws);
            if (upcomingDrawsStr) {
                state.upcomingDraws = JSON.parse(upcomingDrawsStr);
                log('Loaded upcoming draws from localStorage:', state.upcomingDraws);
            }

            // Load upcoming draw times
            const upcomingDrawTimesStr = localStorage.getItem(config.keys.upcomingDrawTimes);
            if (upcomingDrawTimesStr) {
                state.upcomingDrawTimes = JSON.parse(upcomingDrawTimesStr);
                log('Loaded upcoming draw times from localStorage:', state.upcomingDrawTimes);
            }
        } catch (err) {
            error('Failed to load upcoming draws from localStorage', err);
        }
    }

    /**
     * Update the display with current data
     */
    function updateDisplay() {
        if (!state.container) return;

        const list = state.container.querySelector('.upcoming-draw-list');
        if (!list) return;

        // Clear the list
        list.innerHTML = '';

        // Store the current draw number
        if (state.upcomingDraws.length > 0) {
            state.currentDrawNumber = state.upcomingDraws[0];
        }

        // Add upcoming draws to the list
        const maxDraws = Math.min(config.maxDraws, state.upcomingDraws.length);
        for (let i = 0; i < maxDraws; i++) {
            const drawNumber = state.upcomingDraws[i];
            const drawTime = state.upcomingDrawTimes[i] || '';
            const betCount = state.betCounts[drawNumber] || 0;

            const item = document.createElement('li');
            item.className = 'upcoming-draw-item';
            item.dataset.drawNumber = drawNumber;

            // Add current class if this is the current draw
            if (i === 0) {
                item.classList.add('current');
            }

            // Add selected class if this is the selected draw
            if (drawNumber === state.selectedDrawNumber) {
                item.classList.add('selected');
            }

            // Create number span with bet count badge
            const numberSpan = document.createElement('span');
            numberSpan.className = 'upcoming-draw-number';
            numberSpan.textContent = `#${drawNumber}`;

            // Add bet count badge if there are bets
            if (betCount > 0) {
                const badge = document.createElement('span');
                badge.className = 'bet-count-badge has-bets';
                badge.textContent = betCount;
                numberSpan.appendChild(badge);
            } else {
                // Add empty badge for consistent spacing
                const badge = document.createElement('span');
                badge.className = 'bet-count-badge';
                badge.textContent = '0';
                numberSpan.appendChild(badge);
            }

            const timeSpan = document.createElement('span');
            timeSpan.className = 'upcoming-draw-time';
            timeSpan.textContent = drawTime;

            item.appendChild(numberSpan);
            item.appendChild(timeSpan);

            // Add click event to select this draw
            item.addEventListener('click', () => {
                selectDraw(drawNumber);
            });

            list.appendChild(item);
        }

        // Add reset button if a draw is selected
        if (state.selectedDrawNumber) {
            const resetButton = document.createElement('button');
            resetButton.className = 'reset-draw-button';
            resetButton.innerHTML = '<i class="fas fa-undo"></i> Reset to Current Draw';
            resetButton.addEventListener('click', resetToCurrentDraw);
            list.parentNode.appendChild(resetButton);
        } else {
            // Remove any existing reset button
            const existingButton = state.container.querySelector('.reset-draw-button');
            if (existingButton) {
                existingButton.remove();
            }
        }

        log('Updated upcoming draw display');
    }

    /**
     * Update draws with new data
     * @param {Array} drawNumbers - Array of draw numbers
     * @param {Array} drawTimes - Array of draw times
     */
    function updateDraws(drawNumbers, drawTimes) {
        if (!drawNumbers || !drawNumbers.length) {
            log('No draw numbers provided to updateDraws');
            return;
        }

        log('Updating draws with new data', { drawNumbers, drawTimes });

        // Update state
        state.upcomingDraws = drawNumbers;
        state.upcomingDrawTimes = drawTimes;

        // Save to localStorage
        try {
            localStorage.setItem(config.keys.upcomingDraws, JSON.stringify(drawNumbers));
            localStorage.setItem(config.keys.upcomingDrawTimes, JSON.stringify(drawTimes));
            log('Saved upcoming draws to localStorage');
        } catch (err) {
            error('Failed to save upcoming draws to localStorage', err);
        }

        // Update display
        updateDisplay();

        // Fetch bet counts for the new draws
        fetchBetCounts();
    }

    // Return public API
    return {
        initialize,
        showDisplay,
        hideDisplay,
        toggleMinimize,
        syncUpcomingDraws,
        selectDraw,
        resetToCurrentDraw,
        fetchBetCounts,
        updateDraws, // Add the new method to the public API
        getSelectedDraw: () => state.selectedDrawNumber,
        getCurrentDraw: () => state.currentDrawNumber
    };
})();

// Initialize when the document is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the Upcoming Draw Display
    UpcomingDrawDisplay.initialize({
        debug: true,
        syncInterval: 3000, // Check for updates every 3 seconds
        maxDraws: 10 // Maximum number of upcoming draws to display
    });

    // Make it available globally
    window.upcomingDrawDisplay = UpcomingDrawDisplay;

    console.log('UpcomingDrawDisplay initialized and assigned to window.upcomingDrawDisplay');

    // Generate initial draw data if none exists
    if (!localStorage.getItem('tv_display_upcoming_draws')) {
        console.log('No upcoming draws found in localStorage, generating initial data');

        // Get current draw number from GeorgetownTimeSync or SharedDrawTimer
        let currentDrawNumber = 100; // Default fallback

        if (window.GeorgetownTimeSync) {
            const nextDrawFromGeorgetown = window.GeorgetownTimeSync.getNextDrawNumber();
            if (nextDrawFromGeorgetown) {
                currentDrawNumber = nextDrawFromGeorgetown;
                console.log('Using next draw number from GeorgetownTimeSync:', currentDrawNumber);
            }
        } else if (window.SharedDrawTimer) {
            const nextDrawFromShared = window.SharedDrawTimer.getNextDrawNumber();
            if (nextDrawFromShared) {
                currentDrawNumber = nextDrawFromShared;
                console.log('Using next draw number from SharedDrawTimer:', currentDrawNumber);
            }
        }

        // Generate upcoming draws
        const upcomingDraws = [];
        const upcomingDrawTimes = [];

        for (let i = 0; i < 10; i++) {
            upcomingDraws.push(currentDrawNumber + i);
            const drawTime = new Date();
            drawTime.setSeconds(drawTime.getSeconds() + (i * 180)); // 3 minutes per draw
            upcomingDrawTimes.push(drawTime.toTimeString().substring(0, 8));
        }

        // Save to localStorage
        localStorage.setItem('tv_display_upcoming_draws', JSON.stringify(upcomingDraws));
        localStorage.setItem('tv_display_upcoming_draw_times', JSON.stringify(upcomingDrawTimes));

        console.log('Generated initial upcoming draws:', upcomingDraws);
        console.log('Generated initial upcoming draw times:', upcomingDrawTimes);

        // Update the display
        UpcomingDrawDisplay.updateDraws(upcomingDraws, upcomingDrawTimes);
    }
});
