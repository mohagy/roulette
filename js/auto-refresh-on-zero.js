/**
 * Simple Auto Refresh - Page refresh when Georgetown countdown reaches zero
 * 
 * This script ONLY monitors the server countdown and refreshes the page when it hits zero.
 * No timers, no complex logic, just simple server monitoring.
 */

(function() {
    'use strict';
    
    let refreshTriggered = false;
    let lastCountdown = null;
    
    console.log('🔄 Simple auto-refresh loaded - monitoring server countdown');
    
    // Simple server monitoring function
    async function checkServerCountdown() {
        if (refreshTriggered) return;
        
        try {
            const response = await fetch('php/get_georgetown_time.php?t=' + Date.now());
            const data = await response.json();
            
            if (data && data.status === 'success' && data.countdown) {
                const countdown = data.countdown.total_seconds_remaining;
                
                // Log countdown changes
                if (lastCountdown !== countdown) {
                    console.log(`⏰ Server countdown: ${countdown}s (${data.countdown.display_format})`);
                    
                    // DETECT CYCLE RESET: countdown jumps from low to high = zero was reached
                    if (lastCountdown !== null && lastCountdown <= 5 && countdown > 150) {
                        refreshTriggered = true;
                        console.log(`🎯 CYCLE RESET! ${lastCountdown}s → ${countdown}s - REFRESHING PAGE!`);
                        window.location.reload(true);
                        return;
                    }
                    
                    lastCountdown = countdown;
                }
                
                // Alert when close to zero
                if (countdown <= 5 && countdown > 1) {
                    console.log(`🚨 ${countdown} seconds until refresh!`);
                }
                
                // REFRESH when countdown reaches 1 or 0
                if (countdown <= 1) {
                    refreshTriggered = true;
                    console.log(`🎯 COUNTDOWN ZERO! Refreshing page...`);
                    window.location.reload(true);
                }
            }
        } catch (error) {
            // Ignore errors silently
        }
    }
    
    // Start monitoring immediately
    console.log('👁️ Starting server countdown monitoring every 1 second');
    
    // Check immediately
    checkServerCountdown();
    
    // Then check every second
    setInterval(checkServerCountdown, 1000);
    
    // Log when page refreshes
    window.addEventListener('beforeunload', function() {
        if (refreshTriggered) {
            console.log('📄 PAGE REFRESHING - AUTO-REFRESH SUCCESS!');
        }
    });
    
})();
