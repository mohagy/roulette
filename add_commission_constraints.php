<?php
// Database connection parameters
$servername = "localhost";
$username = "root";  // Default XAMPP username
$password = "";      // Default XAMPP password (empty)
$dbname = "roulette";  // Using the roulette database

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Starting commission constraints update...\n";

// Start transaction
$conn->begin_transaction();

try {
    // Check if the unique constraint already exists
    $result = $conn->query("SHOW INDEXES FROM commission_summary WHERE Key_name = 'user_id_date_unique'");
    
    if ($result->num_rows === 0) {
        echo "Adding unique constraint on user_id and date_created...\n";
        
        // Add unique constraint
        if ($conn->query("ALTER TABLE commission_summary ADD CONSTRAINT user_id_date_unique UNIQUE (user_id, date_created)")) {
            echo "Successfully added unique constraint.\n";
        } else {
            echo "Error adding unique constraint: " . $conn->error . "\n";
        }
    } else {
        echo "Unique constraint already exists.\n";
    }
    
    // Commit transaction
    $conn->commit();
    echo "\nCommission constraints update completed successfully.\n";
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo "Error: " . $e->getMessage() . "\n";
}

// Close connection
$conn->close();
?>
