/**
 * Clear Draw Cache Script
 * Clears localStorage and forces refresh of draw data
 */

const FIREBASE_URL = 'https://roulette-2f902-default-rtdb.firebaseio.com';

async function clearDrawCache() {
    console.log('🧹 Clearing draw cache...\n');

    // Clear localStorage keys related to draws
    const keysToClear = [
        'cashier_current_draw',
        'currentDrawNumber',
        'lastCompletedDrawNumber',
        'upcoming_draws_data',
        'last_draw_number',
        'next_draw_number',
        'georgetown_seconds_until_next_draw'
    ];

    let clearedCount = 0;
    keysToClear.forEach(key => {
        if (localStorage.getItem(key)) {
            localStorage.removeItem(key);
            clearedCount++;
            console.log(`   ✅ Cleared: ${key}`);
        }
    });

    console.log(`\n📊 Cleared ${clearedCount} localStorage keys\n`);

    // Verify Firebase has correct data
    try {
        const response = await fetch(`${FIREBASE_URL}/gameState/drawInfo.json`);
        const drawInfo = await response.json();
        
        console.log('📡 Firebase Draw Info:', drawInfo);
        
        if (drawInfo && drawInfo.currentDraw === 1) {
            console.log('✅ Firebase is correctly set to draw #1\n');
        } else {
            console.log('⚠️  Firebase draw info:', drawInfo);
        }
    } catch (error) {
        console.error('❌ Error checking Firebase:', error);
    }

    console.log('🎉 Cache cleared! Please refresh the page.\n');
}

// Run if in browser
if (typeof window !== 'undefined') {
    clearDrawCache();
}

// Export for Node.js
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { clearDrawCache };
}

