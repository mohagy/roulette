# 🎯 Upcoming Draws Panel Feature

## Overview

The Upcoming Draws Panel is a beautiful, floating, movable window that displays 10 upcoming draws with real-time betting slip statistics. Cashiers can select any upcoming draw to assign new betting slips to that specific draw, providing complete control over draw assignment.

## Features

### 🎲 **Draw Selection & Management**
- **10 Upcoming Draws**: Shows the next 10 draws available for betting
- **Click to Select**: Simple click interface to select any upcoming draw
- **Visual Selection**: Selected draw is highlighted with distinctive styling
- **Next Draw Indicator**: Clearly marks the immediate next draw
- **Auto-Selection**: Automatically selects the next draw if none is chosen

### 📊 **Real-Time Statistics**
- **Betting Slip Count**: Shows how many slips are placed on each draw
- **Total Stake Amount**: Displays total money wagered on each draw
- **Live Updates**: Statistics refresh automatically every 3 seconds
- **Visual Indicators**: Color-coded statistics for easy reading

### 🎛️ **User Interface**
- **Floating Panel**: Movable window positioned on the left side
- **Drag & Drop**: Cashiers can reposition the panel anywhere
- **Collapsible**: Minimize to save screen space when not needed
- **Resizable**: Adjust panel size to fit workflow preferences
- **Persistent Settings**: Remembers position, size, and selected draw

### 🔄 **Integration Features**
- **Betting Slip Assignment**: New slips automatically use selected draw
- **Real-Time Sync**: Updates when new betting slips are created
- **Error Prevention**: Validates draw selection before slip creation
- **Notification System**: Shows confirmations and warnings

## Technical Implementation

### Files Added

#### CSS Styling
- `css/upcoming-draws-panel.css` - Complete styling for the floating panel

#### JavaScript Modules
- `js/upcoming-draws-panel.js` - Main panel functionality
- `js/upcoming-draws-integration.js` - Integration with betting slip system

#### API Endpoint
- `api/upcoming_draws_stats.php` - API providing draw statistics

#### Testing
- `test_upcoming_draws_panel.html` - Comprehensive test interface

### Integration Points

#### Main Cashier Interface (`index.html`)
```html
<!-- CSS -->
<link rel="stylesheet" href="css/upcoming-draws-panel.css">

<!-- JavaScript -->
<script src="js/upcoming-draws-panel.js"></script>
<script src="js/upcoming-draws-integration.js"></script>
```

#### API Response Format
```json
{
  "status": "success",
  "data": {
    "upcoming_draws": [
      {
        "draw_number": 138,
        "estimated_time": "18:35",
        "betting_slips_count": 5,
        "total_stake_amount": 125.50,
        "total_potential_payout": 4387.50,
        "is_next": true,
        "minutes_from_now": 3
      }
    ],
    "base_draw": 137,
    "next_draw": 138,
    "system_stats": {
      "total_active_slips": 23,
      "total_active_stake": 567.25
    }
  }
}
```

## Configuration

### Panel Settings
```javascript
const config = {
    debug: true,
    syncInterval: 3000, // Sync every 3 seconds
    apiEndpoint: 'api/upcoming_draws_stats.php',
    drawCount: 10, // Number of upcoming draws to show
    autoSelectNext: true, // Auto-select next draw
    showSelectionConfirmation: true
};
```

### Customization Options
- **Position**: Saved to localStorage, defaults to left side
- **Size**: Adjustable with resize handles
- **Collapse State**: Remembers user preference
- **Selected Draw**: Persists across browser sessions

## Usage Instructions

### For Cashiers

1. **Viewing Upcoming Draws**
   - Panel shows 10 upcoming draws with statistics
   - Each draw displays:
     - Draw number (e.g., #138)
     - Estimated time (e.g., 18:35)
     - Number of betting slips placed
     - Total stake amount wagered

2. **Selecting a Draw**
   - Click on any upcoming draw to select it
   - Selected draw is highlighted in blue
   - Selection indicator shows current choice
   - New betting slips will use the selected draw

3. **Understanding Statistics**
   - **Orange numbers**: Number of betting slips
   - **Green numbers**: Total stake amount in dollars
   - **"NEXT" badge**: Indicates the immediate next draw
   - **Real-time updates**: Statistics refresh automatically

4. **Panel Management**
   - **Move**: Drag the header to reposition
   - **Resize**: Use corner handles to adjust size
   - **Collapse**: Click chevron to minimize
   - **Refresh**: Click sync icon to update data

### For Administrators

1. **Monitoring**
   - Use test page: `test_upcoming_draws_panel.html`
   - Monitor API responses and statistics
   - Check logs in `logs/upcoming_draws_stats.log`

2. **Configuration**
   - Adjust sync intervals in JavaScript files
   - Modify draw count (1-20 draws supported)
   - Enable/disable auto-selection features

## Data Flow

```
Database (betting_slips + detailed_draw_results)
    ↓
API (upcoming_draws_stats.php)
    ↓
Panel Display (upcoming-draws-panel.js)
    ↓
Integration Layer (upcoming-draws-integration.js)
    ↓
Betting Slip System
```

### Draw Statistics Logic

1. **Upcoming Draws**: Base draw + 1 through base draw + 10
2. **Slip Counts**: `COUNT(*)` from `betting_slips` where `draw_number = X`
3. **Stake Amounts**: `SUM(total_stake)` from `betting_slips` where `draw_number = X`
4. **Real-time Updates**: Refreshes every 3 seconds

## Security & Reliability

### Data Integrity
- **Authoritative Source**: Uses `detailed_draw_results` for base draw
- **Real Statistics**: Actual betting slip data from database
- **Validation**: Ensures selected draws are still upcoming

### Error Handling
- **Graceful Degradation**: Falls back to cashier sync API
- **User Feedback**: Clear notifications for selections and errors
- **Retry Logic**: Automatic retry on connection failures
- **Comprehensive Logging**: Detailed error tracking

### Performance
- **Efficient Queries**: Optimized database queries for statistics
- **Minimal Updates**: Only refreshes when data changes
- **Local Caching**: Stores settings locally to reduce server load

## Browser Compatibility

- **Modern Browsers**: Chrome 60+, Firefox 55+, Safari 12+, Edge 79+
- **Mobile Support**: Responsive design for tablet interfaces
- **Touch Support**: Drag and resize work on touch devices

## Integration with Existing Systems

### Betting Slip System
- **Automatic Assignment**: Selected draw is used for new betting slips
- **Override Protection**: Prevents assignment to past draws
- **Event Integration**: Listens for betting slip creation events

### Cashier Draw Display
- **Complementary**: Works alongside the main draw display
- **Synchronized**: Both panels stay in sync with system state
- **Independent**: Can operate separately if needed

## Maintenance

### Regular Tasks
1. **Log Monitoring**: Check `logs/upcoming_draws_stats.log`
2. **Database Optimization**: Ensure betting_slips table is indexed
3. **Performance Monitoring**: Check API response times

### Updates
- **Styling**: Modify `css/upcoming-draws-panel.css`
- **Functionality**: Update `js/upcoming-draws-panel.js`
- **Statistics**: Modify `api/upcoming_draws_stats.php`

## Testing

### Manual Testing
1. Open `test_upcoming_draws_panel.html`
2. Test draw selection functionality
3. Verify statistics accuracy
4. Test panel movement and resizing

### Automated Testing
- API response validation
- Draw selection logic verification
- Statistics calculation accuracy

## Support

### Common Issues

1. **Panel Not Appearing**
   - Check browser console for JavaScript errors
   - Verify CSS file is loaded
   - Ensure API endpoint is accessible

2. **Statistics Not Updating**
   - Check database connectivity
   - Verify betting_slips table structure
   - Force refresh using test page

3. **Selection Not Working**
   - Check integration module is loaded
   - Verify event listeners are active
   - Clear localStorage if corrupted

### Debug Mode
Enable debug logging by setting `config.debug = true` in the JavaScript files.

## Advanced Features

### Custom Draw Counts
```javascript
// Show 15 upcoming draws instead of 10
UpcomingDrawsPanel.setConfig({ drawCount: 15 });
```

### Custom Styling
```css
/* Customize panel colors */
:root {
  --upcoming-gold: #your-color;
  --upcoming-selected: #your-selection-color;
}
```

### Event Handling
```javascript
// Listen for draw selection events
document.addEventListener('drawSelected', function(event) {
  console.log('Selected draw:', event.detail.drawNumber);
});
```

---

**🎯 The Upcoming Draws Panel provides cashiers with complete control over draw selection and real-time visibility into betting activity across all upcoming draws.**
