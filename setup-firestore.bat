@echo off
echo ========================================
echo Firebase CLI Setup for superbet-830b0
echo ========================================
echo.

echo Step 1: Checking Firebase CLI installation...
firebase --version
if %errorlevel% neq 0 (
    echo Firebase CLI not found. Installing...
    npm install -g firebase-tools
)

echo.
echo Step 2: Logging in to Firebase...
echo This will open a browser - please log in with your Google account
firebase login

echo.
echo Step 3: Setting project to superbet-830b0...
firebase use superbet-830b0

echo.
echo Step 4: Initializing Firestore...
echo When prompted:
echo   - Firestore Rules file: Press Enter (default: firestore.rules)
echo   - Firestore Indexes file: Press Enter (default: firestore.indexes.json)
echo   - Use existing rules: Type 'n' and press Enter
echo.
pause
firebase init firestore

echo.
echo Step 5: Deploying Firestore rules...
firebase deploy --only firestore:rules

echo.
echo ========================================
echo Setup Complete!
echo ========================================
echo.
echo Next steps:
echo 1. Go to: https://console.firebase.google.com/u/0/project/superbet-830b0/settings/general
echo 2. Scroll to "Your apps" section
echo 3. Add a web app if needed
echo 4. Copy the firebaseConfig values
echo 5. Update js/firebase-config.js with the actual values
echo.
pause

