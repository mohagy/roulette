<?php
/**
 * Roulette Analytics Migration Master Script
 *
 * This script runs all the migration steps in sequence to convert the
 * single-row analytics table to a multi-row structure.
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'php/db_connect.php';

// Function to log messages
function logMessage($message) {
    echo date('[Y-m-d H:i:s] ') . $message . PHP_EOL;
}

// Start migration
logMessage("Starting Roulette Analytics Migration...");
logMessage("This script will convert the single-row analytics table to a multi-row structure.");
logMessage("---------------------------------------------------------------------");

// Step 1: Create new tables
logMessage("Step 1: Creating new database tables...");
include_once 'migrate_part1_create_tables.php';
logMessage("Step 1 completed.");
logMessage("---------------------------------------------------------------------");

// Step 2: Transfer data from old table to new tables
logMessage("Step 2: Transferring data from old table to new tables...");
include_once 'migrate_part2_transfer_data.php';
logMessage("Step 2 completed.");
logMessage("---------------------------------------------------------------------");

// Migration completed
logMessage("Migration completed successfully!");
logMessage("You can now access the new analytics dashboard at: admin/analytics_dashboard.php");
logMessage("You can set the next draw winning number at: admin/update_next_draw.php");
logMessage("---------------------------------------------------------------------");
logMessage("Note: The old roulette_analytics table has not been deleted. You can delete it manually if you no longer need it.");

// Close the database connection
$conn->close();
?>
