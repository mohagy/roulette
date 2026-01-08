/**
 * Left-Side Toggle Control for Enhanced Cashier Panel
 * Vertical tab-style toggle positioned on the left edge of the screen
 */

(function() {
    'use strict';

    console.log('◀️ Left-side toggle control loaded');

    let leftToggle = null;
    let isVisible = true;
    const sessionStorageKey = 'leftSideCashierPanelVisible';

    /**
     * Create the left-side toggle control
     */
    function createLeftSideToggle() {
        console.log('◀️ Creating left-side toggle control...');
        
        // Remove existing toggle if any
        const existing = document.getElementById('left-side-cashier-toggle');
        if (existing) {
            existing.remove();
        }
        
        // Create toggle element
        leftToggle = document.createElement('div');
        leftToggle.id = 'left-side-cashier-toggle';
        leftToggle.className = 'left-side-toggle-control';
        leftToggle.innerHTML = `
            <div class="toggle-tab">
                <div class="toggle-icon">
                    <i class="fas fa-wallet"></i>
                </div>
                <div class="toggle-text">
                    <span class="toggle-label">CASHIER</span>
                    <span class="toggle-status">VISIBLE</span>
                </div>
                <div class="toggle-arrow">
                    <i class="fas fa-chevron-left"></i>
                </div>
            </div>
        `;
        
        // Add comprehensive styles
        addLeftToggleStyles();
        
        // Add event listeners
        setupLeftToggleEvents();
        
        // Append to body
        document.body.appendChild(leftToggle);
        console.log('◀️ Left-side toggle control created and added to page');
        
        // Restore saved state
        restoreLeftToggleState();
    }

    /**
     * Add CSS styles for the left-side toggle
     */
    function addLeftToggleStyles() {
        const styleId = 'left-side-toggle-styles';
        if (document.getElementById(styleId)) return;

        const styles = document.createElement('style');
        styles.id = styleId;
        styles.textContent = `
            .left-side-toggle-control {
                position: fixed;
                left: 0;
                top: 50%;
                transform: translateY(-50%);
                z-index: 10002;
                font-family: 'Orbitron', 'Arial', sans-serif;
                cursor: pointer;
                user-select: none;
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .toggle-tab {
                background: linear-gradient(135deg,
                    rgba(26, 26, 26, 0.95) 0%,
                    rgba(15, 20, 25, 0.95) 50%,
                    rgba(26, 26, 26, 0.95) 100%);
                border: 2px solid #FFD700;
                border-left: none;
                border-radius: 0 8px 8px 0;
                padding: 8px 6px 8px 4px;
                min-height: 70px;
                width: 36px;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 4px;
                box-shadow:
                    3px 0 15px rgba(255, 215, 0, 0.3),
                    1px 0 8px rgba(0, 0, 0, 0.3),
                    inset 1px 0 0 rgba(255, 255, 255, 0.1);
                backdrop-filter: blur(12px);
                position: relative;
                overflow: hidden;
                transform: translateX(-6px);
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .toggle-tab::before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, 
                    transparent, 
                    rgba(255, 215, 0, 0.1), 
                    transparent);
                transition: left 0.6s ease;
            }

            .left-side-toggle-control:hover .toggle-tab::before {
                left: 100%;
            }

            .left-side-toggle-control:hover .toggle-tab {
                transform: translateX(0);
                box-shadow:
                    4px 0 20px rgba(255, 215, 0, 0.4),
                    2px 0 12px rgba(0, 0, 0, 0.4),
                    inset 1px 0 0 rgba(255, 255, 255, 0.15);
                border-color: #FFA500;
            }

            .toggle-icon {
                color: #FFD700;
                font-size: 14px;
                text-shadow: 0 0 8px rgba(255, 215, 0, 0.5);
                transition: all 0.3s ease;
                margin-bottom: 2px;
            }

            .left-side-toggle-control:hover .toggle-icon {
                transform: scale(1.08);
                text-shadow: 0 0 12px rgba(255, 215, 0, 0.7);
            }

            .toggle-text {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 2px;
                writing-mode: vertical-rl;
                text-orientation: mixed;
                transform: rotate(180deg);
            }

            .toggle-label {
                color: #FFD700;
                font-size: 8px;
                font-weight: 700;
                letter-spacing: 0.5px;
                text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
            }

            .toggle-status {
                color: #ffffff;
                font-size: 7px;
                font-weight: 500;
                opacity: 0.8;
                text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
            }

            .toggle-arrow {
                color: #FFD700;
                font-size: 10px;
                margin-top: 2px;
                transition: all 0.3s ease;
                text-shadow: 0 0 6px rgba(255, 215, 0, 0.5);
            }

            .left-side-toggle-control:hover .toggle-arrow {
                transform: translateX(1px);
                text-shadow: 0 0 10px rgba(255, 215, 0, 0.7);
            }

            /* Hidden state styling */
            .left-side-toggle-control.panel-hidden .toggle-tab {
                background: linear-gradient(135deg, 
                    rgba(40, 167, 69, 0.95) 0%, 
                    rgba(32, 201, 151, 0.95) 50%, 
                    rgba(40, 167, 69, 0.95) 100%);
                border-color: #28a745;
                box-shadow: 
                    4px 0 20px rgba(40, 167, 69, 0.3),
                    2px 0 10px rgba(0, 0, 0, 0.3);
            }

            .left-side-toggle-control.panel-hidden:hover .toggle-tab {
                border-color: #20c997;
                box-shadow:
                    4px 0 20px rgba(40, 167, 69, 0.4),
                    2px 0 12px rgba(0, 0, 0, 0.4);
            }

            .left-side-toggle-control.panel-hidden .toggle-icon {
                color: #ffffff;
                text-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
            }

            .left-side-toggle-control.panel-hidden .toggle-label {
                color: #ffffff;
            }

            .left-side-toggle-control.panel-hidden .toggle-arrow {
                color: #ffffff;
                text-shadow: 0 0 8px rgba(255, 255, 255, 0.5);
            }

            .left-side-toggle-control.panel-hidden .toggle-arrow i {
                transform: rotate(180deg);
            }

            /* Enhanced animations for the panel */
            .user-info.left-toggle-hiding {
                opacity: 0 !important;
                transform: scale(0.9) translateX(-40px) !important;
                pointer-events: none !important;
                filter: blur(2px) !important;
                transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1) !important;
            }

            .user-info.left-toggle-showing {
                opacity: 1 !important;
                transform: scale(1) translateX(0) !important;
                pointer-events: auto !important;
                filter: blur(0) !important;
                transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1) !important;
            }

            /* Responsive design */
            @media (max-width: 768px) {
                .toggle-tab {
                    width: 32px;
                    min-height: 60px;
                    padding: 6px 4px 6px 3px;
                    transform: translateX(-4px);
                }

                .toggle-icon {
                    font-size: 12px;
                }

                .toggle-label {
                    font-size: 7px;
                }

                .toggle-status {
                    font-size: 6px;
                }

                .toggle-arrow {
                    font-size: 8px;
                    margin-top: 1px;
                }

                .left-side-toggle-control:hover .toggle-tab {
                    box-shadow:
                        3px 0 15px rgba(255, 215, 0, 0.4),
                        1px 0 8px rgba(0, 0, 0, 0.4);
                }
            }

            @media (max-width: 480px) {
                .toggle-tab {
                    width: 28px;
                    min-height: 50px;
                    padding: 5px 3px 5px 2px;
                    gap: 2px;
                    transform: translateX(-3px);
                }

                .toggle-icon {
                    font-size: 11px;
                    margin-bottom: 1px;
                }

                .toggle-text {
                    display: none;
                }

                .toggle-arrow {
                    font-size: 9px;
                    margin-top: 3px;
                }

                .left-side-toggle-control:hover .toggle-arrow {
                    transform: translateX(0.5px);
                }
            }

            /* Pulse animation for first-time users */
            @keyframes leftTogglePulse {
                0%, 100% {
                    box-shadow:
                        3px 0 15px rgba(255, 215, 0, 0.3),
                        1px 0 8px rgba(0, 0, 0, 0.3);
                }
                50% {
                    box-shadow:
                        4px 0 20px rgba(255, 215, 0, 0.5),
                        2px 0 12px rgba(0, 0, 0, 0.4);
                }
            }

            .left-side-toggle-control.pulse .toggle-tab {
                animation: leftTogglePulse 2s ease-in-out infinite;
            }

            /* Accessibility improvements */
            .left-side-toggle-control:focus {
                outline: 2px solid #FFD700;
                outline-offset: 2px;
            }

            .left-side-toggle-control[aria-pressed="true"] .toggle-status::after {
                content: " ✓";
            }

            .left-side-toggle-control[aria-pressed="false"] .toggle-status::after {
                content: " ✗";
            }
        `;

        document.head.appendChild(styles);
    }

    /**
     * Setup event listeners for the left toggle
     */
    function setupLeftToggleEvents() {
        if (!leftToggle) return;

        // Click event
        leftToggle.addEventListener('click', togglePanelFromLeft);
        
        // Keyboard accessibility
        leftToggle.setAttribute('tabindex', '0');
        leftToggle.setAttribute('role', 'button');
        leftToggle.setAttribute('aria-label', 'Toggle cashier panel visibility');
        
        leftToggle.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                togglePanelFromLeft();
            }
        });

        // Enhanced keyboard shortcut (Ctrl/Cmd + L for Left)
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'l') {
                e.preventDefault();
                togglePanelFromLeft();
            }
        });
    }

    /**
     * Toggle panel visibility from left control
     */
    function togglePanelFromLeft() {
        console.log('◀️ Left toggle clicked - current state:', isVisible);
        
        const panel = document.getElementById('user-info');
        if (!panel) {
            console.warn('◀️ Cashier panel not found');
            return;
        }

        isVisible = !isVisible;
        
        if (isVisible) {
            showPanelFromLeft(panel);
        } else {
            hidePanelFromLeft(panel);
        }

        // Save state
        sessionStorage.setItem(sessionStorageKey, isVisible.toString());
    }

    /**
     * Show the cashier panel from left control
     */
    function showPanelFromLeft(panel) {
        // Remove hiding classes and add showing class
        panel.classList.remove('left-toggle-hiding');
        panel.classList.add('left-toggle-showing');
        panel.style.display = 'flex';

        // Update left toggle
        leftToggle.classList.remove('panel-hidden');
        leftToggle.setAttribute('aria-pressed', 'true');
        leftToggle.querySelector('.toggle-status').textContent = 'VISIBLE';
        leftToggle.querySelector('.toggle-icon i').className = 'fas fa-wallet';

        console.log('👁️ Cashier panel shown via left toggle');
    }

    /**
     * Hide the cashier panel from left control
     */
    function hidePanelFromLeft(panel) {
        // Remove showing class and add hiding class
        panel.classList.remove('left-toggle-showing');
        panel.classList.add('left-toggle-hiding');

        // Hide after animation completes
        setTimeout(() => {
            if (!isVisible) {
                panel.style.display = 'none';
            }
        }, 500);

        // Update left toggle
        leftToggle.classList.add('panel-hidden');
        leftToggle.setAttribute('aria-pressed', 'false');
        leftToggle.querySelector('.toggle-status').textContent = 'HIDDEN';
        leftToggle.querySelector('.toggle-icon i').className = 'fas fa-eye-slash';

        console.log('🙈 Cashier panel hidden via left toggle');
    }

    /**
     * Restore visibility state from session storage
     */
    function restoreLeftToggleState() {
        const savedState = sessionStorage.getItem(sessionStorageKey);
        
        if (savedState !== null) {
            isVisible = savedState === 'true';
        }

        // Apply the restored state after a short delay
        setTimeout(() => {
            const panel = document.getElementById('user-info');
            if (panel) {
                if (isVisible) {
                    showPanelFromLeft(panel);
                } else {
                    hidePanelFromLeft(panel);
                }
            }
        }, 800);

        // Add pulse animation for first-time users
        if (savedState === null) {
            leftToggle.classList.add('pulse');
            setTimeout(() => {
                leftToggle.classList.remove('pulse');
            }, 6000);
        }
    }

    /**
     * Initialize the left-side toggle functionality
     */
    function init() {
        console.log('◀️ Initializing left-side toggle control...');
        createLeftSideToggle();
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Also initialize after a delay to ensure all styles have loaded
    setTimeout(init, 2000);

})();
