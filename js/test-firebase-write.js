/**
 * Test Firebase Write Function
 * Run this in the browser console to test if Firebase writes are working
 */

async function testFirebaseWrite() {
    console.log('🧪 Testing Firebase write...');
    
    if (!window.FirebaseService) {
        console.error('❌ FirebaseService not available');
        return;
    }
    
    if (!window.FirebaseService.isInitialized()) {
        console.error('❌ FirebaseService not initialized');
        return;
    }
    
    try {
        // Test 1: Write to gameState
        console.log('📝 Test 1: Writing to gameState/current...');
        const testData = {
            test: true,
            timestamp: new Date().toISOString(),
            message: 'Test write from browser'
        };
        
        await window.FirebaseService.GameState.setCurrent(testData);
        console.log('✅ Test 1: Write successful!');
        
        // Test 2: Read back
        console.log('📖 Test 2: Reading back from gameState/current...');
        const readData = await window.FirebaseService.GameState.getCurrent();
        console.log('✅ Test 2: Read successful!', readData);
        
        // Test 3: Write to draws
        console.log('📝 Test 3: Writing to draws/999...');
        const drawData = {
            drawNumber: 999,
            winningNumber: 7,
            winningColor: 'red',
            test: true,
            timestamp: new Date().toISOString()
        };
        
        await window.FirebaseService.Draws.set(999, drawData);
        console.log('✅ Test 3: Write successful!');
        
        // Test 4: Read back
        console.log('📖 Test 4: Reading back from draws/999...');
        const readDraw = await window.FirebaseService.Draws.get(999);
        console.log('✅ Test 4: Read successful!', readDraw);
        
        console.log('🎉 All tests passed! Check Firebase console to see the data.');
        
    } catch (error) {
        console.error('❌ Test failed:', error);
        console.error('Error details:', {
            message: error.message,
            code: error.code,
            stack: error.stack
        });
    }
}

// Auto-run if Firebase is ready
if (window.FirebaseService && window.FirebaseService.isInitialized()) {
    console.log('✅ Firebase ready, you can run testFirebaseWrite() in console');
} else {
    console.log('⏳ Waiting for Firebase...');
    window.addEventListener('firebaseServiceInitialized', () => {
        console.log('✅ Firebase ready, you can run testFirebaseWrite() in console');
    });
}

// Make it available globally
window.testFirebaseWrite = testFirebaseWrite;










