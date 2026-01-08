/**
 * Disable Old Timer
 * 
 * This script disables the old timer implementation in scripts.js
 * to prevent conflicts with the new synchronized timer.
 */

(function() {
    console.log('[DisableOldTimer] Initializing timer disabler...');
    
    // Function to run when the DOM is fully loaded
    function disableOldTimer() {
        console.log('[DisableOldTimer] Disabling old timer implementation...');
        
        // Override the global timer variables and functions from scripts.js
        if (window.countdownInterval) {
            console.log('[DisableOldTimer] Clearing existing countdown interval');
            clearInterval(window.countdownInterval);
            window.countdownInterval = null;
        }
        
        // Override the startCountdown function
        window.startCountdown = function() {
            console.log('[DisableOldTimer] Blocked attempt to start old countdown');
            return false;
        };
        
        // Override the updateCountdownDisplay function
        window.updateCountdownDisplay = function() {
            console.log('[DisableOldTimer] Blocked attempt to update old countdown display');
            return false;
        };
        
        // Override any other timer-related functions
        window.calculateNextDrawTime = window.calculateNextDrawTime || function() {
            console.log('[DisableOldTimer] Blocked attempt to calculate next draw time with old method');
            return {
                timestamp: 0,
                secondsRemaining: 0
            };
        };
        
        // Make sure the countdown timer element is properly set up
        const countdownTimer = document.getElementById('countdown-timer');
        if (countdownTimer) {
            // Ensure it has the synchronized class
            if (!countdownTimer.classList.contains('synchronized')) {
                countdownTimer.classList.add('synchronized');
            }
            
            // Clear any existing content to prevent overlapping
            if (!countdownTimer.getAttribute('data-cleaned')) {
                countdownTimer.innerHTML = '';
                countdownTimer.setAttribute('data-cleaned', 'true');
            }
            
            console.log('[DisableOldTimer] Cleaned up countdown timer element');
        }
        
        // Create a MutationObserver to watch for changes to the countdown timer
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList' || mutation.type === 'attributes') {
                    // If the synchronized class is removed, add it back
                    if (!countdownTimer.classList.contains('synchronized')) {
                        countdownTimer.classList.add('synchronized');
                        console.log('[DisableOldTimer] Re-added synchronized class to countdown timer');
                    }
                }
            });
        });
        
        // Start observing the countdown timer for changes
        if (countdownTimer) {
            observer.observe(countdownTimer, { 
                childList: true,
                attributes: true,
                characterData: true,
                subtree: true
            });
            console.log('[DisableOldTimer] Started observing countdown timer for changes');
        }
        
        console.log('[DisableOldTimer] Old timer implementation disabled successfully');
    }
    
    // Run the disabler function when the DOM is loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', disableOldTimer);
    } else {
        disableOldTimer();
    }
    
    // Also run it after a short delay to ensure it catches any late initializations
    setTimeout(disableOldTimer, 500);
    
    // And run it again after a longer delay to catch any very late initializations
    setTimeout(disableOldTimer, 2000);
})();
