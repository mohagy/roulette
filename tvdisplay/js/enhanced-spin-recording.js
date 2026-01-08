/**
 * Enhanced Spin Recording with Dual Storage
 * 
 * This script enhances the existing spin recording to use the dual storage system
 * while maintaining backward compatibility and security.
 */

// Enhanced spin recording functionality
const EnhancedSpinRecording = {
    /**
     * Record a spin using the dual storage system
     */
    async recordSpin(winningNumber, drawNumber = null, timestamp = null) {
        try {
            console.log("🎯 ENHANCED RECORDING: Starting spin recording", {
                winningNumber,
                drawNumber,
                timestamp
            });
            
            // Validate winning number
            if (!DualStorage.isValidNumber(winningNumber)) {
                throw new Error(`Invalid winning number: ${winningNumber}`);
            }
            
            // Get or generate draw number
            let finalDrawNumber = drawNumber;
            if (!finalDrawNumber) {
                // Get current draw number from analytics or generate
                finalDrawNumber = await this.getNextDrawNumber();
            }
            
            // Generate timestamp if not provided
            const finalTimestamp = timestamp || new Date().toISOString().slice(0, 19).replace("T", " ");
            
            console.log("📊 ENHANCED RECORDING: Using parameters", {
                winningNumber,
                drawNumber: finalDrawNumber,
                timestamp: finalTimestamp
            });
            
            // Save using dual storage
            const result = await DualStorage.saveSpin(winningNumber, finalDrawNumber, finalTimestamp);
            
            console.log("✅ ENHANCED RECORDING: Spin recorded successfully", result);
            
            // Update local analytics display
            this.updateLocalAnalytics(winningNumber, finalDrawNumber);
            
            // Trigger success event
            document.dispatchEvent(new CustomEvent("enhanced_spin_recorded", {
                detail: {
                    winningNumber,
                    drawNumber: finalDrawNumber,
                    color: DualStorage.getColor(winningNumber),
                    timestamp: finalTimestamp,
                    recordId: result.detailed_record_id
                }
            }));
            
            return result;
            
        } catch (error) {
            console.error("❌ ENHANCED RECORDING: Failed to record spin", error);
            
            // Trigger error event
            document.dispatchEvent(new CustomEvent("enhanced_spin_error", {
                detail: { error: error.message, winningNumber, drawNumber }
            }));
            
            // Fallback to legacy recording if available
            if (typeof recordSpinForAnalytics === "function") {
                console.warn("⚠️ ENHANCED RECORDING: Falling back to legacy recording");
                recordSpinForAnalytics(winningNumber);
            }
            
            throw error;
        }
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
            
            // Fallback: get from detailed results table
            const detailedResponse = await fetch("/slipp/php/get_latest_draw_number.php");
            const detailedData = await detailedResponse.json();
            
            if (detailedData.status === "success" && detailedData.latest_draw_number !== undefined) {
                return parseInt(detailedData.latest_draw_number) + 1;
            }
            
            // Final fallback: start from 1
            console.warn("⚠️ ENHANCED RECORDING: No existing draw numbers found, starting from 1");
            return 1;
            
        } catch (error) {
            console.error("❌ ENHANCED RECORDING: Error getting next draw number", error);
            // Return 1 as safe fallback
            return 1;
        }
    },
    
    /**
     * Update local analytics display
     */
    updateLocalAnalytics(winningNumber, drawNumber) {
        try {
            // Update global variables if they exist
            if (typeof allSpins !== "undefined" && Array.isArray(allSpins)) {
                allSpins.unshift(winningNumber);
                allSpins = allSpins.slice(0, 100); // Keep last 100
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
            
            console.log("📊 ENHANCED RECORDING: Local analytics updated");
            
        } catch (error) {
            console.error("❌ ENHANCED RECORDING: Error updating local analytics", error);
        }
    },
    
    /**
     * Initialize enhanced recording system
     */
    init() {
        console.log("🎯 ENHANCED RECORDING: Initializing enhanced spin recording system");
        
        // Listen for successful recordings
        document.addEventListener("enhanced_spin_recorded", (event) => {
            console.log("✅ ENHANCED RECORDING: Spin recorded event", event.detail);
        });
        
        // Listen for recording errors
        document.addEventListener("enhanced_spin_error", (event) => {
            console.error("❌ ENHANCED RECORDING: Spin recording error event", event.detail);
        });
        
        // Override existing recording function if it exists
        if (typeof recordSpinForAnalytics === "function") {
            const originalRecordSpin = recordSpinForAnalytics;
            
            window.recordSpinForAnalytics = async (winningNumber) => {
                try {
                    console.log("🔄 ENHANCED RECORDING: Intercepting legacy recordSpinForAnalytics call");
                    await this.recordSpin(winningNumber);
                } catch (error) {
                    console.error("❌ ENHANCED RECORDING: Enhanced recording failed, using legacy");
                    originalRecordSpin(winningNumber);
                }
            };
            
            console.log("🔄 ENHANCED RECORDING: Overrode legacy recordSpinForAnalytics function");
        }
        
        console.log("✅ ENHANCED RECORDING: Enhanced recording system initialized");
    }
};

// Make EnhancedSpinRecording available globally
window.EnhancedSpinRecording = EnhancedSpinRecording;

// Auto-initialize when DOM is ready
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => {
        EnhancedSpinRecording.init();
    });
} else {
    EnhancedSpinRecording.init();
}

console.log("🎯 Enhanced Spin Recording module loaded");