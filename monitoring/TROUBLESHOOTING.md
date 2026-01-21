# Troubleshooting Guide - Monitoring Dashboard

## Issue: "Loading..." Messages Never Complete

If you see sections stuck on "Loading...", it usually means API calls are failing.

### Quick Diagnosis

1. **Open Browser Console** (F12 → Console tab)
2. **Check for Errors**: Look for red error messages
3. **Check Network Tab**: F12 → Network → Look for failed requests (red)

### Common Causes

#### 1. API Endpoints Not Accessible

**Symptoms**: All API calls show "Loading..." forever

**Solution**: Verify API endpoints exist and are accessible:
- Test: `http://localhost/slipp/api/get_current_draw.php`
- Should return JSON: `{"status":"success",...}`

**Fix**:
```bash
# Check if files exist
ls -la api/get_current_draw.php
ls -la api/get_analytics_history.php
ls -la api/load_preset_schedule.php
```

#### 2. CORS Errors

**Symptoms**: Console shows "CORS policy" errors

**Solution**: APIs should allow localhost. Check PHP files have proper headers:
```php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
```

#### 3. Database Connection Errors

**Symptoms**: API returns `{"status":"error","message":"Database error..."}`

**Solution**: Check database connection in `php/db_connect.php`

#### 4. Wrong API Base URL

**Symptoms**: 404 errors in Network tab

**Solution**: Check `monitoring/js/utils.js`:
```javascript
const API_BASE = 'http://localhost/slipp/api'; // Should match your setup
```

### Testing API Endpoints Manually

Open these URLs in your browser:

1. **Current Draw**: http://localhost/slipp/api/get_current_draw.php
2. **Analytics**: http://localhost/slipp/api/get_analytics_history.php?limit=20
3. **Preset Schedule**: http://localhost/slipp/api/load_preset_schedule.php

**Expected Response**: JSON with `{"status":"success",...}`

### Fixing Loading States

The dashboard now shows error messages instead of infinite loading. After fixing API issues:

1. **Refresh the page** (hard refresh: Ctrl+F5)
2. **Check console** for specific error messages
3. **Check Network tab** to see which API is failing

### Step-by-Step Debugging

1. **Check Browser Console**:
   - Open F12 → Console
   - Look for error messages
   - Copy error messages

2. **Check Network Requests**:
   - Open F12 → Network
   - Refresh page
   - Find failed requests (red)
   - Click to see response

3. **Test API Directly**:
   - Open API URL in browser
   - Check if JSON is returned
   - Verify database connection

4. **Verify File Paths**:
   - Ensure `api/` folder exists
   - Ensure PHP files exist
   - Check file permissions

### Current Status Indicators

- ✅ **"No active alerts"** = Working (Firebase empty, but working)
- ⚠️ **"Loading..."** = API call in progress or failed
- ❌ **"API Error"** = API endpoint not accessible
- ✅ **Data displayed** = Everything working

### Quick Fixes

#### If All APIs Failing:

1. **Check XAMPP is running**
2. **Check PHP is working**: Create `test.php` with `<?php phpinfo(); ?>`
3. **Check database connection**: Test `php/db_connect.php`

#### If Specific API Failing:

1. **Get Current Draw**: Check `api/get_current_draw.php` exists
2. **Analytics History**: Check `api/get_analytics_history.php` exists  
3. **Preset Schedule**: Check `api/load_preset_schedule.php` exists

### Getting Help

When reporting issues, include:
1. Browser console errors (F12 → Console)
2. Network tab failed requests (F12 → Network)
3. API response when accessed directly
4. XAMPP/PHP version

