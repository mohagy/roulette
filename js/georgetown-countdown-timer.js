/**
 * Georgetown Countdown Timer
 * Real-time 3-minute countdown synchronized with Georgetown, Guyana time (GMT-4/UTC-4)
 * Persists across page refreshes and maintains server synchronization
 */

const GeorgetownCountdownTimer = (function() {
    'use strict';

    // Configuration
    const config = {
        cycleDuration: 180, // 3 minutes in seconds
        syncInterval: 30000, // Sync with server every 30 seconds
        updateInterval: 1000, // Update display every second
        serverEndpoint: 'php/get_georgetown_time.php',
        timezoneOffset: -4, // Georgetown is UTC-4
        fallbackEnabled: true
    };

    // State management
    let timerElement = null;
    let countdownInterval = null;
    let syncInterval = null;
    let currentCountdown = 180; // Start with 3 minutes
    let lastServerSync = null;
    let isInitialized = false;
    let serverTimeOffset = 0; // Difference between server and client time

    /**
     * Create and position the countdown timer element
     */
    function createTimerElement() {
        // Remove existing timer if present
        const existingTimer = document.getElementById('georgetown-countdown-timer');
        if (existingTimer) {
            existingTimer.remove();
        }

        // Create timer container
        timerElement = document.createElement('div');
        timerElement.id = 'georgetown-countdown-timer';
        timerElement.className = 'countdown-timer-container';

        // Create timer display
        const timerDisplay = document.createElement('div');
        timerDisplay.className = 'countdown-display';
        timerDisplay.textContent = '03:00';

        // Create timer label
        const timerLabel = document.createElement('div');
        timerLabel.className = 'countdown-label';
        timerLabel.textContent = 'NEXT SPIN IN';

        // Create sync indicator
        const syncIndicator = document.createElement('div');
        syncIndicator.className = 'sync-indicator';
        syncIndicator.innerHTML = '<i class="fas fa-sync-alt"></i>';

        // Assemble timer
        timerElement.appendChild(timerLabel);
        timerElement.appendChild(timerDisplay);
        timerElement.appendChild(syncIndicator);

        // Apply styles
        applyTimerStyles();

        // Add to page
        document.body.appendChild(timerElement);

        console.log('🕒 Georgetown countdown timer element created');
        return timerElement;
    }

    /**
     * Apply comprehensive styling to the timer
     */
    function applyTimerStyles() {
        if (!timerElement) return;

        // Main container styles
        timerElement.style.cssText = `
            position: fixed !important;
            bottom: 20px !important;
            left: 50% !important;
            transform: translateX(-50%) !important;
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%) !important;
            border: 2px solid #ffd700 !important;
            border-radius: 15px !important;
            padding: 15px 25px !important;
            font-family: 'Arial', sans-serif !important;
            text-align: center !important;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3), 0 0 20px rgba(255, 215, 0, 0.2) !important;
            z-index: 9999 !important;
            min-width: 200px !important;
            backdrop-filter: blur(10px) !important;
            user-select: none !important;
            transition: all 0.3s ease !important;
        `;

        // Label styles
        const label = timerElement.querySelector('.countdown-label');
        if (label) {
            label.style.cssText = `
                color: #ffd700 !important;
                font-size: 12px !important;
                font-weight: bold !important;
                letter-spacing: 1px !important;
                margin-bottom: 5px !important;
                text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5) !important;
            `;
        }

        // Display styles
        const display = timerElement.querySelector('.countdown-display');
        if (display) {
            display.style.cssText = `
                color: #ffffff !important;
                font-size: 28px !important;
                font-weight: bold !important;
                font-family: 'Courier New', monospace !important;
                text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5) !important;
                margin: 5px 0 !important;
                letter-spacing: 2px !important;
            `;
        }

        // Sync indicator styles
        const syncIndicator = timerElement.querySelector('.sync-indicator');
        if (syncIndicator) {
            syncIndicator.style.cssText = `
                position: absolute !important;
                top: 5px !important;
                right: 8px !important;
                color: #4CAF50 !important;
                font-size: 10px !important;
                opacity: 0.7 !important;
                transition: all 0.3s ease !important;
            `;
        }

        // Add hover effect
        timerElement.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(-50%) scale(1.05)';
            this.style.boxShadow = '0 12px 35px rgba(0, 0, 0, 0.4), 0 0 30px rgba(255, 215, 0, 0.3)';
        });

        timerElement.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(-50%) scale(1)';
            this.style.boxShadow = '0 8px 25px rgba(0, 0, 0, 0.3), 0 0 20px rgba(255, 215, 0, 0.2)';
        });

        console.log('🎨 Timer styles applied');
    }

    /**
     * Fetch current Georgetown time from server
     */
    async function fetchGeorgetownTime() {
        try {
            const response = await fetch(config.serverEndpoint + '?t=' + Date.now());

            if (!response.ok) {
                throw new Error(`Server responded with status: ${response.status}`);
            }

            const data = await response.json();

            if (data.status === 'success') {
                lastServerSync = Date.now();

                // Calculate server time offset
                const serverTimestamp = data.georgetown_time.timestamp * 1000; // Convert to milliseconds
                const clientTimestamp = Date.now();
                serverTimeOffset = serverTimestamp - clientTimestamp;

                console.log('🕒 Server sync successful:', {
                    serverTime: data.georgetown_time.formatted,
                    countdown: data.countdown.display_format,
                    remaining: data.countdown.total_seconds_remaining,
                    offset: serverTimeOffset
                });

                return data;
            } else {
                throw new Error(data.message || 'Server returned error status');
            }
        } catch (error) {
            console.error('🕒 Failed to fetch Georgetown time:', error);

            if (config.fallbackEnabled) {
                return getFallbackTime();
            }

            throw error;
        }
    }

    /**
     * Fallback time calculation using client time with UTC-4 offset
     */
    function getFallbackTime() {
        console.log('🕒 Using fallback time calculation');

        const now = new Date();
        const utcTime = now.getTime() + (now.getTimezoneOffset() * 60000);
        const georgetownTime = new Date(utcTime + (config.timezoneOffset * 3600000));

        const currentSeconds = Math.floor(georgetownTime.getTime() / 1000);
        const cyclePosition = currentSeconds % config.cycleDuration;
        const remainingSeconds = config.cycleDuration - cyclePosition;

        const minutes = Math.floor(remainingSeconds / 60);
        const seconds = remainingSeconds % 60;

        return {
            status: 'fallback',
            georgetown_time: {
                formatted: georgetownTime.toLocaleString(),
                timestamp: Math.floor(georgetownTime.getTime() / 1000)
            },
            countdown: {
                total_seconds_remaining: remainingSeconds,
                minutes_remaining: minutes,
                seconds_remaining: seconds,
                display_format: `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`
            }
        };
    }

    /**
     * Get current countdown time (server-synchronized or fallback)
     */
    function getCurrentCountdown() {
        if (lastServerSync && (Date.now() - lastServerSync < config.syncInterval * 2)) {
            // Use server-synchronized time
            const adjustedTime = Date.now() + serverTimeOffset;
            const currentSeconds = Math.floor(adjustedTime / 1000);
            const cyclePosition = currentSeconds % config.cycleDuration;
            const remainingSeconds = config.cycleDuration - cyclePosition;

            return Math.max(0, remainingSeconds);
        } else {
            // Use fallback calculation
            const fallbackData = getFallbackTime();
            return fallbackData.countdown.total_seconds_remaining;
        }
    }

    /**
     * Update the timer display
     */
    function updateDisplay() {
        if (!timerElement) return;

        currentCountdown = getCurrentCountdown();

        const minutes = Math.floor(currentCountdown / 60);
        const seconds = currentCountdown % 60;
        const displayText = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

        const display = timerElement.querySelector('.countdown-display');
        if (display) {
            display.textContent = displayText;

            // Add visual effects based on time remaining
            if (currentCountdown <= 10) {
                // Critical time - red and pulsing
                display.style.color = '#ff4444';
                display.style.animation = 'pulse 0.5s infinite alternate';
            } else if (currentCountdown <= 30) {
                // Warning time - orange
                display.style.color = '#ff8800';
                display.style.animation = 'none';
            } else {
                // Normal time - white
                display.style.color = '#ffffff';
                display.style.animation = 'none';
            }
        }

        // Update sync indicator
        const syncIndicator = timerElement.querySelector('.sync-indicator');
        if (syncIndicator) {
            const timeSinceSync = lastServerSync ? Date.now() - lastServerSync : Infinity;
            if (timeSinceSync < config.syncInterval) {
                syncIndicator.style.color = '#4CAF50'; // Green - recently synced
            } else if (timeSinceSync < config.syncInterval * 2) {
                syncIndicator.style.color = '#ff8800'; // Orange - sync needed soon
            } else {
                syncIndicator.style.color = '#ff4444'; // Red - sync overdue
            }
        }

        // Handle cycle reset
        if (currentCountdown <= 0) {
            handleCycleReset();
        }
    }

    /**
     * Handle countdown cycle reset
     */
    function handleCycleReset() {
        console.log('🕒 Countdown cycle reset - starting new 3-minute cycle');

        // Trigger cycle reset event
        document.dispatchEvent(new CustomEvent('countdownCycleReset', {
            detail: {
                timestamp: Date.now(),
                georgetownTime: new Date(Date.now() + serverTimeOffset)
            }
        }));

        // Force server sync on reset
        syncWithServer();
    }

    /**
     * Sync with server
     */
    async function syncWithServer() {
        try {
            const syncIndicator = timerElement?.querySelector('.sync-indicator');
            if (syncIndicator) {
                syncIndicator.style.animation = 'spin 1s linear infinite';
            }

            const data = await fetchGeorgetownTime();

            if (data && data.countdown) {
                currentCountdown = data.countdown.total_seconds_remaining;
                console.log('🕒 Sync complete - countdown updated to:', currentCountdown);
            }

            if (syncIndicator) {
                syncIndicator.style.animation = 'none';
            }

        } catch (error) {
            console.error('🕒 Sync failed:', error);

            const syncIndicator = timerElement?.querySelector('.sync-indicator');
            if (syncIndicator) {
                syncIndicator.style.animation = 'none';
                syncIndicator.style.color = '#ff4444';
            }
        }
    }

    /**
     * Add CSS animations
     */
    function addAnimations() {
        if (document.getElementById('countdown-timer-animations')) return;

        const style = document.createElement('style');
        style.id = 'countdown-timer-animations';
        style.textContent = `
            @keyframes pulse {
                from { opacity: 1; }
                to { opacity: 0.5; }
            }

            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }

            @media (max-width: 768px) {
                #georgetown-countdown-timer {
                    bottom: 10px !important;
                    padding: 10px 20px !important;
                    min-width: 150px !important;
                }

                #georgetown-countdown-timer .countdown-display {
                    font-size: 24px !important;
                }

                #georgetown-countdown-timer .countdown-label {
                    font-size: 10px !important;
                }
            }
        `;

        document.head.appendChild(style);
    }

    /**
     * Initialize the countdown timer
     */
    async function initialize() {
        if (isInitialized) {
            console.log('🕒 Timer already initialized');
            return;
        }

        console.log('🕒 Initializing Georgetown countdown timer...');

        try {
            // Add animations
            addAnimations();

            // Create timer element
            createTimerElement();

            // Initial server sync
            await syncWithServer();

            // Start update interval
            countdownInterval = setInterval(updateDisplay, config.updateInterval);

            // Start sync interval
            syncInterval = setInterval(syncWithServer, config.syncInterval);

            // Handle page visibility changes
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    console.log('🕒 Page became visible - syncing with server');
                    syncWithServer();
                }
            });

            // Handle window focus
            window.addEventListener('focus', function() {
                console.log('🕒 Window focused - syncing with server');
                syncWithServer();
            });

            isInitialized = true;
            console.log('🕒 Georgetown countdown timer initialized successfully');

        } catch (error) {
            console.error('🕒 Failed to initialize timer:', error);

            // Try fallback initialization
            if (config.fallbackEnabled) {
                console.log('🕒 Attempting fallback initialization...');
                createTimerElement();
                countdownInterval = setInterval(updateDisplay, config.updateInterval);
                isInitialized = true;
            }
        }
    }

    /**
     * Destroy the timer
     */
    function destroy() {
        console.log('🕒 Destroying Georgetown countdown timer');

        if (countdownInterval) {
            clearInterval(countdownInterval);
            countdownInterval = null;
        }

        if (syncInterval) {
            clearInterval(syncInterval);
            syncInterval = null;
        }

        if (timerElement) {
            timerElement.remove();
            timerElement = null;
        }

        const animations = document.getElementById('countdown-timer-animations');
        if (animations) {
            animations.remove();
        }

        isInitialized = false;
        lastServerSync = null;
        serverTimeOffset = 0;
    }

    /**
     * Get current timer status
     */
    function getStatus() {
        return {
            isInitialized,
            currentCountdown,
            lastServerSync,
            serverTimeOffset,
            timeSinceSync: lastServerSync ? Date.now() - lastServerSync : null,
            isVisible: timerElement && timerElement.style.display !== 'none'
        };
    }

    /**
     * Manual sync trigger
     */
    function forceSync() {
        console.log('🕒 Manual sync triggered');
        return syncWithServer();
    }

    // Public API
    return {
        initialize,
        destroy,
        getStatus,
        forceSync,

        // Configuration access
        getConfig: () => ({ ...config }),
        updateConfig: (newConfig) => Object.assign(config, newConfig)
    };
})();

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', GeorgetownCountdownTimer.initialize);
} else {
    // DOM is already ready
    GeorgetownCountdownTimer.initialize();
}

// Global access for debugging
window.GeorgetownCountdownTimer = GeorgetownCountdownTimer;
