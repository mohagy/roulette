/**
 * Direct TV Display Integration for Triple Storage
 *
 * This script directly integrates with the TV display to ensure
 * all spins are saved to the triple storage system.
 */

console.log("🔧 DIRECT INTEGRATION: Loading TV Display Triple Storage Integration");

// Global variables to track integration status
window.tripleStorageIntegrationActive = false;
window.tripleStorageDebugMode = true;

// Deduplication system to prevent multiple saves of the same spin
window.recentSpinSaves = new Map(); // Track recent saves to prevent duplicates
window.spinSaveTimeout = 5000; // 5 seconds timeout for duplicate detection

// Direct triple storage function
async function saveSpinToTripleStorage(winningNumber, options = {}) {
    try {
        console.log("💾 DIRECT SAVE: Saving spin to triple storage", { winningNumber, options });

        // Create a unique key for this spin to prevent duplicates
        const spinKey = `${winningNumber}_${Date.now()}`;
        const currentTime = Date.now();

        // Check for recent duplicate saves
        for (const [key, timestamp] of window.recentSpinSaves.entries()) {
            if (currentTime - timestamp > window.spinSaveTimeout) {
                // Remove old entries
                window.recentSpinSaves.delete(key);
            } else if (key.startsWith(`${winningNumber}_`)) {
                // Found a recent save for the same winning number
                console.warn("🚫 DUPLICATE PREVENTION: Skipping duplicate save for winning number", winningNumber);
                return { status: "skipped", reason: "duplicate_prevention", winningNumber };
            }
        }

        // Mark this spin as being saved
        window.recentSpinSaves.set(spinKey, currentTime);
        console.log("✅ DUPLICATE PREVENTION: Spin marked for saving", spinKey);

        // Get current draw number (try multiple sources)
        let drawNumber = options.drawNumber;
        if (!drawNumber) {
            // Try to get from global variables
            if (typeof window.currentDrawNumber !== "undefined") {
                drawNumber = window.currentDrawNumber + 1;
            } else {
                // Fallback to API
                try {
                    const response = await fetch("/slipp/load_analytics.php");
                    const data = await response.json();
                    if (data.status === "success" && data.current_draw_number) {
                        drawNumber = parseInt(data.current_draw_number) + 1;
                    } else {
                        drawNumber = 1; // Start from 1 if no data
                    }
                } catch (e) {
                    console.warn("⚠️ DIRECT SAVE: Could not get draw number from API, using 1");
                    drawNumber = 1;
                }
            }
        }

        // Detect if manual
        const isManual = options.isManual ||
                        (typeof window.manualWinningNumber !== "undefined" && window.manualWinningNumber !== null) ||
                        (typeof window.forcedNumber !== "undefined" && window.forcedNumber !== null);

        // Prepare data for triple storage
        const spinData = {
            winning_number: parseInt(winningNumber),
            draw_number: parseInt(drawNumber),
            timestamp: new Date().toISOString().slice(0, 19).replace("T", " "),
            is_manual: isManual,
            total_bets: options.totalBets || 0,
            total_stake: options.totalStake || 0.00,
            total_payout: options.totalPayout || 0.00
        };

        console.log("📊 DIRECT SAVE: Prepared spin data", spinData);

        // Call triple storage API
        const response = await fetch("/slipp/php/triple_storage_api.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(spinData)
        });

        const result = await response.json();

        if (result.status === "success") {
            console.log("✅ DIRECT SAVE: Successfully saved to triple storage", result.data);

            // Update global draw number if it exists
            if (typeof window.currentDrawNumber !== "undefined") {
                window.currentDrawNumber = result.data.draw_number;
            }

            // Trigger custom event
            document.dispatchEvent(new CustomEvent("tripleStorageSaveSuccess", {
                detail: result.data
            }));

            return result.data;
        } else {
            throw new Error(result.message || "Unknown error from triple storage API");
        }

    } catch (error) {
        console.error("❌ DIRECT SAVE: Failed to save to triple storage", error);

        // Trigger error event
        document.dispatchEvent(new CustomEvent("tripleStorageSaveError", {
            detail: { error: error.message, winningNumber, options }
        }));

        throw error;
    }
}

// Function to override recordSpinForAnalytics
function setupTripleStorageIntegration() {
    console.log("🔧 SETUP: Setting up triple storage integration");

    // Store original function if it exists
    const originalRecordSpin = window.recordSpinForAnalytics;

    // Selectively disable only database saves, keep display updates
    disableLegacyDatabaseSaves();

    // Create new enhanced function
    window.recordSpinForAnalytics = async function(winningNumber) {
        console.log("🎯 INTERCEPT: recordSpinForAnalytics called with number:", winningNumber);

        try {
            // Set flag to prevent legacy database saves during our operation
            window.tripleStorageSaveInProgress = true;

            // Save to triple storage (with duplicate prevention)
            const result = await saveSpinToTripleStorage(winningNumber);

            if (result.status === "skipped") {
                console.log("⏭️ INTERCEPT: Spin save skipped due to duplicate prevention");
                // Still update display even if save was skipped
                updateLocalAnalyticsDisplay(winningNumber);
                return;
            }

            console.log("✅ INTERCEPT: Triple storage save successful");

            // Update local analytics and display
            updateLocalAnalyticsDisplay(winningNumber);

            // Call original function for any additional display updates (but database saves will be blocked)
            if (originalRecordSpin && typeof originalRecordSpin === "function") {
                console.log("🔄 INTERCEPT: Calling original function for display updates");
                try {
                    originalRecordSpin.call(this, winningNumber);
                } catch (legacyError) {
                    console.warn("⚠️ INTERCEPT: Original function failed, but triple storage succeeded", legacyError);
                }
            }

        } catch (error) {
            console.error("❌ INTERCEPT: Triple storage failed", error);

            // Clear flag and fallback to original function
            window.tripleStorageSaveInProgress = false;

            if (originalRecordSpin && typeof originalRecordSpin === "function") {
                console.log("🔄 INTERCEPT: Falling back to original function");
                originalRecordSpin.call(this, winningNumber);
            } else {
                console.error("❌ INTERCEPT: No fallback available, spin may be lost");
            }
        } finally {
            // Always clear the flag when done
            window.tripleStorageSaveInProgress = false;
        }
    };

    window.tripleStorageIntegrationActive = true;
    console.log("✅ SETUP: Triple storage integration is now active");
}

// Function to selectively disable only database save operations (not display updates)
function disableLegacyDatabaseSaves() {
    console.log("🚫 SELECTIVE DISABLE: Disabling only database saves, keeping display updates");

    // Create a flag to track if we're in a triple storage save operation
    window.tripleStorageSaveInProgress = false;

    // Disable saveAnalyticsData database operations but keep local updates
    if (typeof window.saveAnalyticsData === "function") {
        const originalSaveAnalytics = window.saveAnalyticsData;
        window.saveAnalyticsData = function() {
            if (window.tripleStorageSaveInProgress) {
                console.log("🚫 SELECTIVE: saveAnalyticsData database save blocked (triple storage active)");
                return; // Block database save during triple storage operation
            } else {
                console.log("✅ SELECTIVE: saveAnalyticsData allowed (not during triple storage)");
                originalSaveAnalytics.call(this);
            }
        };
        console.log("🔧 SELECTIVE: saveAnalyticsData modified for selective blocking");
    }

    // Disable saveRollHistory database operations but keep local updates
    if (typeof window.saveRollHistory === "function") {
        const originalSaveRollHistory = window.saveRollHistory;
        window.saveRollHistory = function() {
            if (window.tripleStorageSaveInProgress) {
                console.log("🚫 SELECTIVE: saveRollHistory database save blocked (triple storage active)");
                return; // Block database save during triple storage operation
            } else {
                console.log("✅ SELECTIVE: saveRollHistory allowed (not during triple storage)");
                originalSaveRollHistory.call(this);
            }
        };
        console.log("🔧 SELECTIVE: saveRollHistory modified for selective blocking");
    }
}

// Function to update local analytics display without saving to database
function updateLocalAnalyticsDisplay(winningNumber) {
    console.log("📊 LOCAL UPDATE: Updating local analytics display for number:", winningNumber);

    try {
        // First, update local data structures
        updateLocalDataStructures(winningNumber);

        // Apply SAME DIRECT DOM METHOD to analytics elements (like working recent numbers)
        if (typeof window.directUpdateAnalyticsDOM === "function") {
            window.directUpdateAnalyticsDOM(window.allSpins, window.numberFrequency, window.currentDrawNumber);
            console.log("✅ LOCAL UPDATE: Analytics display updated using DIRECT DOM method");
        } else {
            // Fallback to original method if direct DOM function not available
            if (typeof window.updateAnalytics === "function") {
                window.updateAnalytics();
                console.log("✅ LOCAL UPDATE: Analytics display updated (fallback)");
            }
        }

        // Update draw number display using DIRECT DOM method (like working recent numbers)
        if (typeof window.directUpdateDrawNumbers === "function") {
            window.directUpdateDrawNumbers(window.currentDrawNumber);
            console.log("✅ LOCAL UPDATE: Draw number display updated using DIRECT DOM method");
        } else {
            // Fallback to original method if direct DOM function not available
            if (typeof window.updateDrawNumberDisplay === "function") {
                window.updateDrawNumberDisplay();
                console.log("✅ LOCAL UPDATE: Draw number display updated (fallback)");
            }
        }

        // Update number history if function exists (but check coordination flag)
        if (typeof window.updateNumberHistory === "function") {
            // Check if another update is in progress to prevent duplicates
            if (window.recentNumbersUpdateInProgress) {
                console.log("✅ LOCAL UPDATE: Number history update skipped due to coordination flag");
            } else {
                window.updateNumberHistory();
                console.log("✅ LOCAL UPDATE: Number history updated");
            }
        }

        // Update recent numbers display
        if (typeof window.updateRecentNumbers === "function") {
            window.updateRecentNumbers();
            console.log("✅ LOCAL UPDATE: Recent numbers updated");
        }

        // Force refresh of analytics panel
        if (typeof window.refreshAnalyticsPanel === "function") {
            window.refreshAnalyticsPanel();
            console.log("✅ LOCAL UPDATE: Analytics panel refreshed");
        }

        // Trigger any custom update events
        document.dispatchEvent(new CustomEvent('analyticsUpdated', {
            detail: { winningNumber, timestamp: new Date() }
        }));

        console.log("✅ LOCAL UPDATE: All display elements updated successfully");

    } catch (error) {
        console.warn("⚠️ LOCAL UPDATE: Error updating local display", error);
    }
}

// Function to update local data structures (allSpins, numberFrequency, etc.)
function updateLocalDataStructures(winningNumber) {
    console.log("📊 DATA UPDATE: Updating local data structures for number:", winningNumber);

    try {
        // Initialize arrays if they don't exist
        if (typeof window.allSpins === "undefined") {
            window.allSpins = [];
            console.log("📊 DATA UPDATE: Initialized allSpins array");
        }

        if (typeof window.numberFrequency === "undefined") {
            window.numberFrequency = new Array(37).fill(0);
            console.log("📊 DATA UPDATE: Initialized numberFrequency array");
        }

        if (typeof window.currentDrawNumber === "undefined") {
            window.currentDrawNumber = 0;
            console.log("📊 DATA UPDATE: Initialized currentDrawNumber");
        }

        // Add to beginning of allSpins array (newest first)
        window.allSpins.unshift(winningNumber);
        console.log("📊 DATA UPDATE: Added number to allSpins:", winningNumber);

        // Increment frequency counter
        window.numberFrequency[winningNumber]++;
        console.log("📊 DATA UPDATE: Incremented frequency for number", winningNumber, "to", window.numberFrequency[winningNumber]);

        // Limit the number of stored spins (keep last 100)
        const maxSpins = 100;
        if (window.allSpins.length > maxSpins) {
            const removedNumber = window.allSpins.pop();
            window.numberFrequency[removedNumber]--;
            console.log("📊 DATA UPDATE: Removed oldest spin number", removedNumber, ", new frequency:", window.numberFrequency[removedNumber]);
        }

        // Increment draw number
        window.currentDrawNumber++;
        console.log("📊 DATA UPDATE: Incremented draw number to:", window.currentDrawNumber);

        console.log("✅ DATA UPDATE: Local data structures updated successfully");

    } catch (error) {
        console.error("❌ DATA UPDATE: Error updating local data structures", error);
    }
}

// Function to test the integration
async function testTripleStorageIntegration() {
    console.log("🧪 TEST: Testing triple storage integration");

    try {
        if (typeof window.recordSpinForAnalytics === "function") {
            console.log("✅ TEST: recordSpinForAnalytics function is available");

            // Test with a sample number
            await window.recordSpinForAnalytics(99); // Use 99 as a test number
            console.log("✅ TEST: Test call completed successfully");

            return true;
        } else {
            console.error("❌ TEST: recordSpinForAnalytics function not found");
            return false;
        }
    } catch (error) {
        console.error("❌ TEST: Integration test failed", error);
        return false;
    }
}

// Setup integration when DOM is ready
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function() {
        console.log("📄 DOM: Document loaded, setting up integration");
        setTimeout(setupTripleStorageIntegration, 1000); // Wait 1 second for other scripts
    });
} else {
    console.log("📄 DOM: Document already loaded, setting up integration immediately");
    setTimeout(setupTripleStorageIntegration, 100); // Small delay to ensure other scripts are loaded
}

// Also try to setup immediately in case DOMContentLoaded already fired
setTimeout(setupTripleStorageIntegration, 2000); // Backup setup after 2 seconds

// Listen for successful saves
document.addEventListener("tripleStorageSaveSuccess", function(event) {
    console.log("🎉 EVENT: Triple storage save successful", event.detail);
});

// Listen for save errors
document.addEventListener("tripleStorageSaveError", function(event) {
    console.error("💥 EVENT: Triple storage save error", event.detail);
});

// Make functions globally available for debugging
window.saveSpinToTripleStorage = saveSpinToTripleStorage;
window.testTripleStorageIntegration = testTripleStorageIntegration;
window.setupTripleStorageIntegration = setupTripleStorageIntegration;

console.log("🔧 DIRECT INTEGRATION: TV Display Triple Storage Integration loaded and ready");