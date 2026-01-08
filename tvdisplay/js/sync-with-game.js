/**
 * TV Display Synchronization with Main Game
 *
 * This module handles synchronization between the TV display and the main game.
 * It uses Firebase Realtime Database to receive real-time updates about draw numbers.
 */

const GameSynchronizer = (function() {
    // Configuration
    const config = {
        sseEndpoint: '../draw_header_updates.php',
        pollEndpoint: '../draw_header.php?ajax=1',
        pollInterval: 1000, // 1 second (fallback only)
        soundEnabled: true,
        soundFile: '../sounds/draw_notifications.wav',
        useFirebase: true // Prefer Firebase over SSE/polling
    };

    // State
    let eventSource = null;
    let pollTimer = null;
    let lastDrawNumber = null;
    let lastUpdateTimestamp = 0;
    let isConnected = false;
    let listeners = [];
    let firebaseListeners = [];
    let useFirebase = false;

    // Create notification sound object
    const notificationSound = new Audio(config.soundFile);
    notificationSound.preload = 'auto';
    notificationSound.volume = 0.7;

    /**
     * Initialize the synchronizer
     * @param {Object} options - Configuration options
     */
    function initialize(options = {}) {
        // Merge options with default config
        Object.assign(config, options);

        // Try Firebase first if available
        if (config.useFirebase && window.FirebaseService && window.FirebaseDrawManager) {
            initFirebase();
        } else if (typeof EventSource !== 'undefined') {
            console.log('Firebase not available, using SSE');
            initSSE();
        } else {
            console.log('EventSource not supported, falling back to polling');
            initPolling();
        }

        // Add connection status indicator to the UI
        addConnectionIndicator();

        // Initialize sound settings from localStorage if available
        if (localStorage.getItem('tvDrawSoundMuted') === 'true') {
            config.soundEnabled = false;
        }
    }

    /**
     * Initialize Firebase real-time listeners
     */
    function initFirebase() {
        if (!window.FirebaseService || !window.FirebaseDrawManager) {
            console.error('Firebase not available, falling back to SSE/polling');
            if (typeof EventSource !== 'undefined') {
                initSSE();
            } else {
                initPolling();
            }
            return;
        }

        console.log('Initializing Firebase real-time synchronization');

        useFirebase = true;
        isConnected = true;
        updateConnectionStatus(true);

        // Load initial data from Firebase when tvdisplay opens
        async function loadInitialData() {
            try {
                console.log('🔥 Loading initial draw data from Firebase...');
                
                // Get current draw state
                const gameState = await FirebaseDrawManager.getCurrentDrawState();
                const drawInfo = await FirebaseService.GameState.getDrawInfo();
                
                if (gameState || drawInfo) {
                    const currentDraw = drawInfo?.currentDraw || gameState?.drawNumber || gameState?.currentDrawNumber;
                    const nextDraw = drawInfo?.nextDraw || gameState?.nextDrawNumber;
                    
                    if (currentDraw && nextDraw) {
                        console.log('✅ Loaded initial draw data from Firebase:', { currentDraw, nextDraw });
                        processUpdate({
                            currentDrawNumber: currentDraw,
                            nextDrawNumber: nextDraw
                        });
                    }
                }

                // Get latest draw result
                if (drawInfo?.currentDraw) {
                    const latestDraw = await FirebaseDrawManager.getDraw(drawInfo.currentDraw);
                    if (latestDraw) {
                        console.log('✅ Loaded latest draw result from Firebase:', latestDraw);
                        // Update display with latest draw
                        processUpdate({
                            currentDrawNumber: latestDraw.drawNumber,
                            nextDrawNumber: latestDraw.drawNumber + 1,
                            winningNumber: latestDraw.winningNumber,
                            winningColor: latestDraw.winningColor
                        });
                    }
                }
            } catch (error) {
                console.error('❌ Error loading initial data from Firebase:', error);
            }
        }

        // Load initial data
        loadInitialData();

        // Listen to draw info changes
        const drawInfoListener = FirebaseService.GameState.listenDrawInfo((data) => {
            if (data) {
                console.log('Firebase draw info updated:', data);
                processUpdate({
                    currentDrawNumber: data.currentDraw,
                    nextDrawNumber: data.nextDraw
                });
            }
        });

        // Listen to game state changes
        const gameStateListener = FirebaseDrawManager.listenToCurrentDraw((data) => {
            if (data) {
                console.log('Firebase game state updated:', data);
                const currentDraw = data.drawNumber || data.currentDrawNumber;
                const nextDraw = data.nextDrawNumber;

                if (currentDraw && nextDraw) {
                    processUpdate({
                        currentDrawNumber: currentDraw,
                        nextDrawNumber: nextDraw
                    });
                }
            }
        });

        // Listen to new draw results
        const drawListener = FirebaseDrawManager.listenToDraws((data) => {
            if (data) {
                console.log('🔥 TV Display: New draw result from Firebase:', data);
                
                // Update display with new draw result
                processUpdate({
                    currentDrawNumber: data.drawNumber,
                    nextDrawNumber: data.drawNumber + 1,
                    winningNumber: data.winningNumber,
                    winningColor: data.winningColor
                });
                
                // If countdown is at zero or very close, trigger auto-spin
                // This ensures the wheel spins when a new draw result arrives
                const timerEl = document.querySelector('.timer-display');
                if (timerEl) {
                    const timerText = timerEl.textContent.trim();
                    if (timerText === '00:00' || timerText === '0:00') {
                        console.log('🔥 TV Display: Timer at zero, triggering auto-spin for new draw result');
                        setTimeout(() => {
                            const spinButton = document.querySelector('.button-spin');
                            if (spinButton && !document.querySelector('.roulette-wheel-container')?.classList.contains('roulette-wheel-visible')) {
                                spinButton.click();
                            }
                        }, 1000);
                    }
                }
                
                // Trigger notification for new draw
                if (config.soundEnabled) {
                    playNotificationSound();
                }
            }
        });

        // Monitor connection status
        FirebaseService.onConnectionStatusChange((online) => {
            isConnected = online;
            updateConnectionStatus(online);
            
            // When coming online, reload data
            if (online) {
                console.log('🔥 Firebase reconnected, reloading data...');
                loadInitialData();
            }
        });

        firebaseListeners = [drawInfoListener, gameStateListener, drawListener];
        console.log('Firebase listeners initialized');
    }

    /**
     * Initialize Server-Sent Events
     */
    function initSSE() {
        try {
            // Close existing connection if any
            if (eventSource) {
                eventSource.close();
                eventSource = null;
            }

            // Create new SSE connection
            eventSource = new EventSource(config.sseEndpoint);

            // Connection opened
            eventSource.addEventListener('open', () => {
                console.log('SSE connection established');
                isConnected = true;
                updateConnectionStatus(true);
            });

            // Draw update event
            eventSource.addEventListener('drawupdate', (event) => {
                try {
                    const data = JSON.parse(event.data);
                    processUpdate(data);
                } catch (e) {
                    console.error('Error processing SSE update:', e);
                }
            });

            // Connection error
            eventSource.addEventListener('error', (e) => {
                console.error('SSE connection error:', e);
                isConnected = false;
                updateConnectionStatus(false);

                // If connection failed, fall back to polling
                if (eventSource.readyState === EventSource.CLOSED) {
                    eventSource.close();
                    eventSource = null;
                    initPolling();
                }
            });
        } catch (e) {
            console.error('Failed to initialize SSE:', e);
            initPolling();
        }
    }

    /**
     * Initialize polling
     */
    function initPolling() {
        // Clear existing timer
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }

        // Start polling
        pollTimer = setInterval(poll, config.pollInterval);

        // Do an initial poll
        poll();
    }

    /**
     * Poll for updates
     */
    function poll() {
        // Skip if we've polled too recently
        const now = Date.now();
        if (now - lastUpdateTimestamp < 500) return;

        lastUpdateTimestamp = now;

        // Make fetch request
        fetch(config.pollEndpoint, {
            method: 'GET',
            headers: {
                'Cache-Control': 'no-cache'
            }
        })
        .then(response => {
            // Check if we have a connection
            updateConnectionStatus(true);
            return response.json();
        })
        .then(data => {
            processUpdate(data);
        })
        .catch(error => {
            console.error('Error polling for updates:', error);
            updateConnectionStatus(false);
        });
    }

    /**
     * Process a draw update
     * @param {Object} data - The draw update data
     */
    function processUpdate(data) {
        if (!data) return;

        // Handle different data formats (Firebase vs server)
        const currentDraw = data.currentDrawNumber || data.currentDraw || data.drawNumber;
        if (!currentDraw) return;

        // Check if there's a new current draw number
        const hasNewDraw = lastDrawNumber !== null && lastDrawNumber !== currentDraw;

        // Update last draw number
        lastDrawNumber = currentDraw;

        // Notify listeners
        notifyListeners({
            currentDrawNumber: currentDraw,
            nextDrawNumber: data.nextDrawNumber || data.nextDraw || (currentDraw + 1),
            drawNumbers: data.drawNumbers,
            isNewDraw: hasNewDraw,
            timestamp: data.timestamp || data.updatedAt || Date.now()
        });

        // If it's a new draw, highlight it
        if (hasNewDraw) {
            highlightNewDraw(data.currentDrawNumber);

            // Play notification sound
            playNotificationSound();
        }
    }

    /**
     * Play notification sound for new draws
     */
    function playNotificationSound() {
        if (!config.soundEnabled) return;

        try {
            // Reset the sound if it's already playing
            notificationSound.pause();
            notificationSound.currentTime = 0;

            // Play the notification sound
            notificationSound.play().catch(e => {
                // Ignore errors - browser might block autoplay
                console.log('Could not play notification sound:', e.message);
            });
        } catch (e) {
            console.error('Error playing notification sound:', e);
        }
    }

    /**
     * Toggle notification sound on/off
     * @returns {boolean} New sound state (true=enabled, false=disabled)
     */
    function toggleSound() {
        config.soundEnabled = !config.soundEnabled;
        localStorage.setItem('tvDrawSoundMuted', !config.soundEnabled);

        // If we just enabled sound, play a test sound
        if (config.soundEnabled) {
            playNotificationSound();
        }

        return config.soundEnabled;
    }

    /**
     * Notify listeners of a draw update
     * @param {Object} updateData - The update data
     */
    function notifyListeners(updateData) {
        listeners.forEach(listener => {
            try {
                listener(updateData);
            } catch (e) {
                console.error('Error in listener:', e);
            }
        });
    }

    /**
     * Register a listener for draw updates
     * @param {Function} callback - The callback function
     * @returns {Function} Unregister function
     */
    function registerListener(callback) {
        if (typeof callback !== 'function') {
            throw new Error('Listener must be a function');
        }

        listeners.push(callback);

        // Return unregister function
        return function unregister() {
            const index = listeners.indexOf(callback);
            if (index !== -1) {
                listeners.splice(index, 1);
                return true;
            }
            return false;
        };
    }

    /**
     * Add a connection status indicator to the UI
     */
    function addConnectionIndicator() {
        // Create indicator element
        const indicator = document.createElement('div');
        indicator.id = 'gameConnectionIndicator';
        indicator.className = 'game-connection-indicator';
        indicator.innerHTML = `
            <span class="status"></span>
            <span class="label">Game Sync</span>
            <div class="sound-toggle" title="Toggle notification sound">🔊</div>
        `;

        // Add styles
        const style = document.createElement('style');
        style.textContent = `
            .game-connection-indicator {
                position: fixed;
                bottom: 10px;
                right: 10px;
                background-color: rgba(0, 0, 0, 0.7);
                color: white;
                padding: 5px 10px;
                border-radius: 4px;
                font-size: 12px;
                z-index: 9999;
                display: flex;
                align-items: center;
                transition: opacity 0.3s;
            }
            .game-connection-indicator .status {
                width: 8px;
                height: 8px;
                border-radius: 50%;
                margin-right: 5px;
            }
            .game-connection-indicator.connected .status {
                background-color: #4cd137;
                box-shadow: 0 0 0 2px rgba(76, 209, 55, 0.3);
            }
            .game-connection-indicator.disconnected .status {
                background-color: #e74c3c;
                box-shadow: 0 0 0 2px rgba(231, 76, 60, 0.3);
            }
            .game-connection-indicator .label {
                font-weight: 500;
            }
            .game-connection-indicator .sound-toggle {
                margin-left: 10px;
                cursor: pointer;
                opacity: 0.8;
                transition: opacity 0.2s;
            }
            .game-connection-indicator .sound-toggle:hover {
                opacity: 1;
            }
            .game-connection-indicator .sound-toggle.muted {
                position: relative;
            }
            .game-connection-indicator .sound-toggle.muted:after {
                content: "";
                position: absolute;
                width: 2px;
                height: 16px;
                background-color: #e74c3c;
                transform: rotate(45deg);
                top: -2px;
                left: 7px;
            }
        `;

        // Add to DOM
        document.head.appendChild(style);
        document.body.appendChild(indicator);

        // Add sound toggle functionality
        const soundToggle = indicator.querySelector('.sound-toggle');
        if (soundToggle) {
            // Set initial state
            if (!config.soundEnabled) {
                soundToggle.classList.add('muted');
            }

            // Add click handler
            soundToggle.addEventListener('click', () => {
                const soundEnabled = toggleSound();
                if (soundEnabled) {
                    soundToggle.classList.remove('muted');
                } else {
                    soundToggle.classList.add('muted');
                }
            });
        }

        // Set initial status
        updateConnectionStatus(false);
    }

    /**
     * Update the connection status indicator
     * @param {boolean} connected - Whether connected to the game
     */
    function updateConnectionStatus(connected) {
        const indicator = document.getElementById('gameConnectionIndicator');
        if (!indicator) return;

        if (connected) {
            indicator.className = 'game-connection-indicator connected';
        } else {
            indicator.className = 'game-connection-indicator disconnected';
        }
    }

    /**
     * Highlight a new draw
     * @param {number} drawNumber - The new draw number
     */
    function highlightNewDraw(drawNumber) {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = 'new-draw-notification';
        notification.innerHTML = `
            <div class="icon">🎯</div>
            <div class="content">
                <div class="title">New Draw!</div>
                <div class="draw-number">#${drawNumber}</div>
            </div>
        `;

        // Add styles if they don't exist
        if (!document.getElementById('newDrawNotificationStyles')) {
            const style = document.createElement('style');
            style.id = 'newDrawNotificationStyles';
            style.textContent = `
                .new-draw-notification {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background-color: #2ecc71;
                    color: white;
                    padding: 15px;
                    border-radius: 6px;
                    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
                    z-index: 10000;
                    display: flex;
                    align-items: center;
                    transform: translateX(120%);
                    animation: slideIn 0.4s forwards, fadeOut 0.4s 4s forwards;
                }
                .new-draw-notification .icon {
                    font-size: 24px;
                    margin-right: 10px;
                }
                .new-draw-notification .content {
                    display: flex;
                    flex-direction: column;
                }
                .new-draw-notification .title {
                    font-weight: bold;
                    margin-bottom: 3px;
                }
                .new-draw-notification .draw-number {
                    font-size: 18px;
                }
                @keyframes slideIn {
                    from { transform: translateX(120%); }
                    to { transform: translateX(0); }
                }
                @keyframes fadeOut {
                    from { opacity: 1; }
                    to { opacity: 0; }
                }
            `;
            document.head.appendChild(style);
        }

        // Add to DOM
        document.body.appendChild(notification);

        // Remove after animation
        setTimeout(() => {
            if (notification.parentNode) {
                document.body.removeChild(notification);
            }
        }, 5000);

        // Also try to update any relevant UI elements
        updateUIDrawNumber(drawNumber);

        // Update draw number display
        if (typeof updateDrawNumberDisplay === 'function') {
            updateDrawNumberDisplay();
            console.log('Updated draw number display from sync');
        }
    }

    /**
     * Update UI elements with the new draw number
     * @param {number} drawNumber - The new draw number
     */
    function updateUIDrawNumber(drawNumber) {
        // Find elements that might display the draw number
        const drawNumberElements = document.querySelectorAll('.draw-number, .current-draw, [data-draw-display]');

        drawNumberElements.forEach(element => {
            // Check if this element should display the draw number
            if (element.dataset.drawDisplay === 'current' ||
                element.classList.contains('current-draw')) {

                // Update the element
                element.textContent = drawNumber;

                // Add highlight animation
                element.classList.add('updated');
                setTimeout(() => {
                    element.classList.remove('updated');
                }, 2000);
            }
        });
    }

    /**
     * Clean up resources
     */
    function destroy() {
        // Close SSE connection
        if (eventSource) {
            eventSource.close();
            eventSource = null;
        }

        // Clear polling timer
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }

        // Clear listeners
        listeners = [];

        // Remove connection indicator
        const indicator = document.getElementById('gameConnectionIndicator');
        if (indicator && indicator.parentNode) {
            indicator.parentNode.removeChild(indicator);
        }
    }

    // Public API
    /**
     * Stop all listeners and cleanup
     */
    function stop() {
        // Stop Firebase listeners
        if (useFirebase && firebaseListeners.length > 0) {
            firebaseListeners.forEach(listenerKey => {
                if (listenerKey && window.FirebaseService) {
                    FirebaseService.unlisten(listenerKey);
                }
            });
            firebaseListeners = [];
        }

        // Stop SSE
        if (eventSource) {
            eventSource.close();
            eventSource = null;
        }

        // Stop polling
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }

        isConnected = false;
        updateConnectionStatus(false);
        console.log('GameSynchronizer stopped');
    }

    return {
        initialize,
        registerListener,
        destroy,
        getLastDrawNumber: () => lastDrawNumber,
        toggleSound
    };
})();

// Initialize when DOM is loaded and Firebase is ready
function initGameSynchronizer() {
    console.log('🔥 GameSynchronizer: Attempting to initialize...');
    console.log('🔥 FirebaseService available:', !!window.FirebaseService);
    console.log('🔥 FirebaseDrawManager available:', !!window.FirebaseDrawManager);
    
    // Initialize the synchronizer
    GameSynchronizer.initialize();

    // Register a listener for testing
    GameSynchronizer.registerListener(updateData => {
        console.log('Draw update received:', updateData);

        // Example of updating a specific UI element
        if (updateData.isNewDraw) {
            const event = new CustomEvent('newDrawReceived', {
                detail: updateData
            });
            document.dispatchEvent(event);
        }
    });
}

// Wait for DOM and Firebase
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        // Wait a bit for Firebase to initialize
        setTimeout(initGameSynchronizer, 1500);
    });
} else {
    setTimeout(initGameSynchronizer, 1500);
}

// Also listen for firebase-ready event
window.addEventListener('firebase-ready', () => {
    console.log('🔥 GameSynchronizer: firebase-ready event received');
    setTimeout(initGameSynchronizer, 500);
}, { once: true });