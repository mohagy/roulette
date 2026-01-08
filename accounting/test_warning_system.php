<?php
// Test Warning System - Demonstrates the real-time warning functionality
session_start();

// Simulate logged in user for testing
$_SESSION['user_id'] = 1;

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "roulette";

date_default_timezone_set('America/Guyana');

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create test data if tables don't exist
$result = $conn->query("SHOW TABLES LIKE 'betting_slips'");
if ($result->num_rows == 0) {
    // Create tables and insert test data
    $sql = file_get_contents('betting_system_schema.sql');
    $conn->multi_query($sql);
    
    // Wait for all queries to complete
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->next_result());
}

// Get current roulette state
$roulette_state = [
    'current_draw' => 9,
    'next_draw' => 10,
    'last_draw' => 8,
    'winning_number' => 23,
    'countdown_time' => 180
];

$result = $conn->query("SELECT * FROM roulette_state ORDER BY id DESC LIMIT 1");
if ($result && $result->num_rows > 0) {
    $state = $result->fetch_assoc();
    $roulette_state = [
        'current_draw' => $state['current_draw_number'] ?? 9,
        'next_draw' => ($state['current_draw_number'] ?? 9) + 1,
        'last_draw' => $state['current_draw_number'] > 0 ? $state['current_draw_number'] - 1 : 8,
        'winning_number' => $state['winning_number'] ?? 23,
        'countdown_time' => $state['countdown_time'] ?? 180
    ];
}

// Get bet warning data
$bet_warning = [
    'total_straight_bets' => 0,
    'is_warning' => false,
    'warning_level' => 'none'
];

$current_draw = $roulette_state['current_draw'];
$result = $conn->query("
    SELECT COALESCE(SUM(bd.bet_amount), 0) as total_straight_bets
    FROM betting_slips bs
    JOIN bet_details bd ON bs.slip_id = bd.slip_id
    WHERE bs.draw_number = $current_draw 
    AND bs.status = 'active'
    AND bd.bet_type = 'straight_up'
");

if ($result && $result->num_rows > 0) {
    $bet_data = $result->fetch_assoc();
    $bet_warning['total_straight_bets'] = $bet_data['total_straight_bets'] ?? 0;
    
    if ($bet_warning['total_straight_bets'] >= 1600) {
        $bet_warning['is_warning'] = true;
        $bet_warning['warning_level'] = 'exceeded';
    } elseif ($bet_warning['total_straight_bets'] >= 1280) { // 80% of 1600
        $bet_warning['is_warning'] = true;
        $bet_warning['warning_level'] = 'approaching';
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Roulette Warning System Test</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #2C3E50 0%, #34495E 100%);
            min-height: 100vh;
            font-family: 'Nunito', sans-serif;
            padding: 20px;
        }
        .test-container {
            max-width: 800px;
            margin: 0 auto;
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .warning-demo {
            background: rgba(44, 62, 80, 0.95);
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
            color: white;
        }
        
        /* Warning System Styles */
        .warning-widget {
            background: linear-gradient(135deg, #c0392b 0%, #e74c3c 100%);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            border: 2px solid #e74c3c;
            animation: warningPulse 2s infinite;
        }
        .warning-widget.approaching {
            background: linear-gradient(135deg, #e67e22 0%, #f39c12 100%);
            border-color: #f39c12;
        }
        .warning-widget.hidden {
            display: none;
        }
        .warning-header {
            color: white;
            font-weight: bold;
            font-size: 1.1rem;
            margin-bottom: 15px;
            text-align: center;
        }
        .warning-content {
            color: white;
            font-size: 0.95rem;
            line-height: 1.4;
        }
        .warning-amount {
            font-size: 1.3rem;
            font-weight: bold;
            color: #fff;
            text-shadow: 0 0 10px rgba(255,255,255,0.5);
        }
        .cash-status {
            background: rgba(0,0,0,0.3);
            padding: 10px;
            border-radius: 8px;
            margin-top: 10px;
        }
        .cash-sufficient {
            border-left: 4px solid #27ae60;
        }
        .cash-insufficient {
            border-left: 4px solid #e74c3c;
            animation: warningPulse 1.5s infinite;
        }
        @keyframes warningPulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        .bet-summary {
            background: rgba(0,0,0,0.2);
            padding: 8px;
            border-radius: 6px;
            margin: 5px 0;
            font-size: 0.85rem;
        }
        .risk-meter {
            background: rgba(0,0,0,0.3);
            height: 8px;
            border-radius: 4px;
            margin: 10px 0;
            overflow: hidden;
        }
        .risk-fill {
            height: 100%;
            transition: width 0.5s ease;
            border-radius: 4px;
        }
        .risk-low { background: #27ae60; }
        .risk-medium { background: #f39c12; }
        .risk-high { background: #e74c3c; }
        
        .test-btn {
            margin: 10px;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }
        .btn-test-warning { background: #e74c3c; color: white; }
        .btn-test-approaching { background: #f39c12; color: white; }
        .btn-test-safe { background: #27ae60; color: white; }
    </style>
</head>
<body>
    <div class="test-container">
        <h1 class="text-center mb-4">🎰 Roulette Warning System Test</h1>
        
        <div class="alert alert-info">
            <h5><i class="fas fa-info-circle"></i> System Overview</h5>
            <p>This system monitors straight up bet amounts in real-time and alerts cashiers when limits are approached or exceeded.</p>
            <ul>
                <li><strong>Warning Threshold:</strong> $1,600</li>
                <li><strong>Approaching Threshold:</strong> $1,280 (80% of limit)</li>
                <li><strong>Payout Ratio:</strong> 35:1 for straight up bets</li>
                <li><strong>Update Frequency:</strong> Every 10 seconds</li>
            </ul>
        </div>

        <div class="warning-demo">
            <h4><i class="fas fa-exclamation-triangle"></i> Live Warning System</h4>
            
            <!-- Warning Widget Demo -->
            <div id="warning-widget" class="warning-widget <?php echo $bet_warning['is_warning'] ? $bet_warning['warning_level'] : 'hidden'; ?>">
                <div class="warning-header">
                    <i class="fas fa-exclamation-triangle"></i> STRAIGHT BET WARNING
                </div>
                <div class="warning-content">
                    <div class="warning-amount" id="warning-amount">
                        $<?php echo number_format($bet_warning['total_straight_bets'], 2); ?>
                    </div>
                    <div id="warning-message">
                        <?php if ($bet_warning['warning_level'] === 'exceeded'): ?>
                            WARNING: Straight bet total is EXCEEDING the $1,600 limit. Please ensure sufficient cash reserves are available to cover potential payouts.
                        <?php elseif ($bet_warning['warning_level'] === 'approaching'): ?>
                            CAUTION: Straight bet total is approaching the $1,600 limit. Monitor cash reserves closely.
                        <?php endif; ?>
                    </div>
                    
                    <div class="risk-meter">
                        <div class="risk-fill risk-high" id="risk-fill" style="width: <?php echo min(($bet_warning['total_straight_bets'] / 1600) * 100, 100); ?>%"></div>
                    </div>
                    
                    <div class="bet-summary">
                        <strong>Max Potential Payout:</strong> $<span id="max-payout"><?php echo number_format($bet_warning['total_straight_bets'] * 35, 2); ?></span>
                    </div>
                    
                    <div class="cash-status cash-insufficient" id="cash-status">
                        <strong>Cash Drawer Status:</strong> <span id="cash-status-text">Checking...</span>
                    </div>
                </div>
            </div>

            <div class="text-center">
                <h5>Test Different Scenarios:</h5>
                <button class="test-btn btn-test-safe" onclick="testScenario(500)">Safe Level ($500)</button>
                <button class="test-btn btn-test-approaching" onclick="testScenario(1400)">Approaching ($1,400)</button>
                <button class="test-btn btn-test-warning" onclick="testScenario(1800)">Exceeded ($1,800)</button>
                <button class="test-btn btn-test-safe" onclick="testScenario(0)">Reset</button>
            </div>
        </div>

        <div class="alert alert-success">
            <h5><i class="fas fa-check-circle"></i> Integration Status</h5>
            <p><strong>Current Straight Bet Total:</strong> $<?php echo number_format($bet_warning['total_straight_bets'], 2); ?></p>
            <p><strong>Warning Level:</strong> <?php echo ucfirst($bet_warning['warning_level']); ?></p>
            <p><strong>API Endpoints:</strong></p>
            <ul>
                <li><code>api/bet_monitoring.php</code> - Real-time bet monitoring</li>
                <li><code>api/accounting_dashboard_data.php</code> - Dashboard data with roulette state</li>
            </ul>
        </div>
    </div>

    <script>
        function testScenario(amount) {
            const warningWidget = document.getElementById('warning-widget');
            const warningAmount = document.getElementById('warning-amount');
            const warningMessage = document.getElementById('warning-message');
            const riskFill = document.getElementById('risk-fill');
            const maxPayout = document.getElementById('max-payout');
            const cashStatusText = document.getElementById('cash-status-text');

            // Update amounts
            warningAmount.textContent = '$' + amount.toLocaleString('en-US', {minimumFractionDigits: 2});
            maxPayout.textContent = (amount * 35).toLocaleString('en-US', {minimumFractionDigits: 2});

            // Update risk meter
            const riskPercentage = Math.min((amount / 1600) * 100, 100);
            riskFill.style.width = riskPercentage + '%';
            
            // Update risk meter color
            riskFill.className = 'risk-fill';
            if (riskPercentage >= 100) {
                riskFill.classList.add('risk-high');
            } else if (riskPercentage >= 80) {
                riskFill.classList.add('risk-medium');
            } else {
                riskFill.classList.add('risk-low');
            }

            // Update warning widget
            warningWidget.className = 'warning-widget';
            if (amount >= 1600) {
                warningWidget.classList.add('exceeded');
                warningMessage.textContent = `WARNING: Straight bet total ($${amount.toLocaleString('en-US', {minimumFractionDigits: 2})}) is EXCEEDING the $1,600 limit. Please ensure sufficient cash reserves are available to cover potential payouts.`;
                cashStatusText.innerHTML = '⚠ INSUFFICIENT! Need additional cash reserves';
            } else if (amount >= 1280) {
                warningWidget.classList.add('approaching');
                warningMessage.textContent = `CAUTION: Straight bet total ($${amount.toLocaleString('en-US', {minimumFractionDigits: 2})}) is approaching the $1,600 limit. Monitor cash reserves closely.`;
                cashStatusText.innerHTML = '⚠ Monitor closely - approaching limit';
            } else if (amount > 0) {
                warningWidget.classList.add('approaching');
                warningMessage.textContent = `Current straight bet total: $${amount.toLocaleString('en-US', {minimumFractionDigits: 2})}. System is operating normally.`;
                cashStatusText.innerHTML = '✓ Sufficient reserves available';
            } else {
                warningWidget.classList.add('hidden');
            }
        }

        // Auto-refresh simulation
        setInterval(() => {
            fetch('api/bet_monitoring.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Bet monitoring data:', data);
                    }
                })
                .catch(error => console.log('API not available yet:', error.message));
        }, 10000);
    </script>
</body>
</html>
