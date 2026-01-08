/**
 * TV Display Data Persistence System
 *
 * This script handles loading and restoring data from the database
 * when the TV display page loads, ensuring continuity of display data.
 */

console.log("🔄 DATA PERSISTENCE: Loading TV Display Data Persistence System");

// Data persistence configuration
const DataPersistence = {
    // Configuration
    config: {
        maxSpinsToRestore: 100,  // Maximum number of spins to restore from database
        retryAttempts: 3,        // Number of retry attempts for failed loads
        retryDelay: 2000,        // Delay between retry attempts (ms)
        debugMode: true          // Enable debug logging
    },

    // State tracking
    state: {
        isLoading: false,
        isLoaded: false,
        loadAttempts: 0,
        lastLoadTime: null
    },

    /**
     * Initialize data persistence system
     */
    async init() {
        console.log("🔄 DATA PERSISTENCE: Initializing data persistence system");

        try {
            // STEP 1: AGGRESSIVE CACHE CLEARING
            console.log("🧹 DATA PERSISTENCE: Clearing all caches");
            this.clearAllCaches();

            // STEP 2: IMMEDIATE LOAD: Try to load data right away
            console.log("🚀 DATA PERSISTENCE: Attempting immediate load");
            await this.loadDataFromDatabase();

            // STEP 3: Multiple aggressive retries to ensure success
            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", () => {
                    setTimeout(() => this.aggressiveReload(), 100);
                    setTimeout(() => this.aggressiveReload(), 500);
                    setTimeout(() => this.aggressiveReload(), 1000);
                    setTimeout(() => this.aggressiveReload(), 2000);
                });
            } else {
                setTimeout(() => this.aggressiveReload(), 100);
                setTimeout(() => this.aggressiveReload(), 500);
                setTimeout(() => this.aggressiveReload(), 1000);
                setTimeout(() => this.aggressiveReload(), 2000);
            }

        } catch (error) {
            console.error("❌ DATA PERSISTENCE: Failed to initialize", error);
        }
    },

    /**
     * Clear all caches that might contain stale data
     */
    clearAllCaches() {
        console.log("🧹 CACHE CLEAR: Starting comprehensive cache clearing");

        try {
            // Clear localStorage keys related to recent numbers
            const keysToRemove = [
                'rolledNumbersArray',
                'rolledNumbersColorArray',
                'allSpins',
                'numberFrequency',
                'currentDrawNumber',
                'lastRollHistory',
                'gameState',
                'rouletteData',
                'recentNumbers',
                'lastSpins'
            ];

            keysToRemove.forEach(key => {
                if (localStorage.getItem(key)) {
                    console.log(`🧹 CACHE CLEAR: Removing localStorage key: ${key}`);
                    localStorage.removeItem(key);
                }
            });

            // Clear sessionStorage as well
            keysToRemove.forEach(key => {
                if (sessionStorage.getItem(key)) {
                    console.log(`🧹 CACHE CLEAR: Removing sessionStorage key: ${key}`);
                    sessionStorage.removeItem(key);
                }
            });

            // Clear any existing global variables that might contain stale data
            if (window.rolledNumbersArray) {
                console.log("🧹 CACHE CLEAR: Clearing window.rolledNumbersArray");
                window.rolledNumbersArray = null;
            }
            if (window.rolledNumbersColorArray) {
                console.log("🧹 CACHE CLEAR: Clearing window.rolledNumbersColorArray");
                window.rolledNumbersColorArray = null;
            }

            // Clear DOM elements to remove any cached display
            console.log("🧹 CACHE CLEAR: Clearing DOM elements");
            for (let i = 1; i <= 5; i++) {
                const element = document.querySelector(`.roll${i}`);
                if (element) {
                    element.innerHTML = '';
                    element.className = element.className.replace(/roll-(red|black|green)/g, '');
                    console.log(`🧹 CACHE CLEAR: Cleared .roll${i}`);
                }
            }

            console.log("✅ CACHE CLEAR: All caches cleared successfully");

        } catch (error) {
            console.error("❌ CACHE CLEAR: Error clearing caches", error);
        }
    },

    /**
     * Aggressive reload with cache busting
     */
    async aggressiveReload() {
        console.log("💥 AGGRESSIVE RELOAD: Starting");

        // Clear caches again
        this.clearAllCaches();

        // Force reload from database
        await this.loadDataFromDatabase();

        // Force DOM update
        setTimeout(() => {
            this.superAggressiveUpdate();
        }, 100);
    },

    /**
     * Load data from database and restore local data structures
     */
    async loadDataFromDatabase() {
        if (this.state.isLoading) {
            console.log("⏳ DATA PERSISTENCE: Load already in progress, skipping");
            return;
        }

        this.state.isLoading = true;
        this.state.loadAttempts++;

        console.log(`🔄 DATA PERSISTENCE: Loading data from database (attempt ${this.state.loadAttempts})`);

        try {
            // Load analytics data from database
            const analyticsData = await this.fetchAnalyticsData();

            if (analyticsData && analyticsData.status === "success") {
                console.log("✅ DATA PERSISTENCE: Analytics data loaded successfully", analyticsData);

                // Restore local data structures
                await this.restoreLocalData(analyticsData);

                // Update displays
                this.updateDisplays();

                this.state.isLoaded = true;
                this.state.lastLoadTime = new Date();

                console.log("✅ DATA PERSISTENCE: Data restoration completed successfully");

                // Trigger custom event for other components
                document.dispatchEvent(new CustomEvent("dataRestorationComplete", {
                    detail: {
                        analyticsData,
                        timestamp: this.state.lastLoadTime,
                        spinsRestored: window.allSpins ? window.allSpins.length : 0
                    }
                }));

                // Additional manual trigger for recent numbers display after a longer delay
                setTimeout(() => {
                    console.log("🔄 DATA PERSISTENCE: Manual trigger for recent numbers display");
                    if (typeof window.displayRollHistory === "function") {
                        window.displayRollHistory();
                        console.log("✅ DATA PERSISTENCE: Manual displayRollHistory triggered");
                    }
                    if (typeof window.updateRecentNumbers === "function") {
                        window.updateRecentNumbers();
                        console.log("✅ DATA PERSISTENCE: Manual updateRecentNumbers triggered");
                    }

                    // NUCLEAR OPTION: Direct DOM manipulation if functions don't work
                    this.forceUpdateRecentNumbersDOM();
                }, 500); // Wait 500ms for everything to be ready

                // Additional fallback after even longer delay
                setTimeout(() => {
                    console.log("🔄 DATA PERSISTENCE: Final fallback trigger");
                    this.forceUpdateRecentNumbersDOM();
                }, 2000); // Wait 2 seconds for everything to be ready

                // SUPER AGGRESSIVE: Multiple attempts to force update
                setTimeout(() => {
                    console.log("💥 DATA PERSISTENCE: SUPER AGGRESSIVE UPDATE");
                    this.superAggressiveUpdate();
                }, 3000);

                setTimeout(() => {
                    console.log("💥 DATA PERSISTENCE: ULTRA AGGRESSIVE UPDATE");
                    this.superAggressiveUpdate();
                }, 6000);

            } else {
                throw new Error("Failed to load analytics data: " + (analyticsData?.message || "Unknown error"));
            }

        } catch (error) {
            console.error("❌ DATA PERSISTENCE: Failed to load data", error);

            // Retry if we haven't exceeded max attempts
            if (this.state.loadAttempts < this.config.retryAttempts) {
                console.log(`🔄 DATA PERSISTENCE: Retrying in ${this.config.retryDelay}ms...`);
                setTimeout(() => {
                    this.state.isLoading = false;
                    this.loadDataFromDatabase();
                }, this.config.retryDelay);
            } else {
                console.error("❌ DATA PERSISTENCE: Max retry attempts exceeded, using empty data");
                this.initializeEmptyData();
            }

        } finally {
            this.state.isLoading = false;
        }
    },

    /**
     * Fetch analytics data from the server
     */
    async fetchAnalyticsData() {
        console.log("📡 DATA PERSISTENCE: Fetching analytics data from server with AGGRESSIVE cache busting");

        try {
            // SUPER AGGRESSIVE cache busting
            const timestamp = Date.now();
            const random = Math.random().toString(36).substring(2);
            const cacheBuster = `${timestamp}_${random}_${Math.floor(Math.random() * 1000000)}`;

            // Determine the correct path based on current location
            let apiPath = '../load_analytics.php';
            if (window.location.pathname.includes('/tvdisplay/')) {
                apiPath = '../load_analytics.php';
            } else {
                // We're in the root directory or elsewhere
                apiPath = 'load_analytics.php';
            }

            console.log(`📡 DATA PERSISTENCE: Using API path: ${apiPath} with cache buster: ${cacheBuster}`);

            const response = await fetch(`${apiPath}?v=${cacheBuster}&nocache=${timestamp}&force=1&fresh=${random}`, {
                method: "GET",
                headers: {
                    "Cache-Control": "no-cache, no-store, must-revalidate, max-age=0",
                    "Pragma": "no-cache",
                    "Expires": "0",
                    "If-Modified-Since": "Thu, 01 Jan 1970 00:00:00 GMT",
                    "If-None-Match": "*"
                },
                cache: "no-store"
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            console.log("📡 DATA PERSISTENCE: Fresh server response received", data);

            return data;

        } catch (error) {
            console.error("❌ DATA PERSISTENCE: Failed to fetch analytics data", error);
            throw error;
        }
    },

    /**
     * Restore local data structures from database data
     */
    async restoreLocalData(analyticsData) {
        console.log("🔧 DATA PERSISTENCE: Restoring local data structures");

        try {
            // Parse all_spins JSON data
            let allSpinsFromDB = [];
            if (analyticsData.all_spins) {
                try {
                    allSpinsFromDB = JSON.parse(analyticsData.all_spins);
                    if (!Array.isArray(allSpinsFromDB)) {
                        allSpinsFromDB = [];
                    }
                } catch (parseError) {
                    console.warn("⚠️ DATA PERSISTENCE: Failed to parse all_spins JSON", parseError);
                    allSpinsFromDB = [];
                }
            }

            console.log(`📊 DATA PERSISTENCE: Parsed ${allSpinsFromDB.length} spins from database`);

            // Restore allSpins array
            window.allSpins = allSpinsFromDB.slice(0, this.config.maxSpinsToRestore);
            console.log(`✅ DATA PERSISTENCE: Restored allSpins with ${window.allSpins.length} items`);

            // Restore currentDrawNumber
            window.currentDrawNumber = parseInt(analyticsData.current_draw_number) || 0;
            console.log(`✅ DATA PERSISTENCE: Restored currentDrawNumber to ${window.currentDrawNumber}`);

            // Rebuild numberFrequency array from allSpins data
            this.rebuildNumberFrequency();

            // CRITICAL: Restore rolledNumbersArray and rolledNumbersColorArray for the recent numbers display
            // These arrays are what the displayRollHistory() function uses to show the last 5 spins

            // First try to restore from saved state data
            if (analyticsData.rolled_numbers_array) {
                try {
                    window.rolledNumbersArray = JSON.parse(analyticsData.rolled_numbers_array) || [];
                    console.log(`✅ DATA PERSISTENCE: Restored rolledNumbersArray from saved state with ${window.rolledNumbersArray.length} items`);
                } catch (e) {
                    console.warn("⚠️ DATA PERSISTENCE: Failed to parse rolled_numbers_array, generating from allSpins");
                    window.rolledNumbersArray = [];
                }
            }

            if (analyticsData.rolled_numbers_color_array) {
                try {
                    window.rolledNumbersColorArray = JSON.parse(analyticsData.rolled_numbers_color_array) || [];
                    console.log(`✅ DATA PERSISTENCE: Restored rolledNumbersColorArray from saved state with ${window.rolledNumbersColorArray.length} items`);
                } catch (e) {
                    console.warn("⚠️ DATA PERSISTENCE: Failed to parse rolled_numbers_color_array, generating from numbers");
                    window.rolledNumbersColorArray = [];
                }
            }

            // CRITICAL FIX: Always update with fresh database data on page load
            // Only preserve existing data if we're in the middle of active gameplay

            // Check if we're in active gameplay (wheel is spinning or just finished)
            const isActiveGameplay = window.isWheelSpinning ||
                                   (window.lastSpinTime && (Date.now() - window.lastSpinTime) < 10000); // Within 10 seconds of last spin

            console.log(`🔍 DATA PERSISTENCE: Active gameplay check - isActiveGameplay: ${isActiveGameplay}`);
            console.log(`🔍 DATA PERSISTENCE: Current arrays - Numbers: ${window.rolledNumbersArray}, Colors: ${window.rolledNumbersColorArray}`);

            if (isActiveGameplay) {
                console.log(`⚠️ DATA PERSISTENCE: Active gameplay detected, preserving existing arrays`);
                // During active gameplay, preserve existing data
                if (!Array.isArray(window.rolledNumbersArray) || window.rolledNumbersArray.length === 0) {
                    window.rolledNumbersArray = window.allSpins.slice(0, 5);
                    console.log(`✅ DATA PERSISTENCE: Initialized rolledNumbersArray during gameplay`);
                }
            } else {
                console.log(`🔄 DATA PERSISTENCE: Page load detected, FORCING fresh database data`);
                // On page load/refresh, ALWAYS use fresh database data - OVERRIDE EVERYTHING
                window.rolledNumbersArray = window.allSpins.slice(0, 5);
                console.log(`✅ DATA PERSISTENCE: FORCED rolledNumbersArray with fresh database data:`, window.rolledNumbersArray);

                // IMMEDIATELY update DOM to override any cached display
                this.immediateForceUpdate();
            }

            // Always regenerate colors to match the numbers
            const rouletteNumbersRed = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
            window.rolledNumbersColorArray = window.rolledNumbersArray.map(number => {
                const num = parseInt(number);
                if (num === 0) return 'green';
                return rouletteNumbersRed.includes(num) ? 'red' : 'black';
            });
            console.log(`✅ DATA PERSISTENCE: Generated rolledNumbersColorArray:`, window.rolledNumbersColorArray);

            // Ensure both arrays have the same length and are limited to 5 items
            const maxItems = Math.min(5, window.rolledNumbersArray.length);
            window.rolledNumbersArray = window.rolledNumbersArray.slice(0, maxItems);
            window.rolledNumbersColorArray = window.rolledNumbersColorArray.slice(0, maxItems);

            console.log("📊 DATA PERSISTENCE: Final arrays for display:");
            console.log("  rolledNumbersArray:", window.rolledNumbersArray);
            console.log("  rolledNumbersColorArray:", window.rolledNumbersColorArray);

            console.log("✅ DATA PERSISTENCE: Local data structures restored successfully");

        } catch (error) {
            console.error("❌ DATA PERSISTENCE: Failed to restore local data", error);
            throw error;
        }
    },

    /**
     * Rebuild numberFrequency array from allSpins data
     */
    rebuildNumberFrequency() {
        console.log("🔢 DATA PERSISTENCE: Rebuilding numberFrequency array");

        // Initialize frequency array (0-36)
        window.numberFrequency = new Array(37).fill(0);

        // Count frequencies from allSpins
        if (window.allSpins && Array.isArray(window.allSpins)) {
            window.allSpins.forEach(number => {
                const num = parseInt(number);
                if (num >= 0 && num <= 36) {
                    window.numberFrequency[num]++;
                }
            });
        }

        console.log("✅ DATA PERSISTENCE: numberFrequency array rebuilt");

        if (this.config.debugMode) {
            // Log frequency summary
            const totalSpins = window.numberFrequency.reduce((sum, freq) => sum + freq, 0);
            console.log(`📊 DATA PERSISTENCE: Frequency summary - Total spins: ${totalSpins}`);
        }
    },

    /**
     * Update all display elements with restored data
     */
    updateDisplays() {
        console.log("🖥️ DATA PERSISTENCE: Updating displays with restored data");
        console.log("🖥️ DATA PERSISTENCE: Available functions check:");
        console.log("  updateAnalytics:", typeof window.updateAnalytics);
        console.log("  updateDrawNumberDisplay:", typeof window.updateDrawNumberDisplay);
        console.log("  updateNumberHistory:", typeof window.updateNumberHistory);
        console.log("  updateRecentNumbers:", typeof window.updateRecentNumbers);
        console.log("  displayRollHistory:", typeof window.displayRollHistory);

        try {
            // Use setTimeout to ensure DOM is ready and other scripts have loaded
            setTimeout(() => {
                console.log("🖥️ DATA PERSISTENCE: Starting delayed display updates");

                // Update analytics display
                if (typeof window.updateAnalytics === "function") {
                    window.updateAnalytics();
                    console.log("✅ DATA PERSISTENCE: Analytics display updated");
                } else {
                    console.warn("⚠️ DATA PERSISTENCE: updateAnalytics function not available");
                }

                // Update draw number display
                if (typeof window.updateDrawNumberDisplay === "function") {
                    window.updateDrawNumberDisplay();
                    console.log("✅ DATA PERSISTENCE: Draw number display updated");
                } else if (typeof updateDrawNumberDisplay === "function") {
                    updateDrawNumberDisplay();
                    console.log("✅ DATA PERSISTENCE: Draw number display updated (global function)");
                } else {
                    console.warn("⚠️ DATA PERSISTENCE: updateDrawNumberDisplay function not available");
                }

                // Update number history (but check coordination flag)
                if (typeof window.updateNumberHistory === "function") {
                    // Check if another update is in progress to prevent duplicates
                    if (window.recentNumbersUpdateInProgress) {
                        console.log("✅ DATA PERSISTENCE: Number history update skipped due to coordination flag");
                    } else {
                        window.updateNumberHistory();
                        console.log("✅ DATA PERSISTENCE: Number history updated");
                    }
                } else {
                    console.warn("⚠️ DATA PERSISTENCE: updateNumberHistory function not available");
                }

                // CRITICAL: Update recent numbers (roll history display) - this is the main issue
                console.log("🎯 DATA PERSISTENCE: Updating recent numbers display (CRITICAL)");
                if (typeof window.updateRecentNumbers === "function") {
                    window.updateRecentNumbers();
                    console.log("✅ DATA PERSISTENCE: Recent numbers updated via updateRecentNumbers");
                } else if (typeof window.displayRollHistory === "function") {
                    // Fallback to existing displayRollHistory function
                    console.log("🔄 DATA PERSISTENCE: Using displayRollHistory fallback");
                    window.displayRollHistory();
                    console.log("✅ DATA PERSISTENCE: Roll history updated (fallback)");
                } else {
                    console.error("❌ DATA PERSISTENCE: No function available to update recent numbers!");
                }

                // Force refresh of any other display elements
                this.forceDisplayRefresh();

                console.log("✅ DATA PERSISTENCE: All displays updated successfully");

            }, 200); // Wait 200ms to ensure DOM and other scripts are ready

        } catch (error) {
            console.warn("⚠️ DATA PERSISTENCE: Error updating displays", error);
        }
    },

    /**
     * Force refresh of display elements
     */
    forceDisplayRefresh() {
        try {
            // Trigger any custom refresh events
            document.dispatchEvent(new CustomEvent("forceDisplayRefresh"));

            // Update specific display elements if they exist
            const displayElements = [
                "#last-draw-number",
                "#next-draw-number",
                ".analytics-panel",
                ".recent-numbers"
            ];

            displayElements.forEach(selector => {
                const element = document.querySelector(selector);
                if (element) {
                    // Trigger a refresh by dispatching an event
                    element.dispatchEvent(new Event("refresh"));
                }
            });

        } catch (error) {
            console.warn("⚠️ DATA PERSISTENCE: Error in force display refresh", error);
        }
    },

    /**
     * Initialize empty data if database load fails
     */
    initializeEmptyData() {
        console.log("🔧 DATA PERSISTENCE: Initializing empty data structures");

        window.allSpins = [];
        window.numberFrequency = new Array(37).fill(0);
        window.currentDrawNumber = 0;
        window.rolledNumbersArray = [];
        window.rolledNumbersColorArray = [];

        // Ensure global variables are properly initialized
        if (typeof window.maxSpinsToStore === 'undefined') {
            window.maxSpinsToStore = 100;
        }

        console.log("✅ DATA PERSISTENCE: Empty data structures initialized");

        // Update displays with empty data
        this.updateDisplays();
    },

    /**
     * NUCLEAR OPTION: Force update recent numbers DOM directly
     */
    forceUpdateRecentNumbersDOM() {
        console.log("💥 DATA PERSISTENCE: NUCLEAR OPTION - Direct DOM manipulation");

        try {
            // Get the data to display
            const numbersToShow = window.rolledNumbersArray || window.allSpins || [];
            const colorsToShow = window.rolledNumbersColorArray || [];

            console.log("💥 DATA PERSISTENCE: Numbers to force display:", numbersToShow);
            console.log("💥 DATA PERSISTENCE: Colors to force display:", colorsToShow);

            // Define red numbers for color calculation
            const rouletteNumbersRed = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];

            // Clear and update each roll element
            for (let i = 1; i <= 5; i++) {
                const element = document.querySelector(`.roll${i}`);
                if (element) {
                    // Clear existing content and classes
                    element.innerHTML = '';
                    element.classList.remove("roll-red", "roll-black", "roll-green");

                    // Check if we have a number to display
                    const arrayIndex = i - 1;
                    if (arrayIndex < numbersToShow.length && numbersToShow[arrayIndex] !== undefined && numbersToShow[arrayIndex] !== null) {
                        const number = numbersToShow[arrayIndex];
                        element.innerHTML = number;

                        // Determine color
                        let colorClass = 'green';
                        if (arrayIndex < colorsToShow.length && colorsToShow[arrayIndex]) {
                            colorClass = colorsToShow[arrayIndex];
                        } else {
                            // Calculate color from number
                            if (number === 0) {
                                colorClass = 'green';
                            } else if (rouletteNumbersRed.includes(parseInt(number))) {
                                colorClass = 'red';
                            } else {
                                colorClass = 'black';
                            }
                        }

                        element.classList.add(`roll-${colorClass}`);
                        console.log(`💥 DATA PERSISTENCE: Force set .roll${i} to ${number} with color ${colorClass}`);
                    } else {
                        console.log(`💥 DATA PERSISTENCE: No number available for .roll${i}`);
                    }
                } else {
                    console.warn(`💥 DATA PERSISTENCE: Element .roll${i} not found in DOM`);
                }
            }

            console.log("💥 DATA PERSISTENCE: Nuclear DOM update completed");

        } catch (error) {
            console.error("💥 DATA PERSISTENCE: Nuclear DOM update failed", error);
        }
    },

    /**
     * IMMEDIATE FORCE UPDATE: Update DOM immediately with current data
     */
    immediateForceUpdate() {
        console.log("⚡ IMMEDIATE FORCE: Updating DOM with current data");

        try {
            const numbersToShow = window.rolledNumbersArray || [];
            const colorsToShow = window.rolledNumbersColorArray || [];

            console.log("⚡ IMMEDIATE FORCE: Numbers to display:", numbersToShow);
            console.log("⚡ IMMEDIATE FORCE: Colors to display:", colorsToShow);

            // Force update each element immediately
            for (let i = 0; i < 5; i++) {
                const element = document.querySelector(`.roll${i + 1}`);
                if (element) {
                    // Clear everything
                    element.innerHTML = '';
                    element.className = element.className.replace(/roll-(red|black|green)/g, '');

                    // Set new content if available
                    if (i < numbersToShow.length && numbersToShow[i] !== undefined && numbersToShow[i] !== null) {
                        const number = numbersToShow[i];
                        const color = (i < colorsToShow.length) ? colorsToShow[i] : 'black';

                        element.innerHTML = number;
                        element.classList.add(`roll-${color}`);

                        console.log(`⚡ IMMEDIATE FORCE: Set .roll${i + 1} to ${number} (${color})`);

                        // Force browser to recalculate styles
                        element.offsetHeight;
                    }
                }
            }

            console.log("⚡ IMMEDIATE FORCE: DOM update completed");

        } catch (error) {
            console.error("⚡ IMMEDIATE FORCE: Failed:", error);
        }
    },

    /**
     * SUPER AGGRESSIVE UPDATE: Force update with fresh database data
     */
    async superAggressiveUpdate() {
        console.log("💥💥 SUPER AGGRESSIVE: Starting nuclear update sequence");

        try {
            // Force reload data from database
            const response = await fetch('../load_analytics.php?force=' + Date.now(), {
                method: "GET",
                headers: {
                    "Cache-Control": "no-cache, no-store, must-revalidate",
                    "Pragma": "no-cache",
                    "Expires": "0"
                }
            });

            const data = await response.json();
            console.log("💥💥 SUPER AGGRESSIVE: Fresh database data:", data);

            if (data.status === 'success') {
                const allSpins = JSON.parse(data.all_spins || '[]');
                const recentNumbers = allSpins.slice(0, 5);

                console.log("💥💥 SUPER AGGRESSIVE: Recent numbers from DB:", recentNumbers);

                // FORCE UPDATE ARRAYS
                window.allSpins = allSpins;
                window.rolledNumbersArray = recentNumbers;

                // Generate colors
                const rouletteNumbersRed = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
                window.rolledNumbersColorArray = recentNumbers.map(number => {
                    const num = parseInt(number);
                    if (num === 0) return 'green';
                    return rouletteNumbersRed.includes(num) ? 'red' : 'black';
                });

                console.log("💥💥 SUPER AGGRESSIVE: Updated arrays:", window.rolledNumbersArray, window.rolledNumbersColorArray);

                // FORCE DOM UPDATE (using EXACT same method as working recent numbers)
                for (let i = 0; i < recentNumbers.length && i < 5; i++) {
                    const element = document.querySelector(`.roll${i + 1}`);
                    if (element) {
                        const number = recentNumbers[i];
                        const color = window.rolledNumbersColorArray[i];

                        // Clear everything
                        element.innerHTML = '';
                        element.className = element.className.replace(/roll-(red|black|green)/g, '');

                        // Set new content
                        element.innerHTML = number;
                        element.classList.add(`roll-${color}`);

                        console.log(`💥💥 SUPER AGGRESSIVE: Set .roll${i + 1} to ${number} (${color})`);

                        // Force style recalculation
                        element.offsetHeight;
                    }
                }

                // Apply SAME DIRECT DOM METHOD to analytics elements
                if (typeof window.directUpdateAnalyticsDOM === "function") {
                    window.directUpdateAnalyticsDOM(window.allSpins, window.numberFrequency, window.currentDrawNumber);
                    console.log("💥💥 SUPER AGGRESSIVE: Analytics updated using DIRECT DOM method");
                }

                // Update draw number display using DIRECT DOM method
                if (typeof window.directUpdateDrawNumbers === "function") {
                    window.directUpdateDrawNumbers(window.currentDrawNumber);
                    console.log("💥💥 SUPER AGGRESSIVE: Draw numbers updated using DIRECT DOM method");
                }

                // Clear any remaining elements
                for (let i = recentNumbers.length + 1; i <= 5; i++) {
                    const element = document.querySelector(`.roll${i}`);
                    if (element) {
                        element.innerHTML = '';
                        element.className = element.className.replace(/roll-(red|black|green)/g, '');
                    }
                }

                console.log("💥💥 SUPER AGGRESSIVE: DOM update completed");

            } else {
                console.error("💥💥 SUPER AGGRESSIVE: Database error:", data.message);
            }

        } catch (error) {
            console.error("💥💥 SUPER AGGRESSIVE: Failed:", error);
        }
    },

    /**
     * Get current restoration status
     */
    getStatus() {
        return {
            isLoading: this.state.isLoading,
            isLoaded: this.state.isLoaded,
            loadAttempts: this.state.loadAttempts,
            lastLoadTime: this.state.lastLoadTime,
            spinsRestored: window.allSpins ? window.allSpins.length : 0,
            currentDraw: window.currentDrawNumber || 0
        };
    }
};

// Create missing update functions for compatibility
window.updateNumberHistory = function() {
    console.log("📊 UPDATE NUMBER HISTORY: Updating number history display");

    // Check if another update is in progress to prevent duplicates
    if (window.recentNumbersUpdateInProgress) {
        console.log("📊 UPDATE NUMBER HISTORY: Update in progress, skipping to prevent duplicates");
        return;
    }

    try {
        // Clear existing number history display
        const historyElement = document.querySelector('#number-history');
        if (historyElement) {
            historyElement.innerHTML = '';

            // Display number history (last 8 spins in reverse order - newest first)
            if (window.allSpins && Array.isArray(window.allSpins) && window.allSpins.length > 0) {
                const historyToShow = window.allSpins.slice(0, 8);
                const rouletteNumbersRed = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];

                historyToShow.forEach((number, index) => {
                    const colorClass = number === 0 ? 'green' :
                                      rouletteNumbersRed.includes(number) ? 'red' : 'black';

                    // Calculate draw number with proper sequential logic
                    let baseDrawNumber = window.currentDrawNumber || 1;

                    // If the base is too low to show 8 sequential draws, adjust it
                    if (baseDrawNumber <= historyToShow.length) {
                        baseDrawNumber = historyToShow.length + 1;
                    }

                    // Calculate the draw number for this spin (newest first)
                    const drawNum = baseDrawNumber - (index + 1);

                    historyElement.innerHTML += `
                      <div class="history-item">
                        <div class="history-draw">Draw #${drawNum}</div>
                        <div class="history-number ${colorClass}">${number}</div>
                      </div>
                    `;
                });

                console.log("✅ UPDATE NUMBER HISTORY: Number history updated with", historyToShow.length, "items");
            } else {
                console.log("⚠️ UPDATE NUMBER HISTORY: No spin data available");
            }
        } else {
            console.log("⚠️ UPDATE NUMBER HISTORY: Number history element not found");
        }
    } catch (error) {
        console.error("❌ UPDATE NUMBER HISTORY: Error updating number history", error);
    }
};

window.updateRecentNumbers = function() {
    console.log("📊 UPDATE RECENT NUMBERS: Updating recent numbers display");
    console.log("📊 UPDATE RECENT NUMBERS: Current data state:");
    console.log("  rolledNumbersArray:", window.rolledNumbersArray);
    console.log("  rolledNumbersColorArray:", window.rolledNumbersColorArray);
    console.log("  allSpins:", window.allSpins);

    try {
        // Wait a moment to ensure DOM is ready
        setTimeout(() => {
            // Use the existing displayRollHistory function if available
            if (typeof window.displayRollHistory === "function") {
                console.log("📊 UPDATE RECENT NUMBERS: Calling displayRollHistory function");
                window.displayRollHistory();
                console.log("✅ UPDATE RECENT NUMBERS: Used displayRollHistory function");
            } else {
                // Fallback implementation
                console.log("⚠️ UPDATE RECENT NUMBERS: displayRollHistory not available, using fallback");

                // Clear existing display first
                for (let i = 1; i <= 5; i++) {
                    const rollElement = document.querySelector(`.roll${i}`);
                    if (rollElement) {
                        rollElement.innerHTML = '';
                        rollElement.classList.remove("roll-red", "roll-black", "roll-green");
                        console.log(`📊 UPDATE RECENT NUMBERS: Cleared .roll${i}`);
                    } else {
                        console.warn(`⚠️ UPDATE RECENT NUMBERS: Element .roll${i} not found`);
                    }
                }

                // Display recent numbers from rolledNumbersArray or allSpins
                const numbersToShow = window.rolledNumbersArray || window.allSpins || [];
                const colorsToShow = window.rolledNumbersColorArray || [];

                console.log("📊 UPDATE RECENT NUMBERS: Numbers to show:", numbersToShow);
                console.log("📊 UPDATE RECENT NUMBERS: Colors to show:", colorsToShow);

                for (let i = 0; i < numbersToShow.length && i < 5; i++) {
                    const rollElement = document.querySelector(`.roll${i + 1}`);
                    if (rollElement && numbersToShow[i] !== undefined && numbersToShow[i] !== null) {
                        rollElement.innerHTML = numbersToShow[i];

                        // Determine color
                        let colorClass = 'green';
                        if (i < colorsToShow.length && colorsToShow[i]) {
                            colorClass = colorsToShow[i];
                        } else {
                            // Calculate color from number
                            const number = parseInt(numbersToShow[i]);
                            if (number === 0) {
                                colorClass = 'green';
                            } else {
                                const rouletteNumbersRed = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
                                colorClass = rouletteNumbersRed.includes(number) ? 'red' : 'black';
                            }
                        }

                        rollElement.classList.add(`roll-${colorClass}`);
                        console.log(`📊 UPDATE RECENT NUMBERS: Set .roll${i + 1} to ${numbersToShow[i]} with color ${colorClass}`);
                    } else {
                        console.warn(`⚠️ UPDATE RECENT NUMBERS: Element .roll${i + 1} not found or no number to show`);
                    }
                }

                console.log("✅ UPDATE RECENT NUMBERS: Fallback implementation completed");

                // Also update draw number display
                if (typeof updateDrawNumberDisplay === 'function') {
                    updateDrawNumberDisplay();
                    console.log("✅ UPDATE RECENT NUMBERS: Updated draw number display");
                }
            }
        }, 100); // Small delay to ensure DOM is ready
    } catch (error) {
        console.error("❌ UPDATE RECENT NUMBERS: Error updating recent numbers", error);
    }
};

// Auto-initialize when script loads
DataPersistence.init();

// Make DataPersistence globally available
window.DataPersistence = DataPersistence;

// ✅ FIXED: Visibility changes now handled by TabVisibilityManager
// This prevents race conditions when returning to idle tabs
console.log("🔄 DATA PERSISTENCE: Visibility handling delegated to TabVisibilityManager");

console.log("✅ DATA PERSISTENCE: TV Display Data Persistence System loaded and ready");
