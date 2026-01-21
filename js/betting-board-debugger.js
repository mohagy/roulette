/**
 * Betting Board Debugger - Testing Tools for Betting Board Blocker
 * Preserves betting slip functionality while testing draw selection
 */

window.BettingBoardDebugger = (function() {
    'use strict';

    /**
     * Test the complete draw selection workflow
     */
    function testCompleteWorkflow(drawNumber = 172) {
        console.log('🔍 TESTING BETTING BOARD BLOCKER WORKFLOW');
        console.log('=========================================');
        console.log('Target Draw Number:', drawNumber);

        // 1. Check system availability
        console.log('\n1. 🔧 SYSTEM AVAILABILITY CHECK');
        const systems = {
            BettingBoardBlocker: typeof window.BettingBoardBlocker,
            jQuery: typeof window.$,
            upcomingDrawsPanel: !!document.querySelector('.upcoming-draws-panel'),
            bettingArea: !!document.querySelector('.betting-area'),
            bettingSlipContainer: !!document.querySelector('.bet-display-container')
        };
        
        Object.entries(systems).forEach(([name, status]) => {
            console.log(`   ${status ? '✅' : '❌'} ${name}:`, status);
        });

        // 2. Check current blocking state
        console.log('\n2. 🚫 BLOCKING STATE CHECK');
        if (window.BettingBoardBlocker) {
            const status = window.BettingBoardBlocker.getStatus();
            console.log('   Current Status:', status);
            console.log('   Is Blocked:', status.isBlocked);
            console.log('   Selected Draw:', status.selectedDraw);
            console.log('   Betting Board Elements:', status.bettingBoardElementsCount);
            console.log('   Betting Slip Preserved:', status.bettingSlipPreserved);
        }

        // 3. Check betting slip functionality
        console.log('\n3. 🎫 BETTING SLIP FUNCTIONALITY CHECK');
        const bettingSlipElements = {
            'Bet Display Container': document.querySelector('.bet-display-container'),
            'Print Button': document.querySelector('#print-betting-slip-btn'),
            'Cancel Button': document.querySelector('#cancel-betting-slip-btn'),
            'Stake Input': document.querySelector('#global-stake-input'),
            'Bet Display List': document.querySelector('.bet-display-list')
        };

        Object.entries(bettingSlipElements).forEach(([name, element]) => {
            const isBlocked = element && (
                element.style.pointerEvents === 'none' || 
                element.classList.contains('betting-board-blocked')
            );
            console.log(`   ${isBlocked ? '❌' : '✅'} ${name}:`, !!element, isBlocked ? '(BLOCKED)' : '(PRESERVED)');
        });

        // 4. Test manual draw selection
        console.log('\n4. 🧪 TESTING MANUAL DRAW SELECTION');
        if (window.BettingBoardBlocker?.testSelectDraw) {
            console.log('   Calling testSelectDraw...');
            window.BettingBoardBlocker.testSelectDraw(drawNumber);
        } else {
            console.log('   ❌ testSelectDraw function not available');
        }

        // 5. Check final state after delay
        setTimeout(() => {
            console.log('\n5. 📊 FINAL STATE CHECK (after 1s)');
            if (window.BettingBoardBlocker) {
                const finalStatus = window.BettingBoardBlocker.getStatus();
                console.log('   Final Status:', finalStatus);
                
                const overlay = document.querySelector('.betting-blocked-overlay');
                console.log('   Overlay visible:', !!overlay);
                
                const bettingElements = document.querySelectorAll('.part, .number, .bottom-column');
                const blockedElements = Array.from(bettingElements).filter(el => 
                    el.style.pointerEvents === 'none' || el.classList.contains('betting-board-blocked')
                );
                console.log('   Betting board elements (total):', bettingElements.length);
                console.log('   Betting board elements (blocked):', blockedElements.length);
                console.log('   Global selectedDrawNumber:', window.selectedDrawNumber);
                
                // Check betting slip preservation
                const bettingSlipContainer = document.querySelector('.bet-display-container');
                const isSlipBlocked = bettingSlipContainer && (
                    bettingSlipContainer.style.pointerEvents === 'none' ||
                    bettingSlipContainer.classList.contains('betting-board-blocked')
                );
                console.log('   Betting slip preserved:', !isSlipBlocked);
            }
            
            console.log('\n🎯 WORKFLOW TEST COMPLETE');
        }, 1000);
    }

    /**
     * Simulate clicking on a specific draw item
     */
    function simulateDrawClick(drawNumber = 172) {
        console.log('🖱️ SIMULATING DRAW CLICK');
        console.log('Target Draw:', drawNumber);

        const panel = document.querySelector('.upcoming-draws-panel');
        if (!panel) {
            console.log('❌ No upcoming draws panel found');
            return false;
        }

        // Try to find the draw item
        let drawItem = panel.querySelector(`[data-draw-number="${drawNumber}"]`);
        
        if (!drawItem) {
            // Try to find by text content
            const drawItems = panel.querySelectorAll('.upcoming-draw-item');
            for (const item of drawItems) {
                const drawNumberElement = item.querySelector('.draw-number');
                if (drawNumberElement) {
                    const text = drawNumberElement.textContent.trim();
                    const num = parseInt(text.replace('#', '').replace(/\s.*/, ''), 10);
                    if (num === drawNumber) {
                        drawItem = item;
                        break;
                    }
                }
            }
        }

        if (!drawItem) {
            console.log('❌ Draw item not found for number:', drawNumber);
            console.log('Available draw items:');
            panel.querySelectorAll('.upcoming-draw-item').forEach((item, index) => {
                const drawNum = item.dataset.drawNumber;
                const drawText = item.querySelector('.draw-number')?.textContent;
                console.log(`   ${index}: data-draw-number=${drawNum}, text="${drawText}"`);
            });
            return false;
        }

        console.log('✅ Found draw item:', drawItem);

        // Create and dispatch click event
        const clickEvent = new MouseEvent('click', {
            bubbles: true,
            cancelable: true,
            view: window
        });

        console.log('🖱️ Dispatching click event...');
        drawItem.dispatchEvent(clickEvent);

        // Check result after delay
        setTimeout(() => {
            const isBlocked = window.BettingBoardBlocker?.isBlocked();
            const selectedDraw = window.selectedDrawNumber;
            console.log('📊 Click result:');
            console.log('   Is still blocked:', isBlocked);
            console.log('   Selected draw:', selectedDraw);
            console.log('   Overlay visible:', !!document.querySelector('.betting-blocked-overlay'));
            
            // Check betting slip preservation
            const bettingSlipContainer = document.querySelector('.bet-display-container');
            const isSlipBlocked = bettingSlipContainer && (
                bettingSlipContainer.style.pointerEvents === 'none' ||
                bettingSlipContainer.classList.contains('betting-board-blocked')
            );
            console.log('   Betting slip preserved:', !isSlipBlocked);
        }, 500);

        return true;
    }

    /**
     * Check current system state in detail
     */
    function checkSystemState() {
        console.log('🔍 DETAILED BETTING BOARD BLOCKER STATE');
        console.log('======================================');

        // Global variables
        console.log('\n📊 GLOBAL VARIABLES:');
        console.log('   window.selectedDrawNumber:', window.selectedDrawNumber);
        console.log('   window.BettingBoardBlocker:', typeof window.BettingBoardBlocker);

        // System status
        if (window.BettingBoardBlocker) {
            console.log('\n🎯 BETTING BOARD BLOCKER STATUS:');
            const status = window.BettingBoardBlocker.getStatus();
            Object.entries(status).forEach(([key, value]) => {
                console.log(`   ${key}:`, value);
            });
        }

        // DOM elements
        console.log('\n🏗️ DOM ELEMENTS:');
        const elements = {
            'Upcoming Draws Panel': document.querySelector('.upcoming-draws-panel'),
            'Betting Area': document.querySelector('.betting-area'),
            'Blocking Overlay': document.querySelector('.betting-blocked-overlay'),
            'Bet Display Container': document.querySelector('.bet-display-container'),
            'Draw Items': document.querySelectorAll('.upcoming-draw-item').length,
            'Betting Board Elements': document.querySelectorAll('.part, .number, .bottom-column').length
        };

        Object.entries(elements).forEach(([name, element]) => {
            if (typeof element === 'number') {
                console.log(`   ${name}:`, element);
            } else {
                console.log(`   ${name}:`, !!element);
            }
        });

        // Betting board vs betting slip state
        console.log('\n🎰 BETTING BOARD vs BETTING SLIP STATE:');
        const bettingBoardElements = document.querySelectorAll('.part, .number, .bottom-column');
        const blockedBoardElements = Array.from(bettingBoardElements).filter(el => 
            el.style.pointerEvents === 'none' || 
            el.classList.contains('betting-board-blocked') ||
            el.style.opacity === '0.3'
        );
        
        console.log('   Betting board elements (total):', bettingBoardElements.length);
        console.log('   Betting board elements (blocked):', blockedBoardElements.length);
        console.log('   Board blocking percentage:', ((blockedBoardElements.length / bettingBoardElements.length) * 100).toFixed(1) + '%');

        // Check betting slip preservation
        const bettingSlipElements = document.querySelectorAll('.bet-display-container, .bet-action-button, #print-betting-slip-btn, #cancel-betting-slip-btn');
        const blockedSlipElements = Array.from(bettingSlipElements).filter(el => 
            el.style.pointerEvents === 'none' || 
            el.classList.contains('betting-board-blocked')
        );
        
        console.log('   Betting slip elements (total):', bettingSlipElements.length);
        console.log('   Betting slip elements (blocked):', blockedSlipElements.length);
        console.log('   Slip preservation success:', blockedSlipElements.length === 0 ? '✅ YES' : '❌ NO');

        console.log('\n📝 SYSTEM READY FOR TESTING');
        console.log('Available test commands:');
        console.log('   BettingBoardDebugger.test(drawNumber) - Test complete workflow');
        console.log('   BettingBoardDebugger.simulate(drawNumber) - Simulate draw click');
        console.log('   BettingBoardDebugger.forceUnblock() - Emergency unblock');
        console.log('   BettingBoardDebugger.reset() - Reset system');
    }

    /**
     * Force unblock betting board (emergency function)
     */
    function forceUnblock() {
        console.log('🚨 EMERGENCY FORCE UNBLOCK');
        
        if (window.BettingBoardBlocker?.forceUnblock) {
            window.BettingBoardBlocker.forceUnblock();
        } else {
            console.log('   Manual unblock fallback...');
            
            // Remove overlay
            const overlay = document.querySelector('.betting-blocked-overlay');
            if (overlay) {
                overlay.remove();
                console.log('   ✅ Overlay removed');
            }
            
            // Enable betting board elements only
            const bettingElements = document.querySelectorAll('.part, .number, .bottom-column');
            bettingElements.forEach(element => {
                element.style.removeProperty('pointer-events');
                element.style.removeProperty('cursor');
                element.style.removeProperty('opacity');
                element.style.removeProperty('filter');
                element.classList.remove('betting-board-blocked');
            });
            console.log('   ✅ Betting board elements enabled:', bettingElements.length);
            
            // Set global variable
            window.selectedDrawNumber = 999;
            console.log('   ✅ Global variable set to emergency value');
        }
        
        console.log('🚨 EMERGENCY UNBLOCK COMPLETE - BETTING SLIP PRESERVED');
    }

    /**
     * Reset the system to initial state
     */
    function reset() {
        console.log('🔄 RESETTING BETTING BOARD BLOCKER');
        
        if (window.BettingBoardBlocker?.block) {
            window.BettingBoardBlocker.block();
            console.log('   ✅ System reset via BettingBoardBlocker.block()');
        } else {
            console.log('   ❌ BettingBoardBlocker.block() not available');
        }
        
        // Clear global variable
        window.selectedDrawNumber = null;
        console.log('   ✅ Global variable cleared');
        
        console.log('🔄 SYSTEM RESET COMPLETE - BETTING SLIP PRESERVED');
    }

    // Public API
    return {
        test: testCompleteWorkflow,
        simulate: simulateDrawClick,
        check: checkSystemState,
        forceUnblock: forceUnblock,
        reset: reset
    };
})();

// Auto-run initial check after page load
setTimeout(() => {
    console.log('🔍 Betting Board Debugger loaded');
    console.log('Available commands:');
    console.log('   BettingBoardDebugger.test(drawNumber) - Test complete workflow');
    console.log('   BettingBoardDebugger.simulate(drawNumber) - Simulate draw click');
    console.log('   BettingBoardDebugger.check() - Check current state');
    console.log('   BettingBoardDebugger.forceUnblock() - Emergency unblock');
    console.log('   BettingBoardDebugger.reset() - Reset system');
    
    // Auto-check state
    BettingBoardDebugger.check();
}, 3000);
