# Firebase Setup Instructions

## Overview
This application has been migrated to use Firebase Realtime Database for real-time synchronization between the main application (`index.php`) and the TV display (`tvdisplay`).

## Prerequisites
- A Firebase project with Realtime Database enabled
- Firebase project credentials (API key, project ID, etc.)

## Setup Steps

### 1. Configure Firebase Credentials

Edit `js/firebase-config.js` and replace the placeholder values with your actual Firebase project credentials:

```javascript
const firebaseConfig = {
    apiKey: "YOUR_API_KEY",
    authDomain: "YOUR_PROJECT_ID.firebaseapp.com",
    databaseURL: "https://YOUR_PROJECT_ID-default-rtdb.firebaseio.com",
    projectId: "YOUR_PROJECT_ID",
    storageBucket: "YOUR_PROJECT_ID.appspot.com",
    messagingSenderId: "YOUR_MESSAGING_SENDER_ID",
    appId: "YOUR_APP_ID"
};
```

You can find these values in your Firebase Console:
1. Go to Firebase Console (https://console.firebase.google.com/)
2. Select your project
3. Click the gear icon ⚙️ next to "Project Overview"
4. Select "Project settings"
5. Scroll down to "Your apps" section
6. Copy the configuration values

### 2. Set Up Firebase Realtime Database

1. In Firebase Console, go to "Realtime Database"
2. Click "Create Database"
3. Choose your location
4. Start in "test mode" (we'll update security rules next)

### 3. Configure Security Rules

1. In Firebase Console, go to "Realtime Database" > "Rules"
2. Copy the contents of `firebase-security-rules.json`
3. Paste into the rules editor
4. Click "Publish"

**Note:** The current rules allow read/write access to game data. For production, you may want to add authentication requirements.

### 4. Database Structure

The Firebase Realtime Database uses the following structure:

```
/
├── gameState/
│   ├── current/          # Current game state
│   └── drawInfo/         # Draw number information
├── draws/
│   └── {drawNumber}/     # Individual draw results
├── analytics/
│   └── current/          # Analytics data
├── bettingSlips/
│   └── {slipId}/         # Betting slips
├── bets/
│   └── {betId}/          # Individual bets
├── users/
│   └── {userId}/         # User data
└── transactions/
    └── {transactionId}/  # Transactions
```

### 5. Testing

1. Open `http://localhost/slipp/index.php` in one browser tab
2. Open `http://localhost/slipp/tvdisplay/index.html` in another tab
3. Update draw numbers in the main application
4. Verify that the TV display updates in real-time without polling

### 6. Migration from MySQL (Optional)

If you want to migrate existing data from MySQL to Firebase:

1. The system will automatically use Firebase for new operations
2. Historical MySQL data remains accessible for reference
3. You can create a migration script if needed (see `php/migrate_to_firebase.php` - to be created if needed)

## Features

### Real-time Synchronization
- Draw updates from `index.php` automatically sync to `tvdisplay` in real-time
- No need for `tvdisplay` to be running for updates to be saved
- Multiple clients can connect and receive updates simultaneously

### Offline Support
- Firebase SDK automatically queues writes when offline
- Queued operations are processed when connection is restored
- Connection status is displayed in the UI

### Fallback Support
- If Firebase is unavailable, the system falls back to MySQL/SSE/polling
- Ensures the application continues to work even if Firebase is down

## Troubleshooting

### Firebase not connecting
- Check that `firebase-config.js` has correct credentials
- Verify Firebase Realtime Database is enabled in your project
- Check browser console for error messages

### Updates not syncing
- Check Firebase connection status indicator
- Verify security rules allow read/write access
- Check browser console for errors

### Offline mode not working
- Ensure Firebase SDK is loaded correctly
- Check that offline persistence is enabled (it's enabled by default)

## Security Considerations

For production deployment:
1. Implement Firebase Authentication
2. Update security rules to require authentication
3. Restrict write access to authorized users only
4. Consider using Firebase App Check for additional security

## Support

For issues or questions:
- Check browser console for error messages
- Verify Firebase project configuration
- Ensure all required files are loaded in correct order

