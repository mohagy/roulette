# Roulette POS System

A point-of-sale (POS) system for selling betting slips for roulette games with persistent state storage using PHP/MySQL.

## Setup Instructions

### Database Setup

1. Make sure XAMPP is running with MySQL and Apache services started
2. Access the setup script by navigating to: http://localhost/slipp/setup.php
   - This will automatically create the required database and tables
   - You should see a success message if everything worked correctly

### Configuration

If you need to customize the database connection:

1. Open `db_config.php` in your editor
2. Modify the following variables as needed:
   - `$host` - Database server (default: 'localhost')
   - `$dbname` - Database name (default: 'roulette')
   - `$username` - Database username (default: 'root')
   - `$password` - Database password (default: '')

### Running the Application

1. Make sure XAMPP is running with MySQL and Apache services
2. Navigate to the application in your browser:
   - http://localhost/slipp/tvdisplay/

### Resetting the Application State

If you need to reset the roll history and game state:

1. Navigate to: http://localhost/slipp/reset_database.php
2. This will clear all roll history data and reset the game to its initial state
3. Return to the game by clicking the link provided or navigate to http://localhost/slipp/tvdisplay/

## Features

- **Cashier Login System**: Secure login with 12-digit username and 6-digit password
- **Cash Management**: Track and manage cash balances for each cashier
- **Transaction History**: View detailed transaction history
- **Voucher System**: Create and redeem vouchers to add credits
- **Commission Tracking**: Track 4% commission on bets sold
- **Admin Panel**: Manage users, cash balances, and vouchers
- Persistent storage of roulette roll history across browser sessions
- Database-backed countdown timer that continues even when the browser is closed
- Persistent tracking of draw numbers
- Fallback to localStorage if database connection fails

## Cash System

The cash system allows cashiers to:

1. **Place Bets**: Deducts cash from the cashier's balance
2. **Win Bets**: Adds winnings to the cashier's balance
3. **Redeem Vouchers**: Add credits using voucher codes
4. **Track Transactions**: View all cash movements
5. **Track Commission**: Monitor 4% commission on bets sold

All cash balances and transactions are stored in the database and persist across page refreshes.

## Default Login Credentials

### Admin User
- Username: 000000000000
- Password: 000000

### Cashier User
- Username: 123456789012
- Password: 123456

## Project Structure

The application is organized into the following directories:

- **`/admin`** - Administration panel and management tools
- **`/api`** - API endpoints for system operations
- **`/php`** - PHP backend logic and APIs
- **`/js`** - JavaScript files for frontend functionality
- **`/css`** - Stylesheets for the application
- **`/tvdisplay`** - TV display application for showing roulette game
- **`/docs`** - Documentation files (see below)
- **`/accounting`** - Accounting module
- **`/finance`** - Financial management module
- **`/hr`** - Human resources module
- **`/it`** - IT module
- **`/sales`** - Sales module
- **`/stock`** - Stock and inventory management
- **`/remote`** - Remote access and control functionality
- **`/management`** - Management tools and interfaces

For detailed folder structure information, see [`docs/FOLDER_STRUCTURE.md`](docs/FOLDER_STRUCTURE.md).

## Documentation

Comprehensive documentation is available in the `/docs` folder:

- **[FOLDER_STRUCTURE.md](docs/FOLDER_STRUCTURE.md)** - Complete folder structure and file organization
- **[CLEANUP_SUMMARY.md](docs/CLEANUP_SUMMARY.md)** - Cleanup summary and development guidelines
- **[CLEANUP_REPORT.md](docs/CLEANUP_REPORT.md)** - Detailed cleanup report
- **Setup Guides** - Various setup and configuration guides
- **Feature Documentation** - Documentation for specific features
- **Fix Documentation** - Bug fix and implementation summaries

## Troubleshooting

If you experience any issues:

1. Check that XAMPP services are running
2. Try accessing the setup script again: http://localhost/slipp/setup.php
3. Reset the database state: http://localhost/slipp/reset_database.php
4. Check PHP error logs in XAMPP (usually at C:\xampp\php\logs\php_error_log)
5. Ensure your browser has JavaScript enabled
6. Clear your browser cache and cookies, then reload the page

If roll history is not displaying correctly:
- Check browser console for JavaScript errors
- Verify database connection is working
- Try resetting the database with reset_database.php

## Recent Updates

**January 7, 2026** - Comprehensive codebase cleanup:
- Removed 150+ test, debug, and temporary files
- Consolidated all documentation into `/docs` folder
- Organized project structure for better maintainability
- See [`docs/CLEANUP_REPORT.md`](docs/CLEANUP_REPORT.md) for details
