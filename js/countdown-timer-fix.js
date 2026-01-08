/**
 * Countdown Timer Visibility Fix
 * Ensures countdown timer has proper classes and styling
 */

(function() {
    'use strict';
    
    console.log('🕐 Countdown Timer Fix Loading...');
    
    /**
     * Fix countdown timer classes and styling
     */
    function fixCountdownTimer() {
        // Find countdown timer elements
        const countdownTime = document.getElementById('countdown-time');
        const countdownContainer = document.querySelector('.countdown-timer-floating');
        const countdownLabel = document.querySelector('.countdown-label');
        const syncIndicator = document.querySelector('.countdown-sync-indicator');
        
        let fixed = false;
        
        // Fix countdown time element
        if (countdownTime) {
            // Add the proper class if missing
            if (!countdownTime.classList.contains('countdown-time')) {
                countdownTime.classList.add('countdown-time');
                console.log('✅ Added countdown-time class');
                fixed = true;
            }
            
            // Ensure proper styling
            countdownTime.style.color = '#ffffff';
            countdownTime.style.fontSize = '28px';
            countdownTime.style.fontWeight = 'bold';
            countdownTime.style.fontFamily = "'Orbitron', 'Courier New', monospace";
            countdownTime.style.textShadow = '0 0 10px rgba(255, 215, 0, 0.6), 0 2px 4px rgba(0, 0, 0, 0.8)';
            countdownTime.style.margin = '4px 0';
            countdownTime.style.letterSpacing = '2px';
            countdownTime.style.background = 'transparent';
            
            console.log('✅ Applied inline styles to countdown time');
            fixed = true;
        }
        
        // Fix countdown container
        if (countdownContainer) {
            countdownContainer.style.background = 'linear-gradient(135deg, rgba(26, 26, 26, 0.95) 0%, rgba(45, 45, 45, 0.95) 100%)';
            countdownContainer.style.border = '2px solid #FFD700';
            countdownContainer.style.borderRadius = '12px';
            countdownContainer.style.padding = '12px 20px';
            countdownContainer.style.boxShadow = '0 8px 32px rgba(255, 215, 0, 0.3), 0 4px 16px rgba(0, 0, 0, 0.5)';
            countdownContainer.style.zIndex = '9999';
            
            console.log('✅ Applied styles to countdown container');
            fixed = true;
        }
        
        // Fix countdown label
        if (countdownLabel) {
            countdownLabel.style.color = '#FFD700';
            countdownLabel.style.fontSize = '12px';
            countdownLabel.style.fontWeight = 'bold';
            countdownLabel.style.letterSpacing = '1px';
            countdownLabel.style.marginBottom = '6px';
            countdownLabel.style.textTransform = 'uppercase';
            countdownLabel.style.textShadow = '0 1px 2px rgba(0, 0, 0, 0.8)';
            
            console.log('✅ Applied styles to countdown label');
            fixed = true;
        }
        
        // Fix sync indicator
        if (syncIndicator) {
            syncIndicator.style.color = '#FFD700';
            syncIndicator.style.fontSize = '10px';
            syncIndicator.style.marginTop = '6px';
            syncIndicator.style.opacity = '0.6';
            syncIndicator.style.textShadow = '0 1px 2px rgba(0, 0, 0, 0.8)';
            
            console.log('✅ Applied styles to sync indicator');
            fixed = true;
        }
        
        if (fixed) {
            console.log('🎨 Countdown timer styling fixed');
        }
        
        return fixed;
    }
    
    /**
     * Monitor timer state and apply appropriate classes
     */
    function monitorTimerState() {
        const countdownTime = document.getElementById('countdown-time');
        if (!countdownTime) return;
        
        const timeText = countdownTime.textContent || countdownTime.innerText;
        const timeParts = timeText.split(':');
        
        if (timeParts.length === 2) {
            const minutes = parseInt(timeParts[0]) || 0;
            const seconds = parseInt(timeParts[1]) || 0;
            const totalSeconds = minutes * 60 + seconds;
            
            // Remove existing state classes
            countdownTime.classList.remove('warning', 'critical');
            const container = countdownTime.closest('.countdown-timer-floating');
            if (container) {
                container.classList.remove('warning', 'critical');
            }
            
            // Apply appropriate state class
            if (totalSeconds <= 10 && totalSeconds > 0) {
                // Critical state (last 10 seconds)
                countdownTime.classList.add('critical');
                if (container) container.classList.add('critical');
                
                // Override styles for critical state
                countdownTime.style.color = '#f44336';
                countdownTime.style.textShadow = '0 0 20px rgba(244, 67, 54, 0.9), 0 2px 4px rgba(0, 0, 0, 0.8)';
                
            } else if (totalSeconds <= 60) {
                // Warning state (last minute)
                countdownTime.classList.add('warning');
                if (container) container.classList.add('warning');
                
                // Override styles for warning state
                countdownTime.style.color = '#ff9800';
                countdownTime.style.textShadow = '0 0 15px rgba(255, 152, 0, 0.8), 0 2px 4px rgba(0, 0, 0, 0.8)';
                
            } else {
                // Normal state
                countdownTime.style.color = '#ffffff';
                countdownTime.style.textShadow = '0 0 10px rgba(255, 215, 0, 0.6), 0 2px 4px rgba(0, 0, 0, 0.8)';
            }
        }
    }
    
    /**
     * Initialize countdown timer fix
     */
    function init() {
        console.log('🚀 Initializing Countdown Timer Fix...');
        
        // Fix timer immediately
        fixCountdownTimer();
        
        // Monitor for timer changes
        const observer = new MutationObserver(function(mutations) {
            let shouldFix = false;
            let shouldMonitor = false;
            
            mutations.forEach(function(mutation) {
                // Check for new timer elements
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) {
                        if (node.id === 'countdown-time' || 
                            node.classList.contains('countdown-timer-floating') ||
                            node.querySelector && node.querySelector('#countdown-time, .countdown-timer-floating')) {
                            shouldFix = true;
                        }
                    }
                });
                
                // Check for timer content changes
                if (mutation.type === 'childList' || mutation.type === 'characterData') {
                    const target = mutation.target;
                    if (target.id === 'countdown-time' || 
                        target.closest && target.closest('#countdown-time')) {
                        shouldMonitor = true;
                    }
                }
            });
            
            if (shouldFix) {
                setTimeout(fixCountdownTimer, 100);
            }
            
            if (shouldMonitor) {
                setTimeout(monitorTimerState, 50);
            }
        });
        
        // Start observing
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            characterData: true
        });
        
        // Monitor timer state every second
        setInterval(monitorTimerState, 1000);
        
        console.log('✅ Countdown Timer Fix initialized successfully!');
    }
    
    /**
     * Test function to verify timer visibility
     */
    function testTimerVisibility() {
        const countdownTime = document.getElementById('countdown-time');
        if (!countdownTime) {
            console.warn('⚠️ Countdown timer element not found');
            return false;
        }
        
        const computedStyle = window.getComputedStyle(countdownTime);
        const isVisible = computedStyle.display !== 'none' && 
                         computedStyle.visibility !== 'hidden' && 
                         computedStyle.opacity !== '0';
        
        console.log('🔍 Timer Visibility Test:');
        console.log('  Element found:', !!countdownTime);
        console.log('  Display:', computedStyle.display);
        console.log('  Visibility:', computedStyle.visibility);
        console.log('  Opacity:', computedStyle.opacity);
        console.log('  Color:', computedStyle.color);
        console.log('  Background:', computedStyle.backgroundColor);
        console.log('  Font Size:', computedStyle.fontSize);
        console.log('  Is Visible:', isVisible);
        
        return isVisible;
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    // Also initialize after a delay to catch dynamically added timers
    setTimeout(init, 2000);
    setTimeout(init, 5000);
    
    // Expose test function globally
    window.testTimerVisibility = testTimerVisibility;
    
    // Auto-test after initialization
    setTimeout(() => {
        testTimerVisibility();
    }, 3000);
    
    console.log('🕐 Countdown Timer Fix loaded!');
    
})();
