# Forced Number Priority System

## Overview
This document explains how forced winning numbers interact with preset schedules and the priority system.

## Priority Order

1. **Manual Forced Numbers** (Highest Priority)
   - When you manually set a winning number using "Set Winning Number" button
   - Source: `'manual'`
   - **These ALWAYS override preset schedule numbers**
   - Intended for administrator overrides

2. **Preset Schedule Numbers** (Medium Priority)
   - Numbers from the active preset schedule
   - Source: `'preset_schedule'`
   - Used when no manual forced number exists
   - Can override automatic forced numbers

3. **Automatic Forced Numbers** (Lowest Priority)
   - Numbers set by automatic/smart systems
   - Source: `'automatic'`
   - Can be overridden by preset schedule or manual numbers

## How It Works

### Setting a Manual Forced Number

1. Go to the admin panel
2. Use the "Set Winning Number" section
3. Enter the winning number you want
4. Click "Set Winning Number" or "Apply"
5. The system will save it with `source='manual'` in `next_draw_winning_number` table

### When a Manual Forced Number is Set

- ✅ **It will override the preset schedule number** for that draw
- ✅ The preset schedule number will be ignored for that specific draw
- ✅ The manual number will be used when the draw executes

### Example Scenario

**Preset Schedule:**
- Draw #256: Scheduled number = 0 (green)

**You Set Manual Forced Number:**
- Draw #256: Manual number = 7 (red)

**Result:**
- Draw #256 will use **7 (red)** - your manual number
- The preset schedule number (0) is ignored for this draw
- The preset schedule continues normally for other draws

## API Behavior

The `api/direct_forced_number.php` endpoint checks in this order:

1. Check `next_draw_winning_number` table for forced number
2. If found and `source='manual'` → **Use it (override preset)**
3. If found and `source='automatic'` → Check preset schedule
4. If preset exists and differs → Use preset (override automatic)
5. If no forced number → Use preset schedule if available

## Database Structure

The `next_draw_winning_number` table stores:
- `draw_number`: The draw number this applies to
- `winning_number`: The forced winning number (0-36)
- `source`: `'manual'`, `'automatic'`, or `'preset_schedule'`
- `reason`: Description of why this number was set

## Notes

- Manual forced numbers are intended for **temporary overrides**
- They only affect the specific draw number they're set for
- After the draw completes, the forced number is removed
- The preset schedule continues for subsequent draws
- You can set manual numbers for future draws in advance

