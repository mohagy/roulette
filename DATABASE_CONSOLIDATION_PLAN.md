# Database Consolidation Plan

## Current Situation

### Tables Storing Draw Results:
1. **`detailed_draw_results`** ✅ PRIMARY SOURCE
   - Used by: `api_complete_analytics.php` (TV display)
   - Contains: draw_number, winning_number, winning_color, draw_time, etc.
   - **KEEP** - This is the primary source of truth

2. **`game_history`** ❌ REDUNDANT
   - Contains: winning_number, winning_color, draw_id, played_at
   - **REMOVE** - Duplicates `detailed_draw_results`

3. **`roulette_draw_history`** ❌ REDUNDANT
   - Contains: draw_number, winning_number, winning_color, is_manual
   - **REMOVE** - Duplicates `detailed_draw_results`

4. **`roulette_analytics`** ✅ KEEP (Aggregated Data)
   - Contains: all_spins (JSON), number_frequency (JSON), current_draw_number
   - **KEEP** - Used for quick aggregated analytics access

## Consolidation Strategy

### Primary Tables to Use:
1. **`detailed_draw_results`** - Individual draw results (source of truth)
2. **`roulette_analytics`** - Aggregated analytics data (cached for performance)

### Tables to Remove/Deprecate:
1. **`game_history`** - Remove all writes, can be dropped
2. **`roulette_draw_history`** - Remove all writes, can be dropped

## Files to Update:

1. `api/save_draw_result.php` - Remove writes to game_history and roulette_draw_history
2. `php/save_winning_number.php` - Remove writes to game_history
3. `php/tv_betting_api.php` - Remove writes to game_history
4. `php/slip_api.php` - Remove writes to game_history
5. `php/game_api.php` - Remove writes to game_history
6. `php/verify_cashout_flow.php` - Remove writes to game_history

## TV Display Usage:

- **`api_complete_analytics.php`** ✅ Uses `detailed_draw_results` only
- **`load_analytics.php`** ✅ Uses `roulette_analytics` only

Both are correct and should continue working.


