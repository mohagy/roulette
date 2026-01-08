# 🎥 Video Upload Guide for Cashier Roulette Tutorial

## 📋 Quick Start Instructions

### **Step 1: Set Up Directory Structure**
1. **Run the setup script:**
   - Double-click `setup-media-structure.bat` in your tutorial folder
   - This creates the required `media/videos/` directory

2. **Verify directory creation:**
   ```
   📁 Your tutorial folder/
   ├── 📁 media/
   │   ├── 📁 screenshots/
   │   └── 📁 videos/  ← Your videos go here
   ```

### **Step 2: Prepare Your Video Files**

#### **Required Video Files:**
1. **`01-placing-straight-up-bet.mp4`**
   - Duration: 30-45 seconds
   - Content: Demonstrate straight-up betting process

2. **`02-complete-betting-transaction.mp4`**
   - Duration: 2-3 minutes  
   - Content: Full betting workflow

#### **File Format Requirements:**
- **Format:** MP4 only
- **Codec:** H.264 (most common)
- **Resolution:** 1080p preferred (1920x1080)
- **Max File Size:** 50MB per video
- **Audio:** Optional but recommended for narration

### **Step 3: Copy Videos to Correct Location**

#### **Manual File Copy Method (Recommended):**

1. **Locate your recorded videos** on your computer

2. **Rename them to exact required names:**
   - `01-placing-straight-up-bet.mp4`
   - `02-complete-betting-transaction.mp4`

3. **Copy files to the videos directory:**
   - Navigate to: `C:\Users\user\Desktop\slipp\media\videos\`
   - Paste your renamed video files here

4. **Verify file placement:**
   ```
   📁 media/videos/
   ├── 01-placing-straight-up-bet.mp4
   └── 02-complete-betting-transaction.mp4
   ```

### **Step 4: Verify Upload Success**

#### **Method 1: Use Validation Tool**
1. **Open validation tool:**
   - Double-click `validate-media-files.html`
   - Or open in browser: `file:///C:/Users/user/Desktop/slipp/validate-media-files.html`

2. **Check video status:**
   - Videos should show "✅ Found" status
   - File sizes should be displayed
   - Any missing files will show "❌ Missing"

#### **Method 2: Test in Tutorial**
1. **Open the tutorial:**
   - Double-click `cashier-roulette-tutorial.html`
   - Or open in browser: `file:///C:/Users/user/Desktop/slipp/cashier-roulette-tutorial.html`

2. **Navigate to video sections:**
   - Scroll to "Basic Operations" section
   - Look for video players with your content

3. **Test video playback:**
   - Click play button on videos
   - Verify videos load and play correctly
   - Check audio if included

## 🔧 Troubleshooting

### **❌ Video Not Found Error**
**Problem:** Tutorial shows "Video not found" message

**Solutions:**
1. **Check filename exactly:**
   - Must be: `01-placing-straight-up-bet.mp4` (exact spelling)
   - Must be: `02-complete-betting-transaction.mp4` (exact spelling)
   - Case-sensitive on some systems

2. **Verify file location:**
   - Files must be in: `media/videos/` directory
   - Not in subfolders or other locations

3. **Check file format:**
   - Must be `.mp4` extension
   - Not `.mov`, `.avi`, or other formats

### **❌ Video Won't Play**
**Problem:** Video file found but won't play in browser

**Solutions:**
1. **Check video codec:**
   - Use H.264 codec (most compatible)
   - Re-encode if using different codec

2. **Verify file integrity:**
   - Try playing video in media player first
   - Re-record if file is corrupted

3. **Check file size:**
   - Keep under 50MB for web compatibility
   - Compress if too large

### **❌ File Size Too Large**
**Problem:** Video file exceeds 50MB limit

**Solutions:**
1. **Compress video:**
   - Use video compression software
   - Reduce resolution if necessary (720p acceptable)
   - Adjust bitrate settings

2. **Trim content:**
   - Remove unnecessary intro/outro
   - Focus on essential demonstration only

## 📱 Alternative Upload Methods

### **Method 1: Drag and Drop**
1. Open file explorer to your video files
2. Open another window to `media/videos/` folder
3. Drag video files from source to destination
4. Rename files to required names

### **Method 2: Copy/Paste**
1. Right-click your video file → Copy
2. Navigate to `media/videos/` folder
3. Right-click → Paste
4. Right-click pasted file → Rename to required name

## ✅ Success Checklist

Before considering upload complete, verify:

- [ ] Directory structure exists (`media/videos/` folder)
- [ ] Both video files present with exact names
- [ ] Files are in MP4 format
- [ ] File sizes under 50MB each
- [ ] Validation tool shows "✅ Found" for both videos
- [ ] Videos play correctly in tutorial
- [ ] Audio works if included

## 🎯 Video Content Specifications

### **Video 1: Placing a Straight-Up Bet**
**Filename:** `01-placing-straight-up-bet.mp4`

**Content to record:**
1. Start with clean POS interface
2. Click "GO ON" for STRAIGHT betting in left panel
3. Click on number 17 on green roulette table
4. Show purple "100" chip appearing on number
5. Highlight $100.00 stake and $3600.00 return amounts

### **Video 2: Complete Betting Transaction**
**Filename:** `02-complete-betting-transaction.mp4`

**Content to record:**
1. Demonstrate multiple bet types (STRAIGHT, CORNER, SPLIT, STREET)
2. Place bets on different roulette table areas
3. Show purple "100" chips accumulating
4. Use "Calculate Change" button
5. Use "Print Betting Slip" button
6. Show "QCancel Bets" and "REMOVE" functionality

## 📞 Need Help?

If you continue having issues:
1. Check the `README.txt` file in the `media/` directory
2. Verify your video files play in a standard media player
3. Ensure exact filename spelling and MP4 format
4. Try the validation tool to identify specific issues

---

**✅ Once videos are successfully uploaded, your tutorial will have fully integrated media content for comprehensive cashier training!**
