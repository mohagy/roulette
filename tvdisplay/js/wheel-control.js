/**
 * Wheel Control - Connects the ForcedNumberSync with the roulette wheel
 * This file bridges the communication between the control panel and the wheel
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('Wheel Control: Initializing wheel control system');

    // Wait for ForcedNumberSync to be available
    const waitForForcedNumberSync = setInterval(() => {
        if (typeof ForcedNumberSync !== 'undefined') {
            clearInterval(waitForForcedNumberSync);
            initializeWheelControl();
        }
    }, 100);

    function initializeWheelControl() {
        console.log('Wheel Control: Connecting forced number system to wheel');

        // Listen for forced number changes
        ForcedNumberSync.onForcedNumberChanged(function(number, color) {
            console.log(`Wheel Control: Received forced number ${number} (${color})`);

            if (number !== null) {
                // Set the forced number in the global scope
                window.manualWinningNumber = number;

                // Update the UI to show the forced number
                updateForcedNumberDisplay(number, color);

                console.log('Wheel Control: Manual winning number set to', number);

                // Also update the wheel system if it's already initialized
                if (typeof window.rouletteNumber !== 'undefined') {
                    console.log('Wheel Control: Updating wheel system with forced number');

                    // This will trigger the wheel override system
                    if (typeof window.applyPropertyLock === 'function') {
                        window.applyPropertyLock();
                    }

                    if (typeof window.waitForRouletteInit === 'function') {
                        window.waitForRouletteInit();
                    }
                }
            } else {
                // Clear the forced number
                window.manualWinningNumber = null;

                // Hide the forced number display
                updateForcedNumberDisplay(null, null);

                console.log('Wheel Control: Manual winning number cleared');
            }
        });

        // Listen for mode changes
        ForcedNumberSync.onModeChanged(function(mode) {
            console.log(`Wheel Control: Mode changed to ${mode}`);

            if (mode === 'automatic') {
                // Clear the forced number when switching to automatic mode
                window.manualWinningNumber = null;
                updateForcedNumberDisplay(null, null);
                console.log('Wheel Control: Cleared manual winning number due to mode change');
            }
        });

        // Check for forced numbers immediately
        ForcedNumberSync.checkNow();

        // Also connect to the DrawControlConnection if available
        if (typeof DrawControlConnection !== 'undefined') {
            console.log('Wheel Control: Connecting to DrawControlConnection');

            // Initialize if not already initialized
            if (typeof DrawControlConnection.initialize === 'function') {
                DrawControlConnection.initialize({
                    checkInterval: 3000,
                    debug: true
                });
            }

            // Listen for manual number detection
            if (typeof DrawControlConnection.onManualNumberDetected === 'function') {
                DrawControlConnection.onManualNumberDetected(function(data) {
                    console.log('Wheel Control: Received manual number from DrawControlConnection', data);

                    // Update the forced number
                    window.manualWinningNumber = data.number;
                    updateForcedNumberDisplay(data.number, data.color);
                });
            }

            // Listen for revert to automatic
            if (typeof DrawControlConnection.onRevertToAutomatic === 'function') {
                DrawControlConnection.onRevertToAutomatic(function() {
                    console.log('Wheel Control: Received revert to automatic from DrawControlConnection');

                    // Clear the forced number
                    window.manualWinningNumber = null;
                    updateForcedNumberDisplay(null, null);
                });
            }
        }
    }

    // Update the forced number display on the UI (but keep it hidden from players)
    function updateForcedNumberDisplay(number, color) {
        const forcedNumberIndicator = document.getElementById('forced-number-indicator');
        const forcedNumberValue = document.getElementById('forced-number-value');

        if (forcedNumberIndicator && forcedNumberValue) {
            if (number !== null) {
                // Update the value but keep it hidden
                forcedNumberValue.textContent = number;

                // Update the color (even though it's hidden)
                forcedNumberIndicator.classList.remove('red', 'black', 'green');
                if (color) {
                    forcedNumberIndicator.classList.add(color);
                }

                // Ensure the indicator remains hidden
                forcedNumberIndicator.style.display = 'none !important';
                console.log('Wheel Control: Updated forced number indicator with number', number, '(kept hidden)');
            } else {
                // Keep the indicator hidden
                forcedNumberIndicator.style.display = 'none !important';
                console.log('Wheel Control: Cleared forced number indicator (kept hidden)');
            }
        }
    }
});