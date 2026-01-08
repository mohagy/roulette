/**
 * Direct Results Override
 * 
 * This script directly overrides the results display to ensure it shows the correct forced number.
 * It uses a more aggressive approach than the results-display-fix.js script.
 */

(function() {
    // Configuration
    const config = {
        debug: true,
        checkInterval: 100, // How often to check for results display changes (ms)
        maxCheckTime: 15000 // Maximum time to check for results display changes (ms)
    };
    
    // Log messages if debug is enabled
    function log(message) {
        if (config.debug) {
            console.log(`🔴 DIRECT RESULTS OVERRIDE: ${message}`);
        }
    }
    
    // Log warnings
    function warn(message) {
        console.warn(`⚠️ DIRECT RESULTS OVERRIDE: ${message}`);
    }
    
    // Get the color for a number
    function getNumberColor(number) {
        if (number === 0) {
            return 'green';
        } else if ([1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36].includes(number)) {
            return 'red';
        } else {
            return 'black';
        }
    }
    
    // Start monitoring the results display
    function startMonitoring() {
        log('Starting to monitor results display');
        
        // Set up a continuous check for the results display
        const startTime = Date.now();
        
        const checkInterval = setInterval(function() {
            // Stop checking after the maximum check time
            if (Date.now() - startTime > config.maxCheckTime) {
                clearInterval(checkInterval);
                log('Stopped monitoring results display (timeout)');
                return;
            }
            
            // Check if we have a forced number
            if (window.manualWinningNumber === undefined || window.manualWinningNumber === null) {
                return;
            }
            
            const forcedNumber = parseInt(window.manualWinningNumber);
            
            // Check if the results display is visible
            const alertSpinResult = document.querySelector('.alert-spin-result');
            if (!alertSpinResult || !alertSpinResult.classList.contains('alert-message-visible')) {
                return;
            }
            
            // Update the roll number
            const rollNumberElement = document.querySelector('.roll-number');
            if (rollNumberElement && rollNumberElement.innerHTML != forcedNumber) {
                warn(`Correcting roll number from ${rollNumberElement.innerHTML} to ${forcedNumber}`);
                rollNumberElement.innerHTML = forcedNumber;
            }
            
            // Update the results element color
            const resultsElement = document.querySelector('.results');
            if (resultsElement) {
                // Remove existing color classes
                resultsElement.classList.remove('roll-red', 'roll-black', 'roll-green');
                
                // Add the correct color class
                const color = getNumberColor(forcedNumber);
                resultsElement.classList.add(`roll-${color}`);
            }
            
            // Update odd/even
            const oddEvenElement = document.querySelector('.odd-even');
            if (oddEvenElement) {
                if (forcedNumber === 0) {
                    // Zero is neither odd nor even
                    oddEvenElement.innerHTML = '';
                } else if (forcedNumber % 2 === 1) {
                    oddEvenElement.innerHTML = 'ODD';
                } else {
                    oddEvenElement.innerHTML = 'EVEN';
                }
            }
            
            // Update high/low
            const highLowElement = document.querySelector('.high-low');
            if (highLowElement) {
                if (forcedNumber === 0) {
                    // Zero is neither high nor low
                    highLowElement.innerHTML = '';
                } else if (forcedNumber < 19) {
                    highLowElement.innerHTML = 'LOW';
                } else {
                    highLowElement.innerHTML = 'HIGH';
                }
            }
        }, config.checkInterval);
    }
    
    // Monitor for spin button clicks
    function monitorSpinButton() {
        log('Setting up spin button monitoring');
        
        // Wait for jQuery to be available
        if (typeof $ === 'undefined') {
            setTimeout(monitorSpinButton, 100);
            return;
        }
        
        // Find the spin button
        const spinButton = $('.button-spin');
        if (spinButton.length === 0) {
            warn('Spin button not found, will try again');
            setTimeout(monitorSpinButton, 100);
            return;
        }
        
        // Add a click handler
        spinButton.on('click.resultsOverride', function() {
            log('Spin button clicked, starting to monitor results display');
            startMonitoring();
        });
        
        log('Spin button monitoring set up');
    }
    
    // Initialize when the document is ready
    function initialize() {
        log('Initializing');
        
        // Monitor for spin button clicks
        monitorSpinButton();
        
        // Also start monitoring immediately in case we missed a spin
        startMonitoring();
        
        log('Initialization complete');
    }
    
    // Start initialization when the document is ready
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        initialize();
    } else {
        document.addEventListener('DOMContentLoaded', initialize);
    }
})();
