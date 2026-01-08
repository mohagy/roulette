/**
 * Tab Visibility Manager
 * 
 * Prevents draw number skipping caused by catch-up processing when returning
 * to an idle TV display tab. Coordinates all visibility-related events to
 * prevent race conditions.
 */

const TabVisibilityManager = (function() {
    
    // Configuration
    const config = {
        catchUpDelay: 2000,        // Delay before processing catch-up events
        maxCatchUpTime: 300000,    // Max time (5 minutes) before considering tab "stale"
        debounceTime: 1000,        // Debounce time for visibility events
        debug: true                // Enable debug logging
    };
    
    // State tracking
    let state = {
        isVisible: !document.hidden,
        lastVisibleTime: Date.now(),
        lastHiddenTime: null,
        catchUpInProgress: false,
        visibilityChangeTimeout: null,
        registeredHandlers: new Map()
    };
    
    /**
     * Log debug messages
     */
    function log(message, data = null) {
        if (config.debug) {
            const timestamp = new Date().toISOString();
            console.log(`[${timestamp}] [TabVisibilityManager] ${message}`, data || '');
        }
    }
    
    /**
     * Register a handler for visibility changes
     * @param {string} name - Unique name for the handler
     * @param {Object} handlers - Object with onVisible and onHidden functions
     */
    function registerHandler(name, handlers) {
        if (!handlers.onVisible && !handlers.onHidden) {
            throw new Error('Handler must have at least onVisible or onHidden function');
        }
        
        state.registeredHandlers.set(name, {
            onVisible: handlers.onVisible || (() => {}),
            onHidden: handlers.onHidden || (() => {}),
            priority: handlers.priority || 0,
            allowCatchUp: handlers.allowCatchUp !== false // Default to true
        });
        
        log(`Registered handler: ${name}`, handlers);
    }
    
    /**
     * Unregister a handler
     */
    function unregisterHandler(name) {
        if (state.registeredHandlers.delete(name)) {
            log(`Unregistered handler: ${name}`);
        }
    }
    
    /**
     * Handle tab becoming visible
     */
    function handleTabVisible() {
        const now = Date.now();
        const hiddenDuration = state.lastHiddenTime ? now - state.lastHiddenTime : 0;
        
        log(`Tab became visible after ${hiddenDuration}ms hidden`);
        
        state.isVisible = true;
        state.lastVisibleTime = now;
        state.lastHiddenTime = null;
        
        // Determine if this is a "catch-up" scenario
        const needsCatchUp = hiddenDuration > config.maxCatchUpTime;
        
        if (needsCatchUp && !state.catchUpInProgress) {
            log(`Initiating catch-up process (hidden for ${hiddenDuration}ms)`);
            handleCatchUpScenario(hiddenDuration);
        } else {
            // Normal visibility change - execute handlers immediately
            executeVisibilityHandlers('onVisible', { 
                hiddenDuration, 
                needsCatchUp: false 
            });
        }
    }
    
    /**
     * Handle tab becoming hidden
     */
    function handleTabHidden() {
        const now = Date.now();
        const visibleDuration = now - state.lastVisibleTime;
        
        log(`Tab became hidden after ${visibleDuration}ms visible`);
        
        state.isVisible = false;
        state.lastHiddenTime = now;
        
        // Execute hidden handlers immediately
        executeVisibilityHandlers('onHidden', { visibleDuration });
    }
    
    /**
     * Handle catch-up scenario when tab was hidden for a long time
     */
    function handleCatchUpScenario(hiddenDuration) {
        if (state.catchUpInProgress) {
            log('Catch-up already in progress, ignoring');
            return;
        }
        
        state.catchUpInProgress = true;
        
        // First, execute non-catch-up handlers immediately
        executeVisibilityHandlers('onVisible', { 
            hiddenDuration, 
            needsCatchUp: true,
            phase: 'immediate'
        }, false); // Don't allow catch-up handlers
        
        // Then, after a delay, execute catch-up handlers one by one
        setTimeout(() => {
            log('Executing catch-up handlers');
            
            executeCatchUpHandlers(hiddenDuration).then(() => {
                state.catchUpInProgress = false;
                log('Catch-up process completed');
            }).catch(error => {
                log('Catch-up process failed:', error);
                state.catchUpInProgress = false;
            });
            
        }, config.catchUpDelay);
    }
    
    /**
     * Execute catch-up handlers sequentially to prevent race conditions
     */
    async function executeCatchUpHandlers(hiddenDuration) {
        // Get handlers that allow catch-up, sorted by priority
        const catchUpHandlers = Array.from(state.registeredHandlers.entries())
            .filter(([name, handler]) => handler.allowCatchUp)
            .sort(([, a], [, b]) => (b.priority || 0) - (a.priority || 0));
        
        for (const [name, handler] of catchUpHandlers) {
            try {
                log(`Executing catch-up handler: ${name}`);
                
                const result = handler.onVisible({
                    hiddenDuration,
                    needsCatchUp: true,
                    phase: 'catchup'
                });
                
                // If handler returns a promise, wait for it
                if (result && typeof result.then === 'function') {
                    await result;
                }
                
                // Small delay between handlers to prevent race conditions
                await new Promise(resolve => setTimeout(resolve, 100));
                
            } catch (error) {
                log(`Error in catch-up handler ${name}:`, error);
            }
        }
    }
    
    /**
     * Execute visibility handlers
     */
    function executeVisibilityHandlers(type, eventData, allowCatchUpHandlers = true) {
        // Get handlers sorted by priority
        const handlers = Array.from(state.registeredHandlers.entries())
            .filter(([name, handler]) => {
                // Filter out catch-up handlers if not allowed
                if (!allowCatchUpHandlers && handler.allowCatchUp) {
                    return false;
                }
                return true;
            })
            .sort(([, a], [, b]) => (b.priority || 0) - (a.priority || 0));
        
        for (const [name, handler] of handlers) {
            try {
                log(`Executing ${type} handler: ${name}`);
                handler[type](eventData);
            } catch (error) {
                log(`Error in ${type} handler ${name}:`, error);
            }
        }
    }
    
    /**
     * Debounced visibility change handler
     */
    function handleVisibilityChange() {
        // Clear any pending timeout
        if (state.visibilityChangeTimeout) {
            clearTimeout(state.visibilityChangeTimeout);
        }
        
        // Debounce the visibility change to prevent rapid fire events
        state.visibilityChangeTimeout = setTimeout(() => {
            if (document.hidden) {
                handleTabHidden();
            } else {
                handleTabVisible();
            }
        }, config.debounceTime);
    }
    
    /**
     * Initialize the tab visibility manager
     */
    function init() {
        log('Initializing Tab Visibility Manager');
        
        // Set initial state
        state.isVisible = !document.hidden;
        state.lastVisibleTime = Date.now();
        
        // Listen for visibility changes
        document.addEventListener('visibilitychange', handleVisibilityChange);
        
        // Register default handlers for existing systems
        registerDefaultHandlers();
        
        log('Tab Visibility Manager initialized');
    }
    
    /**
     * Register default handlers for existing systems
     */
    function registerDefaultHandlers() {
        // AJAX Updates Handler
        registerHandler('ajaxUpdates', {
            priority: 10,
            allowCatchUp: false, // Don't catch up AJAX - just resume normal polling
            onVisible: (eventData) => {
                if (typeof window.startAjaxUpdates === 'function') {
                    log('Resuming AJAX updates');
                    window.startAjaxUpdates();
                }
            },
            onHidden: (eventData) => {
                if (typeof window.ajaxUpdateInterval !== 'undefined' && window.ajaxUpdateInterval) {
                    log('Stopping AJAX updates');
                    clearInterval(window.ajaxUpdateInterval);
                }
            }
        });
        
        // Data Persistence Handler
        registerHandler('dataPersistence', {
            priority: 20,
            allowCatchUp: true, // Allow catch-up for data persistence
            onVisible: (eventData) => {
                if (eventData.needsCatchUp && window.DataPersistence) {
                    log('Reloading data after long absence');
                    return window.DataPersistence.loadDataFromDatabase();
                }
            }
        });
        
        // Draw Sync Handler
        registerHandler('drawSync', {
            priority: 30,
            allowCatchUp: false, // Don't catch up draw sync - use centralized manager
            onVisible: (eventData) => {
                if (window.DrawSync && !eventData.needsCatchUp) {
                    log('Resuming draw sync');
                    return window.DrawSync.fetchDrawInfo().then(() => {
                        window.DrawSync.startPolling();
                    });
                }
            },
            onHidden: (eventData) => {
                if (window.DrawSync) {
                    log('Pausing draw sync');
                    window.DrawSync.stopPolling();
                }
            }
        });
    }
    
    /**
     * Get current state
     */
    function getState() {
        return { ...state };
    }
    
    /**
     * Check if tab is currently visible
     */
    function isVisible() {
        return state.isVisible;
    }
    
    /**
     * Check if catch-up is in progress
     */
    function isCatchUpInProgress() {
        return state.catchUpInProgress;
    }
    
    // Public API
    return {
        init,
        registerHandler,
        unregisterHandler,
        getState,
        isVisible,
        isCatchUpInProgress
    };
})();

// Auto-initialize when script loads
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', TabVisibilityManager.init);
} else {
    TabVisibilityManager.init();
}

// Make globally available
window.TabVisibilityManager = TabVisibilityManager;
