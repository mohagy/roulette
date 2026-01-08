# Firebase CLI Setup Instructions

## Step 1: Login to Firebase CLI

Open a terminal/command prompt and run:

```bash
firebase login
```

This will open a browser. Log in with **nathonheart@gmail.com**.

## Step 2: Set the Project

After logging in, navigate to your project directory and run:

```bash
cd C:\xampp2\htdocs\slipp
firebase use roulette-2f902
```

## Step 3: Create Realtime Database

In Firebase Console:
1. Go to https://console.firebase.google.com/
2. Select project: **roulette-2f902**
3. Click on "Realtime Database" in the left menu
4. Click "Create Database"
5. Choose location (e.g., `us-central1` or closest to you)
6. Start in **test mode** (we'll update rules next)

## Step 4: Get Firebase Configuration

1. In Firebase Console, click the gear icon ⚙️ next to "Project Overview"
2. Select "Project settings"
3. Scroll down to "Your apps" section
4. Click the `</>` (Web) icon to add a web app
5. Register app name (e.g., "Roulette App")
6. Copy the `firebaseConfig` object

## Step 5: Update firebase-config.js

Edit `js/firebase-config.js` and replace the placeholder values with your actual config:

```javascript
const firebaseConfig = {
    apiKey: "AIza...",  // From Firebase Console
    authDomain: "roulette-2f902.firebaseapp.com",
    databaseURL: "https://roulette-2f902-default-rtdb.firebaseio.com",  // From Realtime Database
    projectId: "roulette-2f902",
    storageBucket: "roulette-2f902.appspot.com",
    messagingSenderId: "522854471260",  // Your project number
    appId: "1:522854471260:web:..."  // From Firebase Console
};
```

**Important:** The `databaseURL` should be from your Realtime Database, not Firestore. It will look like:
`https://roulette-2f902-default-rtdb.firebaseio.com/`

## Step 6: Deploy Security Rules

After setting up the database, deploy the security rules:

```bash
firebase deploy --only database
```

This will deploy the rules from `firebase-security-rules.json`.

## Step 7: Verify Setup

1. Open `http://localhost/slipp/index.php` in your browser
2. Open browser console (F12)
3. Check for "Firebase initialized successfully" message
4. Check for any errors

## Quick Commands Reference

```bash
# Login
firebase login

# Set project
firebase use roulette-2f902

# Deploy database rules
firebase deploy --only database

# View current project
firebase projects:list

# Check current project
firebase use
```

## Troubleshooting

### Database URL not found
- Make sure you created a **Realtime Database** (not just Firestore)
- The URL format is: `https://PROJECT_ID-default-rtdb.firebaseio.com/`

### Rules deployment fails
- Make sure you're logged in: `firebase login`
- Make sure project is set: `firebase use roulette-2f902`
- Check that `firebase-security-rules.json` exists

### Firebase not initializing
- Check browser console for errors
- Verify all config values are correct
- Make sure Firebase SDK scripts are loaded before `firebase-config.js`

