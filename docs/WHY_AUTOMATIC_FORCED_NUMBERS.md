# Why "Automatic" Forced Numbers Were Used

## Root Cause

The system was using "Automatic" forced numbers because of the **Auto-Apply** feature and **Automatic Mode** settings in the admin panel.

## How Automatic Forced Numbers Are Created

### 1. **Auto-Apply Feature**
When the "Auto-Apply" toggle is enabled in the admin panel:
- The system automatically applies preset schedule numbers to the database
- These are saved with `source='automatic'` instead of `source='preset_schedule'`
- This was intended to keep the database in sync with preset schedules

### 2. **Automatic Mode**
When the system is in "Automatic Mode":
- The `keep_auto_mode=true` parameter is sent when setting winning numbers
- This causes numbers to be saved with `source='automatic'`
- Code location: `api/set_winning_number.php` line 233:
  ```php
  $source = $keepAutoMode ? 'automatic' : 'manual';
  ```

### 3. **Smart Number Selection**
The "Smart Number Selection" or "Auto-Select" feature:
- Automatically selects winning numbers based on betting patterns
- Saves these with `source='automatic'` and reason "Auto-selected by smart system"
- This was meant to help minimize payouts

## The Problem

**58 automatic forced numbers** were created over time, and they were:
- Overriding preset schedule numbers
- Showing as "Automatic" instead of "Preset Schedule"
- Not being cleaned up after draws completed
- Accumulating in the database

## Why This Happened

1. **Auto-Apply was enabled** - The system was automatically applying numbers from preset schedule, but saving them as "automatic" instead of recognizing them as preset
2. **No cleanup mechanism** - Automatic forced numbers weren't being deleted after draws completed
3. **Priority logic issue** - The API was checking automatic forced numbers before checking preset schedule

## The Fix

1. **Cleaned up all automatic forced numbers** - Deleted 58 automatic entries
2. **Updated priority logic** - Preset schedule now takes priority over automatic forced numbers
3. **Added cleanup** - Forced numbers are now automatically deleted after draws complete
4. **Updated API** - Always returns preset schedule numbers when available, only uses automatic if no preset exists

## Current Behavior

Now the system:
- ✅ **Always uses preset schedule numbers by default** (shows "Preset Schedule")
- ✅ **Only shows "Manually Set"** when you manually override a number
- ✅ **Only shows "Automatic"** if there's no preset schedule (shouldn't happen now)
- ✅ **Automatically cleans up** forced numbers after draws complete

## Prevention

To prevent automatic forced numbers from accumulating:
- Keep Auto-Apply disabled if you want to use preset schedule directly
- Manual forced numbers are automatically cleaned up after draws complete
- The system now prioritizes preset schedule over automatic forced numbers

