# Commission System Fix

This package contains scripts to diagnose and fix issues with the per-cashier commission filtering in the roulette system.

## Issue Description

The commission page is not showing the correct user-specific commission data on the production server at roulette.aruka.app, even though the user can see their transactions on the my_transactions_new.php page.

## Diagnostic Scripts

Upload these scripts to your server and run them to diagnose the issue:

1. **production_commission_check.php** - Checks the database structure and data related to commissions
2. **check_commission_calculation.php** - Checks if commissions are being calculated correctly
3. **check_commission_file.php** - Checks if the commission.php file has the necessary fixes
4. **check_update_commission_file.php** - Checks if the update_commission.php file has the necessary fixes
5. **check_save_betting_slip_file.php** - Checks if the save_betting_slip.php file has the necessary fixes

## Fix Scripts

After diagnosing the issue, use these scripts to fix it:

1. **fix_production_commission.php** - Fixes commission data in the database for the current user
2. **update_commission_files.php** - Updates all the necessary files with the required fixes (requires admin access)
3. **fix_commission_db.php** - Fixes the database structure and data (created by update_commission_files.php)

## How to Use

### Step 1: Diagnose the Issue

1. Upload all the diagnostic scripts to your server
2. Log in to the roulette system as a cashier
3. Run each diagnostic script by visiting its URL in your browser (e.g., https://roulette.aruka.app/slipp/production_commission_check.php)
4. Review the JSON output to identify the issues

### Step 2: Fix the Issue

1. Upload the fix scripts to your server
2. Log in to the roulette system as a cashier
3. Run fix_production_commission.php to fix the commission data for your user
4. Log in as an admin
5. Run update_commission_files.php to update all the necessary files
6. Run fix_commission_db.php to fix the database structure and data

### Step 3: Verify the Fix

1. Log in to the roulette system as a cashier
2. Visit the commission page (https://roulette.aruka.app/slipp/commission.php)
3. Verify that your commission data is now showing correctly

## Detailed Explanation of Fixes

### Database Fixes

1. Fixes NULL user_id values in the commission_summary table
2. Adds a NOT NULL constraint to the user_id column
3. Adds a unique constraint on the combination of user_id and date_created

### Code Fixes

1. Updates commission.php to validate the user_id and redirect if invalid
2. Updates update_commission.php to use a default user_id if the session value is invalid
3. Updates save_betting_slip.php to validate the user_id and use a default if invalid

## Troubleshooting

If you encounter any issues:

1. Check the server error logs for more information
2. Verify that all files were uploaded correctly
3. Make sure you have the necessary permissions to modify files and database tables
4. If the fixes don't work, try running the diagnostic scripts again to identify any remaining issues

## Contact

If you need further assistance, please contact the developer who provided these scripts.
