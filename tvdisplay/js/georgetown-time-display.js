/**
 * Georgetown Time Display
 * This script creates and updates the Georgetown Time display in the top-right corner
 * of the TV display page.
 */

(function() {
    console.log('[GeorgetownTimeDisplay] Initializing Georgetown time display...');
    
    // Function to create the Georgetown time display
    function createGeorgetownTimeDisplay() {
        console.log('[GeorgetownTimeDisplay] Creating Georgetown time display...');
        
        // Check if the display already exists
        if (document.getElementById('georgetown-time-display')) {
            console.log('[GeorgetownTimeDisplay] Georgetown time display already exists');
            return;
        }
        
        // Create the display
        const timeDisplay = document.createElement('div');
        timeDisplay.id = 'georgetown-time-display';
        timeDisplay.style.position = 'fixed';
        timeDisplay.style.top = '10px';
        timeDisplay.style.right = '10px';
        timeDisplay.style.padding = '8px 12px';
        timeDisplay.style.backgroundColor = 'rgba(0, 0, 0, 0.7)';
        timeDisplay.style.color = 'white';
        timeDisplay.style.borderRadius = '4px';
        timeDisplay.style.fontSize = '14px';
        timeDisplay.style.fontWeight = 'bold';
        timeDisplay.style.zIndex = '9999';
        timeDisplay.textContent = 'Georgetown Time: Loading...';
        document.body.appendChild(timeDisplay);
        
        console.log('[GeorgetownTimeDisplay] Georgetown time display created');
    }
    
    // Function to update the Georgetown time display
    function updateGeorgetownTimeDisplay() {
        const timeDisplay = document.getElementById('georgetown-time-display');
        if (!timeDisplay) {
            console.log('[GeorgetownTimeDisplay] Georgetown time display not found, creating it');
            createGeorgetownTimeDisplay();
            return;
        }
        
        // Get the current time in Georgetown, Guyana (UTC-4)
        const now = new Date();
        const georgetownTime = new Date(now.getTime() - (now.getTimezoneOffset() * 60000) + (-4 * 60 * 60000));
        
        // Format the time
        const hours = georgetownTime.getHours().toString().padStart(2, '0');
        const minutes = georgetownTime.getMinutes().toString().padStart(2, '0');
        const seconds = georgetownTime.getSeconds().toString().padStart(2, '0');
        
        // Calculate time until next draw
        const secondsInDay = hours * 3600 + minutes * 60 + seconds * 1;
        const secondsUntilNextDraw = 180 - (secondsInDay % 180);
        
        // Format the countdown
        const countdownMinutes = Math.floor(secondsUntilNextDraw / 60).toString().padStart(2, '0');
        const countdownSeconds = (secondsUntilNextDraw % 60).toString().padStart(2, '0');
        
        // Update the display
        timeDisplay.innerHTML = `Georgetown Time: ${hours}:${minutes}:${seconds}<br>Next Draw: ${countdownMinutes}:${countdownSeconds}`;
    }
    
    // Run the creation function when the DOM is loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            createGeorgetownTimeDisplay();
            // Start updating the display every second
            setInterval(updateGeorgetownTimeDisplay, 1000);
        });
    } else {
        createGeorgetownTimeDisplay();
        // Start updating the display every second
        setInterval(updateGeorgetownTimeDisplay, 1000);
    }
    
    // Add the tvdisplay class to the body to help with CSS targeting
    document.body.classList.add('tvdisplay');
    
    console.log('[GeorgetownTimeDisplay] Georgetown time display initialization complete');
})();
