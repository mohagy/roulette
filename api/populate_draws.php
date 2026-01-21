<?php
require_once '../includes/db_connection.php';

// Calculate current draw from time
date_default_timezone_set('America/La_Paz');
$now = new DateTime();
$currentHour = (int)$now->format('H');
$currentMinute = (int)$now->format('i');
$totalMinutes = ($currentHour * 60) + $currentMinute;
$currentDrawNumber = floor($totalMinutes / 3) + 1;

echo "Current time: " . $now->format('H:i:s') . "\n";
echo "Current draw: $currentDrawNumber\n\n";

// Find the last draw in detailed_draw_results
$result = $conn->query("SELECT MAX(draw_number) as max_draw FROM detailed_draw_results");
$lastDraw = 0;
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $lastDraw = (int)$row['max_draw'];
}

echo "Last draw in table: $lastDraw\n";
echo "Draws to insert: " . ($currentDrawNumber - $lastDraw) . "\n\n";

if ($lastDraw >= $currentDrawNumber) {
    echo "Table is already up to date!\n";
    exit;
}

// Insert missing draws
$inserted = 0;
for ($draw = $lastDraw + 1; $draw <= $currentDrawNumber; $draw++) {
    // Calculate draw time (starting from midnight)
    $drawMinutes = ($draw - 1) * 3;
    $drawHour = floor($drawMinutes / 60);
    $drawMin = $drawMinutes % 60;
    
    $drawTime = $now->format('Y-m-d') . ' ' . sprintf('%02d:%02d:00', $drawHour, $drawMin);
    
    // Determine color for winning number 0
    $color = 'green';
    
    // Use 0 as winning number for backfilled draws
    $sql = "INSERT INTO detailed_draw_results (draw_number, winning_number, color, timestamp) 
            VALUES (?, 0, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $draw, $color, $drawTime);
    
    if ($stmt->execute()) {
        $inserted++;
        if ($inserted % 50 == 0) {
            echo "Inserted $inserted draws...\n";
        }
    } else {
        echo "Error inserting draw $draw: " . $conn->error . "\n";
    }
    $stmt->close();
}

echo "\n✅ Successfully inserted $inserted draws\n";
echo "Table now has draws from 1 to $currentDrawNumber\n";

$conn->close();
?>
