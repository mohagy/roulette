/**
 * Wheel Numbers Fix
 * 
 * This script ensures the roulette wheel numbers are correctly defined.
 */

(function() {
    // Wait for document to be ready
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🔢 Wheel Numbers Fix: Initializing...');
        
        // European roulette wheel layout (clockwise)
        const correctWheelNumbers = [0, 32, 15, 19, 4, 21, 2, 25, 17, 34, 6, 27, 13, 36, 11, 30, 8, 23, 10, 5, 24, 16, 33, 1, 20, 14, 31, 9, 22, 18, 29, 7, 28, 12, 35, 3, 26];
        
        // Check and fix the wheel numbers
        function checkAndFixWheelNumbers() {
            // Check if rouletteNumbersArray is defined
            if (typeof window.rouletteNumbersArray === 'undefined') {
                console.log('🔢 Wheel Numbers Fix: rouletteNumbersArray not defined, creating it');
                window.rouletteNumbersArray = correctWheelNumbers.slice();
                window.rouletteNumbersAmount = 37;
            } else if (!Array.isArray(window.rouletteNumbersArray) || window.rouletteNumbersArray.length !== 37) {
                console.log('🔢 Wheel Numbers Fix: rouletteNumbersArray is invalid, fixing it');
                window.rouletteNumbersArray = correctWheelNumbers.slice();
                window.rouletteNumbersAmount = 37;
            } else {
                // Check if 0 is in the correct position
                if (window.rouletteNumbersArray[0] !== 0) {
                    console.log('🔢 Wheel Numbers Fix: 0 is not at index 0, fixing wheel layout');
                    window.rouletteNumbersArray = correctWheelNumbers.slice();
                }
            }
            
            // Ensure rouletteNumbersAmount is correct
            if (typeof window.rouletteNumbersAmount === 'undefined' || window.rouletteNumbersAmount !== 37) {
                console.log('🔢 Wheel Numbers Fix: rouletteNumbersAmount is incorrect, fixing it');
                window.rouletteNumbersAmount = 37;
            }
            
            console.log('🔢 Wheel Numbers Fix: Wheel numbers checked and fixed if needed');
        }
        
        // Check immediately
        checkAndFixWheelNumbers();
        
        // Check again after a delay to ensure it's not overwritten
        setTimeout(checkAndFixWheelNumbers, 1000);
        setTimeout(checkAndFixWheelNumbers, 2000);
        
        console.log('🔢 Wheel Numbers Fix: Initialization complete');
    });
})();
