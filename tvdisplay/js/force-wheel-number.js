/**
 * Force Wheel Number
 * 
 * This script ONLY ensures the roulette wheel lands on the forced number.
 * It does not add any new features or change any existing functionality.
 */

(function() {
    // Wait for document to be ready
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🎯 Force Wheel Number: Initializing...');
        
        // Wait for the roulette wheel animation function to be available
        function waitForRouletteWheel() {
            if (typeof window.rouletteWheelAnimation === 'function') {
                overrideRouletteWheelAnimation();
            } else {
                setTimeout(waitForRouletteWheel, 100);
            }
        }
        
        // Override the roulette wheel animation function
        function overrideRouletteWheelAnimation() {
            // Store the original function
            const originalRouletteWheelAnimation = window.rouletteWheelAnimation;
            
            // Replace with our version
            window.rouletteWheelAnimation = function() {
                // Check if we have a forced number
                if (window.manualWinningNumber !== undefined && window.manualWinningNumber !== null) {
                    const forcedNumber = parseInt(window.manualWinningNumber);
                    console.log(`🎯 Force Wheel Number: Forcing wheel to land on ${forcedNumber}`);
                    
                    // Force the roulette number
                    window.rouletteNumber = forcedNumber;
                    
                    // Call the original function to handle the animation
                    const result = originalRouletteWheelAnimation.apply(this, arguments);
                    
                    // After the animation starts, override the ball position
                    setTimeout(function() {
                        // Find the index of the forced number in the wheel
                        let targetIndex = -1;
                        
                        // First try to find it in the rouletteNumbersArray
                        if (window.rouletteNumbersArray && Array.isArray(window.rouletteNumbersArray)) {
                            for (let i = 0; i < window.rouletteNumbersArray.length; i++) {
                                if (window.rouletteNumbersArray[i] === forcedNumber) {
                                    targetIndex = i;
                                    break;
                                }
                            }
                        }
                        
                        // If not found, use a fallback method
                        if (targetIndex === -1) {
                            // European roulette wheel layout
                            const wheelLayout = [0, 32, 15, 19, 4, 21, 2, 25, 17, 34, 6, 27, 13, 36, 11, 30, 8, 23, 10, 5, 24, 16, 33, 1, 20, 14, 31, 9, 22, 18, 29, 7, 28, 12, 35, 3, 26];
                            for (let i = 0; i < wheelLayout.length; i++) {
                                if (wheelLayout[i] === forcedNumber) {
                                    targetIndex = i;
                                    break;
                                }
                            }
                        }
                        
                        // If still not found, use a simple calculation
                        if (targetIndex === -1) {
                            targetIndex = forcedNumber % 37;
                        }
                        
                        // Calculate the degree for the ball to land on
                        const degree = (360 / (window.rouletteNumbersAmount || 37)) * targetIndex;
                        
                        // Create and inject the animation style
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
                        console.log(`🎯 Force Wheel Number: Set ball to land at ${degree} degrees (index ${targetIndex})`);
                        
                        // Ensure the roulette number stays correct
                        const enforcer = setInterval(function() {
                            if (window.rouletteNumber !== forcedNumber) {
                                window.rouletteNumber = forcedNumber;
                            }
                        }, 100);
                        
                        // Clear the enforcer after the animation is complete
                        setTimeout(function() {
                            clearInterval(enforcer);
                        }, 10000);
                    }, 10);
                    
                    return result;
                }
                
                // If no forced number, just call the original function
                return originalRouletteWheelAnimation.apply(this, arguments);
            };
            
            console.log('🎯 Force Wheel Number: Wheel animation override complete');
        }
        
        // Start waiting for the roulette wheel animation function
        waitForRouletteWheel();
    });
})();
