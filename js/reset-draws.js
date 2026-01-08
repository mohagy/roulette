/**
 * Reset All Draw Data Script
 * 
 * This script deletes all draw data from Firebase and resets to draw #1
 * Run this in the browser console or create a button to trigger it
 */

const ResetDraws = {
    firebaseUrl: 'https://roulette-2f902-default-rtdb.firebaseio.com',

    /**
     * Delete all draws from Firebase
     */
    async deleteAllDraws() {
        try {
            const response = await fetch(`${this.firebaseUrl}/draws.json`, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' }
            });
            return response.ok;
        } catch (error) {
            console.error('Error deleting draws:', error);
            return false;
        }
    },

    /**
     * Reset game state to draw #1
     */
    async resetGameState() {
        const gameState = {
            drawNumber: 1,
            nextDrawNumber: 2,
            winningNumber: null,
            nextWinningNumber: null,
            manualMode: false,
            rollHistory: [],
            rollColors: [],
            lastDrawFormatted: '#1',
            nextDrawFormatted: '#2',
            updatedAt: new Date().toISOString()
        };

        try {
            const response = await fetch(`${this.firebaseUrl}/gameState/current.json`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(gameState)
            });
            return response.ok;
        } catch (error) {
            console.error('Error resetting game state:', error);
            return false;
        }
    },

    /**
     * Reset draw info
     */
    async resetDrawInfo() {
        const drawInfo = {
            currentDraw: 1,
            nextDraw: 2
        };

        try {
            const response = await fetch(`${this.firebaseUrl}/gameState/drawInfo.json`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(drawInfo)
            });
            return response.ok;
        } catch (error) {
            console.error('Error resetting draw info:', error);
            return false;
        }
    },

    /**
     * Reset analytics
     */
    async resetAnalytics() {
        const analytics = {
            allSpins: [],
            numberFrequency: Array(37).fill(0),
            currentDrawNumber: 1,
            lastUpdated: new Date().toISOString()
        };

        try {
            const response = await fetch(`${this.firebaseUrl}/analytics/current.json`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(analytics)
            });
            return response.ok;
        } catch (error) {
            console.error('Error resetting analytics:', error);
            return false;
        }
    },

    /**
     * Reset everything
     */
    async resetAll() {
        console.log('🔄 Starting draw data reset...');
        
        const results = {
            draws: false,
            gameState: false,
            drawInfo: false,
            analytics: false
        };

        // Delete all draws
        console.log('1️⃣  Deleting all draws...');
        results.draws = await this.deleteAllDraws();
        console.log(results.draws ? '   ✅ All draws deleted' : '   ❌ Failed to delete draws');

        // Reset game state
        console.log('2️⃣  Resetting game state to draw #1...');
        results.gameState = await this.resetGameState();
        console.log(results.gameState ? '   ✅ Game state reset' : '   ❌ Failed to reset game state');

        // Reset draw info
        console.log('3️⃣  Resetting draw info...');
        results.drawInfo = await this.resetDrawInfo();
        console.log(results.drawInfo ? '   ✅ Draw info reset' : '   ❌ Failed to reset draw info');

        // Reset analytics
        console.log('4️⃣  Resetting analytics...');
        results.analytics = await this.resetAnalytics();
        console.log(results.analytics ? '   ✅ Analytics reset' : '   ❌ Failed to reset analytics');

        const allSuccess = Object.values(results).every(r => r === true);
        
        if (allSuccess) {
            console.log('\n🎉 All draw data reset successfully!');
            console.log('📊 System reset to draw #1');
            alert('✅ All draw data has been deleted and reset to draw #1!\n\nPlease refresh the page to see the changes.');
        } else {
            console.error('\n❌ Some operations failed:', results);
            alert('⚠️ Some operations failed. Check console for details.');
        }

        return results;
    }
};

// Make it available globally
if (typeof window !== 'undefined') {
    window.ResetDraws = ResetDraws;
}

