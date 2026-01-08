# 🎯 Cashier Draw Display Feature

## Overview

The Cashier Draw Display is a floating, movable panel that shows draw number information on the main cashier interface. It provides real-time synchronization of draw numbers to ensure cashiers always know which draw number will be assigned to new betting slips.

## Features

### 📊 **Draw Number Display**
- **Upcoming Draw Number**: Shows the draw number that will be assigned to new betting slips
- **Last Completed Draw**: Shows the most recently completed draw for reference
- **Real-time Updates**: Automatically syncs with the system every 2 seconds
- **Visual Indicators**: Color-coded display with status indicators

### 🎛️ **User Interface**
- **Floating Panel**: Positioned as a movable window that doesn't interfere with betting operations
- **Drag & Drop**: Cashiers can move the panel to their preferred location
- **Collapsible**: Can be minimized to save screen space
- **Resizable**: Adjustable size to fit different screen layouts
- **Persistent Settings**: Remembers position, size, and collapsed state

### 🔄 **Synchronization**
- **One-way Sync**: Receives data from TV display and database (no bidirectional conflicts)
- **Multiple Sources**: Falls back to different APIs if primary source is unavailable
- **Error Handling**: Shows connection status and retry mechanisms
- **Cache Prevention**: Always fetches fresh data to prevent stale information

## Technical Implementation

### Files Added

#### CSS Styling
- `css/cashier-draw-display.css` - Complete styling for the floating panel

#### JavaScript Modules
- `js/cashier-draw-display.js` - Main display functionality
- `js/cashier-draw-integration.js` - Integration with existing betting system

#### API Endpoint
- `api/cashier_draw_sync.php` - Dedicated API for cashier draw information

#### Testing
- `test_cashier_draw_display.html` - Comprehensive test page

### Integration Points

#### Main Cashier Interface (`index.html`)
```html
<!-- CSS -->
<link rel="stylesheet" href="css/cashier-draw-display.css">

<!-- JavaScript -->
<script src="js/cashier-draw-display.js"></script>
<script src="js/cashier-draw-integration.js"></script>
```

#### API Response Format
```json
{
  "status": "success",
  "data": {
    "last_completed_draw": 137,
    "next_draw_for_betting": 138,
    "upcoming_draw": 138,
    "current_completed_draw": 137,
    "system_status": {
      "total_completed_draws": 47,
      "last_draw_time": "2025-01-24 18:30:00",
      "system_time": "2025-01-24 18:32:15",
      "timezone": "UTC"
    }
  },
  "message": "Draw information retrieved successfully",
  "timestamp": 1737745935,
  "server_time": "2025-01-24 18:32:15"
}
```

## Configuration

### Display Settings
```javascript
const config = {
    debug: true,
    syncInterval: 2000, // Sync every 2 seconds
    apiEndpoint: 'api/cashier_draw_sync.php',
    fallbackEndpoint: 'api/tv_sync.php'
};
```

### Customization Options
- **Position**: Saved to localStorage, defaults to top-right
- **Size**: Adjustable with resize handles
- **Collapse State**: Remembers user preference
- **Sync Frequency**: Configurable update interval

## Usage Instructions

### For Cashiers

1. **Viewing Draw Numbers**
   - The panel shows two key numbers:
     - **Green number**: Next draw for new betting slips
     - **Blue number**: Last completed draw

2. **Moving the Panel**
   - Click and drag the header to reposition
   - The panel will remember your preferred location

3. **Minimizing**
   - Click the chevron button to collapse/expand
   - Saves screen space when not actively needed

4. **Understanding Status**
   - **Green dot**: Connected and syncing
   - **Yellow dot**: Syncing in progress
   - **Red dot**: Connection error

### For Administrators

1. **Monitoring**
   - Check the test page: `test_cashier_draw_display.html`
   - Monitor API responses and sync status
   - Review logs in `logs/cashier_draw_sync.log`

2. **Troubleshooting**
   - Force sync if numbers seem outdated
   - Check API endpoints are responding
   - Verify database connectivity

## Data Flow

```
Database (detailed_draw_results)
    ↓
API (cashier_draw_sync.php)
    ↓
Cashier Display (cashier-draw-display.js)
    ↓
Integration Layer (cashier-draw-integration.js)
    ↓
Betting Slip System
```

### Draw Number Logic

1. **Last Completed Draw**: `MAX(draw_number)` from `detailed_draw_results` table
2. **Upcoming Draw**: Last completed draw + 1
3. **Validation**: Ensures upcoming > completed to prevent past draw assignments

## Security & Reliability

### Data Integrity
- **Authoritative Source**: Uses `detailed_draw_results` as single source of truth
- **No Fabricated Data**: Only real, completed draws are processed
- **Validation**: Ensures logical draw number progression

### Error Handling
- **Graceful Degradation**: Falls back to alternative APIs
- **User Feedback**: Clear status indicators and error messages
- **Retry Logic**: Automatic retry on connection failures
- **Logging**: Comprehensive error logging for debugging

### Performance
- **Efficient Polling**: 2-second intervals balance freshness with performance
- **Minimal DOM Updates**: Only updates when data actually changes
- **Local Storage**: Caches settings to reduce server requests

## Browser Compatibility

- **Modern Browsers**: Chrome 60+, Firefox 55+, Safari 12+, Edge 79+
- **Mobile Support**: Responsive design for tablet interfaces
- **Touch Support**: Drag and resize work on touch devices

## Maintenance

### Regular Tasks
1. **Log Rotation**: Monitor and rotate `logs/cashier_draw_sync.log`
2. **Database Cleanup**: Ensure `detailed_draw_results` table is maintained
3. **Performance Monitoring**: Check API response times

### Updates
- **CSS**: Modify `css/cashier-draw-display.css` for styling changes
- **Functionality**: Update `js/cashier-draw-display.js` for feature changes
- **API**: Modify `api/cashier_draw_sync.php` for data source changes

## Testing

### Manual Testing
1. Open `test_cashier_draw_display.html`
2. Test all API endpoints
3. Verify draw number updates
4. Test drag/resize functionality

### Automated Testing
- API response validation
- Draw number logic verification
- Error handling scenarios

## Support

### Common Issues

1. **Display Not Appearing**
   - Check browser console for JavaScript errors
   - Verify CSS file is loaded
   - Ensure DOM is fully loaded before initialization

2. **Numbers Not Updating**
   - Check API endpoint accessibility
   - Verify database connectivity
   - Force sync using test page

3. **Position Not Saving**
   - Check localStorage permissions
   - Clear browser cache if corrupted
   - Reset to default position

### Debug Mode
Enable debug logging by setting `config.debug = true` in the JavaScript files.

---

**🎯 The Cashier Draw Display ensures cashiers always have accurate, real-time draw number information for optimal betting slip management.**
