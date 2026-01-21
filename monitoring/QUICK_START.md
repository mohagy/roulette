# Quick Start Guide - Monitoring Dashboard

## Current Status: Firebase Collections Empty

The dashboard is working correctly, but Firebase Firestore collections are empty. This is expected if you haven't run the sync script yet.

## What You're Seeing

- **Total Active Alerts: 0** - No alerts in Firebase (expected)
- **Critical Alerts: 0** - No critical alerts (expected)
- **Today's Payouts: $0.00** - No stats in Firebase (expected)
- **Shops Monitoring: 0** - No shop data in Firebase (expected)
- **Loading alerts...** / **Loading shop data...** - Waiting for Firebase data

## Next Steps

### Option 1: Use Without Firebase (Current State)
The dashboard works fine even with empty Firebase collections. You'll see:
- ✅ "No active alerts. All systems operational."
- ✅ "No shop performance data available. Firebase collections may be empty."

### Option 2: Populate Firebase (Recommended for Real-time Features)

1. **Create the database table:**
   ```bash
   # Run the SQL script
   mysql -u root -p roulette < sql/create_monitoring_alerts_table.sql
   ```

2. **Set up Firebase Firestore:**
   - Go to Firebase Console: https://console.firebase.google.com/project/superbet-830b0/firestore
   - Create these collections:
     - `monitoring_alerts` (collection)
     - `monitoring_shops` (collection)
     - `monitoring_stats` (collection, with document ID: `live`)

3. **Run the sync script:**
   ```bash
   # Via browser or cron job
   http://localhost/slipp/api/monitoring/sync_to_firebase.php
   ```

### Option 3: Test with Sample Data

You can manually add test data to Firebase Firestore:

1. Go to Firebase Console → Firestore Database
2. Create collection `monitoring_alerts`
3. Add a test alert:
   ```json
   {
     "alert_id": 1,
     "alert_type": "large_payout",
     "severity": "high",
     "title": "Large Payout Detected",
     "description": "Test alert for monitoring",
     "status": "new",
     "created_at": "2026-01-17T10:00:00Z"
   }
   ```

4. Create collection `monitoring_shops`
5. Add test shop:
   ```json
   {
     "shop_id": 1,
     "shop_name": "Test Shop",
     "today_bets": 1000,
     "today_payouts": 500,
     "active_alerts": 0
   }
   ```

6. Create collection `monitoring_stats` → document `live`:
   ```json
   {
     "total_active_alerts": 1,
     "critical_alerts": 0,
     "today_payouts_total": 500,
     "shops_under_monitoring": 1
   }
   ```

## Testing Other Tabs

The **Analytics**, **Draw Monitor**, and **Maintenance** tabs work independently and fetch data directly from your API:

- ✅ **Analytics Tab**: Should work immediately (uses `/api/get_analytics_history.php`)
- ✅ **Draw Monitor Tab**: Should work immediately (uses `/api/get_current_draw.php`)
- ✅ **Maintenance Tab**: Should work immediately (checks API endpoints)

## Troubleshooting

### Firebase Not Connecting?
- Check browser console (F12) for errors
- Verify Firebase config in `config/firebase-config.js`
- Check Firebase Console → Project Settings → General → Your apps

### Still Seeing "Loading..."?
- Check browser console for JavaScript errors
- Verify API endpoints are accessible
- Refresh the page after 2-3 seconds

## Summary

The dashboard is **fully functional**. Empty Firebase collections just mean:
- No real-time alerts yet (but the system is monitoring)
- No shop performance data yet (but shops are tracked)
- Stats will populate once sync script runs

You can use all other features (Analytics, Draw Monitor, Maintenance) immediately!

