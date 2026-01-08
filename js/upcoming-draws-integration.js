/**
 * Upcoming Draws Integration Module
 * Integrates the upcoming draws panel with the betting slip system
 * Handles draw selection and betting slip assignment
 */

const UpcomingDrawsIntegration = (function() {
    // Configuration
    const config = {
        debug: true,
        autoSelectNext: true, // Automatically select next draw if none selected
        showSelectionConfirmation: true
    };

    // State
    let state = {
        initialized: false,
        currentSelectedDraw: null,
        lastUpcomingDraws: [],
        integrationActive: false
    };

    /**
     * Log debug messages
     */
    function log(...args) {
        if (config.debug) {
            console.log('[UpcomingDrawsIntegration]', ...args);
        }
    }

    /**
     * Initialize the integration
     */
    function init() {
        if (state.initialized) return;

        log('Initializing Upcoming Draws Integration');

        setupEventListeners();
        setupBettingSlipIntegration();

        state.initialized = true;
        state.integrationActive = true;
        log('Upcoming Draws Integration initialized');
    }

    /**
     * Setup event listeners
     */
    function setupEventListeners() {
        // Listen for draw selection from the panel
        document.addEventListener('drawSelected', handleDrawSelected);

        // Listen for upcoming draws updates
        document.addEventListener('upcomingDrawsUpdated', handleUpcomingDrawsUpdated);

        // Listen for betting slip events
        document.addEventListener('beforeBettingSlipCreation', handleBeforeBettingSlipCreation);
        document.addEventListener('bettingSlipCreated', handleBettingSlipCreated);

        // Listen for print button clicks
        const printButton = document.getElementById('print-betting-slip-btn');
        if (printButton) {
            printButton.addEventListener('click', handlePrintButtonClick);
        }

        // Listen for cashier draw display updates
        document.addEventListener('cashierDrawNumbersUpdated', handleCashierDrawUpdate);

        log('Event listeners setup complete');
    }

    /**
     * Setup betting slip system integration
     */
    function setupBettingSlipIntegration() {
        // Override the global draw number function if it exists
        if (typeof window.getCurrentDrawNumber === 'function') {
            window.originalGetCurrentDrawNumber = window.getCurrentDrawNumber;
        }

        // Create new function that uses selected draw
        window.getCurrentDrawNumber = function() {
            const selectedDraw = getSelectedDrawNumber();
            if (selectedDraw) {
                log('Returning selected draw number:', selectedDraw);
                return selectedDraw;
            }

            // Fallback to original function or next draw
            if (window.originalGetCurrentDrawNumber) {
                return window.originalGetCurrentDrawNumber();
            }

            // Final fallback
            return getNextAvailableDraw();
        };

        // Override betting slip creation to use selected draw
        if (typeof window.createBettingSlip === 'function') {
            window.originalCreateBettingSlip = window.createBettingSlip;
            
            window.createBettingSlip = function(bets, options = {}) {
                const selectedDraw = getSelectedDrawNumber();
                if (selectedDraw) {
                    options.drawNumber = selectedDraw;
                    log('Creating betting slip for selected draw:', selectedDraw);
                }

                return window.originalCreateBettingSlip(bets, options);
            };
        }

        log('Betting slip integration setup complete');
    }

    /**
     * Handle draw selection
     */
    function handleDrawSelected(event) {
        if (!event.detail) return;

        const { drawNumber, drawData } = event.detail;
        
        log('Draw selected:', drawNumber, drawData);
        
        state.currentSelectedDraw = drawNumber;
        
        // Show confirmation if enabled
        if (config.showSelectionConfirmation) {
            showSelectionConfirmation(drawNumber, drawData);
        }

        // Update any existing draw displays
        updateDrawDisplays(drawNumber);

        // Dispatch integration event
        document.dispatchEvent(new CustomEvent('drawSelectionChanged', {
            detail: {
                selectedDraw: drawNumber,
                drawData: drawData,
                source: 'upcoming_draws_panel'
            }
        }));
    }

    /**
     * Handle upcoming draws updated
     */
    function handleUpcomingDrawsUpdated(event) {
        if (!event.detail) return;

        const { upcomingDraws, selectedDraw } = event.detail;
        
        log('Upcoming draws updated:', upcomingDraws.length, 'draws');
        
        state.lastUpcomingDraws = upcomingDraws;

        // Auto-select next draw if none selected and auto-select is enabled
        if (config.autoSelectNext && !state.currentSelectedDraw && upcomingDraws.length > 0) {
            const nextDraw = upcomingDraws.find(draw => draw.is_next) || upcomingDraws[0];
            if (nextDraw) {
                log('Auto-selecting next draw:', nextDraw.draw_number);
                setSelectedDraw(nextDraw.draw_number);
            }
        }
    }

    /**
     * Handle before betting slip creation
     */
    function handleBeforeBettingSlipCreation(event) {
        const selectedDraw = getSelectedDrawNumber();
        
        if (selectedDraw && event.detail) {
            event.detail.drawNumber = selectedDraw;
            log('Set betting slip draw number to selected:', selectedDraw);
            
            // Validate the selected draw is still upcoming
            const drawData = state.lastUpcomingDraws.find(d => d.draw_number === selectedDraw);
            if (!drawData) {
                log('Warning: Selected draw not found in upcoming draws list');
                showDrawValidationWarning(selectedDraw);
            }
        } else if (!selectedDraw) {
            log('Warning: No draw selected for betting slip creation');
            showNoDrawSelectedWarning();
        }
    }

    /**
     * Handle betting slip created
     */
    function handleBettingSlipCreated(event) {
        if (!event.detail) return;

        const { drawNumber, slipNumber, totalStake } = event.detail;
        
        log('Betting slip created:', { drawNumber, slipNumber, totalStake });

        // Show success notification
        showBettingSlipCreatedNotification(drawNumber, slipNumber, totalStake);

        // Refresh upcoming draws panel to update statistics
        if (window.UpcomingDrawsPanel) {
            setTimeout(() => {
                window.UpcomingDrawsPanel.forceSync();
            }, 1000);
        }
    }

    /**
     * Handle print button click
     */
    function handlePrintButtonClick(event) {
        const selectedDraw = getSelectedDrawNumber();
        
        if (!selectedDraw) {
            log('Warning: Print button clicked but no draw selected');
            showNoDrawSelectedWarning();
            event.preventDefault();
            return false;
        }

        log('Print button clicked for draw:', selectedDraw);
        
        // Validate the draw is still upcoming
        const drawData = state.lastUpcomingDraws.find(d => d.draw_number === selectedDraw);
        if (!drawData) {
            log('Warning: Selected draw not in upcoming draws list');
            showDrawValidationWarning(selectedDraw);
        }
    }

    /**
     * Handle cashier draw display updates
     */
    function handleCashierDrawUpdate(event) {
        if (!event.detail) return;

        const { upcomingDraw } = event.detail;
        
        // If no draw is selected and we have an upcoming draw, auto-select it
        if (config.autoSelectNext && !state.currentSelectedDraw && upcomingDraw) {
            log('Auto-selecting upcoming draw from cashier display:', upcomingDraw);
            setSelectedDraw(upcomingDraw);
        }
    }

    /**
     * Get the currently selected draw number
     */
    function getSelectedDrawNumber() {
        // First check our state
        if (state.currentSelectedDraw) {
            return state.currentSelectedDraw;
        }

        // Check the upcoming draws panel
        if (window.UpcomingDrawsPanel) {
            const panelSelected = window.UpcomingDrawsPanel.getSelectedDraw();
            if (panelSelected) {
                state.currentSelectedDraw = panelSelected;
                return panelSelected;
            }
        }

        return null;
    }

    /**
     * Set the selected draw number
     */
    function setSelectedDraw(drawNumber) {
        state.currentSelectedDraw = drawNumber;
        
        // Update the upcoming draws panel
        if (window.UpcomingDrawsPanel) {
            window.UpcomingDrawsPanel.setSelectedDraw(drawNumber);
        }

        log('Selected draw set to:', drawNumber);
    }

    /**
     * Get the next available draw number
     */
    function getNextAvailableDraw() {
        if (state.lastUpcomingDraws.length > 0) {
            const nextDraw = state.lastUpcomingDraws.find(draw => draw.is_next) || state.lastUpcomingDraws[0];
            return nextDraw.draw_number;
        }

        // Fallback to cashier display
        if (window.CashierDrawDisplay) {
            const drawNumbers = window.CashierDrawDisplay.getDrawNumbers();
            if (drawNumbers.upcomingDraw) {
                return drawNumbers.upcomingDraw;
            }
        }

        return 1; // Final fallback
    }

    /**
     * Update existing draw displays
     */
    function updateDrawDisplays(drawNumber) {
        // Update any draw number displays in the UI
        const drawElements = document.querySelectorAll('[data-selected-draw]');
        drawElements.forEach(element => {
            element.textContent = `#${drawNumber}`;
        });

        // Update global variables
        if (typeof window !== 'undefined') {
            window.selectedDrawNumber = drawNumber;
        }
    }

    /**
     * Show selection confirmation
     */
    function showSelectionConfirmation(drawNumber, drawData) {
        const notification = createNotification(
            `Draw #${drawNumber} Selected`,
            `New betting slips will be assigned to draw #${drawNumber}${drawData?.is_next ? ' (Next Draw)' : ''}`,
            'success',
            3000
        );
        
        document.body.appendChild(notification);
    }

    /**
     * Show no draw selected warning
     */
    function showNoDrawSelectedWarning() {
        const notification = createNotification(
            'No Draw Selected',
            'Please select an upcoming draw from the panel before creating betting slips.',
            'warning',
            5000
        );
        
        document.body.appendChild(notification);
    }

    /**
     * Show draw validation warning
     */
    function showDrawValidationWarning(drawNumber) {
        const notification = createNotification(
            'Draw Validation Warning',
            `Selected draw #${drawNumber} may no longer be available. Please verify the selection.`,
            'warning',
            5000
        );
        
        document.body.appendChild(notification);
    }

    /**
     * Show betting slip created notification
     */
    function showBettingSlipCreatedNotification(drawNumber, slipNumber, totalStake) {
        const notification = createNotification(
            'Betting Slip Created',
            `Slip ${slipNumber} created for draw #${drawNumber} - Stake: $${totalStake}`,
            'success',
            4000
        );
        
        document.body.appendChild(notification);
    }

    /**
     * Create a notification element
     */
    function createNotification(title, message, type = 'info', duration = 3000) {
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            background: ${type === 'success' ? '#28a745' : type === 'warning' ? '#ffc107' : '#007bff'};
            color: ${type === 'warning' ? '#000' : '#fff'};
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            max-width: 300px;
            font-family: Arial, sans-serif;
            animation: slideInRight 0.3s ease;
        `;
        
        notification.innerHTML = `
            <div style="font-weight: bold; margin-bottom: 5px;">${title}</div>
            <div style="font-size: 14px;">${message}</div>
        `;

        // Add animation styles
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOutRight {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);

        // Auto-remove after duration
        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, duration);

        return notification;
    }

    /**
     * Get current state
     */
    function getState() {
        return {
            selectedDraw: state.currentSelectedDraw,
            upcomingDraws: state.lastUpcomingDraws,
            integrationActive: state.integrationActive
        };
    }

    /**
     * Destroy the integration
     */
    function destroy() {
        // Restore original functions
        if (window.originalGetCurrentDrawNumber) {
            window.getCurrentDrawNumber = window.originalGetCurrentDrawNumber;
        }
        
        if (window.originalCreateBettingSlip) {
            window.createBettingSlip = window.originalCreateBettingSlip;
        }
        
        state.initialized = false;
        state.integrationActive = false;
        log('Upcoming Draws Integration destroyed');
    }

    // Public API
    return {
        init,
        destroy,
        getSelectedDrawNumber,
        setSelectedDraw,
        getState,
        // Configuration
        setConfig: (newConfig) => Object.assign(config, newConfig)
    };
})();

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize after other modules are loaded
    setTimeout(() => {
        UpcomingDrawsIntegration.init();
    }, 1200);
});

// Export for global access
window.UpcomingDrawsIntegration = UpcomingDrawsIntegration;
