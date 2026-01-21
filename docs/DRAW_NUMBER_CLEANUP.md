# Draw Number Cleanup - Abnormal Draws Removed

## Summary
All abnormal draw numbers (> 480) have been removed from the database. Draw numbers are now constrained to 1-480 (one day's worth of draws at 3-minute intervals).

## Actions Taken

### 1. Database Cleanup
- **analytics_history**: Deleted 397 records with draw_number > 480
- **detailed_draw_results**: Deleted 409 records with draw_number > 480
- **next_draw_winning_number**: Deleted 1 record with draw_number > 480

**Total cleaned**: 807 abnormal records removed

### 2. Validation Added

#### `api/save_draw_result.php`
- Added validation to reject draw numbers > 480
- Added validation to reject draw numbers < 1
- Error message: "Invalid draw number: Draw numbers must be between 1 and 480"

#### `api/get_current_draw.php`
- Added cap to ensure calculated draw number never exceeds 480
- Draw numbers are calculated based on server time (America/Guyana)
- Formula: `floor((hours * 60 + minutes) / 3) + 1`, capped at 480

#### `api/get_analytics_history.php`
- Added WHERE clause to filter out any draws with draw_number > 480
- Query: `WHERE draw_number >= 1 AND draw_number <= 480`

### 3. Draw Number System
- **Range**: 1-480 per day
- **Interval**: 3 minutes per draw
- **Reset**: Draw numbers reset to 1 at midnight (00:00)
- **Calculation**: Based on server time in America/Guyana timezone

## Verification
After cleanup:
- Max draw number in `analytics_history`: 480 ✓
- Max draw number in `detailed_draw_results`: 480 ✓
- No draws with draw_number > 480 remain in any table ✓

## Prevention
Future draws exceeding 480 will be:
1. Rejected at the API level (`api/save_draw_result.php`)
2. Filtered out in queries (`api/get_analytics_history.php`)
3. Capped in calculations (`api/get_current_draw.php`)

## Files Modified
- `api/save_draw_result.php` - Added validation
- `api/get_current_draw.php` - Added cap
- `api/get_analytics_history.php` - Added filter
- `php/cleanup_abnormal_draws.php` - Cleanup script (can be run again if needed)
- `php/check_abnormal_draws.php` - Verification script

## Date
Completed: 2026-01-17

