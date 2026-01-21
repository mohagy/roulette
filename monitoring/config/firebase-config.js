/**
 * Firebase Configuration for Monitoring App
 * Uses existing Firebase project: superbet-830b0
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
let firebaseApp = null;
let firestore = null;
let auth = null;

(function initializeFirebase() {
    if (typeof firebase !== 'undefined') {
        try {
            // Initialize Firebase app
            if (!firebase.apps.length) {
                firebaseApp = firebase.initializeApp(firebaseConfig);
            } else {
                firebaseApp = firebase.app();
            }
            
            // Initialize Firestore
            if (typeof firebase.firestore === 'function') {
                firestore = firebase.firestore();
            }
            
            // Initialize Auth
            if (typeof firebase.auth === 'function') {
                auth = firebase.auth();
            }
            
            // Export to window
            window.firebaseApp = firebaseApp;
            window.firestore = firestore;
            window.firebaseAuth = auth;
            
            console.log('✅ Firebase initialized for monitoring app');
            
            // Dispatch ready event
            window.dispatchEvent(new CustomEvent('firebase-ready'));
            if (firestore) {
                window.dispatchEvent(new CustomEvent('firestore-ready'));
            }
        } catch (error) {
            console.error('❌ Firebase initialization error:', error);
        }
    } else {
        console.warn('⚠️ Firebase SDK not loaded yet. Retrying...');
        setTimeout(initializeFirebase, 100);
    }
})();

