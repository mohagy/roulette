# Analytics Migration to analytics_history

## Overview

The analytics system has been migrated from `roulette_analytics` to a new `analytics_history` table that:
- Uses `preset_schedule` data as the source of truth
- Works with server time (America/Guyana timezone)
- Provides better tracking of draw sources (preset, manual, random)
- Stores draw times accurately based on server time

## New Tables

### analytics_history
Stores individual draw results with:
- `draw_number` - Draw number based on server time
- `winning_number` - Winning number (0-36)
- `winning_color` - Color (red, black, green)
- `draw_time` - Server time when draw occurred
- `source` - Source: preset_schedule, manual, or random
- `preset_schedule_id` - Reference to preset_schedule if from preset
- `is_preset` - Boolean flag if from preset
- `pattern_type` - Pattern type if from preset
- `scheduled_time` - Scheduled time from preset

### analytics_summary
Daily aggregated analytics for quick access:
- `date` - Date for summary
- `total_draws` - Total draws for date
- `red_count`, `black_count`, `green_count` - Color counts
- `number_frequency` - JSON frequency of each number
- `last_draw_number` - Last draw number for date

## Migration Steps

1. **Run the migration script:**
   ```
   http://your-domain/slipp/php/migrate_to_analytics_history.php
   ```

2. **The script will:**
   - Create new `analytics_history` and `analytics_summary` tables
   - Migrate data from `detailed_draw_results` to `analytics_history`
   - Link draws to `preset_schedule` if applicable
   - Create daily summaries in `analytics_summary`

3. **Update your code:**
   - TV display now uses `/api/get_analytics_history.php`
   - Draw saves automatically write to `analytics_history`
   - Old `roulette_analytics` table can be deprecated (not deleted yet)

## API Changes

### New Endpoint: `/api/get_analytics_history.php`
Returns analytics data with:
- Draw numbers based on server time
- Preset schedule information
- Accurate draw times

### Updated: `/api/get_recent_draws.php`
Now calculates current draw number from server time instead of `roulette_analytics`

### Updated: `/api/save_draw_result.php`
Now also saves to `analytics_history` table

## TV Display Changes

The TV display (`tvdisplay/js/scripts.js`) now:
- Uses `/api/get_analytics_history.php` instead of `/api/get_recent_draws.php`
- Displays draws with correct server time
- Shows preset schedule information when available

## Benefits

1. **Accurate Time Tracking:** Uses server time (America/Guyana) for all draws
2. **Preset Integration:** Directly links to preset_schedule for forced results
3. **Better Source Tracking:** Knows if draw came from preset, manual, or random
4. **Separate Analytics:** Analytics data is now in its own dedicated table
5. **No Duplicates:** Unique constraint on draw_number prevents duplicates

## Deprecation

The `roulette_analytics` table is now deprecated but not deleted for safety. 
All new code should use `analytics_history` instead.

