# 🎥 YouTube Live Stream Integration Guide

## Overview
Your casino TV display system now supports YouTube live streaming! This allows you to show live sports, news, or entertainment content alongside your roulette analytics.

## 🚀 Quick Start

### Current Streams
The system is currently configured with: **Multiple Cricket Live Streams**
- **Cricket Stream 1**: `https://www.youtube.com/live/-g-E-eXp8iY?si=QdW6qLLLqmYyLKS-`
- **Cricket Stream 2**: `https://www.youtube.com/live/LV9-E3qaHkg?si=6YV_zrpMXgLuw5EP`

### How to Use
1. **Access TV Display**: Go to `http://localhost:8080/slipp/tvdisplay/index.html`
2. **Toggle Stream**: Click the red video button (📹) in the bottom-right corner
3. **Control Player**: 
   - **Drag**: Click and drag the player to move it around
   - **Resize**: Drag the corner handle to resize
   - **Close**: Click the ✕ button to close

## 🔧 Changing the Stream

### Method 1: Using Browser Console (Easiest)
1. Open browser developer tools (F12)
2. Go to Console tab
3. Use these commands:

```javascript
// Change to a different YouTube live stream
changeStream('https://www.youtube.com/live/NEW_VIDEO_ID')

// Show current configuration
showStreamConfig()

// Switch between cricket streams
switchStream('cricket1')  // First cricket stream
switchStream('cricket2')  // Second cricket stream
```

### Method 2: Edit Configuration File
1. Open `tvdisplay/stream-config.js`
2. Change the `currentStream` URL:

```javascript
currentStream: 'https://www.youtube.com/live/YOUR_NEW_VIDEO_ID',
```

3. Save the file and refresh the page

### Method 3: Add Alternative Streams
Edit `tvdisplay/stream-config.js` and add to `alternativeStreams`:

```javascript
alternativeStreams: {
    cricket1: 'https://www.youtube.com/live/-g-E-eXp8iY?si=QdW6qLLLqmYyLKS-',
    cricket2: 'https://www.youtube.com/live/LV9-E3qaHkg?si=6YV_zrpMXgLuw5EP',
    sports: 'https://www.youtube.com/watch?v=SPORTS_VIDEO_ID',
    news: 'https://www.youtube.com/watch?v=NEWS_VIDEO_ID',
    your_stream: 'https://www.youtube.com/live/YOUR_VIDEO_ID'
}
```

Then use: `switchStream('your_stream')`

## 📺 Supported YouTube URL Formats

✅ **Supported:**
- `https://www.youtube.com/live/VIDEO_ID`
- `https://www.youtube.com/watch?v=VIDEO_ID`
- `https://youtu.be/VIDEO_ID`
- `https://www.youtube.com/embed/VIDEO_ID`

❌ **Not Supported:**
- Non-YouTube URLs
- Private videos
- Age-restricted content

## ⚙️ Player Settings

### Position & Size
```javascript
playerSettings: {
    position: { x: 479, y: 100 },     // X, Y coordinates
    size: { width: 400, height: 225 }, // Width, height in pixels
    autoShow: true,                    // Show automatically on page load
    muted: false,                      // Start muted/unmuted
    label: 'CRICKET'                   // Label shown on player
}
```

### Display Options
```javascript
displaySettings: {
    showToggleButton: true,    // Show the video toggle button
    buttonPosition: 'bottom-right', // Button position
    enableDragging: true,      // Allow dragging the player
    enableResizing: true       // Allow resizing the player
}
```

## 🛠️ Troubleshooting

### Stream Not Loading
1. **Check URL**: Ensure it's a valid YouTube URL
2. **Check Video**: Make sure the video is public and live
3. **Refresh Page**: Try refreshing the browser
4. **Check Console**: Look for errors in browser console (F12)

### Player Not Showing
1. **Check Toggle Button**: Click the red video button
2. **Check Position**: Player might be off-screen, try resetting:
   ```javascript
   // Reset player position
   window.StreamConfig.updatePlayerSettings({
       position: { x: 100, y: 100 }
   });
   ```

### Console Commands Not Working
1. Make sure you're on the TV display page
2. Check that `stream-config.js` is loaded
3. Try refreshing the page

## 🎯 Advanced Usage

### Programmatic Control
```javascript
// Show player with specific URL
LiveStreamPlayer.show('https://www.youtube.com/live/VIDEO_ID');

// Hide player
LiveStreamPlayer.hide();

// Toggle player
LiveStreamPlayer.toggle('https://www.youtube.com/live/VIDEO_ID');

// Check if player is visible
if (LiveStreamPlayer.isVisible()) {
    console.log('Player is currently visible');
}
```

### Custom Configuration
```javascript
// Update player settings
window.StreamConfig.updatePlayerSettings({
    size: { width: 600, height: 400 },
    muted: true,
    label: 'LIVE SPORTS'
});

// Add new alternative stream
window.StreamConfig.addAlternativeStream('football', 'https://www.youtube.com/live/FOOTBALL_ID');
```

## 📱 Mobile Considerations

- Player is responsive and works on mobile devices
- Touch gestures supported for dragging and resizing
- Smaller default size on mobile screens
- YouTube's mobile player controls are used

## 🔒 Security Notes

- Only YouTube URLs are supported for security
- All streams are embedded using YouTube's official embed system
- No external scripts or unsafe content is loaded
- Player respects YouTube's terms of service

## 📞 Support

If you need help:
1. Check the browser console for error messages
2. Try the test page: `http://localhost:8080/slipp/tvdisplay/test-youtube-stream.html`
3. Verify the YouTube URL works in a regular browser first

## 🎉 Enjoy Your Live Streams!

Your casino TV display now supports live YouTube streaming alongside your roulette analytics. Perfect for keeping customers entertained while they play!
