/**
 * Firebase Config Updater
 * 
 * This script helps you update firebase-config.js with values from Firebase Console
 * 
 * Instructions:
 * 1. Go to: https://console.firebase.google.com/u/0/project/superbet-830b0/settings/general
 * 2. Scroll to "Your apps" section
 * 3. Add a web app if needed, or click on existing web app
 * 4. Copy the firebaseConfig values
 * 5. Update the values below and run this script
 */

const newConfig = {
    apiKey: "PASTE_API_KEY_HERE",
    authDomain: "superbet-830b0.firebaseapp.com",
    databaseURL: "https://superbet-830b0-default-rtdb.firebaseio.com", // Get from Realtime Database
    projectId: "superbet-830b0",
    storageBucket: "superbet-830b0.appspot.com",
    messagingSenderId: "872779929847", // From projects:list output
    appId: "PASTE_APP_ID_HERE"
};

console.log('Update js/firebase-config.js with these values:');
console.log(JSON.stringify(newConfig, null, 2));

