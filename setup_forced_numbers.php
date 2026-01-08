<?php
/**
 * Setup Forced Numbers
 *
 * This script ensures the next_draw_winning_number table is properly set up and populated.
 */

// Include database connection
require_once 'php/db_connect.php';

// Set headers
header('Content-Type: text/html');

// Function to check if a table exists
function tableExists($pdo, $table) {
    try {
        $result = $pdo->query("SHOW TABLES LIKE '{$table}'");
        return $result->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Start HTML output
echo "<!DOCTYPE html>
<html>
<head>
    <title>Setup Forced Numbers</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
        h1, h2 {
            color: #333;
        }
        .success {
            color: green;
        }
        .error {
            color: red;
        }
        pre {
            background-color: #f5f5f5;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .card {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
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
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type='text'],
        input[type='number'] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <h1>Setup Forced Numbers</h1>";

try {
    // Check if the next_draw_winning_number table exists
    if (!tableExists($pdo, 'next_draw_winning_number')) {
        echo "<div class='card error'>
            <h2>Missing Table</h2>
            <p>The next_draw_winning_number table does not exist.</p>
            <p>Please run <a href='setup_draw_tables.php'>setup_draw_tables.php</a> to create it.</p>
        </div>";
    } else {
        echo "<div class='card success'>
            <h2>Table Exists</h2>
            <p>The next_draw_winning_number table exists.</p>
        </div>";

        // Check if we're processing a form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['action'])) {
                if ($_POST['action'] === 'add') {
                    // Add a new forced number
                    $drawNumber = isset($_POST['draw_number']) ? intval($_POST['draw_number']) : null;
                    $winningNumber = isset($_POST['winning_number']) ? intval($_POST['winning_number']) : null;
                    $source = isset($_POST['source']) ? $_POST['source'] : 'manual';
                    $reason = isset($_POST['reason']) ? $_POST['reason'] : 'Set by administrator';

                    if ($drawNumber !== null && $winningNumber !== null) {
                        // Check if the draw number already exists
                        $stmt = $pdo->prepare("SELECT id FROM next_draw_winning_number WHERE draw_number = ?");
                        $stmt->execute([$drawNumber]);
                        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($existing) {
                            // Update existing record
                            $stmt = $pdo->prepare("
                                UPDATE next_draw_winning_number
                                SET winning_number = ?,
                                    source = ?,
                                    reason = ?,
                                    updated_at = NOW()
                                WHERE draw_number = ?
                            ");
                            $stmt->execute([$winningNumber, $source, $reason, $drawNumber]);

                            echo "<div class='card success'>
                                <h2>Forced Number Updated</h2>
                                <p>Draw #{$drawNumber} will now land on {$winningNumber}.</p>
                            </div>";
                        } else {
                            // Insert new record
                            $stmt = $pdo->prepare("
                                INSERT INTO next_draw_winning_number
                                (draw_number, winning_number, source, reason)
                                VALUES (?, ?, ?, ?)
                            ");
                            $stmt->execute([$drawNumber, $winningNumber, $source, $reason]);

                            echo "<div class='card success'>
                                <h2>Forced Number Added</h2>
                                <p>Draw #{$drawNumber} will now land on {$winningNumber}.</p>
                            </div>";
                        }
                    } else {
                        echo "<div class='card error'>
                            <h2>Invalid Input</h2>
                            <p>Please provide a valid draw number and winning number.</p>
                        </div>";
                    }
                } elseif ($_POST['action'] === 'delete') {
                    // Delete a forced number
                    $drawNumber = isset($_POST['draw_number']) ? intval($_POST['draw_number']) : null;

                    if ($drawNumber !== null) {
                        $stmt = $pdo->prepare("DELETE FROM next_draw_winning_number WHERE draw_number = ?");
                        $stmt->execute([$drawNumber]);

                        echo "<div class='card success'>
                            <h2>Forced Number Deleted</h2>
                            <p>Draw #{$drawNumber} will now use a random number.</p>
                        </div>";
                    } else {
                        echo "<div class='card error'>
                            <h2>Invalid Input</h2>
                            <p>Please provide a valid draw number.</p>
                        </div>";
                    }
                } elseif ($_POST['action'] === 'clear') {
                    // Clear all forced numbers
                    $stmt = $pdo->prepare("TRUNCATE TABLE next_draw_winning_number");
                    $stmt->execute();

                    echo "<div class='card success'>
                        <h2>All Forced Numbers Cleared</h2>
                        <p>All draws will now use random numbers.</p>
                    </div>";
                }
            }
        }

        // Get the current draw number
        $stmt = $pdo->prepare("
            SELECT current_draw_number FROM roulette_analytics LIMIT 1
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $currentDrawNumber = $result ? intval($result['current_draw_number']) : 1;

        echo "<div class='card'>
            <h2>Current Draw Number</h2>
            <p>The current draw number is: <strong>{$currentDrawNumber}</strong></p>
        </div>";

        // Get all forced numbers
        $stmt = $pdo->prepare("
            SELECT * FROM next_draw_winning_number ORDER BY draw_number ASC
        ");
        $stmt->execute();
        $forcedNumbers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<div class='card'>
            <h2>Forced Numbers</h2>";

        if (count($forcedNumbers) > 0) {
            echo "<table>
                <tr>
                    <th>Draw Number</th>
                    <th>Winning Number</th>
                    <th>Source</th>
                    <th>Reason</th>
                    <th>Created At</th>
                    <th>Updated At</th>
                    <th>Actions</th>
                </tr>";

            foreach ($forcedNumbers as $forcedNumber) {
                $color = 'green';
                if ($forcedNumber['winning_number'] > 0) {
                    $redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
                    $color = in_array($forcedNumber['winning_number'], $redNumbers) ? 'red' : 'black';
                }

                echo "<tr>
                    <td>{$forcedNumber['draw_number']}</td>
                    <td style='color: {$color};'>{$forcedNumber['winning_number']}</td>
                    <td>{$forcedNumber['source']}</td>
                    <td>{$forcedNumber['reason']}</td>
                    <td>{$forcedNumber['created_at']}</td>
                    <td>{$forcedNumber['updated_at']}</td>
                    <td>
                        <form method='post'>
                            <input type='hidden' name='action' value='delete'>
                            <input type='hidden' name='draw_number' value='{$forcedNumber['draw_number']}'>
                            <button type='submit'>Delete</button>
                        </form>
                    </td>
                </tr>";
            }

            echo "</table>";
        } else {
            echo "<p>No forced numbers found.</p>";
        }

        echo "</div>";

        // Form to add a new forced number
        echo "<div class='card'>
            <h2>Add Forced Number</h2>
            <form method='post'>
                <input type='hidden' name='action' value='add'>
                <div class='form-group'>
                    <label for='draw_number'>Draw Number:</label>
                    <input type='number' id='draw_number' name='draw_number' value='{$currentDrawNumber}' required>
                </div>
                <div class='form-group'>
                    <label for='winning_number'>Winning Number:</label>
                    <input type='number' id='winning_number' name='winning_number' min='0' max='36' required>
                </div>
                <div class='form-group'>
                    <label for='source'>Source:</label>
                    <input type='text' id='source' name='source' value='manual'>
                </div>
                <div class='form-group'>
                    <label for='reason'>Reason:</label>
                    <input type='text' id='reason' name='reason' value='Set by administrator'>
                </div>
                <button type='submit'>Add Forced Number</button>
            </form>
        </div>";

        // Form to clear all forced numbers
        echo "<div class='card'>
            <h2>Clear All Forced Numbers</h2>
            <form method='post' onsubmit='return confirm(\"Are you sure you want to clear all forced numbers?\")'>
                <input type='hidden' name='action' value='clear'>
                <button type='submit'>Clear All</button>
            </form>
        </div>";
    }

    // Links to other pages
    echo "<div class='card'>
        <h2>Links</h2>
        <p>
            <a href='tvdisplay/index.html'><button>TV Display</button></a>
            <a href='admin/bet_distribution.php'><button>Admin Panel</button></a>
            <a href='setup_draw_tables.php'><button>Setup Tables</button></a>
            <a href='test_forced_number.php'><button>Test Forced Number</button></a>
        </p>
    </div>";

} catch (PDOException $e) {
    echo "<div class='card error'>
        <h2>Database Error</h2>
        <p>{$e->getMessage()}</p>
    </div>";
}

// End HTML output
echo "</body></html>";
