/**
 * Future Draw Betting
 * 
 * This module enhances the betting system to allow placing bets for future draws.
 * It integrates with the upcoming draw display and betting slip functionality.
 */

const FutureDrawBetting = (function() {
    // Configuration
    const config = {
        debug: true,
        keys: {
            selectedDraw: 'selected_draw_number'
        }
    };

    // State
    let state = {
        initialized: false,
        selectedDrawNumber: null,
        currentDrawNumber: null,
        selectionIndicator: null
    };

    // Logging function
    function log(message, data) {
        if (config.debug) {
            if (data !== undefined) {
                console.log(`[FutureDrawBetting] ${message}`, data);
            } else {
                console.log(`[FutureDrawBetting] ${message}`);
            }
        }
    }

    // Error logging function
    function error(message, err) {
        console.error(`[FutureDrawBetting] ERROR: ${message}`, err);
    }

    /**
     * Initialize the future draw betting functionality
     */
    function initialize() {
        log('Initializing FutureDrawBetting');

        // Load selected draw from localStorage
        loadSelectedDrawFromLocalStorage();

        // Set up event listeners
        setupEventListeners();

        // Patch the betting slip functionality
        patchBettingSlipFunctionality();

        state.initialized = true;
        return true;
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
            }
        } catch (err) {
            error('Failed to load selected draw from localStorage', err);
        }
    }

    /**
     * Set up event listeners
     */
    function setupEventListeners() {
        // Listen for draw number selection events
        document.addEventListener('drawNumberSelected', (e) => {
            const drawNumber = e.detail.drawNumber;
            log(`Draw number selected event received: ${drawNumber}`);

            // Update state
            state.selectedDrawNumber = drawNumber;
            
            // Update UI
            updateBettingUI();
        });
    }

    /**
     * Update the betting UI to reflect the selected draw
     */
    function updateBettingUI() {
        // Update the print button text if it exists
        const printButton = document.querySelector('#print-betting-slip');
        if (printButton) {
            if (state.selectedDrawNumber) {
                printButton.innerHTML = `<i class="fas fa-print"></i> Print Slip for Draw #${state.selectedDrawNumber}`;
                printButton.classList.add('future-draw');
            } else {
                printButton.innerHTML = '<i class="fas fa-print"></i> Print Betting Slip';
                printButton.classList.remove('future-draw');
            }
        }
    }

    /**
     * Patch the betting slip functionality to include the selected draw
     */
    function patchBettingSlipFunctionality() {
        // Store the original printBettingSlip function if it exists
        if (window.betTracker && typeof window.betTracker.printBettingSlip === 'function') {
            const originalPrintBettingSlip = window.betTracker.printBettingSlip;
            
            // Override with our new function
            window.betTracker.printBettingSlip = function() {
                // Get the selected draw number (or default to current draw)
                const selectedDrawNumber = window.selectedDrawNumber;
                
                if (selectedDrawNumber) {
                    log(`Printing betting slip for future draw #${selectedDrawNumber}`);
                    
                    // Show a notification
                    const notification = document.createElement('div');
                    notification.style.position = 'fixed';
                    notification.style.top = '20px';
                    notification.style.left = '50%';
                    notification.style.transform = 'translateX(-50%)';
                    notification.style.backgroundColor = 'rgba(46, 204, 113, 0.9)';
                    notification.style.color = 'white';
                    notification.style.padding = '10px 20px';
                    notification.style.borderRadius = '5px';
                    notification.style.zIndex = '10000';
                    notification.style.fontWeight = 'bold';
                    notification.textContent = `Printing betting slip for Draw #${selectedDrawNumber}`;
                    document.body.appendChild(notification);
                    
                    // Remove after 3 seconds
                    setTimeout(() => {
                        notification.remove();
                    }, 3000);
                }
                
                // Call the original function
                return originalPrintBettingSlip.apply(this, arguments);
            };
            
            log('Patched betTracker.printBettingSlip function');
        } else {
            error('Could not find betTracker.printBettingSlip function to patch');
        }
    }

    // Return public API
    return {
        initialize,
        getSelectedDraw: () => state.selectedDrawNumber
    };
})();

// Initialize when the document is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the Future Draw Betting functionality
    FutureDrawBetting.initialize();
});
