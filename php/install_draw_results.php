<?php
/**
 * Install Draw Results Table
 * This script checks if the draw_results table exists,
 * and if not, installs it using the SQL file
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<html><head><title>Install Draw Results</title>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
h1 { color: #2c3e50; }
.success { color: green; }
.error { color: red; }
.info { color: blue; }
pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow: auto; }
.button {
    display: inline-block;
    padding: 10px 15px;
    background: #3498db;
    color: white;
    text-decoration: none;
    border-radius: 4px;
    margin-top: 15px;
}
.button:hover { background: #2980b9; }
</style>";
echo "</head><body>";
echo "<h1>Draw Results Table Installation</h1>";

// Database connection parameters
$host = "localhost";
$username = "root"; // Default XAMPP username
$password = ""; // Default XAMPP password is empty
$database = "roulette";

try {
    // Connect to the database
    echo "<p>Connecting to database...</p>";
    $conn = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p class='success'>Connected successfully.</p>";

    // Check if table exists
    echo "<p>Checking if draw_results table exists...</p>";
    $stmt = $conn->query("SHOW TABLES LIKE 'draw_results'");
    $tableExists = $stmt->rowCount() > 0;

    if ($tableExists) {
        echo "<p class='info'>The draw_results table already exists. No need to install.</p>";
    } else {
        echo "<p>Table does not exist. Installing...</p>";

        // Load the SQL file
        $sqlFile = __DIR__ . '/add_draw_results_table.sql';

        if (!file_exists($sqlFile)) {
            throw new Exception("SQL file not found at: $sqlFile");
        }

        $sql = file_get_contents($sqlFile);

        if ($sql === false) {
            throw new Exception("Failed to read SQL file");
        }

        echo "<p>Loaded SQL file successfully.</p>";

        // Execute the SQL statements
        echo "<p>Executing SQL statements...</p>";

        // Split the SQL by DELIMITER
        $parts = explode('DELIMITER', $sql);

        // Execute the first part (table creation)
        $conn->exec($parts[0]);
        echo "<p class='success'>Created table structure successfully.</p>";

        // Handle stored procedures and triggers with different delimiters
        if (count($parts) > 1) {
            // Process each DELIMITER section
            for ($i = 1; $i < count($parts); $i++) {
                if (empty(trim($parts[$i]))) continue;

                // Extract the delimiter
                preg_match('/^[ \t\r\n]*([^\s]+)[ \t\r\n]+/', $parts[$i], $matches);
                $delimiter = $matches[1];

                // Extract the code without the delimiter declaration
                $code = preg_replace('/^[ \t\r\n]*([^\s]+)[ \t\r\n]+/', '', $parts[$i]);

                // Split by the custom delimiter
                $statements = explode($delimiter, $code);

                // Execute each statement
                foreach ($statements as $statement) {
                    $trimmed = trim($statement);
                    if (empty($trimmed)) continue;

                    try {
                        $conn->exec($trimmed);
                        echo "<p class='success'>Successfully executed statement.</p>";
                    } catch (PDOException $e) {
                        echo "<p class='error'>Error executing statement: " . $e->getMessage() . "</p>";
                        echo "<pre>" . htmlspecialchars(substr($trimmed, 0, 300)) . "...</pre>";
                    }
                }
            }
        }

        echo "<p class='success'>Draw results table and related objects installed successfully!</p>";
    }

    // Verify the installation
    $tableCheck = $conn->query("SHOW TABLES LIKE 'draw_results'")->rowCount() > 0;
    $procCheck = $conn->query("SHOW PROCEDURE STATUS WHERE Db = '$database' AND Name = 'save_draw_result'")->rowCount() > 0;
    $triggerCheck = $conn->query("SHOW TRIGGERS WHERE `Table` = 'draw_results'")->rowCount() > 0;
    $viewCheck = $conn->query("SHOW TABLES LIKE 'draw_results_with_stats'")->rowCount() > 0;

    echo "<h2>Installation Status</h2>";
    echo "<ul>";
    echo "<li>draw_results table: " . ($tableCheck ? "<span class='success'>Installed ✓</span>" : "<span class='error'>Missing ✗</span>") . "</li>";
    echo "<li>save_draw_result procedure: " . ($procCheck ? "<span class='success'>Installed ✓</span>" : "<span class='error'>Missing ✗</span>") . "</li>";
    echo "<li>before_draw_results_insert trigger: " . ($triggerCheck ? "<span class='success'>Installed ✓</span>" : "<span class='error'>Missing ✗</span>") . "</li>";
    echo "<li>draw_results_with_stats view: " . ($viewCheck ? "<span class='success'>Installed ✓</span>" : "<span class='error'>Missing ✗</span>") . "</li>";
    echo "</ul>";

    echo "<h2>JavaScript Integration</h2>";

    // Check if the save-detailed-draw.js file exists
    $jsFile = __DIR__ . '/../tvdisplay/js/save-detailed-draw.js';
    $jsExists = file_exists($jsFile);

    echo "<p>JavaScript file status: " . ($jsExists ? "<span class='success'>Found ✓</span>" : "<span class='error'>Missing ✗</span>") . "</p>";

    // Check if the script is included in the HTML
    $htmlFile = __DIR__ . '/../tvdisplay/index.html';
    $htmlExists = file_exists($htmlFile);

    if ($htmlExists) {
        $html = file_get_contents($htmlFile);
        $scriptIncluded = strpos($html, 'save-detailed-draw.js') !== false;

        echo "<p>Script included in HTML: " . ($scriptIncluded ? "<span class='success'>Yes ✓</span>" : "<span class='error'>No ✗</span>") . "</p>";

        if (!$scriptIncluded) {
            echo "<p class='info'>You need to add the following line to tvdisplay/index.html before the closing body tag:</p>";
            echo "<pre>&lt;script src=\"js/save-detailed-draw.js\"&gt;&lt;/script&gt;</pre>";
        }
    } else {
        echo "<p class='error'>Could not find the HTML file to check for script inclusion.</p>";
    }

    echo "<h2>Next Steps</h2>";
    echo "<p>To test if game history saving is working:</p>";
    echo "<ol>";
    echo "<li>Open the tvdisplay interface in your browser</li>";
    echo "<li>Spin the roulette wheel</li>";
    echo "<li>Check the browser console for messages about saving draw results</li>";
    echo "<li>Check the database to verify data is being saved</li>";
    echo "</ol>";

    echo "<p><a href='../tvdisplay' class='button'>Go to TV Display</a></p>";

} catch (Exception $e) {
    echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
}

echo "</body></html>";
?>