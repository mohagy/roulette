/**
 * Quick Fix for Loading Indicator Issue
 * Run this in browser console to immediately fix the loading indicator
 */

(function() {
    console.log('🔧 LOADING INDICATOR FIX: Starting...');
    
    // Method 1: Use LiveStreamPlayer API if available
    if (window.LiveStreamPlayer && window.LiveStreamPlayer.forceHideLoading) {
        console.log('🔧 Using LiveStreamPlayer API to hide loading indicator');
        window.LiveStreamPlayer.forceHideLoading();
    }
    
    // Method 2: Direct DOM manipulation
    const loadingIndicators = document.querySelectorAll('.live-stream-loading');
    if (loadingIndicators.length > 0) {
        console.log('🔧 Found', loadingIndicators.length, 'loading indicators, hiding them');
        loadingIndicators.forEach(indicator => {
            indicator.style.display = 'none';
            indicator.style.visibility = 'hidden';
            indicator.style.opacity = '0';
        });
    }
    
    // Method 3: Find by text content
    const allDivs = document.querySelectorAll('div');
    allDivs.forEach(div => {
        if (div.textContent && div.textContent.includes('Loading stream')) {
            console.log('🔧 Found loading text, hiding element');
            div.style.display = 'none';
            div.style.visibility = 'hidden';
            div.style.opacity = '0';
        }
    });
    
    // Method 4: Find in live stream player container
    const playerContainer = document.querySelector('.live-stream-player');
    if (playerContainer) {
        const loadingInPlayer = playerContainer.querySelector('[style*="Loading stream"]');
        if (loadingInPlayer) {
            console.log('🔧 Found loading indicator in player container');
            loadingInPlayer.style.display = 'none';
        }
        
        // Also check for any element with loading text
        const allPlayerElements = playerContainer.querySelectorAll('*');
        allPlayerElements.forEach(element => {
            if (element.textContent && element.textContent.includes('Loading stream')) {
                console.log('🔧 Hiding loading element in player:', element);
                element.style.display = 'none';
                element.style.visibility = 'hidden';
                element.style.opacity = '0';
            }
        });
    }
    
    // Method 5: Check for YouTube iframe and hide loading if iframe exists
    const youtubeIframe = document.querySelector('.youtube-iframe');
    if (youtubeIframe) {
        console.log('🔧 YouTube iframe found, ensuring loading indicator is hidden');
        
        // Find parent container and hide any loading indicators
        let parent = youtubeIframe.parentElement;
        while (parent) {
            const loadingElements = parent.querySelectorAll('.live-stream-loading, [class*="loading"]');
            loadingElements.forEach(element => {
                if (element.textContent && element.textContent.includes('Loading')) {
                    console.log('🔧 Hiding loading element near YouTube iframe');
                    element.style.display = 'none';
                }
            });
            parent = parent.parentElement;
            if (parent === document.body) break;
        }
    }
    
    console.log('✅ LOADING INDICATOR FIX: Complete!');
    
    // Return a function that can be called again if needed
    return function() {
        console.log('🔧 Re-running loading indicator fix...');
        arguments.callee();
    };
})();

// Make it available globally for easy console access
window.fixLoadingIndicator = function() {
    console.log('🔧 Manual loading indicator fix triggered');
    
    // Hide all loading indicators
    const loadingElements = document.querySelectorAll('.live-stream-loading, [class*="loading"]');
    loadingElements.forEach(element => {
        if (element.textContent && element.textContent.includes('Loading')) {
            element.style.display = 'none !important';
            element.style.visibility = 'hidden !important';
            element.style.opacity = '0 !important';
            console.log('🔧 Hidden loading element:', element);
        }
    });
    
    // Use API if available
    if (window.LiveStreamPlayer && window.LiveStreamPlayer.forceHideLoading) {
        window.LiveStreamPlayer.forceHideLoading();
    }
    
    console.log('✅ Manual fix complete!');
};

console.log('💡 Loading indicator fix loaded! Use fixLoadingIndicator() in console if needed.');

// IMMEDIATE EXECUTION: Run fix every 100ms for the first 10 seconds
let immediateFixCount = 0;
const immediateFixInterval = setInterval(() => {
    immediateFixCount++;

    // Find and hide all loading indicators
    const loadingElements = document.querySelectorAll('.live-stream-loading');
    loadingElements.forEach(element => {
        if (element.textContent && element.textContent.includes('Loading')) {
            element.style.display = 'none !important';
            element.style.visibility = 'hidden !important';
            element.style.opacity = '0 !important';
            element.classList.add('force-hidden');
            console.log('🔧 IMMEDIATE FIX: Hidden loading element #' + immediateFixCount);
        }
    });

    // Also check for YouTube iframes and hide loading if found
    const youtubeIframes = document.querySelectorAll('.youtube-iframe');
    if (youtubeIframes.length > 0) {
        console.log('🔧 IMMEDIATE FIX: YouTube iframe detected, forcing loading hide');
        loadingElements.forEach(element => {
            element.style.display = 'none !important';
            element.style.visibility = 'hidden !important';
            element.style.opacity = '0 !important';
            element.classList.add('force-hidden');
        });
    }

    // Stop after 100 attempts (10 seconds)
    if (immediateFixCount >= 100) {
        clearInterval(immediateFixInterval);
        console.log('🔧 IMMEDIATE FIX: Stopped after 100 attempts');
    }
}, 100);
