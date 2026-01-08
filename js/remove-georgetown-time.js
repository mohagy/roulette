/**
 * Remove Georgetown Time Display
 * This script specifically removes the Georgetown Time display from the DOM
 * and prevents it from being recreated, but ONLY on the main cashier interface.
 * It preserves the Georgetown Time display on the TV display page.
 */

(function() {
    console.log('[RemoveGeorgetownTime] Initializing removal of Georgetown time display...');
    
    // Check if we're on the TV display page
    const isTVDisplay = window.location.pathname.includes('/tvdisplay/') || 
                        document.body.classList.contains('tvdisplay');
    
    // Only proceed if we're NOT on the TV display page
    if (isTVDisplay) {
        console.log('[RemoveGeorgetownTime] TV display page detected, keeping Georgetown time display');
        return; // Exit early, don't remove the Georgetown time display on TV display
    }
    
    console.log('[RemoveGeorgetownTime] Main cashier interface detected, proceeding with removal');
    
    // Function to remove the Georgetown time display
    function removeGeorgetownTimeDisplay() {
        console.log('[RemoveGeorgetownTime] Running element removal...');
        
        // Remove the Georgetown time display
        const georgetownTimeDisplay = document.getElementById('georgetown-time-display');
        if (georgetownTimeDisplay) {
            georgetownTimeDisplay.remove();
            console.log('[RemoveGeorgetownTime] Removed Georgetown time display');
        } else {
            console.log('[RemoveGeorgetownTime] Georgetown time display not found');
        }
        
        console.log('[RemoveGeorgetownTime] Element removal completed');
    }
    
    // Override the function that creates the Georgetown time display
    function overrideGeorgetownTimeSync() {
        if (window.GeorgetownTimeSync) {
            // Override updateGeorgetownTimeDisplays
            const originalUpdateGeorgetownTimeDisplays = window.GeorgetownTimeSync.updateGeorgetownTimeDisplays;
            window.GeorgetownTimeSync.updateGeorgetownTimeDisplays = function() {
                console.log('[RemoveGeorgetownTime] Blocked attempt to update Georgetown time displays');
                return false;
            };
            
            console.log('[RemoveGeorgetownTime] Overrode GeorgetownTimeSync.updateGeorgetownTimeDisplays');
        }
    }
    
    // Run the removal function when the DOM is loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            removeGeorgetownTimeDisplay();
            setTimeout(overrideGeorgetownTimeSync, 500);
        });
    } else {
        removeGeorgetownTimeDisplay();
        setTimeout(overrideGeorgetownTimeSync, 500);
    }
    
    // Also run it after a short delay to ensure it catches any late initializations
    setTimeout(removeGeorgetownTimeDisplay, 1000);
    
    // And run it again after a longer delay to catch any very late initializations
    setTimeout(removeGeorgetownTimeDisplay, 3000);
    
    // Set up a MutationObserver to remove any Georgetown time displays that are added dynamically
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length) {
                // Check if any of the added nodes are Georgetown time displays
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) { // Element node
                        // Check if it's a Georgetown time display
                        if (node.id === 'georgetown-time-display') {
                            node.remove();
                            console.log('[RemoveGeorgetownTime] Removed dynamically added Georgetown time display');
                        }
                    }
                });
            }
        });
    });
    
    // Start observing the document with the configured parameters
    observer.observe(document.body, { childList: true, subtree: true });
    
    console.log('[RemoveGeorgetownTime] MutationObserver set up to remove dynamically added Georgetown time displays');
})();
