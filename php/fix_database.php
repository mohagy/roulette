<?php
// Database connection parameters
$host = "localhost";
$username = "root"; // Default XAMPP username
$password = ""; // Default XAMPP password is empty
$database = "roulette";

try {
    // Create connection to MySQL server
    $conn = new mysqli($host, $username, $password);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    echo "Connected to MySQL server successfully.<br>";

    // Create database if it doesn't exist
    $conn->query("CREATE DATABASE IF NOT EXISTS $database");
    echo "Database '$database' created or verified.<br>";

    // Select the database
    $conn->select_db($database);

    // Disable foreign key checks temporarily
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    echo "Foreign key checks disabled temporarily.<br>";

    // Drop tables in reverse order to avoid foreign key constraints
    echo "Dropping existing tables...<br>";

    // First check if tables exist before trying to drop them
    $tables = array("slip_details", "betting_slips", "bets", "game_history", "players");
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            $conn->query("DROP TABLE IF EXISTS $table");
            echo "Table $table dropped.<br>";
        }
    }

    echo "All tables dropped successfully.<br>";

    // Now recreate the tables
    echo "Recreating tables...<br>";

    // Create players table
    $conn->query("
        CREATE TABLE IF NOT EXISTS players (
            player_id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            cash_balance DECIMAL(10, 2) DEFAULT 1000.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    echo "Table 'players' created.<br>";

    // Create bets table
    $conn->query("
        CREATE TABLE IF NOT EXISTS bets (
            bet_id INT AUTO_INCREMENT PRIMARY KEY,
            player_id INT NOT NULL,
            bet_type VARCHAR(50) NOT NULL,
            bet_numbers VARCHAR(100) NOT NULL,
            bet_amount DECIMAL(10, 2) NOT NULL,
            placed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (player_id) REFERENCES players(player_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    echo "Table 'bets' created.<br>";

    // Create game_history table
    $conn->query("
        CREATE TABLE IF NOT EXISTS game_history (
            game_id INT AUTO_INCREMENT PRIMARY KEY,
            winning_number INT NOT NULL,
            winning_color VARCHAR(10) NOT NULL,
            played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    echo "Table 'game_history' created.<br>";

    // Create betting_slips table
    $conn->query("
        CREATE TABLE IF NOT EXISTS betting_slips (
            slip_id INT AUTO_INCREMENT PRIMARY KEY,
            slip_number VARCHAR(8) UNIQUE NOT NULL,
            player_id INT NOT NULL,
            total_stake DECIMAL(10, 2) NOT NULL,
            potential_payout DECIMAL(10, 2) NOT NULL,
            is_paid BOOLEAN DEFAULT FALSE,
            is_cancelled BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (player_id) REFERENCES players(player_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    echo "Table 'betting_slips' created.<br>";

    // Create slip_details table
    $conn->query("
        CREATE TABLE IF NOT EXISTS slip_details (
            detail_id INT AUTO_INCREMENT PRIMARY KEY,
            slip_id INT NOT NULL,
            bet_id INT NOT NULL,
            FOREIGN KEY (slip_id) REFERENCES betting_slips(slip_id) ON DELETE CASCADE,
            FOREIGN KEY (bet_id) REFERENCES bets(bet_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    echo "Table 'slip_details' created.<br>";

    // Re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    echo "Foreign key checks re-enabled.<br>";

    // Insert default player
    $conn->query("
        INSERT INTO players (username, cash_balance)
        VALUES ('default_player', 1000.00)
        ON DUPLICATE KEY UPDATE username = 'default_player'
    ");
    echo "Default player created.<br>";

    echo "Database setup completed successfully!<br>";
    echo "<strong>The foreign key constraint issue has been fixed.</strong><br>";

    $conn->close();

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";

    // Make sure to re-enable foreign key checks even if there's an error
    if (isset($conn)) {
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        echo "Foreign key checks re-enabled after error.<br>";
    }
}
?>