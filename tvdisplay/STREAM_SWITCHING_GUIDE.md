# 🎥 Quick Stream Switching Guide

## 🏏 Available Cricket Streams

### Cricket Stream 1
- **URL**: `https://www.youtube.com/live/-g-E-eXp8iY?si=QdW6qLLLqmYyLKS-`
- **Switch Command**: `switchStream('cricket1')`

### Cricket Stream 2  
- **URL**: `https://www.youtube.com/live/LV9-E3qaHkg?si=6YV_zrpMXgLuw5EP`
- **Switch Command**: `switchStream('cricket2')`

## 🚀 Quick Switch Methods

### Method 1: Console Commands (Fastest)
Press **F12** → **Console** → Type:

```javascript
// Switch to Cricket Stream 1
switchStream('cricket1')

// Switch to Cricket Stream 2  
switchStream('cricket2')

// Show current configuration
showStreamConfig()
```

### Method 2: Direct URL Change
```javascript
// Cricket Stream 1
changeStream('https://www.youtube.com/live/-g-E-eXp8iY?si=QdW6qLLLqmYyLKS-')

// Cricket Stream 2
changeStream('https://www.youtube.com/live/LV9-E3qaHkg?si=6YV_zrpMXgLuw5EP')
```

### Method 3: Test Page Controls
1. Go to: `http://localhost:8080/slipp/tvdisplay/test-youtube-stream.html`
2. Click **"Cricket Stream 1"** or **"Cricket Stream 2"** buttons

## 🎯 One-Click Stream Switch

Copy and paste for instant switching:

### Switch to Cricket Stream 1:
```javascript
if(window.LiveStreamPlayer){window.LiveStreamPlayer.hide();setTimeout(()=>window.LiveStreamPlayer.show('https://www.youtube.com/live/-g-E-eXp8iY?si=QdW6qLLLqmYyLKS-'),500);}console.log('✅ Switched to Cricket Stream 1');
```

### Switch to Cricket Stream 2:
```javascript
if(window.LiveStreamPlayer){window.LiveStreamPlayer.hide();setTimeout(()=>window.LiveStreamPlayer.show('https://www.youtube.com/live/LV9-E3qaHkg?si=6YV_zrpMXgLuw5EP'),500);}console.log('✅ Switched to Cricket Stream 2');
```

## 📺 Current Stream Status

Check which stream is currently playing:
```javascript
// Show current stream configuration
showStreamConfig()

// Check if player is visible
console.log('Player visible:', window.LiveStreamPlayer.isVisible())
```

## 🔧 Troubleshooting

### Stream Not Switching?
1. **Refresh page** and try again
2. **Hide player first**: `LiveStreamPlayer.hide()`
3. **Wait 2 seconds**, then show new stream
4. **Check console** for error messages

### Loading Indicator Stuck?
```javascript
// Force hide loading indicator
fixLoadingIndicator()
```

### Player Not Responding?
```javascript
// Reset player completely
LiveStreamPlayer.hide()
setTimeout(() => {
    LiveStreamPlayer.show('https://www.youtube.com/live/LV9-E3qaHkg?si=6YV_zrpMXgLuw5EP')
}, 1000)
```

## 🎮 Advanced Controls

### Set Default Stream
```javascript
// Make Cricket Stream 2 the default
window.StreamConfig.setCurrentStream('https://www.youtube.com/live/LV9-E3qaHkg?si=6YV_zrpMXgLuw5EP')
```

### Add New Stream
```javascript
// Add a new alternative stream
window.StreamConfig.addAlternativeStream('cricket3', 'https://www.youtube.com/live/NEW_VIDEO_ID')
```

### Player Position & Size
```javascript
// Resize player
window.StreamConfig.updatePlayerSettings({
    size: { width: 500, height: 300 },
    position: { x: 100, y: 100 }
})
```

---

## 🎉 Quick Reference

| Action | Command |
|--------|---------|
| **Cricket Stream 1** | `switchStream('cricket1')` |
| **Cricket Stream 2** | `switchStream('cricket2')` |
| **Show Config** | `showStreamConfig()` |
| **Hide Player** | `LiveStreamPlayer.hide()` |
| **Fix Loading** | `fixLoadingIndicator()` |

**Perfect for switching between different cricket matches or finding the best quality stream!** 🏏📺
