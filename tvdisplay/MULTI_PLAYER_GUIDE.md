# 🎬 Multi-Player Live Stream Guide

## 🎯 Overview
Your casino TV display now supports **multiple simultaneous YouTube live streams**! You can show both cricket streams (and more) at the same time for maximum entertainment.

## 🎮 Two Player Systems Available

### 1. **Single Player System** (Original)
- Shows **one stream at a time**
- Switch between different streams
- Red video button (📹) in bottom-right

### 2. **Multi-Player System** (NEW)
- Shows **multiple streams simultaneously**
- Each stream in its own draggable window
- Purple grid button (⊞) below the red button

## 🚀 Quick Start - Multi-Player

### Method 1: Use the Purple Button
1. **Access TV Display**: `http://localhost:8080/slipp/tvdisplay/index.html`
2. **Click Purple Button**: Look for the purple grid button (⊞) in bottom-right
3. **Multiple Players Appear**: Both cricket streams will show simultaneously
4. **Drag & Position**: Click and drag each player to arrange them
5. **Close Individual Players**: Click ✕ on any player to close it

### Method 2: Console Commands
Press **F12** → **Console** → Type:

```javascript
// Show all configured streams
showMultiStreams()

// Hide all streams
hideMultiStreams()

// Toggle multi-stream mode
toggleMultiStreams()
```

## 🎥 Available Streams

### Pre-configured Streams:
1. **Cricket Stream 1**: `https://www.youtube.com/live/-g-E-eXp8iY?si=QdW6qLLLqmYyLKS-`
2. **Cricket Stream 2**: `https://www.youtube.com/live/LV9-E3qaHkg?si=6YV_zrpMXgLuw5EP`

### Default Layout:
- **Cricket 1**: Top-left position (50, 100)
- **Cricket 2**: Top-right position (500, 100)
- **Size**: 400×225 pixels each

## 🛠️ Advanced Multi-Player Controls

### Add Custom Stream
```javascript
// Add any YouTube live stream
MultiStreamManager.addStream(
    'https://www.youtube.com/live/VIDEO_ID',
    'CUSTOM LABEL',
    { x: 100, y: 300 }  // Position
)
```

### Remove Specific Player
```javascript
// Get list of active players
const players = MultiStreamManager.getActivePlayerIds()
console.log('Active players:', players)

// Remove specific player
MultiStreamManager.removeStream('player_1')
```

### Check Status
```javascript
// Check if multi-player mode is active
console.log('Multi-player active:', MultiStreamManager.isActive())

// Get all active player IDs
console.log('Active players:', MultiStreamManager.getActivePlayerIds())
```

## 🎯 Player Features

### Each Player Has:
✅ **Draggable Header** - Click and drag to move  
✅ **Close Button** - ✕ to close individual player  
✅ **Stream Label** - Shows stream name (CRICKET 1, CRICKET 2, etc.)  
✅ **YouTube Controls** - Full YouTube player controls  
✅ **Responsive Size** - Automatically adjusts on smaller screens  
✅ **Purple Border** - Distinctive styling for multi-players  

### Player Controls:
- **Drag**: Click header and drag to move
- **Close**: Click ✕ button to close
- **Fullscreen**: Use YouTube's fullscreen button
- **Volume**: Use YouTube's volume controls

## 🎨 Visual Design

### Multi-Player Styling:
- **Purple borders** (vs red for single player)
- **Purple toggle button** with grid icon
- **Fade-in animation** when players appear
- **Hover effects** for better interaction
- **Responsive sizing** for different screen sizes

### Button Locations:
- **Red Button** (📹): Single player toggle
- **Purple Button** (⊞): Multi-player toggle (below red button)

## 📱 Responsive Behavior

### Screen Size Adaptations:
- **Large screens (>1200px)**: Full 400×225 size
- **Medium screens (800-1200px)**: 350×200 size  
- **Small screens (<800px)**: 300×170 size

## 🔧 Troubleshooting

### Players Not Showing?
1. **Check console** for error messages
2. **Try console command**: `showMultiStreams()`
3. **Refresh page** and try again
4. **Check if single player is active** - hide it first

### Players Overlapping?
```javascript
// Reset all player positions
hideMultiStreams()
setTimeout(() => showMultiStreams(), 1000)
```

### Loading Indicators Stuck?
```javascript
// Fix loading indicators on all players
fixLoadingIndicator()
```

### Can't Drag Players?
- Make sure you're clicking on the **header area** (purple bar)
- Don't click on the video content itself

## 🎮 Console Commands Reference

| Command | Action |
|---------|--------|
| `showMultiStreams()` | Show all configured streams |
| `hideMultiStreams()` | Hide all streams |
| `toggleMultiStreams()` | Toggle multi-stream mode |
| `MultiStreamManager.isActive()` | Check if active |
| `MultiStreamManager.getActivePlayerIds()` | List active players |

## 🎯 Use Cases

### Perfect For:
✅ **Multiple Cricket Matches** - Show different games simultaneously  
✅ **Sports Comparison** - Compare different streams/angles  
✅ **Backup Streams** - Have backup if one stream fails  
✅ **Customer Choice** - Let customers choose their preferred stream  
✅ **Event Coverage** - Multiple perspectives of same event  

### Casino Benefits:
- **More Entertainment** = Longer customer engagement
- **Stream Redundancy** = Always have working content
- **Flexible Layout** = Arrange streams as needed
- **Professional Look** = Clean, organized display

## 🎉 Enjoy Multiple Streams!

Your casino TV display now supports both single and multiple stream modes. Switch between them based on your needs:

- **Single Player**: For focused viewing of one stream
- **Multi-Player**: For maximum entertainment with multiple streams

**Perfect for keeping your casino customers entertained with multiple live sports streams!** 🏏⚽📺
