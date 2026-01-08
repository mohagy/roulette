/**
 * Results Display Fix
 * 
 * This script ensures the results display shows the correct forced number.
 */

(function() {
    // Configuration
    const config = {
        debug: true,
        resultDisplayDelay: 5000, // Time after spin when results are displayed (ms)
        checkInterval: 100 // How often to check for results display changes (ms)
    };
    
    // Log messages if debug is enabled
    function log(message) {
        if (config.debug) {
            console.log(`🎯 RESULTS FIX: ${message}`);
        }
    }
    
    // Log warnings
    function warn(message) {
        console.warn(`⚠️ RESULTS FIX: ${message}`);
    }
    
    // Override the results display function
    function overrideResultsDisplay() {
        log('Setting up results display override');
        
        // Wait for the results display function to be available
        if (typeof window.resultsDisplay !== 'function') {
            setTimeout(overrideResultsDisplay, 100);
            return;
        }
        
        // Store the original function
        const originalResultsDisplay = window.resultsDisplay;
        
        // Replace with our version
        window.resultsDisplay = function() {
            log('Results display function called');
            
            // Check if we have a forced number
            if (window.manualWinningNumber !== undefined && window.manualWinningNumber !== null) {
                const forcedNumber = parseInt(window.manualWinningNumber);
                warn(`FORCING RESULTS TO SHOW ${forcedNumber}`);
                
                // Force the roulette number
                window.rouletteNumber = forcedNumber;
            }
            
            // Call the original function
            const result = originalResultsDisplay.apply(this, arguments);
            
            // After the results are displayed, ensure they show the correct number
            if (window.manualWinningNumber !== undefined && window.manualWinningNumber !== null) {
                const forcedNumber = parseInt(window.manualWinningNumber);
                
                // Set up a continuous check to ensure the results display shows the correct number
                const startTime = Date.now();
                const maxCheckTime = 10000; // Check for up to 10 seconds
                
                const checkResultsInterval = setInterval(function() {
                    // Stop checking after 10 seconds
                    if (Date.now() - startTime > maxCheckTime) {
                        clearInterval(checkResultsInterval);
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
                        if (forcedNumber === 0) {
                            resultsElement.classList.add('roll-green');
                        } else if ([1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36].includes(forcedNumber)) {
                            resultsElement.classList.add('roll-red');
                        } else {
                            resultsElement.classList.add('roll-black');
                        }
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
                
                // Also set up a one-time check after the expected results display delay
                setTimeout(function() {
                    // Update the roll number
                    const rollNumberElement = document.querySelector('.roll-number');
                    if (rollNumberElement) {
                        warn(`Final check: Setting roll number to ${forcedNumber}`);
                        rollNumberElement.innerHTML = forcedNumber;
                    }
                    
                    // Update the results element color
                    const resultsElement = document.querySelector('.results');
                    if (resultsElement) {
                        // Remove existing color classes
                        resultsElement.classList.remove('roll-red', 'roll-black', 'roll-green');
                        
                        // Add the correct color class
                        if (forcedNumber === 0) {
                            resultsElement.classList.add('roll-green');
                        } else if ([1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36].includes(forcedNumber)) {
                            resultsElement.classList.add('roll-red');
                        } else {
                            resultsElement.classList.add('roll-black');
                        }
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
                }, config.resultDisplayDelay);
            }
            
            return result;
        };
        
        log('Results display override complete');
    }
    
    // Initialize when the document is ready
    function initialize() {
        log('Initializing');
        
        // Override the results display
        overrideResultsDisplay();
        
        log('Initialization complete');
    }
    
    // Start initialization when the document is ready
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        initialize();
    } else {
        document.addEventListener('DOMContentLoaded', initialize);
    }
})();
