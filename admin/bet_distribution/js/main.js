/**
 * Main Module
 * Initialization, global state, event delegation, and module coordination
 */

// Global state
const AppState = {
    initialized: false,
    currentDraw: null,
    modules: {
        betDistribution: null,
        drawSelection: null,
        drawControl: null,
        presetSchedule: null,
        forcedNumbers: null,
        numberAnalytics: null,
        slipAnalytics: null
    }
};

/**
 * Initialize application
 */
async function initApp() {
    if (AppState.initialized) {
        console.warn('⚠️ App already initialized');
        return;
    }

    console.log('🚀 Initializing Bet Distribution Application...');

    try {
        // Show JavaScript test indicator
        const jsIndicator = Utils.$('#jsTestIndicator');
        if (jsIndicator) {
            jsIndicator.style.display = 'block';
            setTimeout(() => {
                jsIndicator.style.display = 'none';
            }, 3000);
        }

        // Initialize modules with error handling
        try {
            if (betDistribution) betDistribution.init();
        } catch (e) {
            console.error('Error initializing betDistribution:', e);
        }
        
        try {
            if (drawSelection) drawSelection.init();
        } catch (e) {
            console.error('Error initializing drawSelection:', e);
        }
        
        try {
            if (drawControl) drawControl.init();
        } catch (e) {
            console.error('Error initializing drawControl:', e);
        }
        
        try {
            if (presetSchedule) presetSchedule.init();
        } catch (e) {
            console.error('Error initializing presetSchedule:', e);
        }
        
        try {
            if (forcedNumbers) forcedNumbers.init();
        } catch (e) {
            console.error('Error initializing forcedNumbers:', e);
        }
        
        try {
            if (numberAnalytics) numberAnalytics.init();
        } catch (e) {
            console.error('Error initializing numberAnalytics:', e);
        }
        
        try {
            if (slipAnalytics) slipAnalytics.init();
        } catch (e) {
            console.error('Error initializing slipAnalytics:', e);
        }

        // Store module references
        AppState.modules.betDistribution = betDistribution;
        AppState.modules.drawSelection = drawSelection;
        AppState.modules.drawControl = drawControl;
        AppState.modules.presetSchedule = presetSchedule;
        AppState.modules.forcedNumbers = forcedNumbers;
        AppState.modules.numberAnalytics = numberAnalytics;
        AppState.modules.slipAnalytics = slipAnalytics;

        // Load initial data
        await loadInitialData();
        
        // Load slip analytics for current draw
        try {
            if (slipAnalytics && AppState.currentDraw) {
                await slipAnalytics.loadSlipAnalytics(AppState.currentDraw);
            }
        } catch (e) {
            console.warn('⚠️ Could not load initial slip analytics:', e);
        }

        // Setup event listeners
        setupEventListeners();

        // Start auto-refresh
        startAutoRefresh();

        AppState.initialized = true;
        console.log('✅ Application initialized successfully');
    } catch (error) {
        console.error('❌ Error initializing application:', error);
        Utils.showError(Utils.$('#chartContainer'), `Failed to initialize: ${error.message}`);
    }
}

/**
 * Load initial data
 */
async function loadInitialData() {
    try {
        console.log('📥 Loading initial data...');
        
        // Load upcoming draws
        if (drawSelection && typeof drawSelection.loadUpcomingDraws === 'function') {
            await drawSelection.loadUpcomingDraws(10);
        } else {
            console.error('drawSelection.loadUpcomingDraws is not available');
        }
        
        // Load draw info
        if (drawControl && typeof drawControl.loadDrawInfo === 'function') {
            await drawControl.loadDrawInfo();
        } else {
            console.error('drawControl.loadDrawInfo is not available');
        }
        
        console.log('✅ Initial data loaded');
    } catch (error) {
        console.error('❌ Error loading initial data:', error);
        console.error('Error stack:', error.stack);
    }
}

/**
 * Setup event listeners
 */
function setupEventListeners() {
    // Refresh button
    const refreshBtn = Utils.$('#refreshButton');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', async () => {
            console.log('🔄 Manual refresh triggered');
            await loadInitialData();
        });
    }

    // Draw control toggle button
    const toggleDrawControlBtn = Utils.$('#toggleDrawControlButton');
    const drawControlSection = Utils.$('#drawControlSection');
    const toggleDrawControlText = Utils.$('#toggleDrawControlText');
    
    if (toggleDrawControlBtn && drawControlSection) {
        toggleDrawControlBtn.addEventListener('click', () => {
            const isVisible = drawControlSection.style.display !== 'none';
            drawControlSection.style.display = isVisible ? 'none' : 'block';
            if (toggleDrawControlText) {
                toggleDrawControlText.textContent = isVisible ? 'Show Draw Control' : 'Hide Draw Control';
            }
        });
    }

    // Generate preset schedule button
    const generatePresetBtn = Utils.$('#generatePresetScheduleBtn');
    if (generatePresetBtn) {
        generatePresetBtn.addEventListener('click', async () => {
            try {
                await presetSchedule.generateSchedule('auto', 'smart');
            } catch (error) {
                console.error('Error generating preset schedule:', error);
                alert(`Failed to generate schedule: ${error.message}`);
            }
        });
    }

    // Draw selection event
    window.addEventListener('drawSelected', async (event) => {
        const { draw } = event.detail;
        console.log('🎯 Draw selected:', draw);
        
        // Load bet distribution for selected draw
        try {
            await betDistribution.loadBetDistribution(draw.draw_number);
        } catch (error) {
            console.error('Error loading bet distribution:', error);
        }
    });

    // Handle window visibility change
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            console.log('📴 Page hidden, pausing auto-refresh');
            stopAutoRefresh();
        } else {
            console.log('📱 Page visible, resuming auto-refresh');
            startAutoRefresh();
            loadInitialData();
        }
    });
}

/**
 * Start auto-refresh
 */
function startAutoRefresh() {
    // Refresh draw selection every 15 seconds
    drawSelection.startAutoRefresh(15000);
    
    // Refresh draw info every 5 seconds
    drawControl.startDrawInfoRefresh(5000);
    
    // Start forced number check if auto-apply is enabled
    if (forcedNumbers.autoApply) {
        forcedNumbers.startAutoCheck(30000);
    }
}

/**
 * Stop auto-refresh
 */
function stopAutoRefresh() {
    drawSelection.stopAutoRefresh();
    drawControl.stopDrawInfoRefresh();
    forcedNumbers.stopAutoCheck();
}

/**
 * Handle page unload
 */
window.addEventListener('beforeunload', () => {
    stopAutoRefresh();
});

// Immediate test
console.log('🚀 main.js loaded');
console.log('📦 Checking modules:', {
    Utils: typeof Utils,
    apiClient: typeof apiClient,
    betDistribution: typeof betDistribution,
    drawSelection: typeof drawSelection,
    drawControl: typeof drawControl,
    presetSchedule: typeof presetSchedule,
    forcedNumbers: typeof forcedNumbers
});

// Initialize when DOM is ready
function tryInit() {
    if (typeof Utils === 'undefined' || 
        typeof apiClient === 'undefined' ||
        typeof betDistribution === 'undefined' || 
        typeof drawSelection === 'undefined' || 
        typeof drawControl === 'undefined' || 
        typeof presetSchedule === 'undefined' || 
        typeof forcedNumbers === 'undefined') {
        console.warn('⚠️ Modules not ready, retrying...');
        setTimeout(tryInit, 100);
        return;
    }
    
    console.log('✅ All modules ready, initializing app...');
    initApp();
}

// Wait for DOM and all scripts
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(tryInit, 200);
    });
} else {
    setTimeout(tryInit, 200);
}

