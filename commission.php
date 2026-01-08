<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if not logged in
    header('Location: login.html');
    exit;
}

// Database connection parameters
$servername = "localhost";
$username = "root";  // Default XAMPP username
$password = "";      // Default XAMPP password (empty)
$dbname = "roulette";  // Using the roulette database

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get user ID from session
$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

// Make sure we have a valid user_id
if (!$userId || $userId <= 0) {
    // Redirect to login page if not logged in or invalid user_id
    header('Location: login.html');
    exit;
}

// Get user details
$user = null;
$stmt = $conn->prepare("SELECT username, role, cash_balance FROM users WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
}

// Get the most recent date from commission_summary for this cashier
$mostRecentDate = date('Y-m-d'); // Default to today
$recentDateQuery = "SELECT MAX(date_created) as latest_date FROM commission_summary WHERE user_id = ?";
$stmt = $conn->prepare($recentDateQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$recentDateResult = $stmt->get_result();
if ($recentDateResult && $recentDateResult->num_rows > 0) {
    $latestDateRow = $recentDateResult->fetch_assoc();
    if ($latestDateRow['latest_date']) {
        $mostRecentDate = $latestDateRow['latest_date'];
    }
}

// Get commission summary for the most recent day for this cashier
$commissionSummary = null;
$stmt = $conn->prepare("SELECT * FROM commission_summary WHERE date_created = ? AND user_id = ?");
$stmt->bind_param("si", $mostRecentDate, $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $commissionSummary = $result->fetch_assoc();
} else {
    // Create empty summary
    $commissionSummary = [
        'user_id' => $userId,
        'date_created' => $mostRecentDate,
        'total_bets' => 0,
        'total_commission' => 0
    ];
}

// Get commission history (last 30 days) for this cashier
$commissionHistory = [];
$sql = "SELECT date_created, total_bets, total_commission
        FROM commission_summary
        WHERE user_id = ? AND date_created >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY date_created DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $commissionHistory[] = $row;
    }
}

// No manual commission adding - commission is calculated automatically from betting slips
$message = '';
$messageType = '';

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commission Tracking</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            padding-top: 20px;
            background-color: #f5f7fa;
        }
        .container {
            max-width: 1200px;
        }
        .card {
            margin-bottom: 20px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            overflow: hidden;
            border: none;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 20px rgba(0, 0, 0, 0.15);
        }
        .card-header {
            padding: 15px 20px;
            border-bottom: none;
        }
        .card-body {
            padding: 25px;
        }
        .table th {
            background-color: #f8f9fa;
            border-top: none;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        .table td, .table th {
            padding: 15px;
            vertical-align: middle;
        }
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(0,0,0,0.02);
        }
        .navbar {
            margin-bottom: 20px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            border-radius: 0 0 10px 10px;
        }
        .stat-card {
            text-align: center;
            padding: 25px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            color: white;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: scale(1.03);
        }
        .stat-card h3 {
            font-size: 2.2rem;
            margin-bottom: 10px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }
        .stat-card p {
            font-size: 1.2rem;
            margin-bottom: 0;
            opacity: 0.9;
        }
        .stat-card.primary {
            background: linear-gradient(135deg, #007bff, #0056b3);
        }
        .stat-card.success {
            background: linear-gradient(135deg, #28a745, #1e7e34);
        }
        .stat-card.info {
            background: linear-gradient(135deg, #17a2b8, #117a8b);
        }
        .chart-container {
            position: relative;
            height: 400px;
            margin: 0 auto;
            background: linear-gradient(to bottom, rgba(255,255,255,0.8), rgba(255,255,255,0.5));
            border-radius: 8px;
            padding: 10px;
            box-shadow: inset 0 0 10px rgba(0,0,0,0.05);
            animation: fadeIn 0.8s ease-out;
        }
        .btn-group {
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-radius: 30px;
            overflow: hidden;
        }
        .btn-group .btn {
            border-radius: 0;
            padding: 8px 16px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s;
            border: none;
        }
        .btn-group .btn:first-child {
            border-top-left-radius: 30px;
            border-bottom-left-radius: 30px;
        }
        .btn-group .btn:last-child {
            border-top-right-radius: 30px;
            border-bottom-right-radius: 30px;
        }
        .btn-info {
            background: linear-gradient(135deg, #17a2b8, #138496);
        }
        .btn-light {
            background: linear-gradient(135deg, #f8f9fa, #e2e6ea);
        }
        .btn-light:hover {
            background: linear-gradient(135deg, #e2e6ea, #dae0e5);
        }
        /* Animation for chart loading */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        h1 {
            font-weight: 700;
            color: #343a40;
            margin-bottom: 30px;
            position: relative;
            padding-bottom: 10px;
        }
        h1:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100px;
            height: 4px;
            background: linear-gradient(to right, #007bff, #17a2b8);
            border-radius: 2px;
        }
    </style>
</head>
<body>
    <div class="container">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <a class="navbar-brand" href="#">Roulette</a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="https://roulette.aruka.app/slipp/index.html">Game</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="https://roulette.aruka.app/slipp/my_transactions_new.php">My Transactions</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="https://roulette.aruka.app/slipp/redeem_voucher.php">Redeem Voucher</a>
                    </li>
                    <li class="nav-item active">
                        <a class="nav-link" href="https://roulette.aruka.app/slipp/commission.php">Commission</a>
                    </li>
                </ul>
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="https://roulette.aruka.app/slipp/logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </nav>

        <h1 class="mb-4">Your Commission Tracking</h1>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> This page shows commission earned from betting slips sold using your account credits.
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4">
                <div class="stat-card primary">
                    <h3>$<?php echo number_format($commissionSummary['total_bets'], 2); ?></h3>
                    <p>Your Total Bets (<?php echo date('M d, Y', strtotime($commissionSummary['date_created'])); ?>)</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card success">
                    <h3>$<?php echo number_format($commissionSummary['total_commission'], 2); ?></h3>
                    <p>Your Commission (<?php echo date('M d, Y', strtotime($commissionSummary['date_created'])); ?>)</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card info">
                    <h3>4%</h3>
                    <p>Your Commission Rate</p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Your Commission Chart</h5>
                        <div class="btn-group mt-2" role="group">
                            <button type="button" class="btn btn-sm btn-light" data-period="hour">Hour</button>
                            <button type="button" class="btn btn-sm btn-light" data-period="day">Day</button>
                            <button type="button" class="btn btn-sm btn-light" data-period="week">Week</button>
                            <button type="button" class="btn btn-sm btn-light" data-period="month">Month</button>
                            <button type="button" class="btn btn-sm btn-light" data-period="year">Year</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="height: 400px;">
                            <canvas id="commissionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">Your Commission History</h5>
            </div>
            <div class="card-body">
                <?php if (empty($commissionHistory)): ?>
                <div class="alert alert-info">No commission history found.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Total Bets</th>
                                <th>Commission (4%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($commissionHistory as $history): ?>
                            <tr>
                                <td><?php echo $history['date_created']; ?></td>
                                <td>$<?php echo number_format($history['total_bets'], 2); ?></td>
                                <td>$<?php echo number_format($history['total_commission'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <!-- Chart.js and plugins -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <!-- 3D effect libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-3d@2.0.0-rc.1/dist/chartjs-plugin-3d.min.js"></script>
    <script>
        // Initialize chart
        document.addEventListener('DOMContentLoaded', function() {
            // Load Chart.js plugins
            Chart.register(ChartDataLabels);

            const ctx = document.getElementById('commissionChart').getContext('2d');

            // Prepare data for chart
            const rawData = <?php
                // Get more detailed data for hourly/daily/weekly/monthly/yearly views
                $detailedData = [];

                // For demo purposes, we'll generate some sample data based on the existing data
                // In a real implementation, you would query this from the database

                // Use the existing commission history as a base
                foreach ($commissionHistory as $history) {
                    $baseDate = $history['date_created'];
                    $baseBets = floatval($history['total_bets']);
                    $baseCommission = floatval($history['total_commission']);

                    // Add the day record
                    $detailedData['day'][] = [
                        'date' => $baseDate,
                        'bets' => $baseBets,
                        'commission' => $baseCommission
                    ];

                    // Generate week data (same week as the base date)
                    $weekStart = date('Y-m-d', strtotime('monday this week', strtotime($baseDate)));
                    $weekLabel = "Week of " . date('M d', strtotime($weekStart));
                    $weekFound = false;

                    foreach ($detailedData['week'] ?? [] as &$week) {
                        if ($week['date'] === $weekLabel) {
                            $week['bets'] += $baseBets;
                            $week['commission'] += $baseCommission;
                            $weekFound = true;
                            break;
                        }
                    }

                    if (!$weekFound) {
                        $detailedData['week'][] = [
                            'date' => $weekLabel,
                            'bets' => $baseBets,
                            'commission' => $baseCommission
                        ];
                    }

                    // Generate month data
                    $monthLabel = date('M Y', strtotime($baseDate));
                    $monthFound = false;

                    foreach ($detailedData['month'] ?? [] as &$month) {
                        if ($month['date'] === $monthLabel) {
                            $month['bets'] += $baseBets;
                            $month['commission'] += $baseCommission;
                            $monthFound = true;
                            break;
                        }
                    }

                    if (!$monthFound) {
                        $detailedData['month'][] = [
                            'date' => $monthLabel,
                            'bets' => $baseBets,
                            'commission' => $baseCommission
                        ];
                    }

                    // Generate year data
                    $yearLabel = date('Y', strtotime($baseDate));
                    $yearFound = false;

                    foreach ($detailedData['year'] ?? [] as &$year) {
                        if ($year['date'] === $yearLabel) {
                            $year['bets'] += $baseBets;
                            $year['commission'] += $baseCommission;
                            $yearFound = true;
                            break;
                        }
                    }

                    if (!$yearFound) {
                        $detailedData['year'][] = [
                            'date' => $yearLabel,
                            'bets' => $baseBets,
                            'commission' => $baseCommission
                        ];
                    }

                    // Generate hourly data (random distribution for the demo)
                    for ($hour = 9; $hour <= 23; $hour++) {
                        $hourLabel = sprintf("%02d:00", $hour);
                        $hourlyBets = $baseBets * (mt_rand(5, 20) / 100); // Random percentage of daily total
                        $hourlyCommission = $hourlyBets * 0.04; // 4% commission

                        $detailedData['hour'][] = [
                            'date' => $baseDate . ' ' . $hourLabel,
                            'display_date' => $hourLabel,
                            'bets' => $hourlyBets,
                            'commission' => $hourlyCommission
                        ];
                    }
                }

                echo json_encode($detailedData);
            ?>;

            // Set default period to day
            let currentPeriod = 'day';
            let chart = null;

            // Function to update chart based on selected period
            function updateChart(period) {
                // Update active button
                document.querySelectorAll('.btn-group button').forEach(btn => {
                    if (btn.getAttribute('data-period') === period) {
                        btn.classList.remove('btn-light');
                        btn.classList.add('btn-info');
                    } else {
                        btn.classList.remove('btn-info');
                        btn.classList.add('btn-light');
                    }
                });

                // Get data for selected period
                const periodData = rawData[period] || [];

                // Prepare chart data
                const labels = periodData.map(item => period === 'hour' ? item.display_date : item.date);
                const betsData = periodData.map(item => item.bets);
                const commissionData = periodData.map(item => item.commission);

                // Destroy existing chart if it exists
                if (chart) {
                    chart.destroy();
                }

                // Create new chart with 3D effect
                chart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Total Bets',
                                data: betsData,
                                backgroundColor: function(context) {
                                    const index = context.dataIndex;
                                    const value = context.dataset.data[index];
                                    // Create gradient for 3D effect
                                    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
                                    gradient.addColorStop(0, 'rgba(0, 123, 255, 0.9)');
                                    gradient.addColorStop(1, 'rgba(0, 123, 255, 0.5)');
                                    return gradient;
                                },
                                borderColor: 'rgba(0, 123, 255, 1)',
                                borderWidth: 1,
                                borderRadius: 5,
                                borderSkipped: false,
                                datalabels: {
                                    color: '#fff',
                                    anchor: 'end',
                                    align: 'top',
                                    offset: 5,
                                    font: {
                                        weight: 'bold'
                                    },
                                    formatter: function(value) {
                                        return '$' + value.toFixed(2);
                                    },
                                    display: function(context) {
                                        return context.dataset.data[context.dataIndex] > 0;
                                    }
                                }
                            },
                            {
                                label: 'Commission',
                                data: commissionData,
                                backgroundColor: function(context) {
                                    const index = context.dataIndex;
                                    const value = context.dataset.data[index];
                                    // Create gradient for 3D effect
                                    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
                                    gradient.addColorStop(0, 'rgba(40, 167, 69, 0.9)');
                                    gradient.addColorStop(1, 'rgba(40, 167, 69, 0.5)');
                                    return gradient;
                                },
                                borderColor: 'rgba(40, 167, 69, 1)',
                                borderWidth: 1,
                                borderRadius: 5,
                                borderSkipped: false,
                                datalabels: {
                                    color: '#fff',
                                    anchor: 'end',
                                    align: 'top',
                                    offset: 5,
                                    font: {
                                        weight: 'bold'
                                    },
                                    formatter: function(value) {
                                        return '$' + value.toFixed(2);
                                    },
                                    display: function(context) {
                                        return context.dataset.data[context.dataIndex] > 0;
                                    }
                                }
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Commission by ' + period.charAt(0).toUpperCase() + period.slice(1),
                                font: {
                                    size: 18,
                                    weight: 'bold'
                                },
                                padding: {
                                    top: 10,
                                    bottom: 20
                                }
                            },
                            legend: {
                                position: 'top',
                                labels: {
                                    font: {
                                        size: 14
                                    },
                                    usePointStyle: true,
                                    padding: 20
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleFont: {
                                    size: 14
                                },
                                bodyFont: {
                                    size: 14
                                },
                                padding: 15,
                                cornerRadius: 5,
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        label += '$' + context.parsed.y.toFixed(2);
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    font: {
                                        size: 12
                                    }
                                }
                            },
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(200, 200, 200, 0.2)'
                                },
                                ticks: {
                                    font: {
                                        size: 12
                                    },
                                    callback: function(value) {
                                        return '$' + value.toFixed(2);
                                    }
                                }
                            }
                        },
                        animation: {
                            duration: 1500,
                            easing: 'easeOutQuart'
                        },
                        layout: {
                            padding: {
                                top: 20,
                                right: 20,
                                bottom: 20,
                                left: 20
                            }
                        },
                        barPercentage: 0.8,
                        categoryPercentage: 0.7
                    }
                });

                // Add shadow effect to make it look more 3D
                ctx.shadowColor = 'rgba(0, 0, 0, 0.3)';
                ctx.shadowBlur = 15;
                ctx.shadowOffsetX = 10;
                ctx.shadowOffsetY = 10;
            }

            // Add event listeners to period buttons
            document.querySelectorAll('.btn-group button').forEach(button => {
                button.addEventListener('click', function() {
                    const period = this.getAttribute('data-period');
                    currentPeriod = period;
                    updateChart(period);
                });
            });

            // Initialize chart with day data
            updateChart('day');
        });
    </script>
</body>
</html>
