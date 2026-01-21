# API Debugging Guide

## Current Issue: API Calls Stuck on "Loading..."

All API endpoints exist but calls are not completing.

## Quick Tests

### 1. Test API Directly in Browser

Open these URLs directly in your browser:

1. **Current Draw**: 
   - http://localhost/slipp/api/get_current_draw.php
   - Should return JSON: `{"status":"success",...}`

2. **Analytics History**:
   - http://localhost/slipp/api/get_analytics_history.php?limit=20
   - Should return JSON with draws array

3. **Preset Schedule**:
   - http://localhost/slipp/api/load_preset_schedule.php
   - Should return JSON with schedule data

### 2. Check Browser Console

Open Developer Tools (F12) and check:

**Console Tab:**
- Look for error messages (red)
- Look for "Fetching:" messages (added for debugging)
- Look for "Fetch success" or "Fetch error" messages

**Network Tab:**
- Filter by "XHR" or "Fetch"
- Look for failed requests (red)
- Click on requests to see:
  - Request URL
  - Response status
  - Response headers
  - Response body

### 3. Common Issues

#### Issue: CORS Errors
**Symptoms**: Console shows "CORS policy" error

**Fix**: Add CORS headers to API PHP files:
```php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
```

#### Issue: PHP Errors
**Symptoms**: API returns PHP error instead of JSON

**Fix**: Check PHP error logs:
- XAMPP: `C:\xampp\apache\logs\error.log`
- Or enable error display temporarily

#### Issue: Database Connection Errors
**Symptoms**: API returns `{"status":"error","message":"Database..."}`

**Fix**: Check `php/db_connect.php` database credentials

#### Issue: Slow Response/Timeout
**Symptoms**: Requests take > 10 seconds

**Fix**: 
- Check database query performance
- Check server load
- Increase timeout in `utils.js` (currently 10 seconds)

### 4. Check XAMPP Status

Verify XAMPP services are running:
- ✅ Apache should be running
- ✅ MySQL should be running

### 5. Enable Debug Logging

The code now logs to console:
- `Fetching: [URL]` - When request starts
- `Fetch success: [URL] [status]` - When request succeeds
- `Fetch error: [URL] [message]` - When request fails

Check browser console for these messages.

## Next Steps

1. **Open browser console** (F12)
2. **Refresh dashboard** (Ctrl+F5)
3. **Check console messages** - Look for "Fetching:" and error messages
4. **Check Network tab** - See which API calls are failing
5. **Test APIs directly** - Open API URLs in browser

This will help identify the exact issue!

