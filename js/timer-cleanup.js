/**
 * Timer Cleanup
 * 
 * This script runs after all other scripts to ensure the timer is properly set up
 * and to clean up any remnants of old timer implementations.
 */

(function() {
    console.log('[TimerCleanup] Initializing timer cleanup...');
    
    // Function to clean up the timer
    function cleanupTimer() {
        console.log('[TimerCleanup] Running timer cleanup...');
        
        // Get the countdown timer element
        const countdownTimer = document.getElementById('countdown-timer');
        if (!countdownTimer) {
            console.error('[TimerCleanup] Countdown timer element not found');
            return;
        }
        
        // Ensure it has the synchronized class
        if (!countdownTimer.classList.contains('synchronized')) {
            countdownTimer.classList.add('synchronized');
            console.log('[TimerCleanup] Added synchronized class to countdown timer');
        }
        
        // Remove any other classes that might be interfering
        const classesToRemove = ['timer-warning', 'timer-expired', 'timer-reset'];
        classesToRemove.forEach(className => {
            if (countdownTimer.classList.contains(className)) {
                countdownTimer.classList.remove(className);
                console.log(`[TimerCleanup] Removed ${className} class from countdown timer`);
            }
        });
        
        // Clear any inline styles that might be interfering
        if (countdownTimer.hasAttribute('style')) {
            countdownTimer.removeAttribute('style');
            console.log('[TimerCleanup] Removed inline styles from countdown timer');
        }
        
        // Check if the timer has content - if not, force an update
        if (!countdownTimer.textContent.trim() && window.GeorgetownTimeSync) {
            const secondsUntilNextDraw = window.GeorgetownTimeSync.getSecondsUntilNextDraw();
            if (secondsUntilNextDraw !== null) {
                const minutes = Math.floor(secondsUntilNextDraw / 60);
                const seconds = secondsUntilNextDraw % 60;
                countdownTimer.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                console.log('[TimerCleanup] Forced update of countdown timer content');
            }
        }
        
        // Check for any other timer elements that might be interfering
        const timerContainer = document.querySelector('.timer-container');
        if (timerContainer) {
            // Check for any other timer elements in the container
            const otherTimers = timerContainer.querySelectorAll('.timer-display:not(#countdown-timer)');
            if (otherTimers.length > 0) {
                console.log(`[TimerCleanup] Found ${otherTimers.length} other timer elements, removing them`);
                otherTimers.forEach(timer => {
                    timer.remove();
                });
            }
            
            // Check for any other elements that might be interfering
            const otherElements = timerContainer.querySelectorAll('*:not(.timer-label):not(#countdown-timer)');
            if (otherElements.length > 0) {
                console.log(`[TimerCleanup] Found ${otherElements.length} other elements in timer container, removing them`);
                otherElements.forEach(element => {
                    if (!element.classList.contains('timer-label') && element.id !== 'countdown-timer') {
                        element.remove();
                    }
                });
            }
        }
        
        console.log('[TimerCleanup] Timer cleanup completed successfully');
    }
    
    // Run the cleanup function when the window is fully loaded
    if (document.readyState === 'complete') {
        cleanupTimer();
    } else {
        window.addEventListener('load', cleanupTimer);
    }
    
    // Also run it after a short delay to ensure it catches any late initializations
    setTimeout(cleanupTimer, 1000);
    
    // And run it again after a longer delay to catch any very late initializations
    setTimeout(cleanupTimer, 3000);
})();
