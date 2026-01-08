# 🚀 Headless TV Display Setup Guide

This guide provides multiple solutions to run your TV display (`http://localhost/slipp/tvdisplay/index.html`) continuously without browser dependency, eliminating the idle tab issue completely.

## 🎯 **Why This Solves Your Problem**

**Your Issue:** Idle browser tabs cause draw number skipping due to JavaScript throttling and catch-up processing.

**Solution:** Run the TV display in a headless environment that never goes idle, ensuring continuous operation even when your browser is closed.

## 🚀 **Solution Options**

### **Option 1: Python + Selenium (Recommended)**
**Best for:** Full browser simulation with JavaScript execution
**File:** `headless_tv_display.py`

#### **Installation:**
```bash
# Install Python dependencies
pip install selenium webdriver-manager requests beautifulsoup4

# Run the headless TV display
python headless_tv_display.py
```

#### **Features:**
- ✅ **Full Browser Simulation:** Runs actual Chrome browser headlessly
- ✅ **JavaScript Execution:** All TV display JavaScript runs normally
- ✅ **Auto-Restart:** Prevents memory leaks with periodic restarts
- ✅ **Health Monitoring:** Continuous page health checks
- ✅ **Logging:** Complete activity logging to file
- ✅ **Graceful Shutdown:** Handles Ctrl+C and system signals

#### **Configuration:**
```python
config = {
    'url': 'http://localhost/slipp/tvdisplay/index.html',
    'check_interval': 30,     # Check every 30 seconds
    'restart_interval': 3600, # Restart every hour
    'headless': True,         # Set to False for debugging
    'window_size': (1920, 1080),
    'log_level': logging.INFO
}
```

---

### **Option 2: Node.js + Puppeteer**
**Best for:** JavaScript developers or Node.js environments
**File:** `headless_tv_display.js`

#### **Installation:**
```bash
# Install Node.js dependencies
npm install puppeteer

# Run the headless TV display
node headless_tv_display.js
```

#### **Features:**
- ✅ **Puppeteer Browser:** Google's official headless Chrome
- ✅ **JavaScript Native:** Built for JavaScript environments
- ✅ **Console Logging:** Captures page console messages
- ✅ **Error Handling:** Comprehensive error management
- ✅ **Auto-Recovery:** Automatic page reload on failures

---

### **Option 3: Simple Keep-Alive (Lightweight)**
**Best for:** Minimal resource usage, API-only approach
**File:** `simple_tv_keepalive.py`

#### **Installation:**
```bash
# Install Python dependencies
pip install requests

# Run the keep-alive system
python simple_tv_keepalive.py
```

#### **Features:**
- ✅ **Lightweight:** Minimal resource usage
- ✅ **API Pinging:** Keeps backend systems active
- ✅ **Statistics:** Request success/failure tracking
- ✅ **Multiple Endpoints:** Pings various system APIs
- ✅ **Simple Setup:** Easy to configure and run

#### **Endpoints Monitored:**
- `api/get_next_draw_number.php`
- `api/safe_draw_advance.php?action=info`
- `api/tv_sync.php`
- `php/get_draw_history.php`
- `api/cashier_draw_sync.php`

---

### **Option 4: System Service (Production)**
**Best for:** Production environments, auto-start on boot
**File:** `install_tv_service.py`

#### **Installation:**
```bash
# Windows (Run as Administrator)
python install_tv_service.py install
python install_tv_service.py start

# Linux (Run with sudo)
sudo python install_tv_service.py install
sudo python install_tv_service.py start
```

#### **Features:**
- ✅ **Auto-Start:** Starts automatically when computer boots
- ✅ **System Integration:** Runs as Windows Service or Linux daemon
- ✅ **Background Operation:** Runs without user login
- ✅ **Service Management:** Standard start/stop/status commands
- ✅ **Crash Recovery:** Automatic restart on failures

#### **Service Commands:**
```bash
# Install service
python install_tv_service.py install

# Start service
python install_tv_service.py start

# Stop service
python install_tv_service.py stop

# Check status
python install_tv_service.py status

# Uninstall service
python install_tv_service.py uninstall
```

## 🔧 **Quick Start (Recommended)**

### **Step 1: Choose Your Solution**
For most users, **Option 1 (Python + Selenium)** is recommended as it provides the most complete simulation.

### **Step 2: Install Dependencies**
```bash
pip install selenium webdriver-manager requests beautifulsoup4
```

### **Step 3: Run the Headless TV Display**
```bash
python headless_tv_display.py
```

### **Step 4: Verify Operation**
- Check the console output for "TV display loaded successfully"
- Monitor the log file: `headless_tv_display.log`
- Verify draw numbers are updating normally
- Close your browser - the system continues running!

## 📊 **Monitoring and Logs**

### **Log Files:**
- `headless_tv_display.log` - Main application log
- `tv_keepalive.log` - Keep-alive system log

### **Console Output:**
```
2025-01-XX 10:30:00 - INFO - Starting Headless TV Display Simulator
2025-01-XX 10:30:05 - INFO - Chrome WebDriver created successfully
2025-01-XX 10:30:15 - INFO - TV display loaded successfully. Title: Roulette TV Display
2025-01-XX 10:30:45 - INFO - Draw info: {'currentDrawNumber': 7, 'rolledNumbersCount': 6, 'lastUpdate': '2025-01-XX...'}
```

### **Health Checks:**
- **Page Responsiveness:** Verifies JavaScript is executing
- **System Status:** Checks all TV display systems are loaded
- **Draw Information:** Monitors current draw numbers
- **Auto-Recovery:** Restarts on failures

## 🎯 **Benefits of Headless Operation**

### **✅ Eliminates Idle Tab Issues:**
- **No Browser Throttling:** JavaScript runs continuously
- **No Catch-Up Processing:** No missed events to process
- **No Race Conditions:** Consistent execution timing
- **No Tab Switching Impact:** Independent of browser state

### **✅ Improved Reliability:**
- **24/7 Operation:** Runs continuously without interruption
- **Auto-Recovery:** Handles failures gracefully
- **Resource Management:** Prevents memory leaks
- **System Integration:** Can run as system service

### **✅ Better Performance:**
- **Dedicated Resources:** No competition with other browser tabs
- **Optimized Configuration:** Tuned for headless operation
- **Reduced Overhead:** No UI rendering or user interaction
- **Consistent Timing:** Predictable execution intervals

## 🔍 **Troubleshooting**

### **Common Issues:**

#### **Chrome Driver Issues:**
```bash
# Update Chrome driver automatically
pip install --upgrade webdriver-manager
```

#### **Permission Issues (Linux):**
```bash
# Run with appropriate permissions
sudo python headless_tv_display.py
```

#### **Port/URL Issues:**
```python
# Update configuration in script
config['url'] = 'http://your-server/slipp/tvdisplay/index.html'
```

#### **Memory Issues:**
```python
# Reduce restart interval
config['restart_interval'] = 1800  # 30 minutes instead of 1 hour
```

### **Debug Mode:**
```python
# Enable debug mode to see browser window
config['headless'] = False
config['log_level'] = logging.DEBUG
```

## 🎉 **Final Result**

**Your TV display will now run continuously:**
- ✅ **No more idle tab issues** - Runs independently of browser
- ✅ **No more draw number skips** - Consistent JavaScript execution
- ✅ **24/7 operation** - Continues running even when browser is closed
- ✅ **Auto-recovery** - Handles failures and restarts automatically
- ✅ **Production ready** - Can be installed as system service

**Choose the solution that best fits your needs and enjoy uninterrupted roulette system operation!**

## 📞 **Support**

If you encounter any issues:
1. Check the log files for error messages
2. Try debug mode with `headless: False`
3. Verify your localhost server is running
4. Test the TV display URL manually in browser first

**Your idle tab problem is now completely solved!** 🎯
