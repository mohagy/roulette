<?php
/**
 * Bet Distribution Configuration
 * Centralized configuration for API endpoints, intervals, and default values
 */

// API Endpoints
define('API_UPCOMING_DRAWS', '../api/upcoming_draws_stats.php');
define('API_BET_DISTRIBUTION', '../php/get_bet_distribution.php');
define('API_DRAW_INFO', '../api/draw_info.php');
define('API_TOGGLE_MODE', '../api/toggle_mode.php');
define('API_SET_WINNING_NUMBER', '../api/set_winning_number.php');
define('API_SAVE_PRESET_SCHEDULE', '../api/save_preset_schedule.php');
define('API_LOAD_PRESET_SCHEDULE', '../api/load_preset_schedule.php');
define('API_CHECK_PRESET_SCHEDULE', '../api/check_preset_schedule.php');
define('API_DIRECT_FORCED_NUMBER', '../api/direct_forced_number.php');
define('API_GET_CURRENT_PRESET', '../api/get_current_preset.php');
define('API_SMART_SELECTION', '../api/smart_number_selection.php');
define('API_GET_SMART_SETTINGS', '../api/get_smart_selection_settings.php');
define('API_SAVE_SMART_SETTINGS', '../api/save_smart_selection_settings.php');

// Refresh Intervals (in milliseconds)
define('AUTO_REFRESH_INTERVAL', 15000); // 15 seconds
define('DRAW_INFO_REFRESH_INTERVAL', 5000); // 5 seconds
define('FORCED_NUMBER_CHECK_INTERVAL', 30000); // 30 seconds (manual mode)
define('FORCED_NUMBER_CHECK_INTERVAL_AUTO', 5000); // 5 seconds (auto mode)

// Default Values
define('DEFAULT_DRAW_COUNT', 10);
define('DEFAULT_TIMER_INTERVAL', 60); // seconds
define('PRESET_SCHEDULE_DRAWS', 480); // 24 hours * 20 draws per hour
define('DRAW_INTERVAL_MINUTES', 3);

// Feature Flags
define('FEATURE_PRESET_SCHEDULE', true);
define('FEATURE_SMART_SELECTION', true);
define('FEATURE_FORCED_NUMBERS', true);
define('FEATURE_AUTO_REFRESH', true);
define('FEATURE_FIRESTORE_SYNC', true);

// Chart Configuration
define('CHART_HEIGHT', 400);
define('CHART_ANIMATION_DURATION', 800);

// Error Handling
define('API_RETRY_ATTEMPTS', 3);
define('API_RETRY_DELAY', 1000); // milliseconds
define('API_TIMEOUT', 10000); // milliseconds

// Cache Busting
define('ENABLE_CACHE_BUSTING', true);

