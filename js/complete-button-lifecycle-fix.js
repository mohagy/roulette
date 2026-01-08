/**
 * Complete Button Lifecycle Fix
 *
 * This script ensures the COMPLETE button remains functional throughout the entire
 * page lifecycle, specifically addressing the timing issue where the feature-removal-patch.js
 * removes event listeners at 1500ms and 3000ms after DOM load.
 */

(function() {
    console.log('🔄 Complete Button Lifecycle Fix - Initializing...');

    let isCompleteBetMode = false;
    let monitoringInterval = null;
    let buttonProtectionInterval = null;

    const config = {
        debug: true,
        monitorInterval: 300, // Check every 300ms for aggressive protection
        buttonProtectionInterval: 100, // Ultra-aggressive button protection
        criticalTimings: [2000, 4000, 5000] // Run fixes at these critical times
    };

    function log(message, ...args) {
        if (config.debug) {
            console.log(`[CompleteButtonLifecycleFix] ${message}`, ...args);
        }
    }

    /**
     * Aggressively ensure complete button functionality
     */
    function aggressivelyEnsureCompleteButton() {
        // Find all complete buttons (original and floating)
        const completeButtons = document.querySelectorAll('.button-complete, .floating-complete-button');

        if (completeButtons.length === 0) {
            // No buttons found, try to find them in menu container
            const menuContainer = document.querySelector('.menu-container');
            if (menuContainer) {
                const buttonsInMenu = menuContainer.querySelectorAll('.button');
                buttonsInMenu.forEach(button => {
                    if (button.textContent && button.textContent.includes('COMPLETE')) {
                        button.classList.add('button-complete');
                        log('🔧 Found and marked complete button in menu container');
                    }
                });
            }
        }

        // Re-query after potential additions
        const allCompleteButtons = document.querySelectorAll('.button-complete, .floating-complete-button');

        allCompleteButtons.forEach((button, index) => {
            // Ensure button is enabled and clickable
            button.disabled = false;
            button.style.pointerEvents = 'auto';
            button.style.opacity = '1';
            button.style.display = 'flex'; // Ensure it's visible

            // Check if it has our event listener
            if (!button.getAttribute('data-lifecycle-fix-attached')) {
                attachLifecycleClickHandler(button, index);
                button.setAttribute('data-lifecycle-fix-attached', 'true');
                log(`🔧 Attached lifecycle click handler to button ${index + 1}`);
            }

            // Ensure the button has the correct visual state
            if (isCompleteBetMode) {
                button.classList.add('active-button');
            } else {
                button.classList.remove('active-button');
            }
        });

        // Sync the global variable
        if (typeof window.isCompleteBetMode === 'undefined' || window.isCompleteBetMode !== isCompleteBetMode) {
            window.isCompleteBetMode = isCompleteBetMode;
        }

        return allCompleteButtons.length > 0;
    }

    /**
     * Attach lifecycle-aware click handler to a button
     */
    function attachLifecycleClickHandler(button, buttonIndex) {
        // Remove ALL existing event listeners by cloning
        const newButton = button.cloneNode(true);
        button.parentNode.replaceChild(newButton, button);

        // Mark the new button
        newButton.setAttribute('data-lifecycle-fix-attached', 'true');
        newButton.classList.add('button-complete'); // Ensure class is present

        // Add multiple event listeners for maximum compatibility
        newButton.addEventListener('click', (e) => handleCompleteButtonClick(e, buttonIndex), true);
        newButton.addEventListener('mousedown', (e) => handleCompleteButtonClick(e, buttonIndex), true);
        newButton.addEventListener('touchstart', (e) => handleCompleteButtonClick(e, buttonIndex), true);

        // Also handle clicks on child elements
        const children = newButton.querySelectorAll('*');
        children.forEach(child => {
            child.addEventListener('click', (e) => handleCompleteButtonClick(e, buttonIndex), true);
            child.style.pointerEvents = 'auto';
        });

        log(`🔧 Lifecycle click handler attached to button ${buttonIndex + 1}`);
    }

    /**
     * Handle complete button click with lifecycle awareness
     */
    function handleCompleteButtonClick(e, buttonIndex = 0) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();

        log(`🎯 Complete button ${buttonIndex + 1} clicked!`);

        // Toggle complete bet mode
        isCompleteBetMode = !isCompleteBetMode;

        // Update global variable immediately
        window.isCompleteBetMode = isCompleteBetMode;

        // Update all button states
        updateAllButtonStates();

        // Show/hide tooltip
        if (isCompleteBetMode) {
            showCompleteTooltip();
            log('✅ Complete bet mode ACTIVATED - Click any number to place complete bets!');
        } else {
            log('❌ Complete bet mode DEACTIVATED');
        }

        // Play sound
        playClickSound();

        // Force re-attachment of handlers (in case something removes them)
        setTimeout(() => {
            aggressivelyEnsureCompleteButton();
        }, 50);
    }

    /**
     * Update visual states of all complete buttons
     */
    function updateAllButtonStates() {
        const completeButtons = document.querySelectorAll('.button-complete, .floating-complete-button');

        completeButtons.forEach(button => {
            if (isCompleteBetMode) {
                button.classList.add('active-button');
            } else {
                button.classList.remove('active-button');
            }
        });
    }

    /**
     * Show tooltip for complete bet mode
     */
    function showCompleteTooltip() {
        const tooltip = document.querySelector('.bet-type-tooltip');
        if (tooltip) {
            tooltip.textContent = 'Click on a number to place a complete bet';
            tooltip.style.display = 'block';
            tooltip.classList.add('visible');
            setTimeout(() => {
                if (isCompleteBetMode) {
                    tooltip.classList.remove('visible');
                    tooltip.style.display = '';
                }
            }, 3000);
        }
    }

    /**
     * Play click sound
     */
    function playClickSound() {
        if (typeof playAudio !== 'undefined' && playAudio && typeof selectSound !== 'undefined') {
            try {
                selectSound.play();
            } catch (e) {
                log('Could not play sound:', e);
            }
        }
    }

    /**
     * Ensure number click handlers work with complete bets
     */
    function ensureNumberClickHandlers() {
        const numberElements = document.querySelectorAll('.part.regular, .number');

        numberElements.forEach(element => {
            if (!element.getAttribute('data-complete-bet-handler')) {
                element.addEventListener('click', handleNumberClick, true);
                element.setAttribute('data-complete-bet-handler', 'true');
            }
        });
    }

    /**
     * Handle number click for complete bets
     */
    function handleNumberClick(event) {
        if (!isCompleteBetMode) return;

        const element = event.currentTarget;

        // Find the number from class names
        for (let i = 0; i <= 36; i++) {
            if (element.classList.contains(`regular${i}`) || element.classList.contains(`number${i}`)) {
                log(`🎯 Complete bet triggered on number: ${i}`);

                // Call the complete bet function if it exists
                if (typeof placeCompleteBet === 'function') {
                    placeCompleteBet(i);

                    // The placeCompleteBet function will automatically turn off complete bet mode
                    // We need to sync our local variable
                    setTimeout(() => {
                        if (typeof window.isCompleteBetMode !== 'undefined') {
                            isCompleteBetMode = window.isCompleteBetMode;
                            updateAllButtonStates();
                        }
                    }, 100);
                } else {
                    log('placeCompleteBet function not found');
                }
                break;
            }
        }
    }

    /**
     * Start ultra-aggressive monitoring system
     */
    function startUltraAggressiveMonitoring() {
        log('🚀 Starting ultra-aggressive complete button monitoring');

        // Main monitoring interval
        monitoringInterval = setInterval(() => {
            aggressivelyEnsureCompleteButton();
            ensureNumberClickHandlers();
        }, config.monitorInterval);

        // Ultra-aggressive button protection
        buttonProtectionInterval = setInterval(() => {
            const buttons = document.querySelectorAll('.button-complete, .floating-complete-button');
            buttons.forEach(button => {
                button.disabled = false;
                button.style.pointerEvents = 'auto';
                button.style.opacity = '1';
            });
        }, config.buttonProtectionInterval);

        log('🚀 Ultra-aggressive monitoring started');
    }

    /**
     * Stop monitoring system
     */
    function stopMonitoring() {
        if (monitoringInterval) {
            clearInterval(monitoringInterval);
            monitoringInterval = null;
        }
        if (buttonProtectionInterval) {
            clearInterval(buttonProtectionInterval);
            buttonProtectionInterval = null;
        }
        log('Stopped complete button monitoring');
    }

    /**
     * Initialize the lifecycle fix with precise timing
     */
    function initialize() {
        log('🔄 Initializing complete button lifecycle fix with timing protection');

        // Immediate setup (before any other scripts)
        setTimeout(() => {
            log('🔧 Phase 1: Initial setup (500ms)');
            aggressivelyEnsureCompleteButton();
            ensureNumberClickHandlers();
        }, 500);

        // Before feature removal patch first run (1500ms)
        setTimeout(() => {
            log('🔧 Phase 2: Pre-patch setup (1200ms)');
            aggressivelyEnsureCompleteButton();
            ensureNumberClickHandlers();
        }, 1200);

        // CRITICAL: Run AFTER feature removal patch first run (1500ms)
        setTimeout(() => {
            log('🔧 Phase 3: POST feature removal patch 1st run (2000ms) - CRITICAL FIX');
            aggressivelyEnsureCompleteButton();
            ensureNumberClickHandlers();
        }, 2000);

        // CRITICAL: Run AFTER feature removal patch second run (3000ms)
        setTimeout(() => {
            log('🔧 Phase 4: POST feature removal patch 2nd run (4000ms) - CRITICAL FIX');
            aggressivelyEnsureCompleteButton();
            ensureNumberClickHandlers();
        }, 4000);

        // Start ultra-aggressive monitoring AFTER all patches
        setTimeout(() => {
            log('🔧 Phase 5: Starting ultra-aggressive monitoring (5000ms)');
            startUltraAggressiveMonitoring();
        }, 5000);

        // Additional critical timing fixes
        config.criticalTimings.forEach((timing, index) => {
            setTimeout(() => {
                log(`🔧 Critical timing fix ${index + 1} at ${timing}ms`);
                aggressivelyEnsureCompleteButton();
                ensureNumberClickHandlers();
            }, timing);
        });

        // DOM load event handling
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                log('🔧 DOM loaded - Running immediate fix');
                setTimeout(() => {
                    aggressivelyEnsureCompleteButton();
                    ensureNumberClickHandlers();
                }, 100);

                // Critical: Run after feature removal patch
                setTimeout(() => {
                    log('🔧 DOM loaded - POST feature removal patch fix');
                    aggressivelyEnsureCompleteButton();
                    ensureNumberClickHandlers();
                }, 4500);
            });
        }

        log('🔄 Complete button lifecycle fix initialized with precise timing protection');
    }

    // Expose functions globally for debugging
    window.CompleteButtonLifecycleFix = {
        aggressivelyEnsureCompleteButton,
        startUltraAggressiveMonitoring,
        stopMonitoring,
        isCompleteBetMode: () => isCompleteBetMode,
        setCompleteBetMode: (value) => {
            isCompleteBetMode = value;
            window.isCompleteBetMode = value;
            updateAllButtonStates();
            log('Complete bet mode set to:', value);
        },
        forceActivate: () => {
            isCompleteBetMode = true;
            window.isCompleteBetMode = true;
            updateAllButtonStates();
            showCompleteTooltip();
            log('Force activated complete bet mode');
        },
        forceDeactivate: () => {
            isCompleteBetMode = false;
            window.isCompleteBetMode = false;
            updateAllButtonStates();
            log('Force deactivated complete bet mode');
        },
        getCurrentPhase: () => {
            const now = Date.now();
            const start = window.performance ? performance.timeOrigin + performance.now() - now : now;
            const elapsed = now - start;
            if (elapsed < 1500) return 'Pre-patch';
            if (elapsed < 3000) return 'Between patches';
            if (elapsed < 5000) return 'Post-patch stabilizing';
            return 'Monitoring active';
        }
    };

    // Initialize immediately
    initialize();

    log('🔄 Complete Button Lifecycle Fix loaded successfully!');
})();
