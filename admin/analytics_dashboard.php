<?php
/**
 * Roulette Analytics Dashboard
 * 
 * This page provides an interface to view and manage roulette analytics data
 * using the new multi-row database structure.
 */

// Include the analytics handler
require_once '../php/roulette_analytics.php';

// Check if a new draw result is being submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_draw') {
    $drawNumber = isset($_POST['draw_number']) ? (int)$_POST['draw_number'] : 0;
    $winningNumber = isset($_POST['winning_number']) ? (int)$_POST['winning_number'] : 0;
    
    if ($drawNumber > 0 && $winningNumber >= 0 && $winningNumber <= 36) {
        $success = recordDrawResult($drawNumber, $winningNumber);
        $message = $success ? "Draw #$drawNumber recorded successfully!" : "Error recording draw result.";
    } else {
        $message = "Invalid draw or winning number.";
    }
}

// Get current game state
$gameState = getGameState();

// Get recent draws
$recentDraws = getRecentDraws(20);

// Get number frequency
$numberFrequency = getNumberFrequency();

// Get color frequency
$colorFrequency = getColorFrequency();

// Get upcoming draws
$upcomingDraws = getUpcomingDraws(10);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Roulette Analytics Dashboard</title>
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
        .draw-history-table th, .draw-history-table td {
            text-align: center;
        }
        .winning-number {
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 50%;
            display: inline-block;
            min-width: 28px;
        }
        .color-red {
            background-color: #dc3545;
            color: white;
        }
        .color-black {
            background-color: #343a40;
            color: white;
        }
        .color-green {
            background-color: #28a745;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">Roulette Analytics Dashboard</h1>
        
        <?php if (isset($message)): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Game State</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Current Draw Number:</strong> <?php echo $gameState['current_draw_number']; ?></p>
                        <p><strong>Next Draw Number:</strong> <?php echo $gameState['next_draw_number']; ?></p>
                        <p><strong>Next Draw Time:</strong> <?php echo $gameState['next_draw_time'] ? date('Y-m-d H:i:s', strtotime($gameState['next_draw_time'])) : 'Not scheduled'; ?></p>
                        <p><strong>Draw Interval:</strong> <?php echo $gameState['draw_interval_seconds']; ?> seconds</p>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Record New Draw Result</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <input type="hidden" name="action" value="record_draw">
                            
                            <div class="form-group">
                                <label for="draw_number">Draw Number:</label>
                                <input type="number" class="form-control" id="draw_number" name="draw_number" value="<?php echo $gameState['current_draw_number']; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="winning_number">Winning Number (0-36):</label>
                                <input type="number" class="form-control" id="winning_number" name="winning_number" min="0" max="36" required>
                            </div>
                            
                            <button type="submit" class="btn btn-success">Record Result</button>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Color Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card bg-danger text-white">
                                    <div class="card-body text-center">
                                        <h5>Red</h5>
                                        <h3><?php echo $colorFrequency['red']['frequency'] ?? 0; ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-dark text-white">
                                    <div class="card-body text-center">
                                        <h5>Black</h5>
                                        <h3><?php echo $colorFrequency['black']['frequency'] ?? 0; ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-success text-white">
                                    <div class="card-body text-center">
                                        <h5>Green</h5>
                                        <h3><?php echo $colorFrequency['green']['frequency'] ?? 0; ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0">Number Frequency</h5>
                    </div>
                    <div class="card-body">
                        <div class="number-grid">
                            <?php for ($i = 0; $i <= 36; $i++): ?>
                                <?php 
                                    $colorClass = 'number-black';
                                    if ($i == 0) {
                                        $colorClass = 'number-green';
                                    } elseif (in_array($i, [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36])) {
                                        $colorClass = 'number-red';
                                    }
                                    $frequency = $numberFrequency[$i]['frequency'] ?? 0;
                                ?>
                                <div class="number-cell <?php echo $colorClass; ?>">
                                    <?php echo $i; ?><br>
                                    <small><?php echo $frequency; ?></small>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">Upcoming Draws</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Draw Number</th>
                                        <th>Scheduled Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcomingDraws as $draw): ?>
                                        <tr>
                                            <td>#<?php echo $draw['draw_number']; ?></td>
                                            <td><?php echo $draw['draw_time']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">Recent Draw History</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped draw-history-table">
                        <thead>
                            <tr>
                                <th>Draw #</th>
                                <th>Winning Number</th>
                                <th>Color</th>
                                <th>Draw Time</th>
                                <th>Total Bets</th>
                                <th>Total Stake</th>
                                <th>Total Payout</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentDraws as $draw): ?>
                                <?php 
                                    $colorClass = 'color-black';
                                    if ($draw['winning_number'] == 0) {
                                        $colorClass = 'color-green';
                                    } elseif ($draw['winning_color'] == 'red') {
                                        $colorClass = 'color-red';
                                    }
                                ?>
                                <tr>
                                    <td>#<?php echo $draw['draw_number']; ?></td>
                                    <td><span class="winning-number <?php echo $colorClass; ?>"><?php echo $draw['winning_number']; ?></span></td>
                                    <td><?php echo ucfirst($draw['winning_color']); ?></td>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($draw['draw_time'])); ?></td>
                                    <td><?php echo $draw['total_bets']; ?></td>
                                    <td>$<?php echo number_format($draw['total_stake'], 2); ?></td>
                                    <td>$<?php echo number_format($draw['total_payout'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="mt-4 text-center">
            <a href="update_next_draw.php" class="btn btn-primary">Set Next Draw Winning Number</a>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
