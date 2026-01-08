<?php
// Accounting Department Dashboard
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Check if user has accounting department access
$user_role = $_SESSION['role'] ?? '';
$allowed_roles = ['admin', 'accounting_manager', 'accounting_staff'];

// For now, allow admin access - later implement proper department role checking
if (!in_array($user_role, $allowed_roles) && $user_role !== 'admin') {
    header('Location: ../index.php');
    exit;
}

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

// Get dashboard statistics
$stats = [
    'total_assets' => 0,
    'total_liabilities' => 0,
    'accounts_payable' => 0,
    'accounts_receivable' => 0,
    'pending_invoices' => 0,
    'overdue_payments' => 0
];

// Total fixed assets value
$result = $conn->query("SELECT SUM(current_value) as total FROM fixed_assets WHERE status = 'active'");
if ($result && $result->num_rows > 0) {
    $stats['total_assets'] = $result->fetch_assoc()['total'] ?? 0;
}

// Total liabilities
$result = $conn->query("SELECT SUM(current_balance) as total FROM liabilities WHERE status = 'active'");
if ($result && $result->num_rows > 0) {
    $stats['total_liabilities'] = $result->fetch_assoc()['total'] ?? 0;
}

// Accounts payable balance
$result = $conn->query("SELECT SUM(balance) as total FROM accounts_payable WHERE status IN ('pending', 'partial')");
if ($result && $result->num_rows > 0) {
    $stats['accounts_payable'] = $result->fetch_assoc()['total'] ?? 0;
}

// Accounts receivable balance
$result = $conn->query("SELECT SUM(balance) as total FROM accounts_receivable WHERE status IN ('pending', 'partial')");
if ($result && $result->num_rows > 0) {
    $stats['accounts_receivable'] = $result->fetch_assoc()['total'] ?? 0;
}

// Pending invoices
$result = $conn->query("SELECT COUNT(*) as count FROM accounts_payable WHERE status = 'pending'");
if ($result && $result->num_rows > 0) {
    $stats['pending_invoices'] = $result->fetch_assoc()['count'];
}

// Overdue payments
$result = $conn->query("SELECT COUNT(*) as count FROM accounts_payable WHERE status IN ('pending', 'partial') AND due_date < CURDATE()");
if ($result && $result->num_rows > 0) {
    $stats['overdue_payments'] = $result->fetch_assoc()['count'];
}

// Get recent journal entries
$recent_entries = [];
$result = $conn->query("
    SELECT 
        je.entry_number,
        je.description,
        je.total_debit,
        je.total_credit,
        je.entry_date,
        je.status,
        u.username as created_by
    FROM journal_entries je
    LEFT JOIN users u ON je.created_by = u.user_id
    ORDER BY je.created_at DESC
    LIMIT 10
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recent_entries[] = $row;
    }
}

// Get asset distribution by category
$asset_distribution = [];
$result = $conn->query("
    SELECT 
        category,
        COUNT(*) as asset_count,
        SUM(current_value) as total_value
    FROM fixed_assets 
    WHERE status = 'active'
    GROUP BY category
    ORDER BY total_value DESC
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $asset_distribution[] = $row;
    }
}

// Get aging report data
$aging_data = [];
$result = $conn->query("
    SELECT 
        CASE 
            WHEN DATEDIFF(CURDATE(), due_date) <= 0 THEN 'Current'
            WHEN DATEDIFF(CURDATE(), due_date) <= 30 THEN '1-30 Days'
            WHEN DATEDIFF(CURDATE(), due_date) <= 60 THEN '31-60 Days'
            WHEN DATEDIFF(CURDATE(), due_date) <= 90 THEN '61-90 Days'
            ELSE '90+ Days'
        END as age_group,
        COUNT(*) as invoice_count,
        SUM(balance) as total_amount
    FROM accounts_payable 
    WHERE status IN ('pending', 'partial')
    GROUP BY age_group
    ORDER BY 
        CASE age_group
            WHEN 'Current' THEN 1
            WHEN '1-30 Days' THEN 2
            WHEN '31-60 Days' THEN 3
            WHEN '61-90 Days' THEN 4
            ELSE 5
        END
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $aging_data[] = $row;
    }
}

// Get monthly expense trend
$expense_trend = [];
$result = $conn->query("
    SELECT 
        DATE_FORMAT(expense_date, '%Y-%m') as month,
        SUM(amount) as total_expenses
    FROM business_expenses 
    WHERE expense_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    AND status = 'approved'
    GROUP BY DATE_FORMAT(expense_date, '%Y-%m')
    ORDER BY month
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $expense_trend[] = $row;
    }
}

// Get roulette state for draw numbers
$roulette_state = [
    'current_draw' => 0,
    'next_draw' => 1,
    'last_draw' => 0,
    'winning_number' => 0,
    'countdown_time' => 180
];

$result = $conn->query("SELECT * FROM roulette_state ORDER BY id DESC LIMIT 1");
if ($result && $result->num_rows > 0) {
    $state = $result->fetch_assoc();
    $roulette_state = [
        'current_draw' => $state['current_draw_number'] ?? 0,
        'next_draw' => ($state['current_draw_number'] ?? 0) + 1,
        'last_draw' => $state['current_draw_number'] > 0 ? $state['current_draw_number'] - 1 : 0,
        'winning_number' => $state['winning_number'] ?? 0,
        'countdown_time' => $state['countdown_time'] ?? 180
    ];
}

// Get initial bet monitoring data for warning system
$bet_warning = [
    'total_straight_bets' => 0,
    'is_warning' => false,
    'warning_level' => 'none'
];

// Check if betting tables exist and get current straight bet totals
$current_draw = $roulette_state['current_draw'];
$result = $conn->query("SHOW TABLES LIKE 'betting_slips'");
if ($result && $result->num_rows > 0) {
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
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting Department Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #2C3E50 0%, #34495E 100%);
            min-height: 100vh;
            font-family: 'Nunito', sans-serif;
        }
        .sidebar {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            min-height: 100vh;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        .main-content {
            margin-left: 250px;
            margin-right: 320px;
            padding: 20px;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            background: rgba(255,255,255,0.95);
            margin-bottom: 20px;
        }
        .stat-card {
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
        }
        .nav-link {
            color: #5a5c69;
            padding: 12px 20px;
            border-radius: 10px;
            margin: 2px 0;
            transition: all 0.3s ease;
        }
        .nav-link:hover, .nav-link.active {
            background: linear-gradient(135deg, #2C3E50 0%, #34495E 100%);
            color: white;
        }
        .entry-item {
            padding: 10px;
            border-left: 3px solid #2C3E50;
            margin-bottom: 10px;
            background: #f8f9fc;
            border-radius: 5px;
        }
        .entry-posted { border-left-color: #28a745; }
        .entry-draft { border-left-color: #ffc107; }
        .entry-reversed { border-left-color: #dc3545; }
        .aging-item {
            padding: 8px;
            margin-bottom: 8px;
            border-radius: 5px;
            background: #e9ecef;
            border-left: 3px solid #6c757d;
        }
        .aging-current { border-left-color: #28a745; background: #d4edda; }
        .aging-30 { border-left-color: #ffc107; background: #fff3cd; }
        .aging-60 { border-left-color: #fd7e14; background: #ffeaa7; }
        .aging-90 { border-left-color: #dc3545; background: #f8d7da; }
        .real-time-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #28a745;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        /* Right Sidebar Styles */
        .right-sidebar {
            background: rgba(44, 62, 80, 0.95);
            backdrop-filter: blur(10px);
            min-height: 100vh;
            box-shadow: -2px 0 10px rgba(0,0,0,0.1);
            width: 320px;
            right: 0;
            color: white;
        }


        /* Draw Number Widget Styles */
        .draw-widget {
            background: linear-gradient(135deg, #1a252f 0%, #2c3e50 100%);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            border: 2px solid #34495e;
        }
        .draw-header {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
            padding: 10px 15px;
            border-radius: 10px;
            margin-bottom: 15px;
            text-align: center;
            font-weight: bold;
        }
        .draw-section {
            margin-bottom: 15px;
        }
        .draw-number {
            background: #2ecc71;
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .draw-number.last-draw {
            background: #3498db;
        }
        .draw-info {
            color: #bdc3c7;
            font-size: 0.9rem;
            text-align: center;
            margin-bottom: 5px;
        }
        .winning-info {
            background: rgba(52, 73, 94, 0.5);
            padding: 10px;
            border-radius: 8px;
            margin-top: 10px;
        }

        /* Action Buttons */
        .action-btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 10px;
            font-weight: bold;
            margin-bottom: 10px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .btn-complete {
            background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%);
            color: white;
        }
        .btn-complete:hover {
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
            transform: translateY(-2px);
        }
        .btn-reprint {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
        }
        .btn-reprint:hover {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            transform: translateY(-2px);
        }
        .btn-cashout {
            background: linear-gradient(135deg, #e67e22 0%, #f39c12 100%);
            color: white;
        }
        .btn-cashout:hover {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            transform: translateY(-2px);
        }
        .btn-cancel {
            background: linear-gradient(135deg, #c0392b 0%, #e74c3c 100%);
            color: white;
        }
        .btn-cancel:hover {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            transform: translateY(-2px);
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

        /* Floating Bet Display Container Styles */
        .bet-display-container {
            position: fixed;
            top: 100px;
            left: 50%;
            transform: translateX(-50%);
            width: 400px;
            max-width: 90vw;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(15px);
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            z-index: 1000;
            cursor: move;
            user-select: none;
            transition: box-shadow 0.3s ease;
            display: none; /* Hidden by default */
        }

        .bet-display-container:hover {
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
        }

        .bet-display-container.dragging {
            cursor: grabbing;
            transform: none; /* Remove centering transform when dragging */
            transition: none;
        }

        .bet-display-header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 15px 15px 0 0;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: move;
        }

        .bet-display-header .title {
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .bet-display-header .controls {
            display: flex;
            gap: 10px;
        }

        .bet-display-header .control-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s ease;
        }

        .bet-display-header .control-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .bet-display-content {
            padding: 20px;
            max-height: 500px;
            overflow-y: auto;
        }

        .betting-slip-preview {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            color: #6c757d;
            font-style: italic;
        }

        .betting-slip-preview.has-content {
            background: white;
            border: 1px solid #dee2e6;
            color: #333;
            font-style: normal;
        }

        .slip-header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
        }

        .slip-details {
            margin-bottom: 15px;
        }

        .bet-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .bet-item:last-child {
            border-bottom: none;
        }

        .slip-total {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #dee2e6;
            font-weight: bold;
            text-align: center;
        }

        .slip-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .slip-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .slip-btn.primary {
            background: #28a745;
            color: white;
        }

        .slip-btn.primary:hover {
            background: #218838;
        }

        .slip-btn.secondary {
            background: #6c757d;
            color: white;
        }

        .slip-btn.secondary:hover {
            background: #5a6268;
        }

        /* Position indicator for debugging */
        .position-indicator {
            position: fixed;
            top: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8rem;
            z-index: 1001;
            display: none;
        }

        /* Floating Countdown Timer Widget Styles */
        .countdown-timer-widget {
            position: fixed;
            top: 20px;
            right: 20px;
            width: 280px;
            background: rgba(44, 62, 80, 0.95);
            backdrop-filter: blur(15px);
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.1);
            z-index: 999;
            cursor: move;
            user-select: none;
            transition: all 0.3s ease;
            color: white;
            display: block;
        }

        .countdown-timer-widget:hover {
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
            transform: translateY(-2px);
        }

        .countdown-timer-widget.dragging {
            cursor: grabbing;
            transition: none;
        }

        .countdown-timer-widget.minimized .timer-content {
            display: none;
        }

        .countdown-timer-widget.warning {
            border-color: #f39c12;
            box-shadow: 0 20px 40px rgba(243, 156, 18, 0.3);
        }

        .countdown-timer-widget.critical {
            border-color: #e74c3c;
            box-shadow: 0 20px 40px rgba(231, 76, 60, 0.4);
            animation: criticalPulse 1s infinite;
        }

        @keyframes criticalPulse {
            0%, 100% {
                box-shadow: 0 20px 40px rgba(231, 76, 60, 0.4);
                border-color: #e74c3c;
            }
            50% {
                box-shadow: 0 25px 50px rgba(231, 76, 60, 0.6);
                border-color: #ff6b6b;
            }
        }

        .timer-header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            padding: 12px 15px;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: move;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .timer-header .title {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .timer-header .timer-icon {
            font-size: 1rem;
            animation: tickTock 1s infinite;
        }

        @keyframes tickTock {
            0%, 50% { transform: rotate(0deg); }
            25% { transform: rotate(5deg); }
            75% { transform: rotate(-5deg); }
        }

        .timer-header .controls {
            display: flex;
            gap: 5px;
        }

        .timer-control-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s ease;
            font-size: 0.8rem;
        }

        .timer-control-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .timer-content {
            padding: 20px 15px;
            text-align: center;
        }

        .countdown-display {
            font-size: 2.5rem;
            font-weight: bold;
            font-family: 'Courier New', monospace;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            letter-spacing: 2px;
        }

        .countdown-display.warning {
            color: #f39c12;
        }

        .countdown-display.critical {
            color: #e74c3c;
            animation: criticalFlash 0.5s infinite;
        }

        @keyframes criticalFlash {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .timer-label {
            font-size: 0.85rem;
            color: #bdc3c7;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .timer-info {
            font-size: 0.8rem;
            color: #95a5a6;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .timer-status {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            margin-top: 8px;
        }

        .timer-status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #27ae60;
            animation: pulse 2s infinite;
        }

        .timer-status-indicator.warning {
            background: #f39c12;
        }

        .timer-status-indicator.critical {
            background: #e74c3c;
        }

        .timer-status-text {
            font-size: 0.75rem;
            color: #bdc3c7;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .countdown-timer-widget {
                width: 240px;
                top: 10px;
                right: 10px;
            }

            .countdown-display {
                font-size: 2rem;
            }

            .timer-content {
                padding: 15px 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar position-fixed">
        <div class="p-4">
            <h4 class="text-dark"><i class="fas fa-calculator"></i> Accounting</h4>
        </div>
        <nav class="nav flex-column px-3">
            <a class="nav-link active" href="index.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a class="nav-link" href="chart_of_accounts.php">
                <i class="fas fa-list"></i> Chart of Accounts
            </a>
            <a class="nav-link" href="accounts_payable.php">
                <i class="fas fa-file-invoice-dollar"></i> Accounts Payable
            </a>
            <a class="nav-link" href="accounts_receivable.php">
                <i class="fas fa-hand-holding-usd"></i> Accounts Receivable
            </a>
            <a class="nav-link" href="fixed_assets.php">
                <i class="fas fa-building"></i> Fixed Assets
            </a>
            <a class="nav-link" href="liabilities.php">
                <i class="fas fa-credit-card"></i> Liabilities
            </a>
            <a class="nav-link" href="journal_entries.php">
                <i class="fas fa-book"></i> Journal Entries
            </a>
            <a class="nav-link" href="financial_reports.php">
                <i class="fas fa-chart-line"></i> Financial Reports
            </a>
            <a class="nav-link" href="budget_analysis.php">
                <i class="fas fa-chart-pie"></i> Budget Analysis
            </a>
            <hr>
            <a class="nav-link" href="../admin/index.php">
                <i class="fas fa-cog"></i> Admin Panel
            </a>
            <a class="nav-link" href="../logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="text-white">Accounting Department Dashboard</h1>
                <p class="text-white-50 mb-0">
                    <span class="real-time-indicator"></span>
                    Financial monitoring | Last updated: <span id="last-updated"><?php echo date('g:i:s A'); ?></span>
                </p>
            </div>
            <div>
                <button class="btn btn-light" onclick="refreshDashboard()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <a href="journal_entries.php?action=new" class="btn btn-dark">
                    <i class="fas fa-plus"></i> New Journal Entry
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card stat-card border-left-success">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Assets</div>
                                <div class="stat-value text-gray-800" id="total-assets">$<?php echo number_format($stats['total_assets'], 0); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-building stat-icon text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card stat-card border-left-danger">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Liabilities</div>
                                <div class="stat-value text-gray-800" id="total-liabilities">$<?php echo number_format($stats['total_liabilities'], 0); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-credit-card stat-icon text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card stat-card border-left-warning">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Accounts Payable</div>
                                <div class="stat-value text-gray-800" id="accounts-payable">$<?php echo number_format($stats['accounts_payable'], 0); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-file-invoice-dollar stat-icon text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card stat-card border-left-info">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Accounts Receivable</div>
                                <div class="stat-value text-gray-800" id="accounts-receivable">$<?php echo number_format($stats['accounts_receivable'], 0); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-hand-holding-usd stat-icon text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card stat-card border-left-primary">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Pending Invoices</div>
                                <div class="stat-value text-gray-800" id="pending-invoices"><?php echo $stats['pending_invoices']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-clock stat-icon text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="card stat-card border-left-secondary">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Overdue Payments</div>
                                <div class="stat-value text-gray-800" id="overdue-payments"><?php echo $stats['overdue_payments']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-exclamation-triangle stat-icon text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts and Activities -->
        <div class="row">
            <!-- Asset Distribution Chart -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-chart-pie"></i> Fixed Assets Distribution
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="position: relative; height: 300px;">
                            <canvas id="assetChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Expense Trend -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-chart-line"></i> Monthly Expense Trend
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="position: relative; height: 300px;">
                            <canvas id="expenseChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Entries and Aging Report -->
        <div class="row">
            <!-- Recent Journal Entries -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-book"></i> Recent Journal Entries
                        </h6>
                    </div>
                    <div class="card-body">
                        <div id="recent-entries" style="max-height: 300px; overflow-y: auto;">
                            <?php if (empty($recent_entries)): ?>
                                <p class="text-muted text-center">No recent journal entries</p>
                            <?php else: ?>
                                <?php foreach ($recent_entries as $entry): ?>
                                <div class="entry-item entry-<?php echo $entry['status']; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?php echo htmlspecialchars($entry['entry_number']); ?></strong>
                                            <br>
                                            <small><?php echo htmlspecialchars($entry['description']); ?></small>
                                            <?php if ($entry['created_by']): ?>
                                            <br><small class="text-muted">by <?php echo htmlspecialchars($entry['created_by']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-<?php echo $entry['status'] === 'posted' ? 'success' : ($entry['status'] === 'draft' ? 'warning' : 'danger'); ?>">
                                                <?php echo ucfirst($entry['status']); ?>
                                            </span>
                                            <br>
                                            <small class="text-muted">$<?php echo number_format($entry['total_debit'], 2); ?></small>
                                            <br>
                                            <small class="text-muted"><?php echo date('M d', strtotime($entry['entry_date'])); ?></small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Aging Report -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-calendar-alt"></i> Accounts Payable Aging
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($aging_data)): ?>
                            <div class="text-center py-3">
                                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                <p class="text-muted">No outstanding payables!</p>
                            </div>
                        <?php else: ?>
                            <div style="max-height: 300px; overflow-y: auto;">
                                <?php foreach ($aging_data as $aging): ?>
                                <div class="aging-item aging-<?php echo strtolower(str_replace([' ', '+', '-'], '', $aging['age_group'])); ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo htmlspecialchars($aging['age_group']); ?></strong>
                                            <br>
                                            <small><?php echo $aging['invoice_count']; ?> invoice<?php echo $aging['invoice_count'] != 1 ? 's' : ''; ?></small>
                                        </div>
                                        <div class="text-end">
                                            <strong>$<?php echo number_format($aging['total_amount'], 2); ?></strong>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-bolt"></i> Quick Actions
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <a href="journal_entries.php?action=new" class="btn btn-dark btn-block mb-2">
                                    <i class="fas fa-plus"></i> New Journal Entry
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="accounts_payable.php?action=new" class="btn btn-warning btn-block mb-2">
                                    <i class="fas fa-file-invoice-dollar"></i> Record Invoice
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="fixed_assets.php?action=new" class="btn btn-success btn-block mb-2">
                                    <i class="fas fa-building"></i> Add Fixed Asset
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="financial_reports.php" class="btn btn-info btn-block mb-2">
                                    <i class="fas fa-chart-line"></i> Generate Reports
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Sidebar - Draw Numbers & Warning System -->
    <div class="right-sidebar position-fixed">
        <div class="p-4">
            <!-- Bet Warning Widget -->
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
                        <div class="risk-fill" id="risk-fill" style="width: <?php echo min(($bet_warning['total_straight_bets'] / 1600) * 100, 100); ?>%"></div>
                    </div>

                    <div class="bet-summary">
                        <strong>Max Potential Payout:</strong> $<span id="max-payout"><?php echo number_format($bet_warning['total_straight_bets'] * 35, 2); ?></span>
                    </div>

                    <div class="cash-status" id="cash-status">
                        <strong>Cash Drawer Status:</strong> <span id="cash-status-text">Checking...</span>
                    </div>
                </div>
            </div>

            <!-- Draw Numbers Widget -->
            <div class="draw-widget">
                <div class="draw-header">
                    <i class="fas fa-hashtag"></i> DRAW NUMBERS
                </div>

                <!-- Next Betting Slip Draw -->
                <div class="draw-section">
                    <div class="draw-info">
                        <i class="fas fa-circle text-success"></i> NEXT BETTING SLIP DRAW
                    </div>
                    <div class="draw-number" id="next-draw-number">
                        #<?php echo $roulette_state['next_draw']; ?>
                    </div>
                    <div class="draw-info">
                        New betting slips will be assigned to this draw
                    </div>
                </div>

                <!-- Last Completed Draw -->
                <div class="draw-section">
                    <div class="draw-info">
                        <i class="fas fa-circle text-info"></i> LAST COMPLETED DRAW
                    </div>
                    <div class="draw-number last-draw" id="last-draw-number">
                        #<?php echo $roulette_state['last_draw']; ?>
                    </div>
                    <div class="draw-info">
                        Most recent draw with results
                    </div>
                    <div class="winning-info">
                        <strong>Winning Number: <span id="winning-number"><?php echo $roulette_state['winning_number']; ?></span></strong><br>
                        <small>Total Slips: <span id="total-slips">0</span></small><br>
                        <small>Winning Slips: <span id="winning-slips">0</span></small><br>
                        <small>Win Rate: <span id="win-rate">0%</span></small>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <button class="action-btn btn-complete" onclick="handleComplete()">
                    <i class="fas fa-check-circle"></i> COMPLETE
                </button>

                <button class="action-btn btn-reprint" onclick="handleReprint()">
                    <i class="fas fa-print"></i> Reprint Slip
                </button>

                <button class="action-btn btn-cashout" onclick="handleCashout()">
                    <i class="fas fa-money-bill-wave"></i> CASHOUT
                </button>

                <button class="action-btn btn-cancel" onclick="handleCancelSlip()">
                    <i class="fas fa-times-circle"></i> CANCEL SLIP
                </button>
            </div>
        </div>
    </div>

    <!-- Floating Bet Display Container -->
    <div id="bet-display-container" class="bet-display-container">
        <div class="bet-display-header" id="bet-display-header">
            <div class="title">
                <i class="fas fa-receipt"></i>
                Betting Slip Preview
            </div>
            <div class="controls">
                <button class="control-btn" id="minimize-btn" title="Minimize">
                    <i class="fas fa-minus"></i>
                </button>
                <button class="control-btn" id="close-btn" title="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="bet-display-content" id="bet-display-content">
            <div class="betting-slip-preview" id="betting-slip-preview">
                <i class="fas fa-ticket-alt" style="font-size: 2rem; margin-bottom: 10px; opacity: 0.5;"></i>
                <p>No betting slip selected</p>
                <p><small>Click "Print Betting Slip" to preview a slip here</small></p>
            </div>
        </div>
    </div>

    <!-- Position Indicator (for debugging) -->
    <div id="position-indicator" class="position-indicator">
        Position: <span id="position-coords">0, 0</span>
    </div>

    <!-- Floating Countdown Timer Widget -->
    <div id="countdown-timer-widget" class="countdown-timer-widget">
        <div class="timer-header" id="timer-header">
            <div class="title">
                <i class="fas fa-clock timer-icon"></i>
                <span>Next Draw</span>
            </div>
            <div class="controls">
                <button class="timer-control-btn" id="timer-minimize-btn" title="Minimize">
                    <i class="fas fa-minus"></i>
                </button>
                <button class="timer-control-btn" id="timer-close-btn" title="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="timer-content" id="timer-content">
            <div class="timer-label">Time Remaining</div>
            <div class="countdown-display" id="countdown-display">03:00</div>
            <div class="timer-info">
                <div>Draw #<span id="timer-draw-number"><?php echo $roulette_state['next_draw']; ?></span></div>
                <div class="timer-status">
                    <div class="timer-status-indicator" id="timer-status-indicator"></div>
                    <span class="timer-status-text" id="timer-status-text">Active</span>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Asset Distribution Chart
        const assetData = <?php echo json_encode($asset_distribution); ?>;
        
        if (assetData.length > 0) {
            const ctx1 = document.getElementById('assetChart').getContext('2d');
            new Chart(ctx1, {
                type: 'doughnut',
                data: {
                    labels: assetData.map(item => item.category.charAt(0).toUpperCase() + item.category.slice(1)),
                    datasets: [{
                        data: assetData.map(item => parseFloat(item.total_value)),
                        backgroundColor: [
                            '#2C3E50', '#34495E', '#5D6D7E', '#85929E', '#AEB6BF', '#D5DBDB'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': $' + context.parsed.toFixed(2);
                                }
                            }
                        }
                    }
                }
            });
        }

        // Monthly Expense Trend Chart
        const expenseData = <?php echo json_encode($expense_trend); ?>;
        
        if (expenseData.length > 0) {
            const ctx2 = document.getElementById('expenseChart').getContext('2d');
            new Chart(ctx2, {
                type: 'line',
                data: {
                    labels: expenseData.map(item => {
                        const date = new Date(item.month + '-01');
                        return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                    }),
                    datasets: [{
                        label: 'Monthly Expenses',
                        data: expenseData.map(item => parseFloat(item.total_expenses)),
                        borderColor: '#2C3E50',
                        backgroundColor: 'rgba(44, 62, 80, 0.1)',
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toFixed(0);
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Expenses: $' + context.parsed.y.toFixed(2);
                                }
                            }
                        }
                    }
                }
            });
        }

        // Real-time updates
        function refreshDashboard() {
            fetch('api/accounting_dashboard_data.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateStats(data.stats);
                        updateDrawNumbers(data.roulette_state);
                        updateLastUpdated();
                    }
                })
                .catch(error => console.error('Error:', error));

            // Also refresh bet monitoring
            refreshBetMonitoring();
        }

        // Bet monitoring refresh
        function refreshBetMonitoring() {
            fetch('api/bet_monitoring.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateWarningSystem(data);
                    }
                })
                .catch(error => console.error('Bet monitoring error:', error));
        }

        function updateStats(stats) {
            document.getElementById('total-assets').textContent = '$' + parseInt(stats.total_assets).toLocaleString();
            document.getElementById('total-liabilities').textContent = '$' + parseInt(stats.total_liabilities).toLocaleString();
            document.getElementById('accounts-payable').textContent = '$' + parseInt(stats.accounts_payable).toLocaleString();
            document.getElementById('accounts-receivable').textContent = '$' + parseInt(stats.accounts_receivable).toLocaleString();
            document.getElementById('pending-invoices').textContent = stats.pending_invoices;
            document.getElementById('overdue-payments').textContent = stats.overdue_payments;
        }

        function updateDrawNumbers(rouletteState) {
            if (rouletteState) {
                document.getElementById('next-draw-number').textContent = '#' + rouletteState.next_draw;
                document.getElementById('last-draw-number').textContent = '#' + rouletteState.last_draw;
                document.getElementById('winning-number').textContent = rouletteState.winning_number;
            }
        }

        function updateWarningSystem(data) {
            const warningWidget = document.getElementById('warning-widget');
            const warningAmount = document.getElementById('warning-amount');
            const warningMessage = document.getElementById('warning-message');
            const riskFill = document.getElementById('risk-fill');
            const maxPayout = document.getElementById('max-payout');
            const cashStatus = document.getElementById('cash-status');
            const cashStatusText = document.getElementById('cash-status-text');

            const betData = data.straight_bet_data;
            const cashDrawer = data.cash_drawer;

            // Update warning amount
            warningAmount.textContent = '$' + parseFloat(betData.total_straight_bets).toLocaleString('en-US', {minimumFractionDigits: 2});

            // Update max payout
            maxPayout.textContent = parseFloat(betData.total_potential_payout).toLocaleString('en-US', {minimumFractionDigits: 2});

            // Update risk meter
            const riskPercentage = Math.min((betData.total_straight_bets / betData.warning_threshold) * 100, 100);
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

            // Update warning message
            if (betData.warning_level === 'exceeded') {
                warningMessage.textContent = `WARNING: Straight bet total ($${parseFloat(betData.total_straight_bets).toLocaleString('en-US', {minimumFractionDigits: 2})}) is EXCEEDING the $1,600 limit. Please ensure sufficient cash reserves are available to cover potential payouts.`;
            } else if (betData.warning_level === 'approaching') {
                warningMessage.textContent = `CAUTION: Straight bet total ($${parseFloat(betData.total_straight_bets).toLocaleString('en-US', {minimumFractionDigits: 2})}) is approaching the $1,600 limit. Monitor cash reserves closely.`;
            }

            // Update cash drawer status
            cashStatus.className = 'cash-status';
            if (cashDrawer.is_sufficient) {
                cashStatus.classList.add('cash-sufficient');
                cashStatusText.innerHTML = `✓ Sufficient ($${parseFloat(cashDrawer.current_balance).toLocaleString('en-US', {minimumFractionDigits: 2})})`;
            } else {
                cashStatus.classList.add('cash-insufficient');
                cashStatusText.innerHTML = `⚠ INSUFFICIENT! Need $${parseFloat(cashDrawer.shortage_amount).toLocaleString('en-US', {minimumFractionDigits: 2})} more`;
            }

            // Show/hide warning widget
            warningWidget.className = 'warning-widget';
            if (betData.is_warning) {
                warningWidget.classList.add(betData.warning_level);
            } else {
                warningWidget.classList.add('hidden');
            }
        }

        function updateLastUpdated() {
            document.getElementById('last-updated').textContent = new Date().toLocaleTimeString();
        }

        // Auto-refresh every 30 seconds
        setInterval(refreshDashboard, 30000);

        // Auto-refresh bet monitoring every 10 seconds (more frequent for real-time warnings)
        setInterval(refreshBetMonitoring, 10000);

        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            updateLastUpdated();
            refreshBetMonitoring(); // Initial bet monitoring load
            initializeBetDisplayContainer(); // Initialize floating bet display
            initializeCountdownTimer(); // Initialize countdown timer widget
        });

        // Action Button Functions
        function handleComplete() {
            if (confirm('Are you sure you want to complete the current draw?')) {
                // Add your complete draw logic here
                alert('Complete draw functionality would be implemented here');
                console.log('Complete draw action triggered');
            }
        }

        function handleReprint() {
            const slipNumber = prompt('Enter slip number to reprint:');
            if (slipNumber) {
                // Add your reprint logic here
                alert(`Reprinting slip #${slipNumber}`);
                console.log('Reprint slip action triggered for slip:', slipNumber);
            }
        }

        function handleCashout() {
            const slipNumber = prompt('Enter slip number for cashout:');
            if (slipNumber) {
                if (confirm(`Are you sure you want to cashout slip #${slipNumber}?`)) {
                    // Add your cashout logic here
                    alert(`Processing cashout for slip #${slipNumber}`);
                    console.log('Cashout action triggered for slip:', slipNumber);
                }
            }
        }

        function handleCancelSlip() {
            const slipNumber = prompt('Enter slip number to cancel:');
            if (slipNumber) {
                if (confirm(`Are you sure you want to cancel slip #${slipNumber}? This action cannot be undone.`)) {
                    // Add your cancel slip logic here
                    alert(`Cancelling slip #${slipNumber}`);
                    console.log('Cancel slip action triggered for slip:', slipNumber);
                }
            }
        }

        // ===== PERSISTENT POSITIONING SYSTEM FOR BET DISPLAY CONTAINER =====

        // Configuration
        const BET_DISPLAY_CONFIG = {
            storageKey: 'roulette_bet_display_position',
            defaultPosition: { x: null, y: 100 }, // null x means center
            minDistance: 50, // Minimum distance from viewport edges
            debugMode: false // Set to true to show position indicator
        };

        // Global variables for drag functionality
        let isDragging = false;
        let dragOffset = { x: 0, y: 0 };
        let betDisplayContainer = null;
        let positionIndicator = null;

        // Initialize the bet display container system
        function initializeBetDisplayContainer() {
            betDisplayContainer = document.getElementById('bet-display-container');
            positionIndicator = document.getElementById('position-indicator');

            if (!betDisplayContainer) {
                console.warn('Bet display container not found');
                return;
            }

            // Show debug indicator if enabled
            if (BET_DISPLAY_CONFIG.debugMode) {
                positionIndicator.style.display = 'block';
            }

            // Load saved position
            loadSavedPosition();

            // Initialize drag functionality
            initializeDragFunctionality();

            // Initialize control buttons
            initializeControlButtons();

            // Add window resize handler
            window.addEventListener('resize', handleWindowResize);

            console.log('Bet display container initialized with persistent positioning');
        }

        // Load saved position from localStorage
        function loadSavedPosition() {
            try {
                const savedPosition = localStorage.getItem(BET_DISPLAY_CONFIG.storageKey);

                if (savedPosition) {
                    const position = JSON.parse(savedPosition);

                    // Validate and apply saved position
                    if (isValidPosition(position)) {
                        applyPosition(position);
                        console.log('Restored saved position:', position);
                    } else {
                        console.log('Saved position invalid, using default');
                        applyDefaultPosition();
                    }
                } else {
                    console.log('No saved position found, using default');
                    applyDefaultPosition();
                }
            } catch (error) {
                console.error('Error loading saved position:', error);
                applyDefaultPosition();
            }
        }

        // Save current position to localStorage
        function saveCurrentPosition() {
            try {
                const rect = betDisplayContainer.getBoundingClientRect();
                const position = {
                    x: rect.left,
                    y: rect.top,
                    timestamp: Date.now()
                };

                localStorage.setItem(BET_DISPLAY_CONFIG.storageKey, JSON.stringify(position));
                console.log('Position saved:', position);

                // Update debug indicator
                updatePositionIndicator(position);
            } catch (error) {
                console.error('Error saving position:', error);
            }
        }

        // Apply position to the container
        function applyPosition(position) {
            if (!betDisplayContainer) return;

            // Remove centering transform and apply absolute positioning
            betDisplayContainer.style.left = position.x + 'px';
            betDisplayContainer.style.top = position.y + 'px';
            betDisplayContainer.style.transform = 'none';

            updatePositionIndicator(position);
        }

        // Apply default centered position
        function applyDefaultPosition() {
            if (!betDisplayContainer) return;

            const defaultPos = BET_DISPLAY_CONFIG.defaultPosition;

            if (defaultPos.x === null) {
                // Center horizontally
                betDisplayContainer.style.left = '50%';
                betDisplayContainer.style.transform = 'translateX(-50%)';
            } else {
                betDisplayContainer.style.left = defaultPos.x + 'px';
                betDisplayContainer.style.transform = 'none';
            }

            betDisplayContainer.style.top = defaultPos.y + 'px';

            updatePositionIndicator({ x: defaultPos.x || 'center', y: defaultPos.y });
        }

        // Validate if position is within viewport bounds
        function isValidPosition(position) {
            const viewport = {
                width: window.innerWidth,
                height: window.innerHeight
            };

            const containerWidth = 400; // From CSS
            const containerHeight = 200; // Estimated minimum height
            const minDist = BET_DISPLAY_CONFIG.minDistance;

            return (
                position.x >= minDist &&
                position.y >= minDist &&
                position.x + containerWidth <= viewport.width - minDist &&
                position.y + containerHeight <= viewport.height - minDist
            );
        }

        // Initialize drag functionality
        function initializeDragFunctionality() {
            const header = document.getElementById('bet-display-header');

            if (!header) return;

            // Mouse events
            header.addEventListener('mousedown', startDrag);
            document.addEventListener('mousemove', drag);
            document.addEventListener('mouseup', endDrag);

            // Touch events for mobile
            header.addEventListener('touchstart', startDrag, { passive: false });
            document.addEventListener('touchmove', drag, { passive: false });
            document.addEventListener('touchend', endDrag);
        }

        // Start dragging
        function startDrag(e) {
            e.preventDefault();
            isDragging = true;

            // Get mouse/touch position
            const clientX = e.clientX || (e.touches && e.touches[0].clientX);
            const clientY = e.clientY || (e.touches && e.touches[0].clientY);

            // Calculate offset from container's top-left corner
            const rect = betDisplayContainer.getBoundingClientRect();
            dragOffset.x = clientX - rect.left;
            dragOffset.y = clientY - rect.top;

            // Add dragging class for visual feedback
            betDisplayContainer.classList.add('dragging');

            // Prevent text selection during drag
            document.body.style.userSelect = 'none';

            console.log('Started dragging at:', { x: clientX, y: clientY });
        }

        // Handle dragging
        function drag(e) {
            if (!isDragging) return;

            e.preventDefault();

            // Get mouse/touch position
            const clientX = e.clientX || (e.touches && e.touches[0].clientX);
            const clientY = e.clientY || (e.touches && e.touches[0].clientY);

            // Calculate new position
            const newX = clientX - dragOffset.x;
            const newY = clientY - dragOffset.y;

            // Apply constraints to keep within viewport
            const constrainedPosition = constrainToViewport({ x: newX, y: newY });

            // Apply position immediately for smooth dragging
            betDisplayContainer.style.left = constrainedPosition.x + 'px';
            betDisplayContainer.style.top = constrainedPosition.y + 'px';
            betDisplayContainer.style.transform = 'none';

            // Update debug indicator
            updatePositionIndicator(constrainedPosition);
        }

        // End dragging
        function endDrag(e) {
            if (!isDragging) return;

            isDragging = false;

            // Remove dragging class
            betDisplayContainer.classList.remove('dragging');

            // Restore text selection
            document.body.style.userSelect = '';

            // Save the final position
            saveCurrentPosition();

            console.log('Ended dragging');
        }

        // Constrain position to viewport bounds
        function constrainToViewport(position) {
            const viewport = {
                width: window.innerWidth,
                height: window.innerHeight
            };

            const containerRect = betDisplayContainer.getBoundingClientRect();
            const containerWidth = containerRect.width || 400;
            const containerHeight = containerRect.height || 200;
            const minDist = BET_DISPLAY_CONFIG.minDistance;

            return {
                x: Math.max(minDist, Math.min(position.x, viewport.width - containerWidth - minDist)),
                y: Math.max(minDist, Math.min(position.y, viewport.height - containerHeight - minDist))
            };
        }

        // Handle window resize
        function handleWindowResize() {
            if (!betDisplayContainer) return;

            // Get current position
            const rect = betDisplayContainer.getBoundingClientRect();
            const currentPosition = { x: rect.left, y: rect.top };

            // Check if current position is still valid
            if (!isValidPosition(currentPosition)) {
                console.log('Position invalid after resize, constraining...');
                const constrainedPosition = constrainToViewport(currentPosition);
                applyPosition(constrainedPosition);
                saveCurrentPosition();
            }
        }

        // Initialize control buttons
        function initializeControlButtons() {
            const minimizeBtn = document.getElementById('minimize-btn');
            const closeBtn = document.getElementById('close-btn');

            if (minimizeBtn) {
                minimizeBtn.addEventListener('click', toggleMinimize);
            }

            if (closeBtn) {
                closeBtn.addEventListener('click', hideBetDisplay);
            }
        }

        // Toggle minimize state
        function toggleMinimize() {
            const content = document.getElementById('bet-display-content');
            const minimizeBtn = document.getElementById('minimize-btn');

            if (content.style.display === 'none') {
                content.style.display = 'block';
                minimizeBtn.innerHTML = '<i class="fas fa-minus"></i>';
                minimizeBtn.title = 'Minimize';
            } else {
                content.style.display = 'none';
                minimizeBtn.innerHTML = '<i class="fas fa-plus"></i>';
                minimizeBtn.title = 'Restore';
            }
        }

        // Hide bet display
        function hideBetDisplay() {
            betDisplayContainer.style.display = 'none';
        }

        // Show bet display
        function showBetDisplay() {
            betDisplayContainer.style.display = 'block';
        }

        // Update position indicator for debugging
        function updatePositionIndicator(position) {
            if (positionIndicator && BET_DISPLAY_CONFIG.debugMode) {
                const coords = document.getElementById('position-coords');
                if (coords) {
                    coords.textContent = `${Math.round(position.x)}, ${Math.round(position.y)}`;
                }
            }
        }

        // Public API functions for integration
        window.BetDisplayAPI = {
            show: showBetDisplay,
            hide: hideBetDisplay,
            updateContent: function(slipData) {
                updateBettingSlipPreview(slipData);
            },
            resetPosition: function() {
                localStorage.removeItem(BET_DISPLAY_CONFIG.storageKey);
                applyDefaultPosition();
            },
            enableDebug: function() {
                BET_DISPLAY_CONFIG.debugMode = true;
                if (positionIndicator) {
                    positionIndicator.style.display = 'block';
                }
            },
            disableDebug: function() {
                BET_DISPLAY_CONFIG.debugMode = false;
                if (positionIndicator) {
                    positionIndicator.style.display = 'none';
                }
            }
        };

        // Update betting slip preview content
        function updateBettingSlipPreview(slipData) {
            const preview = document.getElementById('betting-slip-preview');

            if (!slipData || !slipData.bets || slipData.bets.length === 0) {
                preview.innerHTML = `
                    <i class="fas fa-ticket-alt" style="font-size: 2rem; margin-bottom: 10px; opacity: 0.5;"></i>
                    <p>No betting slip selected</p>
                    <p><small>Click "Print Betting Slip" to preview a slip here</small></p>
                `;
                preview.className = 'betting-slip-preview';
                return;
            }

            preview.className = 'betting-slip-preview has-content';

            let betsHtml = '';
            let totalAmount = 0;

            slipData.bets.forEach(bet => {
                betsHtml += `
                    <div class="bet-item">
                        <span>${bet.type}: ${bet.selection}</span>
                        <span>$${bet.amount.toFixed(2)}</span>
                    </div>
                `;
                totalAmount += bet.amount;
            });

            preview.innerHTML = `
                <div class="slip-header">
                    <h6>ROULETTE BETTING SLIP</h6>
                    <small>${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}</small><br>
                    <small>Draw #: ${slipData.drawNumber || 'TBD'}</small>
                </div>
                <div class="slip-details">
                    ${betsHtml}
                </div>
                <div class="slip-total">
                    Total Stakes: $${totalAmount.toFixed(2)}<br>
                    <small>Slip #: ${slipData.slipNumber || 'Generating...'}</small>
                </div>
                <div class="slip-actions">
                    <button class="slip-btn primary" onclick="handlePrintSlip()">
                        <i class="fas fa-print"></i> Print Slip
                    </button>
                    <button class="slip-btn secondary" onclick="hideBetDisplay()">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            `;
        }

        // Handle print slip action
        function handlePrintSlip() {
            alert('Print slip functionality would be implemented here');
            console.log('Print slip action triggered');
        }

        // Add a button to the action buttons section to show the bet display for testing
        function addTestButton() {
            const actionButtons = document.querySelector('.action-buttons');
            if (actionButtons) {
                const testButton = document.createElement('button');
                testButton.className = 'action-btn btn-complete';
                testButton.innerHTML = '<i class="fas fa-receipt"></i> Show Bet Preview';
                testButton.onclick = function() {
                    showBetDisplay();

                    // Show sample betting slip data
                    const sampleSlipData = {
                        slipNumber: 'BS' + Math.floor(Math.random() * 1000).toString().padStart(3, '0'),
                        drawNumber: <?php echo $roulette_state['next_draw']; ?>,
                        bets: [
                            { type: 'Straight Up', selection: '7', amount: 50.00 },
                            { type: 'Straight Up', selection: '23', amount: 100.00 },
                            { type: 'Corner', selection: '1,2,4,5', amount: 25.00 }
                        ]
                    };

                    updateBettingSlipPreview(sampleSlipData);
                };
                actionButtons.appendChild(testButton);
            }
        }

        // Add test button after DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(addTestButton, 100); // Small delay to ensure other elements are ready
        });

        // ===== FLOATING COUNTDOWN TIMER WIDGET SYSTEM =====

        // Configuration for countdown timer
        const COUNTDOWN_CONFIG = {
            storageKey: 'roulette_countdown_timer_position',
            defaultPosition: { x: null, y: 20 }, // null x means right-aligned
            minDistance: 20,
            warningThreshold: 60, // seconds
            criticalThreshold: 30, // seconds
            updateInterval: 1000, // 1 second
            debugMode: false
        };

        // Global variables for countdown timer
        let countdownTimerWidget = null;
        let countdownInterval = null;
        let currentCountdownTime = 180; // Default 3 minutes
        let isTimerDragging = false;
        let timerDragOffset = { x: 0, y: 0 };

        // Initialize countdown timer widget
        function initializeCountdownTimer() {
            countdownTimerWidget = document.getElementById('countdown-timer-widget');

            if (!countdownTimerWidget) {
                console.warn('Countdown timer widget not found');
                return;
            }

            // Load saved position
            loadTimerSavedPosition();

            // Initialize drag functionality
            initializeTimerDragFunctionality();

            // Initialize control buttons
            initializeTimerControlButtons();

            // Start countdown
            startCountdownTimer();

            // Add window resize handler
            window.addEventListener('resize', handleTimerWindowResize);

            console.log('Countdown timer widget initialized');
        }

        // Load saved position for timer
        function loadTimerSavedPosition() {
            try {
                const savedPosition = localStorage.getItem(COUNTDOWN_CONFIG.storageKey);

                if (savedPosition) {
                    const position = JSON.parse(savedPosition);

                    if (isValidTimerPosition(position)) {
                        applyTimerPosition(position);
                        console.log('Restored timer position:', position);
                    } else {
                        console.log('Saved timer position invalid, using default');
                        applyDefaultTimerPosition();
                    }
                } else {
                    console.log('No saved timer position found, using default');
                    applyDefaultTimerPosition();
                }
            } catch (error) {
                console.error('Error loading timer position:', error);
                applyDefaultTimerPosition();
            }
        }

        // Save current timer position
        function saveTimerCurrentPosition() {
            try {
                const rect = countdownTimerWidget.getBoundingClientRect();
                const position = {
                    x: rect.left,
                    y: rect.top,
                    timestamp: Date.now()
                };

                localStorage.setItem(COUNTDOWN_CONFIG.storageKey, JSON.stringify(position));
                console.log('Timer position saved:', position);
            } catch (error) {
                console.error('Error saving timer position:', error);
            }
        }

        // Apply position to timer
        function applyTimerPosition(position) {
            if (!countdownTimerWidget) return;

            countdownTimerWidget.style.left = position.x + 'px';
            countdownTimerWidget.style.top = position.y + 'px';
            countdownTimerWidget.style.right = 'auto';
        }

        // Apply default timer position
        function applyDefaultTimerPosition() {
            if (!countdownTimerWidget) return;

            const defaultPos = COUNTDOWN_CONFIG.defaultPosition;

            if (defaultPos.x === null) {
                // Right-aligned
                countdownTimerWidget.style.right = '20px';
                countdownTimerWidget.style.left = 'auto';
            } else {
                countdownTimerWidget.style.left = defaultPos.x + 'px';
                countdownTimerWidget.style.right = 'auto';
            }

            countdownTimerWidget.style.top = defaultPos.y + 'px';
        }

        // Validate timer position
        function isValidTimerPosition(position) {
            const viewport = {
                width: window.innerWidth,
                height: window.innerHeight
            };

            const timerWidth = 280;
            const timerHeight = 150;
            const minDist = COUNTDOWN_CONFIG.minDistance;

            return (
                position.x >= minDist &&
                position.y >= minDist &&
                position.x + timerWidth <= viewport.width - minDist &&
                position.y + timerHeight <= viewport.height - minDist
            );
        }

        // Initialize timer drag functionality
        function initializeTimerDragFunctionality() {
            const header = document.getElementById('timer-header');

            if (!header) return;

            header.addEventListener('mousedown', startTimerDrag);
            document.addEventListener('mousemove', dragTimer);
            document.addEventListener('mouseup', endTimerDrag);
            header.addEventListener('touchstart', startTimerDrag, { passive: false });
            document.addEventListener('touchmove', dragTimer, { passive: false });
            document.addEventListener('touchend', endTimerDrag);
        }

        // Start timer dragging
        function startTimerDrag(e) {
            e.preventDefault();
            isTimerDragging = true;

            const clientX = e.clientX || (e.touches && e.touches[0].clientX);
            const clientY = e.clientY || (e.touches && e.touches[0].clientY);

            const rect = countdownTimerWidget.getBoundingClientRect();
            timerDragOffset.x = clientX - rect.left;
            timerDragOffset.y = clientY - rect.top;

            countdownTimerWidget.classList.add('dragging');
            document.body.style.userSelect = 'none';
        }

        // Handle timer dragging
        function dragTimer(e) {
            if (!isTimerDragging) return;

            e.preventDefault();

            const clientX = e.clientX || (e.touches && e.touches[0].clientX);
            const clientY = e.clientY || (e.touches && e.touches[0].clientY);

            const newX = clientX - timerDragOffset.x;
            const newY = clientY - timerDragOffset.y;

            const constrainedPosition = constrainTimerToViewport({ x: newX, y: newY });

            countdownTimerWidget.style.left = constrainedPosition.x + 'px';
            countdownTimerWidget.style.top = constrainedPosition.y + 'px';
            countdownTimerWidget.style.right = 'auto';
        }

        // End timer dragging
        function endTimerDrag(e) {
            if (!isTimerDragging) return;

            isTimerDragging = false;
            countdownTimerWidget.classList.remove('dragging');
            document.body.style.userSelect = '';
            saveTimerCurrentPosition();
        }

        // Constrain timer to viewport
        function constrainTimerToViewport(position) {
            const viewport = {
                width: window.innerWidth,
                height: window.innerHeight
            };

            const timerRect = countdownTimerWidget.getBoundingClientRect();
            const timerWidth = timerRect.width || 280;
            const timerHeight = timerRect.height || 150;
            const minDist = COUNTDOWN_CONFIG.minDistance;

            return {
                x: Math.max(minDist, Math.min(position.x, viewport.width - timerWidth - minDist)),
                y: Math.max(minDist, Math.min(position.y, viewport.height - timerHeight - minDist))
            };
        }

        // Handle timer window resize
        function handleTimerWindowResize() {
            if (!countdownTimerWidget) return;

            const rect = countdownTimerWidget.getBoundingClientRect();
            const currentPosition = { x: rect.left, y: rect.top };

            if (!isValidTimerPosition(currentPosition)) {
                const constrainedPosition = constrainTimerToViewport(currentPosition);
                applyTimerPosition(constrainedPosition);
                saveTimerCurrentPosition();
            }
        }

        // Initialize timer control buttons
        function initializeTimerControlButtons() {
            const minimizeBtn = document.getElementById('timer-minimize-btn');
            const closeBtn = document.getElementById('timer-close-btn');

            if (minimizeBtn) {
                minimizeBtn.addEventListener('click', toggleTimerMinimize);
            }

            if (closeBtn) {
                closeBtn.addEventListener('click', hideCountdownTimer);
            }
        }

        // Toggle timer minimize
        function toggleTimerMinimize() {
            const content = document.getElementById('timer-content');
            const minimizeBtn = document.getElementById('timer-minimize-btn');

            if (countdownTimerWidget.classList.contains('minimized')) {
                countdownTimerWidget.classList.remove('minimized');
                minimizeBtn.innerHTML = '<i class="fas fa-minus"></i>';
                minimizeBtn.title = 'Minimize';
            } else {
                countdownTimerWidget.classList.add('minimized');
                minimizeBtn.innerHTML = '<i class="fas fa-plus"></i>';
                minimizeBtn.title = 'Restore';
            }
        }

        // Hide countdown timer
        function hideCountdownTimer() {
            countdownTimerWidget.style.display = 'none';
            if (countdownInterval) {
                clearInterval(countdownInterval);
            }
        }

        // Show countdown timer
        function showCountdownTimer() {
            countdownTimerWidget.style.display = 'block';
            if (!countdownInterval) {
                startCountdownTimer();
            }
        }

        // Start countdown timer
        function startCountdownTimer() {
            // Get initial countdown time from PHP
            currentCountdownTime = <?php echo $roulette_state['countdown_time']; ?>;

            // Update display immediately
            updateCountdownDisplay();

            // Clear any existing interval
            if (countdownInterval) {
                clearInterval(countdownInterval);
            }

            // Start new interval
            countdownInterval = setInterval(function() {
                currentCountdownTime--;
                updateCountdownDisplay();

                // Handle timer expiration
                if (currentCountdownTime <= 0) {
                    handleTimerExpiration();
                }
            }, COUNTDOWN_CONFIG.updateInterval);

            console.log('Countdown timer started with', currentCountdownTime, 'seconds');
        }

        // Update countdown display
        function updateCountdownDisplay() {
            const display = document.getElementById('countdown-display');
            const statusIndicator = document.getElementById('timer-status-indicator');
            const statusText = document.getElementById('timer-status-text');

            if (!display) return;

            // Format time as MM:SS
            const minutes = Math.floor(Math.max(0, currentCountdownTime) / 60);
            const seconds = Math.max(0, currentCountdownTime) % 60;
            const timeString = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

            display.textContent = timeString;

            // Update visual state based on time remaining
            countdownTimerWidget.classList.remove('warning', 'critical');
            display.classList.remove('warning', 'critical');
            statusIndicator.classList.remove('warning', 'critical');

            if (currentCountdownTime <= 0) {
                statusText.textContent = 'Draw Completed';
                display.textContent = '00:00';
            } else if (currentCountdownTime <= COUNTDOWN_CONFIG.criticalThreshold) {
                countdownTimerWidget.classList.add('critical');
                display.classList.add('critical');
                statusIndicator.classList.add('critical');
                statusText.textContent = 'Critical';
            } else if (currentCountdownTime <= COUNTDOWN_CONFIG.warningThreshold) {
                countdownTimerWidget.classList.add('warning');
                display.classList.add('warning');
                statusIndicator.classList.add('warning');
                statusText.textContent = 'Warning';
            } else {
                statusText.textContent = 'Active';
            }
        }

        // Handle timer expiration
        function handleTimerExpiration() {
            console.log('Countdown timer expired');

            // Clear the interval
            if (countdownInterval) {
                clearInterval(countdownInterval);
                countdownInterval = null;
            }

            // Update display to show completion
            updateCountdownDisplay();

            // Refresh data to get new countdown time
            setTimeout(function() {
                refreshCountdownData();
            }, 2000); // Wait 2 seconds before refreshing
        }

        // Refresh countdown data from server
        function refreshCountdownData() {
            fetch('api/accounting_dashboard_data.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.roulette_state) {
                        const newCountdownTime = data.roulette_state.countdown_time;
                        const newDrawNumber = data.roulette_state.next_draw;

                        // Update draw number
                        const drawNumberElement = document.getElementById('timer-draw-number');
                        if (drawNumberElement) {
                            drawNumberElement.textContent = newDrawNumber;
                        }

                        // Restart timer with new countdown time
                        if (newCountdownTime > 0) {
                            currentCountdownTime = newCountdownTime;
                            startCountdownTimer();
                            console.log('Countdown refreshed with new time:', newCountdownTime);
                        }
                    }
                })
                .catch(error => {
                    console.error('Error refreshing countdown data:', error);
                    // Retry after 5 seconds if there's an error
                    setTimeout(refreshCountdownData, 5000);
                });
        }

        // Update countdown timer with new data from dashboard refresh
        function updateCountdownTimer(rouletteState) {
            if (rouletteState && rouletteState.countdown_time !== undefined) {
                const newCountdownTime = rouletteState.countdown_time;
                const newDrawNumber = rouletteState.next_draw;

                // Update draw number
                const drawNumberElement = document.getElementById('timer-draw-number');
                if (drawNumberElement) {
                    drawNumberElement.textContent = newDrawNumber;
                }

                // Only update countdown if it's significantly different (to avoid sync issues)
                if (Math.abs(currentCountdownTime - newCountdownTime) > 5) {
                    currentCountdownTime = newCountdownTime;
                    console.log('Countdown time synchronized:', newCountdownTime);
                }
            }
        }

        // Public API for countdown timer
        window.CountdownTimerAPI = {
            show: showCountdownTimer,
            hide: hideCountdownTimer,
            reset: function() {
                localStorage.removeItem(COUNTDOWN_CONFIG.storageKey);
                applyDefaultTimerPosition();
            },
            updateTime: function(seconds) {
                currentCountdownTime = seconds;
                updateCountdownDisplay();
            },
            getCurrentTime: function() {
                return currentCountdownTime;
            }
        };

        // Integrate countdown timer updates with existing dashboard refresh
        const originalUpdateDrawNumbers = updateDrawNumbers;
        updateDrawNumbers = function(rouletteState) {
            originalUpdateDrawNumbers(rouletteState);
            updateCountdownTimer(rouletteState);
        };
    </script>
</body>
</html>
