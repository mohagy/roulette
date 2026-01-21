# Get Firebase Configuration for superbet-830b0

## Step 1: Get Firebase Config from Console

1. **Go to Firebase Console:**
   https://console.firebase.google.com/u/0/project/superbet-830b0/settings/general

2. **Scroll down to "Your apps" section**

3. **If no web app exists:**
   - Click "Add app" button
   - Select Web icon (`</>`)
   - Register app name: "Roulette TV Display"
   - Click "Register app"

4. **Copy the `firebaseConfig` object** - it will look like:
   ```javascript
   const firebaseConfig = {
     apiKey: "AIza...",
     authDomain: "superbet-830b0.firebaseapp.com",
     projectId: "superbet-830b0",
     storageBucket: "superbet-830b0.appspot.com",
     messagingSenderId: "872779929847",
     appId: "1:872779929847:web:..."
   };
   ```

5. **Get Realtime Database URL:**
   - Go to: https://console.firebase.google.com/u/0/project/superbet-830b0/database
   - Click on "Realtime Database" (if not already created, create it)
   - Copy the database URL (e.g., `https://superbet-830b0-default-rtdb.firebaseio.com`)

## Step 2: Update firebase-config.js

Edit `js/firebase-config.js` and replace the placeholder values with the actual values from Step 1.

## Step 3: Verify Firestore is Enabled

1. Go to: https://console.firebase.google.com/u/0/project/superbet-830b0/firestore
2. If you see "Add database" or "Create database", click it
3. Choose location: `nam5` (or closest to you)
4. Start in **test mode** (we'll deploy rules via CLI)
5. Click "Enable"

## Step 4: Test Connection

After updating the config, refresh your TV display page and check the browser console for Firestore initialization messages.

