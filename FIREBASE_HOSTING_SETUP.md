# Firebase Hosting Setup for TV Display

This guide explains how to deploy the TV Display to Firebase Hosting so it runs independently and automatically.

## Overview

The TV Display will be hosted on Firebase Hosting, allowing it to:
- Run independently of your local machine
- Spin automatically based on countdown timer
- Read winning numbers from Firebase Realtime Database
- Work even when `index.php` is closed

## Setup Steps

### 1. Deploy to Firebase Hosting

```bash
# Make sure you're logged in
firebase login

# Deploy hosting
firebase deploy --only hosting
```

### 2. Access Your Hosted TV Display

After deployment, you'll get a URL like:
```
https://roulette-2f902.web.app
```

Or your custom domain if configured.

### 3. How It Works

1. **Automatic Spinning**: The countdown timer automatically triggers a spin when it reaches zero
2. **Firebase Data**: The wheel reads winning numbers from Firebase Realtime Database (saved by `index.php`)
3. **Real-time Sync**: When `index.php` saves a draw result, the TV Display automatically receives it
4. **Independent Operation**: The TV Display runs on Firebase servers, not your local machine

## Configuration

The `firebase.json` file is configured to:
- Host the `tvdisplay` folder as the public directory
- Ignore PHP files, sounds, and other unnecessary files
- Set proper cache headers for real-time updates

## Important Notes

- The TV Display will **read** winning numbers from Firebase, not generate them
- The `index.php` page should be running to save draw results to Firebase
- If `index.php` is closed, the TV Display will wait for new draw results
- The countdown timer continues running independently

## Troubleshooting

If the TV Display doesn't get winning numbers:
1. Check that `index.php` is saving to Firebase (check browser console)
2. Verify Firebase Realtime Database has data
3. Check browser console on TV Display for Firebase connection status

