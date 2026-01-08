<?php
// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Database Connection Test\n";
echo "=======================\n\n";

// Database connection parameters
$host = "localhost";
$username = "root"; // Default XAMPP username
$password = ""; // Default XAMPP password is empty
$database = "roulette";

// Test database connection
echo "Connecting to MySQL server...\n";
try {
    $conn = new mysqli($host, $username, $password);

    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    echo "Connection successful.\n\n";

    // Check if database exists
    echo "Checking if database '$database' exists...\n";
    $result = $conn->query("SHOW DATABASES LIKE '$database'");

    if ($result->num_rows > 0) {
        echo "Database '$database' exists.\n\n";

        // Select the database
        $conn->select_db($database);

        // Check required tables
        $requiredTables = ['players', 'bets', 'game_history', 'betting_slips', 'slip_details'];

        echo "Checking required tables...\n";
        foreach ($requiredTables as $table) {
            $tableResult = $conn->query("SHOW TABLES LIKE '$table'");
            if ($tableResult->num_rows > 0) {
                echo "  - Table '$table' exists.\n";

                // Display table structure
                $structureResult = $conn->query("DESCRIBE $table");
                echo "    Columns:\n";
                while ($column = $structureResult->fetch_assoc()) {
                    echo "      - {$column['Field']} ({$column['Type']})\n";
                }
                echo "\n";
            } else {
                echo "  - Table '$table' does not exist.\n";
            }
        }

        // Check if default player exists
        echo "Checking if default player exists...\n";
        $playerResult = $conn->query("SELECT * FROM players WHERE username = 'default_player'");
        if ($playerResult->num_rows > 0) {
            $player = $playerResult->fetch_assoc();
            echo "Default player exists with ID: {$player['player_id']} and balance: {$player['cash_balance']}\n\n";
        } else {
            echo "Default player does not exist.\n";
            echo "Creating default player...\n";
            $insertResult = $conn->query("INSERT INTO players (username, cash_balance) VALUES ('default_player', 1000.00)");
            if ($insertResult) {
                echo "Default player created successfully.\n\n";
            } else {
                echo "Failed to create default player: " . $conn->error . "\n\n";
            }
        }

    } else {
        echo "Database '$database' does not exist.\n";
        echo "Creating database and tables...\n";

        // Create database
        if ($conn->query("CREATE DATABASE IF NOT EXISTS $database")) {
            echo "Database created successfully.\n";

            // Select the database
            $conn->select_db($database);

            // Run the setup SQL
            $setupFile = file_get_contents('setup_database.sql');

            // Split into individual queries
            $queries = explode(';', $setupFile);

            $success = true;
            foreach ($queries as $query) {
                $query = trim($query);
                if (!empty($query)) {
                    if (!$conn->query($query)) {
                        echo "Error executing query: " . $conn->error . "\n";
                        $success = false;
                    }
                }
            }

            if ($success) {
                echo "Tables created successfully.\n\n";
            } else {
                echo "Error creating tables.\n\n";
            }
        } else {
            echo "Error creating database: " . $conn->error . "\n\n";
        }
    }

    // Test the slip_api.php
    echo "Testing slip_api.php...\n";

    $testData = [
        'action' => 'save_slip',
        'barcode' => '12345678',
        'bets' => json_encode([
            [
                'type' => 'straight',
                'description' => 'Straight Up on 5',
                'amount' => 10,
                'multiplier' => 35,
                'potentialReturn' => 350
            ]
        ]),
        'total_stakes' => 10,
        'potential_return' => 350,
        'date' => date('Y-m-d H:i:s')
    ];

    echo "Simulating a POST request to slip_api.php with test data...\n";

    // Create a temporary context
    $prev = $conn;
    $GLOBALS['conn'] = $conn;
    $_POST = $testData;
    $_SERVER['REQUEST_METHOD'] = 'POST';

    // Buffer output
    ob_start();
    include 'slip_api.php';
    $response = ob_get_clean();

    // Restore context
    $conn = $prev;

    echo "Response from slip_api.php:\n$response\n\n";

    // Decode the response
    $responseData = json_decode($response, true);
    if (isset($responseData['status']) && $responseData['status'] === 'success') {
        echo "Test was successful!\n";
    } else {
        echo "Test failed. Please check the response and error messages.\n";
    }

    $conn->close();

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>