# ⏰ Floating Countdown Timer Widget for Roulette Application

## Overview
A sophisticated floating countdown timer widget that displays the time remaining until the next roulette draw. The timer features persistent positioning, real-time updates, visual state changes, and seamless integration with the existing roulette system.

## 🚀 Key Features

### Core Functionality
- **Real-time Countdown**: Updates every second with MM:SS format display
- **Persistent Positioning**: Remembers and restores position across sessions
- **Visual State Changes**: Color-coded warnings (orange at 60s, red at 30s)
- **Draggable Interface**: Click and drag to reposition anywhere on screen
- **Minimize/Close Controls**: Compact view options for better screen management

### Advanced Features
- **Glass Morphism Design**: Elegant translucent styling with backdrop blur
- **Animated Elements**: Ticking clock icon and pulsing status indicators
- **Mobile Support**: Touch-enabled dragging for mobile devices
- **Auto-refresh**: Syncs with server data and handles timer expiration
- **API Integration**: Connects to existing roulette state system

## 📁 Files Modified/Created

### 1. `index.php` - Main Implementation
- Added floating countdown timer HTML structure
- Implemented complete timer functionality with persistent positioning
- Integrated with existing roulette state system
- Added API endpoints for countdown data

### 2. `api/accounting_dashboard_data.php` - Backend Integration
- Added roulette_state data fetching
- Included countdown_time in API response
- Connected to roulette_state database table

### 3. `countdown_timer_demo.html` - Demonstration
- Standalone demo with interactive controls
- Test different countdown scenarios
- Real-time status monitoring
- Position persistence testing

## 🎯 Visual Design

### Default Appearance
- **Position**: Top-right corner (20px from edges)
- **Size**: 280px width, responsive height
- **Background**: Dark translucent with glass morphism effect
- **Typography**: Monospace countdown display for clarity

### State-Based Styling
```css
/* Normal State - Blue accents */
.countdown-timer-widget { border: 1px solid rgba(255, 255, 255, 0.1); }

/* Warning State - Orange (≤60 seconds) */
.countdown-timer-widget.warning { border-color: #f39c12; }

/* Critical State - Red with pulsing (≤30 seconds) */
.countdown-timer-widget.critical { 
    border-color: #e74c3c;
    animation: criticalPulse 1s infinite;
}
```

### Responsive Design
- **Desktop**: Full 280px width with large countdown display
- **Mobile**: Compact 240px width with adjusted padding
- **Touch Support**: Enhanced touch targets for mobile interaction

## 🔧 Technical Implementation

### HTML Structure
```html
<div id="countdown-timer-widget" class="countdown-timer-widget">
    <div class="timer-header" id="timer-header">
        <div class="title">
            <i class="fas fa-clock timer-icon"></i>
            <span>Next Draw</span>
        </div>
        <div class="controls">
            <button class="timer-control-btn" id="timer-minimize-btn">
                <i class="fas fa-minus"></i>
            </button>
            <button class="timer-control-btn" id="timer-close-btn">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    <div class="timer-content" id="timer-content">
        <div class="timer-label">Time Remaining</div>
        <div class="countdown-display" id="countdown-display">03:00</div>
        <div class="timer-info">
            <div>Draw #<span id="timer-draw-number">42</span></div>
            <div class="timer-status">
                <div class="timer-status-indicator"></div>
                <span class="timer-status-text">Active</span>
            </div>
        </div>
    </div>
</div>
```

### JavaScript Configuration
```javascript
const COUNTDOWN_CONFIG = {
    storageKey: 'roulette_countdown_timer_position',
    defaultPosition: { x: null, y: 20 }, // null x = right-aligned
    minDistance: 20,                     // Min distance from viewport edges
    warningThreshold: 60,                // Warning state at 60 seconds
    criticalThreshold: 30,               // Critical state at 30 seconds
    updateInterval: 1000,                // Update every 1 second
    debugMode: false                     // Debug position indicator
};
```

### Core Functions
```javascript
// Initialize the timer system
function initializeCountdownTimer() {
    loadTimerSavedPosition();
    initializeTimerDragFunctionality();
    initializeTimerControlButtons();
    startCountdownTimer();
}

// Update countdown display with visual states
function updateCountdownDisplay() {
    const minutes = Math.floor(Math.max(0, currentCountdownTime) / 60);
    const seconds = Math.max(0, currentCountdownTime) % 60;
    const timeString = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    
    // Apply visual states based on time remaining
    if (currentCountdownTime <= criticalThreshold) {
        // Red critical state with animations
    } else if (currentCountdownTime <= warningThreshold) {
        // Orange warning state
    }
}
```

## 🎮 Usage Instructions

### Basic Operation
1. **Automatic Display**: Timer appears automatically when page loads
2. **Drag to Move**: Click and drag the header to reposition
3. **Minimize**: Click the minus button to collapse content
4. **Close**: Click the X button to hide completely

### Position Persistence
- **Automatic Saving**: Position saved automatically when dragging ends
- **Session Persistence**: Position restored on page refresh/reload
- **Reset Option**: Use `CountdownTimerAPI.reset()` to return to default

### API Control
```javascript
// Public API for programmatic control
window.CountdownTimerAPI = {
    show: showCountdownTimer,           // Show the timer
    hide: hideCountdownTimer,           // Hide the timer
    reset: resetTimerPosition,          // Reset position to default
    updateTime: function(seconds) {     // Set countdown time
        currentCountdownTime = seconds;
        updateCountdownDisplay();
    },
    getCurrentTime: function() {        // Get current countdown time
        return currentCountdownTime;
    }
};
```

## 🔄 Integration with Roulette System

### Data Synchronization
- **Initial Load**: Gets countdown_time from PHP roulette_state
- **Real-time Updates**: Syncs with dashboard refresh cycles
- **Auto-refresh**: Fetches new countdown data when timer expires
- **Error Handling**: Graceful fallback and retry mechanisms

### Database Integration
```php
// API endpoint returns roulette state data
$roulette_state = [
    'current_draw' => $state['current_draw_number'],
    'next_draw' => $state['current_draw_number'] + 1,
    'countdown_time' => $state['countdown_time'],
    'winning_number' => $state['winning_number']
];
```

### Dashboard Integration
```javascript
// Integrates with existing dashboard refresh
const originalUpdateDrawNumbers = updateDrawNumbers;
updateDrawNumbers = function(rouletteState) {
    originalUpdateDrawNumbers(rouletteState);
    updateCountdownTimer(rouletteState); // Update timer with new data
};
```

## 📱 Mobile Compatibility

### Touch Support
- **Touch Events**: Full touch event handling for mobile dragging
- **Responsive Design**: Adapts to smaller screen sizes
- **Touch Targets**: Enlarged control buttons for easier interaction

### Mobile Optimizations
```css
@media (max-width: 768px) {
    .countdown-timer-widget {
        width: 240px;           /* Smaller width for mobile */
        top: 10px;              /* Reduced margins */
        right: 10px;
    }
    
    .countdown-display {
        font-size: 2rem;        /* Smaller font for mobile */
    }
}
```

## 🚨 Edge Cases & Error Handling

### Connection Issues
- **Network Errors**: Automatic retry with exponential backoff
- **API Failures**: Graceful degradation with local countdown
- **Data Validation**: Validates server responses before applying

### Timer Expiration
- **Auto-refresh**: Automatically fetches new countdown data
- **Visual Feedback**: Clear indication when draw completes
- **Seamless Transition**: Smooth transition to next draw countdown

### Position Validation
- **Viewport Bounds**: Ensures timer stays within visible area
- **Screen Resize**: Automatically adjusts position if needed
- **Invalid Data**: Falls back to default position if saved data corrupted

## 🔧 Customization Options

### Visual Customization
```javascript
// Modify thresholds for different warning levels
COUNTDOWN_CONFIG.warningThreshold = 90;  // Warning at 90 seconds
COUNTDOWN_CONFIG.criticalThreshold = 45; // Critical at 45 seconds

// Change default position
COUNTDOWN_CONFIG.defaultPosition = { x: 50, y: 50 }; // Top-left corner
```

### Styling Customization
```css
/* Custom color scheme */
.countdown-timer-widget.warning {
    border-color: #your-warning-color;
    box-shadow: 0 20px 40px rgba(your-color, 0.3);
}

/* Custom animations */
@keyframes customPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}
```

## 🧪 Testing & Debugging

### Demo Features
- **Interactive Controls**: Test all timer states and functions
- **Position Testing**: Drag and refresh to test persistence
- **State Simulation**: Buttons to trigger warning/critical states
- **Real-time Status**: Monitor timer state and position data

### Debug Commands
```javascript
// Enable debug mode
COUNTDOWN_CONFIG.debugMode = true;

// Manual timer control
CountdownTimerAPI.updateTime(30); // Set to 30 seconds
CountdownTimerAPI.show();         // Show timer
CountdownTimerAPI.reset();        // Reset position
```

## 📊 Performance Considerations

### Optimizations
- **Efficient Updates**: Only updates DOM when values change
- **Throttled Events**: Optimized drag event handling
- **Memory Management**: Proper cleanup of intervals and events
- **Minimal Reflows**: Efficient CSS animations and transitions

### Resource Usage
- **Lightweight**: ~20KB total implementation
- **Low CPU**: Efficient 1-second update intervals
- **Memory Efficient**: Minimal DOM manipulation
- **Battery Friendly**: Optimized for mobile devices

---

**Ready for Production**: The countdown timer widget is fully implemented and ready for use in the roulette application. Access the main `index.php` file to see the timer in action, or use `countdown_timer_demo.html` for testing and demonstration.
