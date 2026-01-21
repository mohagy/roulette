<?php
/**
 * Diagnostic script to check analytics data in database
 * Verifies that hot/cold numbers match recent history
 */

header('Content-Type: text/html; charset=utf-8');

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "roulette";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h1>Analytics Database Diagnostic</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #4CAF50; color: white; }
    tr:nth-child(even) { background-color: #f2f2f2; }
    .section { margin: 30px 0; padding: 20px; border: 2px solid #4CAF50; }
    .hot { color: red; font-weight: bold; }
    .cold { color: blue; font-weight: bold; }
</style>";

// 1. Check last 20 spins from database
echo "<div class='section'>";
echo "<h2>1. Last 20 Spins from detailed_draw_results</h2>";
$stmt = $conn->prepare("
    SELECT
        draw_number,
        winning_number,
        COALESCE(winning_color, color) as color,
        COALESCE(draw_time, timestamp, created_at) as timestamp
    FROM detailed_draw_results
    ORDER BY draw_number DESC
    LIMIT 20
");

$stmt->execute();
$result = $stmt->get_result();

$recentSpins = [];
$frequencyCount = [];

echo "<table>";
echo "<tr><th>Draw #</th><th>Number</th><th>Color</th><th>Timestamp</th></tr>";

while ($row = $result->fetch_assoc()) {
    $drawNum = (int)$row["draw_number"];
    $number = (int)$row["winning_number"];
    $color = $row["color"];
    $timestamp = $row["timestamp"];
    
    $recentSpins[] = [
        "draw_number" => $drawNum,
        "winning_number" => $number,
        "color" => $color,
        "timestamp" => $timestamp
    ];
    
    // Count frequency
    if (!isset($frequencyCount[$number])) {
        $frequencyCount[$number] = 0;
    }
    $frequencyCount[$number]++;
    
    echo "<tr>";
    echo "<td>#{$drawNum}</td>";
    echo "<td><strong>{$number}</strong></td>";
    echo "<td>{$color}</td>";
    echo "<td>{$timestamp}</td>";
    echo "</tr>";
}

echo "</table>";
echo "<p><strong>Total recent spins:</strong> " . count($recentSpins) . "</p>";
echo "</div>";

// 2. Calculate hot numbers from last 20 spins
echo "<div class='section'>";
echo "<h2>2. Hot Numbers (from last 20 spins)</h2>";
arsort($frequencyCount);
$hotNumbers = array_slice($frequencyCount, 0, 5, true);

echo "<table>";
echo "<tr><th>Number</th><th>Frequency (in last 20 spins)</th><th>Color</th></tr>";

foreach ($hotNumbers as $number => $count) {
    $color = ($number == 0) ? 'green' : 
             (in_array($number, [1,3,5,7,9,12,14,16,18,19,21,23,25,27,30,32,34,36]) ? 'red' : 'black');
    echo "<tr class='hot'>";
    echo "<td><strong>{$number}</strong></td>";
    echo "<td><strong>{$count}</strong></td>";
    echo "<td>{$color}</td>";
    echo "</tr>";
}

echo "</table>";
echo "</div>";

// 3. Calculate cold numbers from last 20 spins
echo "<div class='section'>";
echo "<h2>3. Cold Numbers (from last 20 spins)</h2>";
asort($frequencyCount);
$coldNumbers = array_slice($frequencyCount, 0, 5, true);

echo "<table>";
echo "<tr><th>Number</th><th>Frequency (in last 20 spins)</th><th>Color</th></tr>";

foreach ($coldNumbers as $number => $count) {
    if ($count > 0) { // Only show numbers that appeared at least once
        $color = ($number == 0) ? 'green' : 
                 (in_array($number, [1,3,5,7,9,12,14,16,18,19,21,23,25,27,30,32,34,36]) ? 'red' : 'black');
        echo "<tr class='cold'>";
        echo "<td><strong>{$number}</strong></td>";
        echo "<td><strong>{$count}</strong></td>";
        echo "<td>{$color}</td>";
        echo "</tr>";
    }
}

echo "</table>";
echo "</div>";

// 4. Check what API returns
echo "<div class='section'>";
echo "<h2>4. API Response Check</h2>";
echo "<p>Testing api_complete_analytics.php...</p>";

$apiUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
          "://" . $_SERVER['HTTP_HOST'] . 
          dirname($_SERVER['PHP_SELF']) . 
          "/api_complete_analytics.php";

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$apiResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200 && $apiResponse) {
    $apiData = json_decode($apiResponse, true);
    
    if ($apiData && isset($apiData['analytics'])) {
        echo "<h3>Hot Numbers from API:</h3>";
        echo "<table>";
        echo "<tr><th>Number</th><th>Frequency</th><th>Color</th></tr>";
        if (isset($apiData['analytics']['hot_numbers'])) {
            foreach ($apiData['analytics']['hot_numbers'] as $hot) {
                echo "<tr class='hot'>";
                echo "<td><strong>{$hot['number']}</strong></td>";
                echo "<td><strong>{$hot['frequency']}</strong></td>";
                echo "<td>{$hot['color']}</td>";
                echo "</tr>";
            }
        }
        echo "</table>";
        
        echo "<h3>Cold Numbers from API:</h3>";
        echo "<table>";
        echo "<tr><th>Number</th><th>Frequency</th><th>Color</th></tr>";
        if (isset($apiData['analytics']['cold_numbers'])) {
            foreach ($apiData['analytics']['cold_numbers'] as $cold) {
                echo "<tr class='cold'>";
                echo "<td><strong>{$cold['number']}</strong></td>";
                echo "<td><strong>{$cold['frequency']}</strong></td>";
                echo "<td>{$cold['color']}</td>";
                echo "</tr>";
            }
        }
        echo "</table>";
        
        echo "<h3>Last 8 Spins from API:</h3>";
        echo "<table>";
        echo "<tr><th>Draw #</th><th>Number</th><th>Color</th></tr>";
        if (isset($apiData['analytics']['last_8_spins'])) {
            foreach ($apiData['analytics']['last_8_spins'] as $spin) {
                echo "<tr>";
                echo "<td>#{$spin['draw_number']}</td>";
                echo "<td><strong>{$spin['winning_number']}</strong></td>";
                echo "<td>{$spin['color']}</td>";
                echo "</tr>";
            }
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>API returned invalid data structure</p>";
        echo "<pre>" . htmlspecialchars($apiResponse) . "</pre>";
    }
} else {
    echo "<p style='color: red;'>Failed to fetch API data. HTTP Code: {$httpCode}</p>";
    echo "<p>API URL: {$apiUrl}</p>";
}

echo "</div>";

// 5. Database table info
echo "<div class='section'>";
echo "<h2>5. Database Table Information</h2>";

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM detailed_draw_results");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
echo "<p><strong>Total draws in detailed_draw_results:</strong> " . $row['total'] . "</p>";

$stmt = $conn->prepare("SELECT MAX(draw_number) as max_draw FROM detailed_draw_results");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
echo "<p><strong>Highest draw number:</strong> " . ($row['max_draw'] ?? 'N/A') . "</p>";

$stmt = $conn->prepare("SELECT MIN(draw_number) as min_draw FROM detailed_draw_results");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
echo "<p><strong>Lowest draw number:</strong> " . ($row['min_draw'] ?? 'N/A') . "</p>";

echo "</div>";

$conn->close();
?>

