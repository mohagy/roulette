/**
 * Cashier Draw Integration Module
 * Integrates the cashier draw display with the betting slip system
 * Ensures betting slips are assigned to the correct upcoming draw number
 */

const CashierDrawIntegration = (function() {
    // Configuration
    const config = {
        debug: true,
        updateInterval: 1000, // Check for updates every second
        maxRetries: 3
    };

    // State
    let state = {
        initialized: false,
        currentUpcomingDraw: null,
        lastCompletedDraw: null,
        updateIntervalId: null,
        retryCount: 0
    };

    /**
     * Log debug messages
     */
    function log(...args) {
        if (config.debug) {
            console.log('[CashierDrawIntegration]', ...args);
        }
    }

    /**
     * Initialize the integration
     */
    function init() {
        if (state.initialized) return;

        log('Initializing Cashier Draw Integration');

        setupEventListeners();
        startUpdateInterval();

        state.initialized = true;
        log('Cashier Draw Integration initialized');
    }

    /**
     * Setup event listeners
     */
    function setupEventListeners() {
        // Listen for draw number updates from the cashier display
        document.addEventListener('cashierDrawNumbersUpdated', handleDrawNumbersUpdated);

        // Listen for betting slip creation events
        document.addEventListener('bettingSlipCreated', handleBettingSlipCreated);
        document.addEventListener('beforeBettingSlipCreation', handleBeforeBettingSlipCreation);

        // Listen for print betting slip button clicks
        const printButton = document.getElementById('print-betting-slip-btn');
        if (printButton) {
            printButton.addEventListener('click', handlePrintButtonClick);
        }

        log('Event listeners setup complete');
    }

    /**
     * Start the update interval to sync with existing systems
     */
    function startUpdateInterval() {
        if (state.updateIntervalId) {
            clearInterval(state.updateIntervalId);
        }

        state.updateIntervalId = setInterval(syncWithExistingSystems, config.updateInterval);
        log('Update interval started');
    }

    /**
     * Handle draw numbers updated event
     */
    function handleDrawNumbersUpdated(event) {
        if (!event.detail) return;

        const { upcomingDraw, lastCompletedDraw } = event.detail;
        
        log('Received draw numbers update:', { upcomingDraw, lastCompletedDraw });

        // Update state
        const hasChanges = (
            state.currentUpcomingDraw !== upcomingDraw ||
            state.lastCompletedDraw !== lastCompletedDraw
        );

        state.currentUpcomingDraw = upcomingDraw;
        state.lastCompletedDraw = lastCompletedDraw;

        if (hasChanges) {
            // Update existing draw number displays
            updateExistingDrawDisplays();
            
            // Update any global variables that other scripts might use
            updateGlobalDrawVariables();
            
            // Reset retry count on successful update
            state.retryCount = 0;
        }
    }

    /**
     * Handle before betting slip creation
     */
    function handleBeforeBettingSlipCreation(event) {
        log('Before betting slip creation event');

        // Ensure we have the latest draw number
        if (state.currentUpcomingDraw) {
            // Set the draw number for the betting slip
            if (event.detail && typeof event.detail === 'object') {
                event.detail.drawNumber = state.currentUpcomingDraw;
                log('Set betting slip draw number to:', state.currentUpcomingDraw);
            }
        } else {
            log('Warning: No upcoming draw number available for betting slip');
            
            // Try to get draw number from cashier display
            if (window.CashierDrawDisplay) {
                const drawNumbers = window.CashierDrawDisplay.getDrawNumbers();
                if (drawNumbers.upcomingDraw) {
                    state.currentUpcomingDraw = drawNumbers.upcomingDraw;
                    if (event.detail && typeof event.detail === 'object') {
                        event.detail.drawNumber = state.currentUpcomingDraw;
                    }
                    log('Retrieved draw number from display:', state.currentUpcomingDraw);
                }
            }
        }
    }

    /**
     * Handle betting slip created event
     */
    function handleBettingSlipCreated(event) {
        log('Betting slip created event');

        if (event.detail && event.detail.drawNumber) {
            log('Betting slip assigned to draw:', event.detail.drawNumber);
            
            // Verify the draw number is correct
            if (state.currentUpcomingDraw && event.detail.drawNumber !== state.currentUpcomingDraw) {
                log('Warning: Betting slip draw number mismatch!', {
                    slipDraw: event.detail.drawNumber,
                    expectedDraw: state.currentUpcomingDraw
                });
            }
        }
    }

    /**
     * Handle print button click
     */
    function handlePrintButtonClick(event) {
        log('Print button clicked');

        // Ensure we have the latest draw number before printing
        if (!state.currentUpcomingDraw) {
            log('Warning: No upcoming draw number available for printing');
            
            // Try to force sync
            if (window.CashierDrawDisplay) {
                window.CashierDrawDisplay.forceSync();
            }
            
            // Show warning to user
            showDrawNumberWarning();
            return;
        }

        log('Printing betting slip for draw:', state.currentUpcomingDraw);
    }

    /**
     * Update existing draw number displays in the UI
     */
    function updateExistingDrawDisplays() {
        // Update the existing draw container if it exists
        const lastDrawElement = document.getElementById('last-draw-number');
        const nextDrawElement = document.getElementById('next-draw-number');

        if (lastDrawElement && state.lastCompletedDraw) {
            lastDrawElement.textContent = `#${state.lastCompletedDraw}`;
            log('Updated last draw display to:', state.lastCompletedDraw);
        }

        if (nextDrawElement && state.currentUpcomingDraw) {
            nextDrawElement.textContent = `#${state.currentUpcomingDraw}`;
            log('Updated next draw display to:', state.currentUpcomingDraw);
        }

        // Update any other draw number displays
        const drawNumberElements = document.querySelectorAll('[data-draw-number]');
        drawNumberElements.forEach(element => {
            const type = element.getAttribute('data-draw-type');
            if (type === 'upcoming' && state.currentUpcomingDraw) {
                element.textContent = `#${state.currentUpcomingDraw}`;
            } else if (type === 'completed' && state.lastCompletedDraw) {
                element.textContent = `#${state.lastCompletedDraw}`;
            }
        });
    }

    /**
     * Update global variables that other scripts might use
     */
    function updateGlobalDrawVariables() {
        // Set global variables for compatibility with existing scripts
        if (typeof window !== 'undefined') {
            window.currentDrawNumber = state.lastCompletedDraw;
            window.nextDrawNumber = state.currentUpcomingDraw;
            window.upcomingDrawNumber = state.currentUpcomingDraw;
            window.lastCompletedDrawNumber = state.lastCompletedDraw;
            
            log('Updated global draw variables');
        }

        // Update localStorage for other modules
        try {
            localStorage.setItem('cashier_current_draw', state.lastCompletedDraw?.toString() || '0');
            localStorage.setItem('cashier_upcoming_draw', state.currentUpcomingDraw?.toString() || '1');
            localStorage.setItem('cashier_draw_sync_time', Date.now().toString());
        } catch (e) {
            log('Error updating localStorage:', e);
        }
    }

    /**
     * Sync with existing systems
     */
    function syncWithExistingSystems() {
        // Check if we need to get draw numbers from other sources
        if (!state.currentUpcomingDraw || !state.lastCompletedDraw) {
            // Try to get from cashier display
            if (window.CashierDrawDisplay) {
                const drawNumbers = window.CashierDrawDisplay.getDrawNumbers();
                if (drawNumbers.upcomingDraw || drawNumbers.lastCompletedDraw) {
                    state.currentUpcomingDraw = drawNumbers.upcomingDraw || state.currentUpcomingDraw;
                    state.lastCompletedDraw = drawNumbers.lastCompletedDraw || state.lastCompletedDraw;
                    
                    updateExistingDrawDisplays();
                    updateGlobalDrawVariables();
                    
                    log('Synced with cashier display');
                }
            }
        }

        // Check for draw number changes from other modules
        const storedUpcoming = localStorage.getItem('tv_display_current_draw');
        const storedCurrent = localStorage.getItem('tv_display_previous_draw');
        
        if (storedUpcoming && parseInt(storedUpcoming) !== state.currentUpcomingDraw) {
            log('Detected draw number change from TV display sync');
            
            // Force a sync with the cashier display
            if (window.CashierDrawDisplay) {
                window.CashierDrawDisplay.forceSync();
            }
        }
    }

    /**
     * Show warning about missing draw number
     */
    function showDrawNumberWarning() {
        // Create a temporary warning message
        const warning = document.createElement('div');
        warning.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #dc3545;
            color: white;
            padding: 20px;
            border-radius: 8px;
            z-index: 10000;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        `;
        warning.innerHTML = `
            <h3>⚠️ Draw Number Not Available</h3>
            <p>Unable to determine the upcoming draw number.<br>Please wait for synchronization to complete.</p>
        `;

        document.body.appendChild(warning);

        // Remove warning after 3 seconds
        setTimeout(() => {
            if (warning.parentNode) {
                warning.parentNode.removeChild(warning);
            }
        }, 3000);
    }

    /**
     * Get current draw numbers
     */
    function getCurrentDrawNumbers() {
        return {
            upcomingDraw: state.currentUpcomingDraw,
            lastCompletedDraw: state.lastCompletedDraw
        };
    }

    /**
     * Force sync with all systems
     */
    function forceSync() {
        log('Forcing sync with all systems');
        
        if (window.CashierDrawDisplay) {
            window.CashierDrawDisplay.forceSync();
        }
        
        syncWithExistingSystems();
    }

    /**
     * Destroy the integration
     */
    function destroy() {
        if (state.updateIntervalId) {
            clearInterval(state.updateIntervalId);
        }
        
        state.initialized = false;
        log('Cashier Draw Integration destroyed');
    }

    // Public API
    return {
        init,
        destroy,
        getCurrentDrawNumbers,
        forceSync,
        // Configuration
        setConfig: (newConfig) => Object.assign(config, newConfig)
    };
})();

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize after a small delay to ensure other modules are loaded
    setTimeout(() => {
        CashierDrawIntegration.init();
    }, 1000);
});

// Export for global access
window.CashierDrawIntegration = CashierDrawIntegration;
