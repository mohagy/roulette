/**
 * Disable Draw Information Bar
 * This script prevents the draw information bar from being created and displayed
 */

document.addEventListener('DOMContentLoaded', function() {
    // Override the TVStyleDrawDisplay class to prevent creating the bottom display
    if (typeof TVStyleDrawDisplay === 'function') {
        // Store the original createBottomDisplay method
        const originalCreateBottomDisplay = TVStyleDrawDisplay.prototype.createBottomDisplay;
        
        // Override the createBottomDisplay method to do nothing
        TVStyleDrawDisplay.prototype.createBottomDisplay = function() {
            console.log('Draw information bar creation has been disabled');
            
            // Create empty container elements to prevent errors
            this.container = document.createElement('div');
            this.container.style.display = 'none';
            this.previousDrawNumber = document.createElement('div');
            this.currentDrawNumber = document.createElement('div');
            this.nextDrawNumber = document.createElement('div');
            this.timerDisplay = document.createElement('div');
            
            // Add to the document but keep it hidden
            document.body.appendChild(this.container);
        };
        
        console.log('Draw information bar has been disabled');
    }
    
    // Remove any existing draw information bars
    const existingBars = document.querySelectorAll('.tv-style-display');
    existingBars.forEach(bar => {
        bar.remove();
    });
});
