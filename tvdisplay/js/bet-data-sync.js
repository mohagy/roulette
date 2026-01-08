/**
 * Bet Data Sync Module
 * This module handles synchronization of betting data between the TV display and the database
 */

const BetDataSync = (function() {
    // Configuration
    const config = {
        apiEndpoint: '../php/tv_betting_api.php',
        autoSyncInterval: 30000, // 30 seconds
        debug: true,
        enableAutoSync: true
    };
    
    // State
    let currentBets = [];
    let lastSavedState = null;
    let autoSyncTimer = null;
    let isInitialized = false;
    
    /**
     * Initialize the module
     */
    function init() {
        if (isInitialized) return;
        
        log('Initializing Bet Data Sync module');
        
        // Start auto-sync if enabled
        if (config.enableAutoSync) {
            startAutoSync();
        }
        
        // Set up event listeners
        window.addEventListener('beforeunload', saveBeforeUnload);
        
        // Add custom events
        document.addEventListener('betPlaced', handleBetPlaced);
        document.addEventListener('betCancelled', handleBetCancelled);
        document.addEventListener('allBetsCancelled', handleAllBetsCancelled);
        document.addEventListener('drawCompleted', handleDrawCompleted);
        
        isInitialized = true;
        
        // Initial sync with server
        syncStateWithServer();
        
        log('Bet Data Sync module initialized');
    }
    
    /**
     * Log messages if debug is enabled
     */
    function log(message, data) {
        if (config.debug) {
            if (data) {
                console.log(`[BetDataSync] ${message}`, data);
            } else {
                console.log(`[BetDataSync] ${message}`);
            }
        }
    }
    
    /**
     * Handle errors
     */
    function handleError(operation, error) {
        console.error(`[BetDataSync] Error during ${operation}:`, error);
        
        // Display error notification if needed
        const errorMsg = `Error syncing betting data: ${error.message || 'Unknown error'}`;
        showNotification(errorMsg, 'error');
    }
    
    /**
     * Start automatic synchronization
     */
    function startAutoSync() {
        if (autoSyncTimer) {
            clearInterval(autoSyncTimer);
        }
        
        autoSyncTimer = setInterval(function() {
            syncStateWithServer();
        }, config.autoSyncInterval);
        
        log(`Auto-sync started, interval: ${config.autoSyncInterval}ms`);
    }
    
    /**
     * Stop automatic synchronization
     */
    function stopAutoSync() {
        if (autoSyncTimer) {
            clearInterval(autoSyncTimer);
            autoSyncTimer = null;
            log('Auto-sync stopped');
        }
    }
    
    /**
     * Save data before page unload
     */
    function saveBeforeUnload() {
        syncStateWithServer(true);
    }
    
    /**
     * Handle bet placed event
     */
    function handleBetPlaced(event) {
        if (event.detail && event.detail.bet) {
            log('Bet placed event received', event.detail.bet);
            
            // Add to current bets
            currentBets.push(event.detail.bet);
            
            // Save to server
            saveCurrentBets();
        }
    }
    
    /**
     * Handle bet cancelled event
     */
    function handleBetCancelled(event) {
        if (event.detail && event.detail.betId) {
            log('Bet cancelled event received', event.detail.betId);
            
            // Remove from current bets
            currentBets = currentBets.filter(bet => bet.id !== event.detail.betId);
            
            // Save to server
            saveCurrentBets();
        }
    }
    
    /**
     * Handle all bets cancelled event
     */
    function handleAllBetsCancelled() {
        log('All bets cancelled event received');
        
        // Clear current bets
        currentBets = [];
        
        // Save to server
        saveCurrentBets();
    }
    
    /**
     * Handle draw completed event
     */
    function handleDrawCompleted(event) {
        if (event.detail) {
            log('Draw completed event received', event.detail);
            
            // Save the state
            saveGameState(event.detail);
            
            // Clear current bets as the draw is completed
            currentBets = [];
        }
    }
    
    /**
     * Save current bets to the server
     */
    function saveCurrentBets() {
        if (currentBets.length === 0) {
            log('No bets to save');
            return Promise.resolve();
        }
        
        log('Saving current bets', { bets: currentBets });
        
        // Prepare data
        const currentDrawNumber = getCurrentDrawNumber();
        const data = {
            bets: currentBets,
            draw_number: currentDrawNumber,
            slip_number: generateSlipNumber()
        };
        
        // Send to server
        return fetch(config.apiEndpoint + '?action=save_betting_data', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.status === 'success') {
                log('Bets saved successfully', result);
                showNotification('Betting data saved successfully', 'success');
                return result;
            } else {
                throw new Error(result.message || 'Unknown error');
            }
        })
        .catch(error => {
            handleError('saveCurrentBets', error);
            return Promise.reject(error);
        });
    }
    
    /**
     * Generate a unique slip number
     */
    function generateSlipNumber() {
        const timestamp = Date.now().toString().slice(-10);
        const random = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
        return timestamp + random;
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
     * Save the game state to the server
     */
    function saveGameState(state) {
        if (!state) {
            log('No state to save');
            return Promise.resolve();
        }
        
        // Don't save if the state hasn't changed
        if (lastSavedState && JSON.stringify(lastSavedState) === JSON.stringify(state)) {
            log('State has not changed, skipping save');
            return Promise.resolve();
        }
        
        log('Saving game state', state);
        
        // Send to server
        return fetch(config.apiEndpoint + '?action=save_state', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(state)
        })
        .then(response => response.json())
        .then(result => {
            if (result.status === 'success') {
                log('State saved successfully', result);
                lastSavedState = state;
                return result;
            } else {
                throw new Error(result.message || 'Unknown error');
            }
        })
        .catch(error => {
            handleError('saveGameState', error);
            return Promise.reject(error);
        });
    }
    
    /**
     * Sync the current state with the server
     */
    function syncStateWithServer(immediate = false) {
        // Get current state from the UI
        const state = getCurrentState();
        
        // Save to server
        if (immediate) {
            // Use synchronous XHR for beforeunload event
            if (state) {
                try {
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', config.apiEndpoint + '?action=save_state', false); // false makes it synchronous
                    xhr.setRequestHeader('Content-Type', 'application/json');
                    xhr.send(JSON.stringify(state));
                    log('State saved synchronously before unload');
                } catch (error) {
                    console.error('Error saving state before unload:', error);
                }
            }
        } else {
            // Use standard async for normal operation
            saveGameState(state)
                .then(() => {
                    log('State synced with server successfully');
                })
                .catch(error => {
                    console.error('Error syncing state with server:', error);
                });
        }
    }
    
    /**
     * Get the current state from the UI
     */
    function getCurrentState() {
        // Get roll history
        const rollHistory = [];
        const rollColors = [];
        
        // Get from roll elements
        const rollElements = document.querySelectorAll('.roll:not(.roll-last)');
        rollElements.forEach(el => {
            // Try to get the number from the element content or data attribute
            let number = 0;
            if (el.dataset.number) {
                number = parseInt(el.dataset.number, 10);
            } else if (el.textContent.trim()) {
                number = parseInt(el.textContent.trim(), 10);
            }
            
            if (!isNaN(number)) {
                rollHistory.push(number);
                
                // Determine color
                let color = 'black';
                if (el.classList.contains('roll-red')) {
                    color = 'red';
                } else if (el.classList.contains('roll-green')) {
                    color = 'green';
                }
                rollColors.push(color);
            }
        });
        
        // Get draw numbers
        let lastDraw = null;
        let nextDraw = null;
        
        const lastDrawElement = document.getElementById('last-draw-number');
        if (lastDrawElement) {
            lastDraw = lastDrawElement.textContent;
        }
        
        const nextDrawElement = document.getElementById('next-draw-number');
        if (nextDrawElement) {
            nextDraw = nextDrawElement.textContent;
        }
        
        // Get countdown time
        let countdownTime = 120;
        const countdownElement = document.getElementById('countdown-timer');
        if (countdownElement) {
            const timeText = countdownElement.textContent;
            const match = timeText.match(/(\d+):(\d+)/);
            if (match) {
                const minutes = parseInt(match[1], 10);
                const seconds = parseInt(match[2], 10);
                countdownTime = minutes * 60 + seconds;
            }
        }
        
        return {
            roll_history: rollHistory,
            roll_colors: rollColors,
            last_draw: lastDraw,
            next_draw: nextDraw,
            countdown_time: countdownTime
        };
    }
    
    /**
     * Show a notification message
     */
    function showNotification(message, type = 'info') {
        // Check if notification is implemented
        if (typeof window.showNotification === 'function') {
            window.showNotification(message, type);
            return;
        }
        
        // Simple fallback
        console.log(`[Notification - ${type}] ${message}`);
    }
    
    /**
     * Public API
     */
    return {
        init: init,
        saveCurrentBets: saveCurrentBets,
        saveGameState: saveGameState,
        syncStateWithServer: syncStateWithServer,
        startAutoSync: startAutoSync,
        stopAutoSync: stopAutoSync,
        getCurrentDrawNumber: getCurrentDrawNumber,
        // Configuration setters
        setConfig: function(newConfig) {
            Object.assign(config, newConfig);
            
            // Restart auto-sync if interval changed
            if (autoSyncTimer && config.enableAutoSync) {
                startAutoSync();
            } else if (!config.enableAutoSync && autoSyncTimer) {
                stopAutoSync();
            }
        }
    };
})();

// Initialize when the DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize with a delay to ensure other scripts are loaded
    setTimeout(function() {
        BetDataSync.init();
    }, 1000);
}); 