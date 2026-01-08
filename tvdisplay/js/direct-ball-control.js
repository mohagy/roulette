/**
 * Direct Ball Control
 * 
 * This script directly controls the ball position on the roulette wheel.
 * It uses the most aggressive approach possible to ensure the ball lands
 * on the forced number.
 */

(function() {
    // Configuration
    const config = {
        debug: true,
        wheelNumbers: [0, 32, 15, 19, 4, 21, 2, 25, 17, 34, 6, 27, 13, 36, 11, 30, 8, 23, 10, 5, 24, 16, 33, 1, 20, 14, 31, 9, 22, 18, 29, 7, 28, 12, 35, 3, 26]
    };
    
    // Log messages if debug is enabled
    function log(message) {
        if (config.debug) {
            console.log(`🔴 DIRECT BALL CONTROL: ${message}`);
        }
    }
    
    // Log warnings
    function warn(message) {
        console.warn(`⚠️ DIRECT BALL CONTROL: ${message}`);
    }
    
    // Find the index of a number on the wheel
    function findNumberIndex(number) {
        // First try the rouletteNumbersArray if available
        if (window.rouletteNumbersArray && Array.isArray(window.rouletteNumbersArray)) {
            for (let i = 0; i < window.rouletteNumbersArray.length; i++) {
                if (window.rouletteNumbersArray[i] === number) {
                    log(`Found number ${number} at index ${i} in rouletteNumbersArray`);
                    return i;
                }
            }
        }
        
        // Fall back to the hardcoded wheel layout
        for (let i = 0; i < config.wheelNumbers.length; i++) {
            if (config.wheelNumbers[i] === number) {
                log(`Found number ${number} at index ${i} in hardcoded wheel layout`);
                return i;
            }
        }
        
        // Last resort: use modulo
        warn(`Could not find number ${number} in wheel layout, using fallback`);
        return number % 37;
    }
    
    // Create a custom animation for the ball
    function createBallAnimation(number) {
        // Find the index of the number on the wheel
        const index = findNumberIndex(number);
        
        // Calculate the degree for the ball to land on
        const degree = (360 / 37) * index;
        
        log(`Creating animation for number ${number} (index ${index}) at ${degree} degrees`);
        
        // Create the animation style
        const style = document.createElement('style');
        style.id = 'direct-ball-animation';
        style.textContent = `
        @-webkit-keyframes ball-container-animation {
            0% { transform: rotate(1440deg); }
            100% { transform: rotate(${degree}deg); }
        }
        @keyframes ball-container-animation {
            0% { transform: rotate(1440deg); }
            100% { transform: rotate(${degree}deg); }
        }`;
        
        // Remove any existing animation style
        const existingStyle = document.getElementById('direct-ball-animation');
        if (existingStyle) {
            existingStyle.remove();
        }
        
        // Add the new style to the document
        document.head.appendChild(style);
        
        log(`Ball animation created for ${number}`);
    }
    
    // Override the spin button click handler
    function overrideSpinButton() {
        log('Setting up spin button override');
        
        // Wait for jQuery to be available
        if (typeof $ === 'undefined') {
            setTimeout(overrideSpinButton, 100);
            return;
        }
        
        // Find the spin button
        const spinButton = $('.button-spin');
        if (spinButton.length === 0) {
            warn('Spin button not found, will try again');
            setTimeout(overrideSpinButton, 100);
            return;
        }
        
        // Store the original click handler
        const originalClickHandlers = $._data(spinButton[0], 'events')?.click?.slice() || [];
        
        // Remove all existing click handlers
        spinButton.off('click');
        
        // Add our new click handler
        spinButton.on('click', function(event) {
            log('Spin button clicked');
            
            // Check if we have a forced number
            if (window.manualWinningNumber !== undefined && window.manualWinningNumber !== null) {
                const forcedNumber = parseInt(window.manualWinningNumber);
                warn(`FORCING WHEEL TO LAND ON ${forcedNumber}`);
                
                // Set the roulette number
                window.rouletteNumber = forcedNumber;
                
                // Create the ball animation before the wheel starts spinning
                createBallAnimation(forcedNumber);
                
                // Set up a continuous enforcer to ensure the number stays correct
                const enforcer = setInterval(function() {
                    if (window.rouletteNumber !== forcedNumber) {
                        warn(`Correcting rouletteNumber from ${window.rouletteNumber} to ${forcedNumber}`);
                        window.rouletteNumber = forcedNumber;
                    }
                }, 50);
                
                // Clear the enforcer after 15 seconds
                setTimeout(function() {
                    clearInterval(enforcer);
                }, 15000);
            }
            
            // Call the original handlers
            for (let i = 0; i < originalClickHandlers.length; i++) {
                originalClickHandlers[i].handler.apply(this, arguments);
            }
        });
        
        log('Spin button override complete');
    }
    
    // Override the roulette wheel animation function
    function overrideWheelAnimation() {
        log('Setting up wheel animation override');
        
        // Wait for the roulette wheel animation function to be available
        if (typeof window.rouletteWheelAnimation !== 'function') {
            setTimeout(overrideWheelAnimation, 100);
            return;
        }
        
        // Store the original function
        const originalRouletteWheelAnimation = window.rouletteWheelAnimation;
        
        // Replace with our version
        window.rouletteWheelAnimation = function() {
            log('Wheel animation function called');
            
            // Check if we have a forced number
            if (window.manualWinningNumber !== undefined && window.manualWinningNumber !== null) {
                const forcedNumber = parseInt(window.manualWinningNumber);
                warn(`FORCING WHEEL ANIMATION TO USE ${forcedNumber}`);
                
                // Force the roulette number
                window.rouletteNumber = forcedNumber;
                
                // Create the ball animation
                createBallAnimation(forcedNumber);
            }
            
            // Call the original function
            return originalRouletteWheelAnimation.apply(this, arguments);
        };
        
        log('Wheel animation override complete');
    }
    
    // Initialize when the document is ready
    function initialize() {
        log('Initializing');
        
        // Override the spin button
        overrideSpinButton();
        
        // Override the wheel animation
        overrideWheelAnimation();
        
        log('Initialization complete');
    }
    
    // Start initialization when the document is ready
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        initialize();
    } else {
        document.addEventListener('DOMContentLoaded', initialize);
    }
})();
