/**
 * Firebase Configuration
 * 
 * Replace the placeholder values with your actual Firebase project credentials.
 * You can find these in your Firebase Console under Project Settings.
 * 
 * Updated to support both Firestore (for real-time sync) and Realtime Database (for backward compatibility)
 */

const firebaseConfig = {
    apiKey: "AIzaSyD7PEghPHHigevb46NRuJBWj1PqhqGyvOs",
    authDomain: "superbet-830b0.firebaseapp.com",
    databaseURL: "https://superbet-830b0-default-rtdb.firebaseio.com",
    projectId: "superbet-830b0",
    storageBucket: "superbet-830b0.firebasestorage.app",
    messagingSenderId: "872779929847",
    appId: "1:872779929847:web:c4696e158521376c5b5bf2",
    measurementId: "G-89C23ETVC8"
};

// Initialize Firebase
(function initializeFirebase() {
    console.log('🔥 Firebase Config: Checking for Firebase SDK...');
    
    if (typeof firebase !== 'undefined') {
        try {
            console.log('🔥 Firebase Config: Firebase SDK found, initializing...');
            
            // Initialize Firebase app
            if (!firebase.apps.length) {
            firebase.initializeApp(firebaseConfig);
            }
            
            // Get a reference to the Realtime Database service (for backward compatibility)
            const database = firebase.database();
            
            // Get a reference to Firestore service (for real-time sync)
            let firestore = null;
            try {
                // Check if Firestore is available (requires firestore SDK to be loaded)
                console.log('🔥 Checking Firestore availability...');
                console.log('  - firebase object:', firebase);
                console.log('  - firebase.firestore type:', typeof firebase.firestore);
                console.log('  - firebase.firestore function:', firebase.firestore);
                
                // Check for Firestore in compat SDK
                if (typeof firebase.firestore === 'function') {
                    try {
                        firestore = firebase.firestore();
                        console.log('✅ Firestore initialized successfully');
                        console.log('  - Firestore instance:', firestore);
                        console.log('  - Firestore.collection type:', typeof firestore.collection);
                        console.log('  - Firestore instance type:', typeof firestore);
                    } catch (initError) {
                        console.error('❌ Error calling firebase.firestore():', initError);
                        // Try alternative: check if firestore is already available
                        if (firebase.firestore && firebase.firestore.app) {
                            firestore = firebase.firestore;
                            console.log('✅ Using firebase.firestore directly');
                        }
                    }
                } else if (firebase.firestore && typeof firebase.firestore === 'object') {
                    // Firestore might already be initialized
                    firestore = firebase.firestore;
                    console.log('✅ Using firebase.firestore object directly');
                } else {
                    console.error('❌ Firestore SDK not loaded! Make sure firebase-firestore-compat.js is loaded before firebase-config.js');
                    console.warn('⚠️ Firestore SDK not loaded. Firestore features will be unavailable.');
                    console.log('Available firebase properties:', Object.keys(firebase));
                    console.log('firebase.firestore value:', firebase.firestore);
                    console.log('firebase.firestore type:', typeof firebase.firestore);
                }
            } catch (firestoreError) {
                console.error('❌ Firestore initialization error:', firestoreError);
                console.error('Error details:', {
                    message: firestoreError.message,
                    stack: firestoreError.stack,
                    name: firestoreError.name
                });
            }
            
            // Export for use in other files
            window.firebaseDatabase = database;
            window.firebaseApp = firebase;
            window.firebaseFirestore = firestore; // Export Firestore if available
            
            // Debug: Log what we exported
            console.log('🔥 Exported to window:', {
                firebaseDatabase: !!window.firebaseDatabase,
                firebaseApp: !!window.firebaseApp,
                firebaseFirestore: !!window.firebaseFirestore,
                firebaseFirestoreType: typeof window.firebaseFirestore
            });
            
            console.log('✅ Firebase initialized successfully');
            console.log('🔥 Realtime Database URL:', firebaseConfig.databaseURL);
            console.log('🔥 Firestore available:', firestore !== null);
            
            // Dispatch event to notify other scripts
            window.dispatchEvent(new CustomEvent('firebase-ready'));
            if (firestore) {
                console.log('🔥 Dispatching firestore-ready event');
                window.dispatchEvent(new CustomEvent('firestore-ready'));
            } else {
                console.warn('⚠️ Not dispatching firestore-ready event (firestore is null)');
            }
        } catch (error) {
            console.error('❌ Firebase initialization error:', error);
        }
    } else {
        console.warn('⚠️ Firebase SDK not loaded yet. Retrying in 100ms...');
        // Retry after a short delay
        setTimeout(initializeFirebase, 100);
    }
})();

