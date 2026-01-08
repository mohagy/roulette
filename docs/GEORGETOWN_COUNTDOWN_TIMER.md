# Georgetown Countdown Timer Implementation

## 🕒 Overview

A real-time countdown timer synchronized with Georgetown, Guyana timezone (GMT-4/UTC-4) that maintains accuracy across page refreshes and provides consistent timing for roulette betting cycles.

## 📁 Files Created

### Server-Side Components
- **`php/get_georgetown_time.php`** - Georgetown time server endpoint
- **`js/georgetown-countdown-timer.js`** - Client-side countdown timer system

### Testing & Documentation
- **`test-countdown.html`** - Comprehensive test interface
- **`GEORGETOWN_COUNTDOWN_TIMER.md`** - This documentation file

## 🚀 Features Implemented

### ✅ Core Requirements Met

1. **📍 Location**: Timer positioned in bottom center of page
2. **⏱️ Duration**: 3-minute (03:00) countdown cycles with automatic reset
3. **🌍 Timezone**: Synchronized with Georgetown, Guyana (GMT-4/UTC-4)
4. **🔄 Real-time Accuracy**: Server-synchronized time prevents client manipulation
5. **💾 Persistent State**: Resumes correct countdown position after page refresh
6. **🔁 Automatic Reset**: Seamlessly transitions to new cycle at 00:00
7. **📱 Visual Display**: Clean MM:SS format with professional styling

### 🎨 Visual Features

- **Glass morphism design** with backdrop blur effects
- **Dynamic color coding** (white → orange → red as time decreases)
- **Pulsing animation** for critical countdown (≤10 seconds)
- **Hover effects** with scale transformation
- **Sync indicator** showing connection status
- **Mobile responsive** design

### 🔧 Technical Features

- **Server synchronization** every 30 seconds
- **Fallback calculation** using client time with UTC-4 offset
- **Page visibility handling** (syncs when page becomes visible)
- **Error handling** with graceful degradation
- **Debug logging** for troubleshooting
- **Event system** for cycle reset notifications

## 🛠️ Technical Implementation

### Server Endpoint (`php/get_georgetown_time.php`)

```php
// Provides Georgetown time and countdown calculation
GET /php/get_georgetown_time.php
GET /php/get_georgetown_time.php?debug=true  // With debug info
```

**Response Format:**
```json
{
    "status": "success",
    "georgetown_time": {
        "formatted": "2025-05-29 04:47:12",
        "timestamp": 1748508432,
        "timezone": "America/Guyana",
        "offset": "-04:00"
    },
    "countdown": {
        "total_seconds_remaining": 48,
        "display_format": "00:48",
        "cycle_position": 132
    }
}
```

### Client-Side Timer (`js/georgetown-countdown-timer.js`)

**Auto-initialization:**
```javascript
// Automatically initializes when DOM is ready
// Creates timer element and starts synchronization
```

**Manual control:**
```javascript
// Initialize timer
GeorgetownCountdownTimer.initialize();

// Destroy timer
GeorgetownCountdownTimer.destroy();

// Force sync with server
GeorgetownCountdownTimer.forceSync();

// Get current status
const status = GeorgetownCountdownTimer.getStatus();
```

## 🎯 Integration with Existing System

### HTML Integration
- Added script reference to `index.html`
- Removed existing static timer container
- Timer creates its own DOM element dynamically

### CSS Styling
- Self-contained styling with `!important` declarations
- No conflicts with existing stylesheets
- Responsive design for all screen sizes

### JavaScript Compatibility
- Uses module pattern to avoid global namespace pollution
- Provides global access via `window.GeorgetownCountdownTimer`
- Event-driven architecture for cycle notifications

## 🧪 Testing

### Test Interface (`test-countdown.html`)
Access the test interface to verify functionality:
```
http://localhost/slipp/test-countdown.html
```

**Test Features:**
- Timer initialization/destruction controls
- Server endpoint testing
- Real-time status monitoring
- Debug log with timestamps
- Auto-refresh status updates

### Manual Testing Steps

1. **Basic Functionality:**
   - Load `index.html` - timer should appear automatically
   - Verify countdown displays in MM:SS format
   - Check timer position (bottom center)

2. **Persistence Testing:**
   - Note current countdown time
   - Refresh page (F5)
   - Verify timer resumes from correct position

3. **Synchronization Testing:**
   - Open browser developer tools
   - Monitor network requests to `get_georgetown_time.php`
   - Verify sync occurs every 30 seconds

4. **Cycle Reset Testing:**
   - Wait for countdown to reach 00:00
   - Verify automatic reset to 03:00
   - Check console for cycle reset event

## 🔧 Configuration Options

### Timer Configuration
```javascript
const config = {
    cycleDuration: 180,        // 3 minutes in seconds
    syncInterval: 30000,       // Sync every 30 seconds
    updateInterval: 1000,      // Update display every second
    serverEndpoint: 'php/get_georgetown_time.php',
    timezoneOffset: -4,        // Georgetown is UTC-4
    fallbackEnabled: true      // Enable fallback calculation
};
```

### Customization
```javascript
// Update configuration
GeorgetownCountdownTimer.updateConfig({
    syncInterval: 15000  // Sync every 15 seconds instead
});
```

## 🐛 Troubleshooting

### Common Issues

1. **Timer not appearing:**
   - Check browser console for JavaScript errors
   - Verify `georgetown-countdown-timer.js` is loaded
   - Ensure server endpoint is accessible

2. **Incorrect countdown time:**
   - Test server endpoint directly: `/php/get_georgetown_time.php`
   - Check server timezone configuration
   - Verify Georgetown timezone is set correctly

3. **Timer not persisting after refresh:**
   - Check server synchronization
   - Verify network connectivity
   - Test fallback calculation

### Debug Information

**Console Logging:**
```javascript
// Enable debug mode
console.log(GeorgetownCountdownTimer.getStatus());

// Check configuration
console.log(GeorgetownCountdownTimer.getConfig());
```

**Server Endpoint Debug:**
```
GET /php/get_georgetown_time.php?debug=true
```

## 🔄 Event System

### Countdown Cycle Reset Event
```javascript
document.addEventListener('countdownCycleReset', function(event) {
    console.log('New cycle started at:', event.detail.timestamp);
    console.log('Georgetown time:', event.detail.georgetownTime);
});
```

## 📊 Performance Considerations

- **Lightweight**: Minimal DOM manipulation
- **Efficient**: Smart sync scheduling
- **Responsive**: Handles page visibility changes
- **Reliable**: Fallback mechanisms for offline scenarios

## 🔐 Security Features

- **Server-side time calculation** prevents client manipulation
- **Input validation** on all server requests
- **Error handling** prevents information disclosure
- **CORS headers** for secure cross-origin requests

## 🚀 Future Enhancements

Potential improvements for future versions:
- WebSocket integration for real-time updates
- Multiple timezone support
- Custom countdown durations
- Advanced visual effects
- Integration with betting system events
