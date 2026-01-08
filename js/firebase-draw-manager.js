/**
 * Firebase Draw Manager
 * 
 * Handles all draw-related operations using Firebase Realtime Database
 * Replaces PHP endpoints like save_draw_result.php and update_draw.php
 */

const FirebaseDrawManager = (function() {
    let initialized = false;
    let currentDrawListener = null;
    let drawHistoryListener = null;

    /**
     * Initialize the draw manager
     */
    function initialize() {
        if (!window.FirebaseService) {
            console.error('FirebaseService not available');
            return false;
        }

        if (!FirebaseService.isOnline()) {
            console.warn('Firebase is offline, draw manager will queue operations');
        }

        initialized = true;
        console.log('FirebaseDrawManager initialized');
        return true;
    }

    /**
     * Save a draw result to Firebase
     * Replaces api/save_draw_result.php
     */
    async function saveDrawResult(drawData) {
        if (!initialized) {
            if (!initialize()) {
                return { status: 'error', message: 'FirebaseDrawManager not initialized' };
            }
        }

        const {
            drawNumber,
            winningNumber,
            winningColor,
            isForced = false,
            source = 'unknown'
        } = drawData;

        // Validate data
        if (drawNumber === null || drawNumber === undefined || 
            winningNumber === null || winningNumber === undefined || 
            !winningColor) {
            return { status: 'error', message: 'Missing required parameters' };
        }

        if (winningNumber < 0 || winningNumber > 36) {
            return { status: 'error', message: 'Invalid winning number' };
        }

        if (!['red', 'black', 'green'].includes(winningColor)) {
            return { status: 'error', message: 'Invalid winning color' };
        }

        try {
            const timestamp = new Date().toISOString();
            const drawId = 'DRAW-' + new Date().toISOString().slice(0, 10).replace(/-/g, '') + '-' + drawNumber;
            const notes = isForced ? `Forced number set by ${source}` : 'Random number';

            // 1. Save draw result
            const drawResult = {
                drawId: drawId,
                drawNumber: drawNumber,
                winningNumber: winningNumber,
                winningColor: winningColor,
                isManual: isForced ? 1 : 0,
                source: source,
                notes: notes,
                timestamp: timestamp,
                createdAt: timestamp
            };

            await FirebaseService.Draws.set(drawNumber, drawResult);

            // 2. Update game state
            const currentState = await FirebaseService.GameState.getCurrent() || {};
            const rollHistory = (currentState.rollHistory || []).slice(0, 4);
            const rollColors = (currentState.rollColors || []).slice(0, 4);

            const newGameState = {
                drawNumber: drawNumber,
                nextDrawNumber: drawNumber + 1,
                winningNumber: winningNumber,
                nextWinningNumber: currentState.nextWinningNumber || 0,
                rollHistory: [winningNumber, ...rollHistory],
                rollColors: [winningColor, ...rollColors],
                lastDrawFormatted: `#${drawNumber}`,
                nextDrawFormatted: `#${drawNumber + 1}`,
                manualMode: currentState.manualMode || false,
                updatedAt: timestamp
            };

            await FirebaseService.GameState.setCurrent(newGameState);

            // 3. Update analytics
            const analytics = await FirebaseService.Analytics.getCurrent() || {};
            const allSpins = (analytics.allSpins || []).slice(0, 99); // Keep last 100
            const numberFrequency = analytics.numberFrequency || Array(37).fill(0);

            const updatedAnalytics = {
                allSpins: [winningNumber, ...allSpins],
                numberFrequency: numberFrequency.map((count, index) => 
                    index === winningNumber ? count + 1 : count
                ),
                currentDrawNumber: drawNumber + 1,
                lastUpdated: timestamp
            };

            await FirebaseService.Analytics.setCurrent(updatedAnalytics);

            // 4. Update draw info
            const drawInfo = {
                currentDraw: drawNumber,
                nextDraw: drawNumber + 1,
                updatedAt: timestamp
            };

            await FirebaseService.GameState.updateDrawInfo(drawInfo);

            console.log('Draw result saved to Firebase:', drawResult);

            return {
                status: 'success',
                message: 'Draw result saved successfully',
                data: {
                    drawId: drawId,
                    drawNumber: drawNumber,
                    winningNumber: winningNumber,
                    winningColor: winningColor,
                    nextDrawNumber: drawNumber + 1
                }
            };

        } catch (error) {
            console.error('Error saving draw result:', error);
            return {
                status: 'error',
                message: 'Failed to save draw result: ' + error.message
            };
        }
    }

    /**
     * Update draw numbers
     * Replaces php/update_draw.php
     */
    async function updateDrawNumbers(currentDraw, nextDraw) {
        if (!initialized) {
            if (!initialize()) {
                return { success: false, message: 'FirebaseDrawManager not initialized' };
            }
        }

        if (currentDraw <= 0 || nextDraw <= 0) {
            return { success: false, message: 'Invalid draw numbers' };
        }

        try {
            const timestamp = new Date().toISOString();

            // Update game state
            const currentState = await FirebaseService.GameState.getCurrent() || {};
            await FirebaseService.GameState.updateCurrent({
                ...currentState,
                drawNumber: currentDraw,
                nextDrawNumber: nextDraw,
                updatedAt: timestamp
            });

            // Update draw info
            await FirebaseService.GameState.updateDrawInfo({
                currentDraw: currentDraw,
                nextDraw: nextDraw,
                updatedAt: timestamp
            });

            // Update analytics
            const analytics = await FirebaseService.Analytics.getCurrent() || {};
            await FirebaseService.Analytics.updateCurrent({
                ...analytics,
                currentDrawNumber: currentDraw,
                lastUpdated: timestamp
            });

            console.log(`Draw numbers updated: Current #${currentDraw}, Next #${nextDraw}`);

            return {
                success: true,
                message: 'Draw numbers updated successfully',
                currentDraw: currentDraw,
                nextDraw: nextDraw
            };

        } catch (error) {
            console.error('Error updating draw numbers:', error);
            return {
                success: false,
                message: 'Failed to update draw numbers: ' + error.message
            };
        }
    }

    /**
     * Get current draw state
     */
    async function getCurrentDrawState() {
        if (!initialized) {
            if (!initialize()) {
                return null;
            }
        }

        try {
            const gameState = await FirebaseService.GameState.getCurrent();
            return gameState;
        } catch (error) {
            console.error('Error getting current draw state:', error);
            return null;
        }
    }

    /**
     * Get draw by number
     */
    async function getDraw(drawNumber) {
        if (!initialized) {
            if (!initialize()) {
                return null;
            }
        }

        try {
            return await FirebaseService.Draws.get(drawNumber);
        } catch (error) {
            console.error('Error getting draw:', error);
            return null;
        }
    }

    /**
     * Listen to current draw state changes
     */
    function listenToCurrentDraw(callback) {
        if (!initialized) {
            if (!initialize()) {
                return null;
            }
        }

        if (currentDrawListener) {
            FirebaseService.unlisten(currentDrawListener);
        }

        currentDrawListener = FirebaseService.GameState.listen((data) => {
            if (callback) {
                callback(data);
            }
        });

        return currentDrawListener;
    }

    /**
     * Listen to draw results
     */
    function listenToDraws(callback) {
        if (!initialized) {
            if (!initialize()) {
                return null;
            }
        }

        if (drawHistoryListener) {
            FirebaseService.unlisten(drawHistoryListener);
        }

        drawHistoryListener = FirebaseService.Draws.listenAll((data) => {
            if (callback) {
                callback(data);
            }
        });

        return drawHistoryListener;
    }

    /**
     * Stop all listeners
     */
    function stopListening() {
        if (currentDrawListener) {
            FirebaseService.unlisten(currentDrawListener);
            currentDrawListener = null;
        }
        if (drawHistoryListener) {
            FirebaseService.unlisten(drawHistoryListener);
            drawHistoryListener = null;
        }
    }

    // Public API
    return {
        initialize,
        saveDrawResult,
        updateDrawNumbers,
        getCurrentDrawState,
        getDraw,
        listenToCurrentDraw,
        listenToDraws,
        stopListening
    };
})();

// Auto-initialize when FirebaseService is ready
function initFirebaseDrawManager() {
    console.log('🔥 FirebaseDrawManager: Attempting to initialize...');
    
    if (window.FirebaseService && FirebaseDrawManager.initialize()) {
        console.log('✅ FirebaseDrawManager initialized successfully');
    } else {
        console.warn('⚠️ FirebaseDrawManager: Waiting for FirebaseService...');
        window.addEventListener('firebase-ready', () => {
            setTimeout(() => {
                if (window.FirebaseService && FirebaseDrawManager.initialize()) {
                    console.log('✅ FirebaseDrawManager initialized successfully');
                }
            }, 200);
        }, { once: true });
    }
}

// Wait a bit for scripts to load
setTimeout(initFirebaseDrawManager, 500);

// Also try when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(initFirebaseDrawManager, 1000);
    });
}

// Export for global use
window.FirebaseDrawManager = FirebaseDrawManager;

