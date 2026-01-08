/**
 * TV Betting Integration
 * This module handles synchronization of betting data from the TV display to the main game
 */

const TVBettingIntegration = (function() {
    // Configuration
    const config = {
        apiEndpoint: 'php/tv_betting_api.php',
        pollInterval: 5000, // 5 seconds
        debug: true,
        enableAutoPoll: true
    };
    
    // State
    let pollTimer = null;
    let lastProcessedSlipNumber = null;
    let isInitialized = false;
    let pendingBets = [];
    
    /**
     * Initialize the module
     */
    function init() {
        if (isInitialized) return;
        
        log('Initializing TV Betting Integration module');
        
        // Load the last processed slip number from localStorage
        lastProcessedSlipNumber = localStorage.getItem('lastProcessedSlipNumber');
        log('Last processed slip number:', lastProcessedSlipNumber);
        
        // Start auto-polling if enabled
        if (config.enableAutoPoll) {
            startPolling();
        }
        
        isInitialized = true;
        log('TV Betting Integration module initialized');
    }
    
    /**
     * Log messages if debug is enabled
     */
    function log(message, data) {
        if (config.debug) {
            if (data) {
                console.log(`[TVBetting] ${message}`, data);
            } else {
                console.log(`[TVBetting] ${message}`);
            }
        }
    }
    
    /**
     * Handle errors
     */
    function handleError(operation, error) {
        console.error(`[TVBetting] Error during ${operation}:`, error);
        
        // Display error notification if possible
        if (typeof window.showNotification === 'function') {
            window.showNotification(`Error processing TV bets: ${error.message || 'Unknown error'}`, 'error');
        }
    }
    
    /**
     * Start polling for new TV display bets
     */
    function startPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
        }
        
        // Initial poll immediately
        pollForNewBets();
        
        // Set up recurring poll
        pollTimer = setInterval(pollForNewBets, config.pollInterval);
        log(`Auto-polling started, interval: ${config.pollInterval}ms`);
    }
    
    /**
     * Stop polling
     */
    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
            log('Auto-polling stopped');
        }
    }
    
    /**
     * Poll for new TV display bets
     */
    function pollForNewBets() {
        log('Polling for new TV display bets');
        
        // Get the current draw number
        const currentDrawNumber = getCurrentDrawNumber();
        
        // Prepare query parameters
        const params = new URLSearchParams({
            action: 'get_betting_slips',
            draw_number: currentDrawNumber
        });
        
        if (lastProcessedSlipNumber) {
            params.append('after_slip_number', lastProcessedSlipNumber);
        }
        
        // Query the API
        fetch(`${config.apiEndpoint}?${params.toString()}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success' && data.betting_slips && data.betting_slips.length > 0) {
                    log(`Found ${data.betting_slips.length} new betting slips`, data.betting_slips);
                    processBettingSlips(data.betting_slips);
                } else {
                    log('No new betting slips found');
                }
            })
            .catch(error => {
                handleError('pollForNewBets', error);
            });
    }
    
    /**
     * Process betting slips from TV display
     */
    function processBettingSlips(slips) {
        if (!slips || slips.length === 0) return;
        
        let lastSlipNumber = lastProcessedSlipNumber;
        
        // Process each slip
        slips.forEach(slip => {
            try {
                log(`Processing slip #${slip.slip_number}`, slip);
                
                // Get the bets for this slip
                fetchBetsForSlip(slip.slip_id)
                    .then(bets => {
                        if (bets && bets.length > 0) {
                            // Add bets to the pending bets array
                            pendingBets = pendingBets.concat(bets);
                            
                            // Apply the bets to the game
                            applyBets(bets);
                            
                            // Update the last processed slip number
                            if (slip.slip_number > lastSlipNumber) {
                                lastSlipNumber = slip.slip_number;
                                localStorage.setItem('lastProcessedSlipNumber', lastSlipNumber);
                                lastProcessedSlipNumber = lastSlipNumber;
                            }
                        }
                    })
                    .catch(error => {
                        handleError('fetchBetsForSlip', error);
                    });
            } catch (error) {
                handleError('processBettingSlips', error);
            }
        });
    }
    
    /**
     * Fetch bets for a slip
     */
    function fetchBetsForSlip(slipId) {
        return fetch(`${config.apiEndpoint}?action=get_slip_bets&slip_id=${slipId}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success' && data.bets) {
                    log(`Found ${data.bets.length} bets for slip #${slipId}`, data.bets);
                    return data.bets;
                } else {
                    log(`No bets found for slip #${slipId}`);
                    return [];
                }
            })
            .catch(error => {
                handleError('fetchBetsForSlip', error);
                return [];
            });
    }
    
    /**
     * Apply bets to the game
     */
    function applyBets(bets) {
        if (!bets || bets.length === 0) return;
        
        log(`Applying ${bets.length} bets to the game`, bets);
        
        bets.forEach(bet => {
            try {
                // Find the corresponding bet element on the page
                const betElement = findBetElement(bet.bet_type, bet.bet_value);
                
                if (betElement) {
                    log(`Found bet element for ${bet.bet_type} ${bet.bet_value}`, betElement);
                    
                    // Get the current chip value
                    const chipValue = getSelectedChipValue();
                    
                    // Set the chip value to match the bet
                    setChipValue(bet.stake);
                    
                    // Trigger a click on the element
                    triggerClick(betElement);
                    
                    // Restore the original chip value
                    setChipValue(chipValue);
                } else {
                    log(`Could not find bet element for ${bet.bet_type} ${bet.bet_value}`);
                }
            } catch (error) {
                handleError('applyBet', error);
            }
        });
        
        // Trigger bet placed event
        triggerBetPlacedEvent(bets);
    }
    
    /**
     * Find the bet element on the page
     */
    function findBetElement(betType, betValue) {
        // This function needs to be customized based on the game's DOM structure
        switch (betType) {
            case 'number':
                return document.querySelector(`.number.number${betValue}`);
                
            case 'color':
                return document.querySelector(`.bet-${betValue}`);
                
            case 'even-odd':
                return document.querySelector(`.bet-${betValue}`);
                
            case 'high-low':
                return document.querySelector(`.bet-${betValue}`);
                
            case 'dozen':
                return document.querySelector(`.bet-dozen-${betValue}`);
                
            case 'column':
                return document.querySelector(`.bet-column-${betValue}`);
                
            default:
                return null;
        }
    }
    
    /**
     * Get the current selected chip value
     */
    function getSelectedChipValue() {
        const activeChip = document.querySelector('.chip.active');
        if (activeChip) {
            return parseFloat(activeChip.getAttribute('data-value') || activeChip.dataset.value || '100');
        }
        return 100; // Default value
    }
    
    /**
     * Set the chip value
     */
    function setChipValue(value) {
        // Find the closest chip value or custom input
        const chipElements = document.querySelectorAll('.chip');
        let closestChip = null;
        let minDiff = Infinity;
        
        chipElements.forEach(chip => {
            const chipValue = parseFloat(chip.getAttribute('data-value') || chip.dataset.value || '0');
            const diff = Math.abs(chipValue - value);
            
            if (diff < minDiff) {
                minDiff = diff;
                closestChip = chip;
            }
        });
        
        if (closestChip) {
            // Simulate a click on the chip
            triggerClick(closestChip);
        }
    }
    
    /**
     * Trigger a click event on an element
     */
    function triggerClick(element) {
        if (!element) return;
        
        try {
            // Create and dispatch a mouse event
            const event = new MouseEvent('click', {
                view: window,
                bubbles: true,
                cancelable: true
            });
            
            element.dispatchEvent(event);
        } catch (error) {
            handleError('triggerClick', error);
        }
    }
    
    /**
     * Trigger bet placed event
     */
    function triggerBetPlacedEvent(bets) {
        try {
            // Create and dispatch a custom event
            const event = new CustomEvent('tvDisplayBetsPlaced', {
                detail: {
                    bets: bets,
                    timestamp: new Date().toISOString()
                }
            });
            
            document.dispatchEvent(event);
            log('Triggered tvDisplayBetsPlaced event', bets);
        } catch (error) {
            handleError('triggerBetPlacedEvent', error);
        }
    }
    
    /**
     * Get the current draw number
     */
    function getCurrentDrawNumber() {
        // Try to get from the DOM or global variable
        let drawNumber = 1;
        
        // Try to get from the DOM
        const nextDrawElement = document.getElementById('next-draw-number');
        if (nextDrawElement) {
            const text = nextDrawElement.textContent || '';
            const match = text.match(/#(\d+)/);
            if (match && match[1]) {
                drawNumber = parseInt(match[1], 10);
            }
        }
        
        // Try to get from global variable if available
        if (window.currentDrawNumber) {
            drawNumber = parseInt(window.currentDrawNumber, 10);
        }
        
        return drawNumber;
    }
    
    /**
     * Public API
     */
    return {
        init: init,
        startPolling: startPolling,
        stopPolling: stopPolling,
        pollForNewBets: pollForNewBets,
        setPollInterval: function(interval) {
            config.pollInterval = interval;
            if (pollTimer) {
                // Restart polling with new interval
                stopPolling();
                startPolling();
            }
        },
        setConfig: function(newConfig) {
            Object.assign(config, newConfig);
            
            // Restart polling if the interval changed
            if (pollTimer && config.enableAutoPoll) {
                stopPolling();
                startPolling();
            } else if (!config.enableAutoPoll && pollTimer) {
                stopPolling();
            }
        }
    };
})();

// Initialize when the DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize with a slight delay to ensure other scripts are loaded
    setTimeout(function() {
        TVBettingIntegration.init();
    }, 1500);
}); 