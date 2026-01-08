<?php
// Include database connection
require_once 'php/db_connect.php';

// First try to get state from roulette_state table (primary source)
$stateQuery = "SELECT last_draw, next_draw FROM roulette_state ORDER BY id LIMIT 1";
$stateResult = $conn->query($stateQuery);

$currentDrawNumber = 14; // Default to draw 14
$nextDrawNumber = 15; // Default to draw 15

if ($stateResult && $stateResult->num_rows > 0) {
    $stateRow = $stateResult->fetch_assoc();

    // Extract the draw numbers from the last_draw and next_draw fields
    $currentDrawNumber = (int)str_replace('#', '', $stateRow['last_draw']);
    $nextDrawNumber = (int)str_replace('#', '', $stateRow['next_draw']);

    error_log("Found draw numbers in roulette_state table: Current=$currentDrawNumber, Next=$nextDrawNumber");
} else {
    // If not found in roulette_state, try roulette_analytics
    $query = "SELECT current_draw_number FROM roulette_analytics WHERE id = 1";
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $currentDrawNumber = $row['current_draw_number'];
        $nextDrawNumber = $currentDrawNumber + 1;
    }
}

// Log the current draw number for debugging
error_log("Current draw number: $currentDrawNumber, Next draw number: $nextDrawNumber");

// Generate the next 10 draw numbers
$drawNumbers = [];
for ($i = 0; $i < 10; $i++) {
    $drawNumbers[] = $currentDrawNumber + $i;
}

// Output JSON if this is an AJAX request
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    header('Content-Type: application/json');
    echo json_encode([
        'currentDrawNumber' => $currentDrawNumber,
        'drawNumbers' => $drawNumbers
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Draw Numbers Header</title>
    <style>
        .draw-header-container {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(to right, #1a2a3a, #2c3e50);
            color: white;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            padding: 10px;
            z-index: 9999;
            user-select: none;
            width: auto;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
        }

        .draw-header-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            padding-bottom: 5px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            cursor: move;
        }

        .draw-header-title h3 {
            margin: 0;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .draw-header-controls {
            display: flex;
            gap: 8px;
        }

        .draw-header-control {
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.2s;
            font-size: 14px;
        }

        .draw-header-control:hover {
            opacity: 1;
        }

        .draw-numbers-row {
            display: flex;
            gap: 5px;
            overflow-x: auto;
            padding-bottom: 5px;
            max-width: 600px;
        }

        .draw-number {
            padding: 8px 12px;
            min-width: 40px;
            text-align: center;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: bold;
            position: relative;
        }

        .draw-number:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .draw-number.current {
            background: #e74c3c;
            color: white;
        }

        .draw-number.selected {
            background: #2ecc71;
            color: white;
        }

        .draw-number.past {
            background: rgba(0, 0, 0, 0.3);
            color: rgba(255, 255, 255, 0.5);
        }

        .draw-number .label {
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 10px;
            background: rgba(0, 0, 0, 0.7);
            padding: 2px 5px;
            border-radius: 3px;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .draw-number:hover .label {
            opacity: 1;
        }

        .draw-header-info {
            font-size: 12px;
            margin-top: 8px;
            text-align: center;
            color: rgba(255, 255, 255, 0.7);
        }

        .draw-header-container.minimized .draw-numbers-row,
        .draw-header-container.minimized .draw-header-info {
            display: none;
        }

        /* Dialog styling */
        .draw-select-dialog {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            z-index: 10000;
            width: 300px;
        }

        .draw-select-dialog h3 {
            margin-top: 0;
            color: #2c3e50;
        }

        .draw-select-dialog p {
            margin-bottom: 15px;
            color: #555;
        }

        .draw-select-dialog .buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .draw-select-dialog button {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }

        .draw-select-dialog .confirm-btn {
            background: #2ecc71;
            color: white;
        }

        .draw-select-dialog .cancel-btn {
            background: #e74c3c;
            color: white;
        }

        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
        }

        @media (max-width: 768px) {
            .draw-header-container {
                width: 90%;
                max-width: none;
            }

            .draw-numbers-row {
                max-width: none;
            }
        }
    </style>
</head>
<body>
    <!-- Draw Header Container -->
    <div class="draw-header-container" id="drawHeaderContainer">
        <div class="draw-header-title" id="drawHeaderTitle">
            <h3>Draw Numbers</h3>
            <div class="draw-header-controls">
                <div class="draw-header-control" id="minimizeHeader">−</div>
                <div class="draw-header-control" id="closeHeader">×</div>
            </div>
        </div>
        <div class="draw-numbers-row" id="drawNumbersRow">
            <?php foreach ($drawNumbers as $index => $drawNumber): ?>
                <div class="draw-number <?php echo $index === 0 ? 'current' : ''; ?>"
                     data-draw="<?php echo $drawNumber; ?>">
                    #<?php echo $drawNumber; ?>
                    <div class="label">
                        <?php echo $index === 0 ? 'Current' : ($index === 1 ? 'Next' : 'Future'); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="draw-header-info">
            Click on a draw number to place bets for that specific draw
        </div>
    </div>

    <!-- Confirmation Dialog -->
    <div class="overlay" id="overlay"></div>
    <div class="draw-select-dialog" id="drawSelectDialog">
        <h3>Confirm Draw Selection</h3>
        <p>You are about to place bets for draw <span id="selectedDrawNumber"></span>.</p>
        <p>Are you sure you want to continue?</p>
        <div class="buttons">
            <button class="confirm-btn" id="confirmDrawSelection">Confirm</button>
            <button class="cancel-btn" id="cancelDrawSelection">Cancel</button>
        </div>
    </div>

    <script>
        // This script will be included in the main page
    </script>
</body>
</html>