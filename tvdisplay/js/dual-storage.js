/**
 * Dual Storage Integration for TV Display
 * 
 * This script integrates the dual storage system with the TV display
 * to save spin data to both analytics and detailed results tables.
 */

// Dual storage functionality
const DualStorage = {
    /**
     * Save spin data to both tables
     */
    async saveSpin(winningNumber, drawNumber, timestamp = null) {
        try {
            console.log("🔄 DUAL STORAGE: Saving spin data", {
                winningNumber,
                drawNumber,
                timestamp: timestamp || new Date().toISOString()
            });
            
            const data = {
                winning_number: parseInt(winningNumber),
                draw_number: parseInt(drawNumber),
                timestamp: timestamp || new Date().toISOString().slice(0, 19).replace("T", " ")
            };
            
            const response = await fetch("/slipp/php/dual_storage_api.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.status === "success") {
                console.log("✅ DUAL STORAGE: Spin saved successfully", result.data);
                
                // Trigger custom event for successful save
                document.dispatchEvent(new CustomEvent("dual_storage_success", {
                    detail: result.data
                }));
                
                return result.data;
            } else {
                console.error("❌ DUAL STORAGE: Save failed", result.message);
                throw new Error(result.message);
            }
            
        } catch (error) {
            console.error("❌ DUAL STORAGE: Error saving spin", error);
            
            // Trigger custom event for error
            document.dispatchEvent(new CustomEvent("dual_storage_error", {
                detail: { error: error.message, winningNumber, drawNumber }
            }));
            
            throw error;
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
    }
};

// Make DualStorage available globally
window.DualStorage = DualStorage;

// Log initialization
console.log("🔄 Dual Storage system initialized");