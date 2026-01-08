/**
 * Cashier Draw Display Module
 * Provides a floating, movable panel showing current and upcoming draw numbers
 * Synchronized one-way from TV display to main cashier interface
 */

const CashierDrawDisplay = (function() {
    // Configuration
    const config = {
        debug: true,
        syncInterval: 5000, // Sync every 5 seconds
        apiEndpoint: 'php/get_last_completed_draw_details.php',
        fallbackEndpoint: 'api/tv_sync.php',
        storageKeys: {
            position: 'cashier_draw_display_position',
            collapsed: 'cashier_draw_display_collapsed',
            size: 'cashier_draw_display_size'
        }
    };

    // State
    let state = {
        initialized: false,
        currentDraw: null,
        nextDraw: null,
        lastCompletedDraw: null,
        upcomingDraw: null,
        lastSyncTime: null,
        syncStatus: 'disconnected', // 'active', 'syncing', 'error', 'disconnected'
        syncIntervalId: null,
        isCollapsed: false,
        isDragging: false,
        isResizing: false,
        lastDrawDetailsData: null // Store last draw details for comparison
    };

    // DOM elements
    let elements = {
        container: null,
        upcomingNumber: null,
        completedNumber: null,
        statusIndicator: null,
        syncStatus: null,
        syncTime: null,
        toggleButton: null,
        winningNumberCircle: null,
        lastWinningSlips: null,
        lastTotalSlips: null,
        lastWinRate: null,
        winRateFill: null,
        lastDrawDetails: null
    };

    /**
     * Log debug messages
     */
    function log(...args) {
        if (config.debug) {
            console.log('[CashierDrawDisplay]', ...args);
        }
    }

    /**
     * Initialize the module
     */
    function init() {
        if (state.initialized) return;

        log('Initializing Cashier Draw Display');

        createDisplayElement();
        setupEventListeners();
        loadSavedSettings();
        startSyncInterval();

        state.initialized = true;
        log('Cashier Draw Display initialized');
    }

    /**
     * Create the main display element
     */
    function createDisplayElement() {
        const container = document.createElement('div');
        container.className = 'cashier-draw-display';
        container.innerHTML = `
            <div class="cashier-draw-header">
                <div class="cashier-draw-title">
                    <i class="fas fa-hashtag"></i>
                    DRAW NUMBERS
                </div>
                <div class="cashier-draw-controls">
                    <button class="cashier-draw-control toggle-btn" title="Collapse/Expand">
                        <i class="fas fa-chevron-up"></i>
                    </button>
                </div>
            </div>
            <div class="cashier-draw-content">
                <div class="cashier-draw-section">
                    <div class="cashier-draw-label upcoming">
                        <i class="fas fa-arrow-right"></i>
                        <span class="cashier-status-indicator active"></span>
                        NEXT BETTING SLIP DRAW
                    </div>
                    <div class="cashier-draw-number upcoming" id="upcoming-draw-number">
                        #---
                    </div>
                    <div class="cashier-draw-info highlight">
                        New betting slips will be assigned to this draw
                    </div>
                </div>

                <div class="cashier-draw-section">
                    <div class="cashier-draw-label completed">
                        <i class="fas fa-check-circle"></i>
                        LAST COMPLETED DRAW
                    </div>
                    <div class="cashier-draw-number completed" id="completed-draw-number">
                        #---
                    </div>
                    <div class="cashier-draw-info">
                        Most recent draw with results
                    </div>

                    <!-- Enhanced draw details -->
                    <div class="cashier-draw-details" id="last-draw-details" style="display: none;">
                        <div class="cashier-winning-number-section">
                            <div class="cashier-winning-label">Winning Number:</div>
                            <div class="number-circle" id="winning-number-circle">--</div>
                        </div>

                        <div class="cashier-slips-info">
                            <div class="cashier-slips-row">
                                <span class="cashier-slips-label">Total Slips:</span>
                                <span class="cashier-total-slips" id="last-total-slips">0</span>
                            </div>
                            <div class="cashier-slips-row">
                                <span class="cashier-slips-label">Winning Slips:</span>
                                <span class="cashier-winning-slips" id="last-winning-slips">0</span>
                            </div>
                            <div class="cashier-slips-row">
                                <span class="cashier-slips-label">Win Rate:</span>
                                <span class="cashier-win-rate" id="last-win-rate">0%</span>
                            </div>
                        </div>

                        <div class="cashier-win-rate-bar">
                            <div class="cashier-win-rate-fill" id="win-rate-fill" style="width: 0%;"></div>
                        </div>
                    </div>
                </div>

                <div class="cashier-sync-status">
                    <div>
                        <span class="cashier-status-indicator syncing"></span>
                        Status: <span id="sync-status">Syncing...</span>
                    </div>
                    <div class="cashier-sync-time" id="sync-time">
                        Last sync: 3:21:42 pm
                    </div>
                </div>
            </div>

            <!-- Resize handles -->
            <div class="cashier-resize-handle cashier-resize-se"></div>
            <div class="cashier-resize-handle cashier-resize-e"></div>
            <div class="cashier-resize-handle cashier-resize-s"></div>
        `;

        document.body.appendChild(container);

        // Store element references
        elements.container = container;
        elements.upcomingNumber = container.querySelector('#upcoming-draw-number');
        elements.completedNumber = container.querySelector('#completed-draw-number');
        elements.statusIndicator = container.querySelector('.cashier-status-indicator');
        elements.syncStatus = container.querySelector('#sync-status');
        elements.syncTime = container.querySelector('#sync-time');
        elements.toggleButton = container.querySelector('.toggle-btn');
        elements.winningNumberCircle = container.querySelector('#winning-number-circle');
        elements.lastWinningSlips = container.querySelector('#last-winning-slips');
        elements.lastTotalSlips = container.querySelector('#last-total-slips');
        elements.lastWinRate = container.querySelector('#last-win-rate');
        elements.winRateFill = container.querySelector('#win-rate-fill');
        elements.lastDrawDetails = container.querySelector('#last-draw-details');

        log('Enhanced display element created');
    }

    /**
     * Setup event listeners
     */
    function setupEventListeners() {
        // Toggle collapse/expand
        elements.toggleButton.addEventListener('click', toggleCollapse);

        // Make draggable
        makeDraggable();

        // Make resizable
        makeResizable();

        // Listen for draw number changes from other modules
        document.addEventListener('drawNumbersUpdated', handleDrawNumbersUpdated);

        // Listen for TV display sync events
        document.addEventListener('tvDisplaySync', handleTVDisplaySync);

        log('Event listeners setup complete');
    }

    /**
     * Make the display draggable
     */
    function makeDraggable() {
        const header = elements.container.querySelector('.cashier-draw-header');
        let isDragging = false;
        let startX, startY, startLeft, startTop;

        header.addEventListener('mousedown', (e) => {
            isDragging = true;
            state.isDragging = true;
            elements.container.classList.add('dragging');

            startX = e.clientX;
            startY = e.clientY;
            startLeft = elements.container.offsetLeft;
            startTop = elements.container.offsetTop;

            e.preventDefault();
        });

        document.addEventListener('mousemove', (e) => {
            if (!isDragging) return;

            const deltaX = e.clientX - startX;
            const deltaY = e.clientY - startY;

            elements.container.style.left = (startLeft + deltaX) + 'px';
            elements.container.style.top = (startTop + deltaY) + 'px';
            elements.container.style.right = 'auto';
        });

        document.addEventListener('mouseup', () => {
            if (isDragging) {
                isDragging = false;
                state.isDragging = false;
                elements.container.classList.remove('dragging');
                savePosition();
            }
        });
    }

    /**
     * Make the display resizable
     */
    function makeResizable() {
        // Implementation for resize functionality
        // This is a simplified version - full implementation would handle all resize directions
        const resizeHandle = elements.container.querySelector('.cashier-resize-se');

        resizeHandle.addEventListener('mousedown', (e) => {
            state.isResizing = true;
            e.preventDefault();
            e.stopPropagation();

            const startX = e.clientX;
            const startY = e.clientY;
            const startWidth = elements.container.offsetWidth;
            const startHeight = elements.container.offsetHeight;

            const handleMouseMove = (e) => {
                if (!state.isResizing) return;

                const deltaX = e.clientX - startX;
                const deltaY = e.clientY - startY;

                elements.container.style.width = (startWidth + deltaX) + 'px';
                elements.container.style.height = (startHeight + deltaY) + 'px';
            };

            const handleMouseUp = () => {
                state.isResizing = false;
                document.removeEventListener('mousemove', handleMouseMove);
                document.removeEventListener('mouseup', handleMouseUp);
                saveSize();
            };

            document.addEventListener('mousemove', handleMouseMove);
            document.addEventListener('mouseup', handleMouseUp);
        });
    }

    /**
     * Toggle collapse/expand state
     */
    function toggleCollapse() {
        state.isCollapsed = !state.isCollapsed;
        elements.container.classList.toggle('collapsed', state.isCollapsed);

        const icon = elements.toggleButton.querySelector('i');
        icon.className = state.isCollapsed ? 'fas fa-chevron-down' : 'fas fa-chevron-up';

        saveCollapsedState();
        log('Display', state.isCollapsed ? 'collapsed' : 'expanded');
    }

    /**
     * Start the sync interval
     */
    function startSyncInterval() {
        if (state.syncIntervalId) {
            clearInterval(state.syncIntervalId);
        }

        // Initial sync
        syncDrawNumbers();

        // Set up interval
        state.syncIntervalId = setInterval(syncDrawNumbers, config.syncInterval);
        log('Sync interval started');
    }

    /**
     * Sync draw numbers and enhanced details
     */
    async function syncDrawNumbers() {
        try {
            updateSyncStatus('syncing');

            // Try Firebase first if available
            if (window.FirebaseService && window.FirebaseService.isOnline()) {
                try {
                    log('Fetching draw data from Firebase...');
                    
                    // Get draw info from Firebase
                    const drawInfo = await window.FirebaseService.GameState.getDrawInfo();
                    const gameState = await window.FirebaseService.GameState.getCurrent();
                    
                    let currentDraw = 0;
                    let lastCompletedDraw = 0;
                    
                    if (drawInfo && drawInfo.currentDraw) {
                        currentDraw = drawInfo.currentDraw;
                        lastCompletedDraw = currentDraw;
                    } else if (gameState && gameState.drawNumber) {
                        currentDraw = gameState.drawNumber;
                        lastCompletedDraw = currentDraw;
                    }
                    
                    // Get last draw details from Firebase
                    let lastDrawDetails = null;
                    if (lastCompletedDraw > 0) {
                        try {
                            const lastDraw = await window.FirebaseService.Draws.getDraw(lastCompletedDraw);
                            if (lastDraw) {
                                lastDrawDetails = {
                                    draw_number: lastCompletedDraw,
                                    winning_number: lastDraw.winningNumber || null,
                                    winning_number_color: lastDraw.color || null,
                                    total_slips: lastDraw.totalSlips || 0,
                                    winning_slips: lastDraw.winningSlips || 0
                                };
                            }
                        } catch (e) {
                            log('Error fetching last draw details:', e);
                        }
                    }
                    
                    if (currentDraw > 0) {
                        const firebaseData = {
                            draw_number: lastCompletedDraw,
                            last_completed_draw: lastCompletedDraw,
                            next_draw_for_betting: currentDraw + 1,
                            upcoming_draw: currentDraw + 1,
                            ...lastDrawDetails
                        };
                        
                        processDrawData(firebaseData);
                        updateLastCompletedDrawDetails(firebaseData);
                        updateSyncStatus('active');
                        log('Successfully synced from Firebase:', firebaseData);
                        return;
                    }
                } catch (firebaseError) {
                    log('Firebase sync failed, trying API fallback:', firebaseError);
                }
            }

            // Fallback to PHP API if Firebase is not available
            const response = await fetch(config.apiEndpoint + '?_cb=' + Date.now());

            if (!response.ok) {
                throw new Error('Failed to fetch draw data');
            }

            const data = await response.json();

            if (data.status === 'success' && data.data) {
                processDrawData(data.data);
                updateLastCompletedDrawDetails(data.data);
                updateSyncStatus('active');
            } else {
                throw new Error(data.message || 'Invalid response format');
            }

        } catch (error) {
            log('Sync error:', error);
            updateSyncStatus('error');
        }
    }

    /**
     * Process draw data from API or Firebase
     */
    function processDrawData(data) {
        // Handle enhanced API response format or Firebase data
        const lastCompletedDraw = data.draw_number || data.last_completed_draw || data.current_completed_draw || 0;
        const upcomingDraw = data.upcoming_draw || data.next_draw_for_betting || (lastCompletedDraw ? lastCompletedDraw + 1 : 1);

        // Update state
        const hasChanges = (
            state.lastCompletedDraw !== lastCompletedDraw ||
            state.upcomingDraw !== upcomingDraw
        );

        state.currentDraw = lastCompletedDraw; // For compatibility
        state.nextDraw = upcomingDraw; // For compatibility
        state.lastCompletedDraw = lastCompletedDraw;
        state.upcomingDraw = upcomingDraw;
        state.lastSyncTime = new Date();

        // Update UI
        updateDrawNumbers(hasChanges);
        updateSyncTime();

        if (hasChanges) {
            log('Draw numbers updated:', { lastCompleted: lastCompletedDraw, upcoming: upcomingDraw });

            // Dispatch event for other modules
            document.dispatchEvent(new CustomEvent('cashierDrawNumbersUpdated', {
                detail: {
                    currentDraw: lastCompletedDraw,
                    nextDraw: upcomingDraw,
                    lastCompletedDraw: lastCompletedDraw,
                    upcomingDraw: upcomingDraw
                }
            }));
        }
    }

    /**
     * Update draw numbers in UI
     */
    function updateDrawNumbers(animate = false) {
        if (elements.upcomingNumber && state.upcomingDraw) {
            elements.upcomingNumber.textContent = `#${state.upcomingDraw}`;
            if (animate) {
                elements.upcomingNumber.classList.add('updated');
                setTimeout(() => elements.upcomingNumber.classList.remove('updated'), 800);
            }
        }

        if (elements.completedNumber && state.lastCompletedDraw) {
            elements.completedNumber.textContent = `#${state.lastCompletedDraw}`;
            if (animate) {
                elements.completedNumber.classList.add('updated');
                setTimeout(() => elements.completedNumber.classList.remove('updated'), 800);
            }
        }
    }

    /**
     * Update sync status
     */
    function updateSyncStatus(status) {
        state.syncStatus = status;

        if (elements.statusIndicator) {
            elements.statusIndicator.className = `cashier-status-indicator ${status}`;
        }

        if (elements.syncStatus) {
            const statusText = {
                'active': 'Connected',
                'syncing': 'Syncing...',
                'error': 'Connection Error',
                'disconnected': 'Disconnected'
            };
            elements.syncStatus.textContent = statusText[status] || status;
        }
    }

    /**
     * Update sync time display
     */
    function updateSyncTime() {
        if (elements.syncTime && state.lastSyncTime) {
            const timeStr = state.lastSyncTime.toLocaleTimeString();
            elements.syncTime.textContent = `Last sync: ${timeStr}`;
        }
    }

    /**
     * Update last completed draw details with winning number and slips info
     */
    function updateLastCompletedDrawDetails(data) {
        if (!data) return;

        log('Updating last completed draw details:', data);

        // Check if this is new data (different draw number)
        const isNewData = state.lastDrawDetailsData?.draw_number !== data.draw_number;

        // Store the current data for comparison
        state.lastDrawDetailsData = data;

        // Update winning number with animation for new data
        if (elements.winningNumberCircle && data.winning_number !== null) {
            elements.winningNumberCircle.textContent = data.winning_number;
            elements.winningNumberCircle.className = `number-circle number-${data.winning_number_color}`;

            // Add update animation for new data
            if (isNewData) {
                elements.winningNumberCircle.classList.add('updated');
                setTimeout(() => elements.winningNumberCircle.classList.remove('updated'), 800);
            }
        } else if (elements.winningNumberCircle) {
            elements.winningNumberCircle.textContent = '--';
            elements.winningNumberCircle.className = 'number-circle';
        }

        // Update winning slips count with animation
        if (elements.lastWinningSlips) {
            const newValue = data.winning_slips || 0;
            elements.lastWinningSlips.textContent = newValue;

            if (isNewData && newValue > 0) {
                elements.lastWinningSlips.classList.add('updated');
                setTimeout(() => elements.lastWinningSlips.classList.remove('updated'), 800);
            }
        }

        // Update total slips count with animation
        if (elements.lastTotalSlips) {
            const newValue = data.total_slips || 0;
            elements.lastTotalSlips.textContent = newValue;

            if (isNewData && newValue > 0) {
                elements.lastTotalSlips.classList.add('updated');
                setTimeout(() => elements.lastTotalSlips.classList.remove('updated'), 800);
            }
        }

        // Update win rate
        if (elements.lastWinRate) {
            elements.lastWinRate.textContent = `${data.win_percentage || 0}%`;
        }

        // Update win rate bar with smooth transition
        if (elements.winRateFill) {
            const percentage = data.win_percentage || 0;
            elements.winRateFill.style.width = `${percentage}%`;

            // Color the bar based on win rate
            if (percentage >= 50) {
                elements.winRateFill.style.backgroundColor = '#28a745'; // Green for high win rate
            } else if (percentage >= 25) {
                elements.winRateFill.style.backgroundColor = '#ffc107'; // Yellow for medium win rate
            } else {
                elements.winRateFill.style.backgroundColor = '#dc3545'; // Red for low win rate
            }
        }

        // Show the details section if we have data
        if (elements.lastDrawDetails && data.draw_number) {
            elements.lastDrawDetails.style.display = 'block';

            // Add a subtle highlight effect for new data
            if (isNewData) {
                elements.lastDrawDetails.classList.add('updated');
                setTimeout(() => elements.lastDrawDetails.classList.remove('updated'), 1000);
            }
        }
    }

    /**
     * Handle draw numbers updated event from other modules
     */
    function handleDrawNumbersUpdated(event) {
        if (event.detail) {
            processDrawData(event.detail);
        }
    }

    /**
     * Handle TV display sync event
     */
    function handleTVDisplaySync(event) {
        if (event.detail) {
            processDrawData(event.detail);
        }
    }

    /**
     * Save position to localStorage
     */
    function savePosition() {
        const position = {
            left: elements.container.style.left,
            top: elements.container.style.top,
            right: elements.container.style.right
        };
        localStorage.setItem(config.storageKeys.position, JSON.stringify(position));
    }

    /**
     * Save collapsed state to localStorage
     */
    function saveCollapsedState() {
        localStorage.setItem(config.storageKeys.collapsed, state.isCollapsed.toString());
    }

    /**
     * Save size to localStorage
     */
    function saveSize() {
        const size = {
            width: elements.container.style.width,
            height: elements.container.style.height
        };
        localStorage.setItem(config.storageKeys.size, JSON.stringify(size));
    }

    /**
     * Load saved settings from localStorage
     */
    function loadSavedSettings() {
        // Load position
        try {
            const savedPosition = localStorage.getItem(config.storageKeys.position);
            if (savedPosition) {
                const position = JSON.parse(savedPosition);
                if (position.left) elements.container.style.left = position.left;
                if (position.top) elements.container.style.top = position.top;
                if (position.right) elements.container.style.right = position.right;
            }
        } catch (e) {
            log('Error loading saved position:', e);
        }

        // Load collapsed state
        try {
            const savedCollapsed = localStorage.getItem(config.storageKeys.collapsed);
            if (savedCollapsed === 'true') {
                state.isCollapsed = true;
                elements.container.classList.add('collapsed');
                elements.toggleButton.querySelector('i').className = 'fas fa-chevron-down';
            }
        } catch (e) {
            log('Error loading collapsed state:', e);
        }

        // Load size
        try {
            const savedSize = localStorage.getItem(config.storageKeys.size);
            if (savedSize) {
                const size = JSON.parse(savedSize);
                if (size.width) elements.container.style.width = size.width;
                if (size.height) elements.container.style.height = size.height;
            }
        } catch (e) {
            log('Error loading saved size:', e);
        }
    }

    /**
     * Get current draw numbers
     */
    function getDrawNumbers() {
        return {
            currentDraw: state.currentDraw,
            nextDraw: state.nextDraw,
            lastCompletedDraw: state.lastCompletedDraw,
            upcomingDraw: state.upcomingDraw
        };
    }

    /**
     * Force a sync
     */
    function forceSync() {
        log('Forcing sync...');
        syncDrawNumbers();
    }

    /**
     * Destroy the module
     */
    function destroy() {
        if (state.syncIntervalId) {
            clearInterval(state.syncIntervalId);
        }

        if (elements.container) {
            elements.container.remove();
        }

        state.initialized = false;
        log('Cashier Draw Display destroyed');
    }

    // Public API
    return {
        init,
        destroy,
        getDrawNumbers,
        forceSync,
        toggleCollapse,
        // Configuration
        setConfig: (newConfig) => Object.assign(config, newConfig)
    };
})();

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Small delay to ensure other modules are loaded
    setTimeout(() => {
        CashierDrawDisplay.init();
    }, 500);
});

// Export for global access
window.CashierDrawDisplay = CashierDrawDisplay;
