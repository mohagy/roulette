/**
 * Georgetown Time-Synchronized 3-Minute Countdown Timer
 *
 * Uses Georgetown, Guyana timezone (GMT-4) as authoritative time source.
 * Follows precise 3-minute cycles based on Georgetown time (:00, :03, :06, :09, etc.)
 * Automatically refreshes page when countdown reaches zero.
 */

(function() {
    'use strict';

    // Timer configuration
    const CYCLE_DURATION = 180; // 3 minutes in seconds
    const UPDATE_INTERVAL = 1000; // Update every second
    const SYNC_INTERVAL = 5000; // Sync with Georgetown server every 5 seconds for accuracy
    const GEORGETOWN_TIMEZONE = 'America/Guyana'; // GMT-4

    // Timer state
    let currentCountdown = CYCLE_DURATION;
    let timerInterval = null;
    let syncInterval = null;
    let timerElement = null;
    let refreshTriggered = false;
    let lastServerSync = null;
    let georgetownTimeOffset = 0;
    let lastGeorgetownTime = null;

    console.log('🕒 Georgetown Time-Synchronized countdown timer initializing...');

    /**
     * Create and inject the timer display into the page
     */
    function createTimerDisplay() {
        // Create timer container as a floating element
        timerElement = document.createElement('div');
        timerElement.id = 'real-time-countdown-timer';
        timerElement.className = 'countdown-timer-floating';

        // Create timer content
        timerElement.innerHTML = `
            <div class="countdown-container">
                <div class="countdown-label">NEXT DRAW IN</div>
                <div class="countdown-time" id="countdown-time">03:00</div>
                <div class="countdown-sync-indicator" id="sync-indicator">
                    <i class="fas fa-sync-alt"></i>
                </div>
            </div>
        `;

        // Add styles
        addTimerStyles();

        // Append to body for floating behavior
        document.body.appendChild(timerElement);

        // Force visibility
        timerElement.style.display = 'block';
        timerElement.style.visibility = 'visible';
        timerElement.style.opacity = '1';
        timerElement.style.zIndex = '99999';

        console.log('🕒 Floating timer display created and added to page', timerElement);
    }

    /**
     * Add CSS styles for the timer
     */
    function addTimerStyles() {
        const styleId = 'countdown-timer-styles';
        if (document.getElementById(styleId)) return;

        const styles = document.createElement('style');
        styles.id = styleId;
        styles.textContent = `
            .countdown-timer-floating {
                position: fixed;
                bottom: 20px;
                left: 50%;
                transform: translateX(-50%);
                background: linear-gradient(135deg, rgba(10, 10, 20, 0.98) 0%, rgba(20, 20, 35, 0.98) 100%);
                border: 3px solid #FFD700;
                border-radius: 20px;
                padding: 20px 35px;
                text-align: center;
                box-shadow: 
                    0 10px 40px rgba(0, 0, 0, 0.7),
                    0 0 30px rgba(255, 215, 0, 0.4),
                    inset 0 0 20px rgba(255, 215, 0, 0.1);
                backdrop-filter: blur(15px);
                z-index: 9999;
                min-width: 240px;
                max-width: 320px;
                overflow: visible;
                transition: all 0.3s ease;
            }

            .countdown-timer-floating:hover {
                transform: translateX(-50%) translateY(-5px);
                box-shadow: 
                    0 15px 50px rgba(0, 0, 0, 0.8),
                    0 0 40px rgba(255, 215, 0, 0.5),
                    inset 0 0 25px rgba(255, 215, 0, 0.15);
                border-color: #FFA500;
            }

            .countdown-timer-floating::before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255, 215, 0, 0.15), transparent);
                animation: shimmer 4s infinite;
                border-radius: 20px;
            }

            @keyframes shimmer {
                0% { left: -100%; }
                100% { left: 100%; }
            }

            .countdown-container {
                position: relative;
                z-index: 1;
            }

            .countdown-label {
                color: #FFD700;
                font-size: 13px;
                font-weight: 900;
                letter-spacing: 2px;
                margin-bottom: 10px;
                text-transform: uppercase;
                text-shadow: 
                    0 0 10px rgba(255, 215, 0, 0.8),
                    0 2px 4px rgba(0, 0, 0, 0.9);
                font-family: 'Arial', sans-serif;
            }

            .countdown-time {
                color: #FFD700 !important;
                font-size: 48px !important;
                font-weight: 900 !important;
                font-family: 'Orbitron', 'Courier New', 'Digital-7', monospace !important;
                text-shadow: 0 1px 1px rgba(0, 0, 0, 0.8) !important;
                margin: 8px 0;
                transition: all 0.3s ease;
                letter-spacing: 4px;
                line-height: 1.2;
                background: transparent !important;
                -webkit-background-clip: unset !important;
                -webkit-text-fill-color: #FFD700 !important;
                background-clip: unset !important;
                filter: none !important;
                display: inline-block;
                padding: 5px 0;
                -webkit-font-smoothing: antialiased !important;
                -moz-osx-font-smoothing: grayscale !important;
            }

            .countdown-time.warning {
                color: #ff9800 !important;
                background: transparent !important;
                -webkit-background-clip: unset !important;
                -webkit-text-fill-color: #ff9800 !important;
                background-clip: unset !important;
                text-shadow: 0 1px 1px rgba(0, 0, 0, 1) !important;
                filter: none !important;
                animation: pulse 1s infinite;
                -webkit-font-smoothing: antialiased !important;
                -moz-osx-font-smoothing: grayscale !important;
            }

            .countdown-time.critical {
                color: #f44336 !important;
                background: transparent !important;
                -webkit-background-clip: unset !important;
                -webkit-text-fill-color: #f44336 !important;
                background-clip: unset !important;
                text-shadow: 0 1px 1px rgba(0, 0, 0, 1) !important;
                filter: none !important;
                animation: pulse 0.5s infinite;
                -webkit-font-smoothing: antialiased !important;
                -moz-osx-font-smoothing: grayscale !important;
            }

            .countdown-timer-floating.warning {
                border-color: #ff9800;
                box-shadow: 0 8px 32px rgba(255, 152, 0, 0.4), 0 4px 16px rgba(0, 0, 0, 0.5);
            }

            .countdown-timer-floating.critical {
                border-color: #f44336;
                box-shadow: 0 8px 32px rgba(244, 67, 54, 0.5), 0 4px 16px rgba(0, 0, 0, 0.5);
                animation: shake 0.5s infinite;
            }

            @keyframes pulse {
                0% { transform: scale(1); opacity: 1; }
                50% { transform: scale(1.05); opacity: 0.8; }
                100% { transform: scale(1); opacity: 1; }
            }

            @keyframes shake {
                0%, 100% { transform: translateX(-50%) translateY(0); }
                25% { transform: translateX(-50%) translateY(-2px); }
                75% { transform: translateX(-50%) translateY(2px); }
            }

            .countdown-sync-indicator {
                color: #FFD700;
                font-size: 10px;
                margin-top: 6px;
                opacity: 0.6;
                transition: all 0.3s ease;
                text-shadow: 0 1px 2px rgba(0, 0, 0, 0.8);
            }

            .countdown-sync-indicator.syncing {
                animation: spin 1s linear infinite;
                opacity: 0.8;
            }

            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }

            .countdown-sync-indicator.error {
                color: #f44336;
                opacity: 0.8;
            }

            /* Responsive design for mobile devices */
            @media (max-width: 768px) {
                .countdown-timer-floating {
                    bottom: 15px;
                    min-width: 220px;
                    max-width: 280px;
                    padding: 16px 28px;
                }

                .countdown-time {
                    font-size: 40px !important;
                    letter-spacing: 3px;
                }

                .countdown-label {
                    font-size: 12px;
                    margin-bottom: 8px;
                }

                .countdown-sync-indicator {
                    font-size: 9px;
                    margin-top: 6px;
                }
            }

            @media (max-width: 480px) {
                .countdown-timer-floating {
                    bottom: 10px;
                    min-width: 200px;
                    max-width: 260px;
                    padding: 14px 24px;
                }

                .countdown-time {
                    font-size: 36px !important;
                    letter-spacing: 2px;
                }

                .countdown-label {
                    font-size: 11px;
                    margin-bottom: 6px;
                }

                .countdown-sync-indicator {
                    font-size: 8px;
                    margin-top: 5px;
                }
            }
        `;

        document.head.appendChild(styles);
        console.log('🎨 Timer styles added');
    }

    /**
     * Update the timer display
     */
    function updateDisplay() {
        if (!timerElement) return;

        const timeElement = document.getElementById('countdown-time');
        if (!timeElement) return;

        // Calculate minutes and seconds
        const minutes = Math.floor(currentCountdown / 60);
        const seconds = currentCountdown % 60;
        const displayText = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

        // Update display
        timeElement.textContent = displayText;

        // ✅ FORCE VISIBILITY: Apply inline styles - small but visible
        timeElement.style.cssText = `
            color: #FFD700 !important;
            font-size: 20px !important;
            font-weight: 700 !important;
            font-family: 'Orbitron', 'Courier New', monospace !important;
            text-shadow: 
                0 0 8px rgba(255, 215, 0, 0.9),
                0 0 16px rgba(255, 215, 0, 0.5),
                0 2px 4px rgba(0, 0, 0, 0.9) !important;
            margin: 4px 0 !important;
            letter-spacing: 2px !important;
            line-height: 1.2 !important;
            display: inline-block !important;
            padding: 2px 0 !important;
            background: transparent !important;
        `;

        // Update page title with Georgetown time reference
        document.title = `Roulette - ${displayText} (Georgetown)`;

        // Apply warning styles to both time element and container
        timeElement.className = 'countdown-time';
        timerElement.className = 'countdown-timer-floating';

        if (currentCountdown <= 10) {
            timeElement.className = 'countdown-time critical';
            timerElement.className += ' critical';
            // Critical state styles - minimal shadow for clarity
            timeElement.style.color = '#f44336';
            timeElement.style.textShadow = '0 1px 1px rgba(0, 0, 0, 1)';
        } else if (currentCountdown <= 30) {
            timeElement.className = 'countdown-time warning';
            timerElement.className += ' warning';
            // Warning state styles - minimal shadow for clarity
            timeElement.style.color = '#ff9800';
            timeElement.style.textShadow = '0 1px 1px rgba(0, 0, 0, 1)';
        } else {
            // Normal state - ensure gold color with minimal shadow
            timeElement.style.color = '#FFD700';
            timeElement.style.textShadow = '0 1px 1px rgba(0, 0, 0, 0.8)';
        }

        // Log countdown progress
        if (currentCountdown <= 10) {
            console.log(`🚨 Countdown: ${currentCountdown}s - ${displayText}`);
        } else if (currentCountdown % 30 === 0) {
            console.log(`⏰ Countdown: ${currentCountdown}s - ${displayText}`);
        }

        // Check for zero and refresh
        if (currentCountdown <= 0) {
            handleCountdownZero();
        }
    }

    /**
     * Handle countdown reaching zero
     */
    function handleCountdownZero() {
        if (refreshTriggered) return;

        refreshTriggered = true;
        console.log('🎯 COUNTDOWN REACHED ZERO!');
        console.log('🔄 Refreshing page to start new cycle...');

        // Stop all intervals
        if (timerInterval) clearInterval(timerInterval);
        if (syncInterval) clearInterval(syncInterval);

        // Show refresh message
        const timeElement = document.getElementById('countdown-time');
        if (timeElement) {
            timeElement.textContent = '00:00';
            timeElement.className = 'critical';
        }

        // Refresh the page after a brief delay
        setTimeout(() => {
            console.log('🔄 EXECUTING PAGE REFRESH');
            window.location.reload(true);
        }, 500);
    }

    /**
     * Sync with Georgetown time server
     */
    async function syncWithServer() {
        const syncIndicator = document.getElementById('sync-indicator');

        try {
            if (syncIndicator) {
                syncIndicator.className = 'countdown-sync-indicator syncing';
            }

            console.log('🔄 Syncing with Georgetown time server...');

            const response = await fetch('php/get_georgetown_time.php?t=' + Date.now());
            const data = await response.json();

            if (data && data.status === 'success' && data.countdown && data.georgetown_time) {
                const serverCountdown = data.countdown.total_seconds_remaining;
                const georgetownTimestamp = data.georgetown_time.timestamp;

                // Calculate Georgetown time offset for client-side calculations
                const now = Date.now();
                georgetownTimeOffset = now - (georgetownTimestamp * 1000);
                lastServerSync = now;
                lastGeorgetownTime = georgetownTimestamp;

                // Update countdown from Georgetown server
                currentCountdown = Math.max(0, serverCountdown);

                console.log(`✅ Georgetown sync successful: ${currentCountdown}s remaining`);
                console.log(`🌍 Georgetown time: ${data.georgetown_time.formatted}`);
                console.log(`⏰ Next cycle: ${data.next_cycle.start_time}`);

                if (syncIndicator) {
                    syncIndicator.className = 'countdown-sync-indicator';
                }

                // Update display immediately
                updateDisplay();

            } else {
                throw new Error('Invalid Georgetown server response');
            }

        } catch (error) {
            console.error('❌ Georgetown sync failed:', error.message);

            if (syncIndicator) {
                syncIndicator.className = 'countdown-sync-indicator error';
            }

            // Use Georgetown time fallback
            useGeorgetownTimeFallback();
        }
    }

    /**
     * Use Georgetown time-based fallback when server is unavailable
     */
    function useGeorgetownTimeFallback() {
        console.log('⚠️ Using Georgetown time fallback calculation');

        try {
            // If we have recent Georgetown time data, calculate based on elapsed time
            if (lastServerSync && lastGeorgetownTime && Date.now() - lastServerSync < 30000) {
                const elapsedMs = Date.now() - lastServerSync;
                const elapsedSeconds = Math.floor(elapsedMs / 1000);
                currentCountdown = Math.max(0, currentCountdown - elapsedSeconds);

                console.log(`📊 Fallback: ${elapsedSeconds}s elapsed, ${currentCountdown}s remaining`);
            } else {
                // Use browser's Georgetown time calculation as last resort
                const now = new Date();
                const georgetownTime = new Date(now.getTime() - (4 * 60 * 60 * 1000)); // UTC-4

                const minutes = georgetownTime.getMinutes();
                const seconds = georgetownTime.getSeconds();

                // Calculate position in 3-minute cycle
                const cycleMinute = minutes % 3;
                const secondsInCycle = (cycleMinute * 60) + seconds;
                currentCountdown = CYCLE_DURATION - secondsInCycle;

                console.log(`🌍 Browser Georgetown fallback: ${currentCountdown}s remaining`);
            }

            // Ensure countdown is valid
            if (currentCountdown <= 0 || currentCountdown > CYCLE_DURATION) {
                currentCountdown = CYCLE_DURATION;
                console.log('🔄 Reset to full Georgetown 3-minute cycle');
            }

        } catch (error) {
            console.error('❌ Georgetown fallback failed:', error);
            currentCountdown = CYCLE_DURATION;
        }
    }

    /**
     * Start the countdown timer
     */
    function startTimer() {
        console.log('▶️ Starting countdown timer');

        // Clear any existing intervals
        if (timerInterval) clearInterval(timerInterval);
        if (syncInterval) clearInterval(syncInterval);

        // Start countdown interval
        timerInterval = setInterval(() => {
            if (currentCountdown > 0) {
                currentCountdown--;
                updateDisplay();
            }
        }, UPDATE_INTERVAL);

        // Start sync interval
        syncInterval = setInterval(syncWithServer, SYNC_INTERVAL);

        // Initial sync and display update
        syncWithServer();
        updateDisplay();

        console.log('✅ Timer started successfully');
    }

    /**
     * Initialize the timer system
     */
    function initialize() {
        console.log('🚀 Initializing real-time countdown timer...');

        // Create the display
        createTimerDisplay();

        // Start the timer
        startTimer();

        // Add page visibility change handler
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                console.log('👁️ Page became visible - syncing with server');
                syncWithServer();
            }
        });

        // Add window focus handler
        window.addEventListener('focus', () => {
            console.log('🎯 Window focused - syncing with server');
            syncWithServer();
        });

        // Add beforeunload handler
        window.addEventListener('beforeunload', () => {
            if (refreshTriggered) {
                console.log('📄 Page refreshing due to countdown timer - SUCCESS!');
            }
        });

        console.log('✅ Real-time countdown timer initialized successfully');
    }

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        // DOM is already ready
        setTimeout(initialize, 100);
    }
    
    // Force initialization after a delay to ensure it runs
    setTimeout(() => {
        const timerEl = document.getElementById('real-time-countdown-timer');
        if (!timerEl) {
            console.log('🕒 Timer not found, re-initializing...');
            initialize();
        } else {
            console.log('🕒 Timer element exists:', timerEl);
            // Force visibility
            timerEl.style.display = 'block';
            timerEl.style.visibility = 'visible';
            timerEl.style.opacity = '1';
        }
    }, 2000);

    // Global access for debugging
    window.GeorgetownCountdownTimer = {
        getCurrentCountdown: () => currentCountdown,
        getLastSync: () => lastServerSync,
        getLastGeorgetownTime: () => lastGeorgetownTime,
        forceSync: syncWithServer,
        getStatus: () => ({
            currentCountdown,
            lastServerSync,
            lastGeorgetownTime,
            georgetownTimeOffset,
            refreshTriggered,
            isRunning: !!timerInterval,
            timezone: GEORGETOWN_TIMEZONE
        })
    };

})();
