/**
 * Forced Number Handler
 *
 * This script ensures the roulette wheel always lands on the forced number
 * set in the next_draw_winning_number table.
 */

(function() {
    // Configuration
    const config = {
        debug: true,
        apiEndpoint: '../api/direct_forced_number.php',
        checkInterval: 1000, // How often to check for forced numbers (ms)
    };

    // State variables
    let forcedNumber = null;
    let forcedColor = null;
    let checkIntervalId = null;
    let isInitialized = false;
    let currentDrawNumber = null;

    // Log messages if debug is enabled
    function log(message) {
        if (config.debug) {
            console.log(`🎯 FORCED NUMBER HANDLER: ${message}`);
        }
    }

    // Log warnings
    function warn(message) {
        console.warn(`⚠️ FORCED NUMBER HANDLER: ${message}`);
    }

    // Log errors
    function error(message) {
        console.error(`❌ FORCED NUMBER HANDLER: ${message}`);
    }

    // Initialize the handler
    function initialize() {
        if (isInitialized) {
            warn('Already initialized');
            return;
        }

        log('Initializing');

        // Start checking for forced numbers
        startChecking();

        // Override the roulette wheel animation function
        overrideRouletteWheel();

        isInitialized = true;
        log('Initialization complete');
    }

    // Start checking for forced numbers
    function startChecking() {
        if (checkIntervalId) {
            clearInterval(checkIntervalId);
        }

        // Check immediately
        checkForForcedNumber();

        // Set up interval for subsequent checks
        checkIntervalId = setInterval(checkForForcedNumber, config.checkInterval);
        log(`Started checking for forced numbers every ${config.checkInterval}ms`);
    }

    // Stop checking for forced numbers
    function stopChecking() {
        if (checkIntervalId) {
            clearInterval(checkIntervalId);
            checkIntervalId = null;
            log('Stopped checking for forced numbers');
        }
    }

    // Check for forced numbers from the API
    function checkForForcedNumber() {
        fetch(config.apiEndpoint + '?t=' + Date.now()) // Add timestamp to prevent caching
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Network response was not ok: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    processForcedNumberData(data);
                } else {
                    error(`API error: ${data.message}`);
                }
            })
            .catch(err => {
                error(`Error checking for forced number: ${err.message}`);
            });
    }

    // Process forced number data from the API
    function processForcedNumberData(data) {
        // Update current draw number
        if (currentDrawNumber !== data.draw_number) {
            currentDrawNumber = data.draw_number;
            log(`Current draw number: ${currentDrawNumber}`);
        }

        // Check if a forced number is set
        if (data.has_forced_number) {
            // Update forced number and color
            const newForcedNumber = parseInt(data.forced_number);
            const newForcedColor = data.forced_color;

            // Only update if the number has changed
            if (forcedNumber !== newForcedNumber) {
                forcedNumber = newForcedNumber;
                forcedColor = newForcedColor;

                // Set the global variable for other scripts to use
                window.manualWinningNumber = forcedNumber;

                log(`Forced number set: ${forcedNumber} (${forcedColor})`);

                // Update the forced number indicator
                updateForcedNumberIndicator();

                // Apply property lock immediately
                applyPropertyLock();
            }
        } else {
            // Only clear if we had a forced number before
            if (forcedNumber !== null) {
                // Clear forced number
                forcedNumber = null;
                forcedColor = null;
                window.manualWinningNumber = null;

                log('No forced number set');

                // Hide the forced number indicator
                hideForcedNumberIndicator();
            }
        }
    }

    // Get the color for a number
    function getNumberColor(number) {
        if (number === 0) {
            return 'green';
        } else if ([1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36].includes(parseInt(number))) {
            return 'red';
        } else {
            return 'black';
        }
    }

    // Update the forced number indicator (but keep it hidden)
    function updateForcedNumberIndicator() {
        // Get the indicator element
        const indicator = document.getElementById('forced-number-indicator');
        const valueSpan = document.getElementById('forced-number-value');

        if (!indicator || !valueSpan) {
            // Create the indicator if it doesn't exist
            createForcedNumberIndicator();
            return;
        }

        // Update the number
        valueSpan.textContent = forcedNumber;

        // Update the color
        indicator.className = '';
        indicator.classList.add(forcedColor);

        // Keep the indicator hidden
        indicator.style.display = 'none !important';
    }

    // Hide the forced number indicator
    function hideForcedNumberIndicator() {
        // Get the indicator element
        const indicator = document.getElementById('forced-number-indicator');

        if (!indicator) {
            return;
        }

        // Hide the indicator
        indicator.style.display = 'none';
    }

    // Create the forced number indicator (hidden from players)
    function createForcedNumberIndicator() {
        // Check if the indicator already exists
        if (document.getElementById('forced-number-indicator')) {
            return;
        }

        // Create the indicator
        const indicator = document.createElement('div');
        indicator.id = 'forced-number-indicator';
        indicator.style.position = 'fixed';
        indicator.style.top = '10px';
        indicator.style.right = '10px';
        indicator.style.padding = '10px';
        indicator.style.borderRadius = '5px';
        indicator.style.color = 'white';
        indicator.style.fontWeight = 'bold';
        indicator.style.zIndex = '9999';
        indicator.style.display = 'none !important'; // Always keep it hidden

        // Create the value span
        const valueSpan = document.createElement('span');
        valueSpan.id = 'forced-number-value';
        valueSpan.textContent = forcedNumber || '';

        // Add the value span to the indicator
        indicator.appendChild(document.createTextNode('Forced Number: '));
        indicator.appendChild(valueSpan);

        // Add the indicator to the document
        document.body.appendChild(indicator);

        // Update the indicator (but keep it hidden)
        if (forcedNumber !== null) {
            updateForcedNumberIndicator();
        }
    }

    // Override the roulette wheel animation function
    function overrideRouletteWheel() {
        log('Setting up NUCLEAR roulette wheel override');

        // Wait for the roulette wheel animation function to be available
        const waitForRouletteWheel = setInterval(() => {
            if (typeof window.rouletteWheelAnimation === 'function') {
                clearInterval(waitForRouletteWheel);

                // Store the original function
                const originalRouletteWheelAnimation = window.rouletteWheelAnimation;

                // Replace with our version that completely takes over the wheel animation
                window.rouletteWheelAnimation = function() {
                    log('Roulette wheel animation called');

                    // If we have a forced number, use it
                    if (forcedNumber !== null) {
                        log(`Using forced number: ${forcedNumber}`);

                        // Force the roulette number
                        window.rouletteNumber = parseInt(forcedNumber);

                        // Find the index of the forced number in the roulette numbers array
                        let targetIndex = -1;
                        for (let i = 0; i < window.rouletteNumbersArray.length; i++) {
                            if (window.rouletteNumbersArray[i] == forcedNumber) {
                                targetIndex = i;
                                log(`Found ${forcedNumber} at index ${i} in rouletteNumbersArray`);
                                break;
                            }
                        }

                        // Special handling for number 0
                        if (parseInt(forcedNumber) === 0 && targetIndex === -1) {
                            log(`Special handling for zero`);
                            if (window.rouletteNumbersArray[0] === 0) {
                                targetIndex = 0;
                                log(`Found 0 at index 0`);
                            } else {
                                // Search the entire array for 0
                                for (let i = 0; i < window.rouletteNumbersArray.length; i++) {
                                    if (window.rouletteNumbersArray[i] === 0) {
                                        targetIndex = i;
                                        log(`Found 0 at index ${i}`);
                                        break;
                                    }
                                }
                            }
                        }

                        if (targetIndex === -1) {
                            error(`Could not find ${forcedNumber} in rouletteNumbersArray`);
                            // Call the original function as fallback
                            return originalRouletteWheelAnimation.apply(this, arguments);
                        }

                        // Call the original function first to set up the animation
                        originalRouletteWheelAnimation.apply(this, arguments);

                        // Then override the ball animation to land on our forced number
                        setTimeout(() => {
                            try {
                                // Find and remove any existing animation style
                                const existingStyles = document.querySelectorAll('style');
                                existingStyles.forEach(style => {
                                    if (style.textContent.includes('@-webkit-keyframes ball-container-animation') ||
                                        style.textContent.includes('@keyframes ball-container-animation')) {
                                        log('Removing existing ball animation style');
                                        style.remove();
                                    }
                                });

                                // Get the ball container
                                const ballContainer = document.querySelector(".ball-spinner");
                                if (!ballContainer) {
                                    error('Ball container not found');
                                    return;
                                }

                                // Calculate the exact degree for our forced number
                                const degree = (360 / window.rouletteNumbersAmount) * targetIndex;
                                log(`Setting ball to land at exactly ${degree} degrees for number ${forcedNumber}`);

                                // Create and inject our custom keyframe animation
                                const sheet = document.createElement("style");
                                sheet.textContent = `
                                @-webkit-keyframes ball-container-animation {
                                    0% {
                                        transform: rotate(1440deg);
                                    }
                                    100% {
                                        transform: rotate(${degree}deg);
                                    }
                                }
                                @keyframes ball-container-animation {
                                    0% {
                                        transform: rotate(1440deg);
                                    }
                                    100% {
                                        transform: rotate(${degree}deg);
                                    }
                                }`;

                                document.head.appendChild(sheet);
                                log('Injected forced ball landing animation');

                                // Make double sure the number is correct
                                window.rouletteNumber = parseInt(forcedNumber);

                                // Watch for any attempts to change it and block them
                                const continuousEnforcer = setInterval(() => {
                                    if (window.rouletteNumber != forcedNumber) {
                                        log(`Enforcing rouletteNumber = ${forcedNumber}`);
                                        window.rouletteNumber = parseInt(forcedNumber);
                                    }
                                }, 50);

                                // Clear the enforcer after the spin is complete
                                setTimeout(() => {
                                    clearInterval(continuousEnforcer);
                                }, 10000);
                            } catch (err) {
                                error(`Error overriding ball animation: ${err.message}`);
                            }
                        }, 10);

                        return;
                    }

                    // Otherwise, call the original function
                    return originalRouletteWheelAnimation.apply(this, arguments);
                };

                log('Roulette wheel override complete');

                // Also override the results display function
                overrideResultsDisplay();
            }
        }, 100);
    }

    // Override the results display function
    function overrideResultsDisplay() {
        log('Setting up NUCLEAR results display override');

        // Wait for the results display function to be available
        const waitForResultsDisplay = setInterval(() => {
            if (typeof window.resultsDisplay === 'function') {
                clearInterval(waitForResultsDisplay);

                // Store the original function
                const originalResultsDisplay = window.resultsDisplay;

                // Replace with our version
                window.resultsDisplay = function() {
                    log('Results display called');

                    // If we have a forced number, use it
                    if (forcedNumber !== null) {
                        log(`Using forced number for results: ${forcedNumber}`);

                        // Force the roulette number
                        window.rouletteNumber = parseInt(forcedNumber);

                        // Call the original function
                        const result = originalResultsDisplay.apply(this, arguments);

                        // Make sure the displayed number is correct
                        setTimeout(() => {
                            try {
                                // Update the roll number display
                                const rollNumberElement = document.querySelector('.roll-number');
                                if (rollNumberElement) {
                                    rollNumberElement.innerHTML = forcedNumber;
                                    log(`Updated roll number display to ${forcedNumber}`);
                                }

                                // Update the high/low display
                                const highLowElement = document.querySelector('.high-low');
                                if (highLowElement) {
                                    if (parseInt(forcedNumber) === 0) {
                                        highLowElement.innerHTML = '';
                                    } else if (parseInt(forcedNumber) < 19) {
                                        highLowElement.innerHTML = 'LOW';
                                    } else {
                                        highLowElement.innerHTML = 'HIGH';
                                    }
                                }

                                // Update the odd/even display
                                const oddEvenElement = document.querySelector('.odd-even');
                                if (oddEvenElement) {
                                    if (parseInt(forcedNumber) === 0) {
                                        oddEvenElement.innerHTML = '';
                                    } else if (parseInt(forcedNumber) % 2 === 1) {
                                        oddEvenElement.innerHTML = 'ODD';
                                    } else {
                                        oddEvenElement.innerHTML = 'EVEN';
                                    }
                                }

                                // Also update the roll history
                                setTimeout(() => {
                                    // Force the roll history to show our number
                                    if (Array.isArray(window.rolledNumbersArray) && window.rolledNumbersArray.length > 0) {
                                        // Replace the first element with our forced number
                                        window.rolledNumbersArray[0] = parseInt(forcedNumber);

                                        // Update the color
                                        if (Array.isArray(window.rolledNumbersColorArray) && window.rolledNumbersColorArray.length > 0) {
                                            window.rolledNumbersColorArray[0] = forcedColor || getNumberColor(forcedNumber);
                                        }

                                        // Update the display
                                        for (let i = 0; i < window.rolledNumbersArray.length && i < 5; i++) {
                                            let rolledNumberIndex = i + 1;
                                            const rollElement = document.querySelector(`.roll${rolledNumberIndex}`);

                                            if (rollElement) {
                                                if (i === 0) {
                                                    // This is the most recent roll, set it to our forced number
                                                    rollElement.innerHTML = forcedNumber;

                                                    // Update the color
                                                    rollElement.classList.remove("roll-red", "roll-black", "roll-green");
                                                    rollElement.classList.add(`roll-${forcedColor || getNumberColor(forcedNumber)}`);
                                                }
                                            }
                                        }

                                        log('Updated roll history display');

                                        // Update draw number display after forced number
                                        if (typeof updateDrawNumberDisplay === 'function') {
                                            updateDrawNumberDisplay();
                                            log('Updated draw number display after forced number');
                                        }
                                    }
                                }, 5000); // Wait for the original function to update the roll history
                            } catch (err) {
                                error(`Error updating results display: ${err.message}`);
                            }
                        }, 100);

                        return result;
                    }

                    // Otherwise, call the original function
                    return originalResultsDisplay.apply(this, arguments);
                };

                log('Results display override complete');

                // Also override the lastRollDisplay function if it exists
                if (typeof window.lastRollDisplay === 'function') {
                    const originalLastRollDisplay = window.lastRollDisplay;

                    window.lastRollDisplay = function() {
                        log('Last roll display called');

                        // If we have a forced number, use it
                        if (forcedNumber !== null) {
                            log(`Using forced number for last roll display: ${forcedNumber}`);

                            // Force the roulette number
                            window.rouletteNumber = parseInt(forcedNumber);
                        }

                        // Call the original function
                        const result = originalLastRollDisplay.apply(this, arguments);

                        // Update draw number display after last roll display
                        setTimeout(() => {
                            if (typeof updateDrawNumberDisplay === 'function') {
                                updateDrawNumberDisplay();
                                log('Updated draw number display after last roll display');
                            }
                        }, 100);

                        return result;
                    };

                    log('Last roll display override complete');
                }
            }
        }, 100);
    }

    // Add a property lock to ensure rouletteNumber is always our forced number
    function applyPropertyLock() {
        log('Applying property lock to rouletteNumber');

        if (forcedNumber !== null) {
            // Store the original value
            let originalValue = window.rouletteNumber;

            // Define a new property that always returns our forced number
            Object.defineProperty(window, 'rouletteNumber', {
                get: function() {
                    if (forcedNumber !== null) {
                        return parseInt(forcedNumber);
                    }
                    return originalValue;
                },
                set: function(value) {
                    log(`Attempt to set rouletteNumber to ${value}`);
                    if (forcedNumber !== null) {
                        // If we have a forced number, ignore the set and keep our forced number
                        log(`Blocked attempt to set rouletteNumber to ${value}, keeping ${forcedNumber}`);
                        originalValue = parseInt(forcedNumber);
                    } else {
                        // Otherwise, allow the set
                        originalValue = value;
                    }
                },
                configurable: true // Allow this property to be redefined later
            });

            log(`Property lock applied to rouletteNumber, now always returns ${forcedNumber}`);
        }
    }

    // Initialize when the document is ready
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        initialize();

        // Apply property lock after a short delay to ensure everything is loaded
        setTimeout(applyPropertyLock, 1000);
    } else {
        document.addEventListener('DOMContentLoaded', () => {
            initialize();

            // Apply property lock after a short delay to ensure everything is loaded
            setTimeout(applyPropertyLock, 1000);
        });
    }

    // Expose some functions to the global scope for debugging
    window.forcedNumberHandler = {
        checkNow: checkForForcedNumber,
        applyPropertyLock: applyPropertyLock
    };
})();
