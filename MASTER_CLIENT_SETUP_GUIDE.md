# 👑 Master-Client Roulette System Setup Guide

## 🎯 Overview
Your casino now has a **master-client architecture** where one display controls the game and multiple client displays mirror it exactly. No Node.js server required!

### 🏗️ **System Architecture:**
- **Master Display** (`index.html`) - Controls the game, timer, and results
- **Client Displays** (`shop1.html`, `shop2.html`, etc.) - Mirror the master exactly
- **Browser-to-Browser Communication** - Uses BroadcastChannel API for real-time sync

## 🚀 Quick Setup

### Step 1: Start Master Display
1. **Open Master**: `http://localhost:8080/slipp/tvdisplay/index.html`
2. **Look for Status**: Should show 👑 **"MASTER"** in top-left corner
3. **Verify Functionality**: Timer, betting, spinning all work normally

### Step 2: Start Client Displays
1. **Shop 1**: `http://localhost:8080/slipp/tvdisplay/shop1.html`
2. **Shop 2**: `http://localhost:8080/slipp/tvdisplay/shop2.html`
3. **Look for Status**: Should show 📺 **"CLIENT"** in top-left corner
4. **Verify Sync**: Timer should match master exactly

### Step 3: Test Synchronization
1. **Watch Timer**: All displays should show identical countdown
2. **Trigger Spin**: Spin on master, all clients should spin simultaneously
3. **Check Results**: All displays should show same winning numbers
4. **Verify History**: Previous numbers should match across all displays

## 🖥️ Display Types

### 👑 **Master Display** (`index.html`)
- **Full Control**: Can place bets, spin wheel, control game
- **Game Logic**: Generates timer, results, and game phases
- **Broadcasting**: Sends updates to all connected clients
- **Status**: Shows 👑 "MASTER" with client count

### 📺 **Client Displays** (`shop1.html`, `shop2.html`)
- **Complete Structure**: Identical HTML structure to master display
- **Visual Consistency**: All UI elements present and properly styled
- **Read-Only**: Cannot place bets or control game (interactions disabled)
- **Synchronized**: Mirrors master display exactly in real-time
- **Real-Time**: Updates instantly when master changes
- **Status**: Shows 📺 "CLIENT - Syncing with master"

## 🔧 Technical Details

### **Communication Method:**
- **BroadcastChannel API** - Modern browser-to-browser communication
- **localStorage Fallback** - For older browsers
- **Real-Time Updates** - Instant synchronization across all displays

### **Synchronized Elements:**
✅ **Timer Countdown** - All displays show identical time remaining  
✅ **Spin Events** - All wheels spin simultaneously  
✅ **Winning Numbers** - Same results displayed everywhere  
✅ **Previous Numbers** - Identical history across all displays  
✅ **Game Phases** - Betting, spinning, result phases synchronized  

### **Client Restrictions:**
🚫 **Betting Disabled** - Chips and betting areas are non-interactive (opacity reduced)
🚫 **Spin Disabled** - Cannot trigger spins from client displays
🚫 **Game Control Disabled** - Only master can control the game
🚫 **Debug Panel Hidden** - Administrative controls not visible on client displays
✅ **Complete Visual Display** - All UI elements visible and properly styled
✅ **Real-Time Sync** - Clients mirror master display perfectly

## 📱 Multi-Device Access

### **Same Computer:**
- **Master**: `http://localhost:8080/slipp/tvdisplay/index.html`
- **Shop 1**: `http://localhost:8080/slipp/tvdisplay/shop1.html`
- **Shop 2**: `http://localhost:8080/slipp/tvdisplay/shop2.html`

### **Network Access:**
Replace `localhost` with your computer's IP address:
- **Master**: `http://192.168.1.100:8080/slipp/tvdisplay/index.html`
- **Shop 1**: `http://192.168.1.100:8080/slipp/tvdisplay/shop1.html`
- **Shop 2**: `http://192.168.1.100:8080/slipp/tvdisplay/shop2.html`

## 🏪 Creating New Client Displays

### **Easy Method:**
1. **Copy** `shop1.html` to `shop3.html`
2. **Edit Title**: Change "Shop 1" to "Shop 3" in the HTML
3. **Edit Game Name**: Change "ROULETTE - SHOP 1" to "ROULETTE - SHOP 3"
4. **Edit Console Log**: Change "Shop 1" to "Shop 3" in JavaScript
5. **Access**: `http://localhost:8080/slipp/tvdisplay/shop3.html`

**Note**: Client displays now have the complete HTML structure from the master display, ensuring visual consistency and all functionality is present (but disabled for client mode).

### **Template for New Shops:**
```html
<!-- Change these lines in the new file -->
<title>Roulette Client Display - Shop X</title>
<div class="game-name">ROULETTE - SHOP X</div>
console.log('📺 CLIENT DISPLAY: Shop X initializing...');
console.log('📺 Initialized as CLIENT display - Shop X');
console.log('📺 CLIENT: Shop X display ready - syncing with master');
```

## 🎮 How It Works

### **Master Broadcast Flow:**
1. **Master detects change** (timer update, spin result, etc.)
2. **Broadcasts message** via BroadcastChannel API
3. **All clients receive** the update instantly
4. **Clients update display** to match master

### **Client Sync Flow:**
1. **Client connects** and requests current game state
2. **Master sends** complete game state
3. **Client updates** all elements to match
4. **Real-time sync** continues automatically

### **Fallback System:**
- **Primary**: BroadcastChannel API (modern browsers)
- **Fallback**: localStorage polling (older browsers)
- **Automatic**: System chooses best method available

## 🔍 Monitoring & Status

### **Master Status Indicators:**
- 👑 **"MASTER"** - System is broadcasting
- **Client Count** - Shows number of connected clients
- **Green Status** - Everything working normally

### **Client Status Indicators:**
- 📺 **"CLIENT"** - System is receiving updates
- **"Syncing with master"** - Connected and synchronized
- **Blue Status** - Receiving updates normally

### **Console Monitoring:**
Press **F12** and check console for:
- **Master**: Broadcasting messages and client connections
- **Client**: Receiving updates and sync confirmations

## 🛠️ Troubleshooting

### ❌ **Client Not Syncing**
**Problem**: Client shows different timer/results than master
**Solutions**:
1. **Refresh client page** - Force reconnection
2. **Check same network** - Master and client must be on same network
3. **Verify master running** - Master must be open and active
4. **Check browser support** - Use modern browsers (Chrome, Firefox, Safari, Edge)

### ❌ **No Status Indicator**
**Problem**: No 👑 MASTER or 📺 CLIENT status showing
**Solutions**:
1. **Wait 2-3 seconds** - Status appears after initialization
2. **Check console** - Look for error messages
3. **Refresh page** - Restart the initialization process

### ❌ **Timer Not Matching**
**Problem**: Timers show different times
**Solutions**:
1. **Refresh all clients** - Resync with master
2. **Restart master** - Reset the master timer
3. **Check system clocks** - Ensure devices have correct time

### ❌ **BroadcastChannel Not Supported**
**Problem**: Older browser doesn't support BroadcastChannel
**Solutions**:
1. **Automatic fallback** - System uses localStorage polling
2. **Update browser** - Use latest version for best performance
3. **Check console** - Will show fallback activation

## 🎯 Best Practices

### **For Casino Operations:**
1. **Master on Main Display** - Use primary/central display as master
2. **Clients on Shop Displays** - Each shop gets its own client display
3. **Network Stability** - Ensure stable network connection
4. **Browser Updates** - Keep browsers updated for best performance

### **For Testing:**
1. **Open Multiple Windows** - Test on same computer first
2. **Check Synchronization** - Verify timer and results match
3. **Test Network Access** - Verify clients work from other devices
4. **Monitor Console** - Watch for any error messages

## 🎉 Success Verification

### ✅ **System Working When:**
1. **Master Status**: Shows 👑 "MASTER" with client count
2. **Client Status**: Shows 📺 "CLIENT - Syncing with master"
3. **Timer Sync**: All displays show identical countdown
4. **Result Sync**: All displays show same winning numbers
5. **Spin Sync**: All wheels spin and stop simultaneously

### 🎯 **Perfect for Casino Use:**
- **Central Control** - One master controls all displays
- **Instant Updates** - Real-time synchronization
- **Easy Scaling** - Add new shops by copying HTML files
- **No Server Required** - Pure browser-to-browser communication
- **Reliable Fallbacks** - Works even with older browsers

## 🚀 Ready to Use!

Your master-client roulette system is ready! 

**Quick Start:**
1. **Open Master**: `index.html` (shows 👑 MASTER)
2. **Open Clients**: `shop1.html`, `shop2.html` (show 📺 CLIENT)
3. **Verify Sync**: All timers and results should match exactly

**All client displays will now mirror the master display perfectly, creating a unified casino experience across all locations!** 🎰👑📺
