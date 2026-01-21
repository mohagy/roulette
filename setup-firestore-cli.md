# Firebase CLI Setup for superbet-830b0

## Step 1: Install Firebase CLI (if not already installed)

Open PowerShell or Command Prompt and run:

```bash
npm install -g firebase-tools
```

## Step 2: Login to Firebase

```bash
firebase login
```

This will open a browser. Log in with your Google account that has access to the superbet-830b0 project.

## Step 3: Navigate to Project Directory

```bash
cd C:\xampp2\htdocs\slipp
```

## Step 4: Set Firebase Project

```bash
firebase use superbet-830b0
```

## Step 5: Initialize Firestore (if not already initialized)

```bash
firebase init firestore
```

When prompted:
- **What file should be used for Firestore Rules?** → Press Enter (default: `firestore.rules`)
- **What file should be used for Firestore indexes?** → Press Enter (default: `firestore.indexes.json`)
- **Do you want to use an existing rules file?** → Type `n` (no) if you want to create new rules

## Step 6: Create Firestore Security Rules

Create or update `firestore.rules` file:

```javascript
rules_version = '2';
service cloud.firestore {
  match /databases/{database}/documents {
    // Allow read/write access to spinCommands collection
    match /spinCommands/{commandId} {
      allow read, write: if true;
    }
    
    // Allow read/write access to gameState collection
    match /gameState/{document=**} {
      allow read, write: if true;
    }
    
    // Allow read/write access to winningNumbers collection
    match /winningNumbers/{drawNumber} {
      allow read, write: if true;
    }
    
    // Default: deny all other access
    match /{document=**} {
      allow read, write: if false;
    }
  }
}
```

## Step 7: Deploy Firestore Rules

```bash
firebase deploy --only firestore:rules
```

## Step 8: Get Firebase Configuration from Console

1. Go to: https://console.firebase.google.com/u/0/project/superbet-830b0/settings/general
2. Scroll down to "Your apps" section
3. If no web app exists, click "Add app" > Web (</> icon)
4. Register app name: "Roulette TV Display"
5. Copy the `firebaseConfig` object
6. Update `js/firebase-config.js` with the actual values

## Step 9: Verify Firestore is Enabled

1. Go to: https://console.firebase.google.com/u/0/project/superbet-830b0/firestore
2. If you see "Add database", click it
3. Choose "Start in test mode" (we'll update rules via CLI)
4. Select location: `nam5` (North America - multi-region) or closest to you
5. Click "Enable"

## Step 10: Test Firestore Connection

After completing the above steps, refresh your TV display page and check the browser console for:
- `✅ Firestore initialized successfully`
- `✅ FirestoreService initialized successfully`
- `🔥 Firestore available, setting up listeners...`

## Troubleshooting

If Firestore still doesn't work:

1. **Check if Firestore is enabled:**
   ```bash
   firebase firestore:indexes
   ```

2. **Verify project connection:**
   ```bash
   firebase projects:list
   firebase use superbet-830b0
   ```

3. **Check Firestore rules:**
   ```bash
   firebase firestore:rules:get
   ```

4. **View Firestore data:**
   - Go to: https://console.firebase.google.com/u/0/project/superbet-830b0/firestore/data

