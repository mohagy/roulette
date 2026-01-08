/**
 * Remove All Draw Displays
 * This script forcefully removes all draw displays except for the upcoming draw display
 * It also overrides any functions that try to make them visible again
 */

(function() {
    console.log('[RemoveAllDrawDisplays] Initializing removal of all draw displays...');
    
    // Function to remove all draw displays
    function removeAllDrawDisplays() {
        console.log('[RemoveAllDrawDisplays] Running element removal...');
        
        // Remove the draw container
        const drawContainer = document.querySelector('.draw-container');
        if (drawContainer) {
            drawContainer.remove();
            console.log('[RemoveAllDrawDisplays] Removed draw container');
        } else {
            console.log('[RemoveAllDrawDisplays] Draw container not found');
        }
        
        // Remove the Georgetown time display
        const georgetownTimeDisplay = document.getElementById('georgetown-time-display');
        if (georgetownTimeDisplay) {
            georgetownTimeDisplay.remove();
            console.log('[RemoveAllDrawDisplays] Removed Georgetown time display');
        } else {
            console.log('[RemoveAllDrawDisplays] Georgetown time display not found');
        }
        
        // Remove the TV-style draw display
        const tvStyleDisplay = document.querySelector('.tv-style-display');
        if (tvStyleDisplay) {
            tvStyleDisplay.remove();
            console.log('[RemoveAllDrawDisplays] Removed TV-style draw display');
        } else {
            console.log('[RemoveAllDrawDisplays] TV-style draw display not found');
        }
        
        // Remove the TV-style draw numbers header
        const tvDrawNumbersHeader = document.querySelector('.tv-draw-numbers-header');
        if (tvDrawNumbersHeader) {
            tvDrawNumbersHeader.remove();
            console.log('[RemoveAllDrawDisplays] Removed TV-style draw numbers header');
        } else {
            console.log('[RemoveAllDrawDisplays] TV-style draw numbers header not found');
        }
        
        // Make sure the upcoming draw display is visible
        const upcomingDrawDisplay = document.getElementById('upcoming-draw-display');
        if (upcomingDrawDisplay) {
            upcomingDrawDisplay.style.display = 'block';
            upcomingDrawDisplay.style.visibility = 'visible';
            upcomingDrawDisplay.style.opacity = '1';
            console.log('[RemoveAllDrawDisplays] Ensured upcoming draw display is visible');
        } else {
            console.log('[RemoveAllDrawDisplays] Upcoming draw display not found');
        }
        
        console.log('[RemoveAllDrawDisplays] Element removal completed');
    }
    
    // Override functions that try to make draw displays visible
    function overrideFunctions() {
        // Override any function that tries to make the draw container visible
        if (window.tvStyleDrawDisplay) {
            // Override updateDrawNumberContainer
            const originalUpdateDrawNumberContainer = window.tvStyleDrawDisplay.updateDrawNumberContainer;
            window.tvStyleDrawDisplay.updateDrawNumberContainer = function() {
                console.log('[RemoveAllDrawDisplays] Blocked attempt to update draw number container');
                return false;
            };
            
            // Override createBottomDisplay
            const originalCreateBottomDisplay = window.tvStyleDrawDisplay.createBottomDisplay;
            window.tvStyleDrawDisplay.createBottomDisplay = function() {
                console.log('[RemoveAllDrawDisplays] Blocked attempt to create bottom display');
                return false;
            };
            
            // Override createTopDisplay
            const originalCreateTopDisplay = window.tvStyleDrawDisplay.createTopDisplay;
            window.tvStyleDrawDisplay.createTopDisplay = function() {
                console.log('[RemoveAllDrawDisplays] Blocked attempt to create top display');
                return false;
            };
            
            console.log('[RemoveAllDrawDisplays] Overrode tvStyleDrawDisplay functions');
        }
        
        // Override GeorgetownTimeSync functions
        if (window.GeorgetownTimeSync) {
            // Override updateGeorgetownTimeDisplays
            const originalUpdateGeorgetownTimeDisplays = window.GeorgetownTimeSync.updateGeorgetownTimeDisplays;
            window.GeorgetownTimeSync.updateGeorgetownTimeDisplays = function() {
                console.log('[RemoveAllDrawDisplays] Blocked attempt to update Georgetown time displays');
                return false;
            };
            
            console.log('[RemoveAllDrawDisplays] Overrode GeorgetownTimeSync functions');
        }
        
        console.log('[RemoveAllDrawDisplays] Function overrides completed');
    }
    
    // Run the removal function when the DOM is loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            removeAllDrawDisplays();
            setTimeout(overrideFunctions, 500);
        });
    } else {
        removeAllDrawDisplays();
        setTimeout(overrideFunctions, 500);
    }
    
    // Also run it after a short delay to ensure it catches any late initializations
    setTimeout(removeAllDrawDisplays, 1000);
    
    // And run it again after a longer delay to catch any very late initializations
    setTimeout(removeAllDrawDisplays, 3000);
    
    // Set up a MutationObserver to remove any draw displays that are added dynamically
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length) {
                // Check if any of the added nodes are draw displays
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) { // Element node
                        // Check if it's a draw container
                        if (node.classList && node.classList.contains('draw-container')) {
                            node.remove();
                            console.log('[RemoveAllDrawDisplays] Removed dynamically added draw container');
                        }
                        
                        // Check if it's a Georgetown time display
                        if (node.id === 'georgetown-time-display') {
                            node.remove();
                            console.log('[RemoveAllDrawDisplays] Removed dynamically added Georgetown time display');
                        }
                        
                        // Check if it's a TV-style draw display
                        if (node.classList && node.classList.contains('tv-style-display')) {
                            node.remove();
                            console.log('[RemoveAllDrawDisplays] Removed dynamically added TV-style draw display');
                        }
                        
                        // Check if it's a TV-style draw numbers header
                        if (node.classList && node.classList.contains('tv-draw-numbers-header')) {
                            node.remove();
                            console.log('[RemoveAllDrawDisplays] Removed dynamically added TV-style draw numbers header');
                        }
                    }
                });
            }
        });
    });
    
    // Start observing the document with the configured parameters
    observer.observe(document.body, { childList: true, subtree: true });
    
    console.log('[RemoveAllDrawDisplays] MutationObserver set up to remove dynamically added draw displays');
})();
