/**
 * Reset All Draw Data to Draw #1
 * 
 * Usage: node reset-draws-to-one.js
 */

const https = require('https');

const FIREBASE_URL = 'https://roulette-2f902-default-rtdb.firebaseio.com';

/**
 * Make HTTP request to Firebase
 */
function firebaseRequest(method, path, data = null) {
    return new Promise((resolve, reject) => {
        const url = `${FIREBASE_URL}/${path}.json`;
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            }
        };

        if (data) {
            const dataStr = JSON.stringify(data);
            options.headers['Content-Length'] = Buffer.byteLength(dataStr);
        }

        const req = https.request(url, options, (res) => {
            let responseData = '';
            res.on('data', (chunk) => {
                responseData += chunk;
            });
            res.on('end', () => {
                if (res.statusCode >= 200 && res.statusCode < 300) {
                    resolve(true);
                } else {
                    reject(new Error(`HTTP ${res.statusCode}: ${responseData}`));
                }
            });
        });

        req.on('error', reject);
        
        if (data) {
            req.write(JSON.stringify(data));
        }
        
        req.end();
    });
}

async function resetAllDraws() {
    console.log('🔄 Resetting all draw data to draw #1...\n');

    try {
        // 1. Delete all draws
        console.log('1️⃣  Deleting all draws...');
        await firebaseRequest('DELETE', 'draws');
        console.log('   ✅ All draws deleted\n');

        // 2. Reset game state
        console.log('2️⃣  Resetting game state to draw #1...');
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
        await firebaseRequest('PUT', 'gameState/current', gameState);
        console.log('   ✅ Game state reset to draw #1\n');

        // 3. Reset draw info
        console.log('3️⃣  Resetting draw info...');
        const drawInfo = {
            currentDraw: 1,
            nextDraw: 2
        };
        await firebaseRequest('PUT', 'gameState/drawInfo', drawInfo);
        console.log('   ✅ Draw info reset\n');

        // 4. Reset analytics
        console.log('4️⃣  Resetting analytics...');
        const analytics = {
            allSpins: [],
            numberFrequency: Array(37).fill(0),
            currentDrawNumber: 1,
            lastUpdated: new Date().toISOString()
        };
        await firebaseRequest('PUT', 'analytics/current', analytics);
        console.log('   ✅ Analytics reset\n');

        console.log('🎉 Success! All draw data has been reset to draw #1\n');
        console.log('📊 Current state:');
        console.log('   - Current Draw: #1');
        console.log('   - Next Draw: #2');
        console.log('   - All draw history: Deleted');
        console.log('   - Analytics: Cleared\n');
        console.log('💡 IMPORTANT: Clear browser localStorage and refresh the page.\n');
        console.log('   Run this in browser console to clear cache:');
        console.log('   localStorage.removeItem("cashier_current_draw");');
        console.log('   localStorage.removeItem("currentDrawNumber");');
        console.log('   localStorage.removeItem("lastCompletedDrawNumber");');
        console.log('   location.reload();\n');

    } catch (error) {
        console.error('\n❌ Error resetting draws:', error.message);
        process.exit(1);
    }
}

// Run the reset
resetAllDraws();

