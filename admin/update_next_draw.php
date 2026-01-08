<?php
/**
 * Update Next Draw Winning Number
 *
 * This script allows administrators to set the winning number for the next draw
 * using the new multi-row database structure.
 */

// Include the analytics handler
require_once '../php/roulette_analytics.php';
require_once '../php/db_connect.php';

// Default response
$response = [
    'success' => false,
    'message' => 'No action taken'
];

// Check if this is an AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the game state
    $gameState = getGameState();
    $nextDrawNumber = $gameState['next_draw_number'];

    // Check if we're setting the next winning number
    if (isset($_POST['action']) && $_POST['action'] === 'set_next_winning_number') {
        $winningNumber = isset($_POST['winning_number']) ? (int)$_POST['winning_number'] : -1;

        if ($winningNumber >= 0 && $winningNumber <= 36) {
            try {
                // Determine the color
                $color = 'green'; // Default for 0
                if ($winningNumber > 0) {
                    if (in_array($winningNumber, [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36])) {
                        $color = 'red';
                    } else {
                        $color = 'black';
                    }
                }

                // Create a new table for next draw winning number if it doesn't exist
                $createTableQuery = "CREATE TABLE IF NOT EXISTS `next_draw_winning_number` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `draw_number` int(11) NOT NULL,
                    `winning_number` int(11) NOT NULL,
                    `winning_color` varchar(10) NOT NULL,
                    `is_manual` tinyint(1) NOT NULL DEFAULT 1,
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_draw_number` (`draw_number`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

                $conn->query($createTableQuery);

                // Insert or update the next winning number
                $query = "INSERT INTO next_draw_winning_number
                         (draw_number, winning_number, winning_color, is_manual)
                         VALUES (?, ?, ?, 1)
                         ON DUPLICATE KEY UPDATE
                         winning_number = VALUES(winning_number),
                         winning_color = VALUES(winning_color),
                         is_manual = 1,
                         created_at = CURRENT_TIMESTAMP";

                $stmt = $conn->prepare($query);
                $stmt->bind_param("iis", $nextDrawNumber, $winningNumber, $color);

                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = "Next draw (#$nextDrawNumber) winning number set to $winningNumber ($color)";
                } else {
                    $response['message'] = "Error setting next winning number: " . $stmt->error;
                }
            } catch (Exception $e) {
                $response['message'] = "Error: " . $e->getMessage();
            }
        } else {
            $response['message'] = "Invalid winning number. Must be between 0 and 36.";
        }
    }
}

// If this is an AJAX request, return JSON
if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Get current game state for the form
$gameState = getGameState();
$nextDrawNumber = $gameState['next_draw_number'];

// Check if there's already a next winning number set
$nextWinningNumber = null;
$nextWinningColor = null;

try {
    $query = "SELECT * FROM next_draw_winning_number WHERE draw_number = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $nextDrawNumber);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $nextWinningNumber = $row['winning_number'];
        $nextWinningColor = $row['winning_color'];
    }
} catch (Exception $e) {
    // Table might not exist yet, that's okay
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Next Draw - Roulette Admin</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            padding: 20px;
            background-color: #f8f9fa;
        }
        .card {
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .number-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 10px;
            margin-top: 15px;
        }
        .number-cell {
            padding: 10px;
            text-align: center;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .number-cell:hover {
            transform: scale(1.05);
        }
        .number-green {
            background-color: #28a745;
            color: white;
        }
        .number-red {
            background-color: #dc3545;
            color: white;
        }
        .number-black {
            background-color: #343a40;
            color: white;
        }
        .number-selected {
            box-shadow: 0 0 0 3px #007bff;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">Update Next Draw Winning Number</h1>

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Set Winning Number for Draw #<?php echo $nextDrawNumber; ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($response['message'])): ?>
                            <div class="alert alert-<?php echo $response['success'] ? 'success' : 'danger'; ?>">
                                <?php echo $response['message']; ?>
                            </div>
                        <?php endif; ?>

                        <form id="winningNumberForm" method="post" action="">
                            <input type="hidden" name="action" value="set_next_winning_number">
                            <input type="hidden" id="winning_number" name="winning_number" value="<?php echo $nextWinningNumber !== null ? $nextWinningNumber : ''; ?>">

                            <div class="form-group">
                                <label>Click to select a winning number:</label>
                                <div class="number-grid">
                                    <?php for ($i = 0; $i <= 36; $i++): ?>
                                        <?php
                                            $colorClass = 'number-black';
                                            if ($i == 0) {
                                                $colorClass = 'number-green';
                                            } elseif (in_array($i, [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36])) {
                                                $colorClass = 'number-red';
                                            }
                                            $isSelected = $nextWinningNumber !== null && $nextWinningNumber == $i;
                                        ?>
                                        <div class="number-cell <?php echo $colorClass; ?> <?php echo $isSelected ? 'number-selected' : ''; ?>" data-number="<?php echo $i; ?>">
                                            <?php echo $i; ?>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-success">Set Winning Number</button>
                                <a href="analytics_dashboard.php" class="btn btn-secondary ml-2">Back to Dashboard</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            // Handle number selection
            $('.number-cell').click(function() {
                // Remove selected class from all cells
                $('.number-cell').removeClass('number-selected');

                // Add selected class to clicked cell
                $(this).addClass('number-selected');

                // Update hidden input
                $('#winning_number').val($(this).data('number'));
            });

            // Submit form via AJAX
            $('#winningNumberForm').submit(function(e) {
                e.preventDefault();

                $.ajax({
                    type: 'POST',
                    url: 'update_next_draw.php',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        // Show alert with response message
                        const alertClass = response.success ? 'alert-success' : 'alert-danger';
                        const alertHtml = `<div class="alert ${alertClass}">${response.message}</div>`;

                        // Remove any existing alerts
                        $('.alert').remove();

                        // Add new alert
                        $('#winningNumberForm').prepend(alertHtml);
                    },
                    error: function() {
                        // Show error alert
                        const alertHtml = `<div class="alert alert-danger">An error occurred while processing your request.</div>`;

                        // Remove any existing alerts
                        $('.alert').remove();

                        // Add new alert
                        $('#winningNumberForm').prepend(alertHtml);
                    }
                });
            });
        });
    </script>
</body>
</html>
