<?php
// Set error reporting to show all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to print results both to HTML and to a log file for debugging
function writeOutput($message) {
    echo $message . "\n";

    // Also write to a log file for debugging
    $logFile = 'logs/draw_info.log';
    if (!file_exists('logs')) {
        mkdir('logs', 0777, true);
    }
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

writeOutput("<h1>Draw Information Check</h1>");

// Database configuration
$host = 'localhost';
$database = 'roulette';
$user = 'root';
$password = '';

try {
    // Create connection
    $conn = new mysqli($host, $user, $password, $database);

    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    writeOutput("<p>Connected to database successfully</p>");

    // Check betting_slips table structure
    writeOutput("<h2>BETTING_SLIPS Table Structure:</h2>");
    $result = $conn->query("DESCRIBE betting_slips");
    if ($result) {
        writeOutput("<table border='1' cellpadding='5'>");
        writeOutput("<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>");

        while ($row = $result->fetch_assoc()) {
            writeOutput("<tr>");
            writeOutput("<td>{$row['Field']}</td>");
            writeOutput("<td>{$row['Type']}</td>");
            writeOutput("<td>{$row['Null']}</td>");
            writeOutput("<td>{$row['Key']}</td>");
            writeOutput("<td>{$row['Default']}</td>");
            writeOutput("<td>{$row['Extra']}</td>");
            writeOutput("</tr>");
        }

        writeOutput("</table>");
    } else {
        writeOutput("<p style='color:red'>Error getting betting_slips table structure: " . $conn->error . "</p>");
    }

    // Check detailed_draw_results table structure
    writeOutput("<h2>DETAILED_DRAW_RESULTS Table Structure:</h2>");
    $result = $conn->query("DESCRIBE detailed_draw_results");
    if ($result) {
        writeOutput("<table border='1' cellpadding='5'>");
        writeOutput("<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>");

        while ($row = $result->fetch_assoc()) {
            writeOutput("<tr>");
            writeOutput("<td>{$row['Field']}</td>");
            writeOutput("<td>{$row['Type']}</td>");
            writeOutput("<td>{$row['Null']}</td>");
            writeOutput("<td>{$row['Key']}</td>");
            writeOutput("<td>{$row['Default']}</td>");
            writeOutput("<td>{$row['Extra']}</td>");
            writeOutput("</tr>");
        }

        writeOutput("</table>");
    } else {
        writeOutput("<p style='color:red'>Error getting detailed_draw_results table structure: " . $conn->error . "</p>");
    }

    // Get sample betting slips with draw information
    writeOutput("<h2>Sample Betting Slips with Draw Information:</h2>");
    $query = "
        SELECT bs.slip_id, bs.slip_number, bs.draw_number, bs.total_stake, bs.potential_payout,
               bs.created_at, bs.is_paid, bs.is_cancelled, bs.status,
               bs.winning_number, bs.paid_out_amount, bs.transaction_id,
               ddr.winning_number AS actual_winning_number, ddr.color as winning_color,
               ddr.timestamp as draw_time, ddr.created_at as draw_date
        FROM betting_slips bs
        LEFT JOIN detailed_draw_results ddr ON bs.draw_number = ddr.draw_number
        ORDER BY bs.created_at DESC
        LIMIT 10
    ";

    $result = $conn->query($query);

    if ($result) {
        writeOutput("<table border='1' cellpadding='5'>");
        writeOutput("<tr>
            <th>Slip ID</th>
            <th>Slip Number</th>
            <th>Draw Number</th>
            <th>Draw Time</th>
            <th>Draw Date</th>
            <th>Winning Number</th>
            <th>Status</th>
        </tr>");

        while ($row = $result->fetch_assoc()) {
            writeOutput("<tr>");
            writeOutput("<td>{$row['slip_id']}</td>");
            writeOutput("<td>{$row['slip_number']}</td>");
            writeOutput("<td>{$row['draw_number']}</td>");
            writeOutput("<td>{$row['draw_time']}</td>");
            writeOutput("<td>{$row['draw_date']}</td>");
            writeOutput("<td>{$row['actual_winning_number']}</td>");
            writeOutput("<td>{$row['status']}</td>");
            writeOutput("</tr>");
        }

        writeOutput("</table>");
    } else {
        writeOutput("<p style='color:red'>Error getting sample betting slips: " . $conn->error . "</p>");
    }

    // Get current draw information
    writeOutput("<h2>Current Draw Information:</h2>");
    $query = "
        SELECT * FROM roulette_state
        LIMIT 1
    ";

    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        writeOutput("<table border='1' cellpadding='5'>");
        writeOutput("<tr><th>Field</th><th>Value</th></tr>");

        foreach ($row as $field => $value) {
            writeOutput("<tr>");
            writeOutput("<td>{$field}</td>");
            writeOutput("<td>{$value}</td>");
            writeOutput("</tr>");
        }

        writeOutput("</table>");
    } else {
        writeOutput("<p style='color:red'>Error getting current draw information: " . $conn->error . "</p>");
    }

    // Close connection
    $conn->close();

} catch (Exception $e) {
    writeOutput("<p style='color:red'>Error: " . $e->getMessage() . "</p>");
}

writeOutput("<p>Check complete!</p>");
writeOutput("<p><a href='https://roulette.aruka.app/slipp/my_transactions_new.php'>Return to My Transactions</a></p>");
?>
