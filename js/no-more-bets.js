/**
 * Glass Morphism No More Bets System
 * 
 * Disables the roulette betting board when countdown reaches 10 seconds
 * Shows elegant glass morphism overlay with professional design
 * Integrates seamlessly with Georgetown countdown timer system
 */

(function() {
    'use strict';

    // Configuration
    const NO_MORE_BETS_THRESHOLD = 10; // Disable betting at 10 seconds
    const CHECK_INTERVAL = 500; // Check countdown every 500ms for accuracy
    
    // State tracking
    let bettingDisabled = false;
    let checkInterval = null;
    let noMoreBetsOverlay = null;
    let originalBettingHandlers = new Map();
    
    console.log('✨ Glass Morphism No More Bets system initializing...');

    /**
     * Create the glass morphism "No More Bets" overlay
     */
    function createNoMoreBetsOverlay() {
        // Remove existing overlay if present
        if (noMoreBetsOverlay) {
            noMoreBetsOverlay.remove();
        }

        noMoreBetsOverlay = document.createElement('div');
        noMoreBetsOverlay.id = 'no-more-bets-overlay';
        noMoreBetsOverlay.className = 'no-more-bets-overlay hidden';
        
        noMoreBetsOverlay.innerHTML = `
            <div class="no-more-bets-container">
                <div class="no-more-bets-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="no-more-bets-message">No More Bets</div>
                <div class="no-more-bets-subtitle">Betting is now closed for this draw</div>
                <div class="no-more-bets-countdown">
                    <span id="no-more-bets-time">00:10</span>
                </div>
            </div>
        `;

        // Add styles
        addNoMoreBetsStyles();

        // Insert overlay into the page
        document.body.appendChild(noMoreBetsOverlay);
        
        console.log('✨ Glass morphism overlay created');
    }

    /**
     * Add CSS styles for the glass morphism overlay
     */
    function addNoMoreBetsStyles() {
        const styleId = 'glass-no-more-bets-styles';
        if (document.getElementById(styleId)) return;

        // Check if external CSS file is loaded
        const existingLink = document.querySelector('link[href*="no-more-bets.css"]');
        if (existingLink) {
            console.log('✨ External glass morphism CSS already loaded');
            return;
        }

        // Add link to external CSS file
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'css/no-more-bets.css';
        link.id = styleId;
        document.head.appendChild(link);
        
        console.log('✨ Glass morphism styles loaded');
    }

    /**
     * Disable all betting functionality with glass morphism style
     */
    function disableBetting() {
        if (bettingDisabled) return;
        
        bettingDisabled = true;
        console.log('✨ BETTING DISABLED - Glass morphism overlay active');

        // Show overlay with elegant entrance
        if (noMoreBetsOverlay) {
            noMoreBetsOverlay.classList.remove('hidden');
        }

        // Disable betting area with elegant transition
        const bettingArea = document.querySelector('.betting-area');
        if (bettingArea) {
            bettingArea.classList.add('betting-disabled');
        }

        // Disable bet display container
        const betDisplay = document.querySelector('.bet-display-container');
        if (betDisplay) {
            betDisplay.classList.add('betting-disabled');
        }

        // Disable complete button
        const completeButton = document.querySelector('.button-complete');
        if (completeButton) {
            completeButton.classList.add('betting-disabled');
        }

        // Disable all betting numbers and parts
        const bettingElements = document.querySelectorAll('.betting-area .number, .betting-area .part');
        bettingElements.forEach(element => {
            // Store original event handlers
            const events = ['click', 'mousedown', 'mouseup', 'touchstart', 'touchend'];
            events.forEach(eventType => {
                const handlers = element.cloneNode(true);
                originalBettingHandlers.set(`${element.className}-${eventType}`, handlers);
            });
            
            // Remove event listeners by replacing the element
            const newElement = element.cloneNode(true);
            element.parentNode.replaceChild(newElement, element);
        });

        // Disable stake input
        const stakeInput = document.getElementById('global-stake-input');
        if (stakeInput) {
            stakeInput.disabled = true;
        }

        // Show elegant notification
        showGlassAlert();
    }

    /**
     * Re-enable betting functionality (for next round)
     */
    function enableBetting() {
        if (!bettingDisabled) return;
        
        bettingDisabled = false;
        console.log('✨ BETTING ENABLED - New round started');

        // Hide overlay with elegant exit
        if (noMoreBetsOverlay) {
            noMoreBetsOverlay.classList.add('hidden');
        }

        // Re-enable betting area
        const bettingArea = document.querySelector('.betting-area');
        if (bettingArea) {
            bettingArea.classList.remove('betting-disabled');
        }

        // Re-enable bet display container
        const betDisplay = document.querySelector('.bet-display-container');
        if (betDisplay) {
            betDisplay.classList.remove('betting-disabled');
        }

        // Re-enable complete button
        const completeButton = document.querySelector('.button-complete');
        if (completeButton) {
            completeButton.classList.remove('betting-disabled');
        }

        // Re-enable stake input
        const stakeInput = document.getElementById('global-stake-input');
        if (stakeInput) {
            stakeInput.disabled = false;
        }
    }

    /**
     * Show elegant glass morphism alert message
     */
    function showGlassAlert() {
        // Check if alert container exists
        let alertContainer = document.querySelector('.alert-message-container.alert-no-more-bets');
        
        if (!alertContainer) {
            alertContainer = document.createElement('div');
            alertContainer.className = 'alert-message-container alert-no-more-bets';
            alertContainer.innerHTML = '<div class="alert-message">✨ Betting window has closed gracefully</div>';
            
            // Add to page
            document.body.appendChild(alertContainer);
        }

        // Show alert with elegant timing
        alertContainer.style.display = 'block';
        setTimeout(() => {
            if (alertContainer) {
                alertContainer.style.display = 'none';
            }
        }, 3500);
    }

    /**
     * Update the countdown display in the overlay
     */
    function updateOverlayCountdown(seconds) {
        const timeElement = document.getElementById('no-more-bets-time');
        if (timeElement) {
            const minutes = Math.floor(seconds / 60);
            const secs = seconds % 60;
            timeElement.textContent = `${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        }
    }

    /**
     * Check countdown and manage betting state
     */
    function checkCountdown() {
        // Get current countdown from the Georgetown timer system
        let currentCountdown = 0;
        
        // Try to get countdown from the global Georgetown timer object
        if (window.GeorgetownCountdownTimer) {
            currentCountdown = window.GeorgetownCountdownTimer.getCurrentCountdown();
        } else {
            // Fallback: try to parse from timer display
            const timerElement = document.getElementById('countdown-time');
            if (timerElement) {
                const timeText = timerElement.textContent;
                const [minutes, seconds] = timeText.split(':').map(Number);
                currentCountdown = (minutes * 60) + seconds;
            }
        }

        // Update overlay countdown if betting is disabled
        if (bettingDisabled) {
            updateOverlayCountdown(currentCountdown);
        }

        // Check if we need to disable betting
        if (currentCountdown <= NO_MORE_BETS_THRESHOLD && currentCountdown > 0) {
            if (!bettingDisabled) {
                disableBetting();
            }
        } 
        // Re-enable betting when countdown resets (new round)
        else if (currentCountdown > NO_MORE_BETS_THRESHOLD) {
            if (bettingDisabled) {
                enableBetting();
            }
        }
    }

    /**
     * Initialize the Glass Morphism No More Bets system
     */
    function initialize() {
        console.log('✨ Initializing Glass Morphism No More Bets system...');

        // Create overlay
        createNoMoreBetsOverlay();

        // Start checking countdown
        checkInterval = setInterval(checkCountdown, CHECK_INTERVAL);

        // Initial check
        checkCountdown();

        console.log('✨ Glass Morphism No More Bets system initialized successfully');
    }

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        // DOM is already ready
        setTimeout(initialize, 200); // Small delay to ensure timer is loaded
    }

    // Global access for debugging and integration
    window.NoMoreBetsSystem = {
        getCurrentState: () => ({
            bettingDisabled,
            threshold: NO_MORE_BETS_THRESHOLD,
            checkInterval: CHECK_INTERVAL,
            style: 'glass-morphism'
        }),
        forceDisable: disableBetting,
        forceEnable: enableBetting,
        checkNow: checkCountdown,
        isActive: () => bettingDisabled
    };

})();
