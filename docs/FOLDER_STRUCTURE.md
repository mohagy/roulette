# Roulette POS System - Folder Structure

This document describes the organized folder structure of the Roulette POS System after cleanup.

## Root Directory Files

### Core Application Files
- **index.php** - Main entry point, checks authentication and includes index.html
- **index.html** - Main application interface
- **login.php** - Login page and authentication handler
- **login.html** - Login page interface
- **logout.php** - Logout handler
- **db_config.php** - Database configuration and connection
- **setup.php** - Database setup and initialization script
- **reset_database.php** - Database reset utility
- **manifest.json** - PWA manifest file
- **service-worker.js** - Service worker for PWA functionality

### User Management
- **create_user.php** - User creation utility
- **reset_password.php** - Password reset functionality
- **direct_login.php** - Direct login handler

### Transaction & Cash Management
- **my_transactions_new.php** - Transaction history page
- **my_transactions_updated.js** - Transaction page JavaScript
- **redeem_voucher.php** - Voucher redemption handler
- **get_cash_balance.php** - Cash balance API endpoint
- **update_cash_balance.php** - Cash balance update handler
- **update_cash_from_bets.php** - Cash update from betting operations

### Commission & Analytics
- **commission.php** - Commission tracking page
- **update_commission.php** - Commission update handler
- **analytics_monitor_dashboard.php** - Analytics monitoring dashboard
- **load_analytics.php** - Load analytics data
- **save_analytics.php** - Save analytics data

### State Management
- **load_state.php** - Load application state
- **save_state.php** - Save application state
- **sync_timer.php** - Timer synchronization
- **sync_draw_timer.php** - Draw timer synchronization

### Draw Management
- **draw_header.php** - Draw header component
- **update_draw_number.php** - Draw number update handler

### Viewing & Reports
- **view_betting_slips.php** - Betting slip viewer

### Admin Files
- **admin.php** - Admin panel
- **admin_cash.php** - Admin cash management
- **admin_vouchers.php** - Admin voucher management

### Setup Files (Keep for initial setup)
- **setup_betting_tables.php** - Betting tables setup
- **setup_cash_system.php** - Cash system setup
- **setup_database.php** - Database setup
- **setup_draw_tables.php** - Draw tables setup
- **setup_forced_numbers.php** - Forced numbers setup
- **setup_login.php** - Login system setup
- **setup_sisp_database.php** - SISP database setup
- **setup_users_table.php** - Users table setup
- **login_setup.php** - Login setup utility

### Utility Scripts
- **setup.bat** - Windows setup batch file
- **install_printing.bat** - Printing installation batch file
- **start_headless_tv.bat** - Start headless TV display
- **copy-videos-helper.bat** - Video copying helper

### Python Scripts
- **headless_tv_display.py** - Headless TV display Python script
- **install_tv_service.py** - TV service installation
- **setup_headless_tv.py** - Headless TV setup
- **simple_tv_keepalive.py** - TV keepalive script

### Other Files
- **requirements.txt** - Python dependencies
- **headless_tv_display.js** - Headless TV display JavaScript
- **headless_tv_display.log** - TV display log file
- **redirect.php** - Redirect handler
- **ajax_login.php** - AJAX login handler
- **roulette (10).sql** - Database backup/dump

## Directories

### /accounting
Accounting module for the system
- Contains countdown timer and warning system components
- Includes positioning and UI components

### /admin
Administration panel and management tools
- **index.php** - Admin dashboard
- **analytics.php** - Analytics management
- **analytics_dashboard.php** - Analytics dashboard
- **bet_distribution.php** - Bet distribution analysis
- **betting_history.php** - Betting history viewer
- **betting_shops.php** - Betting shops management
- **betting_shops_add.php** - Add betting shops
- **betting_shops_setup.php** - Betting shops setup
- **betting_shops_users.php** - Betting shop users
- **betting_shops_view.php** - View betting shops
- **cash.php** - Cash management
- **commission.php** - Commission management
- **departments_setup.php** - Departments setup
- **factory_reset.php** - Factory reset utility
- **game_settings.php** - Game settings configuration
- **get_bet_details.php** - Get bet details API
- **hr_setup.php** - HR setup
- **remote_setup.php** - Remote setup
- **sidebar.php** - Admin sidebar component
- **stock_accounting_setup.php** - Stock accounting setup
- **system_logs.php** - System logs viewer
- **transactions.php** - Transactions management
- **update_next_draw.php** - Update next draw
- **users.php** - User management
- **vouchers.php** - Voucher management
- **betting-limits-working.html** - Betting limits interface
- **db_setup.php** - Database setup for admin

#### /admin/api
- **betting_shops_data.php** - Betting shops data API

#### /admin/css
- **admin.css** - Admin panel styles

### /api
API endpoints for various system operations
- **betting_limits_api.php** - Betting limits API
- **cashier_draw_sync.php** - Cashier draw synchronization
- **direct_forced_number.php** - Direct forced number API
- **draw_info.php** - Draw information API
- **equipment_issues_api.php** - Equipment issues API
- **get_basic_data.php** - Basic data retrieval
- **get_current_draw.php** - Current draw information
- **get_next_winning_number.php** - Next winning number
- **get_transactions_data.php** - Transactions data API
- **inventory_api.php** - Inventory management API
- **php_print_solution.php** - PHP printing solution
- **print_slip_api.php** - Print slip API
- **print_slip.py** - Python print slip script
- **purchase_orders_api.php** - Purchase orders API
- **safe_draw_advance.php** - Safe draw advance
- **save_draw_result.php** - Save draw result
- **set_manual_winning_number.php** - Set manual winning number
- **set_mode.php** - Set game mode
- **set_next_winning_number.php** - Set next winning number
- **set_winning_number.php** - Set winning number
- **test_python.php** - Python test script
- **toggle_mode.php** - Toggle game mode
- **tv_sync.php** - TV synchronization
- **upcoming_draws_stats.php** - Upcoming draws statistics
- **update_timer_settings.php** - Update timer settings
- **vendors_api.php** - Vendors API

### /assets
Static assets for the application
- Contains CSS and JavaScript files
- Includes dashboard styles and sidebar loader

### /backups
Database and file backups

### /css
CSS stylesheets for the main application
- **auth.css** - Authentication styles
- **style.css** - Main styles
- **login.css** - Login page styles
- **custom-styles.css** - Custom styles
- Various component-specific stylesheets for:
  - Cancel slip functionality
  - Cashier panels
  - Draw displays
  - Sidebar layouts
  - Upcoming draws
  - Action buttons
  - And more...

### /docs
Documentation files (consolidated from root)
- All technical documentation and fix summaries
- Implementation guides
- Setup instructions
- Bug fix documentation
- Feature documentation
- **roulette_state_normalization.md** - State normalization guide
- **FOLDER_STRUCTURE.md** - This file

### /finance
Financial management module

### /hr
Human resources module

### /images
Image assets and icons
- Contains application icons and graphics

### /includes
PHP include files and shared components

### /it
IT-related files and utilities

### /js
JavaScript files for the main application
- **api.js** - API communication
- **auth-check.js** - Authentication checking
- **login.js** - Login functionality
- **scripts.js** - Main scripts
- **automatic_printing.js** - Automatic printing
- **roulette_print_integration.js** - Roulette print integration
- Various component-specific JavaScript files for:
  - Betting functionality
  - Cash management
  - Cashout operations
  - Draw synchronization
  - Timer management
  - UI components
  - And more...

### /logs
Application log files

### /management
Management tools and interfaces
- Contains draw control functionality

### /media
Media files (videos, images, etc.)

### /node_modules
Node.js dependencies (WebSocket library)

### /php
PHP backend files and APIs
- **analytics_monitor.php** - Analytics monitoring
- **analytics_protection.php** - Analytics protection
- **auto_winning_number.php** - Automatic winning number
- **background_processor.php** - Background processing
- **cache_prevention.php** - Cache prevention
- **cancel_betting_slip.php** - Cancel betting slip
- **cashout_api.php** - Cashout API
- **cashout_api_fixed.php** - Fixed cashout API
- **check_database.php** - Database checker
- **check_player.php** - Player checker
- **complete_setup.php** - Complete setup
- **create_guest_player.php** - Create guest player
- **db_config.php** - Database configuration
- **db_connect.php** - Database connection
- **draw_number_manager.php** - Draw number manager
- **draw_sync.php** - Draw synchronization
- **dual_storage_api.php** - Dual storage API
- **ensure_guest_player.php** - Ensure guest player exists
- **fix_bet_table.php** - Fix bet table
- **fix_database.php** - Fix database
- **game_api.php** - Game API
- **gap_alert_api.php** - Gap alert API
- **get_bet_distribution.php** - Get bet distribution
- **get_commission_data.php** - Get commission data
- **get_current_bets.php** - Get current bets
- **get_detailed_draw_results.php** - Get detailed draw results
- **get_draw_bet_counts.php** - Get draw bet counts
- **get_draw_details.php** - Get draw details
- **get_draw_history.php** - Get draw history
- **get_georgetown_time.php** - Get Georgetown time
- **get_last_completed_draw_details.php** - Get last completed draw details
- **get_latest_detailed_results.php** - Get latest detailed results
- **get_latest_draw_number.php** - Get latest draw number
- **get_latest_roulette_draw.php** - Get latest roulette draw
- **get_latest_roulette_draws.php** - Get latest roulette draws
- **get_latest_winning_number.php** - Get latest winning number
- **get_next_draw_info.php** - Get next draw info
- **get_next_draw_number.php** - Get next draw number
- **get_slip_id.php** - Get slip ID
- **high_performance_storage_api.php** - High performance storage
- **install_draw_results.php** - Install draw results
- **manual_test.php** - Manual test
- **migrate_analytics_data.php** - Migrate analytics data
- **my_transactions_api.php** - My transactions API
- **ping.php** - Ping endpoint
- **player_api.php** - Player API
- **process_cashout.php** - Process cashout
- **redeem_voucher.php** - Redeem voucher
- **reprint_slip_api.php** - Reprint slip API
- **reprint_slip_direct.php** - Direct reprint slip
- **roulette_analytics.php** - Roulette analytics
- **roulette_color.php** - Roulette color utilities
- **safe_spin_api.php** - Safe spin API
- **save_betting_slip.php** - Save betting slip
- **save_detailed_draw_result.php** - Save detailed draw result
- **save_winning_number.php** - Save winning number
- **secure_analytics.php** - Secure analytics
- **sequential_draw_manager.php** - Sequential draw manager
- **setup_database.php** - Setup database
- **slip_api.php** - Slip API
- **test_automatic_selection.php** - Test automatic selection
- **test_save_winning_number.php** - Test save winning number
- **test_winning_number.php** - Test winning number
- **toggle_automatic_mode.php** - Toggle automatic mode
- **triple_storage_api.php** - Triple storage API
- **tv_betting_api.php** - TV betting API
- **ultra_fast_storage_api.php** - Ultra fast storage API
- **update_betting_slips_for_reprint.php** - Update betting slips for reprint
- **update_draw.php** - Update draw
- **verify_cashout_flow.php** - Verify cashout flow

### /remote
Remote access and control functionality
- **login.php** - Remote login
- **logout.php** - Remote logout
- **bet_distribution.php** - Bet distribution viewer

#### /remote/api
- **heartbeat.php** - Heartbeat endpoint
- **log_activity.php** - Activity logging
- **trigger_draw.php** - Trigger draw remotely

### /sales
Sales module and dashboard
- **index.php** - Sales dashboard

#### /sales/api
- **sales_dashboard_data.php** - Sales dashboard data API

### /sounds
Sound files and audio assets
- **new-draw.js** - New draw sound handler

### /sql
SQL scripts and database schemas
- **add_transaction_id_column.sql** - Add transaction ID column
- **add_voucher_transactions_table.sql** - Add voucher transactions table
- **normalize_roulette_state.sql** - Normalize roulette state
- **restructure_roulette_analytics.sql** - Restructure roulette analytics

### /stock
Stock and inventory management
- **index.php** - Stock dashboard
- **equipment_issues.php** - Equipment issues tracker
- **inventory.php** - Inventory management
- **purchase_orders.php** - Purchase orders
- **vendors.php** - Vendors management

#### /stock/api
- **stock_dashboard_data.php** - Stock dashboard data API

### /tvdisplay
TV display application for showing roulette game
- Main TV display interface and functionality
- Wheel control and synchronization
- Analytics display
- Video streaming
- Countdown handlers
- Multiple markdown documentation files for TV display features

#### /tvdisplay/css
- **style.css** - TV display styles
- **analytics-panel.css** - Analytics panel styles
- **live-stream-player.css** - Live stream player styles
- **ads-player.css** - Ads player styles

#### /tvdisplay/js
- Extensive JavaScript files for:
  - Wheel control and synchronization
  - Storage APIs (dual, triple, high-performance)
  - Analytics and data persistence
  - Live streaming
  - Draw management
  - Forced number handling
  - Georgetown time display
  - And more...

### /tvdisplayfonts
Fonts for TV display

## Cleanup Summary

The following types of files were removed during cleanup:
- **Test files**: test_*.php, test_*.html, test-*.html (60+ files)
- **Debug files**: debug_*.php, debug_*.html, debug_*.js (10+ files)
- **Check files**: check_*.php (25+ files)
- **Fix files**: fix_*.php, *-fix*.js (20+ files)
- **Migration files**: migrate_*.php (4 files)
- **Verification files**: verify_*.php (3 files)
- **Investigation files**: investigate_*.php (3 files)
- **Monitor files**: monitor_*.php (4 files)
- **Analysis files**: analyze_*.php (2 files)
- **Temporary files**: Various temporary SQL, HTML, CSS, JS files
- **Duplicate files**: Backup and duplicate versions
- **Documentation**: Moved 39 markdown files from root to /docs folder

Total files removed: **150+ files**

## Best Practices

1. **Keep root directory clean**: Only essential entry points and configuration files
2. **Organize by module**: Related files grouped in directories
3. **Separate concerns**: API, CSS, JS, PHP in their own directories
4. **Documentation**: All docs in /docs folder
5. **Backups**: Use /backups folder for database backups
6. **Logs**: Application logs in /logs folder

## Development Guidelines

1. **New features**: Create files in appropriate module directories
2. **Testing**: Use separate test environment, don't commit test files
3. **Documentation**: Add new docs to /docs folder
4. **API endpoints**: Add to /api directory
5. **Styles**: Component styles in /css directory
6. **Scripts**: JavaScript in /js directory


