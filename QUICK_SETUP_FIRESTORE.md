# Quick Setup: Connect to superbet-830b0 Firestore

## ✅ What's Already Done

1. ✅ Firebase CLI connected to `superbet-830b0`
2. ✅ Firestore rules deployed
3. ✅ Project configuration updated

## 🔧 What You Need to Do

### Step 1: Get Firebase Config Values

1. **Open Firebase Console:**
   https://console.firebase.google.com/u/0/project/superbet-830b0/settings/general

2. **Scroll to "Your apps" section**

3. **If no web app exists:**
   - Click "Add app" → Select Web (`</>`)
   - App nickname: "Roulette TV Display"
   - Click "Register app"

4. **Copy the config values:**
   - You'll see a `firebaseConfig` object
   - Copy the `apiKey` and `appId` values

5. **Get Realtime Database URL:**
   - Go to: https://console.firebase.google.com/u/0/project/superbet-830b0/database
   - If Realtime Database doesn't exist, create it
   - Copy the database URL

### Step 2: Update js/firebase-config.js

Edit `js/firebase-config.js` and replace:
- `YOUR_API_KEY_HERE` → Your actual API key
- `YOUR_APP_ID_HERE` → Your actual App ID
- Update `databaseURL` if different

### Step 3: Enable Firestore (if not already enabled)

1. Go to: https://console.firebase.google.com/u/0/project/superbet-830b0/firestore
2. If you see "Create database" or "Add database", click it
3. Choose location: `nam5` (or closest to you)
4. Start in **test mode**
5. Click "Enable"

### Step 4: Test

1. Refresh your TV display page: `http://localhost/slipp/tvdisplay/`
2. Open browser console (F12)
3. Look for:
   - `✅ Firestore initialized successfully`
   - `✅ FirestoreService initialized successfully`
   - `🔥 Firestore available, setting up listeners...`

### Step 5: Verify Data Appears in Console

1. Go to: https://console.firebase.google.com/u/0/project/superbet-830b0/firestore/data
2. Set a winning number from `bet_distribution.php`
3. You should see data appear in:
   - `spinCommands` collection
   - `winningNumbers` collection
   - `gameState/current` document

## 🐛 Troubleshooting

**If Firestore still doesn't work:**

1. Check browser console for errors
2. Verify Firestore is enabled in console
3. Check that `firebase-firestore-compat.js` is loading (check Network tab)
4. Run diagnostic: Open `http://localhost/slipp/tvdisplay/firestore-diagnostic.html`

## 📝 Current Status

- ✅ Firebase CLI: Connected to superbet-830b0
- ✅ Firestore Rules: Deployed
- ⚠️ Firebase Config: Needs API key and App ID from console
- ⚠️ Firestore: Needs to be enabled in console (if not already)

