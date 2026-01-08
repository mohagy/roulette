/**
 * Betting Board Blocker - Preserves Betting Slip Functionality
 * Blocks only the betting board interactions while preserving all betting slip features
 */

const BettingBoardBlocker = (function() {
    'use strict';

    // State management
    let isBlocked = true;
    let selectedDraw = null;
    let initialized = false;
    let blockingOverlay = null;

    // Configuration - Only block betting board elements, NOT betting slip functionality
    const config = {
        bettingBoardSelectors: [
            '.part', '.number', '.bottom-column', '.regular', '.line', '.corner', '.between',
            '.number0', '.number1', '.number2', '.number3', '.number4', '.number5', '.number6',
            '.number7', '.number8', '.number9', '.number10', '.number11', '.number12', '.number13',
            '.number14', '.number15', '.number16', '.number17', '.number18', '.number19', '.number20',
            '.number21', '.number22', '.number23', '.number24', '.number25', '.number26', '.number27',
            '.number28', '.number29', '.number30', '.number31', '.number32', '.number33', '.number34',
            '.number35', '.number36', '.regular0', '.regular1', '.regular2', '.regular3', '.regular4',
            '.regular5', '.regular6', '.regular7', '.regular8', '.regular9', '.regular10', '.regular11',
            '.regular12', '.regular13', '.regular14', '.regular15', '.regular16', '.regular17',
            '.regular18', '.regular19', '.regular20', '.regular21', '.regular22', '.regular23',
            '.regular24', '.regular25', '.regular26', '.regular27', '.regular28', '.regular29',
            '.regular30', '.regular31', '.regular32', '.regular33', '.regular34', '.regular35', '.regular36',
            '.column-1st12', '.column-2nd12', '.column-3rd12', '.column-1to18', '.column-19to36',
            '.column-even', '.column-odd', '.column-red', '.column-black', '.bet2to1-1', '.bet2to1-2', '.bet2to1-3'
        ],
        // DO NOT BLOCK these betting slip related elements
        preservedSelectors: [
            '.bet-display-container', '.bet-display-list', '.betting-chip', '.bet-action-button',
            '.print-slip-button', '.cancel-slip-button', '.update-stake-modal', '.cancel-slip-modal',
            '.bet-display-header', '.bet-display-body', '.bet-display-footer', '.stake-control',
            '#global-stake-input', '#print-betting-slip-btn', '#cancel-betting-slip-btn',
            '.elegant-cancel-button', '.reprint-slip-button', '.right-sidebar'
        ],
        drawSelectors: [
            '.upcoming-draw-item',
            '[data-draw-number]',
            '.tv-draw-number-item'
        ],
        events: ['click', 'mousedown', 'mouseup', 'touchstart', 'touchend']
    };

    /**
     * Block event handler - prevents betting board interactions only
     */
    function blockEvent(event) {
        // Check if the event target is a preserved element (betting slip functionality)
        const target = event.target;
        const isPreservedElement = config.preservedSelectors.some(selector => {
            return target.closest(selector) !== null;
        });

        if (isPreservedElement) {
            // Allow the event for betting slip functionality
            console.log('✅ Allowing event for betting slip element:', target);
            return true;
        }

        // Block betting board interactions
        event.preventDefault();
        event.stopImmediatePropagation();
        event.stopPropagation();

        console.log('🚫 BETTING BOARD BLOCKED - Draw selection required');
        showBlockedMessage();

        return false;
    }

    /**
     * Create and show visual blocking overlay
     */
    function addVisualBlocking() {
        const bettingArea = document.querySelector('.betting-area');
        if (!bettingArea || blockingOverlay) return;

        bettingArea.classList.add('board-disabled-no-draw');

        blockingOverlay = document.createElement('div');
        blockingOverlay.className = 'betting-blocked-overlay';
        blockingOverlay.innerHTML = `
            <div class="blocking-message">
                <div class="blocking-icon">
                    <i class="fas fa-hand-paper"></i>
                </div>
                <div class="blocking-text">
                    <h3>🎯 Draw Selection Required</h3>
                    <p>Click on a draw number in the upcoming draws panel to enable betting</p>
                    <div class="blocking-arrow">
                        <i class="fas fa-arrow-up"></i>
                        <span>Select a draw number above</span>
                    </div>
                </div>
            </div>
        `;

        // Enhanced styling
        blockingOverlay.style.cssText = `
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            background: rgba(0, 0, 0, 0.85) !important;
            display: flex !important;
            justify-content: center !important;
            align-items: center !important;
            z-index: 999 !important;
            backdrop-filter: blur(5px) !important;
            font-family: Arial, sans-serif !important;
            color: white !important;
            text-align: center !important;
            animation: fadeIn 0.3s ease !important;
            pointer-events: none !important;
        `;

        // Add animation styles
        if (!document.querySelector('#blocking-animations')) {
            const style = document.createElement('style');
            style.id = 'blocking-animations';
            style.textContent = `
                @keyframes fadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }
                .blocking-message h3 {
                    color: #ffc107 !important;
                    margin-bottom: 10px !important;
                    font-size: 24px !important;
                }
                .blocking-message p {
                    margin-bottom: 15px !important;
                    font-size: 16px !important;
                }
                .blocking-arrow {
                    animation: bounce 2s infinite !important;
                }
                @keyframes bounce {
                    0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
                    40% { transform: translateY(-10px); }
                    60% { transform: translateY(-5px); }
                }
            `;
            document.head.appendChild(style);
        }

        bettingArea.appendChild(blockingOverlay);
        console.log('🚫 Visual blocking overlay added (betting slip functionality preserved)');
    }

    /**
     * Remove visual blocking overlay
     */
    function removeVisualBlocking() {
        const bettingArea = document.querySelector('.betting-area');
        if (bettingArea) {
            bettingArea.classList.remove('board-disabled-no-draw');
        }

        if (blockingOverlay) {
            blockingOverlay.style.transition = 'all 0.5s ease';
            blockingOverlay.style.opacity = '0';
            blockingOverlay.style.transform = 'scale(0.9)';

            setTimeout(() => {
                if (blockingOverlay && blockingOverlay.parentNode) {
                    blockingOverlay.remove();
                    blockingOverlay = null;
                }
            }, 500);

            console.log('✅ Visual blocking overlay removed');
        }
    }

    /**
     * Block betting board functionality (preserve betting slip)
     */
    function blockBettingBoard() {
        console.log('🚫 BLOCKING BETTING BOARD (preserving betting slip functionality)...');
        isBlocked = true;

        // Get only betting board elements (not betting slip elements)
        const bettingElements = document.querySelectorAll(config.bettingBoardSelectors.join(', '));
        console.log(`🚫 Found ${bettingElements.length} betting board elements to block`);

        // Block each betting board element
        bettingElements.forEach(element => {
            // Skip if this element is part of betting slip functionality
            const isPreservedElement = config.preservedSelectors.some(selector => {
                return element.closest(selector) !== null;
            });

            if (isPreservedElement) {
                console.log('✅ Preserving betting slip element:', element);
                return; // Skip blocking this element
            }

            // Store original styles
            if (!element.dataset.originalPointerEvents) {
                element.dataset.originalPointerEvents = element.style.pointerEvents || 'auto';
                element.dataset.originalCursor = element.style.cursor || 'pointer';
                element.dataset.originalOpacity = element.style.opacity || '1';
                element.dataset.originalFilter = element.style.filter || 'none';
            }

            // Apply blocking styles only to betting board elements
            element.style.setProperty('pointer-events', 'none', 'important');
            element.style.setProperty('cursor', 'not-allowed', 'important');
            element.style.setProperty('opacity', '0.3', 'important');
            element.style.setProperty('filter', 'grayscale(50%)', 'important');
            element.style.setProperty('user-select', 'none', 'important');

            // Add blocking event listeners only to betting board elements
            config.events.forEach(eventType => {
                element.addEventListener(eventType, blockEvent, { capture: true, passive: false });
            });

            element.classList.add('betting-board-blocked');
        });

        // Handle jQuery events more carefully - only block betting board interactions
        if (typeof $ !== 'undefined') {
            const jqueryElements = $(config.bettingBoardSelectors.join(', '));

            // Filter out betting slip elements
            const filteredElements = jqueryElements.filter(function() {
                const isPreservedElement = config.preservedSelectors.some(selector => {
                    return $(this).closest(selector).length > 0;
                });
                return !isPreservedElement;
            });

            // Only block filtered elements (betting board only)
            filteredElements.off('click.boardBlocker').on('click.boardBlocker', function(e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                e.stopPropagation();
                blockEvent(e);
                return false;
            });
        }

        // Add visual blocking
        addVisualBlocking();

        console.log('🚫 BETTING BOARD BLOCKED - BETTING SLIP FUNCTIONALITY PRESERVED');
    }

    /**
     * Unblock betting board functionality
     */
    function unblockBettingBoard() {
        console.log('✅ UNBLOCKING BETTING BOARD - Draw selected:', selectedDraw);
        isBlocked = false;

        // Get all betting board elements
        const bettingElements = document.querySelectorAll(config.bettingBoardSelectors.join(', '));

        // Restore each betting board element
        bettingElements.forEach(element => {
            // Skip if this element is part of betting slip functionality
            const isPreservedElement = config.preservedSelectors.some(selector => {
                return element.closest(selector) !== null;
            });

            if (isPreservedElement) {
                return; // Skip unblocking betting slip elements (they were never blocked)
            }

            // Restore original styles
            element.style.setProperty('pointer-events', element.dataset.originalPointerEvents || 'auto', 'important');
            element.style.setProperty('cursor', element.dataset.originalCursor || 'pointer', 'important');
            element.style.setProperty('opacity', element.dataset.originalOpacity || '1', 'important');
            element.style.setProperty('filter', element.dataset.originalFilter || 'none', 'important');
            element.style.removeProperty('user-select');
            element.style.transition = 'all 0.3s ease';

            // Remove blocking event listeners
            config.events.forEach(eventType => {
                element.removeEventListener(eventType, blockEvent, true);
            });

            element.classList.remove('betting-board-blocked');
        });

        // Remove jQuery blocking handlers
        if (typeof $ !== 'undefined') {
            $(config.bettingBoardSelectors.join(', ')).off('click.boardBlocker');
        }

        // Remove visual blocking
        removeVisualBlocking();

        // Set global variable
        window.selectedDrawNumber = selectedDraw;

        // Show success notification
        showSuccessNotification(selectedDraw);

        // Dispatch events
        document.dispatchEvent(new CustomEvent('bettingBoardEnabled', {
            detail: { drawNumber: selectedDraw }
        }));

        console.log('✅ BETTING BOARD ENABLED for draw:', selectedDraw, '- BETTING SLIP FUNCTIONALITY PRESERVED');
    }

    /**
     * Show blocked message with panel highlighting
     */
    function showBlockedMessage() {
        const upcomingPanel = document.querySelector('.upcoming-draws-panel');
        if (upcomingPanel) {
            upcomingPanel.style.boxShadow = '0 0 30px rgba(255, 107, 107, 0.8)';
            upcomingPanel.style.borderColor = '#ff6b6b';
            upcomingPanel.style.animation = 'pulse 1s ease-in-out 3';

            setTimeout(() => {
                upcomingPanel.style.boxShadow = '';
                upcomingPanel.style.borderColor = '';
                upcomingPanel.style.animation = '';
            }, 3000);
        }
    }

    /**
     * Show success notification
     */
    function showSuccessNotification(drawNumber) {
        // Remove existing notifications
        document.querySelectorAll('.draw-selection-notification').forEach(n => n.remove());

        const notification = document.createElement('div');
        notification.className = 'draw-selection-notification success';
        notification.innerHTML = `
            <div class="notification-content">
                <div class="notification-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="notification-text">
                    <div class="notification-title">✅ Draw #${drawNumber} Selected</div>
                    <div class="notification-message">Betting board enabled! Betting slip preserved.</div>
                </div>
            </div>
        `;

        notification.style.cssText = `
            position: fixed !important;
            top: 20px !important;
            right: 20px !important;
            background: linear-gradient(135deg, #28a745, #20c997) !important;
            color: white !important;
            padding: 15px 20px !important;
            border-radius: 10px !important;
            z-index: 10001 !important;
            font-weight: bold !important;
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4) !important;
            border: 2px solid #34ce57 !important;
            min-width: 300px !important;
            animation: slideInRight 0.3s ease !important;
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 4000);
    }

    /**
     * Handle draw selection from upcoming draws panel
     */
    function handleDrawSelection(drawNumber, sourceElement) {
        console.log('🎯 Processing draw selection:', drawNumber);

        if (!drawNumber || isNaN(parseInt(drawNumber))) {
            console.log('❌ Invalid draw number:', drawNumber);
            return;
        }

        selectedDraw = parseInt(drawNumber);

        // Mark draw as selected in UI
        markDrawAsSelected(sourceElement, selectedDraw);

        // Unblock betting board (preserve betting slip)
        unblockBettingBoard();

        // Dispatch events for other systems
        const events = [
            new CustomEvent('drawNumberSelected', { detail: { drawNumber: selectedDraw } }),
            new CustomEvent('drawSelected', { detail: { drawNumber: selectedDraw } }),
            new CustomEvent('drawSelectionChanged', { detail: { drawNumber: selectedDraw } })
        ];

        events.forEach(event => {
            document.dispatchEvent(event);
        });

        console.log('✅ Draw selection complete:', selectedDraw);
    }

    /**
     * Mark draw as selected in UI
     */
    function markDrawAsSelected(selectedItem, drawNumber) {
        // Remove selection from all draw items
        document.querySelectorAll(config.drawSelectors.join(', ')).forEach(item => {
            item.classList.remove('selected');
        });

        // Mark the selected item
        if (selectedItem) {
            selectedItem.classList.add('selected');

            const parentDrawItem = selectedItem.closest('.upcoming-draw-item');
            if (parentDrawItem && parentDrawItem !== selectedItem) {
                parentDrawItem.classList.add('selected');
            }
        }

        window.selectedDrawNumber = drawNumber;
        console.log('🎯 Draw marked as selected:', drawNumber);
    }

    /**
     * Setup draw selection event handlers
     */
    function setupDrawSelectionHandlers() {
        console.log('🎯 Setting up draw selection handlers...');

        // Listen for clicks on draw items
        document.addEventListener('click', function(event) {
            const upcomingPanel = event.target.closest('.upcoming-draws-panel');
            if (!upcomingPanel) return;

            console.log('🎯 Click in upcoming draws panel:', event.target);

            const drawItem = event.target.closest('.upcoming-draw-item');
            if (drawItem) {
                event.preventDefault();
                event.stopPropagation();

                let drawNumber = parseInt(drawItem.dataset.drawNumber, 10);

                if (isNaN(drawNumber)) {
                    const drawNumberElement = drawItem.querySelector('.draw-number');
                    if (drawNumberElement) {
                        const drawNumberText = drawNumberElement.textContent.trim();
                        drawNumber = parseInt(drawNumberText.replace('#', '').replace(/\s.*/, ''), 10);
                    }
                }

                if (!isNaN(drawNumber)) {
                    console.log('🎯 Draw item clicked:', drawNumber);
                    handleDrawSelection(drawNumber, drawItem);
                }
                return;
            }

            const drawElement = event.target.closest('[data-draw-number]');
            if (drawElement) {
                event.preventDefault();
                event.stopPropagation();

                const drawNumber = parseInt(drawElement.dataset.drawNumber, 10);
                if (!isNaN(drawNumber)) {
                    console.log('🎯 Draw element clicked:', drawNumber);
                    handleDrawSelection(drawNumber, drawElement);
                }
                return;
            }
        }, true);

        // Listen for existing draw selection events
        document.addEventListener('drawNumberSelected', function(event) {
            if (event.detail && event.detail.drawNumber) {
                handleDrawSelection(event.detail.drawNumber, null);
            }
        });

        console.log('🎯 Draw selection handlers setup complete');
    }

    /**
     * Initialize the betting board blocker
     */
    function init() {
        if (initialized) {
            console.log('🎯 BettingBoardBlocker already initialized');
            return;
        }

        console.log('🎯 Initializing Betting Board Blocker (preserving betting slip functionality)...');

        // Block betting board immediately
        blockBettingBoard();

        // Setup draw selection handlers
        setupDrawSelectionHandlers();

        initialized = true;
        console.log('🎯 BETTING BOARD BLOCKER ACTIVE - BETTING SLIP FUNCTIONALITY PRESERVED');
    }

    /**
     * Test function for manual draw selection
     */
    function testSelectDraw(drawNumber) {
        console.log('🧪 TEST: Manually selecting draw:', drawNumber);
        handleDrawSelection(drawNumber, null);
    }

    /**
     * Force unblock for emergency situations
     */
    function forceUnblock() {
        console.log('🚨 FORCE UNBLOCK: Emergency betting board enable');
        selectedDraw = 999; // Emergency draw number
        unblockBettingBoard();
    }

    /**
     * Get current system status
     */
    function getStatus() {
        return {
            initialized,
            isBlocked,
            selectedDraw,
            upcomingDrawsPanelExists: !!document.querySelector('.upcoming-draws-panel'),
            bettingBoardElementsCount: document.querySelectorAll(config.bettingBoardSelectors.join(', ')).length,
            bettingSlipPreserved: true
        };
    }

    // Public API
    return {
        init,
        block: blockBettingBoard,
        unblock: unblockBettingBoard,
        testSelectDraw,
        forceUnblock,
        getStatus,
        isBlocked: () => isBlocked,
        getSelectedDraw: () => selectedDraw
    };
})();

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('🎯 DOM ready, initializing Betting Board Blocker...');
    BettingBoardBlocker.init();
});

// Multiple initialization attempts to ensure reliability
setTimeout(() => {
    console.log('🎯 Re-initializing after 1s delay...');
    BettingBoardBlocker.init();
}, 1000);

setTimeout(() => {
    console.log('🎯 Final initialization after 2s delay...');
    BettingBoardBlocker.init();
}, 2000);

// Export for global access
window.BettingBoardBlocker = BettingBoardBlocker;

console.log('✅ Betting Board Blocker loaded - BETTING BOARD BLOCKED, BETTING SLIP PRESERVED');
