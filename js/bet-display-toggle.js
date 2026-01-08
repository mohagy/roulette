/**
 * Bet Display Container Toggle
 * Adds hide/unhide functionality to the bet display container
 */

(function() {
    'use strict';

    console.log('🎰 Bet Display Toggle - Initializing...');

    let isVisible = true;
    const sessionStorageKey = 'betDisplayVisible';
    let betDisplayContainer = null;
    let betDisplayToggle = null;

    /**
     * Create the left-side toggle button for bet display
     */
    function createLeftSideToggle() {
        console.log('🎰 Creating left-side bet display toggle...');
        
        // Remove existing toggle if any
        const existing = document.getElementById('left-side-bet-display-toggle');
        if (existing) {
            existing.remove();
        }
        
        // Create toggle element
        const leftToggle = document.createElement('div');
        leftToggle.id = 'left-side-bet-display-toggle';
        leftToggle.className = 'left-side-bet-toggle-control';
        leftToggle.innerHTML = `
            <div class="toggle-tab">
                <div class="toggle-icon">
                    <i class="fas fa-list"></i>
                </div>
                <div class="toggle-text">
                    <span class="toggle-label">BETS</span>
                    <span class="toggle-status">VISIBLE</span>
                </div>
                <div class="toggle-arrow">
                    <i class="fas fa-chevron-left"></i>
                </div>
            </div>
        `;
        
        // Add comprehensive styles
        addLeftToggleStyles();
        
        // Add click event
        leftToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleBetDisplay();
        });
        
        // Append to body
        document.body.appendChild(leftToggle);
        console.log('🎰 Left-side bet display toggle created');
        
        // Restore saved state
        restoreVisibilityState();
    }

    /**
     * Add CSS styles for the left-side toggle
     */
    function addLeftToggleStyles() {
        const styleId = 'left-side-bet-toggle-styles';
        if (document.getElementById(styleId)) return;

        const styles = document.createElement('style');
        styles.id = styleId;
        styles.textContent = `
            .left-side-bet-toggle-control {
                position: fixed;
                left: 0;
                top: calc(50% - 80px); /* Position slightly above cashier toggle */
                transform: translateY(-50%);
                z-index: 10001;
                font-family: 'Orbitron', 'Arial', sans-serif;
                cursor: pointer;
                user-select: none;
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .left-side-bet-toggle-control .toggle-tab {
                background: linear-gradient(135deg,
                    rgba(26, 26, 26, 0.95) 0%,
                    rgba(15, 20, 25, 0.95) 50%,
                    rgba(26, 26, 26, 0.95) 100%);
                border: 2px solid #4deeea;
                border-left: none;
                border-radius: 0 8px 8px 0;
                padding: 6px 5px 6px 3px;
                min-height: 60px;
                width: 32px;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 3px;
                box-shadow:
                    3px 0 15px rgba(77, 238, 234, 0.3),
                    1px 0 8px rgba(0, 0, 0, 0.3),
                    inset 1px 0 0 rgba(255, 255, 255, 0.1);
                backdrop-filter: blur(12px);
                position: relative;
                overflow: hidden;
                transform: translateX(-5px);
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .left-side-bet-toggle-control:hover .toggle-tab {
                transform: translateX(0);
                box-shadow:
                    4px 0 20px rgba(77, 238, 234, 0.4),
                    2px 0 12px rgba(0, 0, 0, 0.4);
                border-color: #4dcd91;
            }

            .left-side-bet-toggle-control .toggle-icon {
                color: #4deeea;
                font-size: 12px;
                text-shadow: 0 0 8px rgba(77, 238, 234, 0.5);
                transition: all 0.3s ease;
                margin-bottom: 1px;
            }

            .left-side-bet-toggle-control:hover .toggle-icon {
                transform: scale(1.08);
                text-shadow: 0 0 12px rgba(77, 238, 234, 0.7);
            }

            .left-side-bet-toggle-control .toggle-text {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 2px;
                writing-mode: vertical-rl;
                text-orientation: mixed;
                transform: rotate(180deg);
            }

            .left-side-bet-toggle-control .toggle-label {
                color: #4deeea;
                font-size: 7px;
                font-weight: 700;
                letter-spacing: 0.5px;
                text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
            }

            .left-side-bet-toggle-control .toggle-status {
                color: #ffffff;
                font-size: 6px;
                font-weight: 500;
                opacity: 0.8;
                text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
            }

            .left-side-bet-toggle-control .toggle-arrow {
                color: #4deeea;
                font-size: 9px;
                margin-top: 2px;
                transition: all 0.3s ease;
                text-shadow: 0 0 6px rgba(77, 238, 234, 0.5);
            }

            .left-side-bet-toggle-control:hover .toggle-arrow {
                transform: translateX(1px);
                text-shadow: 0 0 10px rgba(77, 238, 234, 0.7);
            }

            /* Hidden state styling */
            .left-side-bet-toggle-control.panel-hidden .toggle-tab {
                background: linear-gradient(135deg, 
                    rgba(40, 167, 69, 0.95) 0%, 
                    rgba(32, 201, 151, 0.95) 50%, 
                    rgba(40, 167, 69, 0.95) 100%);
                border-color: #28a745;
                box-shadow: 
                    4px 0 20px rgba(40, 167, 69, 0.3),
                    2px 0 10px rgba(0, 0, 0, 0.3);
            }

            .left-side-bet-toggle-control.panel-hidden:hover .toggle-tab {
                border-color: #20c997;
                box-shadow:
                    4px 0 20px rgba(40, 167, 69, 0.4),
                    2px 0 12px rgba(0, 0, 0, 0.4);
            }

            .left-side-bet-toggle-control.panel-hidden .toggle-icon {
                color: #ffffff;
                text-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
            }

            .left-side-bet-toggle-control.panel-hidden .toggle-label {
                color: #ffffff;
            }

            .left-side-bet-toggle-control.panel-hidden .toggle-arrow {
                color: #ffffff;
                text-shadow: 0 0 8px rgba(255, 255, 255, 0.5);
            }

            .left-side-bet-toggle-control.panel-hidden .toggle-arrow i {
                transform: rotate(180deg);
            }
        `;

        document.head.appendChild(styles);
    }

    /**
     * Update the toggle button state
     */
    function updateToggleButton() {
        const toggleButton = document.getElementById('left-side-bet-display-toggle');
        if (!toggleButton) return;

        if (isVisible) {
            toggleButton.classList.remove('panel-hidden');
            const status = toggleButton.querySelector('.toggle-status');
            if (status) status.textContent = 'VISIBLE';
            const icon = toggleButton.querySelector('.toggle-icon i');
            if (icon) icon.className = 'fas fa-list';
        } else {
            toggleButton.classList.add('panel-hidden');
            const status = toggleButton.querySelector('.toggle-status');
            if (status) status.textContent = 'HIDDEN';
            const icon = toggleButton.querySelector('.toggle-icon i');
            if (icon) icon.className = 'fas fa-eye-slash';
        }
    }

    /**
     * Initialize the bet display toggle
     */
    function init() {
        console.log('🎰 Initializing bet display toggle...');
        
        betDisplayContainer = document.getElementById('bet-display-container');
        betDisplayToggle = document.querySelector('.bet-display-toggle');

        if (!betDisplayContainer) {
            console.warn('🎰 Bet display container not found, retrying...');
            setTimeout(init, 500);
            return;
        }

        // Create left-side toggle button
        createLeftSideToggle();

        // Add CSS styles for hide/show animations
        addToggleStyles();

        // Restore saved state
        restoreVisibilityState();

        // Setup toggle event listener for existing toggle button (if it exists)
        if (betDisplayToggle) {
            setupToggleEvents();
        }

        // Ensure initial visibility state
        if (isVisible) {
            betDisplayContainer.classList.add('bet-display-visible');
            betDisplayContainer.classList.remove('bet-display-hidden');
        } else {
            betDisplayContainer.classList.add('bet-display-hidden');
            betDisplayContainer.classList.remove('bet-display-visible');
        }

        // Update toggle button state
        updateToggleButton();

        console.log('🎰 Bet display toggle initialized');
    }

    /**
     * Add CSS styles for hide/show animations
     */
    function addToggleStyles() {
        const styleId = 'bet-display-toggle-styles';
        if (document.getElementById(styleId)) return;

        const styles = document.createElement('style');
        styles.id = styleId;
        styles.textContent = `
            /* Hide state */
            .bet-display-container.bet-display-hidden {
                opacity: 0 !important;
                transform: scale(0.9) translateY(-20px) !important;
                pointer-events: none !important;
                filter: blur(2px) !important;
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1) !important;
            }

            /* Show state */
            .bet-display-container.bet-display-visible {
                opacity: 1 !important;
                transform: scale(1) translateY(0) !important;
                pointer-events: auto !important;
                filter: blur(0) !important;
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1) !important;
            }

            /* Toggle button icon rotation */
            .bet-display-container.bet-display-hidden .bet-display-toggle i {
                transform: rotate(0deg) !important;
            }

            .bet-display-container.bet-display-visible .bet-display-toggle i {
                transform: rotate(180deg) !important;
            }

            /* Toggle button hover effect when hidden */
            .bet-display-container.bet-display-hidden .bet-display-toggle {
                background: rgba(248, 211, 72, 0.3) !important;
            }

            .bet-display-container.bet-display-hidden .bet-display-toggle:hover {
                background: rgba(248, 211, 72, 0.5) !important;
            }

            /* Ensure container is visible by default */
            .bet-display-container:not(.bet-display-hidden) {
                display: block !important;
            }

        `;

        document.head.appendChild(styles);
    }

    /**
     * Setup toggle event listeners
     */
    function setupToggleEvents() {
        if (!betDisplayToggle) return;

        // Remove any existing event listeners by cloning the element
        const newToggle = betDisplayToggle.cloneNode(true);
        betDisplayToggle.parentNode.replaceChild(newToggle, betDisplayToggle);
        betDisplayToggle = newToggle;

        // Add click event listener (use capture to ensure it runs first)
        betDisplayToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            toggleBetDisplay();
        }, true);

        // No keyboard shortcuts - only toggle button to avoid conflicts
    }

    /**
     * Toggle bet display visibility
     */
    function toggleBetDisplay() {
        console.log('🎰 Toggling bet display - current state:', isVisible);
        
        isVisible = !isVisible;
        
        if (isVisible) {
            showBetDisplay();
        } else {
            hideBetDisplay();
        }

        // Save state
        sessionStorage.setItem(sessionStorageKey, isVisible.toString());
    }

    /**
     * Show the bet display
     */
    function showBetDisplay() {
        if (!betDisplayContainer) return;

        // Show the container first
        betDisplayContainer.style.display = 'block';
        
        // Force reflow to ensure display change is applied
        betDisplayContainer.offsetHeight;
        
        // Remove hidden class and add visible class
        betDisplayContainer.classList.remove('bet-display-hidden');
        betDisplayContainer.classList.add('bet-display-visible');

        // Update toggle button
        updateToggleButton();

        console.log('👁️ Bet display shown');
    }

    /**
     * Hide the bet display
     */
    function hideBetDisplay() {
        if (!betDisplayContainer) return;

        // Remove visible class and add hidden class
        betDisplayContainer.classList.remove('bet-display-visible');
        betDisplayContainer.classList.add('bet-display-hidden');

        // Update toggle button
        updateToggleButton();

        // Hide after animation completes
        setTimeout(() => {
            if (!isVisible && betDisplayContainer.classList.contains('bet-display-hidden')) {
                betDisplayContainer.style.display = 'none';
            }
        }, 400);

        console.log('🙈 Bet display hidden');
    }

    /**
     * Restore visibility state from session storage
     */
    function restoreVisibilityState() {
        const savedState = sessionStorage.getItem(sessionStorageKey);
        
        if (savedState !== null) {
            isVisible = savedState === 'true';
        }

        // Apply the restored state after a short delay
        setTimeout(() => {
            if (betDisplayContainer) {
                if (isVisible) {
                    showBetDisplay();
                } else {
                    hideBetDisplay();
                }
            }
        }, 300);
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Also initialize after a delay to ensure all styles have loaded
    setTimeout(init, 1000);

})();

