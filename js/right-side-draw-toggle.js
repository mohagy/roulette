/**
 * Right-Side Toggle Control for Draw Number Display Panel
 * Compact vertical tab-style toggle positioned on the right edge of the screen
 */

(function() {
    'use strict';

    console.log('▶️ Right-side draw toggle control loaded');

    let rightToggle = null;
    let isVisible = true;
    const sessionStorageKey = 'rightSideDrawPanelVisible';

    /**
     * Create the right-side toggle control
     */
    function createRightSideToggle() {
        console.log('▶️ Creating right-side draw toggle control...');
        
        // Remove existing toggle if any
        const existing = document.getElementById('right-side-draw-toggle');
        if (existing) {
            existing.remove();
        }
        
        // Create toggle element
        rightToggle = document.createElement('div');
        rightToggle.id = 'right-side-draw-toggle';
        rightToggle.className = 'right-side-toggle-control';
        rightToggle.innerHTML = `
            <div class="toggle-tab">
                <div class="toggle-icon">
                    <i class="fas fa-dice"></i>
                </div>
                <div class="toggle-text">
                    <span class="toggle-label">DRAWS</span>
                    <span class="toggle-status">VISIBLE</span>
                </div>
                <div class="toggle-arrow">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </div>
        `;
        
        // Add comprehensive styles
        addRightToggleStyles();
        
        // Add event listeners
        setupRightToggleEvents();
        
        // Append to body
        document.body.appendChild(rightToggle);
        console.log('▶️ Right-side draw toggle control created and added to page');
        
        // Restore saved state
        restoreRightToggleState();
    }

    /**
     * Add CSS styles for the right-side toggle
     */
    function addRightToggleStyles() {
        const styleId = 'right-side-toggle-styles';
        if (document.getElementById(styleId)) return;

        const styles = document.createElement('style');
        styles.id = styleId;
        styles.textContent = `
            .right-side-toggle-control {
                position: fixed;
                right: 0;
                top: 50%;
                transform: translateY(-50%);
                z-index: 10002;
                font-family: 'Orbitron', 'Arial', sans-serif;
                cursor: pointer;
                user-select: none;
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .right-side-toggle-control .toggle-tab {
                background: linear-gradient(135deg, 
                    rgba(26, 26, 26, 0.95) 0%, 
                    rgba(15, 20, 25, 0.95) 50%, 
                    rgba(26, 26, 26, 0.95) 100%);
                border: 2px solid #FFD700;
                border-right: none;
                border-radius: 8px 0 0 8px;
                padding: 8px 4px 8px 6px;
                min-height: 70px;
                width: 36px;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 4px;
                box-shadow: 
                    -3px 0 15px rgba(255, 215, 0, 0.3),
                    -1px 0 8px rgba(0, 0, 0, 0.3),
                    inset -1px 0 0 rgba(255, 255, 255, 0.1);
                backdrop-filter: blur(12px);
                position: relative;
                overflow: hidden;
                transform: translateX(6px);
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .right-side-toggle-control .toggle-tab::before {
                content: '';
                position: absolute;
                top: 0;
                right: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, 
                    transparent, 
                    rgba(255, 215, 0, 0.1), 
                    transparent);
                transition: right 0.6s ease;
            }

            .right-side-toggle-control:hover .toggle-tab::before {
                right: 100%;
            }

            .right-side-toggle-control:hover .toggle-tab {
                transform: translateX(0);
                box-shadow: 
                    -4px 0 20px rgba(255, 215, 0, 0.4),
                    -2px 0 12px rgba(0, 0, 0, 0.4),
                    inset -1px 0 0 rgba(255, 255, 255, 0.15);
                border-color: #FFA500;
            }

            .right-side-toggle-control .toggle-icon {
                color: #FFD700;
                font-size: 14px;
                text-shadow: 0 0 8px rgba(255, 215, 0, 0.5);
                transition: all 0.3s ease;
                margin-bottom: 2px;
            }

            .right-side-toggle-control:hover .toggle-icon {
                transform: scale(1.08);
                text-shadow: 0 0 12px rgba(255, 215, 0, 0.7);
            }

            .right-side-toggle-control .toggle-text {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 2px;
                writing-mode: vertical-rl;
                text-orientation: mixed;
                transform: rotate(180deg);
            }

            .right-side-toggle-control .toggle-label {
                color: #FFD700;
                font-size: 8px;
                font-weight: 700;
                letter-spacing: 0.5px;
                text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
            }

            .right-side-toggle-control .toggle-status {
                color: #ffffff;
                font-size: 7px;
                font-weight: 500;
                opacity: 0.8;
                text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
            }

            .right-side-toggle-control .toggle-arrow {
                color: #FFD700;
                font-size: 10px;
                margin-top: 2px;
                transition: all 0.3s ease;
                text-shadow: 0 0 6px rgba(255, 215, 0, 0.5);
            }

            .right-side-toggle-control:hover .toggle-arrow {
                transform: translateX(-1px);
                text-shadow: 0 0 10px rgba(255, 215, 0, 0.7);
            }

            /* Hidden state styling */
            .right-side-toggle-control.panel-hidden .toggle-tab {
                background: linear-gradient(135deg, 
                    rgba(40, 167, 69, 0.95) 0%, 
                    rgba(32, 201, 151, 0.95) 50%, 
                    rgba(40, 167, 69, 0.95) 100%);
                border-color: #28a745;
                box-shadow: 
                    -3px 0 15px rgba(40, 167, 69, 0.3),
                    -1px 0 8px rgba(0, 0, 0, 0.3);
            }

            .right-side-toggle-control.panel-hidden:hover .toggle-tab {
                border-color: #20c997;
                box-shadow: 
                    -4px 0 20px rgba(40, 167, 69, 0.4),
                    -2px 0 12px rgba(0, 0, 0, 0.4);
            }

            .right-side-toggle-control.panel-hidden .toggle-icon {
                color: #ffffff;
                text-shadow: 0 0 8px rgba(255, 255, 255, 0.5);
            }

            .right-side-toggle-control.panel-hidden .toggle-label {
                color: #ffffff;
            }

            .right-side-toggle-control.panel-hidden .toggle-arrow {
                color: #ffffff;
                text-shadow: 0 0 6px rgba(255, 255, 255, 0.5);
            }

            .right-side-toggle-control.panel-hidden .toggle-arrow i {
                transform: rotate(180deg);
            }

            /* Enhanced animations for the draw panel - Complete hiding */
            .cashier-draw-content.right-toggle-hiding {
                opacity: 0 !important;
                transform: scale(0.8) translateX(50px) !important;
                pointer-events: none !important;
                filter: blur(3px) !important;
                visibility: hidden !important;
                transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1) !important;
            }

            .cashier-draw-content.right-toggle-showing {
                opacity: 1 !important;
                transform: scale(1) translateX(0) !important;
                pointer-events: auto !important;
                filter: blur(0) !important;
                visibility: visible !important;
                display: block !important;
                transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1) !important;
            }

            /* Completely hidden state - no trace */
            .cashier-draw-content.completely-hidden {
                display: none !important;
                opacity: 0 !important;
                visibility: hidden !important;
                pointer-events: none !important;
                position: absolute !important;
                left: -9999px !important;
                top: -9999px !important;
                width: 0 !important;
                height: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                box-shadow: none !important;
                background: none !important;
                overflow: hidden !important;
                z-index: -1 !important;
            }

            /* Responsive design */
            @media (max-width: 768px) {
                .right-side-toggle-control .toggle-tab {
                    width: 32px;
                    min-height: 60px;
                    padding: 6px 3px 6px 4px;
                    transform: translateX(4px);
                }
                
                .right-side-toggle-control .toggle-icon {
                    font-size: 12px;
                }
                
                .right-side-toggle-control .toggle-label {
                    font-size: 7px;
                }
                
                .right-side-toggle-control .toggle-status {
                    font-size: 6px;
                }
                
                .right-side-toggle-control .toggle-arrow {
                    font-size: 8px;
                    margin-top: 1px;
                }
                
                .right-side-toggle-control:hover .toggle-tab {
                    box-shadow: 
                        -3px 0 15px rgba(255, 215, 0, 0.4),
                        -1px 0 8px rgba(0, 0, 0, 0.4);
                }
            }

            @media (max-width: 480px) {
                .right-side-toggle-control .toggle-tab {
                    width: 28px;
                    min-height: 50px;
                    padding: 5px 2px 5px 3px;
                    gap: 2px;
                    transform: translateX(3px);
                }
                
                .right-side-toggle-control .toggle-icon {
                    font-size: 11px;
                    margin-bottom: 1px;
                }
                
                .right-side-toggle-control .toggle-text {
                    display: none;
                }
                
                .right-side-toggle-control .toggle-arrow {
                    font-size: 9px;
                    margin-top: 3px;
                }
                
                .right-side-toggle-control:hover .toggle-arrow {
                    transform: translateX(-0.5px);
                }
            }

            /* Pulse animation for first-time users */
            @keyframes rightTogglePulse {
                0%, 100% { 
                    box-shadow: 
                        -3px 0 15px rgba(255, 215, 0, 0.3),
                        -1px 0 8px rgba(0, 0, 0, 0.3);
                }
                50% { 
                    box-shadow: 
                        -4px 0 20px rgba(255, 215, 0, 0.5),
                        -2px 0 12px rgba(0, 0, 0, 0.4);
                }
            }

            .right-side-toggle-control.pulse .toggle-tab {
                animation: rightTogglePulse 2s ease-in-out infinite;
            }

            /* Accessibility improvements */
            .right-side-toggle-control:focus {
                outline: 2px solid #FFD700;
                outline-offset: 2px;
            }

            .right-side-toggle-control[aria-pressed="true"] .toggle-status::after {
                content: " ✓";
            }

            .right-side-toggle-control[aria-pressed="false"] .toggle-status::after {
                content: " ✗";
            }
        `;

        document.head.appendChild(styles);
    }

    /**
     * Setup event listeners for the right toggle
     */
    function setupRightToggleEvents() {
        if (!rightToggle) return;

        // Click event
        rightToggle.addEventListener('click', toggleDrawPanelFromRight);

        // Keyboard accessibility
        rightToggle.setAttribute('tabindex', '0');
        rightToggle.setAttribute('role', 'button');
        rightToggle.setAttribute('aria-label', 'Toggle draw panel visibility');

        rightToggle.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggleDrawPanelFromRight();
            }
        });

        // Enhanced keyboard shortcut (Ctrl/Cmd + R for Right)
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                toggleDrawPanelFromRight();
            }
        });
    }

    /**
     * Toggle draw panel visibility from right control
     */
    function toggleDrawPanelFromRight() {
        console.log('▶️ Right toggle clicked - current state:', isVisible);

        const panel = document.querySelector('.cashier-draw-content');
        if (!panel) {
            console.warn('▶️ Draw panel not found');
            return;
        }

        isVisible = !isVisible;

        if (isVisible) {
            showDrawPanelFromRight(panel);
        } else {
            hideDrawPanelFromRight(panel);
        }

        // Save state
        sessionStorage.setItem(sessionStorageKey, isVisible.toString());
    }

    /**
     * Show the draw panel from right control
     */
    function showDrawPanelFromRight(panel) {
        // Remove all hiding classes first
        panel.classList.remove('right-toggle-hiding', 'completely-hidden');

        // Reset display and visibility immediately
        panel.style.display = 'block';
        panel.style.visibility = 'visible';
        panel.style.position = '';
        panel.style.left = '';
        panel.style.top = '';
        panel.style.width = '';
        panel.style.height = '';
        panel.style.margin = '';
        panel.style.padding = '';
        panel.style.border = '';
        panel.style.boxShadow = '';
        panel.style.background = '';
        panel.style.overflow = '';
        panel.style.zIndex = '';

        // Add showing class for animation
        panel.classList.add('right-toggle-showing');

        // Update right toggle
        rightToggle.classList.remove('panel-hidden');
        rightToggle.setAttribute('aria-pressed', 'true');
        rightToggle.querySelector('.toggle-status').textContent = 'VISIBLE';
        rightToggle.querySelector('.toggle-icon i').className = 'fas fa-dice';

        console.log('👁️ Draw panel completely restored via right toggle');
    }

    /**
     * Hide the draw panel from right control
     */
    function hideDrawPanelFromRight(panel) {
        // Remove showing class and add hiding class for animation
        panel.classList.remove('right-toggle-showing');
        panel.classList.add('right-toggle-hiding');

        // Complete hiding after animation completes
        setTimeout(() => {
            if (!isVisible) {
                // Remove animation class and add complete hiding class
                panel.classList.remove('right-toggle-hiding');
                panel.classList.add('completely-hidden');

                // Ensure complete invisibility and no layout impact
                panel.style.display = 'none';
                panel.style.opacity = '0';
                panel.style.visibility = 'hidden';
                panel.style.pointerEvents = 'none';
                panel.style.position = 'absolute';
                panel.style.left = '-9999px';
                panel.style.top = '-9999px';
                panel.style.width = '0';
                panel.style.height = '0';
                panel.style.margin = '0';
                panel.style.padding = '0';
                panel.style.border = 'none';
                panel.style.boxShadow = 'none';
                panel.style.background = 'none';
                panel.style.overflow = 'hidden';
                panel.style.zIndex = '-1';

                console.log('🙈 Draw panel completely hidden - zero footprint');
            }
        }, 500);

        // Update right toggle
        rightToggle.classList.add('panel-hidden');
        rightToggle.setAttribute('aria-pressed', 'false');
        rightToggle.querySelector('.toggle-status').textContent = 'HIDDEN';
        rightToggle.querySelector('.toggle-icon i').className = 'fas fa-eye-slash';

        console.log('🙈 Draw panel hiding animation started via right toggle');
    }

    /**
     * Restore visibility state from session storage
     */
    function restoreRightToggleState() {
        const savedState = sessionStorage.getItem(sessionStorageKey);

        if (savedState !== null) {
            isVisible = savedState === 'true';
        }

        // Apply the restored state after a short delay
        setTimeout(() => {
            const panel = document.querySelector('.cashier-draw-content');
            if (panel) {
                if (isVisible) {
                    showDrawPanelFromRight(panel);
                } else {
                    // For hidden state, apply complete hiding immediately without animation
                    panel.classList.remove('right-toggle-showing', 'right-toggle-hiding');
                    panel.classList.add('completely-hidden');

                    // Apply all hiding styles immediately
                    panel.style.display = 'none';
                    panel.style.opacity = '0';
                    panel.style.visibility = 'hidden';
                    panel.style.pointerEvents = 'none';
                    panel.style.position = 'absolute';
                    panel.style.left = '-9999px';
                    panel.style.top = '-9999px';
                    panel.style.width = '0';
                    panel.style.height = '0';
                    panel.style.margin = '0';
                    panel.style.padding = '0';
                    panel.style.border = 'none';
                    panel.style.boxShadow = 'none';
                    panel.style.background = 'none';
                    panel.style.overflow = 'hidden';
                    panel.style.zIndex = '-1';

                    // Update toggle state
                    rightToggle.classList.add('panel-hidden');
                    rightToggle.setAttribute('aria-pressed', 'false');
                    rightToggle.querySelector('.toggle-status').textContent = 'HIDDEN';
                    rightToggle.querySelector('.toggle-icon i').className = 'fas fa-eye-slash';

                    console.log('🙈 Draw panel restored in completely hidden state');
                }
            }
        }, 800);

        // Add pulse animation for first-time users
        if (savedState === null) {
            rightToggle.classList.add('pulse');
            setTimeout(() => {
                rightToggle.classList.remove('pulse');
            }, 6000);
        }
    }

    /**
     * Initialize the right-side toggle functionality
     */
    function init() {
        console.log('▶️ Initializing right-side draw toggle control...');
        createRightSideToggle();
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
