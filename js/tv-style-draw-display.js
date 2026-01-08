/**
 * TV-Style Draw Display
 * Displays the previous, current, and next draw numbers along with a countdown timer
 * Also shows upcoming draw numbers at the top of the screen
 */
class TVStyleDrawDisplay {
    /**
     * Constructor
     * @param {Object} config - Configuration options
     */
    constructor(config = {}) {
        // Default configuration
        this.config = {
            container: document.body,
            debug: false,
            syncInterval: 5000, // 5 seconds
            syncUrl: 'sync_draw_timer.php',
            ...config
        };

        // Initialize state
        this.state = {
            initialized: false,
            lastDrawNumber: null,
            currentDrawNumber: null,
            nextDrawNumber: null,
            countdownTime: 0,
            countdownInterval: null,
            syncInterval: null,
            selectedDrawNumber: null,
            upcomingDraws: [],
            upcomingDrawTimes: []
        };

        // Create the displays
        this.createBottomDisplay();
        this.createTopDisplay();

        // Initial sync with server
        this.syncWithServer();

        // Set up regular sync interval
        this.state.syncInterval = setInterval(() => {
            this.syncWithServer();
        }, this.config.syncInterval);
    }

    /**
     * Log messages if debug is enabled
     */
    log(...args) {
        if (this.config.debug) {
            console.log('[TVStyleDrawDisplay]', ...args);
        }
    }

    /**
     * Sync with server to get latest data
     */
    syncWithServer() {
        this.log('Syncing with server...');

        // Add a cache-busting parameter to prevent caching
        const url = this.config.syncUrl + '?t=' + new Date().getTime();

        fetch(url, {
            method: 'GET',
            headers: {
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache',
                'Expires': '0'
            }
        })
            .then(response => response.json())
            .then(data => {
                this.log('Received data from server:', data);

                // Force refresh if draw numbers have changed
                const drawNumberChanged =
                    (data.currentDrawNumber !== undefined && this.state.currentDrawNumber !== data.currentDrawNumber) ||
                    (data.lastDrawNumber !== undefined && this.state.lastDrawNumber !== data.lastDrawNumber);

                if (drawNumberChanged) {
                    this.log('Draw numbers changed, forcing refresh');
                }

                this.updateDisplay(data);
            })
            .catch(error => {
                this.log('Error syncing with server:', error);
                // Try again after a short delay
                setTimeout(() => this.syncWithServer(), 2000);
            });
    }

    /**
     * Create the bottom display elements
     */
    createBottomDisplay() {
        // Create main container
        this.container = document.createElement('div');
        this.container.className = 'tv-style-display';

        // Create draw info section (previous, current, and next draw)
        const drawContainer = document.createElement('div');
        drawContainer.className = 'tv-draw-container';

        // Previous draw section
        const previousDraw = document.createElement('div');
        previousDraw.className = 'tv-draw-section tv-previous-draw';

        const previousDrawLabel = document.createElement('div');
        previousDrawLabel.className = 'tv-draw-label';
        previousDrawLabel.textContent = 'PREVIOUS DRAW';

        this.previousDrawNumber = document.createElement('div');
        this.previousDrawNumber.className = 'tv-draw-number';
        this.previousDrawNumber.textContent = '#--';

        previousDraw.appendChild(previousDrawLabel);
        previousDraw.appendChild(this.previousDrawNumber);

        // Current draw section
        const currentDraw = document.createElement('div');
        currentDraw.className = 'tv-draw-section tv-current-draw';

        const currentDrawLabel = document.createElement('div');
        currentDrawLabel.className = 'tv-draw-label';
        currentDrawLabel.textContent = 'CURRENT DRAW';

        this.currentDrawNumber = document.createElement('div');
        this.currentDrawNumber.className = 'tv-draw-number';
        this.currentDrawNumber.textContent = '#--';

        currentDraw.appendChild(currentDrawLabel);
        currentDraw.appendChild(this.currentDrawNumber);

        // Next draw section
        const nextDraw = document.createElement('div');
        nextDraw.className = 'tv-draw-section tv-next-draw';

        const nextDrawLabel = document.createElement('div');
        nextDrawLabel.className = 'tv-draw-label';
        nextDrawLabel.textContent = 'NEXT DRAW';

        this.nextDrawNumber = document.createElement('div');
        this.nextDrawNumber.className = 'tv-draw-number';
        this.nextDrawNumber.textContent = '#--';

        nextDraw.appendChild(nextDrawLabel);
        nextDraw.appendChild(this.nextDrawNumber);

        // Add draw sections to container
        drawContainer.appendChild(previousDraw);
        drawContainer.appendChild(currentDraw);
        drawContainer.appendChild(nextDraw);

        // Create timer container
        const timerContainer = document.createElement('div');
        timerContainer.className = 'tv-timer-container';

        const timerLabel = document.createElement('div');
        timerLabel.className = 'tv-timer-label';
        timerLabel.textContent = 'NEXT SPIN IN';

        this.timerDisplay = document.createElement('div');
        this.timerDisplay.className = 'tv-timer-display';
        this.timerDisplay.textContent = '00:00';

        timerContainer.appendChild(timerLabel);
        timerContainer.appendChild(this.timerDisplay);

        // Add a small indicator for the next draw
        const nextDrawIndicator = document.createElement('div');
        nextDrawIndicator.className = 'tv-next-draw-indicator';
        nextDrawIndicator.textContent = 'NEXT DRAW';
        nextDrawIndicator.style.fontSize = '10px';
        nextDrawIndicator.style.textAlign = 'center';
        nextDrawIndicator.style.color = 'rgba(255,255,255,0.7)';
        nextDrawIndicator.style.marginTop = '5px';
        timerContainer.appendChild(nextDrawIndicator);

        // Add containers to main container
        this.container.appendChild(drawContainer);
        this.container.appendChild(timerContainer);

        // Add to the document
        document.body.appendChild(this.container);
    }

    /**
     * Create the top display for upcoming draw numbers
     */
    createTopDisplay() {
        // Create header container
        this.headerContainer = document.createElement('div');
        this.headerContainer.className = 'tv-draw-numbers-header';

        // Create title
        const title = document.createElement('div');
        title.className = 'tv-draw-numbers-title';
        title.textContent = 'DRAW NUMBERS';

        // Create draw numbers row
        this.drawNumbersRow = document.createElement('div');
        this.drawNumbersRow.className = 'tv-draw-numbers-row';

        // Add elements to container
        this.headerContainer.appendChild(title);
        this.headerContainer.appendChild(this.drawNumbersRow);

        // Add a small note about clicking on draw numbers
        const noteElement = document.createElement('div');
        noteElement.className = 'draw-note';
        noteElement.style.fontSize = '10px';
        noteElement.style.textAlign = 'center';
        noteElement.style.color = 'rgba(255,255,255,0.6)';
        noteElement.style.marginTop = '5px';
        noteElement.textContent = 'Click on a draw number to place bets for that specific draw';
        this.headerContainer.appendChild(noteElement);

        // Add to the document
        document.body.appendChild(this.headerContainer);

        // Make the header draggable
        this.makeHeaderDraggable();
    }

    /**
     * Make the header draggable
     */
    makeHeaderDraggable() {
        let isDragging = false;
        let dragOffsetX = 0;
        let dragOffsetY = 0;

        // Mouse down event to start dragging
        this.headerContainer.addEventListener('mousedown', (e) => {
            // Only allow dragging from the title bar
            if (e.target.classList.contains('tv-draw-numbers-title')) {
                isDragging = true;

                const rect = this.headerContainer.getBoundingClientRect();
                dragOffsetX = e.clientX - rect.left;
                dragOffsetY = e.clientY - rect.top;

                // Change cursor
                this.headerContainer.style.cursor = 'grabbing';
            }
        });

        // Mouse move event to drag
        document.addEventListener('mousemove', (e) => {
            if (isDragging) {
                const x = e.clientX - dragOffsetX;
                const y = e.clientY - dragOffsetY;

                this.headerContainer.style.left = x + 'px';
                this.headerContainer.style.top = y + 'px';
                this.headerContainer.style.transform = 'none';
            }
        });

        // Mouse up event to stop dragging
        document.addEventListener('mouseup', () => {
            if (isDragging) {
                isDragging = false;
                this.headerContainer.style.cursor = '';
            }
        });

        // Add visual cue that it's draggable
        const titleElement = this.headerContainer.querySelector('.tv-draw-numbers-title');
        if (titleElement) {
            titleElement.style.cursor = 'grab';
            titleElement.title = 'Drag to move';
        }
    }

    /**
     * Update the display with new data
     */
    updateDisplay(data) {
        this.log('Updating display with data:', data);

        // Store previous state for comparison
        const previousState = { ...this.state };

        // Check if draw numbers have changed
        const drawNumberChanged =
            (data.lastDrawNumber !== undefined && this.state.lastDrawNumber !== data.lastDrawNumber) ||
            (data.currentDrawNumber !== undefined && this.state.currentDrawNumber !== data.currentDrawNumber);

        // Update draw numbers in bottom display
        if (data.lastDrawNumber !== undefined) {
            this.state.lastDrawNumber = data.lastDrawNumber;

            // Update the previous draw number (without winning number)
            this.previousDrawNumber.textContent = '#' + data.lastDrawNumber;

            // Update the last draw number in the main UI
            const lastDrawElement = document.getElementById('last-draw-number');
            if (lastDrawElement) {
                lastDrawElement.textContent = '#' + data.lastDrawNumber;
            }
        }

        if (data.currentDrawNumber !== undefined) {
            this.state.currentDrawNumber = data.currentDrawNumber;

            // Update the current draw number with winning number if available
            let displayText = '#' + data.currentDrawNumber;
            if (data.lastWinningNumber !== undefined && data.lastWinningNumber !== null) {
                displayText += ` (${data.lastWinningNumber})`;

                // Add color indicator if available
                if (data.lastWinningColor) {
                    this.currentDrawNumber.dataset.color = data.lastWinningColor;

                    // Add color indicator dot
                    const colorClass = `color-${data.lastWinningColor}`;
                    if (!this.currentDrawNumber.classList.contains(colorClass)) {
                        // Remove any existing color classes
                        this.currentDrawNumber.classList.remove('color-red', 'color-black', 'color-green');
                        // Add the new color class
                        this.currentDrawNumber.classList.add(colorClass);
                    }
                }
            }

            this.currentDrawNumber.textContent = displayText;
        }

        // Update next draw number
        if (data.upcomingDraws && data.upcomingDraws.length > 0) {
            this.state.nextDrawNumber = data.upcomingDraws[0];
            this.nextDrawNumber.textContent = '#' + data.upcomingDraws[0];

            // Update the next draw number in the main UI
            const nextDrawElement = document.getElementById('next-draw-number');
            if (nextDrawElement) {
                nextDrawElement.textContent = '#' + data.upcomingDraws[0];
            }

            // Make sure the draw container is visible
            const drawContainer = document.querySelector('.draw-container');
            if (drawContainer) {
                drawContainer.style.display = 'block';
            }
        }

        // Update countdown timer
        if (data.countdownTime !== undefined) {
            this.state.countdownTime = data.countdownTime;
            this.updateCountdown();

            // Start or restart countdown
            if (this.state.countdownInterval) {
                clearInterval(this.state.countdownInterval);
            }

            this.state.countdownInterval = setInterval(() => {
                if (this.state.countdownTime > 0) {
                    this.state.countdownTime--;
                    this.updateCountdown();
                } else {
                    // When countdown reaches zero, sync with server
                    this.syncWithServer();
                }
            }, 1000);
        }

        // Store upcoming draws for reference
        if (data.upcomingDraws) {
            this.state.upcomingDraws = data.upcomingDraws;
            this.updateDrawNumbersHeader(data.upcomingDraws, data.upcomingDrawTimes);
        }

        // Store upcoming draw times
        if (data.upcomingDrawTimes) {
            this.state.upcomingDrawTimes = data.upcomingDrawTimes;
        }

        // Store recent draws history
        if (data.recentDraws) {
            this.state.recentDraws = data.recentDraws;
        }

        // If draw numbers have changed, show notification
        if (drawNumberChanged && this.state.initialized) {
            this.showNotification(`Draw updated: Now on Draw #${data.currentDrawNumber}`);

            // Also dispatch an event for other components to react to
            const event = new CustomEvent('drawNumbersChanged', {
                detail: {
                    lastDrawNumber: this.state.lastDrawNumber,
                    currentDrawNumber: this.state.currentDrawNumber,
                    nextDrawNumber: this.state.nextDrawNumber
                }
            });
            document.dispatchEvent(event);
        }

        // Mark as initialized
        if (!this.state.initialized) {
            this.state.initialized = true;
            this.log('Display initialized');
        }
    }

    /**
     * Update the draw numbers header
     */
    updateDrawNumbersHeader(drawNumbers, drawTimes) {
        // Clear existing draw numbers
        this.drawNumbersRow.innerHTML = '';

        // Update the title to match the screenshot
        if (this.headerContainer.querySelector('.tv-draw-numbers-title')) {
            this.headerContainer.querySelector('.tv-draw-numbers-title').textContent = 'DRAW NUMBERS';
        }

        // Force draw numbers to start from 15 if they're starting from 1 or 2
        if (drawNumbers.length > 0 && (drawNumbers[0] === 1 || drawNumbers[0] === 2)) {
            this.log('Forcing draw numbers to start from 15 instead of ' + drawNumbers[0]);
            drawNumbers = drawNumbers.map((num, index) => 15 + index);
        }

        // Add new draw numbers (limit to 10 for better display)
        const displayCount = Math.min(drawNumbers.length, 10);

        // If no draw numbers, add a message and generate some default ones
        if (displayCount === 0) {
            this.log('No draw numbers provided, generating default ones starting from 6');

            // Generate 10 upcoming draws starting from 15
            drawNumbers = [];
            drawTimes = [];

            for (let i = 0; i < 10; i++) {
                drawNumbers.push(15 + i);
                const drawTime = new Date();
                drawTime.setSeconds(drawTime.getSeconds() + (i * 180));
                drawTimes.push(drawTime.toTimeString().substring(0, 8));
            }
        }

        // Update the Draw Number Container in the main UI
        this.updateDrawNumberContainer(drawNumbers[0]);

        for (let i = 0; i < displayCount; i++) {
            const drawNumber = drawNumbers[i];
            const drawElement = document.createElement('div');
            drawElement.className = 'tv-draw-number-item';
            drawElement.setAttribute('data-draw-number', drawNumber);

            // Add current class to the current draw
            if (i === 0) {
                drawElement.classList.add('current');
            }

            // Format the draw number to match the screenshot (#25, #26, etc.)
            let drawText = `#${drawNumber}`;

            // Add time if available
            if (drawTimes && drawTimes[i]) {
                drawText += `<br><small>${drawTimes[i]}</small>`;
            }

            drawElement.innerHTML = drawText;

            // Add click event to select this draw
            drawElement.addEventListener('click', () => {
                this.selectDraw(drawNumber);
            });

            // Add tooltip to show this is clickable
            drawElement.title = `Click to select Draw #${drawNumber}`;

            this.drawNumbersRow.appendChild(drawElement);
        }

        // Add a note below the draw numbers
        const noteElement = document.createElement('div');
        noteElement.style.fontSize = '10px';
        noteElement.style.textAlign = 'center';
        noteElement.style.color = 'rgba(255,255,255,0.6)';
        noteElement.style.marginTop = '5px';
        noteElement.textContent = 'Click on a draw number to place bets for that specific draw';

        // Replace any existing note
        const existingNote = this.headerContainer.querySelector('.draw-note');
        if (existingNote) {
            existingNote.remove();
        }

        noteElement.className = 'draw-note';
        this.headerContainer.appendChild(noteElement);
    }

    /**
     * Update the Draw Number Container in the main UI
     */
    updateDrawNumberContainer(nextDrawNumber) {
        // Find the Draw Number Container
        const drawContainer = document.querySelector('.draw-container');
        if (!drawContainer) return;

        // Make sure it's visible
        drawContainer.style.display = 'block';

        // Force next draw to be 15 if it's 1 or 2
        if (nextDrawNumber === 1 || nextDrawNumber === 2) {
            this.log('Forcing next draw number to be 15 instead of ' + nextDrawNumber);
            nextDrawNumber = 15;
        }

        // Update the next draw number
        const nextDrawElement = document.getElementById('next-draw-number');
        if (nextDrawElement) {
            nextDrawElement.textContent = `#${nextDrawNumber}`;
        }

        // Force last draw to be 14 if it's 0
        let lastDrawNumber = this.state.lastDrawNumber;
        if (lastDrawNumber === 0) {
            this.log('Forcing last draw number to be 14 instead of 0');
            lastDrawNumber = 14;
        }

        // Update the last draw number if available
        if (lastDrawNumber) {
            const lastDrawElement = document.getElementById('last-draw-number');
            if (lastDrawElement) {
                lastDrawElement.textContent = `#${lastDrawNumber}`;
            }
        }

        // Log the updated draw numbers
        this.log(`Updated Draw Number Container: Last=${lastDrawNumber}, Next=${nextDrawNumber}`);
    }

    /**
     * Update the countdown display
     */
    updateCountdown() {
        const minutes = Math.floor(this.state.countdownTime / 60);
        const seconds = this.state.countdownTime % 60;

        this.timerDisplay.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

        // Add warning class if less than 30 seconds
        if (this.state.countdownTime < 30) {
            this.timerDisplay.classList.add('tv-timer-warning');
        } else {
            this.timerDisplay.classList.remove('tv-timer-warning');
        }
    }

    /**
     * Select a draw number
     */
    selectDraw(drawNumber) {
        this.state.selectedDrawNumber = drawNumber;

        // Dispatch event for other components to react to
        const event = new CustomEvent('drawNumberSelected', {
            detail: { drawNumber: drawNumber }
        });
        document.dispatchEvent(event);

        this.log('Selected draw number:', drawNumber);

        // Show notification
        this.showNotification(`Selected Draw #${drawNumber}`);
    }

    /**
     * Show a notification
     */
    showNotification(message) {
        // Create notification element
        const notification = document.createElement('div');
        notification.style.position = 'fixed';
        notification.style.bottom = '100px';
        notification.style.left = '50%';
        notification.style.transform = 'translateX(-50%)';
        notification.style.backgroundColor = 'rgba(0, 0, 0, 0.8)';
        notification.style.color = '#ffcc00';
        notification.style.padding = '10px 20px';
        notification.style.borderRadius = '5px';
        notification.style.zIndex = '10000';
        notification.style.fontWeight = 'bold';
        notification.style.boxShadow = '0 0 10px rgba(0, 0, 0, 0.5)';
        notification.textContent = message;

        // Add to document
        document.body.appendChild(notification);

        // Remove after 3 seconds
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transition = 'opacity 0.5s';

            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 500);
        }, 3000);
    }

    /**
     * Clean up resources
     */
    destroy() {
        // Clear intervals
        if (this.state.countdownInterval) {
            clearInterval(this.state.countdownInterval);
        }

        if (this.state.syncInterval) {
            clearInterval(this.state.syncInterval);
        }

        // Remove from DOM
        if (this.container && this.container.parentNode) {
            this.container.parentNode.removeChild(this.container);
        }

        if (this.headerContainer && this.headerContainer.parentNode) {
            this.headerContainer.parentNode.removeChild(this.headerContainer);
        }

        this.log('Display destroyed');
    }
}
