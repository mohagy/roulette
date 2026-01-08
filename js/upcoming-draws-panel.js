/**
 * Upcoming Draws Panel Module
 * Provides a floating panel showing 10 upcoming draws with selection capability
 * and betting slip statistics for each draw
 */

const UpcomingDrawsPanel = (function() {
    // Configuration
    const config = {
        debug: true,
        syncInterval: 3000, // Sync every 3 seconds
        apiEndpoint: 'api/upcoming_draws_stats.php',
        fallbackEndpoint: 'api/cashier_draw_sync.php',
        drawCount: 10, // Number of upcoming draws to show
        storageKeys: {
            position: 'upcoming_draws_panel_position',
            collapsed: 'upcoming_draws_panel_collapsed',
            size: 'upcoming_draws_panel_size',
            selectedDraw: 'upcoming_draws_selected_draw'
        }
    };

    // State
    let state = {
        initialized: false,
        upcomingDraws: [],
        selectedDrawNumber: null,
        lastSyncTime: null,
        syncStatus: 'disconnected',
        syncIntervalId: null,
        isCollapsed: false,
        isDragging: false,
        isResizing: false,
        baseDrawNumber: null
    };

    // DOM elements
    let elements = {
        container: null,
        drawsList: null,
        selectedIndicator: null,
        toggleButton: null,
        refreshButton: null
    };

    /**
     * Log debug messages
     */
    function log(...args) {
        if (config.debug) {
            console.log('[UpcomingDrawsPanel]', ...args);
        }
    }

    /**
     * Initialize the module
     */
    function init() {
        if (state.initialized) return;

        log('Initializing Upcoming Draws Panel');

        createPanelElement();
        setupEventListeners();
        loadSavedSettings();
        startSyncInterval();

        state.initialized = true;
        log('Upcoming Draws Panel initialized');
    }

    /**
     * Create the main panel element
     */
    function createPanelElement() {
        const container = document.createElement('div');
        container.className = 'upcoming-draws-panel';
        container.innerHTML = `
            <div class="upcoming-draws-header">
                <div class="upcoming-draws-title">
                    <i class="fas fa-calendar-alt"></i>
                    Upcoming Draws
                </div>
                <div class="upcoming-draws-controls">
                    <button class="upcoming-draws-control refresh-btn" title="Refresh">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                    <button class="upcoming-draws-control toggle-btn" title="Collapse/Expand">
                        <i class="fas fa-chevron-up"></i>
                    </button>
                </div>
            </div>
            <div class="upcoming-draws-content">
                <div class="selected-draw-indicator" id="selected-draw-indicator">
                    <i class="fas fa-target"></i>
                    <span>No draw selected - Click to select</span>
                </div>
                <div class="upcoming-draws-list" id="upcoming-draws-list">
                    <div class="upcoming-draws-loading">
                        <i class="fas fa-spinner"></i>
                        <div>Loading upcoming draws...</div>
                    </div>
                </div>
            </div>

            <!-- Resize handles -->
            <div class="upcoming-resize-handle upcoming-resize-se"></div>
            <div class="upcoming-resize-handle upcoming-resize-e"></div>
            <div class="upcoming-resize-handle upcoming-resize-s"></div>
        `;

        document.body.appendChild(container);

        // Store element references
        elements.container = container;
        elements.drawsList = container.querySelector('#upcoming-draws-list');
        elements.selectedIndicator = container.querySelector('#selected-draw-indicator');
        elements.toggleButton = container.querySelector('.toggle-btn');
        elements.refreshButton = container.querySelector('.refresh-btn');

        log('Panel element created');
    }

    /**
     * Setup event listeners
     */
    function setupEventListeners() {
        // Toggle collapse/expand
        elements.toggleButton.addEventListener('click', toggleCollapse);

        // Refresh button
        elements.refreshButton.addEventListener('click', forceSync);

        // Make draggable
        makeDraggable();

        // Make resizable
        makeResizable();

        // Listen for draw selection events
        elements.drawsList.addEventListener('click', handleDrawSelection);

        // Listen for betting slip events
        document.addEventListener('bettingSlipCreated', handleBettingSlipCreated);
        document.addEventListener('beforeBettingSlipCreation', handleBeforeBettingSlipCreation);

        log('Event listeners setup complete');
    }

    /**
     * Make the panel draggable
     */
    function makeDraggable() {
        const header = elements.container.querySelector('.upcoming-draws-header');
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
     * Make the panel resizable
     */
    function makeResizable() {
        const resizeHandle = elements.container.querySelector('.upcoming-resize-se');

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
        log('Panel', state.isCollapsed ? 'collapsed' : 'expanded');
    }

    /**
     * Start the sync interval
     */
    function startSyncInterval() {
        if (state.syncIntervalId) {
            clearInterval(state.syncIntervalId);
        }

        // Initial sync
        syncUpcomingDraws();

        // Set up interval
        state.syncIntervalId = setInterval(syncUpcomingDraws, config.syncInterval);
        log('Sync interval started');
    }

    /**
     * Sync upcoming draws data
     */
    async function syncUpcomingDraws() {
        try {
            state.syncStatus = 'syncing';
            updateRefreshButton(true);

            // Try primary endpoint first
            let response = await fetch(config.apiEndpoint + '?_cb=' + Date.now());
            let responseText = '';

            if (!response.ok) {
                log('Primary endpoint failed with status:', response.status);
                // Try fallback endpoint and generate upcoming draws
                response = await fetch(config.fallbackEndpoint + '?_cb=' + Date.now());

                if (response.ok) {
                    responseText = await response.text();
                    try {
                        const fallbackData = JSON.parse(responseText);
                        if (fallbackData.status === 'success' && fallbackData.data) {
                            const generatedDraws = generateUpcomingDraws(fallbackData.data);
                            processUpcomingDrawsData(generatedDraws);
                            state.syncStatus = 'active';
                            updateRefreshButton(false);
                            return;
                        }
                    } catch (parseError) {
                        log('Fallback endpoint returned invalid JSON:', responseText.substring(0, 200));
                        throw new Error('Fallback API returned invalid response');
                    }
                }

                throw new Error('Both primary and fallback endpoints failed');
            }

            // Get response text first to handle parsing errors better
            responseText = await response.text();

            try {
                const data = JSON.parse(responseText);

                if (data.status === 'success' && data.data) {
                    processUpcomingDrawsData(data.data);
                    state.syncStatus = 'active';
                } else {
                    throw new Error(data.message || 'Invalid response format');
                }
            } catch (parseError) {
                log('Primary endpoint returned invalid JSON:', responseText.substring(0, 200));

                // Check if it's a PHP error
                if (responseText.includes('<br />') || responseText.includes('Fatal error') || responseText.includes('Parse error')) {
                    throw new Error('API server error - check server logs');
                } else {
                    throw new Error('Invalid JSON response from server');
                }
            }

        } catch (error) {
            log('Sync error:', error);
            state.syncStatus = 'error';

            // Try to generate basic upcoming draws as last resort
            try {
                log('Attempting fallback draw generation...');
                const basicDraws = generateBasicUpcomingDraws();
                if (basicDraws.upcoming_draws.length > 0) {
                    processUpcomingDrawsData(basicDraws);
                    state.syncStatus = 'active';
                    log('Fallback draw generation successful');
                    return;
                }
            } catch (fallbackError) {
                log('Fallback generation failed:', fallbackError);
            }

            showError('Failed to load upcoming draws: ' + error.message);
        } finally {
            updateRefreshButton(false);
        }
    }

    /**
     * Generate upcoming draws from base data
     */
    function generateUpcomingDraws(baseData) {
        const lastCompletedDraw = baseData.last_completed_draw || baseData.current_completed_draw || 0;
        const upcomingDraws = [];

        for (let i = 1; i <= config.drawCount; i++) {
            const drawNumber = lastCompletedDraw + i;
            const drawTime = new Date();
            drawTime.setMinutes(drawTime.getMinutes() + (i * 3)); // 3 minutes apart

            upcomingDraws.push({
                draw_number: drawNumber,
                estimated_time: drawTime.toTimeString().substring(0, 5),
                betting_slips_count: 0,
                total_stake_amount: 0,
                is_next: i === 1
            });
        }

        return {
            upcoming_draws: upcomingDraws,
            base_draw: lastCompletedDraw
        };
    }

    /**
     * Generate basic upcoming draws as last resort fallback
     */
    function generateBasicUpcomingDraws() {
        log('Generating basic upcoming draws as fallback...');

        // Try to get base draw from localStorage or use default
        let baseDraw = 0;
        try {
            const storedDraw = localStorage.getItem('cashier_current_draw');
            if (storedDraw) {
                baseDraw = parseInt(storedDraw) || 0;
            }
        } catch (e) {
            // Use default
        }

        // If still 0, use a reasonable default
        if (baseDraw === 0) {
            baseDraw = 100; // Default starting point
        }

        const upcomingDraws = [];

        for (let i = 1; i <= config.drawCount; i++) {
            const drawNumber = baseDraw + i;
            const drawTime = new Date();
            drawTime.setMinutes(drawTime.getMinutes() + (i * 3)); // 3 minutes apart

            upcomingDraws.push({
                draw_number: drawNumber,
                estimated_time: drawTime.toTimeString().substring(0, 5),
                betting_slips_count: 0,
                total_stake_amount: 0.00,
                is_next: i === 1,
                is_fallback: true // Mark as fallback data
            });
        }

        return {
            upcoming_draws: upcomingDraws,
            base_draw: baseDraw,
            is_fallback: true
        };
    }

    /**
     * Process upcoming draws data
     */
    function processUpcomingDrawsData(data) {
        const upcomingDraws = data.upcoming_draws || [];

        // Update state
        const hasChanges = JSON.stringify(state.upcomingDraws) !== JSON.stringify(upcomingDraws);

        state.upcomingDraws = upcomingDraws;
        state.baseDrawNumber = data.base_draw;
        state.lastSyncTime = new Date();

        // Update UI
        renderUpcomingDraws(hasChanges);

        if (hasChanges) {
            log('Upcoming draws updated:', upcomingDraws.length, 'draws');

            // Dispatch event for other modules
            document.dispatchEvent(new CustomEvent('upcomingDrawsUpdated', {
                detail: {
                    upcomingDraws: upcomingDraws,
                    selectedDraw: state.selectedDrawNumber,
                    baseDraw: state.baseDrawNumber
                }
            }));
        }
    }

    /**
     * Render upcoming draws in the UI
     */
    function renderUpcomingDraws(animate = false) {
        if (!elements.drawsList) return;

        if (state.upcomingDraws.length === 0) {
            elements.drawsList.innerHTML = `
                <div class="upcoming-draws-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>No upcoming draws available</div>
                    <button class="refresh-button" onclick="UpcomingDrawsPanel.forceSync()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            `;
            return;
        }

        const drawsHTML = state.upcomingDraws.map((draw, index) => {
            const isSelected = draw.draw_number === state.selectedDrawNumber;
            const isNext = draw.is_next || index === 0;
            const isFallback = draw.is_fallback || false;

            return `
                <div class="upcoming-draw-item ${isSelected ? 'selected' : ''} ${isNext ? 'next-draw' : ''} ${animate ? 'updated' : ''}"
                     data-draw-number="${draw.draw_number}">
                    <div class="selection-indicator"></div>
                    <div class="draw-item-header">
                        <div class="draw-number">#${draw.draw_number}${isFallback ? ' <small style="color: #ffc107;">(Est.)</small>' : ''}</div>
                        <div class="draw-time">${draw.estimated_time || 'TBD'}</div>
                    </div>
                    <div class="draw-item-stats">
                        <div class="draw-stats-left">
                            <div class="draw-stat slips">
                                <i class="fas fa-receipt"></i>
                                <span class="draw-stat-value">${draw.betting_slips_count || 0}</span>
                                <span>slips</span>
                            </div>
                            <div class="draw-stat amount">
                                <i class="fas fa-dollar-sign"></i>
                                <span class="draw-stat-value">$${(draw.total_stake_amount || 0).toFixed(2)}</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        elements.drawsList.innerHTML = drawsHTML;

        // Update selected draw indicator
        updateSelectedDrawIndicator();
    }

    /**
     * Handle draw selection
     */
    function handleDrawSelection(event) {
        const drawItem = event.target.closest('.upcoming-draw-item');
        if (!drawItem) return;

        const drawNumber = parseInt(drawItem.dataset.drawNumber);

        // Remove previous selection
        elements.drawsList.querySelectorAll('.upcoming-draw-item').forEach(item => {
            item.classList.remove('selected');
        });

        // Add selection to clicked item
        drawItem.classList.add('selected');

        // Update state
        state.selectedDrawNumber = drawNumber;
        saveSelectedDraw();

        // Update indicator
        updateSelectedDrawIndicator();

        log('Selected draw:', drawNumber);

        // Dispatch event
        document.dispatchEvent(new CustomEvent('drawSelected', {
            detail: {
                drawNumber: drawNumber,
                drawData: state.upcomingDraws.find(d => d.draw_number === drawNumber)
            }
        }));
    }

    /**
     * Update selected draw indicator
     */
    function updateSelectedDrawIndicator() {
        if (!elements.selectedIndicator) return;

        if (state.selectedDrawNumber) {
            const selectedDraw = state.upcomingDraws.find(d => d.draw_number === state.selectedDrawNumber);
            elements.selectedIndicator.innerHTML = `
                <i class="fas fa-target"></i>
                <span>Selected: Draw #${state.selectedDrawNumber}${selectedDraw && selectedDraw.is_next ? ' (Next)' : ''}</span>
            `;
        } else {
            elements.selectedIndicator.innerHTML = `
                <i class="fas fa-hand-pointer"></i>
                <span>Click a draw below to select it for betting</span>
            `;
        }
    }

    /**
     * Handle before betting slip creation
     */
    function handleBeforeBettingSlipCreation(event) {
        if (state.selectedDrawNumber && event.detail) {
            event.detail.drawNumber = state.selectedDrawNumber;
            log('Set betting slip draw number to selected:', state.selectedDrawNumber);
        }
    }

    /**
     * Handle betting slip created
     */
    function handleBettingSlipCreated(event) {
        if (event.detail && event.detail.drawNumber) {
            log('Betting slip created for draw:', event.detail.drawNumber);

            // Refresh data to update slip counts
            setTimeout(() => {
                syncUpcomingDraws();
            }, 1000);
        }
    }

    /**
     * Show error message
     */
    function showError(message) {
        if (elements.drawsList) {
            elements.drawsList.innerHTML = `
                <div class="upcoming-draws-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>${message}</div>
                    <button class="refresh-button" onclick="UpcomingDrawsPanel.forceSync()">
                        <i class="fas fa-sync-alt"></i> Retry
                    </button>
                </div>
            `;
        }
    }

    /**
     * Update refresh button state
     */
    function updateRefreshButton(isLoading) {
        if (elements.refreshButton) {
            const icon = elements.refreshButton.querySelector('i');
            if (isLoading) {
                icon.className = 'fas fa-spinner';
                icon.style.animation = 'upcoming-spin 1s linear infinite';
            } else {
                icon.className = 'fas fa-sync-alt';
                icon.style.animation = '';
            }
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
     * Save selected draw to localStorage
     */
    function saveSelectedDraw() {
        localStorage.setItem(config.storageKeys.selectedDraw, state.selectedDrawNumber?.toString() || '');
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

        // Load selected draw
        try {
            const savedSelectedDraw = localStorage.getItem(config.storageKeys.selectedDraw);
            if (savedSelectedDraw && savedSelectedDraw !== '') {
                state.selectedDrawNumber = parseInt(savedSelectedDraw);
            }
        } catch (e) {
            log('Error loading selected draw:', e);
        }
    }

    /**
     * Get selected draw number
     */
    function getSelectedDraw() {
        return state.selectedDrawNumber;
    }

    /**
     * Set selected draw number
     */
    function setSelectedDraw(drawNumber) {
        state.selectedDrawNumber = drawNumber;
        saveSelectedDraw();

        // Update UI
        elements.drawsList.querySelectorAll('.upcoming-draw-item').forEach(item => {
            item.classList.toggle('selected', parseInt(item.dataset.drawNumber) === drawNumber);
        });

        updateSelectedDrawIndicator();

        log('Selected draw set to:', drawNumber);
    }

    /**
     * Force a sync
     */
    function forceSync() {
        log('Forcing sync...');
        syncUpcomingDraws();
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
        log('Upcoming Draws Panel destroyed');
    }

    // Public API
    return {
        init,
        destroy,
        getSelectedDraw,
        setSelectedDraw,
        forceSync,
        toggleCollapse,
        // Configuration
        setConfig: (newConfig) => Object.assign(config, newConfig)
    };
})();

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize after a small delay to ensure other modules are loaded
    setTimeout(() => {
        UpcomingDrawsPanel.init();
    }, 800);
});

// Export for global access
window.UpcomingDrawsPanel = UpcomingDrawsPanel;
