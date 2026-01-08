/**
 * Force Draw Synchronization
 *
 * This script forces synchronization between the TV display and the main cashier interface.
 * It directly sets the draw numbers in the main cashier interface to match the TV display.
 */

(function() {
    // Configuration
    const config = {
        debug: true,
        syncInterval: 1000, // Check every second
        tvDisplayUrl: 'http://localhost/slipp/tvdisplay/index.html',
        mainInterfaceUrl: 'http://localhost/slipp/index.php',
        currentDrawNumber: 133, // Force this draw number if no other source is available
        keys: {
            tvPreviousDraw: 'tv_display_previous_draw',
            tvCurrentDraw: 'tv_display_current_draw',
            georgetownCurrentDraw: 'georgetown_current_draw_number',
            georgetownNextDraw: 'georgetown_next_draw_number'
        }
    };

    // Logging function
    function log(message, data) {
        if (config.debug) {
            if (data !== undefined) {
                console.log(`[ForceDrawSync] ${message}`, data);
            } else {
                console.log(`[ForceDrawSync] ${message}`);
            }
        }
    }

    // Error logging function
    function error(message, err) {
        console.error(`[ForceDrawSync] ERROR: ${message}`, err);
    }

    // Check if we're on the TV display or main interface
    const isTVDisplay = window.location.pathname.includes('/tvdisplay/');

    // Initialize
    function initialize() {
        log('Initializing Force Draw Sync');

        // Force the draw number to 133 on both interfaces
        localStorage.setItem(config.keys.tvCurrentDraw, config.currentDrawNumber.toString());
        localStorage.setItem(config.keys.tvPreviousDraw, (config.currentDrawNumber - 1).toString());
        localStorage.setItem(config.keys.georgetownCurrentDraw, config.currentDrawNumber.toString());
        localStorage.setItem(config.keys.georgetownNextDraw, (config.currentDrawNumber + 1).toString());

        log('Forced draw numbers in localStorage:', {
            tvCurrent: config.currentDrawNumber,
            tvPrevious: config.currentDrawNumber - 1,
            georgetownCurrent: config.currentDrawNumber,
            georgetownNext: config.currentDrawNumber + 1
        });

        if (isTVDisplay) {
            log('Running on TV display, will update draw numbers');
            updateTVDisplayDrawNumbers();
        } else {
            log('Running on main interface, will sync from TV display');
            syncFromTVDisplay();
        }

        // Set up interval for continuous sync
        setInterval(isTVDisplay ? updateTVDisplayDrawNumbers : syncFromTVDisplay, config.syncInterval);
    }

    // Update TV display draw numbers
    function updateTVDisplayDrawNumbers() {
        // Get the current draw number from the TV display
        const nextDrawElement = document.getElementById('next-draw-number');
        const lastDrawElement = document.getElementById('last-draw-number');

        if (nextDrawElement) {
            const nextDrawText = nextDrawElement.textContent;
            const currentDraw = parseInt(nextDrawText.replace('#', ''));

            if (!isNaN(currentDraw)) {
                // Save to localStorage
                localStorage.setItem(config.keys.tvCurrentDraw, currentDraw.toString());
                log('Updated TV current draw in localStorage:', currentDraw);

                // Also update Georgetown draw numbers
                localStorage.setItem(config.keys.georgetownCurrentDraw, currentDraw.toString());
                localStorage.setItem(config.keys.georgetownNextDraw, (currentDraw + 1).toString());
                log('Updated Georgetown draw numbers in localStorage:', {
                    current: currentDraw,
                    next: currentDraw + 1
                });
            }
        }

        if (lastDrawElement) {
            const lastDrawText = lastDrawElement.textContent;
            if (lastDrawText !== '-') {
                const previousDraw = parseInt(lastDrawText.replace('#', ''));
                if (!isNaN(previousDraw)) {
                    localStorage.setItem(config.keys.tvPreviousDraw, previousDraw.toString());
                    log('Updated TV previous draw in localStorage:', previousDraw);
                }
            }
        }
    }

    // Sync from TV display to main interface
    function syncFromTVDisplay() {
        // Get draw numbers from localStorage
        const tvCurrentDraw = localStorage.getItem(config.keys.tvCurrentDraw);
        const tvPreviousDraw = localStorage.getItem(config.keys.tvPreviousDraw);

        // If TV current draw is available, use it
        if (tvCurrentDraw) {
            const currentDraw = parseInt(tvCurrentDraw);
            if (!isNaN(currentDraw)) {
                // Update the main interface draw numbers
                updateMainInterfaceDrawNumbers(
                    tvPreviousDraw ? parseInt(tvPreviousDraw) : currentDraw - 1,
                    currentDraw
                );
            }
        } else {
            // If no TV draw numbers are available, use the forced draw number
            updateMainInterfaceDrawNumbers(
                config.currentDrawNumber - 1,
                config.currentDrawNumber
            );

            // Also update localStorage for future use
            localStorage.setItem(config.keys.tvCurrentDraw, config.currentDrawNumber.toString());
            localStorage.setItem(config.keys.tvPreviousDraw, (config.currentDrawNumber - 1).toString());
            localStorage.setItem(config.keys.georgetownCurrentDraw, config.currentDrawNumber.toString());
            localStorage.setItem(config.keys.georgetownNextDraw, (config.currentDrawNumber + 1).toString());

            log('Forced draw numbers in localStorage:', {
                tvCurrent: config.currentDrawNumber,
                tvPrevious: config.currentDrawNumber - 1,
                georgetownCurrent: config.currentDrawNumber,
                georgetownNext: config.currentDrawNumber + 1
            });
        }
    }

    // Update main interface draw numbers
    function updateMainInterfaceDrawNumbers(previousDraw, currentDraw) {
        // Update the UI elements
        const lastDrawElement = document.getElementById('last-draw-number');
        const nextDrawElement = document.getElementById('next-draw-number');

        if (lastDrawElement) {
            const formattedPreviousDraw = `#${previousDraw}`;
            if (lastDrawElement.textContent !== formattedPreviousDraw) {
                lastDrawElement.textContent = formattedPreviousDraw;
                log('Updated last-draw-number element:', formattedPreviousDraw);
            }
        }

        if (nextDrawElement) {
            const formattedCurrentDraw = `#${currentDraw}`;
            if (nextDrawElement.textContent !== formattedCurrentDraw) {
                nextDrawElement.textContent = formattedCurrentDraw;
                log('Updated next-draw-number element:', formattedCurrentDraw);
            }
        }

        // Make sure the draw container is visible
        const drawContainer = document.querySelector('.draw-container');
        if (drawContainer) {
            drawContainer.style.display = 'block';
            drawContainer.style.visibility = 'visible';
            drawContainer.style.opacity = '1';
        }

        // Update upcoming draws
        updateUpcomingDraws(currentDraw);

        // Make sure the upcoming draw display is visible
        const upcomingDrawContainer = document.getElementById('upcoming-draw-display');
        if (upcomingDrawContainer) {
            upcomingDrawContainer.style.display = 'block';
            upcomingDrawContainer.style.visibility = 'visible';
            upcomingDrawContainer.style.opacity = '1';
            log('Ensured upcoming draw display is visible');
        }

        // Force update the upcoming draw display if it exists
        if (window.upcomingDrawDisplay && typeof window.upcomingDrawDisplay.syncUpcomingDraws === 'function') {
            window.upcomingDrawDisplay.syncUpcomingDraws();
            log('Forced update of upcoming draw display');
        }
    }

    // Update upcoming draws
    function updateUpcomingDraws(currentDraw) {
        // Generate upcoming draws
        const upcomingDraws = [];
        const upcomingDrawTimes = [];

        // Calculate Georgetown time offset (UTC-4)
        let georgetownTimeOffset = 0;
        try {
            const now = new Date();
            const utcTime = now.getTime() + (now.getTimezoneOffset() * 60000);
            const georgetownTime = new Date(utcTime - (4 * 3600000));
            georgetownTimeOffset = georgetownTime.getTime() - now.getTime();
        } catch (err) {
            error('Error calculating Georgetown time offset', err);
        }

        for (let i = 0; i < 10; i++) {
            upcomingDraws.push(currentDraw + i);

            // Calculate the time for this draw using Georgetown time
            const drawTime = new Date();
            drawTime.setTime(drawTime.getTime() + georgetownTimeOffset);
            drawTime.setSeconds(drawTime.getSeconds() + (i * 180));

            // Format time as HH:MM:SS
            const hours = drawTime.getHours().toString().padStart(2, '0');
            const minutes = drawTime.getMinutes().toString().padStart(2, '0');
            const seconds = drawTime.getSeconds().toString().padStart(2, '0');
            upcomingDrawTimes.push(`${hours}:${minutes}:${seconds}`);
        }

        // Save to localStorage
        localStorage.setItem('tv_display_upcoming_draws', JSON.stringify(upcomingDraws));
        localStorage.setItem('tv_display_upcoming_draw_times', JSON.stringify(upcomingDrawTimes));

        log('Generated and saved upcoming draws to localStorage', {
            upcomingDraws,
            upcomingDrawTimes
        });

        // If the upcoming draw display exists, update it
        if (window.upcomingDrawDisplay && typeof window.upcomingDrawDisplay.updateDraws === 'function') {
            window.upcomingDrawDisplay.updateDraws(upcomingDraws, upcomingDrawTimes);
            log('Updated upcoming draw display');
        }
    }

    // Initialize when the document is ready
    document.addEventListener('DOMContentLoaded', initialize);

    // If document is already loaded, initialize immediately
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        initialize();
    }
})();
