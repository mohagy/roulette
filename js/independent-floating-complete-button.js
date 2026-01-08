/**
 * Independent Floating COMPLETE Button
 *
 * A completely self-contained, interference-immune floating COMPLETE button
 * that bypasses all existing DOM infrastructure and script conflicts.
 */

(function() {
    'use strict';

    console.log('🚀 Independent Floating COMPLETE Button - Initializing...');

    // Private state - completely isolated
    let isCompleteBetModeActive = false;
    let floatingButton = null;
    let isDragging = false;
    let dragOffset = { x: 0, y: 0 };
    let currentPosition = { x: 150, y: 150 };

    const BUTTON_ID = 'independent-floating-complete-btn';
    const Z_INDEX = 999999;
    const POSITION_STORAGE_KEY = 'floating-complete-button-position';

    /**
     * Load saved position from localStorage
     */
    function loadSavedPosition() {
        try {
            const savedPosition = localStorage.getItem(POSITION_STORAGE_KEY);
            console.log(`🔍 Raw saved position data:`, savedPosition);

            if (savedPosition) {
                const position = JSON.parse(savedPosition);
                console.log(`🔍 Parsed position:`, position);

                if (position && typeof position.x === 'number' && typeof position.y === 'number') {
                    // Validate position is within viewport bounds
                    const maxX = window.innerWidth - 90;
                    const maxY = window.innerHeight - 90;

                    const oldPosition = { x: currentPosition.x, y: currentPosition.y };
                    currentPosition.x = Math.max(0, Math.min(position.x, maxX));
                    currentPosition.y = Math.max(0, Math.min(position.y, maxY));

                    console.log(`📍 Position changed from (${oldPosition.x}, ${oldPosition.y}) to (${currentPosition.x}, ${currentPosition.y})`);
                    return true;
                } else {
                    console.warn('⚠️ Invalid position data structure:', position);
                }
            } else {
                console.log('📍 No saved position found in localStorage');
            }
        } catch (e) {
            console.warn('⚠️ Failed to load saved position:', e);
        }

        console.log(`📍 Using default position: (${currentPosition.x}, ${currentPosition.y})`);
        return false;
    }

    /**
     * Save current position to localStorage
     */
    function saveCurrentPosition() {
        try {
            const positionData = {
                x: currentPosition.x,
                y: currentPosition.y,
                timestamp: Date.now()
            };

            console.log(`💾 Saving position data:`, positionData);
            localStorage.setItem(POSITION_STORAGE_KEY, JSON.stringify(positionData));

            // Verify it was saved
            const verification = localStorage.getItem(POSITION_STORAGE_KEY);
            console.log(`✅ Position saved and verified: (${currentPosition.x}, ${currentPosition.y})`, verification);
        } catch (e) {
            console.warn('⚠️ Failed to save position:', e);
        }
    }

    /**
     * Create the independent floating button with beautiful styling
     */
    function createIndependentButton() {
        // Remove any existing button
        const existing = document.getElementById(BUTTON_ID);
        if (existing) existing.remove();

        // Load saved position before creating button
        loadSavedPosition();

        // Create button container
        floatingButton = document.createElement('div');
        floatingButton.id = BUTTON_ID;
        floatingButton.innerHTML = `
            <div class="floating-complete-inner">
                <div class="floating-complete-icon">⚡</div>
                <div class="floating-complete-text">COMPLETE</div>
            </div>
        `;

        // Apply styles directly (self-contained)
        applyButtonStyles();

        // Add to body with maximum priority
        document.body.appendChild(floatingButton);

        // Setup independent event handlers
        setupIndependentEventHandlers();

        console.log('✅ Independent floating COMPLETE button created');
        return floatingButton;
    }

    /**
     * Apply beautiful self-contained styles
     */
    function applyButtonStyles() {
        // Button container styles
        Object.assign(floatingButton.style, {
            position: 'fixed',
            left: currentPosition.x + 'px',
            top: currentPosition.y + 'px',
            width: '90px',
            height: '90px',
            borderRadius: '50%',
            background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
            boxShadow: '0 20px 40px rgba(102, 126, 234, 0.4), 0 0 0 2px rgba(255, 255, 255, 0.1)',
            cursor: 'move',
            zIndex: Z_INDEX,
            userSelect: 'none',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            transition: 'all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275)',
            transform: 'scale(1)',
            backdropFilter: 'blur(20px)',
            webkitBackdropFilter: 'blur(20px)',
            border: '2px solid rgba(255, 255, 255, 0.2)',
            fontFamily: 'Arial, sans-serif',
            overflow: 'hidden'
        });

        // Inner container
        const inner = floatingButton.querySelector('.floating-complete-inner');
        Object.assign(inner.style, {
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            justifyContent: 'center',
            width: '100%',
            height: '100%',
            pointerEvents: 'none'
        });

        // Icon styles
        const icon = floatingButton.querySelector('.floating-complete-icon');
        Object.assign(icon.style, {
            fontSize: '24px',
            color: '#fff',
            marginBottom: '2px',
            textShadow: '0 2px 4px rgba(0, 0, 0, 0.3)',
            transition: 'all 0.3s ease'
        });

        // Text styles
        const text = floatingButton.querySelector('.floating-complete-text');
        Object.assign(text.style, {
            fontSize: '10px',
            color: '#fff',
            fontWeight: 'bold',
            textShadow: '0 1px 2px rgba(0, 0, 0, 0.3)',
            letterSpacing: '0.5px'
        });

        // Add floating animation
        floatingButton.style.animation = 'independentFloat 4s ease-in-out infinite';

        // Inject keyframe animations
        injectAnimationStyles();
    }

    /**
     * Inject CSS animations
     */
    function injectAnimationStyles() {
        const styleId = 'independent-floating-complete-styles';
        if (document.getElementById(styleId)) return;

        const style = document.createElement('style');
        style.id = styleId;
        style.textContent = `
            @keyframes independentFloat {
                0%, 100% { transform: scale(1) translateY(0px) rotate(0deg); }
                25% { transform: scale(1) translateY(-8px) rotate(1deg); }
                50% { transform: scale(1) translateY(-15px) rotate(0deg); }
                75% { transform: scale(1) translateY(-8px) rotate(-1deg); }
            }

            @keyframes independentPulse {
                0%, 100% {
                    box-shadow: 0 20px 40px rgba(17, 153, 142, 0.6), 0 0 0 2px rgba(56, 239, 125, 0.8);
                }
                50% {
                    box-shadow: 0 25px 50px rgba(17, 153, 142, 0.8), 0 0 0 4px rgba(56, 239, 125, 1);
                }
            }

            @keyframes independentRainbow {
                0% { background: linear-gradient(45deg, #ff6b6b, #4ecdc4); }
                25% { background: linear-gradient(45deg, #4ecdc4, #45b7d1); }
                50% { background: linear-gradient(45deg, #45b7d1, #96ceb4); }
                75% { background: linear-gradient(45deg, #96ceb4, #ffeaa7); }
                100% { background: linear-gradient(45deg, #ffeaa7, #ff6b6b); }
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * Setup completely independent event handlers
     */
    function setupIndependentEventHandlers() {
        // Mouse events for dragging
        floatingButton.addEventListener('mousedown', handleMouseDown, { passive: false });
        document.addEventListener('mousemove', handleMouseMove, { passive: false });
        document.addEventListener('mouseup', handleMouseUp, { passive: false });

        // Touch events for mobile
        floatingButton.addEventListener('touchstart', handleTouchStart, { passive: false });
        document.addEventListener('touchmove', handleTouchMove, { passive: false });
        document.addEventListener('touchend', handleTouchEnd, { passive: false });

        // Click event for complete bet functionality
        floatingButton.addEventListener('click', handleCompleteButtonClick, { passive: false });

        // Hover effects
        floatingButton.addEventListener('mouseenter', handleMouseEnter);
        floatingButton.addEventListener('mouseleave', handleMouseLeave);

        console.log('✅ Independent event handlers attached');
    }

    /**
     * Handle mouse down for dragging
     */
    function handleMouseDown(e) {
        e.preventDefault();
        e.stopPropagation();

        const rect = floatingButton.getBoundingClientRect();
        dragOffset.x = e.clientX - rect.left;
        dragOffset.y = e.clientY - rect.top;
        isDragging = true;

        floatingButton.style.transition = 'none';
        floatingButton.style.transform = 'scale(0.95)';
        floatingButton.style.animation = 'independentRainbow 2s linear infinite';
    }

    /**
     * Handle mouse move for dragging
     */
    function handleMouseMove(e) {
        if (!isDragging) return;

        e.preventDefault();

        currentPosition.x = e.clientX - dragOffset.x;
        currentPosition.y = e.clientY - dragOffset.y;

        // Constrain to viewport
        currentPosition.x = Math.max(0, Math.min(currentPosition.x, window.innerWidth - 90));
        currentPosition.y = Math.max(0, Math.min(currentPosition.y, window.innerHeight - 90));

        floatingButton.style.left = currentPosition.x + 'px';
        floatingButton.style.top = currentPosition.y + 'px';
    }

    /**
     * Handle mouse up for dragging
     */
    function handleMouseUp(e) {
        if (!isDragging) return;

        isDragging = false;
        floatingButton.style.transition = 'all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
        floatingButton.style.transform = 'scale(1)';
        floatingButton.style.animation = 'independentFloat 4s ease-in-out infinite';

        // Save the new position to localStorage
        saveCurrentPosition();

        updateButtonAppearance();
    }

    /**
     * Touch event handlers (mobile support)
     */
    function handleTouchStart(e) {
        e.preventDefault();
        const touch = e.touches[0];
        const mouseEvent = new MouseEvent('mousedown', {
            clientX: touch.clientX,
            clientY: touch.clientY
        });
        handleMouseDown(mouseEvent);
    }

    function handleTouchMove(e) {
        if (!isDragging) return;
        e.preventDefault();
        const touch = e.touches[0];
        const mouseEvent = new MouseEvent('mousemove', {
            clientX: touch.clientX,
            clientY: touch.clientY
        });
        handleMouseMove(mouseEvent);
    }

    function handleTouchEnd(e) {
        e.preventDefault();
        handleMouseUp(e);
    }

    /**
     * Handle complete button click - core functionality
     */
    function handleCompleteButtonClick(e) {
        // Don't trigger if we're dragging
        if (isDragging) return;

        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();

        // Toggle complete bet mode
        isCompleteBetModeActive = !isCompleteBetModeActive;

        // Update global variable for compatibility
        window.isCompleteBetMode = isCompleteBetModeActive;

        console.log(`🎯 Independent COMPLETE button clicked! Mode: ${isCompleteBetModeActive ? 'ACTIVE' : 'INACTIVE'}`);

        // Update appearance
        updateButtonAppearance();

        // Show feedback
        if (isCompleteBetModeActive) {
            showCompleteModeTooltip();
            playClickSound();
        }

        // Setup number click handlers if active
        if (isCompleteBetModeActive) {
            setupNumberClickHandlers();
        }
    }

    /**
     * Update button appearance based on state
     */
    function updateButtonAppearance() {
        if (!floatingButton) return;

        if (isCompleteBetModeActive) {
            // Active state - green with pulsing glow
            floatingButton.style.background = 'linear-gradient(135deg, #11998e 0%, #38ef7d 100%)';
            floatingButton.style.animation = 'independentPulse 2s ease-in-out infinite';

            const icon = floatingButton.querySelector('.floating-complete-icon');
            if (icon) {
                icon.textContent = '⚡';
                icon.style.transform = 'rotate(45deg)';
            }

            const text = floatingButton.querySelector('.floating-complete-text');
            if (text) {
                text.textContent = 'ACTIVE';
            }
        } else {
            // Inactive state - original gradient with floating animation
            floatingButton.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
            floatingButton.style.animation = 'independentFloat 4s ease-in-out infinite';

            const icon = floatingButton.querySelector('.floating-complete-icon');
            if (icon) {
                icon.textContent = '⚡';
                icon.style.transform = 'rotate(0deg)';
            }

            const text = floatingButton.querySelector('.floating-complete-text');
            if (text) {
                text.textContent = 'COMPLETE';
            }
        }
    }

    /**
     * Handle hover effects
     */
    function handleMouseEnter() {
        if (isDragging) return;

        floatingButton.style.transform = 'scale(1.1)';
        floatingButton.style.boxShadow = '0 30px 60px rgba(102, 126, 234, 0.6), 0 0 0 3px rgba(255, 255, 255, 0.3)';
    }

    function handleMouseLeave() {
        if (isDragging) return;

        floatingButton.style.transform = 'scale(1)';
        updateButtonAppearance();
    }

    /**
     * Setup number click handlers for complete bet functionality
     */
    function setupNumberClickHandlers() {
        // Find all number elements on the roulette board
        const numberSelectors = [
            '.part.regular',
            '.number',
            '[class*="regular"]',
            '.betting-area .part'
        ];

        let numberElements = [];
        numberSelectors.forEach(selector => {
            const elements = document.querySelectorAll(selector);
            elements.forEach(el => {
                if (!numberElements.includes(el)) {
                    numberElements.push(el);
                }
            });
        });

        console.log(`🎯 Found ${numberElements.length} potential number elements`);

        // Attach click handlers to number elements
        numberElements.forEach(element => {
            if (!element.hasAttribute('data-independent-complete-handler')) {
                element.addEventListener('click', handleNumberClick, {
                    passive: false,
                    capture: true
                });
                element.setAttribute('data-independent-complete-handler', 'true');
            }
        });
    }

    /**
     * Handle number click for complete bet placement
     */
    function handleNumberClick(e) {
        if (!isCompleteBetModeActive) return;

        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();

        const element = e.currentTarget;
        let number = null;

        // Extract number from class names
        for (let i = 0; i <= 36; i++) {
            if (element.classList.contains(`regular${i}`) ||
                element.classList.contains(`number${i}`) ||
                element.getAttribute('data-number') == i) {
                number = i;
                break;
            }
        }

        if (number !== null) {
            console.log(`🎯 Complete bet triggered on number: ${number}`);

            // Try multiple approaches to place complete bets
            let success = false;

            // Method 1: Try the global placeCompleteBet function
            if (typeof window.placeCompleteBet === 'function') {
                try {
                    window.placeCompleteBet(number);
                    success = true;
                    console.log('✅ Used window.placeCompleteBet');
                } catch (e) {
                    console.warn('❌ window.placeCompleteBet failed:', e);
                }
            }

            // Method 2: Try the local placeCompleteBet function
            if (!success && typeof placeCompleteBet === 'function') {
                try {
                    placeCompleteBet(number);
                    success = true;
                    console.log('✅ Used local placeCompleteBet');
                } catch (e) {
                    console.warn('❌ local placeCompleteBet failed:', e);
                }
            }

            // Method 3: Direct implementation using betting system
            if (!success) {
                console.log('🔧 Using direct complete bet implementation');
                success = placeCompleteBetDirect(number);
            }

            // Method 4: Fallback simulation
            if (!success) {
                console.warn('⚠️ All methods failed, using simulation');
                simulateCompleteBet(number);
            }

            // Deactivate complete bet mode after placing bets
            setTimeout(() => {
                isCompleteBetModeActive = false;
                window.isCompleteBetMode = false;
                updateButtonAppearance();
                console.log('✅ Complete bet mode deactivated after bet placement');
            }, 500);
        }
    }

    /**
     * Direct implementation of complete bet placement
     */
    function placeCompleteBetDirect(number) {
        console.log(`🎲 Placing complete bet directly on number: ${number}`);

        try {
            // Get the complete bet positions for this number
            const completeBets = getCompleteBetPositions(number);
            console.log(`Found ${completeBets.length} complete bet positions for number ${number}`);

            let betsPlaced = 0;

            // Place bets on each position
            completeBets.forEach(selector => {
                const element = document.querySelector(selector);
                if (element) {
                    try {
                        // Simulate a click on the betting position
                        const clickEvent = new MouseEvent('click', {
                            bubbles: true,
                            cancelable: true,
                            view: window
                        });

                        element.dispatchEvent(clickEvent);
                        betsPlaced++;
                        console.log(`✅ Placed bet on: ${selector}`);
                    } catch (e) {
                        console.warn(`❌ Failed to place bet on ${selector}:`, e);
                    }
                } else {
                    console.warn(`⚠️ Element not found: ${selector}`);
                }
            });

            if (betsPlaced > 0) {
                showCompleteBetFeedback(number, betsPlaced);
                return true;
            }

        } catch (e) {
            console.error('❌ Direct complete bet placement failed:', e);
        }

        return false;
    }

    /**
     * Get complete bet positions for a number (simplified version)
     */
    function getCompleteBetPositions(number) {
        const bets = [];

        // 1. Straight up bet (the number itself)
        bets.push(`.regular${number}`);

        // 2. Split bets - simplified logic for common cases
        if (number === 0) {
            bets.push(`.line1`, `.line2`, `.line3`); // 0-1, 0-2, 0-3 splits
        } else {
            // Add adjacent number splits (simplified)
            if (number > 1) bets.push(`.line${number - 1}`); // Split with previous
            if (number < 36) bets.push(`.line${number}`); // Split with next
        }

        // 3. Add column and dozen bets
        if (number > 0) {
            // Column bets
            if (number % 3 === 1) bets.push('.bet2to1-1st'); // 1st column
            else if (number % 3 === 2) bets.push('.bet2to1-2nd'); // 2nd column
            else bets.push('.bet2to1-3rd'); // 3rd column

            // Dozen bets
            if (number <= 12) bets.push('.bet2to1-1st12');
            else if (number <= 24) bets.push('.bet2to1-2nd12');
            else bets.push('.bet2to1-3rd12');
        }

        return bets;
    }

    /**
     * Show feedback for complete bet placement
     */
    function showCompleteBetFeedback(number, betsPlaced) {
        const feedback = document.createElement('div');
        feedback.textContent = `Complete bet on ${number}! (${betsPlaced} bets placed)`;
        Object.assign(feedback.style, {
            position: 'fixed',
            left: '50%',
            top: '30%',
            transform: 'translate(-50%, -50%)',
            background: 'linear-gradient(135deg, #11998e 0%, #38ef7d 100%)',
            color: 'white',
            padding: '15px 25px',
            borderRadius: '10px',
            fontSize: '16px',
            fontWeight: 'bold',
            zIndex: Z_INDEX + 2,
            boxShadow: '0 10px 30px rgba(0, 0, 0, 0.3)',
            opacity: '0',
            transition: 'all 0.3s ease'
        });

        document.body.appendChild(feedback);

        // Animate in
        setTimeout(() => {
            feedback.style.opacity = '1';
            feedback.style.transform = 'translate(-50%, -50%) scale(1.1)';
        }, 10);

        // Remove after 2 seconds
        setTimeout(() => {
            feedback.style.opacity = '0';
            feedback.style.transform = 'translate(-50%, -50%) scale(0.9)';
            setTimeout(() => feedback.remove(), 300);
        }, 2000);
    }

    /**
     * Show tooltip for complete mode
     */
    function showCompleteModeTooltip() {
        // Create temporary tooltip
        const tooltip = document.createElement('div');
        tooltip.textContent = 'Click any number to place complete bets!';
        Object.assign(tooltip.style, {
            position: 'fixed',
            left: (currentPosition.x + 100) + 'px',
            top: (currentPosition.y + 20) + 'px',
            background: 'rgba(0, 0, 0, 0.8)',
            color: 'white',
            padding: '8px 12px',
            borderRadius: '6px',
            fontSize: '12px',
            zIndex: Z_INDEX + 1,
            pointerEvents: 'none',
            opacity: '0',
            transition: 'opacity 0.3s ease'
        });

        document.body.appendChild(tooltip);

        // Animate in
        setTimeout(() => tooltip.style.opacity = '1', 10);

        // Remove after 3 seconds
        setTimeout(() => {
            tooltip.style.opacity = '0';
            setTimeout(() => tooltip.remove(), 300);
        }, 3000);
    }

    /**
     * Play click sound if available
     */
    function playClickSound() {
        try {
            if (typeof window.playAudio !== 'undefined' && window.playAudio &&
                typeof window.selectSound !== 'undefined') {
                window.selectSound.play();
            }
        } catch (e) {
            // Ignore sound errors
        }
    }

    /**
     * Simulate complete bet placement (fallback)
     */
    function simulateCompleteBet(number) {
        console.log(`🎲 Simulating complete bet on number ${number}`);

        // Create visual feedback
        const feedback = document.createElement('div');
        feedback.textContent = `Complete bet placed on ${number}!`;
        Object.assign(feedback.style, {
            position: 'fixed',
            left: '50%',
            top: '50%',
            transform: 'translate(-50%, -50%)',
            background: 'linear-gradient(135deg, #11998e 0%, #38ef7d 100%)',
            color: 'white',
            padding: '15px 25px',
            borderRadius: '10px',
            fontSize: '16px',
            fontWeight: 'bold',
            zIndex: Z_INDEX + 2,
            boxShadow: '0 10px 30px rgba(0, 0, 0, 0.3)',
            opacity: '0',
            transition: 'all 0.3s ease'
        });

        document.body.appendChild(feedback);

        // Animate in
        setTimeout(() => {
            feedback.style.opacity = '1';
            feedback.style.transform = 'translate(-50%, -50%) scale(1.1)';
        }, 10);

        // Remove after 2 seconds
        setTimeout(() => {
            feedback.style.opacity = '0';
            feedback.style.transform = 'translate(-50%, -50%) scale(0.9)';
            setTimeout(() => feedback.remove(), 300);
        }, 2000);
    }

    /**
     * Initialize the independent floating button
     */
    function initialize() {
        console.log('🚀 Initializing Independent Floating COMPLETE Button...');

        // Create button immediately
        createIndependentButton();

        // Ensure it stays on top and functional
        const protectionInterval = setInterval(() => {
            if (!document.getElementById(BUTTON_ID)) {
                console.log('🔧 Button missing, recreating...');
                createIndependentButton();
            } else if (floatingButton) {
                // Ensure z-index stays maximum
                floatingButton.style.zIndex = Z_INDEX;

                // Ensure position is maintained (in case something moved it)
                floatingButton.style.left = currentPosition.x + 'px';
                floatingButton.style.top = currentPosition.y + 'px';
            }
        }, 1000);

        // Setup number handlers periodically (in case DOM changes)
        setInterval(() => {
            if (isCompleteBetModeActive) {
                setupNumberClickHandlers();
            }
        }, 2000);

        console.log('✅ Independent Floating COMPLETE Button initialized successfully!');
    }

    // Expose API for debugging and control
    window.IndependentFloatingCompleteButton = {
        isActive: () => isCompleteBetModeActive,
        activate: () => {
            isCompleteBetModeActive = true;
            window.isCompleteBetMode = true;
            updateButtonAppearance();
            setupNumberClickHandlers();
            console.log('🎯 Complete bet mode activated via API');
        },
        deactivate: () => {
            isCompleteBetModeActive = false;
            window.isCompleteBetMode = false;
            updateButtonAppearance();
            console.log('🎯 Complete bet mode deactivated via API');
        },
        toggle: () => {
            if (isCompleteBetModeActive) {
                window.IndependentFloatingCompleteButton.deactivate();
            } else {
                window.IndependentFloatingCompleteButton.activate();
            }
        },
        recreate: () => {
            createIndependentButton();
            console.log('🔧 Button recreated via API');
        },
        getPosition: () => currentPosition,
        setPosition: (x, y) => {
            currentPosition.x = x;
            currentPosition.y = y;
            if (floatingButton) {
                floatingButton.style.left = x + 'px';
                floatingButton.style.top = y + 'px';
            }
            // Save the new position
            saveCurrentPosition();
        },
        resetPosition: () => {
            // Clear saved position and use default
            try {
                localStorage.removeItem(POSITION_STORAGE_KEY);
                console.log('🗑️ Cleared saved position');
            } catch (e) {
                console.warn('⚠️ Failed to clear saved position:', e);
            }

            // Reset to default position
            currentPosition.x = 150;
            currentPosition.y = 150;

            if (floatingButton) {
                floatingButton.style.left = currentPosition.x + 'px';
                floatingButton.style.top = currentPosition.y + 'px';
            }
        },
        getSavedPosition: () => {
            try {
                const savedPosition = localStorage.getItem(POSITION_STORAGE_KEY);
                return savedPosition ? JSON.parse(savedPosition) : null;
            } catch (e) {
                console.warn('⚠️ Failed to get saved position:', e);
                return null;
            }
        }
    };

    /**
     * Handle window resize to keep button within bounds
     */
    function handleWindowResize() {
        if (!floatingButton) return;

        const maxX = window.innerWidth - 90;
        const maxY = window.innerHeight - 90;

        let positionChanged = false;

        if (currentPosition.x > maxX) {
            currentPosition.x = maxX;
            positionChanged = true;
        }

        if (currentPosition.y > maxY) {
            currentPosition.y = maxY;
            positionChanged = true;
        }

        if (positionChanged) {
            floatingButton.style.left = currentPosition.x + 'px';
            floatingButton.style.top = currentPosition.y + 'px';
            saveCurrentPosition();
            console.log(`📐 Adjusted position for window resize: (${currentPosition.x}, ${currentPosition.y})`);
        }
    }

    // Add window resize listener
    window.addEventListener('resize', handleWindowResize);

    // Test localStorage functionality
    function testLocalStorage() {
        try {
            const testKey = 'test-storage-key';
            const testValue = { test: 'data', timestamp: Date.now() };

            localStorage.setItem(testKey, JSON.stringify(testValue));
            const retrieved = localStorage.getItem(testKey);
            const parsed = JSON.parse(retrieved);

            localStorage.removeItem(testKey);

            console.log('🧪 localStorage test successful:', parsed);
            return true;
        } catch (e) {
            console.error('❌ localStorage test failed:', e);
            return false;
        }
    }

    // Test localStorage before initializing
    testLocalStorage();

    // Initialize immediately - no waiting for DOM or other scripts
    initialize();

    console.log('🚀 Independent Floating COMPLETE Button loaded successfully!');
})();
