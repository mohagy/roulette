/**
 * Triple Storage Integration for TV Display
 *
 * This script integrates the triple storage system with the TV display
 * to save spin data to roulette_analytics, detailed_draw_results, and roulette_draws tables.
 */

// Triple storage functionality
const TripleStorage = {
    /**
     * Save spin data to all three tables
     */
    async saveSpin(winningNumber, drawNumber = null, options = {}) {
        try {
            console.log("🔄 TRIPLE STORAGE: Saving spin data", {
                winningNumber,
                drawNumber,
                options
            });

            // Validate winning number
            if (!this.isValidNumber(winningNumber)) {
                throw new Error(`Invalid winning number: ${winningNumber}`);
            }

            // Get or generate draw number
            let finalDrawNumber = drawNumber;
            if (!finalDrawNumber) {
                finalDrawNumber = await this.getNextDrawNumber();
            }

            // Prepare data for triple storage
            const data = {
                winning_number: parseInt(winningNumber),
                draw_number: parseInt(finalDrawNumber),
                timestamp: options.timestamp || new Date().toISOString().slice(0, 19).replace("T", " "),
                is_manual: options.isManual || this.detectManualSpin(),
                total_bets: options.totalBets || 0,
                total_stake: options.totalStake || 0.00,
                total_payout: options.totalPayout || 0.00
            };

            console.log("📊 TRIPLE STORAGE: Using parameters", data);

            // Save using triple storage API
            const response = await fetch("/slipp/php/triple_storage_api.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.status === "success") {
                console.log("✅ TRIPLE STORAGE: Spin saved successfully", result.data);

                // Trigger custom event for successful save
                document.dispatchEvent(new CustomEvent("triple_storage_success", {
                    detail: result.data
                }));

                return result.data;
            } else {
                console.error("❌ TRIPLE STORAGE: Save failed", result.message);
                throw new Error(result.message);
            }

        } catch (error) {
            console.error("❌ TRIPLE STORAGE: Error saving spin", error);

            // Trigger custom event for error
            document.dispatchEvent(new CustomEvent("triple_storage_error", {
                detail: { error: error.message, winningNumber, drawNumber }
            }));

            throw error;
        }
    },

    /**
     * Detect if the current spin is manual
     */
    detectManualSpin() {
        // Check for manual winning number indicators
        if (typeof window.manualWinningNumber !== "undefined" && window.manualWinningNumber !== null) {
            console.log("🎯 TRIPLE STORAGE: Manual spin detected via manualWinningNumber");
            return true;
        }

        // Check for forced number indicators
        if (typeof window.forcedNumber !== "undefined" && window.forcedNumber !== null) {
            console.log("🎯 TRIPLE STORAGE: Manual spin detected via forcedNumber");
            return true;
        }

        // Check for manual mode indicators
        if (typeof window.isManualMode !== "undefined" && window.isManualMode === true) {
            console.log("🎯 TRIPLE STORAGE: Manual spin detected via isManualMode");
            return true;
        }

        // Check DOM for manual indicators
        const forcedIndicator = document.getElementById("forced-number-indicator");
        if (forcedIndicator && forcedIndicator.style.display !== "none") {
            console.log("🎯 TRIPLE STORAGE: Manual spin detected via DOM indicator");
            return true;
        }

        console.log("🤖 TRIPLE STORAGE: Automatic spin detected");
        return false;
    },

    /**
     * Get the next draw number
     */
    async getNextDrawNumber() {
        try {
            // Try to get current draw number from analytics
            const response = await fetch("/slipp/load_analytics.php");
            const data = await response.json();

            if (data.status === "success" && data.current_draw_number !== undefined) {
                return parseInt(data.current_draw_number) + 1;
            }

            // Fallback: get from roulette_draws table
            const drawsResponse = await fetch("/slipp/php/get_latest_roulette_draw.php");
            const drawsData = await drawsResponse.json();

            if (drawsData.status === "success" && drawsData.latest_draw_number !== undefined) {
                return parseInt(drawsData.latest_draw_number) + 1;
            }

            // Final fallback: start from 1
            console.warn("⚠️ TRIPLE STORAGE: No existing draw numbers found, starting from 1");
            return 1;

        } catch (error) {
            console.error("❌ TRIPLE STORAGE: Error getting next draw number", error);
            return 1;
        }
    },

    /**
     * Get color for a roulette number
     */
    getColor(number) {
        const redNumbers = [1,3,5,7,9,12,14,16,18,19,21,23,25,27,30,32,34,36];

        if (number === 0) {
            return "green";
        } else if (redNumbers.includes(number)) {
            return "red";
        } else {
            return "black";
        }
    },

    /**
     * Validate roulette number
     */
    isValidNumber(number) {
        return Number.isInteger(number) && number >= 0 && number <= 36;
    },

    /**
     * Update local analytics display
     */
    updateLocalAnalytics(winningNumber, drawNumber) {
        try {
            // Update global variables if they exist
            if (typeof allSpins !== "undefined" && Array.isArray(allSpins)) {
                allSpins.unshift(winningNumber);
                allSpins = allSpins.slice(0, 100);
            }

            if (typeof numberFrequency !== "undefined" && typeof numberFrequency === "object") {
                numberFrequency[winningNumber] = (numberFrequency[winningNumber] || 0) + 1;
            }

            if (typeof currentDrawNumber !== "undefined") {
                currentDrawNumber = Math.max(currentDrawNumber, drawNumber);
            }

            // Update analytics display if function exists
            if (typeof updateAnalytics === "function") {
                updateAnalytics();
            }

            // Update draw number display if function exists
            if (typeof updateDrawNumberDisplay === "function") {
                updateDrawNumberDisplay();
            }

            console.log("📊 TRIPLE STORAGE: Local analytics updated");

        } catch (error) {
            console.error("❌ TRIPLE STORAGE: Error updating local analytics", error);
        }
    }
};

// Make TripleStorage available globally
window.TripleStorage = TripleStorage;

// Enhanced spin recording with triple storage
const EnhancedTripleSpinRecording = {
    /**
     * Record a spin using the triple storage system
     */
    async recordSpin(winningNumber, drawNumber = null, options = {}) {
        try {
            console.log("🎯 ENHANCED TRIPLE RECORDING: Starting spin recording", {
                winningNumber,
                drawNumber,
                options
            });

            // Save using triple storage
            const result = await TripleStorage.saveSpin(winningNumber, drawNumber, options);

            console.log("✅ ENHANCED TRIPLE RECORDING: Spin recorded successfully", result);

            // Update local analytics display
            TripleStorage.updateLocalAnalytics(winningNumber, result.draw_number);

            // Trigger success event
            document.dispatchEvent(new CustomEvent("enhanced_triple_spin_recorded", {
                detail: {
                    winningNumber,
                    drawNumber: result.draw_number,
                    winningColor: result.winning_color,
                    isManual: result.is_manual,
                    timestamp: result.timestamp,
                    detailedRecordId: result.detailed_record_id,
                    rouletteDrawId: result.roulette_draw_id
                }
            }));

            return result;

        } catch (error) {
            console.error("❌ ENHANCED TRIPLE RECORDING: Failed to record spin", error);

            // Trigger error event
            document.dispatchEvent(new CustomEvent("enhanced_triple_spin_error", {
                detail: { error: error.message, winningNumber, drawNumber }
            }));

            // Fallback to legacy recording if available
            if (typeof recordSpinForAnalytics === "function") {
                console.warn("⚠️ ENHANCED TRIPLE RECORDING: Falling back to legacy recording");
                recordSpinForAnalytics(winningNumber);
            }

            throw error;
        }
    },

    /**
     * Initialize enhanced triple recording system
     */
    init() {
        console.log("🎯 ENHANCED TRIPLE RECORDING: Initializing enhanced triple spin recording system");

        // Listen for successful recordings
        document.addEventListener("enhanced_triple_spin_recorded", (event) => {
            console.log("✅ ENHANCED TRIPLE RECORDING: Spin recorded event", event.detail);
        });

        // Listen for recording errors
        document.addEventListener("enhanced_triple_spin_error", (event) => {
            console.error("❌ ENHANCED TRIPLE RECORDING: Spin recording error event", event.detail);
        });

        // Try to override existing recording function, or wait for it to be defined
        this.setupRecordSpinOverride();

        console.log("✅ ENHANCED TRIPLE RECORDING: Enhanced triple recording system initialized");
    },

    /**
     * Setup the recordSpinForAnalytics override with retry logic
     */
    setupRecordSpinOverride() {
        const self = this;

        function attemptOverride() {
            if (typeof window.recordSpinForAnalytics === "function") {
                console.log("🔄 ENHANCED TRIPLE RECORDING: Found recordSpinForAnalytics function, setting up override");

                const originalRecordSpin = window.recordSpinForAnalytics;

                window.recordSpinForAnalytics = async function(winningNumber) {
                    try {
                        console.log("🔄 ENHANCED TRIPLE RECORDING: Intercepting legacy recordSpinForAnalytics call with number:", winningNumber);

                        // Use triple storage to record the spin
                        await self.recordSpin(winningNumber);

                        console.log("✅ ENHANCED TRIPLE RECORDING: Successfully recorded spin using triple storage");

                    } catch (error) {
                        console.error("❌ ENHANCED TRIPLE RECORDING: Enhanced recording failed, using legacy fallback", error);

                        // Fallback to original function
                        try {
                            originalRecordSpin.call(this, winningNumber);
                        } catch (fallbackError) {
                            console.error("❌ ENHANCED TRIPLE RECORDING: Legacy fallback also failed", fallbackError);
                        }
                    }
                };

                console.log("✅ ENHANCED TRIPLE RECORDING: Successfully overrode recordSpinForAnalytics function");
                return true;
            }
            return false;
        }

        // Try immediately
        if (attemptOverride()) {
            return;
        }

        // If not found, set up a watcher to override it when it becomes available
        console.log("🔍 ENHANCED TRIPLE RECORDING: recordSpinForAnalytics not found yet, setting up watcher");

        let attempts = 0;
        const maxAttempts = 50; // Try for 5 seconds

        const watcher = setInterval(() => {
            attempts++;

            if (attemptOverride()) {
                clearInterval(watcher);
                console.log("✅ ENHANCED TRIPLE RECORDING: Successfully found and overrode recordSpinForAnalytics after", attempts, "attempts");
            } else if (attempts >= maxAttempts) {
                clearInterval(watcher);
                console.warn("⚠️ ENHANCED TRIPLE RECORDING: Could not find recordSpinForAnalytics function after", maxAttempts, "attempts");

                // Create our own recordSpinForAnalytics function as a fallback
                window.recordSpinForAnalytics = async function(winningNumber) {
                    console.log("🆕 ENHANCED TRIPLE RECORDING: Using fallback recordSpinForAnalytics implementation");
                    try {
                        await self.recordSpin(winningNumber);
                    } catch (error) {
                        console.error("❌ ENHANCED TRIPLE RECORDING: Fallback implementation failed", error);
                    }
                };

                console.log("🆕 ENHANCED TRIPLE RECORDING: Created fallback recordSpinForAnalytics function");
            }
        }, 100); // Check every 100ms
    }
};

// Make EnhancedTripleSpinRecording available globally
window.EnhancedTripleSpinRecording = EnhancedTripleSpinRecording;

// Auto-initialize when DOM is ready
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => {
        EnhancedTripleSpinRecording.init();
    });
} else {
    EnhancedTripleSpinRecording.init();
}

console.log("🎯 Enhanced Triple Storage module loaded");