# Firebase Firestore Security Rules Setup

## Current Issue: Permission Denied

You're seeing `FirebaseError: Missing or insufficient permissions` because Firestore security rules need to be configured.

## Solution: Configure Firestore Security Rules

### Option 1: Allow Public Read Access (For Development/Testing)

1. Go to Firebase Console: https://console.firebase.google.com/project/superbet-830b0/firestore
2. Click on **Rules** tab
3. Replace the rules with:

```javascript
rules_version = '2';
service cloud.firestore {
  match /databases/{database}/documents {
    // Monitoring collections - allow read/write for authenticated users
    // For testing, you can temporarily allow public read access
    
    // Alerts collection
    match /monitoring_alerts/{alertId} {
      allow read: if request.auth != null || true; // Allow read if authenticated OR public (testing)
      allow write: if request.auth != null; // Only authenticated users can write
    }
    
    // Shops collection
    match /monitoring_shops/{shopId} {
      allow read: if request.auth != null || true; // Allow read if authenticated OR public (testing)
      allow write: if request.auth != null; // Only authenticated users can write
    }
    
    // Stats collection
    match /monitoring_stats/{document=**} {
      allow read: if request.auth != null || true; // Allow read if authenticated OR public (testing)
      allow write: if request.auth != null; // Only authenticated users can write
    }
    
    // Payouts collection
    match /monitoring_payouts/{payoutId} {
      allow read: if request.auth != null || true; // Allow read if authenticated OR public (testing)
      allow write: if request.auth != null; // Only authenticated users can write
    }
    
    // Deny all other access
    match /{document=**} {
      allow read, write: if false;
    }
  }
}
```

4. Click **Publish**

### Option 2: Require Authentication (Recommended for Production)

```javascript
rules_version = '2';
service cloud.firestore {
  match /databases/{database}/documents {
    // Monitoring collections - require authentication
    match /monitoring_alerts/{alertId} {
      allow read, write: if request.auth != null;
    }
    
    match /monitoring_shops/{shopId} {
      allow read, write: if request.auth != null;
    }
    
    match /monitoring_stats/{document=**} {
      allow read, write: if request.auth != null;
    }
    
    match /monitoring_payouts/{payoutId} {
      allow read, write: if request.auth != null;
    }
    
    // Deny all other access
    match /{document=**} {
      allow read, write: if false;
    }
  }
}
```

Then update `monitoring/js/auth.js` to use Firebase Authentication instead of simple username/password.

### Option 3: Use API Fallback Only (No Firebase Required)

The dashboard already falls back to API calls when Firebase permissions are denied. You can:

1. **Use the dashboard without Firebase** - It will show empty states and work with API fallback
2. **Remove Firebase listeners** - The Analytics, Draw Monitor, and Maintenance tabs work without Firebase

To disable Firebase listeners completely, comment out `AlertsModule.init()` in `dashboard.js`:

```javascript
async loadDashboardTab() {
    // Initialize alerts
    // AlertsModule.init(); // Commented out - using API only
}
```

## Testing Security Rules

After updating rules:

1. **Wait 1-2 minutes** for rules to propagate
2. **Refresh the dashboard** (hard refresh: Ctrl+F5)
3. **Check browser console** - permission errors should stop
4. **Verify data** - Collections should be readable if rules are correct

## Creating Required Indexes

If you see index errors, Firebase will provide a link to create them automatically. Or create manually:

1. Go to Firestore → **Indexes** tab
2. Click **Create Index**
3. Collection: `monitoring_alerts`
4. Fields: `status` (Ascending), `created_at` (Descending)
5. Click **Create**

## Current Status

- ✅ **Dashboard is working** - Permission errors are handled gracefully
- ✅ **API fallback works** - Dashboard shows empty states when Firebase is unavailable
- ⚠️ **Firebase needs rules** - Update security rules to enable real-time features
- ✅ **Other tabs work** - Analytics, Draw Monitor, Maintenance don't need Firebase

## Quick Fix (Temporary)

For immediate testing, use **Option 1** with `|| true` in the rules to allow public read access temporarily. Remember to change this to require authentication for production!

