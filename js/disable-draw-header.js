/**
 * Disable Draw Header
 * This script prevents the draw header from being loaded and displayed
 */

document.addEventListener('DOMContentLoaded', function() {
    // Remove the draw header container if it exists
    const drawHeaderContainer = document.getElementById('drawHeaderContainer');
    if (drawHeaderContainer) {
        drawHeaderContainer.remove();
    }
    
    // Remove any draw header toggle buttons
    const toggleButton = document.querySelector('.draw-header-toggle-button');
    if (toggleButton) {
        toggleButton.remove();
    }
    
    // Remove the overlay and dialog
    const overlay = document.getElementById('overlay');
    if (overlay) {
        overlay.remove();
    }
    
    const dialog = document.getElementById('drawSelectDialog');
    if (dialog) {
        dialog.remove();
    }
    
    // Override the draw header initialization functions
    if (typeof window.DrawHeader === 'function') {
        // Create a dummy DrawHeader class that does nothing
        window.DrawHeader = function() {
            return {
                loadSavedState: function() {},
                updateDrawNumbers: function() {},
                setupDrawNumberSelection: function() {},
                show: function() {},
                toggleVisibility: function() {},
                toggleMinimize: function() {}
            };
        };
    }
    
    // Override the initializeDrawNumbers function
    if (typeof window.initializeDrawNumbers === 'function') {
        window.initializeDrawNumbers = function() {
            console.log('Draw header initialization disabled');
        };
    }
    
    // Override the addDrawHeaderToggleButton function
    if (typeof window.addDrawHeaderToggleButton === 'function') {
        window.addDrawHeaderToggleButton = function() {
            console.log('Draw header toggle button disabled');
        };
    }
    
    // Create a dummy drawHeader object if it doesn't exist
    if (!window.drawHeader) {
        window.drawHeader = {
            loadSavedState: function() {},
            updateDrawNumbers: function() {},
            setupDrawNumberSelection: function() {},
            show: function() {},
            toggleVisibility: function() {},
            toggleMinimize: function() {}
        };
    }
    
    // Prevent the AJAX request to load the draw header
    const originalFetch = window.fetch;
    window.fetch = function(url, options) {
        if (typeof url === 'string' && url.includes('draw_header.php')) {
            console.log('Blocked request to draw_header.php');
            return Promise.resolve({
                ok: true,
                json: function() {
                    return Promise.resolve({
                        currentDrawNumber: 1,
                        drawNumbers: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]
                    });
                },
                text: function() {
                    return Promise.resolve('');
                }
            });
        }
        return originalFetch(url, options);
    };
    
    console.log('Draw header functionality disabled');
});
