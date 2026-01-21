<?php
/**
 * Add Test Draw
 * 
 * This script adds a test draw to the detailed_draw_results table
 * to simulate a new draw being completed.
 */

// Include database connection
require_once 'php/db_connect.php';

// Set headers
header('Content-Type: text/html');

// Get the latest draw number
$latestDrawQuery = "SELECT draw_number FROM detailed_draw_results ORDER BY id DESC LIMIT 1";
$latestDrawResult = $conn->query($latestDrawQuery);

$nextDrawNumber = 1;
if ($latestDrawResult && $latestDrawResult->num_rows > 0) {
    $latestDrawRow = $latestDrawResult->fetch_assoc();
    $nextDrawNumber = (int)$latestDrawRow['draw_number'] + 1;
}

// Generate a random winning number
$winningNumber = rand(0, 36);

// Determine the color
$color = 'black';
if ($winningNumber == 0) {
    $color = 'green';
} else if (in_array($winningNumber, [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36])) {
    $color = 'red';
}

// Create a unique draw ID
$drawId = "DRAW-{$nextDrawNumber}-{$winningNumber}";

// Insert the new draw
$insertQuery = "INSERT INTO detailed_draw_results 
                (draw_id, draw_number, winning_number, winning_color, draw_time) 
                VALUES (?, ?, ?, ?, NOW())";

$stmt = $conn->prepare($insertQuery);
$stmt->bind_param("siis", $drawId, $nextDrawNumber, $winningNumber, $color);

$success = $stmt->execute();

// Output result
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Test Draw</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
        h1 {
            color: #333;
        }
        .success {
            color: green;
            font-weight: bold;
        }
        .error {
            color: red;
            font-weight: bold;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
            border-radius: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .actions {
            margin-top: 20px;
        }
        .actions a {
            display: inline-block;
            margin-right: 10px;
            padding: 10px 15px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .actions a:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Add Test Draw</h1>
        
        <?php if ($success): ?>
            <p class="success">Successfully added new draw!</p>
            <table>
                <tr>
                    <th>Draw ID</th>
                    <td><?php echo htmlspecialchars($drawId); ?></td>
                </tr>
                <tr>
                    <th>Draw Number</th>
                    <td><?php echo $nextDrawNumber; ?></td>
                </tr>
                <tr>
                    <th>Winning Number</th>
                    <td><?php echo $winningNumber; ?></td>
                </tr>
                <tr>
                    <th>Color</th>
                    <td style="color: <?php echo $color; ?>;"><?php echo ucfirst($color); ?></td>
                </tr>
                <tr>
                    <th>Time</th>
                    <td><?php echo date('Y-m-d H:i:s'); ?></td>
                </tr>
            </table>
        <?php else: ?>
            <p class="error">Error adding draw: <?php echo $stmt->error; ?></p>
        <?php endif; ?>
        
        <div class="actions">
            <a href="add_test_draw.php">Add Another Draw</a>
            <a href="index.php">Go to Main Page</a>
            <a href="sync_draw_timer.php" target="_blank">View Raw Timer Data</a>
        </div>
    </div>
</body>
</html>
