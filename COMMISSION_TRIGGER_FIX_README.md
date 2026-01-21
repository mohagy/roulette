# Commission Trigger Fix

This package contains scripts to fix the issue with the per-cashier commission filtering in the roulette system.

## Issue Description

The commission page is not showing the correct user-specific commission data because the database trigger that updates the commission_summary table is missing the `user_id` field. This causes commission data to be stored without being properly associated with the user who made the bet.

## Fix Scripts

1. **fix_commission_trigger.php** - Fixes the database trigger and rebuilds the commission_summary data
2. **verify_commission_data.php** - Verifies that the commission data is now correctly associated with the user

## How to Use

### Step 1: Fix the Database Trigger

1. Upload the fix_commission_trigger.php script to your server
2. Log in to the roulette system as an admin
3. Run the script by visiting its URL in your browser (e.g., https://roulette.aruka.app/slipp/fix_commission_trigger.php)
4. The script will:
   - Drop the existing trigger
   - Create a new trigger that includes the user_id field
   - Fix existing commission_summary records with NULL or 0 user_id
   - Rebuild the commission_summary data from the commission records

### Step 2: Verify the Fix

1. Upload the verify_commission_data.php script to your server
2. Log in to the roulette system as a cashier
3. Run the script by visiting its URL in your browser (e.g., https://roulette.aruka.app/slipp/verify_commission_data.php)
4. The script will:
   - Check if the commission trigger exists and is correct
   - Check if commission records exist for the current user
   - Check if commission summary records exist for the current user
   - Calculate the expected commission based on betting slips
   - Compare the expected commission with the actual commission
   - Provide recommendations if any issues are found

### Step 3: Test the Commission Page

1. Log in to the roulette system as a cashier
2. Visit the commission page (https://roulette.aruka.app/slipp/commission.php)
3. Verify that your commission data is now showing correctly

## Detailed Explanation of the Fix

The issue was in the database trigger that updates the commission_summary table when a new commission record is inserted. The trigger was missing the `user_id` field, which caused the commission data to be stored without being properly associated with the user who made the bet.

Here's the original trigger:

```sql
CREATE TRIGGER update_commission_summary AFTER INSERT ON commission
FOR EACH ROW BEGIN
    INSERT INTO commission_summary (date_created, total_bets, total_commission)
    VALUES (NEW.date_created, NEW.bet_amount, NEW.commission_amount)
    ON DUPLICATE KEY UPDATE
    total_bets = total_bets + NEW.bet_amount,
    total_commission = total_commission + NEW.commission_amount;
END
```

And here's the fixed trigger:

```sql
CREATE TRIGGER update_commission_summary AFTER INSERT ON commission
FOR EACH ROW BEGIN
    INSERT INTO commission_summary (user_id, date_created, total_bets, total_commission)
    VALUES (NEW.user_id, NEW.date_created, NEW.bet_amount, NEW.commission_amount)
    ON DUPLICATE KEY UPDATE
    total_bets = total_bets + NEW.bet_amount,
    total_commission = total_commission + NEW.commission_amount;
END
```

The fix adds the `user_id` field to the INSERT statement, which ensures that the commission data is properly associated with the user who made the bet.

## Troubleshooting

If you encounter any issues:

1. Check the server error logs for more information
2. Verify that all files were uploaded correctly
3. Make sure you have the necessary permissions to modify database triggers
4. If the fix doesn't work, try running the verify_commission_data.php script to identify any remaining issues

## Contact

If you need further assistance, please contact the developer who provided these scripts.
