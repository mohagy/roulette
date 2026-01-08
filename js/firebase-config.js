/**
 * Firebase Configuration
 * 
 * Replace the placeholder values with your actual Firebase project credentials.
 * You can find these in your Firebase Console under Project Settings.
 */

const firebaseConfig = {
    apiKey: "AIzaSyA0ieIqlj931McUnu1CeYzkN4s5MwOm2u4",
    authDomain: "roulette-2f902.firebaseapp.com",
    databaseURL: "https://roulette-2f902-default-rtdb.firebaseio.com",
    projectId: "roulette-2f902",
    storageBucket: "roulette-2f902.firebasestorage.app",
    messagingSenderId: "522854471260",
    appId: "1:522854471260:web:ef4c4698248ceb34c5b899"
};

// Initialize Firebase
(function initializeFirebase() {
    console.log('🔥 Firebase Config: Checking for Firebase SDK...');
    
    if (typeof firebase !== 'undefined') {
        try {
            console.log('🔥 Firebase Config: Firebase SDK found, initializing...');
            firebase.initializeApp(firebaseConfig);
            
            // Get a reference to the database service
            const database = firebase.database();
            
            // Export for use in other files
            window.firebaseDatabase = database;
            window.firebaseApp = firebase;
            
            console.log('✅ Firebase initialized successfully');
            console.log('🔥 Database URL:', firebaseConfig.databaseURL);
            
            // Dispatch event to notify other scripts
            window.dispatchEvent(new CustomEvent('firebase-ready'));
        } catch (error) {
            console.error('❌ Firebase initialization error:', error);
        }
    } else {
        console.warn('⚠️ Firebase SDK not loaded yet. Retrying in 100ms...');
        // Retry after a short delay
        setTimeout(initializeFirebase, 100);
    }
})();

