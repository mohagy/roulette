<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include database configuration
require_once 'db_config.php';

// Output HTML header for better readability in browser
echo "<!DOCTYPE html>
<html>
<head>
    <title>Roulette Betting Tables Setup</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        h1 { color: #333; }
        .success { color: green; }
        .error { color: red; }
        code { background: #f5f5f5; padding: 2px 5px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>Roulette Betting Tables Setup</h1>";

try {
    // Read SQL file
    $sql = file_get_contents('create_betting_tables.sql');
    
    if (!$sql) {
        throw new Exception("Could not read the SQL file. Make sure create_betting_tables.sql exists.");
    }
    
    echo "<p>Read SQL file successfully.</p>";
    
    // Split SQL statements
    $statements = array_filter(array_map('trim', explode(';', $sql)), 'strlen');
    
    // Execute each statement
    foreach ($statements as $statement) {
        try {
            $pdo->exec($statement);
            echo "<p class='success'>✓ SQL executed successfully: " . htmlspecialchars(substr($statement, 0, 70)) . "...</p>";
        } catch (PDOException $e) {
            echo "<p class='error'>✗ Error executing SQL: " . $e->getMessage() . "</p>";
            echo "<pre>" . htmlspecialchars($statement) . "</pre>";
        }
    }
    
    // Create the db_connect.php file if it doesn't exist
    $db_connect_path = 'php/db_connect.php';
    if (!file_exists($db_connect_path)) {
        $db_connect_content = '<?php
// Database connection using MySQLi
$db_host = "localhost";
$db_name = "roulette";
$db_user = "root";
$db_pass = "";

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set character set
$conn->set_charset("utf8mb4");
?>';

        // Create directory if it doesn't exist
        if (!is_dir(dirname($db_connect_path))) {
            mkdir(dirname($db_connect_path), 0755, true);
        }
        
        // Save the file
        if (file_put_contents($db_connect_path, $db_connect_content)) {
            echo "<p class='success'>✓ Created db_connect.php file</p>";
        } else {
            echo "<p class='error'>✗ Failed to create db_connect.php file</p>";
        }
    } else {
        echo "<p class='success'>✓ db_connect.php file already exists</p>";
    }
    
    // Check if the slip_api.php file exists
    $slip_api_path = 'php/slip_api.php';
    if (!file_exists($slip_api_path)) {
        // If it doesn't exist, we need to create it (only part of it)
        echo "<p class='error'>✗ slip_api.php file does not exist. You need to create it.</p>";
        echo "<p>Please see <a href='create_slip_api.php'>create_slip_api.php</a> for a sample implementation.</p>";
    } else {
        echo "<p class='success'>✓ slip_api.php file exists</p>";
    }
    
    echo "<h2 class='success'>Database setup completed successfully!</h2>";
    echo "<p>Your roulette betting system database is now ready to use.</p>";
    
    // Show links to test/verify
    echo "<h3>Next Steps:</h3>";
    echo "<ul>
        <li><a href='test_db.php' target='_blank'>Test Database Connection</a></li>
        <li><a href='index.html' target='_blank'>Launch Roulette Game</a></li>
    </ul>";

} catch (Exception $e) {
    echo "<h2 class='error'>Setup Error</h2>";
    echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
}

// Close HTML
echo "</body></html>";
?> 