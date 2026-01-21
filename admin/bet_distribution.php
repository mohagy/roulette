<?php
// Start session
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Redirect to login page if not logged in or not an admin
    header('Location: ../login.php');
    exit;
}

// Get current page filename for sidebar active state
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#4e73df">
    <title>Bet Distribution & Draw Control - Roulette Admin</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&family=Orbitron:wght@400;500;700;900&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- ApexCharts -->
    <link href="https://cdn.jsdelivr.net/npm/apexcharts@3.35.0/dist/apexcharts.min.css" rel="stylesheet">
    <!-- Three.js for 3D animations -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <!-- GSAP for smooth animations -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.9.1/gsap.min.js"></script>
    <!-- Admin CSS -->
    <link rel="stylesheet" href="css/admin.css">
    <!-- Custom CSS for Bet Distribution -->
    <style>
        /* Bet Distribution Styles */
        .bet-distribution-container {
            background-color: white;
            border-radius: 0.35rem;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
        }

        .chart-container {
            min-height: 400px;
        }

        .bet-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
            gap: 10px;
            margin-top: 20px;
        }

        .bet-info-item {
            background-color: #f8f9fc;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .bet-info-item.has-bets {
            border-left: 3px solid var(--success-color);
        }

        .bet-info-item.no-bets {
            border-left: 3px solid var(--secondary-color);
            opacity: 0.7;
        }

        .number-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            font-weight: bold;
            margin-bottom: 8px;
            color: white;
        }

        .number-badge.red {
            background-color: var(--danger-color);
        }

        .number-badge.black {
            background-color: var(--dark-color);
        }

        .number-badge.green {
            background-color: var(--success-color);
        }

        .bet-count {
            font-size: 0.9rem;
            color: var(--secondary-color);
        }

        .payout-amount {
            font-weight: bold;
            color: var(--success-color);
            margin-top: 5px;
        }

        .tab-container {
            margin-bottom: 20px;
        }

        .view-tab {
            padding: 8px 15px;
            background-color: #f8f9fc;
            border: none;
            border-radius: 4px;
            margin-right: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .view-tab.active {
            background-color: var(--primary-color);
            color: white;
        }

        .view-container {
            display: none;
        }

        .view-container.active {
            display: block;
        }

        /* Draw Control Styles */
        .draw-control-section {
            margin-top: 30px;
        }

        .number-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            color: white;
        }

        .red { background-color: var(--danger-color); }
        .black { background-color: var(--dark-color); }
        .green { background-color: var(--success-color); }

        .roll-history {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .roll-item {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            font-weight: bold;
            color: white;
        }

        /* Recommended Numbers Styles */
        .recommended-numbers-container {
            margin-top: 15px;
            padding: 15px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
        }

        .recommended-numbers-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .recommended-numbers-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary-color);
            margin: 0;
        }

        .recommended-numbers-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .recommended-tab {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            background-color: #f8f9fc;
            border: 1px solid #e3e6f0;
            transition: all 0.2s;
        }

        .recommended-tab.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .recommended-numbers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(70px, 1fr));
            gap: 10px;
        }

        .recommended-number-item {
            position: relative;
            cursor: pointer;
            transition: all 0.2s;
            transform-style: preserve-3d;
            perspective: 1000px;
        }

        .recommended-number-item:hover {
            transform: translateY(-3px);
        }

        .recommended-number-item:active {
            transform: translateY(1px);
        }

        .recommended-number-badge {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: bold;
            color: white;
            margin: 0 auto 5px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            position: relative;
            transition: transform 0.3s;
        }

        .recommended-number-item:hover .recommended-number-badge {
            transform: rotateY(10deg);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.3);
        }

        .recommended-number-info {
            text-align: center;
            font-size: 0.8rem;
        }

        .recommended-number-label {
            font-weight: 600;
            color: #555;
            margin-bottom: 2px;
        }

        .recommended-number-value {
            font-weight: 700;
            color: var(--success-color);
        }

        .recommended-number-reason {
            font-size: 0.7rem;
            color: #777;
            margin-top: 3px;
        }

        .no-recommendations {
            text-align: center;
            padding: 20px;
            color: #777;
            font-style: italic;
        }

        /* Smart Number Management Styles */
        .number-circle-sm {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: bold;
            color: white;
            margin: 0 5px;
        }

        .pattern-chart {
            background: #f8f9fc;
            border-radius: 8px;
            padding: 15px;
            min-height: 150px;
            border: 1px solid #e3e6f0;
        }

        .preset-schedule-container {
            border: 1px solid #e3e6f0;
            border-radius: 8px;
            padding: 10px;
            background: #f8f9fc;
            margin-top: 10px;
        }

        .preset-schedule-container table {
            font-size: 0.85rem;
            margin-bottom: 0;
        }

        .preset-schedule-container th {
            font-size: 0.8rem;
            font-weight: 600;
            background: #e3e6f0;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .preset-schedule-container td {
            vertical-align: middle;
            padding: 8px;
        }

        .preset-schedule-container .table-responsive {
            max-height: 300px;
            overflow-y: auto;
        }

        .pattern-item {
            display: inline-block;
            margin: 5px;
            padding: 8px 12px;
            background: white;
            border-radius: 4px;
            border-left: 3px solid #4e73df;
            font-size: 0.9rem;
        }

        .pattern-item.highlight {
            background: #fff3cd;
            border-left-color: #ffc107;
        }

        .legend-container {
            display: flex;
            gap: 15px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9rem;
        }

        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 2px;
        }

        .no-bets-legend {
            background-color: #ccc;
        }

        .has-bets-legend {
            background-color: var(--success-color);
        }

        .auto-refresh-status {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.8rem;
            color: var(--secondary-color);
        }

        .auto-refresh-status i {
            color: var(--success-color);
        }

        /* Upcoming Draws Overview Styles */
        .upcoming-draws-overview .table th {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--primary-color);
            border-bottom: 2px solid #e3e6f0;
        }

        .upcoming-draws-overview .table td {
            font-size: 0.9rem;
            vertical-align: middle;
        }

        .upcoming-draws-overview .table tbody tr:hover {
            background-color: rgba(78, 115, 223, 0.05);
        }

        .draw-row.selected {
            background-color: rgba(78, 115, 223, 0.1) !important;
            border-left: 3px solid var(--primary-color);
        }

        .draw-row.current {
            background-color: rgba(28, 200, 138, 0.1) !important;
            border-left: 3px solid var(--success-color);
        }

        /* Draw Selection Tabs Styles */
        .draw-tabs-container {
            overflow-x: auto;
            scrollbar-width: thin;
            scrollbar-color: #ccc transparent;
        }

        .draw-tabs-container::-webkit-scrollbar {
            height: 6px;
        }

        .draw-tabs-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .draw-tabs-container::-webkit-scrollbar-thumb {
            background: #ccc;
            border-radius: 3px;
        }

        .draw-tabs {
            display: flex;
            gap: 10px;
            padding: 5px 0;
            min-width: max-content;
        }

        .draw-tab {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 10px 15px;
            border-radius: 8px;
            background-color: #f8f9fc;
            border: 2px solid #e3e6f0;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 80px;
            text-align: center;
        }

        .draw-tab:hover {
            background-color: #e3e6f0;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .draw-tab.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(78, 115, 223, 0.3);
        }

        .draw-tab.current {
            background-color: var(--success-color);
            color: white;
            border-color: var(--success-color);
        }

        .draw-tab.current.active {
            background-color: var(--success-color);
            border-color: var(--success-color);
            box-shadow: 0 4px 12px rgba(28, 200, 138, 0.3);
        }

        .draw-tab.loading {
            background-color: #f8f9fc;
            color: #6c757d;
            cursor: not-allowed;
        }

        .draw-tab-number {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .draw-tab-time {
            font-size: 0.75rem;
            opacity: 0.8;
            margin-bottom: 2px;
        }

        .draw-tab-stats {
            font-size: 0.7rem;
            opacity: 0.9;
        }

        .draw-navigation button {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .draw-navigation button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* 3D Timer Styles */
        .timer-3d-container {
            position: relative;
            width: 100%;
            height: 200px;
            margin: 20px auto;
            perspective: 1000px;
            overflow: hidden;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            background: linear-gradient(135deg, #1a2a6c, #b21f1f, #fdbb2d);
            background-size: 300% 300%;
            animation: gradientBG 15s ease infinite;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        @keyframes pulse {
            0% { transform: translate(-50%, -50%) scale(1); text-shadow: 0 0 10px rgba(255, 82, 82, 0.7), 0 0 20px rgba(255, 82, 82, 0.5), 0 0 30px rgba(255, 82, 82, 0.3); }
            100% { transform: translate(-50%, -50%) scale(1.1); text-shadow: 0 0 15px rgba(255, 82, 82, 0.9), 0 0 30px rgba(255, 82, 82, 0.7), 0 0 45px rgba(255, 82, 82, 0.5); }
        }

        .timer-3d-scene {
            width: 100%;
            height: 100%;
        }

        .timer-3d-display {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 3.5rem;
            font-weight: 700;
            color: white;
            text-shadow: 0 0 10px rgba(255, 255, 255, 0.7),
                         0 0 20px rgba(255, 255, 255, 0.5),
                         0 0 30px rgba(255, 255, 255, 0.3);
            z-index: 10;
            font-family: 'Orbitron', sans-serif;
            letter-spacing: 2px;
        }

        .timer-sync-indicator {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: rgba(28, 200, 138, 0.8);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
            z-index: 10;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            animation: fadeInOut 2s infinite alternate;
        }

        @keyframes fadeInOut {
            0% { opacity: 0.7; }
            100% { opacity: 1; }
        }

        .timer-3d-controls {
            position: absolute;
            bottom: 15px;
            left: 0;
            right: 0;
            display: flex;
            justify-content: center;
            gap: 10px;
            z-index: 10;
        }

        .timer-3d-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 30px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(5px);
            background-color: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .timer-3d-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
            background-color: rgba(255, 255, 255, 0.3);
        }

        .timer-3d-btn:active {
            transform: translateY(1px);
        }

        .timer-3d-btn.start {
            background-color: rgba(46, 204, 113, 0.7);
        }

        .timer-3d-btn.pause {
            background-color: rgba(52, 73, 94, 0.7);
        }

        .timer-3d-btn.reset {
            background-color: rgba(231, 76, 60, 0.7);
        }

        .timer-3d-settings {
            position: relative;
            margin-top: 15px;
            padding: 15px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .timer-3d-settings label {
            color: white;
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
        }

        .timer-3d-settings input {
            width: 100%;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            font-size: 1rem;
            margin-bottom: 10px;
        }

        .timer-3d-settings button {
            width: 100%;
            padding: 10px;
            border-radius: 5px;
            border: none;
            background-color: rgba(52, 152, 219, 0.7);
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .timer-3d-settings button:hover {
            background-color: rgba(52, 152, 219, 0.9);
        }

        .particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 5;
            pointer-events: none;
        }

        /* Timer warning styles */
        .timer-warning {
            color: #ff5252 !important;
            animation: pulse 0.5s infinite alternate !important;
        }

        /* Forced Number Checker Styles */
        .forced-number-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .forced-number-status {
            text-align: center;
            font-size: 0.9rem;
            color: var(--secondary-color);
            margin-bottom: 15px;
        }

        .forced-number-display {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .forced-number-badge-container {
            position: relative;
            margin-bottom: 10px;
        }

        .forced-number-badge {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: bold;
            color: white;
            background-color: #6c757d;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            position: relative;
            z-index: 2;
            transition: all 0.3s ease;
        }

        .forced-number-badge.red {
            background-color: var(--danger-color);
        }

        .forced-number-badge.black {
            background-color: var(--dark-color);
        }

        .forced-number-badge.green {
            background-color: var(--success-color);
        }

        .forced-number-glow {
            position: absolute;
            top: -5px;
            left: -5px;
            right: -5px;
            bottom: -5px;
            border-radius: 50%;
            background: transparent;
            z-index: 1;
            opacity: 0;
            transition: all 0.3s ease;
        }

        .forced-number-badge.has-forced .forced-number-glow {
            box-shadow: 0 0 20px 5px rgba(255, 215, 0, 0.7);
            animation: pulse-glow 2s infinite alternate;
            opacity: 1;
        }

        @keyframes pulse-glow {
            0% { box-shadow: 0 0 20px 5px rgba(255, 215, 0, 0.5); }
            100% { box-shadow: 0 0 30px 10px rgba(255, 215, 0, 0.8); }
        }

        .forced-number-info {
            text-align: center;
            font-size: 0.9rem;
        }

        .forced-number-draw {
            font-weight: 600;
            color: var(--primary-color);
        }

        .forced-number-details {
            width: 100%;
            padding: 10px;
            background-color: #f8f9fc;
            border-radius: 8px;
            margin-top: 15px;
        }

        .forced-number-detail-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            color: #555;
            margin-bottom: 5px;
        }

        .forced-number-detail-item i {
            color: var(--primary-color);
        }

        .forced-number-badge.checking {
            animation: spin 1s infinite linear;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .forced-number-badge.found {
            animation: bounce 0.5s ease;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-20px); }
            60% { transform: translateY(-10px); }
        }

        /* Mobile-friendly styles */
        @media (max-width: 768px) {
            /* General layout adjustments */
            .content-wrapper {
                padding: 0.75rem;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .breadcrumb {
                font-size: 0.75rem;
            }

            /* Action buttons */
            .mb-4 {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
                margin-bottom: 0.75rem !important;
            }

            .btn {
                padding: 0.5rem 0.75rem;
                font-size: 0.875rem;
                flex: 1 1 auto;
                white-space: nowrap;
                min-width: 0;
            }

            /* Tab container */
            .tab-container {
                display: flex;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                margin-bottom: 0.75rem;
                padding-bottom: 0.25rem;
            }

            .view-tab {
                flex: 0 0 auto;
                white-space: nowrap;
                font-size: 0.875rem;
                padding: 0.5rem 0.75rem;
            }

            /* Bet distribution grid */
            .bet-info-grid {
                grid-template-columns: repeat(auto-fill, minmax(60px, 1fr));
                gap: 6px;
            }

            .bet-info-item {
                padding: 6px;
                border-radius: 6px;
            }

            .number-badge {
                width: 28px;
                height: 28px;
                font-size: 0.85rem;
                margin-bottom: 4px;
            }

            .bet-count, .payout-amount {
                font-size: 0.7rem;
                line-height: 1.2;
            }

            /* Draw control section */
            .number-circle {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }

            .roll-item {
                width: 32px;
                height: 32px;
                font-size: 0.8rem;
            }

            /* 3D Timer */
            .timer-3d-container {
                height: 180px;
            }

            .timer-3d-display {
                font-size: 2.5rem;
            }

            .timer-3d-controls {
                bottom: 10px;
                gap: 5px;
            }

            .timer-3d-btn {
                padding: 6px 10px;
                font-size: 0.8rem;
            }

            /* Recommended numbers */
            .recommended-numbers-container {
                padding: 10px;
            }

            .recommended-numbers-grid {
                grid-template-columns: repeat(auto-fill, minmax(55px, 1fr));
                gap: 8px;
            }

            .recommended-number-badge {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }

            .recommended-number-info {
                font-size: 0.7rem;
            }

            .recommended-number-reason {
                font-size: 0.6rem;
            }

            /* Forced number checker */
            .forced-number-badge {
                width: 60px;
                height: 60px;
                font-size: 2rem;
            }

            /* Sticky controls for mobile */
            .mobile-sticky-controls {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(10px);
                padding: 10px;
                box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
                z-index: 1000;
                display: flex;
                justify-content: space-around;
                border-top: 1px solid rgba(0, 0, 0, 0.1);
            }

            .mobile-sticky-btn {
                display: flex;
                flex-direction: column;
                align-items: center;
                font-size: 0.7rem;
                color: var(--secondary-color);
                padding: 5px;
            }

            .mobile-sticky-btn i {
                font-size: 1.2rem;
                margin-bottom: 3px;
                color: var(--primary-color);
            }

            /* Add padding to bottom of page to account for sticky controls */
            body {
                padding-bottom: 70px;
            }

            /* Collapsible sections for mobile */
            .mobile-collapsible-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px 15px;
                background-color: var(--primary-color);
                color: white;
                border-radius: 8px 8px 0 0;
                cursor: pointer;
            }

            .mobile-collapsible-content {
                max-height: 0;
                overflow: hidden;
                transition: max-height 0.3s ease;
            }

            .mobile-collapsible-content.expanded {
                max-height: 1000px;
            }

            /* Floating action button for mobile */
            .mobile-fab {
                position: fixed;
                bottom: 80px;
                right: 20px;
                width: 56px;
                height: 56px;
                border-radius: 50%;
                background-color: var(--primary-color);
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
                z-index: 1000;
                font-size: 1.5rem;
            }

            /* Touch-friendly form controls */
            input[type="number"] {
                height: 44px;
                font-size: 16px; /* Prevents iOS zoom on focus */
            }

            /* Improved table responsiveness */
            .table-sm td, .table-sm th {
                padding: 0.5rem;
                font-size: 0.8rem;
            }
        }

        /* Small phones */
        @media (max-width: 375px) {
            .bet-info-grid {
                grid-template-columns: repeat(auto-fill, minmax(50px, 1fr));
                gap: 4px;
            }

            .number-badge {
                width: 24px;
                height: 24px;
                font-size: 0.75rem;
            }

            .recommended-numbers-grid {
                grid-template-columns: repeat(auto-fill, minmax(45px, 1fr));
            }

            .recommended-number-badge {
                width: 32px;
                height: 32px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <nav class="top-navbar">
            <div class="navbar-search">
                <input type="text" class="search-input" placeholder="Search...">
            </div>

            <ul class="navbar-nav">
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-bell"></i>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-envelope"></i>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Page Header -->
    <div class="page-header">
        <h1 class="page-title">Bet Distribution & Draw Control</h1>
        <div class="breadcrumb">
            <div class="breadcrumb-item"><a href="index.php">Admin</a></div>
            <div class="breadcrumb-item active">Bet Distribution & Draw Control</div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="mb-4">
        <button id="refreshButton" class="btn btn-primary">
            <i class="fas fa-sync-alt"></i> Refresh Data
        </button>
        <button id="toggleDrawControlButton" class="btn btn-info ms-2">
            <i class="fas fa-sliders-h"></i> <span id="toggleDrawControlText">Show Draw Control</span>
        </button>
    </div>

    <!-- Tab Container -->
    <div class="tab-container">
        <button class="view-tab active" data-view="chart">Chart View</button>
        <button class="view-tab" data-view="grid">Grid View</button>
    </div>

    <div class="row">
        <!-- LEFT COLUMN: Main Content -->
        <div class="col-lg-8">
            <!-- Bet Distribution Container -->
            <div class="bet-distribution-container">
                <div class="auto-refresh-status mb-3">
                    <i class="fas fa-circle-notch fa-spin"></i>
                    <span>Auto-refreshing data for 10 upcoming draws every 15 seconds</span>
                </div>

                <!-- Draw Selection Tabs -->
                <div class="draw-selection-tabs mb-3">
                    <div class="card shadow">
                        <div class="card-body p-2">
                            <div class="d-flex align-items-center justify-content-between flex-wrap">
                                <div class="draw-tabs-container flex-grow-1">
                                    <div class="draw-tabs" id="drawTabs">
                                        <!-- Draw tabs will be populated here -->
                                        <div class="draw-tab loading">
                                            <div class="spinner-border spinner-border-sm" role="status"></div>
                                            <span>Loading...</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="draw-navigation ms-3">
                                    <button class="btn btn-sm btn-outline-secondary" id="prevDraw" disabled>
                                        <i class="fas fa-chevron-left"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary" id="nextDraw" disabled>
                                        <i class="fas fa-chevron-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="draw-info-header">
                    <h2>Draw: <span id="upcomingDrawNumber">Loading...</span> <span id="drawStatus"
                            class="badge bg-primary">Current</span></h2>
                    <div class="legend-container">
                        <div class="legend-item">
                            <div class="legend-color has-bets-legend"></div>
                            <span>Has Bets</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color no-bets-legend"></div>
                            <span>No Bets</span>
                        </div>
                    </div>
                </div>

                <div class="view-container active" id="chartView">
                    <div id="chartContainer" class="chart-container">
                        <div class="loading-indicator">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p>Loading bet distribution data...</p>
                        </div>
                    </div>
                </div>

                <div class="view-container" id="gridView">
                    <div id="betInfoGrid" class="bet-info-grid">
                        <!-- Bet information for each number will be populated here -->
                        <div class="loading-indicator">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p>Loading bet distribution data...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bet Type Distribution -->
            <div class="bet-distribution-container">
                <h3>Bet Type Distribution</h3>
                <div id="betTypeChartContainer" class="chart-container">
                    <div class="loading-indicator">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p>Loading bet type distribution...</p>
                    </div>
                </div>
            </div>

            <!-- Draw Control Section (Hidden by default) -->
            <div id="drawControlSection" class="draw-control-section" style="display: none;">
                <!-- Mobile View Placeholder - Keeping structure for JS compatibility -->
                <div class="d-block d-md-none">
                    <!-- Mobile content omitted for brevity in this view, but structure preserved -->
                </div>

                <div class="d-none d-md-block">
                    <div class="row">
                        <div class="col-12">
                            <div class="card shadow mb-4">
                                <div
                                    class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-primary">Winning Number Control</h6>
                                    <button class="btn btn-primary btn-sm" id="toggleAutoMode">
                                        <i class="fas fa-robot"></i> <span id="modeToggleText">Auto Mode</span>
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div class="text-center mb-3">
                                        <div class="number-circle mx-auto" id="winningNumberDisplay">-</div>
                                    </div>
                                    <div id="winningNumberSource" class="text-center">Source: -</div>
                                    <div id="winningNumberReason" class="text-center mb-3">Reason: -</div>

                                    <div class="form-group">
                                        <label for="manualWinningNumber">Set Manual Winning Number:</label>
                                        <input type="number" id="manualWinningNumber" class="form-control" value="0"
                                            min="0" max="36">
                                    </div>
                                    <button class="btn btn-primary btn-sm mt-2" id="setManualWinningNumber">
                                        <i class="fas fa-hand-pointer"></i> Set Winning Number
                                    </button>

                                    <!-- Recommended Numbers Section -->
                                    <div class="recommended-numbers-container mt-4">
                                        <div class="recommended-numbers-header">
                                            <h6 class="recommended-numbers-title">Recommended Numbers</h6>
                                            <button class="btn btn-sm btn-outline-primary" id="refreshRecommendations">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                        </div>

                                        <div class="recommended-numbers-tabs">
                                            <div class="recommended-tab active" data-type="no-bets">No Bets</div>
                                            <div class="recommended-tab" data-type="lowest-payout">Lowest Payout</div>
                                            <div class="recommended-tab" data-type="highest-payout">Highest Payout</div>
                                        </div>

                                        <div id="recommendedNumbersGrid" class="recommended-numbers-grid">
                                            <div class="no-recommendations">
                                                <i class="fas fa-info-circle"></i> Loading recommendations...
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Advanced Smart Number Management -->
                                    <div class="card shadow mt-4" style="border: 2px solid #4e73df;">
                                        <div class="card-header py-3 bg-primary text-white">
                                            <h6 class="m-0 font-weight-bold">
                                                <i class="fas fa-brain"></i> Smart Number Management
                                                <span class="badge badge-light ml-2">House Profit Optimizer</span>
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <!-- Time-Based Presets -->
                                            <div class="mb-4">
                                                <label class="font-weight-bold mb-2">
                                                    <i class="fas fa-clock"></i> Time-Based Preset:
                                                </label>
                                                <select id="timePreset" class="form-control form-control-sm">
                                                    <option value="auto">Auto (Based on Current Time)</option>
                                                    <option value="morning">Morning (6AM-12PM) - Lower Numbers</option>
                                                    <option value="afternoon">Afternoon (12PM-6PM) - Mid Range</option>
                                                    <option value="evening">Evening (6PM-12AM) - Higher Numbers</option>
                                                    <option value="night">Night (12AM-6AM) - Random Distribution
                                                    </option>
                                                    <option value="custom">Custom Pattern</option>
                                                </select>
                                                <small class="text-muted">Time-based patterns appear mathematical but
                                                    optimize for house profit</small>
                                            </div>

                                            <!-- Pattern Type -->
                                            <div class="mb-4">
                                                <label class="font-weight-bold mb-2">
                                                    <i class="fas fa-chart-line"></i> Pattern Strategy:
                                                </label>
                                                <select id="patternType" class="form-control form-control-sm">
                                                    <option value="smart">Smart (Pattern + Low Payout)</option>
                                                    <option value="fibonacci">Fibonacci-like Sequence</option>
                                                    <option value="color_alternate">Color Alternation</option>
                                                    <option value="cold_numbers">Cold Numbers (Not Recent)</option>
                                                    <option value="lowest_payout">Pure Lowest Payout</option>
                                                </select>
                                                <small class="text-muted">Creates patterns users think they can
                                                    predict</small>
                                            </div>

                                            <!-- Smart Selection Button -->
                                            <div class="mb-3">
                                                <button class="btn btn-success btn-block" id="smartSelectNumber">
                                                    <i class="fas fa-magic"></i> Generate Smart Number
                                                </button>
                                                <small class="text-muted d-block mt-1">
                                                    Analyzes bets, patterns, and time to select optimal number
                                                </small>
                                            </div>

                                            <!-- Smart Selection Result -->
                                            <div id="smartSelectionResult" class="alert alert-info"
                                                style="display: none;">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <strong>Selected:</strong>
                                                        <span class="number-circle-sm" id="smartSelectedNumber">-</span>
                                                        <span id="smartSelectionReason" class="ml-2"></span>
                                                    </div>
                                                    <button class="btn btn-sm btn-primary" id="applySmartSelection">
                                                        <i class="fas fa-check"></i> Apply
                                                    </button>
                                                </div>
                                                <div class="mt-2">
                                                    <small>
                                                        <strong>Pattern Analysis:</strong>
                                                        <span id="patternAnalysis"></span>
                                                    </small>
                                                </div>
                                                <div class="mt-1">
                                                    <small>
                                                        <strong>Payout:</strong>
                                                        <span id="smartPayout"
                                                            class="text-success font-weight-bold">$0.00</span>
                                                    </small>
                                                </div>
                                            </div>

                                            <!-- Preset Schedule -->
                                            <div class="mt-3 mb-3">
                                                <label class="font-weight-bold mb-2">
                                                    <i class="fas fa-calendar-alt"></i> Preset Schedule:
                                                </label>
                                                <div id="presetSchedule" class="preset-schedule-container" style="display: none;">
                                                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                                        <table class="table table-sm table-bordered mb-0">
                                                            <thead class="thead-light sticky-top">
                                                                <tr>
                                                                    <th style="width: 15%;">Draw #</th>
                                                                    <th style="width: 25%;">Time</th>
                                                                    <th style="width: 20%;">Number</th>
                                                                    <th style="width: 40%;">Pattern</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody id="presetScheduleBody">
                                                                <!-- Will be populated by JavaScript -->
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <div id="presetScheduleProgress" class="text-center text-info mb-2" style="display: none;">
                                                        <i class="fas fa-spinner fa-spin"></i> <span>Generating...</span>
                                                    </div>
                                                    <div id="presetScheduleStatus" class="text-muted small mb-2" style="display: none;">
                                                        <!-- Status will be populated by JavaScript -->
                                                    </div>
                                                    <small class="text-muted d-block mt-2">
                                                        <i class="fas fa-info-circle"></i> Shows 480 preset numbers (24 hours) based on selected pattern. Table shows first 30 entries.
                                                    </small>
                                                </div>
                                                <div id="presetSchedulePlaceholder" class="text-center text-muted">
                                                    <i class="fas fa-calendar-alt fa-2x mb-2"></i>
                                                    <p>Click "Generate Smart Number" to see preset schedule</p>
                                                </div>
                                            </div>

                                            <!-- Pattern Visualization -->
                                            <div class="mt-3">
                                                <label class="font-weight-bold mb-2">
                                                    <i class="fas fa-chart-bar"></i> Pattern Analysis:
                                                </label>
                                                <div id="patternVisualization" class="pattern-chart">
                                                    <div class="text-center text-muted">
                                                        <i class="fas fa-chart-line fa-2x mb-2"></i>
                                                        <p>Click "Generate Smart Number" to see pattern analysis</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: Sidebar & Preview -->
        <div class="col-lg-4">
            <!-- Live TV Preview -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Live TV Preview</h6>
                </div>
                <div class="card-body p-0"
                    style="height: 240px; overflow: hidden; position: relative; background: #000;">
                    <iframe src="../tvdisplay/index.html"
                        style="width: 1920px; height: 1080px; border: 0; transform: scale(0.2); transform-origin: 0 0; position: absolute; top: 0; left: 0;"></iframe>
                </div>
            </div>

            <!-- Upcoming Draws Overview -->
            <div class="upcoming-draws-overview mb-4">
                <div class="card shadow">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">10 Upcoming Draws Overview</h6>
                        <button class="btn btn-sm btn-outline-primary" id="refreshAllDraws">
                            <i class="fas fa-sync-alt"></i> Refresh All
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="upcomingDrawsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Draw #</th>
                                        <th>Est. Time</th>
                                        <th>Betting Slips</th>
                                        <th>Total Stake</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="5" class="text-center py-3">
                                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                            <span class="ms-2">Loading upcoming draws...</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Current Draw Information -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Current Draw Information</h6>
                </div>
                <div class="card-body">
                    <div class="h3 mb-0 font-weight-bold text-gray-800" id="currentDrawNumber">-</div>
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Current Draw Number</div>

                    <table class="table table-sm mt-3">
                        <tr>
                            <td>Last Draw Time:</td>
                            <td id="lastDrawTime">-</td>
                        </tr>
                        <tr>
                            <td>Next Draw Time:</td>
                            <td id="nextDrawTime">-</td>
                        </tr>
                        <tr>
                            <td>Mode:</td>
                            <td id="currentMode">-</td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Draw Timer -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Draw Timer</h6>
                </div>
                <div class="card-body p-0">
                    <!-- 3D Timer Container -->
                    <div class="timer-3d-container">
                        <!-- Three.js scene will be rendered here -->
                        <div id="timer3dScene" class="timer-3d-scene"></div>

                        <!-- Timer display -->
                        <div id="timer3dDisplay" class="timer-3d-display">00:00</div>

                        <!-- Sync indicator -->
                        <div id="timerSyncIndicator" class="timer-sync-indicator" style="display: none;">
                            <i class="fas fa-sync-alt fa-spin"></i>
                            <span class="sync-text">Synced with real-time</span>
                        </div>

                        <!-- Next draw time display -->
                        <div class="next-draw-time"
                            style="position: absolute; bottom: 50px; left: 0; right: 0; text-align: center; color: white; font-size: 0.9rem; text-shadow: 0 0 5px rgba(0,0,0,0.5);">
                            Next draw at: <span id="nextDrawTimeDisplay">--:--:--</span>
                        </div>

                        <!-- Particle container -->
                        <div id="particles" class="particles"></div>

                        <!-- Timer controls -->
                        <div class="timer-3d-controls">
                            <button id="startTimer3d" class="timer-3d-btn start">
                                <i class="fas fa-play"></i> Start
                            </button>
                            <button id="pauseTimer3d" class="timer-3d-btn pause">
                                <i class="fas fa-pause"></i> Pause
                            </button>
                            <button id="resetTimer3d" class="timer-3d-btn reset">
                                <i class="fas fa-redo"></i> Reset
                            </button>
                        </div>
                    </div>

                    <!-- Timer settings -->
                    <div class="timer-3d-settings">
                        <label for="timerInterval3d">Draw Interval (seconds):</label>
                        <input type="number" id="timerInterval3d" value="60" min="10" max="300">
                        <button id="updateTimerSettings3d">
                            <i class="fas fa-save"></i> Update Settings
                        </button>
                    </div>

                    <!-- Hidden original timer for compatibility -->
                    <div style="display: none;">
                        <div id="timerDisplay">00:00</div>
                        <button id="startTimer"></button>
                        <button id="pauseTimer"></button>
                        <button id="resetTimer"></button>
                        <input id="timerInterval" value="60">
                        <button id="updateTimerSettings"></button>
                    </div>
                </div>
            </div>

            <!-- Forced Number Checker -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Forced Number Checker</h6>
                    <button class="btn btn-primary btn-sm" id="checkForcedNumber">
                        <i class="fas fa-sync-alt"></i> Check Now
                    </button>
                </div>
                <div class="card-body">
                    <div class="forced-number-container">
                        <div class="forced-number-status mb-2" id="forcedNumberStatus">Click to check for forced numbers
                        </div>

                        <div class="forced-number-display">
                            <div class="forced-number-badge-container">
                                <div class="forced-number-badge" id="forcedNumberBadge">?</div>
                                <div class="forced-number-glow"></div>
                            </div>
                            <div class="forced-number-info mt-2">
                                <div class="forced-number-draw">Draw: <span id="forcedNumberDraw">-</span></div>
                            </div>
                        </div>

                        <div class="forced-number-details mt-3">
                            <div class="forced-number-detail-item">
                                <i class="fas fa-info-circle"></i>
                                <span id="forcedNumberMessage">No information available</span>
                            </div>
                        </div>

                        <!-- Auto-Apply Toggle -->
                        <div class="mt-3 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="autoApplyForcedNumber">
                                <label class="form-check-label" for="autoApplyForcedNumber">
                                    <i class="fas fa-magic"></i> Auto-Apply from Preset Schedule
                                </label>
                            </div>
                            <small class="text-muted d-block mt-1">
                                <span id="autoApplyStatus">Manual Mode</span> - <span id="autoApplyDescription">Click "Apply" to set winning number</span>
                            </small>
                        </div>

                        <!-- Apply Button (shown when forced number is found) -->
                        <div class="mt-3" id="forcedNumberApplyContainer" style="display: none;">
                            <button class="btn btn-success btn-block" id="applyForcedNumber">
                                <i class="fas fa-check-circle"></i> Apply as Winning Number
                            </button>
                            <small class="text-muted d-block mt-2 text-center">
                                This will set the forced number as the winning number for the current draw
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Roll History -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Roll History</h6>
                </div>
                <div class="card-body">
                    <div class="roll-history" id="rollHistory">
                        <!-- Roll history items will be added here -->
                    </div>
                    <div class="auto-refresh-status mt-3">
                        <span>Auto-refreshing data every 15 seconds (Last updated: -)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- Mobile Sticky Controls -->
    <div class="mobile-sticky-controls d-md-none">
        <div class="mobile-sticky-btn" id="mobileRefreshBtn">
            <i class="fas fa-sync-alt"></i>
            <span>Refresh</span>
        </div>
        <div class="mobile-sticky-btn" id="mobileToggleViewBtn">
            <i class="fas fa-th"></i>
            <span>Toggle View</span>
        </div>
        <div class="mobile-sticky-btn" id="mobileDrawControlBtn">
            <i class="fas fa-sliders-h"></i>
            <span>Draw Control</span>
        </div>
        <div class="mobile-sticky-btn" id="mobileTimerBtn">
            <i class="fas fa-clock"></i>
            <span>Timer</span>
        </div>
    </div>

    <!-- Mobile Floating Action Button -->
    <div class="mobile-fab d-md-none" id="mobileFab">
        <i class="fas fa-dice"></i>
    </div>

    <!-- Loading Overlay -->
    <div class="loading" id="loadingOverlay" style="display: none;">
        <div class="loading-spinner"></div>
        <div class="loading-text">Loading data...</div>
    </div>
    
    <!-- JavaScript Test Indicator -->
    <div id="jsTestIndicator" style="position: fixed; top: 10px; right: 10px; background: #28a745; color: white; padding: 10px; border-radius: 5px; z-index: 9999; display: none;">
        ✅ JavaScript is working!
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.35.0/dist/apexcharts.min.js"></script>
    
    <!-- Firebase SDK for Firestore sync -->
    <script src="https://www.gstatic.com/firebasejs/9.23.0/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.23.0/firebase-database-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.23.0/firebase-firestore-compat.js"></script>
    <script src="../js/firebase-config.js"></script>
    <script src="../js/firestore-service.js"></script>

    <script>
        // IMMEDIATE TEST - This should appear in console immediately
        console.log('🚀 SCRIPT STARTED - JavaScript is working!');
        console.log('📄 bet_distribution.php script is loading...');
        console.log('📄 Current page URL:', window.location.href);
        
        // Show visual indicator that JavaScript is working
        (function() {
            try {
                const indicator = document.getElementById('jsTestIndicator');
                if (indicator) {
                    indicator.style.display = 'block';
                    setTimeout(() => {
                        indicator.style.display = 'none';
                    }, 3000);
                }
            } catch (e) {
                console.error('Error showing JS indicator:', e);
            }
        })();
        
        // Test if we can access DOM immediately
        if (document.readyState === 'loading') {
            console.log('📄 Document is still loading...');
        } else if (document.readyState === 'interactive') {
            console.log('📄 Document is interactive');
        } else if (document.readyState === 'complete') {
            console.log('📄 Document is complete');
        }
        
        // Global variables
        let loadingOverlay;
        let refreshInterval;
        let betDistributionChart;
        let betTypeChart;
        let currentData = null;
        let upcomingDrawNumber = null;
        let currentRecommendationType = 'no-bets';
        let recommendedNumbers = {
            'no-bets': [],
            'lowest-payout': [],
            'highest-payout': []
        };

        // Multiple draws variables
        let allDrawsData = [];
        let selectedDrawIndex = 0;
        let currentDrawNumber = 0;
        let isManualSelectionInProgress = false; // Flag to prevent forced number checker from overriding manual selections

        // Draw Control variables
        let timerInterval = 60; // Default timer interval in seconds
        let timerValue = timerInterval;
        let timerRunning = false;
        let timerIntervalId = null;
        let autoRefreshIntervalId = null;
        const autoRefreshInterval = 15000; // 15 seconds
        let isAutoMode = true;
        let currentWinningNumber = null;
        let drawControlVisible = false;
        let timerSyncIntervalId = null;
        const timerSyncInterval = 5000; // Sync timer every 5 seconds
        let forcedNumberCheckIntervalId = null;
        const forcedNumberCheckInterval = 30000; // Check forced number every 30 seconds (manual mode)
        const forcedNumberCheckIntervalAuto = 5000; // Check forced number every 5 seconds (auto mode)

        // 3D Timer variables
        let scene, camera, renderer;
        let particles = [];
        let clock = new THREE.Clock();
        let particleSystem;
        let animationFrameId;
        let timerMesh;
        let isTimerInitialized = false;
        let isTimerSynced = false;

        // Log that script is loading
        console.log('📄 bet_distribution.php script is loading...');

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            console.log('✅ ========================================');
            console.log('✅ Bet distribution and draw control page loaded');
            console.log('✅ DOMContentLoaded event fired');
            console.log('✅ ========================================');

            try {
                // Cache DOM elements
                loadingOverlay = document.getElementById('loadingOverlay');
                
                if (!loadingOverlay) {
                    console.warn('⚠️ Loading overlay element not found in DOM');
                } else {
                    console.log('✅ Loading overlay found');
                }
                
                // Immediately fetch draw info on page load
                console.log('🔄 Calling fetchDrawInfo()...');
                fetchDrawInfo();
            } catch (error) {
                console.error('❌ Error in DOMContentLoaded handler:', error);
            }

            // Set up refresh button
            document.getElementById('refreshButton').addEventListener('click', function() {
                console.log('Refresh button clicked');
                fetchBetDistribution();
                if (drawControlVisible) {
                    fetchDrawInfo();
                }
            });

            // Set up view tabs
            const viewTabs = document.querySelectorAll('.view-tab');
            viewTabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const viewName = this.getAttribute('data-view');

                    // Update active tab
                    viewTabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');

                    // Show corresponding view
                    document.querySelectorAll('.view-container').forEach(view => {
                        view.classList.remove('active');
                    });
                    document.getElementById(viewName + 'View').classList.add('active');

                    // Redraw chart if needed
                    if (viewName === 'chart' && betDistributionChart) {
                        setTimeout(() => {
                            betDistributionChart.render();
                        }, 10);
                    }
                });
            });

            // Set up toggle draw control button
            document.getElementById('toggleDrawControlButton').addEventListener('click', function() {
                toggleDrawControl();
            });

            // Set up mobile sticky controls
            document.getElementById('mobileRefreshBtn').addEventListener('click', function() {
                console.log('Mobile refresh button clicked');
                fetchBetDistribution();
                if (drawControlVisible) {
                    fetchDrawInfo();
                }

                // Add visual feedback
                const icon = this.querySelector('i');
                icon.classList.add('fa-spin');
                setTimeout(() => {
                    icon.classList.remove('fa-spin');
                }, 1000);
            });

            document.getElementById('mobileToggleViewBtn').addEventListener('click', function() {
                // Toggle between chart and grid view
                const activeTab = document.querySelector('.view-tab.active');
                const nextView = activeTab.getAttribute('data-view') === 'chart' ? 'grid' : 'chart';

                // Find and click the corresponding tab
                document.querySelector(`.view-tab[data-view="${nextView}"]`).click();

                // Update icon
                const icon = this.querySelector('i');
                icon.className = nextView === 'chart' ? 'fas fa-chart-bar' : 'fas fa-th';
            });

            document.getElementById('mobileDrawControlBtn').addEventListener('click', function() {
                toggleDrawControl();

                // Scroll to draw control section if visible
                if (drawControlVisible) {
                    document.getElementById('drawControlSection').scrollIntoView({ behavior: 'smooth' });
                }
            });

            document.getElementById('mobileTimerBtn').addEventListener('click', function() {
                // If draw control is not visible, show it
                if (!drawControlVisible) {
                    toggleDrawControl();
                }

                // Expand the timer section if it's not already expanded
                const timerContent = document.getElementById('timerContent');
                if (!timerContent.classList.contains('expanded')) {
                    document.querySelector('[data-target="timerContent"]').click();
                }

                // Scroll to timer section
                document.querySelector('[data-target="timerContent"]').scrollIntoView({ behavior: 'smooth' });
            });

            // Set up mobile floating action button
            document.getElementById('mobileFab').addEventListener('click', function() {
                // Show a quick menu with common actions
                const actions = [
                    { icon: 'fa-sync-alt', text: 'Refresh Data', action: () => document.getElementById('mobileRefreshBtn').click() },
                    { icon: 'fa-dice', text: 'Set Winning Number', action: () => {
                        toggleDrawControl(true);
                        document.querySelector('[data-target="winningNumberContent"]').click();
                        document.querySelector('[data-target="winningNumberContent"]').scrollIntoView({ behavior: 'smooth' });
                    }},
                    { icon: 'fa-clock', text: 'Timer Controls', action: () => document.getElementById('mobileTimerBtn').click() }
                ];

                // Create and show a simple action menu
                const menu = document.createElement('div');
                menu.className = 'mobile-action-menu';
                menu.style.position = 'fixed';
                menu.style.bottom = '150px';
                menu.style.right = '20px';
                menu.style.backgroundColor = 'white';
                menu.style.borderRadius = '8px';
                menu.style.boxShadow = '0 4px 12px rgba(0,0,0,0.2)';
                menu.style.zIndex = '1001';

                actions.forEach(action => {
                    const item = document.createElement('div');
                    item.className = 'mobile-action-item';
                    item.style.padding = '12px 16px';
                    item.style.display = 'flex';
                    item.style.alignItems = 'center';
                    item.style.gap = '10px';
                    item.style.borderBottom = '1px solid #eee';
                    item.innerHTML = `<i class="fas ${action.icon}"></i> ${action.text}`;
                    item.addEventListener('click', () => {
                        action.action();
                        document.body.removeChild(menu);
                    });
                    menu.appendChild(item);
                });

                document.body.appendChild(menu);

                // Close menu when clicking outside
                const closeMenu = (e) => {
                    if (!menu.contains(e.target) && e.target !== document.getElementById('mobileFab')) {
                        document.body.removeChild(menu);
                        document.removeEventListener('click', closeMenu);
                    }
                };

                // Delay adding the event listener to prevent immediate closing
                setTimeout(() => {
                    document.addEventListener('click', closeMenu);
                }, 100);
            });

            // Set up mobile collapsible sections
            document.querySelectorAll('.mobile-collapsible-header').forEach(header => {
                header.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    const content = document.getElementById(targetId);
                    const icon = this.querySelector('i');

                    // Toggle expanded state
                    if (content.classList.contains('expanded')) {
                        content.classList.remove('expanded');
                        icon.className = 'fas fa-chevron-down';
                    } else {
                        content.classList.add('expanded');
                        icon.className = 'fas fa-chevron-up';
                    }
                });
            });

            // Set up mobile timer controls
            document.getElementById('startTimer3d-mobile')?.addEventListener('click', startTimer);
            document.getElementById('pauseTimer3d-mobile')?.addEventListener('click', pauseTimer);
            document.getElementById('resetTimer3d-mobile')?.addEventListener('click', resetTimer);
            document.getElementById('updateTimerSettings3d-mobile')?.addEventListener('click', function() {
                document.getElementById('timerInterval').value = document.getElementById('timerInterval3d-mobile').value;
                updateTimerSettings();
            });

            // Set up mobile winning number controls
            document.getElementById('toggleAutoMode-mobile')?.addEventListener('click', toggleMode);
            document.getElementById('setManualWinningNumber-mobile')?.addEventListener('click', function() {
                document.getElementById('manualWinningNumber').value = document.getElementById('manualWinningNumber-mobile').value;
                setManualWinningNumber();
            });

            // Set up mobile forced number checker
            document.getElementById('checkForcedNumber-mobile')?.addEventListener('click', checkForcedNumber);

            // Set up mobile recommendation tabs
            document.querySelectorAll('.recommended-tab[data-mobile="true"]').forEach(tab => {
                tab.addEventListener('click', function() {
                    const recommendationType = this.getAttribute('data-type');

                    // Update active tab
                    document.querySelectorAll('.recommended-tab[data-mobile="true"]').forEach(t => t.classList.remove('active'));
                    this.classList.add('active');

                    // Update current recommendation type
                    currentRecommendationType = recommendationType;

                    // Update recommendations display for mobile
                    displayRecommendations(recommendationType, true);
                });
            });

            // Initial data fetch
            console.log('🔄 About to call fetchBetDistribution()...');
            console.log('🔄 fetchBetDistribution function exists:', typeof fetchBetDistribution);
            
            // Set a timeout to hide loading if nothing happens
            const loadingTimeout = setTimeout(() => {
                console.warn('⚠️ Loading timeout - hiding overlay after 10 seconds');
                showLoading(false);
                if (allDrawsData.length === 0) {
                    showError('Page is taking too long to load. Please check your connection and refresh.');
                }
            }, 10000);
            
            // Use setTimeout to ensure DOM is fully ready and function is defined
            setTimeout(() => {
                console.log('⏰ setTimeout callback executing...');
                console.log('⏰ fetchBetDistribution type:', typeof fetchBetDistribution);
                console.log('⏰ showLoading type:', typeof showLoading);
                console.log('⏰ showError type:', typeof showError);
                
                try {
                    if (typeof fetchBetDistribution === 'function') {
                        console.log('🔄 Calling fetchBetDistribution() now...');
                        fetchBetDistribution()
                            .then(() => {
                                console.log('✅ fetchBetDistribution() completed successfully');
                                clearTimeout(loadingTimeout);
                            })
                            .catch(error => {
                                clearTimeout(loadingTimeout);
                                console.error('❌ fetchBetDistribution() promise rejected:', error);
                                console.error('❌ Error details:', {
                                    message: error.message,
                                    stack: error.stack,
                                    name: error.name
                                });
                                if (typeof showError === 'function') {
                                    showError('Failed to fetch bet distribution: ' + error.message);
                                } else {
                                    alert('Failed to fetch bet distribution: ' + error.message);
                                }
                            });
                    } else {
                        clearTimeout(loadingTimeout);
                        console.error('❌ fetchBetDistribution is not a function!');
                        console.error('❌ Available functions:', Object.keys(window).filter(k => typeof window[k] === 'function').slice(0, 10));
                        if (typeof showError === 'function') {
                            showError('fetchBetDistribution function not found. Please refresh the page.');
                        } else {
                            alert('fetchBetDistribution function not found. Please refresh the page.');
                        }
                    }
                } catch (error) {
                    clearTimeout(loadingTimeout);
                    console.error('❌ Error calling fetchBetDistribution():', error);
                    console.error('❌ Error stack:', error.stack);
                    if (typeof showError === 'function') {
                        showError('Failed to initialize bet distribution: ' + error.message);
                    } else {
                        alert('Failed to initialize bet distribution: ' + error.message);
                    }
                }
            }, 500); // Increased timeout to 500ms to ensure function is defined
            
            // Load smart selection settings on page load
            try {
                loadSmartSelectionSettings();
            } catch (error) {
                console.error('❌ Error loading smart selection settings:', error);
            }

            // Set up auto refresh - every 15 seconds
            refreshInterval = setInterval(fetchBetDistribution, 15000);

            // Add event listeners for new navigation controls
            document.getElementById('prevDraw').addEventListener('click', navigatePrevDraw);
            document.getElementById('nextDraw').addEventListener('click', navigateNextDraw);
            document.getElementById('refreshAllDraws').addEventListener('click', fetchBetDistribution);
        });

        // Function to toggle draw control visibility
        function toggleDrawControl(forceShow = null) {
            const drawControlSection = document.getElementById('drawControlSection');

            // If forceShow is provided, use that value, otherwise toggle
            if (forceShow !== null) {
                drawControlVisible = forceShow;
            } else {
                drawControlVisible = !drawControlVisible;
            }

            if (drawControlVisible) {
                drawControlSection.style.display = 'block';
                document.getElementById('toggleDrawControlText').textContent = 'Hide Draw Control';
                document.getElementById('mobileDrawControlBtn').querySelector('i').className = 'fas fa-eye-slash';
                document.getElementById('mobileDrawControlBtn').querySelector('span').textContent = 'Hide Control';

                // Initialize draw control
                fetchDrawInfo();
                setupDrawControlEventListeners();

                // Immediately sync with TV display timer
                syncTimerWithTVDisplay();

                // Start periodic forced number checking
                startForcedNumberCheck();
                
                // Initialize Firestore real-time listeners
                initFirestoreRealTimeListeners();
                
                // Check and auto-generate preset schedule if needed
                checkAndAutoGeneratePreset();
            } else {
                drawControlSection.style.display = 'none';
                document.getElementById('toggleDrawControlText').textContent = 'Show Draw Control';
                document.getElementById('mobileDrawControlBtn').querySelector('i').className = 'fas fa-sliders-h';
                document.getElementById('mobileDrawControlBtn').querySelector('span').textContent = 'Draw Control';

                // Stop timer sync
                stopTimerSync();

                // Stop forced number checking
                stopForcedNumberCheck();

                // Stop the timer
                if (timerRunning) {
                    pauseTimer();
                }

                // Clean up 3D timer resources when hidden
                if (isTimerInitialized && animationFrameId) {
                    cancelAnimationFrame(animationFrameId);
                    animationFrameId = null;

                    // Remove renderer from DOM if it exists
                    const container = document.getElementById('timer3dScene');
                    if (container && renderer && renderer.domElement) {
                        container.removeChild(renderer.domElement);
                    }

                    // Reset flags so it can be reinitialized when shown again
                    isTimerInitialized = false;
                    isTimerSynced = false;
                }
            }
        }

        // Set up draw control event listeners
        function setupDrawControlEventListeners() {
            // Timer controls
            document.getElementById('startTimer').addEventListener('click', startTimer);
            document.getElementById('pauseTimer').addEventListener('click', pauseTimer);
            document.getElementById('resetTimer').addEventListener('click', resetTimer);
            document.getElementById('updateTimerSettings').addEventListener('click', updateTimerSettings);

            // 3D Timer controls
            document.getElementById('startTimer3d').addEventListener('click', startTimer);
            document.getElementById('pauseTimer3d').addEventListener('click', pauseTimer);
            document.getElementById('resetTimer3d').addEventListener('click', resetTimer);
            document.getElementById('updateTimerSettings3d').addEventListener('click', function() {
                document.getElementById('timerInterval').value = document.getElementById('timerInterval3d').value;
                updateTimerSettings();
            });

            // Winning number controls
            document.getElementById('toggleAutoMode').addEventListener('click', toggleMode);
            document.getElementById('setManualWinningNumber').addEventListener('click', setManualWinningNumber);
            
            // Apply forced number button
            document.getElementById('applyForcedNumber')?.addEventListener('click', async function() {
                console.log('🎯 Apply Forced Number button clicked');
                
                const applyContainer = document.getElementById('forcedNumberApplyContainer');
                let forcedNumber = applyContainer?.getAttribute('data-forced-number');
                // ALWAYS use current draw number, not the data attribute which might be wrong
                const drawNumber = currentDrawNumber;
                let numberSource = 'forced'; // Track where the number came from
                
                // If no forced number is set manually, get it from preset schedule for CURRENT draw
                if (!forcedNumber) {
                    console.log('🎯 No forced number set manually, checking preset schedule for current draw...');
                    const presetNumber = await getPresetNumberForCurrentDraw();
                    
                    if (presetNumber) {
                        forcedNumber = presetNumber.number.toString();
                        numberSource = 'preset';
                        console.log('✅ Found number from preset schedule for current draw:', { number: forcedNumber, drawNumber, color: presetNumber.color });
                    } else {
                        // Try to get from recommended numbers (no-bets tab)
                        console.log('🎯 No preset schedule found, checking recommended numbers...');
                        const recommendedNumber = getRecommendedNumberForCurrentDraw();
                        
                        if (recommendedNumber !== null) {
                            forcedNumber = recommendedNumber.toString();
                            numberSource = 'recommended';
                            console.log('✅ Found number from recommended numbers:', forcedNumber);
                        } else {
                            showToast('Error', 'No number available. Please generate a preset schedule first.', 'error');
                            return;
                        }
                    }
                }
                
                if (forcedNumber) {
                    const number = parseInt(forcedNumber);
                    if (!isNaN(number) && number >= 0 && number <= 36) {
                        // Set the manual winning number input
                        const manualInput = document.getElementById('manualWinningNumber');
                        if (manualInput) {
                            manualInput.value = number;
                        }
                        
                        // Set the winning number for CURRENT draw
                        setManualWinningNumber();
                        
                        // Show success message with source
                        const sourceText = numberSource === 'preset' ? 'preset schedule' : 
                                         numberSource === 'recommended' ? 'recommended numbers' : 
                                         'forced number';
                        showToast('Applied', `Number ${number} from ${sourceText} has been set as the winning number for draw #${drawNumber}`, 'success');
                        
                        // Hide apply button after a short delay
                        setTimeout(() => {
                            if (applyContainer) {
                                applyContainer.style.display = 'none';
                            }
                        }, 2000);
                        
                        // After applying, automatically set the next draw number from preset schedule
                        setTimeout(() => {
                            setNextDrawNumberFromPresetSchedule();
                        }, 1000);
                    } else {
                        showToast('Error', 'Invalid forced number', 'error');
                    }
                } else {
                    showToast('Error', 'No number available to apply', 'error');
                }
            });

            // Forced Number Checker controls
            document.getElementById('checkForcedNumber').addEventListener('click', checkForcedNumber);
            
            // Auto-apply checkbox change handler
            const autoApplyCheckbox = document.getElementById('autoApplyForcedNumber');
            const autoApplyStatus = document.getElementById('autoApplyStatus');
            const autoApplyDescription = document.getElementById('autoApplyDescription');
            
            // Function to update auto-apply status display
            function updateAutoApplyStatus() {
                if (autoApplyStatus && autoApplyDescription) {
                    if (autoApplyCheckbox.checked) {
                        autoApplyStatus.textContent = 'Auto Mode';
                        autoApplyStatus.className = 'text-success font-weight-bold';
                        autoApplyDescription.textContent = 'Numbers will be applied automatically from preset schedule';
                    } else {
                        autoApplyStatus.textContent = 'Manual Mode';
                        autoApplyStatus.className = 'text-warning font-weight-bold';
                        autoApplyDescription.textContent = 'Click "Apply" to set winning number';
                    }
                }
            }
            
            if (autoApplyCheckbox) {
                // Load saved auto-apply setting (default to false/manual mode)
                const savedAutoApply = localStorage.getItem('autoApplyForcedNumber');
                if (savedAutoApply !== null) {
                    autoApplyCheckbox.checked = savedAutoApply === 'true';
                } else {
                    // Default to manual mode (unchecked)
                    autoApplyCheckbox.checked = false;
                    localStorage.setItem('autoApplyForcedNumber', 'false');
                }
                
                // Update status display on load
                updateAutoApplyStatus();
                
                // Save setting when changed
                autoApplyCheckbox.addEventListener('change', function() {
                    localStorage.setItem('autoApplyForcedNumber', this.checked.toString());
                    console.log('✅ Auto-apply setting saved:', this.checked);
                    
                    // Update status display
                    updateAutoApplyStatus();
                    
                    // Restart forced number checking with new settings
                    if (forcedNumberCheckIntervalId) {
                        stopForcedNumberCheck();
                    }
                    startForcedNumberCheck();
                    
                    // Show toast notification
                    const mode = this.checked ? 'Auto Mode' : 'Manual Mode';
                    const message = this.checked 
                        ? 'Numbers will be applied automatically from preset schedule' 
                        : 'You must click "Apply" to set winning numbers';
                    showToast('Mode Changed', `${mode} - ${message}`, 'info');
                });
            }

            // Recommended numbers controls
            document.getElementById('refreshRecommendations').addEventListener('click', generateRecommendations);

            // Set up recommendation tabs
            const recommendationTabs = document.querySelectorAll('.recommended-tab');
            recommendationTabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const recommendationType = this.getAttribute('data-type');

                    // Update active tab
                    recommendationTabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');

                    // Update current recommendation type
                    currentRecommendationType = recommendationType;

                    // Update recommendations display
                    displayRecommendations(recommendationType);
                });
            });

            // Initialize 3D timer
            initTimer3D();

            // Sync timer with TV display
            syncTimerWithTVDisplay();

            // Start periodic timer sync
            startTimerSync();

            // Check forced number on initial load
            checkForcedNumber();
        }

        // Function to check for forced numbers
        async function checkForcedNumber() {
            // Don't check if manual selection is in progress
            if (isManualSelectionInProgress) {
                console.log('⏸️ Skipping forced number check - manual selection in progress');
                return;
            }
            
            // Update UI to show checking state
            const forcedNumberBadge = document.getElementById('forcedNumberBadge');
            const forcedNumberStatus = document.getElementById('forcedNumberStatus');
            const forcedNumberMessage = document.getElementById('forcedNumberMessage');
            const forcedNumberDraw = document.getElementById('forcedNumberDraw');

            // Reset classes and set checking state
            forcedNumberBadge.className = 'forced-number-badge checking';
            forcedNumberBadge.textContent = '?';
            forcedNumberStatus.textContent = 'Checking for forced numbers...';
            forcedNumberMessage.textContent = 'Fetching data from server...';
            forcedNumberDraw.textContent = '-';

            // ⚠️ CRITICAL: Check for forced number for the NEXT draw number
            // When setting a winning number, it's set for the NEXT draw, not the current one
            // So we need to check nextDrawNumber (currentDrawNumber + 1)
            const targetDrawNumber = currentDrawNumber + 1;
            
            // Add timestamp to prevent caching and specify draw number
            fetch(`../api/direct_forced_number.php?draw_number=${targetDrawNumber}&t=${Date.now()}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Network response was not ok: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Forced number check response:', data);

                    // Update status
                    forcedNumberStatus.textContent = data.message;
                    forcedNumberMessage.textContent = data.message;

                    // Remove checking animation
                    forcedNumberBadge.classList.remove('checking');

                    // ⚠️ PRIORITY LOGIC: Manual forced numbers > Preset schedule
                    // The API already handles this priority, so we trust the API response
                    let finalForcedNumber = null;
                    let finalForcedColor = null;
                    let numberSource = 'unknown';
                    
                    if (data.has_forced_number && data.draw_number === targetDrawNumber) {
                        // API returned a forced number for the current draw
                        finalForcedNumber = data.forced_number;
                        finalForcedColor = data.forced_color;
                        
                        // Determine source based on API response
                        if (data.source === 'manual') {
                            numberSource = 'manually set';
                        } else if (data.source === 'preset_schedule') {
                            numberSource = 'preset schedule';
                        } else {
                            numberSource = 'automatic';
                        }
                        
                        console.log('✅ Using forced number from API:', { 
                            number: finalForcedNumber, 
                            drawNumber: data.draw_number,
                            source: data.source 
                        });
                    } else {
                        // No forced number from API - check preset schedule as fallback
                    let presetNumber = null;
                    
                    // Try DOM first (synchronous)
                    const scheduleBody = document.getElementById('presetScheduleBody');
                    if (scheduleBody) {
                        const rows = scheduleBody.querySelectorAll('tr');
                        for (let row of rows) {
                            const drawCell = row.querySelector('td:first-child');
                            if (drawCell) {
                                const drawText = drawCell.textContent.trim();
                                const drawMatch = drawText.match(/#(\d+)/);
                                if (drawMatch) {
                                    const drawNum = parseInt(drawMatch[1]);
                                    if (drawNum === targetDrawNumber) {
                                        const numberCell = row.querySelector('td:nth-child(3)');
                                        if (numberCell) {
                                            const numberSpan = numberCell.querySelector('.number-circle-sm');
                                            if (numberSpan) {
                                                const number = parseInt(numberSpan.textContent.trim());
                                                const color = numberSpan.classList.contains('red') ? 'red' : 
                                                             numberSpan.classList.contains('black') ? 'black' : 'green';
                                                presetNumber = { number, color };
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    
                    // If not in DOM, query database (async)
                    if (!presetNumber) {
                            fetch(`../api/get_current_preset.php?draw_number=${targetDrawNumber}&_cb=${Date.now()}`)
                                .then(presetResponse => presetResponse.json())
                                .then(presetData => {
                                if (presetData.status === 'success' && presetData.data) {
                                    presetNumber = {
                                        number: presetData.data.winning_number,
                                        color: presetData.data.color
                                    };
                                }
                                })
                                .catch(error => {
                            console.warn('⚠️ Error querying database for preset:', error);
                                });
                    }
                    
                    if (presetNumber) {
                        finalForcedNumber = presetNumber.number;
                        finalForcedColor = presetNumber.color;
                            numberSource = 'preset schedule';
                            console.log('✅ Using preset schedule number (no forced number from API):', { number: finalForcedNumber, drawNumber: targetDrawNumber });
                        }
                    }
                    
                    // Update forced number display
                    if (finalForcedNumber !== null) {
                        // Check auto-apply setting BEFORE doing anything
                        const autoApplyCheckbox = document.getElementById('autoApplyForcedNumber');
                        const isAutoApplyEnabled = autoApplyCheckbox ? autoApplyCheckbox.checked : false;
                        
                        // Set number and color
                        forcedNumberBadge.textContent = finalForcedNumber;
                        forcedNumberBadge.className = 'forced-number-badge ' + finalForcedColor + ' has-forced found';
                        
                        // ALWAYS use current draw number
                        forcedNumberDraw.textContent = '#' + targetDrawNumber;

                        // Update message with more details - show source clearly
                        const sourceLabel = numberSource === 'manually set' ? 
                            '<span style="color: #ff6b6b; font-weight: bold;">Manually Set</span>' :
                            numberSource === 'preset schedule' ? 
                            '<span style="color: #4ecdc4;">Preset Schedule</span>' :
                            '<span style="color: #95a5a6;">Automatic</span>';
                        forcedNumberMessage.innerHTML = `<strong>Number ${finalForcedNumber} (${finalForcedColor})</strong> - ${sourceLabel} for draw #${targetDrawNumber}`;

                        // Only set manual input if auto-apply is enabled OR if there's no value in the input
                        // This prevents overriding manual selections when auto-apply is OFF
                        const manualInput = document.getElementById('manualWinningNumber');
                        if (manualInput) {
                            const currentInputValue = manualInput.value.trim();
                            // Only update if auto-apply is ON, or if input is empty, or if it's the same number
                            if (isAutoApplyEnabled || currentInputValue === '' || currentInputValue === finalForcedNumber.toString()) {
                                manualInput.value = finalForcedNumber;
                            } else {
                                console.log('📌 Preserving manual input value:', currentInputValue, '(auto-apply is OFF)');
                            }
                        }

                        // Show Apply button
                        const applyContainer = document.getElementById('forcedNumberApplyContainer');
                        if (applyContainer) {
                            applyContainer.style.display = 'block';
                            applyContainer.setAttribute('data-forced-number', finalForcedNumber);
                            applyContainer.setAttribute('data-draw-number', targetDrawNumber);
                        }
                        
                        // Auto-apply ONLY if enabled AND no manual selection is in progress
                        // ⚠️ CRITICAL: Never auto-apply if user just manually set a number
                        if (isAutoApplyEnabled && !isManualSelectionInProgress) {
                            console.log('🔄 Auto-apply enabled, automatically applying number:', finalForcedNumber);
                            // Automatically apply after a short delay
                            setTimeout(() => {
                                // Double-check manual selection isn't in progress and auto-apply is still enabled
                                const currentAutoApply = document.getElementById('autoApplyForcedNumber')?.checked;
                                if (!isManualSelectionInProgress && currentAutoApply) {
                                    // ⚠️ IMPORTANT: Use applyPresetNumberAsForced which sets keep_auto_mode=true
                                    // This is intentional for auto-apply - it should use automatic mode
                                    applyPresetNumberAsForced(finalForcedNumber, null, null, false, 0, targetDrawNumber);
                                } else {
                                    console.log('⏸️ Auto-apply cancelled - manual selection in progress or auto-apply disabled');
                                }
                            }, 500);
                        } else {
                            // Manual mode or manual selection in progress - show toast notification and keep Apply button visible
                            if (isManualSelectionInProgress) {
                                console.log('📌 Manual selection in progress - skipping auto-apply');
                            } else if (!isAutoApplyEnabled) {
                                // Don't show toast in manual mode to avoid spam - just update the display
                                console.log('📌 Manual mode (auto-apply OFF) - waiting for user to click Apply or select a number');
                            } else {
                                showToast('Number Found', `Number ${finalForcedNumber} from ${numberSource} for draw #${targetDrawNumber}. Click "Apply" to use it.`, 'info');
                                console.log('📌 Manual mode - waiting for user to click Apply button');
                            }
                        }
                    } else {
                        // No forced number
                        forcedNumberBadge.textContent = '?';
                        forcedNumberBadge.className = 'forced-number-badge';
                        forcedNumberDraw.textContent = data.draw_number ? '#' + data.draw_number : '-';
                        forcedNumberMessage.textContent = 'No forced number is currently set';
                        
                        // Hide Apply button
                        const applyContainer = document.getElementById('forcedNumberApplyContainer');
                        if (applyContainer) {
                            applyContainer.style.display = 'none';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error checking forced number:', error);
                    forcedNumberStatus.textContent = 'Error checking forced number';
                    forcedNumberMessage.textContent = 'Error: ' + error.message;
                    forcedNumberBadge.classList.remove('checking');
                    forcedNumberBadge.textContent = '!';

                    // Show error toast
                    showToast('Error', 'Failed to check for forced numbers: ' + error.message, 'error');
                });
        }

        // Start periodic forced number checking
        function startForcedNumberCheck() {
            // Clear any existing interval
            if (forcedNumberCheckIntervalId) {
                clearInterval(forcedNumberCheckIntervalId);
            }

            // Check auto-apply setting
            const autoApplyCheckbox = document.getElementById('autoApplyForcedNumber');
            const isAutoApplyEnabled = autoApplyCheckbox ? autoApplyCheckbox.checked : false;

            // If auto-apply is OFF, don't auto-check (user can click "Check Now" button)
            // Only auto-check if auto-apply is ON
            if (!isAutoApplyEnabled) {
                console.log('⏸️ Forced number checking paused - auto-apply is OFF (manual mode)');
                const forcedNumberStatus = document.getElementById('forcedNumberStatus');
                if (forcedNumberStatus) {
                    forcedNumberStatus.innerHTML = 'Click "Check Now" to check for forced numbers';
                }
                return; // Don't start auto-checking
            }

            // Use shorter interval in auto mode for faster updates
            const interval = isAutoMode ? forcedNumberCheckIntervalAuto : forcedNumberCheckInterval;
            const intervalSeconds = interval / 1000;

            // Set up new interval
            forcedNumberCheckIntervalId = setInterval(checkForcedNumber, interval);
            console.log(`Started forced number checking every ${intervalSeconds} seconds (auto-apply: ${isAutoApplyEnabled}, mode: ${isAutoMode ? 'auto' : 'manual'})`);

            // Update status to show auto-checking
            const forcedNumberStatus = document.getElementById('forcedNumberStatus');
            if (forcedNumberStatus) {
                forcedNumberStatus.innerHTML = `Auto-checking every ${intervalSeconds} seconds <i class="fas fa-sync-alt fa-spin fa-sm"></i>`;
            }
            
            // Run initial check
            checkForcedNumber();
        }

        // Stop periodic forced number checking
        function stopForcedNumberCheck() {
            if (forcedNumberCheckIntervalId) {
                clearInterval(forcedNumberCheckIntervalId);
                forcedNumberCheckIntervalId = null;
                console.log('Stopped forced number checking');

                // Update status
                const forcedNumberStatus = document.getElementById('forcedNumberStatus');
                if (forcedNumberStatus) {
                    forcedNumberStatus.textContent = 'Auto-checking stopped';
                }
            }
        }

        // Function to calculate the next draw time based on real-time of day
        function calculateNextDrawTime() {
            const now = new Date();
            const currentMinutes = now.getMinutes();
            const currentSeconds = now.getSeconds();

            // Calculate minutes until next 3-minute interval
            // We want draws to happen every 3 minutes: at :00, :03, :06, :09, etc.
            const minutesUntilNextDraw = 3 - (currentMinutes % 3);
            let secondsUntilNextDraw = (minutesUntilNextDraw * 60) - currentSeconds;

            // If we're exactly at a 3-minute mark, set for the next one
            if (secondsUntilNextDraw === 0 || secondsUntilNextDraw === 180) {
                secondsUntilNextDraw = 180;
            }

            console.log(`Next draw in ${Math.floor(secondsUntilNextDraw/60)}:${(secondsUntilNextDraw%60).toString().padStart(2, '0')} (${secondsUntilNextDraw} seconds)`);

            // Calculate the exact timestamp for the next draw
            const nextDrawTime = new Date(now.getTime() + (secondsUntilNextDraw * 1000));

            return {
                secondsRemaining: secondsUntilNextDraw,
                timestamp: nextDrawTime.getTime(),
                formattedTime: nextDrawTime.toLocaleTimeString()
            };
        }

        // Function to sync timer with TV display
        function syncTimerWithTVDisplay() {
            console.log('Syncing timer with TV display...');

            try {
                // Calculate the next draw time based on real-time of day
                const nextDraw = calculateNextDrawTime();

                // Update timer value
                timerValue = nextDraw.secondsRemaining;

                // Store the end time in localStorage for persistence
                localStorage.setItem('adminCountdownEndTime', nextDraw.timestamp.toString());

                console.log(`Timer synced to real-time: ${timerValue} seconds until next draw at ${nextDraw.formattedTime}`);

                // Update timer display
                updateTimerDisplay();

                // Auto-start timer if not already running
                if (!timerRunning) {
                    startTimer();
                }

                isTimerSynced = true;

                // Show sync indicator
                const syncIndicator = document.getElementById('timerSyncIndicator');
                if (syncIndicator) {
                    syncIndicator.style.display = 'flex';
                }

                // Show success notification
                showToast('Timer Synchronized', 'Timer has been synchronized with TV display using real-time intervals', 'success');

                // Also fetch from API to ensure we have the latest timer settings
                fetch('../api/draw_info.php')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.status === 'success') {
                            console.log('Timer settings received from API:', data);

                            // Update timer interval if needed
                            if (data.data.timer_seconds !== undefined) {
                                const newInterval = parseInt(data.data.timer_seconds);
                                if (!isNaN(newInterval) && newInterval > 0) {
                                    timerInterval = newInterval;
                                    document.getElementById('timerInterval').value = timerInterval;
                                    document.getElementById('timerInterval3d').value = timerInterval;
                                }
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching timer settings:', error);
                    });
            } catch (error) {
                console.error('Error syncing timer with real-time:', error);
                showToast('Sync Error', 'Failed to sync timer with real-time', 'error');

                // Fallback to API method if real-time sync fails
                fetch('../api/draw_info.php')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.status === 'success' && data.data.countdown !== undefined) {
                            const newTimerValue = parseInt(data.data.countdown);
                            if (!isNaN(newTimerValue) && newTimerValue > 0) {
                                timerValue = newTimerValue;
                                updateTimerDisplay();
                                if (!timerRunning) startTimer();
                                showToast('Timer Synchronized', 'Timer has been synchronized with TV display via API', 'success');
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error syncing timer via API:', error);
                        showToast('Sync Error', 'Failed to connect to TV display timer', 'error');
                    });
            }
        }

        // Start periodic timer sync
        function startTimerSync() {
            // Clear any existing interval
            if (timerSyncIntervalId) {
                clearInterval(timerSyncIntervalId);
            }

            // Set up new interval
            timerSyncIntervalId = setInterval(syncTimerWithTVDisplay, timerSyncInterval);
            console.log(`Started timer sync every ${timerSyncInterval/1000} seconds`);
        }

        // Stop periodic timer sync
        function stopTimerSync() {
            if (timerSyncIntervalId) {
                clearInterval(timerSyncIntervalId);
                timerSyncIntervalId = null;
                console.log('Stopped timer sync');
            }
        }

        // Initialize 3D timer
        function initTimer3D() {
            if (isTimerInitialized) return;

            // Initialize desktop 3D timer
            const container = document.getElementById('timer3dScene');
            if (!container) return;

            // Create scene
            scene = new THREE.Scene();

            // Create camera
            camera = new THREE.PerspectiveCamera(75, container.clientWidth / container.clientHeight, 0.1, 1000);
            camera.position.z = 5;

            // Create renderer
            renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
            renderer.setSize(container.clientWidth, container.clientHeight);
            renderer.setClearColor(0x000000, 0);
            container.appendChild(renderer.domElement);

            // Add ambient light
            const ambientLight = new THREE.AmbientLight(0xffffff, 0.5);
            scene.add(ambientLight);

            // Add directional light
            const directionalLight = new THREE.DirectionalLight(0xffffff, 1);
            directionalLight.position.set(5, 5, 5);
            scene.add(directionalLight);

            // Create particles
            createParticles();

            // Start animation loop
            animate();

            // Handle window resize
            window.addEventListener('resize', onWindowResize);

            // Sync with timer value
            updateTimer3DDisplay();

            // Initialize mobile 3D timer if it exists
            initMobileTimer3D();

            isTimerInitialized = true;
        }

        // Initialize mobile 3D timer
        function initMobileTimer3D() {
            const mobileContainer = document.getElementById('timer3dScene-mobile');
            if (!mobileContainer) return;

            // Create a separate scene for mobile
            const mobileScene = new THREE.Scene();

            // Create camera
            const mobileCamera = new THREE.PerspectiveCamera(75, mobileContainer.clientWidth / mobileContainer.clientHeight, 0.1, 1000);
            mobileCamera.position.z = 5;

            // Create renderer
            const mobileRenderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
            mobileRenderer.setSize(mobileContainer.clientWidth, mobileContainer.clientHeight);
            mobileRenderer.setClearColor(0x000000, 0);
            mobileContainer.appendChild(mobileRenderer.domElement);

            // Add ambient light
            const ambientLight = new THREE.AmbientLight(0xffffff, 0.5);
            mobileScene.add(ambientLight);

            // Add directional light
            const directionalLight = new THREE.DirectionalLight(0xffffff, 1);
            directionalLight.position.set(5, 5, 5);
            mobileScene.add(directionalLight);

            // Create particles for mobile
            const particleCount = 100; // Fewer particles for mobile
            const particleGeometry = new THREE.BufferGeometry();
            const particleMaterial = new THREE.PointsMaterial({
                color: 0xffffff,
                size: 0.05,
                transparent: true,
                opacity: 0.8,
                blending: THREE.AdditiveBlending
            });

            const positions = new Float32Array(particleCount * 3);
            const velocities = [];

            for (let i = 0; i < particleCount; i++) {
                const i3 = i * 3;
                positions[i3] = (Math.random() - 0.5) * 10;
                positions[i3 + 1] = (Math.random() - 0.5) * 10;
                positions[i3 + 2] = (Math.random() - 0.5) * 10;

                velocities.push({
                    x: (Math.random() - 0.5) * 0.02,
                    y: (Math.random() - 0.5) * 0.02,
                    z: (Math.random() - 0.5) * 0.02
                });
            }

            particleGeometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
            const mobileParticleSystem = new THREE.Points(particleGeometry, particleMaterial);
            mobileScene.add(mobileParticleSystem);

            // Store velocities for animation
            mobileParticleSystem.userData.velocities = velocities;

            // Animation function for mobile
            function animateMobile() {
                requestAnimationFrame(animateMobile);

                // Rotate camera slightly for subtle movement
                const time = clock.getElapsedTime() * 0.2;
                mobileCamera.position.x = Math.sin(time) * 0.5;
                mobileCamera.position.y = Math.cos(time) * 0.3;
                mobileCamera.lookAt(0, 0, 0);

                // Animate particles
                const positions = mobileParticleSystem.geometry.attributes.position.array;
                const velocities = mobileParticleSystem.userData.velocities;

                for (let i = 0; i < positions.length / 3; i++) {
                    const i3 = i * 3;

                    positions[i3] += velocities[i].x;
                    positions[i3 + 1] += velocities[i].y;
                    positions[i3 + 2] += velocities[i].z;

                    // Boundary check and reset
                    if (positions[i3] < -5 || positions[i3] > 5) velocities[i].x *= -1;
                    if (positions[i3 + 1] < -5 || positions[i3 + 1] > 5) velocities[i].y *= -1;
                    if (positions[i3 + 2] < -5 || positions[i3 + 2] > 5) velocities[i].z *= -1;
                }

                mobileParticleSystem.geometry.attributes.position.needsUpdate = true;

                mobileRenderer.render(mobileScene, mobileCamera);
            }

            // Start animation
            animateMobile();

            // Handle window resize for mobile
            function onMobileWindowResize() {
                if (!mobileContainer || !mobileCamera || !mobileRenderer) return;

                mobileCamera.aspect = mobileContainer.clientWidth / mobileContainer.clientHeight;
                mobileCamera.updateProjectionMatrix();
                mobileRenderer.setSize(mobileContainer.clientWidth, mobileContainer.clientHeight);
            }

            window.addEventListener('resize', onMobileWindowResize);

            console.log('Mobile 3D timer initialized');
        }

        // Create particles for background effect
        function createParticles() {
            const particleCount = 200;
            const particleGeometry = new THREE.BufferGeometry();
            const particleMaterial = new THREE.PointsMaterial({
                color: 0xffffff,
                size: 0.05,
                transparent: true,
                opacity: 0.8,
                blending: THREE.AdditiveBlending
            });

            const positions = new Float32Array(particleCount * 3);
            const velocities = [];

            for (let i = 0; i < particleCount; i++) {
                const i3 = i * 3;
                positions[i3] = (Math.random() - 0.5) * 10;
                positions[i3 + 1] = (Math.random() - 0.5) * 10;
                positions[i3 + 2] = (Math.random() - 0.5) * 10;

                velocities.push({
                    x: (Math.random() - 0.5) * 0.02,
                    y: (Math.random() - 0.5) * 0.02,
                    z: (Math.random() - 0.5) * 0.02
                });
            }

            particleGeometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
            particleSystem = new THREE.Points(particleGeometry, particleMaterial);
            scene.add(particleSystem);

            // Store velocities for animation
            particleSystem.userData.velocities = velocities;
        }

        // Animation loop
        function animate() {
            animationFrameId = requestAnimationFrame(animate);

            // Rotate camera slightly for subtle movement
            const time = clock.getElapsedTime() * 0.2;
            camera.position.x = Math.sin(time) * 0.5;
            camera.position.y = Math.cos(time) * 0.3;
            camera.lookAt(0, 0, 0);

            // Animate particles
            if (particleSystem) {
                const positions = particleSystem.geometry.attributes.position.array;
                const velocities = particleSystem.userData.velocities;

                for (let i = 0; i < positions.length / 3; i++) {
                    const i3 = i * 3;

                    positions[i3] += velocities[i].x;
                    positions[i3 + 1] += velocities[i].y;
                    positions[i3 + 2] += velocities[i].z;

                    // Boundary check and reset
                    if (positions[i3] < -5 || positions[i3] > 5) velocities[i].x *= -1;
                    if (positions[i3 + 1] < -5 || positions[i3 + 1] > 5) velocities[i].y *= -1;
                    if (positions[i3 + 2] < -5 || positions[i3 + 2] > 5) velocities[i].z *= -1;
                }

                particleSystem.geometry.attributes.position.needsUpdate = true;
            }

            renderer.render(scene, camera);
        }

        // Handle window resize
        function onWindowResize() {
            const container = document.getElementById('timer3dScene');
            if (!container || !camera || !renderer) return;

            camera.aspect = container.clientWidth / container.clientHeight;
            camera.updateProjectionMatrix();
            renderer.setSize(container.clientWidth, container.clientHeight);
        }

        // Update 3D timer display
        function updateTimer3DDisplay() {
            const minutes = Math.floor(timerValue / 60);
            const seconds = timerValue % 60;
            const timeString = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

            // Update the desktop timer display
            const timerDisplay = document.getElementById('timer3dDisplay');
            if (timerDisplay) {
                timerDisplay.textContent = timeString;

                // Add pulse animation when time is low
                if (timerValue <= 10) {
                    timerDisplay.style.animation = 'pulse 0.5s infinite alternate';
                    timerDisplay.style.color = '#ff5252';
                } else {
                    timerDisplay.style.animation = '';
                    timerDisplay.style.color = 'white';
                }
            }

            // Update the mobile timer display if it exists
            const mobileTimerDisplay = document.getElementById('timer3dDisplay-mobile');
            if (mobileTimerDisplay) {
                mobileTimerDisplay.textContent = timeString;

                // Add pulse animation when time is low
                if (timerValue <= 10) {
                    mobileTimerDisplay.style.animation = 'pulse 0.5s infinite alternate';
                    mobileTimerDisplay.style.color = '#ff5252';
                } else {
                    mobileTimerDisplay.style.animation = '';
                    mobileTimerDisplay.style.color = 'white';
                }
            }

            // Update desktop sync indicator
            const syncIndicator = document.getElementById('timerSyncIndicator');
            if (syncIndicator) {
                syncIndicator.style.display = isTimerSynced ? 'flex' : 'none';

                // Update the sync indicator text to show it's using real-time
                if (isTimerSynced) {
                    const syncText = syncIndicator.querySelector('.sync-text');
                    if (syncText) {
                        syncText.textContent = 'Synced with real-time';
                    }
                }
            }

            // Update mobile sync indicator if it exists
            const mobileSyncIndicator = document.getElementById('timerSyncIndicator-mobile');
            if (mobileSyncIndicator) {
                mobileSyncIndicator.style.display = isTimerSynced ? 'flex' : 'none';

                // Update the sync indicator text to show it's using real-time
                if (isTimerSynced) {
                    const syncText = mobileSyncIndicator.querySelector('.sync-text');
                    if (syncText) {
                        syncText.textContent = 'Synced';
                    }
                }
            }

            // Sync with original timer
            document.getElementById('timerInterval3d').value = document.getElementById('timerInterval').value;

            // Sync with mobile timer if it exists
            if (document.getElementById('timerInterval3d-mobile')) {
                document.getElementById('timerInterval3d-mobile').value = document.getElementById('timerInterval').value;
            }

            // If we have a saved end time, calculate and display the exact time of the next draw
            const savedEndTime = localStorage.getItem('adminCountdownEndTime');
            if (savedEndTime && !isNaN(parseInt(savedEndTime))) {
                const nextDrawTime = new Date(parseInt(savedEndTime));
                const timeString = nextDrawTime.toLocaleTimeString();

                // Update desktop display
                const nextDrawTimeDisplay = document.getElementById('nextDrawTimeDisplay');
                if (nextDrawTimeDisplay) {
                    nextDrawTimeDisplay.textContent = timeString;
                }

                // Update mobile display if it exists
                const mobileNextDrawTimeDisplay = document.getElementById('nextDrawTimeDisplay-mobile');
                if (mobileNextDrawTimeDisplay) {
                    mobileNextDrawTimeDisplay.textContent = timeString;
                }
            }
        }

        // Function to clear all loading states
        function clearAllLoadingStates() {
            console.log('🧹 Clearing all loading states...');
            
            // Clear draw number
            const drawNumberEl = document.getElementById('upcomingDrawNumber');
            if (drawNumberEl && drawNumberEl.textContent === 'Loading...') {
                drawNumberEl.textContent = 'N/A';
            }
            
            // Clear draw tabs loading state
            const drawTabs = document.getElementById('drawTabs');
            if (drawTabs) {
                const loadingTab = drawTabs.querySelector('.draw-tab.loading');
                if (loadingTab) {
                    loadingTab.remove();
                }
            }
            
            // Clear overview table loading state
            const overviewTable = document.querySelector('#upcomingDrawsTable tbody');
            if (overviewTable) {
                const loadingRow = overviewTable.querySelector('tr');
                if (loadingRow && loadingRow.textContent.includes('Loading')) {
                    overviewTable.innerHTML = '';
                }
            }
            
            // Clear chart container loading indicator
            const chartContainer = document.getElementById('chartContainer');
            if (chartContainer) {
                const loadingIndicator = chartContainer.querySelector('.loading-indicator');
                if (loadingIndicator) {
                    loadingIndicator.remove();
                }
            }
            
            // Clear grid container loading indicator
            const gridContainer = document.getElementById('betInfoGrid');
            if (gridContainer) {
                const loadingIndicator = gridContainer.querySelector('.loading-indicator');
                if (loadingIndicator) {
                    loadingIndicator.remove();
                }
            }
            
            // Clear bet type chart loading indicator
            const betTypeChart = document.getElementById('betTypeChartContainer');
            if (betTypeChart) {
                const loadingIndicator = betTypeChart.querySelector('.loading-indicator');
                if (loadingIndicator) {
                    loadingIndicator.remove();
                }
            }
        }

        // Function to fetch bet distribution data for multiple draws
        async function fetchBetDistribution() {
            console.log('🔄 Starting fetchBetDistribution...');
            console.log('🔄 Current URL:', window.location.href);
            console.log('🔄 Loading overlay element:', loadingOverlay);
            console.log('🔄 showLoading function exists:', typeof showLoading);
            
            try {
                if (typeof showLoading === 'function') {
                    showLoading(true);
                } else {
                    console.error('❌ showLoading is not a function!');
                    if (loadingOverlay) {
                        loadingOverlay.style.display = 'flex';
                    } else {
                        console.error('❌ loadingOverlay is also null!');
                    }
                }
            } catch (e) {
                console.error('❌ Error showing loading overlay:', e);
                console.error('Error stack:', e.stack);
                if (loadingOverlay) {
                    loadingOverlay.style.display = 'flex';
                }
            }

            try {
                // First, get the upcoming draws data from the existing API
                console.log('📡 Fetching upcoming draws data from ../api/upcoming_draws_stats.php...');
                const upcomingResponse = await fetch('../api/upcoming_draws_stats.php?count=10&_cb=' + Date.now());

                if (!upcomingResponse.ok) {
                    const errorText = await upcomingResponse.text().catch(() => 'Unable to read error response');
                    console.error('❌ API Response Error:', {
                        status: upcomingResponse.status,
                        statusText: upcomingResponse.statusText,
                        url: upcomingResponse.url,
                        body: errorText.substring(0, 500) // First 500 chars
                    });
                    throw new Error(`HTTP error! status: ${upcomingResponse.status} - ${upcomingResponse.statusText}`);
                }

                const upcomingData = await upcomingResponse.json();
                console.log('✅ Fetched upcoming draws data:', upcomingData);

                if (upcomingData.status === 'success') {
                    const upcomingDraws = upcomingData.data.upcoming_draws;
                    currentDrawNumber = upcomingData.data.base_draw;

                    // Fetch bet distribution for each draw
                    console.log(`📊 Fetching bet distribution for ${upcomingDraws.length} draws...`);
                    const drawPromises = upcomingDraws.map(async (draw) => {
                        try {
                            console.log(`📊 Fetching bet distribution for draw #${draw.draw_number}...`);
                            const betResponse = await fetch(`../php/get_bet_distribution.php?draw=${draw.draw_number}&_cb=${Date.now()}`);
                            
                            if (!betResponse.ok) {
                                console.error(`❌ Failed to fetch bet distribution for draw #${draw.draw_number}:`, betResponse.status, betResponse.statusText);
                                throw new Error(`HTTP ${betResponse.status} for draw ${draw.draw_number}`);
                            }
                            
                            const betData = await betResponse.json();
                            console.log(`✅ Got bet distribution for draw #${draw.draw_number}:`, betData);

                            if (betData.status === 'success') {
                                // Convert numbers array to object if needed
                                let numbersData = betData.numbers;
                                if (Array.isArray(numbersData)) {
                                    const numbersObj = {};
                                    numbersData.forEach((data, index) => {
                                        numbersObj[index] = data;
                                    });
                                    numbersData = numbersObj;
                                }

                                // Combine upcoming draw info with bet distribution
                                return {
                                    ...betData,
                                    numbers: numbersData,
                                    estimated_time: draw.estimated_time,
                                    estimated_datetime: draw.estimated_datetime,
                                    betting_slips_count: draw.betting_slips_count,
                                    total_stake_amount: draw.total_stake_amount,
                                    total_potential_payout: draw.total_potential_payout,
                                    is_next: draw.is_next,
                                    minutes_from_now: draw.minutes_from_now
                                };
                            } else {
                                // Return empty data structure for failed draws
                                const emptyNumbers = {};
                                for (let i = 0; i <= 36; i++) {
                                    emptyNumbers[i] = {bet_count: 0, total_payout: 0};
                                }
                                return {
                                    status: 'success',
                                    draw_number: draw.draw_number,
                                    total_bets: 0,
                                    numbers: emptyNumbers,
                                    bet_types: {},
                                    estimated_time: draw.estimated_time,
                                    estimated_datetime: draw.estimated_datetime,
                                    betting_slips_count: draw.betting_slips_count,
                                    total_stake_amount: draw.total_stake_amount,
                                    total_potential_payout: draw.total_potential_payout,
                                    is_next: draw.is_next,
                                    minutes_from_now: draw.minutes_from_now
                                };
                            }
                        } catch (error) {
                            console.error(`Error fetching bet distribution for draw ${draw.draw_number}:`, error);
                            // Return empty data structure for failed draws
                            const emptyNumbers = {};
                            for (let i = 0; i <= 36; i++) {
                                emptyNumbers[i] = {bet_count: 0, total_payout: 0};
                            }
                            return {
                                status: 'success',
                                draw_number: draw.draw_number,
                                total_bets: 0,
                                numbers: emptyNumbers,
                                bet_types: {},
                                estimated_time: draw.estimated_time,
                                estimated_datetime: draw.estimated_datetime,
                                betting_slips_count: draw.betting_slips_count,
                                total_stake_amount: draw.total_stake_amount,
                                total_potential_payout: draw.total_potential_payout,
                                is_next: draw.is_next,
                                minutes_from_now: draw.minutes_from_now
                            };
                        }
                    });

                    // Wait for all bet distribution requests to complete
                    console.log('⏳ Waiting for all bet distribution requests to complete...');
                    allDrawsData = await Promise.all(drawPromises);
                    console.log(`✅ All ${allDrawsData.length} draws loaded successfully`);

                    // Clear loading states before updating
                    clearAllLoadingStates();

                    // Update the overview table
                    console.log('📋 Updating overview table...');
                    updateUpcomingDrawsOverview(allDrawsData);

                    // Update the draw tabs
                    console.log('📑 Updating draw tabs...');
                    updateDrawTabs(allDrawsData);

                    // Select the first draw by default
                    if (allDrawsData.length > 0) {
                        console.log('🎯 Selecting first draw...');
                        selectDraw(0);
                    } else {
                        console.warn('⚠️ No draws data available');
                        showError('No upcoming draws found. Please check your database.');
                    }

                    console.log('🕐 Updating last updated time...');
                    updateLastUpdated();
                    console.log('✅ All UI updates completed!');
                } else {
                    console.error('❌ Failed to fetch upcoming draws data:', upcomingData);
                    const errorMsg = upcomingData.message || upcomingData.error || 'Unknown error from API';
                    console.error('Error message:', errorMsg);
                    // Clear loading states before showing error
                    clearAllLoadingStates();
                    showError('Failed to fetch upcoming draws: ' + errorMsg);
                    // Fallback to single draw mode
                    try {
                        await fetchSingleDrawFallback();
                    } catch (fallbackError) {
                        console.error('❌ Fallback also failed:', fallbackError);
                        clearAllLoadingStates();
                        showError('Unable to load bet distribution data. Please check your connection and try again.');
                    }
                }
            } catch (error) {
                console.error('❌ Error fetching bet distribution:', error);
                console.error('Error details:', {
                    message: error.message,
                    stack: error.stack,
                    name: error.name
                });
                
                // Show error to user
                const errorMsg = error.message || 'Failed to fetch bet distribution data';
                showError('Error: ' + errorMsg + '. Trying fallback...');
                
                // Fallback to single draw mode
                try {
                    console.log('🔄 Attempting fallback mode...');
                    await fetchSingleDrawFallback();
                } catch (fallbackError) {
                    console.error('❌ Fallback also failed:', fallbackError);
                    showError('Unable to load bet distribution data. Please check your connection and try again. Error: ' + fallbackError.message);
                }
            } finally {
                console.log('✅ fetchBetDistribution completed, hiding loading overlay');
                try {
                    showLoading(false);
                    // Clear any remaining loading states
                    clearAllLoadingStates();
                } catch (e) {
                    console.error('❌ Error hiding loading overlay:', e);
                    // Force hide the overlay
                    if (loadingOverlay) {
                        loadingOverlay.style.display = 'none';
                    }
                    // Still try to clear loading states
                    try {
                        clearAllLoadingStates();
                    } catch (clearError) {
                        console.error('❌ Error clearing loading states:', clearError);
                    }
                }
            }
        }

        // Fallback function to fetch single draw (original behavior)
        async function fetchSingleDrawFallback() {
            console.log('Using fallback single draw mode...');
            try {
                const response = await fetch('../php/get_bet_distribution.php?upcoming=1');

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                console.log('Fetched single draw data:', data);

                if (data.status === 'success') {
                    // Convert numbers array to object if needed
                    let numbersData = data.numbers;
                    if (Array.isArray(numbersData)) {
                        const numbersObj = {};
                        numbersData.forEach((numberData, index) => {
                            numbersObj[index] = numberData;
                        });
                        numbersData = numbersObj;
                    }

                    // Create a single draw array for compatibility
                    allDrawsData = [{
                        ...data,
                        numbers: numbersData,
                        estimated_time: 'TBD',
                        estimated_datetime: null,
                        betting_slips_count: 0,
                        total_stake_amount: 0,
                        total_potential_payout: 0,
                        is_next: true,
                        minutes_from_now: 3
                    }];

                    currentData = data;
                    upcomingDrawNumber = data.draw_number;

                    // Hide the overview panel and tabs since we only have one draw
                    document.querySelector('.upcoming-draws-overview').style.display = 'none';
                    document.querySelector('.draw-selection-tabs').style.display = 'none';

                    // Update the UI with single draw
                    updateBetDistributionUI(data);
                    updateLastUpdated();
                } else {
                    showError(data.message || 'Failed to fetch bet distribution data');
                }
            } catch (error) {
                console.error('Error in fallback mode:', error);
                showError('Fallback mode failed: ' + error.message);
            } finally {
                console.log('✅ fetchSingleDrawFallback completed, hiding loading overlay');
                try {
                    showLoading(false);
                } catch (e) {
                    console.error('❌ Error hiding loading overlay:', e);
                    if (loadingOverlay) {
                        loadingOverlay.style.display = 'none';
                    }
                }
            }
        }

        // Function to update the upcoming draws overview table
        function updateUpcomingDrawsOverview(draws) {
            const tableBody = document.querySelector('#upcomingDrawsTable tbody');
            if (!tableBody) {
                console.warn('⚠️ Overview table body not found');
                return;
            }
            tableBody.innerHTML = '';

            draws.forEach((draw, index) => {
                const row = document.createElement('tr');
                row.className = `draw-row ${draw.is_next ? 'current' : ''}`;
                row.setAttribute('data-draw-index', index);

                const formatCurrency = (amount) => {
                    return new Intl.NumberFormat('en-US', {
                        style: 'currency',
                        currency: 'USD'
                    }).format(amount);
                };

                row.innerHTML = `
                    <td>
                        <strong>#${draw.draw_number}</strong>
                        ${draw.is_next ? '<span class="badge bg-success ms-1">Current</span>' : ''}
                    </td>
                    <td>${draw.estimated_time || 'TBD'}</td>
                    <td>
                        <span class="badge bg-info">${draw.betting_slips_count}</span>
                    </td>
                    <td>${formatCurrency(draw.total_stake_amount)}</td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="selectDraw(${index})">
                            <i class="fas fa-eye"></i> View
                        </button>
                    </td>
                `;

                // Add click event to select draw
                row.addEventListener('click', () => selectDraw(index));

                tableBody.appendChild(row);
            });
        }

        // Function to update the draw tabs
        function updateDrawTabs(draws) {
            const tabsContainer = document.getElementById('drawTabs');
            tabsContainer.innerHTML = '';

            draws.forEach((draw, index) => {
                const tab = document.createElement('div');
                tab.className = `draw-tab ${draw.is_next ? 'current' : ''}`;
                tab.setAttribute('data-draw-index', index);

                tab.innerHTML = `
                    <div class="draw-tab-number">#${draw.draw_number}</div>
                    <div class="draw-tab-time">${draw.estimated_time || 'TBD'}</div>
                    <div class="draw-tab-stats">${draw.betting_slips_count} slips</div>
                `;

                // Add click event to select draw
                tab.addEventListener('click', () => selectDraw(index));

                tabsContainer.appendChild(tab);
            });

            // Update navigation buttons
            updateNavigationButtons();
        }

        // Function to select a specific draw
        function selectDraw(index) {
            if (index < 0 || index >= allDrawsData.length) return;

            selectedDrawIndex = index;
            const selectedDraw = allDrawsData[index];

            // Update current data
            currentData = selectedDraw;
            upcomingDrawNumber = selectedDraw.draw_number;

            // Update the UI
            updateBetDistributionUI(selectedDraw);

            // Update visual selection in tabs
            updateTabSelection();

            // Update visual selection in overview table
            updateTableSelection();

            // Update navigation buttons
            updateNavigationButtons();
        }

        // Function to update tab selection visual state
        function updateTabSelection() {
            const tabs = document.querySelectorAll('.draw-tab');
            tabs.forEach((tab, index) => {
                if (index === selectedDrawIndex) {
                    tab.classList.add('active');
                } else {
                    tab.classList.remove('active');
                }
            });
        }

        // Function to update table selection visual state
        function updateTableSelection() {
            const rows = document.querySelectorAll('.draw-row');
            rows.forEach((row, index) => {
                if (index === selectedDrawIndex) {
                    row.classList.add('selected');
                } else {
                    row.classList.remove('selected');
                }
            });
        }

        // Function to update navigation buttons
        function updateNavigationButtons() {
            const prevBtn = document.getElementById('prevDraw');
            const nextBtn = document.getElementById('nextDraw');

            prevBtn.disabled = selectedDrawIndex <= 0;
            nextBtn.disabled = selectedDrawIndex >= allDrawsData.length - 1;
        }

        // Function to navigate to previous draw
        function navigatePrevDraw() {
            if (selectedDrawIndex > 0) {
                selectDraw(selectedDrawIndex - 1);
            }
        }

        // Function to navigate to next draw
        function navigateNextDraw() {
            if (selectedDrawIndex < allDrawsData.length - 1) {
                selectDraw(selectedDrawIndex + 1);
            }
        }

        // Function to update the bet distribution UI
        function updateBetDistributionUI(data) {
            // Clear loading states first
            const drawNumberEl = document.getElementById('upcomingDrawNumber');
            if (drawNumberEl) {
                drawNumberEl.textContent = `#${data.draw_number}`;
            } else {
                console.warn('⚠️ upcomingDrawNumber element not found');
            }

            const statusBadge = document.getElementById('drawStatus');
            if (data.is_next) {
                statusBadge.textContent = 'Current';
                statusBadge.className = 'badge bg-success';
            } else {
                statusBadge.textContent = `+${data.minutes_from_now} min`;
                statusBadge.className = 'badge bg-primary';
            }

            // Update chart view
            updateBetDistributionChart(data);

            // Update grid view
            updateBetDistributionGrid(data);

            // Update bet type distribution chart
            updateBetTypeDistributionChart(data);

            // Generate recommendations based on the data
            generateRecommendations();
        }

        // Function to generate number recommendations
        function generateRecommendations() {
            if (!currentData || !currentData.numbers) {
                showToast('Error', 'No bet distribution data available for recommendations', 'error');
                return;
            }

            // Clear previous recommendations
            recommendedNumbers = {
                'no-bets': [],
                'lowest-payout': [],
                'highest-payout': []
            };

            // Get numbers with no bets
            const numbersWithNoBets = [];
            for (let i = 0; i <= 36; i++) {
                const numberData = currentData.numbers[i] || { bet_count: 0, total_payout: 0 };
                if (numberData.bet_count === 0) {
                    numbersWithNoBets.push({
                        number: i,
                        color: getNumberColor(i),
                        bet_count: 0,
                        total_payout: 0,
                        reason: 'No bets placed on this number'
                    });
                }
            }
            recommendedNumbers['no-bets'] = numbersWithNoBets;

            // Get numbers with bets, sorted by payout
            const numbersWithBets = [];
            for (let i = 0; i <= 36; i++) {
                const numberData = currentData.numbers[i] || { bet_count: 0, total_payout: 0 };
                if (numberData.bet_count > 0) {
                    numbersWithBets.push({
                        number: i,
                        color: getNumberColor(i),
                        bet_count: numberData.bet_count,
                        total_payout: numberData.total_payout || 0,
                        reason: `${numberData.bet_count} bets, ${formatCurrency(numberData.total_payout || 0)} payout`
                    });
                }
            }

            // Sort by payout (ascending for lowest, descending for highest)
            const lowestPayout = [...numbersWithBets].sort((a, b) => a.total_payout - b.total_payout);
            const highestPayout = [...numbersWithBets].sort((a, b) => b.total_payout - a.total_payout);

            recommendedNumbers['lowest-payout'] = lowestPayout.slice(0, 10); // Top 10 lowest payout
            recommendedNumbers['highest-payout'] = highestPayout.slice(0, 10); // Top 10 highest payout

            // Update the display with the current recommendation type
            displayRecommendations(currentRecommendationType);

            console.log('Generated recommendations:', recommendedNumbers);
        }

        // Function to display recommendations
        function displayRecommendations(type, isMobile = false) {
            // Determine which container to use based on isMobile flag
            const containerId = isMobile ? 'recommendedNumbersGrid-mobile' : 'recommendedNumbersGrid';
            const container = document.getElementById(containerId);

            if (!container) {
                console.error(`Container ${containerId} not found`);
                return;
            }

            container.innerHTML = '';

            const recommendations = recommendedNumbers[type] || [];

            if (recommendations.length === 0) {
                container.innerHTML = `
                    <div class="no-recommendations">
                        <i class="fas fa-info-circle"></i> No ${type.replace('-', ' ')} recommendations available
                    </div>
                `;
                return;
            }

            // Limit the number of recommendations for mobile to prevent overflow
            const displayRecommendations = isMobile ? recommendations.slice(0, 6) : recommendations;

            // Create recommendation items
            displayRecommendations.forEach(rec => {
                const item = document.createElement('div');
                item.className = 'recommended-number-item';
                item.setAttribute('data-number', rec.number);

                let label, value;
                if (type === 'no-bets') {
                    label = 'Bet Count';
                    value = '0';
                } else if (type === 'lowest-payout') {
                    label = 'Payout';
                    value = formatCurrency(rec.total_payout);
                } else if (type === 'highest-payout') {
                    label = 'Payout';
                    value = formatCurrency(rec.total_payout);
                }

                // Simplified display for mobile
                if (isMobile) {
                    item.innerHTML = `
                        <div class="recommended-number-badge ${rec.color}">${rec.number}</div>
                        <div class="recommended-number-info">
                            <div class="recommended-number-value">${value}</div>
                        </div>
                    `;
                } else {
                    item.innerHTML = `
                        <div class="recommended-number-badge ${rec.color}">${rec.number}</div>
                        <div class="recommended-number-info">
                            <div class="recommended-number-label">${label}</div>
                            <div class="recommended-number-value">${value}</div>
                            <div class="recommended-number-reason">${rec.reason}</div>
                        </div>
                    `;
                }

                // Add click event to set the winning number
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const number = parseInt(this.getAttribute('data-number'));
                    console.log('🎯 Recommended number clicked:', { number, isMobile, currentDrawNumber });
                    
                    if (isNaN(number) || number < 0 || number > 36) {
                        console.error('❌ Invalid number from click:', number);
                        showError('Invalid number selected');
                        return;
                    }
                    
                    if (isMobile) {
                        // For mobile, set the mobile input value first
                        const mobileInput = document.getElementById('manualWinningNumber-mobile');
                        const mobileButton = document.getElementById('setManualWinningNumber-mobile');
                        if (mobileInput && mobileButton) {
                            mobileInput.value = number;
                            mobileButton.click();
                        } else {
                            console.error('❌ Mobile elements not found');
                            showError('Mobile input elements not found');
                        }
                    } else {
                        // Desktop - always allow manual selection regardless of auto-apply setting
                        console.log('📌 Manual selection from recommended numbers (desktop)');
                        setRecommendedNumber(number);
                    }
                });

                container.appendChild(item);
            });

            // If on mobile and there are more recommendations than we're showing
            if (isMobile && recommendations.length > 6) {
                const moreItem = document.createElement('div');
                moreItem.className = 'recommended-number-item more-item';
                moreItem.innerHTML = `
                    <div class="recommended-number-badge" style="background-color: #6c757d;">+${recommendations.length - 6}</div>
                    <div class="recommended-number-info">
                        <div class="recommended-number-value">More</div>
                    </div>
                `;
                container.appendChild(moreItem);
            }
        }

        // Function to set a recommended number as the winning number
        function setRecommendedNumber(number) {
            console.log('🎯 setRecommendedNumber called:', { number, currentDrawNumber });
            
            // Set flag to prevent forced number checker from interfering
            isManualSelectionInProgress = true;
            
            // Validate number
            if (isNaN(number) || number < 0 || number > 36) {
                console.error('❌ Invalid number:', number);
                showError('Invalid number selected');
                isManualSelectionInProgress = false;
                return;
            }
            
            // Set the input value - this is the manual selection
            const manualInput = document.getElementById('manualWinningNumber');
            if (!manualInput) {
                console.error('❌ manualWinningNumber input not found');
                isManualSelectionInProgress = false;
                showError('Manual winning number input not found');
                return;
            }
            
            // Set the value
            manualInput.value = number;
            console.log('✅ Set manual input value to:', number);
            
            // Also update the forced number container to show this selection
            const applyContainer = document.getElementById('forcedNumberApplyContainer');
            if (applyContainer) {
                applyContainer.setAttribute('data-forced-number', number.toString());
                applyContainer.setAttribute('data-draw-number', currentDrawNumber.toString());
                applyContainer.style.display = 'block';
            }
            
            // Update forced number badge to show the selected number
            const forcedNumberBadge = document.getElementById('forcedNumberBadge');
            const forcedNumberDraw = document.getElementById('forcedNumberDraw');
            const forcedNumberMessage = document.getElementById('forcedNumberMessage');
            
            if (forcedNumberBadge) {
                const color = number === 0 ? 'green' : ([1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36].includes(number) ? 'red' : 'black');
                forcedNumberBadge.textContent = number;
                forcedNumberBadge.className = 'forced-number-badge ' + color + ' has-forced found';
            }
            
            if (forcedNumberDraw) {
                forcedNumberDraw.textContent = '#' + currentDrawNumber;
            }
            
            if (forcedNumberMessage) {
                const color = number === 0 ? 'green' : ([1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36].includes(number) ? 'red' : 'black');
                forcedNumberMessage.innerHTML = `<strong>Number ${number} (${color})</strong> selected manually for draw #${currentDrawNumber}`;
            }

            // Call the set winning number function (this will submit it)
            console.log('📤 Calling setManualWinningNumber() to submit number...');
            setManualWinningNumber();

            // Highlight the selected number
            const items = document.querySelectorAll('.recommended-number-item');
            items.forEach(item => {
                const itemNumber = parseInt(item.getAttribute('data-number'));
                if (itemNumber === number) {
                    item.style.transform = 'scale(1.05)';
                    item.style.boxShadow = '0 0 15px rgba(46, 204, 113, 0.5)';
                    setTimeout(() => {
                        item.style.transform = '';
                        item.style.boxShadow = '';
                    }, 1000);
                }
            });
            
            // Show success feedback
            showToast('Number Selected', `Number ${number} selected. Setting as winning number for draw #${currentDrawNumber}...`, 'info');
            
            // Reset flag after a delay to allow the submission to complete
            setTimeout(() => {
                isManualSelectionInProgress = false;
                console.log('✅ Manual selection flag reset');
            }, 5000); // Increased to 5 seconds to ensure submission completes
        }

        // Function to update the bet distribution chart
        function updateBetDistributionChart(data) {
            const chartContainer = document.getElementById('chartContainer');
            chartContainer.innerHTML = ''; // Clear previous chart

            const seriesData = [];
            const colors = [];

            // Prepare data for chart
            for (let i = 0; i <= 36; i++) {
                const numberData = data.numbers[i] || { bet_count: 0, total_payout: 0 };
                seriesData.push({
                    x: i.toString(),
                    y: numberData.bet_count || 0,
                    payout: formatCurrency(numberData.total_payout || 0),
                    color: getNumberColor(i)
                });

                // Set bar color based on whether there are bets
                if (numberData.bet_count > 0) {
                    colors.push('#2ecc71'); // Green for numbers with bets
                } else {
                    colors.push('#cccccc'); // Gray for numbers without bets
                }
            }

            const options = {
                series: [{
                    name: 'Number of Bets',
                    data: seriesData
                }],
                chart: {
                    type: 'bar',
                    height: 400,
                    toolbar: {
                        show: true,
                        tools: {
                            download: true,
                            selection: false,
                            zoom: false,
                            zoomin: false,
                            zoomout: false,
                            pan: false,
                            reset: false
                        }
                    }
                },
                plotOptions: {
                    bar: {
                        distributed: true,
                        borderRadius: 4,
                        columnWidth: '80%',
                        dataLabels: {
                            position: 'top'
                        }
                    }
                },
                colors: colors,
                dataLabels: {
                    enabled: true,
                    formatter: function(val) {
                        return val > 0 ? val : '';
                    },
                    offsetY: -20,
                    style: {
                        fontSize: '12px',
                        colors: ["#304758"]
                    }
                },
                xaxis: {
                    categories: Array.from({ length: 37 }, (_, i) => i.toString()),
                    labels: {
                        style: {
                            colors: Array(37).fill('#666')
                        }
                    },
                    title: {
                        text: 'Roulette Numbers'
                    }
                },
                yaxis: {
                    title: {
                        text: 'Number of Bets'
                    }
                },
                tooltip: {
                    y: {
                        formatter: function(val, { seriesIndex, dataPointIndex, w }) {
                            const dataPoint = w.config.series[seriesIndex].data[dataPointIndex];
                            return `<div>
                                <div>Number of Bets: ${val}</div>
                                <div>Potential Payout: ${dataPoint.payout}</div>
                            </div>`;
                        }
                    },
                    custom: function({ series, seriesIndex, dataPointIndex, w }) {
                        const dataPoint = w.config.series[seriesIndex].data[dataPointIndex];
                        return `<div class="apexcharts-tooltip-box">
                            <div style="background-color: ${dataPoint.color}; width: 20px; height: 20px; border-radius: 50%; margin: 5px auto;"></div>
                            <div style="text-align: center; font-weight: bold; margin-bottom: 5px;">Number ${dataPoint.x}</div>
                            <div>Number of Bets: ${dataPoint.y}</div>
                            <div>Potential Payout: ${dataPoint.payout}</div>
                        </div>`;
                    }
                },
                title: {
                    text: 'Bet Distribution by Number',
                    align: 'center',
                    margin: 15,
                    style: {
                        fontSize: '16px',
                        fontWeight: 'bold'
                    }
                }
            };

            betDistributionChart = new ApexCharts(chartContainer, options);
            betDistributionChart.render();
        }

        // Function to update the bet distribution grid
        function updateBetDistributionGrid(data) {
            const gridContainer = document.getElementById('betInfoGrid');
            gridContainer.innerHTML = ''; // Clear previous content

            // Create a grid item for each number
            for (let i = 0; i <= 36; i++) {
                const numberData = data.numbers[i] || { bet_count: 0, total_payout: 0 };
                const hasBets = numberData.bet_count > 0;

                const gridItem = document.createElement('div');
                gridItem.className = `bet-info-item ${hasBets ? 'has-bets' : 'no-bets'}`;

                gridItem.innerHTML = `
                    <div class="number-details">
                        <div class="number-badge ${getNumberColor(i)}">${i}</div>
                        <div class="bet-count">${hasBets ? numberData.bet_count + ' bets' : 'No bets'}</div>
                        <div class="payout-amount">${formatCurrency(numberData.total_payout || 0)}</div>
                    </div>
                `;

                gridContainer.appendChild(gridItem);
            }
        }

        // Function to update the bet type distribution chart
        function updateBetTypeDistributionChart(data) {
            const chartContainer = document.getElementById('betTypeChartContainer');
            chartContainer.innerHTML = ''; // Clear previous chart

            // Check if we have bet types data
            if (!data.bet_types || Object.keys(data.bet_types).length === 0) {
                chartContainer.innerHTML = `
                    <div class="no-data-message">
                        <i class="fas fa-info-circle"></i>
                        <p>No bet type data available for the upcoming draw.</p>
                    </div>
                `;
                return;
            }

            // Prepare data for chart - filter out bet types with zero bets
            const betTypes = Object.keys(data.bet_types).filter(type =>
                data.bet_types[type].bet_count > 0
            );
            const betCounts = betTypes.map(type => data.bet_types[type].bet_count || 0);
            const payouts = betTypes.map(type => data.bet_types[type].total_payout || 0);

            const options = {
                series: [{
                    name: 'Number of Bets',
                    data: betCounts
                }, {
                    name: 'Potential Payout',
                    data: payouts
                }],
                chart: {
                    type: 'bar',
                    height: 350,
                    stacked: false,
                    toolbar: {
                        show: true
                    }
                },
                plotOptions: {
                    bar: {
                        horizontal: false,
                        columnWidth: '55%',
                        borderRadius: 4,
                    },
                },
                dataLabels: {
                    enabled: false
                },
                stroke: {
                    show: true,
                    width: 2,
                    colors: ['transparent']
                },
                xaxis: {
                    categories: betTypes.map(formatBetTypeLabel),
                    labels: {
                        style: {
                            fontSize: '12px'
                        },
                        rotate: -45,
                        rotateAlways: true
                    }
                },
                yaxis: [
                    {
                        title: {
                            text: 'Number of Bets'
                        }
                    },
                    {
                        opposite: true,
                        title: {
                            text: 'Potential Payout ($)'
                        }
                    }
                ],
                fill: {
                    opacity: 1
                },
                tooltip: {
                    y: {
                        formatter: function(val, { seriesIndex }) {
                            if (seriesIndex === 0) {
                                return val + ' bets';
                            } else {
                                return formatCurrency(val);
                            }
                        }
                    }
                },
                title: {
                    text: 'Bet Type Distribution',
                    align: 'center',
                    margin: 15,
                    style: {
                        fontSize: '16px',
                        fontWeight: 'bold'
                    }
                },
                colors: ['#4e73df', '#1cc88a']
            };

            betTypeChart = new ApexCharts(chartContainer, options);
            betTypeChart.render();
        }

        // Function to format bet type labels
        function formatBetTypeLabel(betType) {
            const typeMap = {
                'straight': 'Straight Up',
                'split': 'Split',
                'street': 'Street',
                'corner': 'Corner',
                'line': 'Line',
                'dozen': 'Dozen',
                'column': 'Column',
                'red': 'Red',
                'black': 'Black',
                'even': 'Even',
                'odd': 'Odd',
                'low': 'Low (1-18)',
                'high': 'High (19-36)',
                'even-money': 'Even Money'
            };

            return typeMap[betType] || capitalize(betType);
        }

        // Function to get number color
        function getNumberColor(number) {
            if (number === 0) {
                return 'green';
            } else if ([1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36].includes(number)) {
                return 'red';
            } else {
                return 'black';
            }
        }

        // Function to capitalize first letter
        function capitalize(string) {
            return string.charAt(0).toUpperCase() + string.slice(1);
        }

        // Function to format currency
        function formatCurrency(amount) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD',
                minimumFractionDigits: 2
            }).format(amount);
        }

        // Function to update last updated timestamp
        function updateLastUpdated() {
            const lastUpdated = new Date();
            const elements = document.querySelectorAll('.auto-refresh-status span');
            elements.forEach(el => {
                const drawCount = allDrawsData.length;
                const message = drawCount > 1
                    ? `Auto-refreshing data for ${drawCount} upcoming draws every 15 seconds (Last updated: ${lastUpdated.toLocaleTimeString()})`
                    : `Auto-refreshing data for upcoming draw every 15 seconds (Last updated: ${lastUpdated.toLocaleTimeString()})`;
                el.innerHTML = message;
            });
        }

        // Function to show/hide loading overlay
        function showLoading(show) {
            if (loadingOverlay) {
                loadingOverlay.style.display = show ? 'flex' : 'none';
            } else {
                console.warn('⚠️ Loading overlay element not found');
            }
        }

        // Function to show error message
        function showError(message) {
            console.error('🚨 Showing error message:', message);
            const chartContainer = document.getElementById('chartContainer');
            const gridContainer = document.getElementById('betInfoGrid');
            const overviewTable = document.querySelector('.upcoming-draws-overview tbody');

            // Show error in chart view
            if (chartContainer) {
                chartContainer.innerHTML = `
                    <div class="error-message" style="padding: 20px; text-align: center; color: #dc3545; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">
                        <i class="fas fa-exclamation-circle" style="font-size: 2em; margin-bottom: 10px;"></i>
                        <p style="font-size: 1.1em; margin-bottom: 15px;">${message}</p>
                        <button class="btn btn-primary btn-sm" onclick="fetchBetDistribution()">
                            <i class="fas fa-sync-alt"></i> Try Again
                        </button>
                    </div>
                `;
            } else {
                console.warn('⚠️ chartContainer not found');
            }

            // Show error in grid view
            if (gridContainer) {
                gridContainer.innerHTML = `
                    <div class="error-message" style="padding: 20px; text-align: center; color: #dc3545; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">
                        <i class="fas fa-exclamation-circle" style="font-size: 2em; margin-bottom: 10px;"></i>
                        <p style="font-size: 1.1em;">${message}</p>
                    </div>
                `;
            } else {
                console.warn('⚠️ gridContainer not found');
            }
            
            // Show error in overview table
            if (overviewTable) {
                overviewTable.innerHTML = `
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 20px; color: #dc3545;">
                            <i class="fas fa-exclamation-circle"></i> ${message}
                        </td>
                    </tr>
                `;
            }
        }

        // Draw Control Functions

        // Function to fetch draw information
        async function fetchDrawInfo() {
            showLoading(true);

            try {
                const response = await fetch('../api/draw_info.php');
                const data = await response.json();

                if (data.status === 'success') {
                    // Update with the new nested data structure
                    updateDrawInfo(data.data);

                    // Set countdown timer based on API response
                    if (data.data.countdown !== undefined) {
                        timerValue = data.data.countdown;
                        updateTimerDisplay();
                    }

                    // Update roll history
                    updateRollHistory(data.data.recent_rolls, data.data.recent_colors);

                    // Update mode toggle button
                    const modeToggleBtn = document.getElementById('toggleAutoMode');
                    modeToggleBtn.textContent = data.data.is_automatic ? 'Switch to Manual' : 'Switch to Auto';

                    // Update the mode display
                    document.getElementById('currentMode').textContent = data.data.is_automatic ? 'Automatic' : 'Manual';

                    // Update timer settings
                    document.getElementById('timerInterval').value = data.data.timer_seconds;
                } else {
                    console.error('Failed to fetch draw info:', data.message);
                    showToast('Error', data.message, 'error');
                }
            } catch (error) {
                console.error('Error fetching draw info:', error);
                showToast('Error', 'Failed to connect to server. Please try again.', 'error');
            } finally {
                showLoading(false);
                updateLastUpdated();
            }
        }

        // Function to update draw information on the page
        function updateDrawInfo(data) {
            // Check for draw number mismatch and correct it
            if (data.expected_draw !== undefined && data.current_draw !== undefined && 
                data.current_draw !== data.expected_draw) {
                console.warn(`⚠️ Draw number mismatch detected: Current=${data.current_draw}, Expected=${data.expected_draw}`);
                
                // Auto-correct if mismatch is significant (more than 1 draw difference)
                if (Math.abs(data.current_draw - data.expected_draw) > 1) {
                    console.log('🔄 Auto-correcting draw number...');
                    if (typeof correctDrawNumber === 'function') {
                        correctDrawNumber(data.expected_draw);
                    }
                    return; // Exit early, will be called again after correction
                }
            }
            
            currentDrawNumber = data.current_draw || '-';

            // Update desktop elements
            const currentDrawNumberEl = document.getElementById('currentDrawNumber');
            if (currentDrawNumberEl) {
                currentDrawNumberEl.textContent = currentDrawNumber;
            }

            // Update mobile elements if they exist
            const currentDrawNumberMobile = document.getElementById('currentDrawNumber-mobile');
            if (currentDrawNumberMobile) {
                currentDrawNumberMobile.textContent = currentDrawNumber;
            }

            // Calculate and update draw times
            // Last draw time: 3 minutes before next draw (or current time if just started)
            const nextDrawCalc = calculateNextDrawTime();
            const nextDrawTime = new Date(nextDrawCalc.timestamp);
            const lastDrawTime = new Date(nextDrawTime.getTime() - (3 * 60 * 1000)); // 3 minutes ago
            
            // Format times for display
            const formatTime = (date) => {
                return date.toLocaleTimeString('en-US', { 
                    hour: '2-digit', 
                    minute: '2-digit', 
                    second: '2-digit',
                    hour12: true 
                });
            };
            
            // Use API data if available, otherwise calculate
            let lastDrawDisplay = data.last_draw;
            if (!lastDrawDisplay || lastDrawDisplay === '-' || lastDrawDisplay === null) {
                lastDrawDisplay = formatTime(lastDrawTime);
            }
            
            let nextDrawDisplay = data.next_draw;
            if (!nextDrawDisplay || nextDrawDisplay === '-' || nextDrawDisplay === null) {
                nextDrawDisplay = formatTime(nextDrawTime);
            }
            
            // Update display
            const lastDrawTimeEl = document.getElementById('lastDrawTime');
            const nextDrawTimeEl = document.getElementById('nextDrawTime');
            
            if (lastDrawTimeEl) {
                lastDrawTimeEl.textContent = lastDrawDisplay || '-';
            }
            
            const lastDrawTimeMobile = document.getElementById('lastDrawTime-mobile');
            if (lastDrawTimeMobile) {
                lastDrawTimeMobile.textContent = lastDrawDisplay || '-';
            }

            if (nextDrawTimeEl) {
                nextDrawTimeEl.textContent = nextDrawDisplay || '-';
            }
            
            const nextDrawTimeMobile = document.getElementById('nextDrawTime-mobile');
            if (nextDrawTimeMobile) {
                nextDrawTimeMobile.textContent = nextDrawDisplay || '-';
            }

            // Update mode
            isAutoMode = data.is_automatic;
            const modeText = isAutoMode ? 'Automatic' : 'Manual';
            const toggleText = isAutoMode ? 'Switch to Manual' : 'Switch to Auto';

            // Update desktop elements
            const currentModeEl = document.getElementById('currentMode');
            const modeToggleTextEl = document.getElementById('modeToggleText');
            
            if (currentModeEl) {
                currentModeEl.textContent = modeText;
            }
            if (modeToggleTextEl) {
                modeToggleTextEl.textContent = toggleText;
            }

            // Update mobile elements if they exist
            if (document.getElementById('currentMode-mobile')) {
                document.getElementById('currentMode-mobile').textContent = modeText;
            }
            if (document.getElementById('modeToggleText-mobile')) {
                document.getElementById('modeToggleText-mobile').textContent = isAutoMode ? 'Manual' : 'Auto';
            }

            // Update winning number
            if (data.winning_number !== null && data.winning_number !== undefined) {
                currentWinningNumber = data.winning_number;
                const numberClass = 'number-circle ' + (data.winning_color || 'green');

                // Update desktop elements
                const winningNumberDisplay = document.getElementById('winningNumberDisplay');
                const winningNumberSource = document.getElementById('winningNumberSource');
                const winningNumberReason = document.getElementById('winningNumberReason');
                
                if (winningNumberDisplay) {
                    winningNumberDisplay.textContent = data.winning_number;
                    winningNumberDisplay.className = numberClass;
                }
                
                if (winningNumberSource) {
                    const source = data.winning_number_source || 'Not set';
                    winningNumberSource.textContent = `Source: ${source}`;
                }
                
                if (winningNumberReason) {
                    const reason = data.winning_number_reason || 'No reason provided';
                    winningNumberReason.textContent = `Reason: ${reason}`;
                }

                // Update mobile elements if they exist
                const winningNumberDisplayMobile = document.getElementById('winningNumberDisplay-mobile');
                const winningNumberSourceMobile = document.getElementById('winningNumberSource-mobile');
                const winningNumberReasonMobile = document.getElementById('winningNumberReason-mobile');
                
                if (winningNumberDisplayMobile) {
                    winningNumberDisplayMobile.textContent = data.winning_number;
                    winningNumberDisplayMobile.className = numberClass;
                }
                if (winningNumberSourceMobile) {
                    const source = data.winning_number_source || 'Not set';
                    winningNumberSourceMobile.textContent = `Source: ${source}`;
                }
                if (winningNumberReasonMobile) {
                    const reason = data.winning_number_reason || 'No reason provided';
                    winningNumberReasonMobile.textContent = `Reason: ${reason}`;
                }
            } else {
                // Update desktop elements
                const winningNumberDisplay = document.getElementById('winningNumberDisplay');
                const winningNumberSource = document.getElementById('winningNumberSource');
                const winningNumberReason = document.getElementById('winningNumberReason');
                
                if (winningNumberDisplay) {
                    winningNumberDisplay.textContent = '-';
                    winningNumberDisplay.className = 'number-circle';
                }
                if (winningNumberSource) {
                    winningNumberSource.textContent = 'Source: -';
                }
                if (winningNumberReason) {
                    winningNumberReason.textContent = 'Reason: -';
                }

                // Update mobile elements if they exist
                const winningNumberDisplayMobile = document.getElementById('winningNumberDisplay-mobile');
                const winningNumberSourceMobile = document.getElementById('winningNumberSource-mobile');
                const winningNumberReasonMobile = document.getElementById('winningNumberReason-mobile');
                
                if (winningNumberDisplayMobile) {
                    winningNumberDisplayMobile.textContent = '-';
                    winningNumberDisplayMobile.className = 'number-circle';
                }
                if (winningNumberSourceMobile) {
                    winningNumberSourceMobile.textContent = 'Source: -';
                }
                if (winningNumberReasonMobile) {
                    winningNumberReasonMobile.textContent = 'Reason: -';
                }
            }

            // Update manual winning number input
            if (data.manual_winning_number) {
                document.getElementById('manualWinningNumber').value = data.manual_winning_number;
                if (document.getElementById('manualWinningNumber-mobile')) {
                    document.getElementById('manualWinningNumber-mobile').value = data.manual_winning_number;
                }
            }
            
            // Load smart selection settings
            loadSmartSelectionSettings();

            // Update roll history for both desktop and mobile
            updateRollHistory(data.recent_rolls, data.recent_colors);
        }

        // Function to update roll history display
        function updateRollHistory(rolls, colors) {
            // Update desktop roll history
            const rollHistoryContainer = document.getElementById('rollHistory');
            if (rollHistoryContainer) {
                rollHistoryContainer.innerHTML = '';

                if (!rolls || rolls.length === 0) {
                    const emptyMessage = document.createElement('div');
                    emptyMessage.textContent = 'No roll history available';
                    emptyMessage.style.color = '#666';
                    rollHistoryContainer.appendChild(emptyMessage);
                } else {
                    rolls.forEach((roll, index) => {
                        const rollItem = document.createElement('div');
                        rollItem.className = `roll-item ${colors[index]}`;
                        rollItem.textContent = roll;
                        rollHistoryContainer.appendChild(rollItem);
                    });
                }
            }

            // Update mobile roll history if it exists
            const mobileRollHistoryContainer = document.getElementById('rollHistory-mobile');
            if (mobileRollHistoryContainer) {
                mobileRollHistoryContainer.innerHTML = '';

                if (!rolls || rolls.length === 0) {
                    const emptyMessage = document.createElement('div');
                    emptyMessage.textContent = 'No history available';
                    emptyMessage.style.color = '#666';
                    emptyMessage.style.fontSize = '0.8rem';
                    mobileRollHistoryContainer.appendChild(emptyMessage);
                } else {
                    // For mobile, limit to the most recent 10 rolls to save space
                    const mobileRolls = rolls.slice(0, 10);
                    const mobileColors = colors.slice(0, 10);

                    mobileRolls.forEach((roll, index) => {
                        const rollItem = document.createElement('div');
                        rollItem.className = `roll-item ${mobileColors[index]}`;
                        rollItem.textContent = roll;
                        mobileRollHistoryContainer.appendChild(rollItem);
                    });

                    // If there are more rolls than we're showing, add an indicator
                    if (rolls.length > 10) {
                        const moreIndicator = document.createElement('div');
                        moreIndicator.className = 'roll-item more';
                        moreIndicator.textContent = '+' + (rolls.length - 10);
                        moreIndicator.style.backgroundColor = '#6c757d';
                        moreIndicator.style.color = 'white';
                        mobileRollHistoryContainer.appendChild(moreIndicator);
                    }
                }
            }
        }

        // Timer functions
        function startTimer() {
            if (!timerRunning) {
                timerRunning = true;

                // Clear any existing interval
                if (timerIntervalId) {
                    clearInterval(timerIntervalId);
                }

                // Get the saved end time from localStorage
                const savedEndTime = localStorage.getItem('adminCountdownEndTime');
                const currentTime = new Date().getTime();

                // If we don't have a valid end time, calculate one
                if (!savedEndTime || isNaN(parseInt(savedEndTime))) {
                    const nextDraw = calculateNextDrawTime();
                    localStorage.setItem('adminCountdownEndTime', nextDraw.timestamp.toString());
                }

                // Start the countdown
                timerIntervalId = setInterval(() => {
                    // Calculate the exact time remaining based on the stored end time
                    const savedEndTime = localStorage.getItem('adminCountdownEndTime');
                    const currentTime = new Date().getTime();

                    if (savedEndTime && !isNaN(parseInt(savedEndTime))) {
                        const remainingTimeMs = parseInt(savedEndTime) - currentTime;
                        timerValue = Math.max(0, Math.floor(remainingTimeMs / 1000));
                    } else {
                        // Fallback to decrementing if no end time is saved
                        timerValue--;
                    }

                    if (timerValue <= 0) {
                        // Timer has reached zero
                        if (isAutoMode) {
                            executeAutoDraw();
                        } else {
                            resetTimer();
                            fetchDrawInfo();
                        }
                    }

                    updateTimerDisplay();
                }, 1000);

                updateTimerControlsUI();
            }
        }

        function pauseTimer() {
            if (timerRunning) {
                timerRunning = false;
                clearInterval(timerIntervalId);
                timerIntervalId = null;
                updateTimerControlsUI();
            }
        }

        function resetTimer() {
            pauseTimer();

            // If timer is synced with TV display, re-sync instead of just resetting
            if (isTimerSynced) {
                syncTimerWithTVDisplay();
            } else {
                // Calculate a new end time based on real-time of day
                const nextDraw = calculateNextDrawTime();
                timerValue = nextDraw.secondsRemaining;
                localStorage.setItem('adminCountdownEndTime', nextDraw.timestamp.toString());
                updateTimerDisplay();
            }
        }

        function updateTimerDisplay() {
            const minutes = Math.floor(timerValue / 60);
            const seconds = timerValue % 60;
            document.getElementById('timerDisplay').textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

            // Add visual effect when time is running low
            const timerDisplay = document.getElementById('timerDisplay');
            if (timerValue <= 10) {
                timerDisplay.classList.add('timer-warning');
            } else {
                timerDisplay.classList.remove('timer-warning');
            }

            // Update 3D timer display
            updateTimer3DDisplay();
        }

        function updateTimerControlsUI() {
            // Update original controls
            document.getElementById('startTimer').disabled = timerRunning;
            document.getElementById('pauseTimer').disabled = !timerRunning;

            // Update 3D timer controls
            document.getElementById('startTimer3d').disabled = timerRunning;
            document.getElementById('pauseTimer3d').disabled = !timerRunning;

            // Add visual indication of active state
            if (timerRunning) {
                document.getElementById('startTimer3d').style.opacity = '0.5';
                document.getElementById('pauseTimer3d').style.opacity = '1';
            } else {
                document.getElementById('startTimer3d').style.opacity = '1';
                document.getElementById('pauseTimer3d').style.opacity = '0.5';
            }
        }

        function updateTimerSettings() {
            const newInterval = parseInt(document.getElementById('timerInterval').value);
            if (newInterval >= 10 && newInterval <= 300) {
                timerInterval = newInterval;

                // Update the server with the new timer interval
                fetch('../api/update_timer_settings.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `duration=${timerInterval}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        showToast('Success', 'Timer settings updated successfully', 'success');
                        resetTimer();
                    } else {
                        showError(data.message || 'Failed to update timer settings');
                    }
                })
                .catch(error => {
                    console.error('Error updating timer settings:', error);
                    showError('Failed to update timer settings. Please try again.');
                });
            } else {
                showError('Please enter a valid interval between 10 and 300 seconds');
            }
        }

        // Winning number functions
        function toggleMode() {
            const newMode = !isAutoMode ? 'automatic' : 'manual';

            fetch('../api/toggle_mode.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `mode=${newMode}`
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success') {
                        showToast('Mode Changed', data.message, 'success');

                        // Update UI based on the new mode
                        isAutoMode = data.data.automatic;
                        document.getElementById('currentMode').textContent = isAutoMode ? 'Automatic' : 'Manual';
                        document.getElementById('modeToggleText').textContent = isAutoMode ? 'Switch to Manual' : 'Switch to Auto';

                        // Refresh the draw info
                        fetchDrawInfo();
                    } else {
                        showError(data.message || 'Failed to toggle mode');
                    }
                })
                .catch(error => {
                    console.error('Error toggling mode:', error);
                    showError('Failed to toggle mode. Please try again.');
                });
        }

        // Auto-save settings when dropdowns change
        document.getElementById('timePreset')?.addEventListener('change', function() {
            const timePreset = this.value;
            const patternType = document.getElementById('patternType').value;
            saveSmartSelectionSettings(timePreset, patternType);
        });

        document.getElementById('patternType')?.addEventListener('change', function() {
            const timePreset = document.getElementById('timePreset').value;
            const patternType = this.value;
            saveSmartSelectionSettings(timePreset, patternType);
        });

        // Mobile dropdowns
        document.getElementById('timePreset-mobile')?.addEventListener('change', function() {
            const timePreset = this.value;
            const patternType = document.getElementById('patternType-mobile').value;
            saveSmartSelectionSettings(timePreset, patternType);
            // Sync to desktop
            if (document.getElementById('timePreset')) {
                document.getElementById('timePreset').value = timePreset;
            }
        });

        document.getElementById('patternType-mobile')?.addEventListener('change', function() {
            const timePreset = document.getElementById('timePreset-mobile').value;
            const patternType = this.value;
            saveSmartSelectionSettings(timePreset, patternType);
            // Sync to desktop
            if (document.getElementById('patternType')) {
                document.getElementById('patternType').value = patternType;
            }
        });

        // Smart Number Selection Functions
        document.getElementById('smartSelectNumber')?.addEventListener('click', function() {
            generateSmartNumber();
        });

        document.getElementById('applySmartSelection')?.addEventListener('click', async function() {
            // Get number from preset schedule for current draw instead of smart selection
            const presetNumber = await getPresetNumberForCurrentDraw();
            const selectedNumber = presetNumber ? presetNumber.number : 
                                 document.getElementById('smartSelectedNumber').textContent;
            
            if (selectedNumber && selectedNumber !== '-') {
                // Apply as forced number automatically
                applyPresetNumberAsForced(parseInt(selectedNumber), 
                    document.getElementById('timePreset').value,
                    document.getElementById('patternType').value,
                    false);
                
                // Also set in manual input for immediate use
                document.getElementById('manualWinningNumber').value = selectedNumber;
                setManualWinningNumber();
                document.getElementById('smartSelectionResult').style.display = 'none';
            }
        });

        // Mobile smart selection
        document.getElementById('smartSelectNumber-mobile')?.addEventListener('click', function() {
            generateSmartNumber(true);
        });

        document.getElementById('applySmartSelection-mobile')?.addEventListener('click', function() {
            const selectedNumber = document.getElementById('smartSelectedNumber-mobile').textContent;
            if (selectedNumber && selectedNumber !== '-') {
                document.getElementById('manualWinningNumber-mobile').value = selectedNumber;
                document.getElementById('manualWinningNumber').value = selectedNumber;
                setManualWinningNumber();
                document.getElementById('smartSelectionResult-mobile').style.display = 'none';
            }
        });

        async function generateSmartNumber(isMobile = false) {
            console.log('🎯 generateSmartNumber called', { isMobile, currentDrawNumber });
            
            // Validate current draw number
            if (!currentDrawNumber || currentDrawNumber === 0 || currentDrawNumber === '-') {
                console.error('❌ Invalid currentDrawNumber:', currentDrawNumber);
                showError('Invalid draw number. Please refresh the page.');
                return;
            }
            
            const timePreset = isMobile ? 
                document.getElementById('timePreset-mobile')?.value : 
                document.getElementById('timePreset')?.value;
            const patternType = isMobile ? 
                document.getElementById('patternType-mobile')?.value : 
                document.getElementById('patternType')?.value;
            
            if (!timePreset || !patternType) {
                console.error('❌ Missing timePreset or patternType:', { timePreset, patternType });
                showError('Please select time preset and pattern type.');
                return;
            }
            
            // Save settings for auto mode
            saveSmartSelectionSettings(timePreset, patternType);
            
            showToast('Analyzing', 'Generating smart number selection...', 'info');
            console.log('🎯 Starting smart number generation', { timePreset, patternType, currentDrawNumber });
            
            // Always generate preset schedule first, then check bets
            // First, get smart selection to generate the schedule
            fetch(`../api/smart_number_selection.php?draw_number=${currentDrawNumber}&time_preset=${timePreset}&pattern_type=${patternType}&_cb=${Date.now()}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('🎯 Smart selection API response:', data);
                    
                    if (data.status === 'success') {
                        const number = data.data.selected_number;
                        const color = data.data.selected_color;
                        const reason = data.data.reason;
                        const payout = data.data.payout;
                        const patternAnalysis = data.data.pattern_analysis;
                        
                        // Generate and display preset schedule FIRST (this creates the schedule table)
                        generatePresetSchedule(timePreset, patternType, number, data.data);
                        
                        // Now check for bets
                        fetch(`../api/get_bet_distribution.php?draw_number=${currentDrawNumber}&_cb=${Date.now()}`)
                            .then(response => response.json())
                            .then(betData => {
                                console.log('🎯 Bet distribution response:', betData);
                                
                                // Check if there are any bets
                                const hasBets = betData.success && betData.summary && betData.summary.total_bets > 0;
                                console.log('🎯 Has bets:', hasBets);
                                
                                // Check payout - if too high, use preset schedule number instead
                                const maxPayout = 500; // Maximum allowed payout
                                let finalNumber = number;
                                let finalColor = color;
                                let finalReason = reason;
                                
                                // If no bets OR payout too high, use preset schedule number
                                if (!hasBets || payout > maxPayout) {
                                    // Get number from preset schedule for current draw
                                    const presetNumber = await getPresetNumberForCurrentDraw();
                                    console.log('🎯 Preset number from schedule:', presetNumber);
                                    
                                    if (presetNumber !== null) {
                                        finalNumber = presetNumber.number;
                                        finalColor = presetNumber.color;
                                        if (!hasBets) {
                                            finalReason = `Preset schedule number (no bets placed)`;
                                        } else {
                                            finalReason = `Preset schedule number (payout too high: $${parseFloat(payout).toFixed(2)})`;
                                            showToast('Warning', `Payout too high ($${parseFloat(payout).toFixed(2)}), using preset schedule number ${finalNumber}`, 'warning');
                                        }
                                    } else if (!hasBets) {
                                        // No preset schedule yet, but no bets - use the smart selection number
                                        finalReason = `Smart selection (no bets, using calculated number)`;
                                    }
                                }
                                
                                // Update UI (desktop)
                                if (!isMobile) {
                                    const numberCircle = document.getElementById('smartSelectedNumber');
                                    if (numberCircle) {
                                        numberCircle.textContent = finalNumber;
                                        numberCircle.className = `number-circle-sm ${finalColor}`;
                                    }
                                    
                                    const reasonEl = document.getElementById('smartSelectionReason');
                                    if (reasonEl) reasonEl.textContent = finalReason;
                                    
                                    const payoutEl = document.getElementById('smartPayout');
                                    if (payoutEl) payoutEl.textContent = '$' + parseFloat(payout).toFixed(2);
                                    
                                    const patternEl = document.getElementById('patternAnalysis');
                                    if (patternEl) patternEl.textContent = patternAnalysis.suggested_pattern + ' (Confidence: ' + patternAnalysis.confidence + ')';
                                    
                                    // Show pattern visualization
                                    displayPatternVisualization(patternAnalysis, data.data);
                                    
                                    // Show result
                                    const resultEl = document.getElementById('smartSelectionResult');
                                    if (resultEl) resultEl.style.display = 'block';
                                } else {
                                    // Update UI (mobile)
                                    const numberCircle = document.getElementById('smartSelectedNumber-mobile');
                                    if (numberCircle) {
                                        numberCircle.textContent = finalNumber;
                                        numberCircle.className = `number-circle-sm ${finalColor}`;
                                    }
                                    
                                    const resultEl = document.getElementById('smartSelectionResult-mobile');
                                    if (resultEl) resultEl.style.display = 'block';
                                }
                                
                                // Automatically apply as forced number
                                applyPresetNumberAsForced(finalNumber, timePreset, patternType, isMobile, payout);
                                
                                showToast('Success', `Smart number ${finalNumber} selected (Payout: $${parseFloat(payout).toFixed(2)})`, 'success');
                                console.log('✅ Smart number generation complete:', { finalNumber, finalColor, finalReason });
                            })
                            .catch(error => {
                                console.error('❌ Error checking bets:', error);
                                // If bet check fails, still show the result
                                showToast('Warning', 'Could not check bets, showing smart selection', 'warning');
                            });
                    } else {
                        console.error('❌ Smart selection API error:', data);
                        showError(data.message || 'Failed to generate smart number');
                    }
                })
                .catch(error => {
                    console.error('❌ Error generating smart number:', error);
                    showError('Failed to generate smart number. Please try again.');
                });
        }
        
        // Get preset number for current draw from the schedule table or database
        async function getPresetNumberForCurrentDraw() {
            // First, try to get from DOM table (for backward compatibility and immediate access)
            const scheduleBody = document.getElementById('presetScheduleBody');
            if (scheduleBody) {
                const rows = scheduleBody.querySelectorAll('tr');
                console.log('🎯 Checking preset schedule DOM:', { rowsCount: rows.length, currentDrawNumber });
                
                for (let row of rows) {
                    const drawCell = row.querySelector('td:first-child');
                    if (drawCell) {
                        const drawText = drawCell.textContent.trim();
                        const drawMatch = drawText.match(/#(\d+)/);
                        if (drawMatch) {
                            const drawNum = parseInt(drawMatch[1]);
                            
                            if (drawNum === currentDrawNumber) {
                                // Found the current draw - get the number
                                const numberCell = row.querySelector('td:nth-child(3)');
                                if (numberCell) {
                                    const numberSpan = numberCell.querySelector('.number-circle-sm');
                                    if (numberSpan) {
                                        const number = parseInt(numberSpan.textContent.trim());
                                        const color = numberSpan.classList.contains('red') ? 'red' : 
                                                     numberSpan.classList.contains('black') ? 'black' : 'green';
                                        console.log('✅ Found preset number for current draw from DOM:', { number, color });
                                        return { number, color };
                                    }
                                }
                            }
                        }
                    }
                }
            }
            
            // If not found in DOM, query database
            console.log('🎯 Preset number not found in DOM, querying database...');
            try {
                const response = await fetch(`../api/get_current_preset.php?draw_number=${currentDrawNumber}&_cb=${Date.now()}`);
                if (response.ok) {
                    const data = await response.json();
                    if (data.status === 'success' && data.data) {
                        console.log('✅ Found preset number for current draw from database:', data.data);
                        return {
                            number: data.data.winning_number,
                            color: data.data.color
                        };
                    } else {
                        console.log('ℹ️ No preset number found in database for draw #' + currentDrawNumber);
                    }
                } else {
                    console.warn('⚠️ Failed to query database for preset number:', response.status);
                }
            } catch (error) {
                console.error('❌ Error querying database for preset number:', error);
            }
            
            console.warn('⚠️ No preset number found for current draw:', currentDrawNumber);
            return null;
        }
        
        // Get preset number for next draw from the schedule table
        function getPresetNumberForNextDraw() {
            const scheduleBody = document.getElementById('presetScheduleBody');
            if (!scheduleBody) {
                console.warn('⚠️ presetScheduleBody not found');
                return null;
            }
            
            const nextDrawNumber = currentDrawNumber + 1;
            const rows = scheduleBody.querySelectorAll('tr');
            console.log('🎯 Checking preset schedule for next draw:', { rowsCount: rows.length, nextDrawNumber });
            
            for (let row of rows) {
                const drawCell = row.querySelector('td:first-child');
                if (drawCell) {
                    const drawText = drawCell.textContent.trim();
                    const drawMatch = drawText.match(/#(\d+)/);
                    if (drawMatch) {
                        const drawNum = parseInt(drawMatch[1]);
                        
                        if (drawNum === nextDrawNumber) {
                            // Found the next draw - get the number
                            const numberCell = row.querySelector('td:nth-child(3)');
                            if (numberCell) {
                                const numberSpan = numberCell.querySelector('.number-circle-sm');
                                if (numberSpan) {
                                    const number = parseInt(numberSpan.textContent.trim());
                                    const color = numberSpan.classList.contains('red') ? 'red' : 
                                                 numberSpan.classList.contains('black') ? 'black' : 'green';
                                    console.log('✅ Found preset number for next draw:', { number, color, drawNumber: nextDrawNumber });
                                    return { number, color, drawNumber: nextDrawNumber };
                                }
                            }
                        }
                    }
                }
            }
            
            console.warn('⚠️ No preset number found for next draw:', nextDrawNumber);
            return null;
        }
        
        // Get recommended number for current draw (from recommended numbers grid)
        function getRecommendedNumberForCurrentDraw() {
            // Get the active tab (default to no-bets)
            const activeTab = document.querySelector('.recommended-tab.active');
            const tabType = activeTab ? activeTab.getAttribute('data-type') : 'no-bets';
            
            // Get all recommended number items
            const recommendedItems = document.querySelectorAll('.recommended-number-item');
            
            if (recommendedItems.length === 0) {
                console.warn('⚠️ No recommended numbers found');
                return null;
            }
            
            // For no-bets tab, get the first number with 0 bets
            if (tabType === 'no-bets') {
                for (let item of recommendedItems) {
                    const betCountEl = item.querySelector('.recommended-number-value');
                    if (betCountEl) {
                        const betCount = parseInt(betCountEl.textContent.trim());
                        if (betCount === 0) {
                            const number = parseInt(item.getAttribute('data-number'));
                            console.log('✅ Found recommended number (no-bets):', number);
                            return number;
                        }
                    }
                }
            }
            
            // For lowest-payout tab, get the first number
            if (tabType === 'lowest-payout') {
                const firstItem = recommendedItems[0];
                if (firstItem) {
                    const number = parseInt(firstItem.getAttribute('data-number'));
                    console.log('✅ Found recommended number (lowest-payout):', number);
                    return number;
                }
            }
            
            // Fallback: return first available number
            if (recommendedItems.length > 0) {
                const firstItem = recommendedItems[0];
                const number = parseInt(firstItem.getAttribute('data-number'));
                console.log('✅ Found recommended number (fallback):', number);
                return number;
            }
            
            return null;
        }
        
        // Set next draw number from preset schedule automatically
        function setNextDrawNumberFromPresetSchedule() {
            console.log('🎯 Setting next draw number from preset schedule...');
            
            const nextPreset = getPresetNumberForNextDraw();
            
            if (nextPreset) {
                // Set as forced number for the next draw
                applyPresetNumberAsForced(nextPreset.number, null, null, false, 0, nextPreset.drawNumber);
                console.log('✅ Next draw number set from preset schedule:', { number: nextPreset.number, drawNumber: nextPreset.drawNumber });
                showToast('Next Draw Set', `Number ${nextPreset.number} automatically set for draw #${nextPreset.drawNumber}`, 'info');
            } else {
                console.warn('⚠️ No preset number found for next draw, will use smart selection when needed');
            }
        }
        
        // Apply preset number as forced number automatically
        async function applyPresetNumberAsForced(number, timePreset, patternType, isMobile, payout = 0, targetDrawNumber = null) {
            // Use target draw number if provided, otherwise use current draw number
            const drawNumber = targetDrawNumber || currentDrawNumber;
            
            // Set as forced number via API
            try {
                const bodyParams = `winning_number=${number}&keep_auto_mode=true`;
                const bodyWithDraw = targetDrawNumber ? `${bodyParams}&draw_number=${targetDrawNumber}` : bodyParams;
                
                const response = await fetch('../api/set_winning_number.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: bodyWithDraw
                });
                
                const data = await response.json();
                if (data.status === 'success') {
                    console.log('✅ Preset number applied as forced number:', { number, drawNumber });
                    
                    if (targetDrawNumber) {
                        showToast('Next Draw Set', `Number ${number} automatically set as forced number for draw #${drawNumber}`, 'success');
                    } else {
                        showToast('Applied', `Number ${number} automatically set as forced number for draw #${drawNumber}`, 'success');
                    }
                    
                    // Refresh forced number checker
                    setTimeout(() => {
                        checkForcedNumber();
                    }, 1000);
                } else {
                    console.warn('⚠️ Failed to apply preset number as forced:', data.message);
                }
            } catch (error) {
                console.error('❌ Error applying preset number as forced:', error);
            }
        }

        function saveSmartSelectionSettings(timePreset, patternType) {
            // Save settings to database for auto mode to use
            fetch('../api/save_smart_selection_settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `time_preset=${encodeURIComponent(timePreset)}&pattern_type=${encodeURIComponent(patternType)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    console.log('✅ Smart selection settings saved for auto mode');
                    showToast('Settings Saved', 'Smart selection preferences saved. Auto mode will use these settings.', 'success');
                } else {
                    console.warn('⚠️ Failed to save smart selection settings:', data.message);
                }
            })
            .catch(error => {
                console.warn('⚠️ Error saving smart selection settings:', error);
            });
        }

        function loadSmartSelectionSettings() {
            // Load saved smart selection settings
            fetch('../api/get_smart_selection_settings.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success' && data.data) {
                        const timePreset = data.data.time_preset || 'auto';
                        const patternType = data.data.pattern_type || 'smart';
                        
                        // Update desktop dropdowns
                        if (document.getElementById('timePreset')) {
                            document.getElementById('timePreset').value = timePreset;
                        }
                        if (document.getElementById('patternType')) {
                            document.getElementById('patternType').value = patternType;
                        }
                        
                        // Update mobile dropdowns
                        if (document.getElementById('timePreset-mobile')) {
                            document.getElementById('timePreset-mobile').value = timePreset;
                        }
                        if (document.getElementById('patternType-mobile')) {
                            document.getElementById('patternType-mobile').value = patternType;
                        }
                        
                        console.log('✅ Smart selection settings loaded:', { timePreset, patternType });
                    }
                })
                .catch(error => {
                    console.warn('⚠️ Error loading smart selection settings:', error);
                });
        }

        function displayPatternVisualization(patternAnalysis, data) {
            const container = document.getElementById('patternVisualization');
            const lastThree = patternAnalysis.last_three || [];
            
            let html = '<div class="mb-3">';
            html += '<strong>Recent Pattern:</strong> ';
            lastThree.forEach((num, index) => {
                const color = num === 0 ? 'green' : ([1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36].includes(num) ? 'red' : 'black');
                html += `<span class="pattern-item ${index === 0 ? 'highlight' : ''} ${color}" style="background-color: ${color === 'red' ? '#f8d7da' : (color === 'black' ? '#d1ecf1' : '#d4edda')};">${num}</span>`;
            });
            html += '</div>';
            
            html += '<div class="mb-2"><strong>Mathematical Basis:</strong> ' + patternAnalysis.mathematical_basis + '</div>';
            html += '<div class="mb-2"><strong>Strategy:</strong> ' + data.pattern_type + ' pattern with time-based optimization</div>';
            html += '<div><strong>Analysis:</strong> ' + data.numbers_with_no_bets + ' numbers with no bets, ' + data.low_payout_options + ' low payout options analyzed</div>';
            
            container.innerHTML = html;
        }

        // Generate preset schedule showing numbers and their scheduled times
        async function generatePresetSchedule(timePreset, patternType, currentNumber, selectionData) {
            const scheduleContainer = document.getElementById('presetSchedule');
            const scheduleBody = document.getElementById('presetScheduleBody');
            const placeholder = document.getElementById('presetSchedulePlaceholder');
            
            if (!scheduleContainer || !scheduleBody) return;
            
            // Use data from selectionData (already fetched by generateSmartNumber)
            let recentNumbers = selectionData?.recent_numbers || [];
            let payoutData = selectionData?.payout_data || {};
            
            // If not in selectionData, try to fetch
            if (recentNumbers.length === 0) {
                try {
                    const recentResponse = await fetch(`../api/draw_info.php?_cb=${Date.now()}`);
                    if (recentResponse.ok) {
                        const recentData = await recentResponse.json();
                        if (recentData.status === 'success' && recentData.data.recent_rolls) {
                            recentNumbers = recentData.data.recent_rolls.map(n => parseInt(n)).filter(n => !isNaN(n));
                        }
                    }
                } catch (error) {
                    console.warn('Could not fetch recent numbers:', error);
                }
            }
            
            // Show progress indicator
            const progressIndicator = document.getElementById('presetScheduleProgress');
            if (progressIndicator) {
                progressIndicator.style.display = 'block';
                progressIndicator.textContent = 'Generating 480 preset numbers... This may take a moment.';
            }
            
            // Calculate start draw number based on current draw and time of day
            // We want to start from the next draw that aligns with the current time
            const now = new Date();
            const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            const minutesSinceMidnight = (now.getTime() - today.getTime()) / (1000 * 60);
            const drawsSinceMidnight = Math.floor(minutesSinceMidnight / 3);
            
            // Calculate the first draw number for today (assuming draws start at midnight)
            // If current draw is already past today's start, use current draw as start
            const todayStartDraw = Math.max(1, currentDrawNumber - drawsSinceMidnight);
            const startDrawNumber = Math.max(todayStartDraw, currentDrawNumber);
            
            const schedule = [];
            
            // Get pattern-based number sequence using mathematical calculations (480 numbers)
            console.log('🎯 Generating 480 preset numbers...');
            let numberSequence = await generateMathBasedSequence(patternType, timePreset, currentNumber, selectionData, recentNumbers, payoutData);
            console.log('✅ Generated 480 numbers:', numberSequence.length);
            
            // Generate schedule for 480 draws (24 hours)
            const startTime = new Date(now);
            // Align start time to the next 3-minute interval
            const currentMinutes = startTime.getMinutes();
            const currentSeconds = startTime.getSeconds();
            const minutesToNextInterval = 3 - (currentMinutes % 3);
            const secondsToNextInterval = minutesToNextInterval * 60 - currentSeconds;
            startTime.setSeconds(startTime.getSeconds() + secondsToNextInterval);
            startTime.setMilliseconds(0);
            
            for (let i = 0; i < 480; i++) {
                const drawNumber = startDrawNumber + i;
                const drawTime = new Date(startTime.getTime() + (i * 3 * 60 * 1000)); // 3 minutes per draw
                const scheduledNumber = numberSequence[i];
                const numberColor = getNumberColorForSchedule(scheduledNumber);
                
                // Determine pattern description based on calculation
                let patternDesc = getPatternDescription(patternType, i, recentNumbers, scheduledNumber);
                
                schedule.push({
                    draw_number: drawNumber,
                    winning_number: scheduledNumber,
                    color: numberColor,
                    scheduled_time: drawTime.toISOString().slice(0, 19).replace('T', ' '),
                    pattern: patternDesc
                });
            }
            
            // Save to database
            console.log('💾 Saving preset schedule to database...');
            const saveResult = await savePresetScheduleToDatabase(schedule, timePreset, patternType, startDrawNumber);
            
            if (saveResult.success) {
                console.log('✅ Preset schedule saved to database');
                showToast('Schedule Saved', `480 preset numbers saved for 24 hours (Draws #${startDrawNumber} to #${startDrawNumber + 479})`, 'success');
            } else {
                console.warn('⚠️ Failed to save preset schedule to database:', saveResult.message);
                showToast('Warning', 'Schedule generated but failed to save to database: ' + saveResult.message, 'warning');
            }
            
            // Clear and populate table (show first 30 entries, make it scrollable)
            scheduleBody.innerHTML = '';
            const displayCount = Math.min(30, schedule.length); // Show first 30 entries in table
            
            for (let i = 0; i < displayCount; i++) {
                const item = schedule[i];
                const row = document.createElement('tr');
                const bgColor = item.color === 'red' ? '#dc3545' : item.color === 'black' ? '#343a40' : '#28a745';
                const displayTime = new Date(item.scheduled_time).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
                row.innerHTML = `
                    <td><strong>#${item.draw_number}</strong></td>
                    <td><small>${displayTime}</small></td>
                    <td>
                        <span class="number-circle-sm ${item.color}" style="display: inline-block; width: 30px; height: 30px; line-height: 30px; text-align: center; border-radius: 50%; color: white; font-weight: bold; background-color: ${bgColor};">
                            ${item.winning_number}
                        </span>
                    </td>
                    <td><small class="text-muted">${item.pattern}</small></td>
                `;
                scheduleBody.appendChild(row);
            }
            
            // Add note if there are more entries
            if (schedule.length > displayCount) {
                const noteRow = document.createElement('tr');
                noteRow.innerHTML = `<td colspan="4" class="text-center text-muted"><small>... and ${schedule.length - displayCount} more draws (scroll to see all)</small></td>`;
                scheduleBody.appendChild(noteRow);
            }
            
            // Hide progress indicator
            if (progressIndicator) {
                progressIndicator.style.display = 'none';
            }
            
            // Show schedule, hide placeholder
            scheduleContainer.style.display = 'block';
            if (placeholder) placeholder.style.display = 'none';
            
            // Update schedule status
            updatePresetScheduleStatus(startDrawNumber, startDrawNumber + 479, schedule.length);
        }
        
        // Generate number sequence using mathematical calculations that look predictable
        async function generateMathBasedSequence(patternType, timePreset, currentNumber, selectionData, recentNumbers, payoutData) {
            const sequence = [];
            const usedNumbers = new Set(); // Track numbers already used in this sequence
            const rouletteNumbers = Array.from({length: 37}, (_, i) => i);
            
            // Get numbers with low/no payouts (house edge protection)
            const lowPayoutNumbers = getLowPayoutNumbers(payoutData);
            const noBetNumbers = getNoBetNumbers(payoutData);
            
            // Start with recent numbers for calculations
            let lastNumber = recentNumbers.length > 0 ? recentNumbers[0] : currentNumber;
            let secondLast = recentNumbers.length > 1 ? recentNumbers[1] : (lastNumber > 0 ? lastNumber - 1 : 36);
            let thirdLast = recentNumbers.length > 2 ? recentNumbers[2] : (secondLast > 0 ? secondLast - 1 : 36);
            
            // Create pool of available numbers (prioritize low payout, but include all)
            const availablePool = [...new Set([
                ...(noBetNumbers.length > 0 ? noBetNumbers : []),
                ...(lowPayoutNumbers.length > 0 ? lowPayoutNumbers : []),
                ...rouletteNumbers
            ])];
            
            // Generate 480 numbers using mathematical patterns with variety
            // Since we have 480 numbers but only 37 possible values, numbers will repeat
            // We'll use a sliding window approach to avoid immediate repetitions
            const recentWindowSize = 15; // Don't repeat within last 15 numbers
            const recentWindow = [];
            
            for (let i = 0; i < 480; i++) {
                let calculatedNumber;
                let attempts = 0;
                const maxAttempts = 100; // Increased for larger sequence
                
                do {
                    attempts++;
                    
                    if (patternType === 'fibonacci') {
                        // Fibonacci-like: sum of last two, but with variations
                        if (i === 0) {
                            calculatedNumber = (lastNumber + secondLast) % 37;
                        } else {
                            const prev = sequence[sequence.length - 1];
                            const prevPrev = sequence.length > 1 ? sequence[sequence.length - 2] : lastNumber;
                            // Add variation to avoid repetition
                            const variation = (i % 7) - 3; // Vary between -3 and 3
                            calculatedNumber = (prev + prevPrev + variation) % 37;
                            if (calculatedNumber < 0) calculatedNumber += 37;
                        }
                    } else if (patternType === 'color_alternate') {
                        // Alternate colors with math: if last was red, next is black (but calculated)
                        const lastColor = getNumberColorForSchedule(lastNumber);
                        const targetColor = lastColor === 'red' ? 'black' : (lastColor === 'black' ? 'red' : 'green');
                        const colorNumbers = getNumbersByColor(targetColor);
                        
                        // Vary the calculation to get different numbers
                        const addValue = 7 + (i % 13); // More variation for 480 numbers
                        calculatedNumber = (lastNumber + addValue) % 37;
                        
                        // If not the right color, find nearest in target color
                        if (getNumberColorForSchedule(calculatedNumber) !== targetColor) {
                            calculatedNumber = findNearestColorNumber(calculatedNumber, targetColor);
                        }
                    } else if (patternType === 'cold_numbers') {
                        // Use cold numbers but calculate position with more variety
                        const coldNumbers = rouletteNumbers.filter(n => !recentNumbers.includes(n));
                        if (coldNumbers.length > 0) {
                            const step = 3 + (i % 7); // More variation
                            const index = (lastNumber + i * step) % coldNumbers.length;
                            calculatedNumber = coldNumbers[index];
                        } else {
                            // Fallback: use any number not recently used
                            calculatedNumber = (lastNumber + 5 + i * 3) % 37;
                        }
                    } else if (patternType === 'lowest_payout') {
                        // Use lowest payout but with more variety
                        if (lowPayoutNumbers.length > 0) {
                            const step = 2 + (i % 5); // More variation
                            const index = (lastNumber + i * step) % lowPayoutNumbers.length;
                            calculatedNumber = lowPayoutNumbers[index];
                        } else {
                            calculatedNumber = (lastNumber + 3 + i * 2) % 37;
                        }
                    } else {
                        // Smart pattern: mix of different math operations with more variety
                        const operations = [
                            () => (lastNumber + 7 + (i % 13)) % 37,  // Add 7 + variation
                            () => (lastNumber + secondLast + (i % 11)) % 37,  // Sum + variation
                            () => (Math.abs(lastNumber - secondLast) + (i % 17)) % 37,  // Difference + variation
                            () => (Math.floor((lastNumber + secondLast + thirdLast) / 3) + (i % 7)) % 37,  // Average + variation
                            () => (lastNumber * 2 + (i % 19)) % 37,  // Double + variation
                            () => (lastNumber + 13 + ((i * 2) % 23)) % 37,  // Add 13 + variation
                            () => (lastNumber + secondLast + 5 + (i % 9)) % 37,  // Sum + 5 + variation
                            () => (lastNumber + 11 + (i % 15)) % 37,  // Add 11 + variation
                            () => (secondLast * 2 - lastNumber + (i % 21)) % 37,  // Complex pattern
                            () => (Math.floor((lastNumber + thirdLast) / 2) + (i % 13)) % 37,  // Midpoint pattern
                            () => ((lastNumber + secondLast + thirdLast + i) % 37),  // Sum of three + index
                            () => ((lastNumber * 3 - secondLast + i) % 37),  // Triple minus previous
                            () => ((lastNumber + i * 3) % 37),  // Linear progression
                            () => ((lastNumber ^ secondLast) + i) % 37,  // XOR operation
                            () => (Math.floor(Math.sqrt(lastNumber * secondLast)) + (i % 7)) % 37,  // Square root pattern
                        ];
                        
                        const opIndex = i % operations.length;
                        calculatedNumber = operations[opIndex]();
                    }
                    
                    // Ensure number is valid (0-36)
                    calculatedNumber = Math.max(0, Math.min(36, Math.floor(calculatedNumber)));
                    
                    // Check if number was used in recent window (last 15 numbers)
                    const inRecentWindow = recentWindow.includes(calculatedNumber);
                    
                    // If in recent window, try to find alternative
                    if (inRecentWindow && attempts < maxAttempts) {
                        // Find number not in recent window
                        const availableNumbers = rouletteNumbers.filter(n => !recentWindow.includes(n));
                        if (availableNumbers.length > 0) {
                            // Prefer low payout numbers if available
                            const preferredNumbers = availableNumbers.filter(n => 
                                noBetNumbers.includes(n) || lowPayoutNumbers.includes(n)
                            );
                            if (preferredNumbers.length > 0) {
                                calculatedNumber = preferredNumbers[Math.floor(Math.random() * preferredNumbers.length)];
                            } else {
                                calculatedNumber = availableNumbers[Math.floor(Math.random() * availableNumbers.length)];
                            }
                        } else {
                            // All numbers in recent window, allow but add variation
                            calculatedNumber = (calculatedNumber + 1 + (attempts % 5)) % 37;
                        }
                    }
                    
                } while (recentWindow.includes(calculatedNumber) && attempts < maxAttempts);
                
                // House edge: Prefer low/no payout numbers when possible
                if (!recentWindow.includes(calculatedNumber)) {
                    if (noBetNumbers.length > 0 && !noBetNumbers.includes(calculatedNumber)) {
                        const availableNoBet = noBetNumbers.filter(n => !recentWindow.includes(n));
                        if (availableNoBet.length > 0 && Math.random() < 0.3) { // 30% chance to prefer no-bet
                            calculatedNumber = availableNoBet[Math.floor(Math.random() * availableNoBet.length)];
                        }
                    } else if (lowPayoutNumbers.length > 0 && !lowPayoutNumbers.includes(calculatedNumber)) {
                        const availableLowPayout = lowPayoutNumbers.filter(n => !recentWindow.includes(n));
                        if (availableLowPayout.length > 0 && Math.random() < 0.2) { // 20% chance to prefer low-payout
                            calculatedNumber = availableLowPayout[Math.floor(Math.random() * availableLowPayout.length)];
                        }
                    }
                }
                
                // ⚠️ CRITICAL: Validate multiple constraints to prevent abnormal patterns
                // 1. No more than 2 consecutive identical numbers
                // 2. No more than 5 same numbers per hour (20 draws per hour)
                // 3. Daily frequency limit per number (reasonable distribution)
                
                let needsChange = false;
                let changeReason = '';
                
                // Constraint 1: Check for 3+ consecutive identical numbers
                if (sequence.length >= 2) {
                    const lastTwo = sequence.slice(-2);
                    if (lastTwo[0] === lastTwo[1] && lastTwo[0] === calculatedNumber) {
                        needsChange = true;
                        changeReason = `3+ consecutive ${calculatedNumber}`;
                    }
                }
                
                // Constraint 2: Check hourly frequency (max 5 same number per hour)
                // Each hour has 20 draws (3-minute intervals), so check last 20 draws
                if (!needsChange && sequence.length >= 20) {
                    const hourWindow = sequence.slice(-20);
                    const countInHour = hourWindow.filter(n => n === calculatedNumber).length;
                    if (countInHour >= 5) {
                        needsChange = true;
                        changeReason = `${calculatedNumber} already appeared ${countInHour} times in this hour (max 5)`;
                    }
                }
                
                // Constraint 3: Check daily frequency (max reasonable limit per number)
                // With 480 draws and 37 numbers, average is ~13 per number
                // Setting max to 20-25 per day per number (allows some variation)
                const maxDailyFrequency = 25;
                const countInDay = sequence.filter(n => n === calculatedNumber).length;
                if (!needsChange && countInDay >= maxDailyFrequency) {
                    needsChange = true;
                    changeReason = `${calculatedNumber} already appeared ${countInDay} times today (max ${maxDailyFrequency})`;
                }
                
                if (needsChange) {
                    console.log(`⚠️ ${changeReason} at position ${i} (draw #${startDrawNumber + i}) - changing to different number`);
                    
                    // Find alternative numbers that meet all constraints
                    let alternativeNumbers = rouletteNumbers.filter(n => {
                        // Not the same as calculated number
                        if (n === calculatedNumber) return false;
                        
                        // Not violating consecutive constraint (if we have 2 previous)
                        if (sequence.length >= 2) {
                            const lastTwo = sequence.slice(-2);
                            if (lastTwo[0] === lastTwo[1] && lastTwo[0] === n) return false;
                        }
                        
                        // Not violating hourly frequency (max 5 per hour)
                        if (sequence.length >= 20) {
                            const hourWindow = sequence.slice(-20);
                            if (hourWindow.filter(x => x === n).length >= 5) return false;
                        }
                        
                        // Not violating daily frequency (max 25 per day)
                        if (sequence.filter(x => x === n).length >= maxDailyFrequency) return false;
                        
                        return true;
                    });
                    
                    // If no alternatives found that meet all constraints, relax constraints
                    if (alternativeNumbers.length === 0) {
                        // Relax: just avoid consecutive and very high frequency
                        alternativeNumbers = rouletteNumbers.filter(n => {
                            if (n === calculatedNumber) return false;
                            if (sequence.length >= 2) {
                                const lastTwo = sequence.slice(-2);
                                if (lastTwo[0] === lastTwo[1] && lastTwo[0] === n) return false;
                            }
                            return true;
                        });
                    }
                    
                    if (alternativeNumbers.length > 0) {
                        // Prefer low payout numbers if available
                        const preferredAlternatives = alternativeNumbers.filter(n => 
                            (noBetNumbers.length === 0 || noBetNumbers.includes(n)) || 
                            (lowPayoutNumbers.length === 0 || lowPayoutNumbers.includes(n))
                        );
                        
                        if (preferredAlternatives.length > 0) {
                            // Use weighted selection - prefer numbers closer to calculated number
                            const sortedAlternatives = preferredAlternatives.sort((a, b) => {
                                const distA = Math.min(Math.abs(a - calculatedNumber), Math.abs(37 + a - calculatedNumber));
                                const distB = Math.min(Math.abs(b - calculatedNumber), Math.abs(37 + b - calculatedNumber));
                                return distA - distB;
                            });
                            calculatedNumber = sortedAlternatives[0];
                        } else {
                            // Fallback: use any alternative number
                            calculatedNumber = alternativeNumbers[(i * 7) % alternativeNumbers.length];
                        }
                        
                        console.log(`✅ Changed to ${calculatedNumber} - Reason: ${changeReason}`);
                    } else {
                        console.warn(`⚠️ Could not find alternative number that meets all constraints at position ${i}`);
                    }
                }
                
                sequence.push(calculatedNumber);
                
                // Update recent window (sliding window of last 15 numbers)
                recentWindow.push(calculatedNumber);
                if (recentWindow.length > recentWindowSize) {
                    recentWindow.shift(); // Remove oldest
                }
                
                // Update for next iteration
                thirdLast = secondLast;
                secondLast = lastNumber;
                lastNumber = calculatedNumber;
                
                // Progress indicator for large sequence (every 50 numbers)
                if ((i + 1) % 50 === 0) {
                    console.log(`Generated ${i + 1}/480 numbers...`);
                }
            }
            
            // For 480 numbers, we don't need to shuffle as much since we already have variety
            // Only do a light shuffle to break obvious patterns
            if (sequence.length <= 50) {
                // For smaller sequences, do full shuffle
                return shuffleWithMathPreservation(sequence, recentNumbers);
            } else {
                // For large sequences (480), do light shuffle to maintain performance
                const shuffled = [...sequence];
                // Only swap every 10th pair to break obvious patterns
                for (let i = 0; i < shuffled.length - 1; i += 10) {
                    if (Math.random() > 0.7) {
                        const swapIdx = Math.min(i + 1, shuffled.length - 1);
                        [shuffled[i], shuffled[swapIdx]] = [shuffled[swapIdx], shuffled[i]];
                    }
                }
                return shuffled;
            }
        }
        
        // Save preset schedule to database
        async function savePresetScheduleToDatabase(schedule, timePreset, patternType, startDrawNumber) {
            try {
                const scheduleDate = new Date().toISOString().split('T')[0]; // YYYY-MM-DD
                const endDrawNumber = startDrawNumber + schedule.length - 1;
                
                const payload = {
                    schedule_date: scheduleDate,
                    start_draw_number: startDrawNumber,
                    end_draw_number: endDrawNumber,
                    time_preset: timePreset,
                    pattern_type: patternType,
                    schedule_data: schedule
                };
                
                console.log('💾 Saving preset schedule:', { scheduleDate, startDrawNumber, endDrawNumber, totalDraws: schedule.length });
                
                const response = await fetch('../api/save_preset_schedule.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    return { success: true, data: data.data };
                } else {
                    return { success: false, message: data.message || 'Unknown error' };
                }
            } catch (error) {
                console.error('❌ Error saving preset schedule:', error);
                return { success: false, message: error.message };
            }
        }
        
        // Update preset schedule status display
        function updatePresetScheduleStatus(startDraw, endDraw, totalDraws) {
            const statusElement = document.getElementById('presetScheduleStatus');
            if (statusElement) {
                const scheduleDate = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
                statusElement.innerHTML = `
                    <i class="fas fa-check-circle text-success"></i> 
                    Schedule loaded for <strong>${scheduleDate}</strong> | 
                    Draws <strong>#${startDraw}</strong> to <strong>#${endDraw}</strong> 
                    (${totalDraws} total draws)
                `;
                statusElement.style.display = 'block';
            }
        }
        
        // Check and auto-generate preset schedule if needed
        async function checkAndAutoGeneratePreset() {
            try {
                console.log('🔍 Checking if preset schedule exists for today...');
                
                // Check if schedule exists for today
                const checkResponse = await fetch(`../api/check_preset_schedule.php?_cb=${Date.now()}`);
                if (!checkResponse.ok) {
                    console.warn('⚠️ Failed to check preset schedule:', checkResponse.status);
                    return;
                }
                
                const checkData = await checkResponse.json();
                
                if (checkData.exists) {
                    // Schedule exists, load it
                    console.log('✅ Preset schedule exists for today:', checkData.data);
                    await loadPresetScheduleFromDatabase(checkData.data.schedule_date);
                } else {
                    // Schedule doesn't exist, auto-generate it
                    console.log('📅 No preset schedule found for today, auto-generating...');
                    
                    // Get saved settings or use defaults
                    const savedSettings = loadSmartSelectionSettings();
                    const timePreset = savedSettings.timePreset || 'auto';
                    const patternType = savedSettings.patternType || 'smart';
                    
                    // Show notification
                    showToast('Auto-Generating', 'Creating preset schedule for today (480 draws)...', 'info');
                    
                    // Generate schedule by calling generateSmartNumber which will create and save it
                    await generateSmartNumber(false);
                    
                    console.log('✅ Preset schedule auto-generated for today');
                }
            } catch (error) {
                console.error('❌ Error checking/auto-generating preset schedule:', error);
                // Don't show error to user, just log it
            }
        }
        
        // Load preset schedule from database and display it
        async function loadPresetScheduleFromDatabase(date = null) {
            try {
                const loadUrl = date ? 
                    `../api/load_preset_schedule.php?date=${date}&_cb=${Date.now()}` :
                    `../api/load_preset_schedule.php?_cb=${Date.now()}`;
                
                const response = await fetch(loadUrl);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.status === 'success' && data.data && data.data.schedule_data) {
                    const schedule = data.data.schedule_data;
                    const scheduleContainer = document.getElementById('presetSchedule');
                    const scheduleBody = document.getElementById('presetScheduleBody');
                    const placeholder = document.getElementById('presetSchedulePlaceholder');
                    
                    if (!scheduleContainer || !scheduleBody) return;
                    
                    // Clear and populate table (show first 30 entries)
                    scheduleBody.innerHTML = '';
                    const displayCount = Math.min(30, schedule.length);
                    
                    for (let i = 0; i < displayCount; i++) {
                        const item = schedule[i];
                        const row = document.createElement('tr');
                        const bgColor = item.color === 'red' ? '#dc3545' : item.color === 'black' ? '#343a40' : '#28a745';
                        const displayTime = new Date(item.scheduled_time).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
                        row.innerHTML = `
                            <td><strong>#${item.draw_number}</strong></td>
                            <td><small>${displayTime}</small></td>
                            <td>
                                <span class="number-circle-sm ${item.color}" style="display: inline-block; width: 30px; height: 30px; line-height: 30px; text-align: center; border-radius: 50%; color: white; font-weight: bold; background-color: ${bgColor};">
                                    ${item.winning_number}
                                </span>
                            </td>
                            <td><small class="text-muted">${item.pattern || 'Preset'}</small></td>
                        `;
                        scheduleBody.appendChild(row);
                    }
                    
                    // Add note if there are more entries
                    if (schedule.length > displayCount) {
                        const noteRow = document.createElement('tr');
                        noteRow.innerHTML = `<td colspan="4" class="text-center text-muted"><small>... and ${schedule.length - displayCount} more draws (scroll to see all)</small></td>`;
                        scheduleBody.appendChild(noteRow);
                    }
                    
                    // Update status
                    updatePresetScheduleStatus(data.data.start_draw_number, data.data.end_draw_number, data.data.total_draws);
                    
                    // Show schedule, hide placeholder
                    scheduleContainer.style.display = 'block';
                    if (placeholder) placeholder.style.display = 'none';
                    
                    console.log('✅ Preset schedule loaded from database:', { 
                        totalDraws: schedule.length, 
                        startDraw: data.data.start_draw_number, 
                        endDraw: data.data.end_draw_number 
                    });
                } else {
                    console.log('ℹ️ No preset schedule found in database');
                }
            } catch (error) {
                console.error('❌ Error loading preset schedule from database:', error);
            }
        }
        
        // Check for midnight transition and auto-generate new schedule
        let lastCheckedDate = new Date().toDateString();
        function checkMidnightTransition() {
            const currentDate = new Date().toDateString();
            
            if (currentDate !== lastCheckedDate) {
                console.log('🕛 Midnight transition detected, generating new preset schedule...');
                lastCheckedDate = currentDate;
                
                // Deactivate old schedule (handled by API when saving new one)
                // Auto-generate new schedule
                checkAndAutoGeneratePreset();
            }
        }
        
        // Set up midnight check interval (check every minute)
        setInterval(checkMidnightTransition, 60000);
        
        // Get numbers with low payouts
        function getLowPayoutNumbers(payoutData) {
            if (!payoutData || !payoutData.number_payouts) {
                return [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
            }
            
            const payouts = payoutData.number_payouts;
            const numbers = Object.keys(payouts).map(n => parseInt(n));
            numbers.sort((a, b) => (payouts[a] || 0) - (payouts[b] || 0));
            
            // Return bottom 50% with lowest payouts
            return numbers.slice(0, Math.floor(numbers.length / 2));
        }
        
        // Get numbers with no bets
        function getNoBetNumbers(payoutData) {
            if (!payoutData || !payoutData.number_payouts) {
                return [];
            }
            
            const payouts = payoutData.number_payouts;
            const numbers = [];
            for (let i = 0; i <= 36; i++) {
                if (!payouts[i] || payouts[i] === 0) {
                    numbers.push(i);
                }
            }
            return numbers;
        }
        
        // Find similar number with low payout
        function findSimilarLowPayoutNumber(target, noBetNumbers, lowPayoutNumbers) {
            // First try no-bet numbers
            if (noBetNumbers.length > 0) {
                // Find closest number
                const closest = noBetNumbers.reduce((prev, curr) => {
                    return Math.abs(curr - target) < Math.abs(prev - target) ? curr : prev;
                });
                return closest;
            }
            
            // Then try low payout numbers
            if (lowPayoutNumbers.length > 0) {
                const closest = lowPayoutNumbers.reduce((prev, curr) => {
                    return Math.abs(curr - target) < Math.abs(prev - target) ? curr : prev;
                });
                return closest;
            }
            
            return null;
        }
        
        // Get numbers by color
        function getNumbersByColor(color) {
            const redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
            const blackNumbers = [2, 4, 6, 8, 10, 11, 13, 15, 17, 20, 22, 24, 26, 28, 29, 31, 33, 35];
            
            if (color === 'red') return redNumbers;
            if (color === 'black') return blackNumbers;
            return [0];
        }
        
        // Find nearest number of specific color
        function findNearestColorNumber(number, targetColor) {
            const colorNumbers = getNumbersByColor(targetColor);
            return colorNumbers.reduce((prev, curr) => {
                return Math.abs(curr - number) < Math.abs(prev - number) ? curr : prev;
            });
        }
        
        // Shuffle while preserving some mathematical relationships
        function shuffleWithMathPreservation(sequence, recentNumbers) {
            // Don't fully randomize - keep some mathematical flow
            // But mix it up so it's not sequential
            const shuffled = [...sequence];
            
            // Partial shuffle: swap some adjacent pairs
            for (let i = 0; i < shuffled.length - 1; i += 2) {
                // Swap every other pair to break sequential pattern
                if (Math.random() > 0.5) {
                    [shuffled[i], shuffled[i + 1]] = [shuffled[i + 1], shuffled[i]];
                }
            }
            
            // Randomly swap a few non-adjacent elements (but keep math relationships)
            for (let i = 0; i < 3; i++) {
                const idx1 = Math.floor(Math.random() * shuffled.length);
                const idx2 = Math.floor(Math.random() * shuffled.length);
                if (Math.abs(idx1 - idx2) > 2) { // Only swap if not too close
                    [shuffled[idx1], shuffled[idx2]] = [shuffled[idx2], shuffled[idx1]];
                }
            }
            
            return shuffled;
        }
        
        // Get pattern description for display
        function getPatternDescription(patternType, index, recentNumbers, number) {
            if (recentNumbers.length >= 2) {
                const last = recentNumbers[0];
                const second = recentNumbers[1];
                
                if (patternType === 'fibonacci') {
                    return `(${last} + ${second}) mod 37`;
                } else if (patternType === 'color_alternate') {
                    return `(${last} + 7) mod 37`;
                } else if (patternType === 'smart') {
                    const ops = ['+7', '+13', 'sum', 'avg', '×2'];
                    return `${last} ${ops[index % ops.length]} pattern`;
                }
            }
            
            return patternType === 'fibonacci' ? 'Fibonacci-like' :
                   patternType === 'color_alternate' ? 'Color alternation' :
                   patternType === 'cold_numbers' ? 'Cold number' :
                   patternType === 'lowest_payout' ? 'Low payout' :
                   'Math calculation';
        }
        
        // Get time-based numbers based on preset
        function getTimeBasedNumbers(timePreset) {
            const now = new Date();
            const hour = now.getHours();
            
            let numbers = [];
            
            if (timePreset === 'morning' || (timePreset === 'auto' && hour >= 6 && hour < 12)) {
                // Morning: Lower numbers (0-12)
                numbers = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
            } else if (timePreset === 'afternoon' || (timePreset === 'auto' && hour >= 12 && hour < 18)) {
                // Afternoon: Mid range (13-24)
                numbers = [13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24];
            } else if (timePreset === 'evening' || (timePreset === 'auto' && hour >= 18 && hour < 24)) {
                // Evening: Higher numbers (25-36)
                numbers = [25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36];
            } else {
                // Night: Random distribution
                numbers = Array.from({length: 37}, (_, i) => i);
            }
            
            // Create a varied sequence
            const sequence = [];
            for (let i = 0; i < 15; i++) {
                sequence.push(numbers[i % numbers.length]);
            }
            
            return sequence;
        }
        
        // Helper function to get number color for schedule
        function getNumberColorForSchedule(number) {
            const num = parseInt(number);
            if (num === 0) return 'green';
            const redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
            return redNumbers.includes(num) ? 'red' : 'black';
        }

        function setManualWinningNumber() {
            const manualInput = document.getElementById('manualWinningNumber');
            if (!manualInput) {
                console.error('❌ manualWinningNumber input not found');
                showError('Manual winning number input not found');
                return;
            }
            
            const number = parseInt(manualInput.value);
            console.log('🎯 setManualWinningNumber called:', { number, currentDrawNumber, currentMode: isAutoMode ? 'Auto' : 'Manual' });

            if (isNaN(number) || number < 0 || number > 36) {
                console.error('❌ Invalid number:', number);
                showError('Please enter a valid number (0-36)');
                return;
            }

            // ⚠️ CRITICAL: If in Auto Mode, switch to Manual Mode first
            // This ensures the number is always saved as 'manual' in the database
            if (isAutoMode) {
                console.log('⚠️ Currently in Auto Mode - switching to Manual Mode to ensure number is saved correctly');
                // Switch to manual mode
                isAutoMode = false;
                document.getElementById('currentMode').textContent = 'Manual';
                document.getElementById('modeToggleText').textContent = 'Switch to Auto';
                // Also update the API if needed (optional, but good for consistency)
                fetch('../api/toggle_mode.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'mode=manual'
                }).catch(err => console.warn('Could not update mode on server:', err));
            }

            // ⚠️ CRITICAL: Set flag to prevent auto-apply from interfering
            // But we'll clear it after the API call completes so checkForcedNumber can run
            isManualSelectionInProgress = true;
            console.log('🔒 Setting isManualSelectionInProgress = true to prevent auto-apply interference');

            // Check if auto-apply is enabled - if so, don't submit in manual mode
            const autoApplyCheckbox = document.getElementById('autoApplyForcedNumber');
            const isAutoApplyEnabled = autoApplyCheckbox ? autoApplyCheckbox.checked : false;
            
            // In manual mode (auto-apply disabled), always submit the number
            // In auto mode (auto-apply enabled), the system will handle it automatically
            // But if user manually selects, we should still submit it
            console.log('📤 Submitting winning number:', { number, currentDrawNumber, isAutoApplyEnabled, mode: 'Manual (forced)' });
            
            // ⚠️ CRITICAL: ALWAYS call submitWinningNumber with keepAutoMode = false (manual mode)
            // This ensures the number is ALWAYS saved with source='manual' in the database
            // regardless of any other settings or modes
            submitWinningNumber(number, false);
            
            // Clear the flag after a delay to allow the API call to complete
            setTimeout(() => {
                isManualSelectionInProgress = false;
                console.log('🔓 Clearing isManualSelectionInProgress flag');
            }, 2000); // 2 seconds should be enough for the API call
        }

        async function executeAutoDraw() {
            showToast('Auto Draw', 'Generating winning number...', 'info');
            
            // First, try to get number from preset schedule
            const presetNumber = await getPresetNumberForCurrentDraw();
            
            if (presetNumber) {
                // Use preset schedule number
                console.log('Auto-selected number from preset schedule:', presetNumber.number);
                submitWinningNumber(presetNumber.number, true);
                
                // After submitting, automatically set next draw number from preset schedule
                setTimeout(() => {
                    setNextDrawNumberFromPresetSchedule();
                }, 1500);
                
                return;
            }
            
            // If no preset schedule, check for bets and use smart selection
            fetch(`../api/get_bet_distribution.php?draw_number=${currentDrawNumber}&_cb=${Date.now()}`)
                .then(response => response.json())
                .then(betData => {
                    const hasBets = betData.success && betData.summary && betData.summary.total_bets > 0;
                    
                    if (!hasBets) {
                        // No bets - use random or skip
                        console.log('No bets found, skipping auto draw');
                        resetTimer();
                        fetchDrawInfo();
                        return;
                    }
                    
                    // Has bets - use smart selection
                    const timePreset = document.getElementById('timePreset').value;
                    const patternType = document.getElementById('patternType').value;
                    
                    fetch(`../api/smart_number_selection.php?draw_number=${currentDrawNumber}&time_preset=${timePreset}&pattern_type=${patternType}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                const number = data.data.selected_number;
                                const payout = data.data.payout;
                                
                                // Check payout - if too high, skip this draw
                                const maxPayout = 500;
                                if (payout > maxPayout) {
                                    console.warn('Payout too high, skipping auto draw');
                                    showToast('Skipped', `Payout too high ($${parseFloat(payout).toFixed(2)}), skipping draw`, 'warning');
                                    resetTimer();
                                    fetchDrawInfo();
                                    return;
                                }
                                
                                console.log('Auto-selected number:', number);
                                submitWinningNumber(number, true);
                                
                                // After submitting, automatically set next draw number from preset schedule
                                setTimeout(() => {
                                    setNextDrawNumberFromPresetSchedule();
                                }, 1500);
                            } else {
                                console.error('Failed to generate smart number:', data);
                                resetTimer();
                                fetchDrawInfo();
                            }
                        })
                        .catch(error => {
                            console.error('Error in auto draw:', error);
                            resetTimer();
                            fetchDrawInfo();
                        });
                })
                .catch(error => {
                    console.error('Error checking bets:', error);
                    resetTimer();
                    fetchDrawInfo();
                });
        }
        
        // Immediately check forced number after a short delay (to allow server to process)
        function checkForcedNumberDelayed(delay = 1000) {
            setTimeout(() => {
                checkForcedNumber();
            }, delay);
        }
        
        // Initialize Firestore real-time listeners for draw numbers and game state
        function initFirestoreRealTimeListeners() {
            console.log('🔥 Initializing Firestore real-time listeners...');
            
            // Wait for FirestoreService to be available
            function startListeners() {
                if (!window.FirestoreService || !window.FirestoreService.isAvailable()) {
                    console.warn('⚠️ FirestoreService not available yet, retrying...');
                    setTimeout(startListeners, 500);
                    return;
                }
                
                console.log('✅ FirestoreService available, setting up listeners...');
                
                // Listen to game state changes (includes draw numbers)
                const gameStateUnsubscribe = window.FirestoreService.listenToGameState((gameState) => {
                    console.log('🔥 Game state updated from Firestore:', gameState);
                    
                    // Update draw number if available
                    if (gameState.currentDrawNumber !== undefined) {
                        const newDrawNumber = parseInt(gameState.currentDrawNumber);
                        if (newDrawNumber !== currentDrawNumber && newDrawNumber > 0) {
                            console.log(`🔥 Draw number updated via Firestore: ${currentDrawNumber} → ${newDrawNumber}`);
                            currentDrawNumber = newDrawNumber;
                            
                            // Update UI
                            document.getElementById('currentDrawNumber').textContent = newDrawNumber;
                            if (document.getElementById('currentDrawNumber-mobile')) {
                                document.getElementById('currentDrawNumber-mobile').textContent = newDrawNumber;
                            }
                            
                            // Update upcoming draw number
                            upcomingDrawNumber = newDrawNumber;
                            if (document.getElementById('upcomingDrawNumber')) {
                                document.getElementById('upcomingDrawNumber').textContent = newDrawNumber;
                            }
                            
                            // Refresh bet distribution for new draw
                            fetchBetDistribution();
                            
                            // Show notification
                            showToast('Draw Updated', `Draw number updated to #${newDrawNumber}`, 'info');
                        }
                    }
                    
                    // Update winning number if available
                    if (gameState.winningNumber !== undefined && gameState.winningNumber !== null) {
                        const newWinningNumber = parseInt(gameState.winningNumber);
                        if (newWinningNumber !== currentWinningNumber) {
                            currentWinningNumber = newWinningNumber;
                            const winningColor = gameState.winningColor || getNumberColor(newWinningNumber);
                            const numberClass = 'number-circle ' + winningColor;
                            
                            document.getElementById('winningNumberDisplay').textContent = newWinningNumber;
                            document.getElementById('winningNumberDisplay').className = numberClass;
                            
                            if (document.getElementById('winningNumberSource')) {
                                document.getElementById('winningNumberSource').textContent = `Source: ${gameState.source || 'automatic'}`;
                            }
                            if (document.getElementById('winningNumberReason')) {
                                document.getElementById('winningNumberReason').textContent = `Reason: ${gameState.reason || 'Auto-selected'}`;
                            }
                        }
                    }
                });
                
                // Monitor connection status
                window.FirestoreService.onConnectionStatusChange((online) => {
                    console.log('🔥 Firestore connection status:', online ? 'ONLINE' : 'OFFLINE');
                    
                    if (online) {
                        // Sync draw number to Firestore when connection is restored
                        syncDrawNumberToFirestore();
                    }
                });
                
                // Initial sync of draw number to Firestore
                syncDrawNumberToFirestore();
                
                // Periodically sync draw number to Firestore (every 30 seconds)
                setInterval(() => {
                    syncDrawNumberToFirestore();
                }, 30000);
                
                console.log('✅ Firestore real-time listeners initialized');
            }
            
            // Start listeners
            startListeners();
        }
        
        // Sync current draw number to Firestore
        function syncDrawNumberToFirestore() {
            fetch('../api/sync_draw_number_to_firestore.php?_cb=' + Date.now())
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success' && window.FirestoreService && window.FirestoreService.isAvailable()) {
                        // Write to Firestore gameState
                        window.FirestoreService.writeGameState({
                            currentDrawNumber: data.data.currentDrawNumber,
                            nextDrawNumber: data.data.nextDrawNumber,
                            expectedDrawNumber: data.data.expectedDrawNumber,
                            lastUpdated: data.data.lastUpdated,
                            lastResetDate: data.data.lastResetDate,
                            needsReset: data.data.needsReset,
                            timezone: data.data.timezone
                        }).then(() => {
                            console.log('✅ Draw number synced to Firestore:', data.data.currentDrawNumber);
                        }).catch(error => {
                            console.warn('⚠️ Failed to sync draw number to Firestore:', error);
                        });
                    }
                })
                .catch(error => {
                    console.warn('⚠️ Error syncing draw number:', error);
                });
        }
        
        // Correct draw number if it's out of sync
        function correctDrawNumber(expectedDrawNumber) {
            console.log(`🔄 Correcting draw number to ${expectedDrawNumber}...`);
            
            fetch('../api/sync_draw_number_to_time.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'force=true'
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    console.log('✅ Draw number corrected:', data.data.new_draw_number);
                    showToast('Draw Number Corrected', `Draw number updated to #${data.data.new_draw_number}`, 'success');
                    
                    // Refresh draw info
                    fetchDrawInfo();
                    
                    // Sync to Firestore
                    syncDrawNumberToFirestore();
                } else {
                    console.error('❌ Failed to correct draw number:', data.message);
                    showToast('Error', 'Failed to correct draw number: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('❌ Error correcting draw number:', error);
                showToast('Error', 'Failed to correct draw number', 'error');
            });
        }

        function submitWinningNumber(number, keepAutoMode = false) {
            // Show loading
            showToast('Processing', `Setting winning number to ${number}...`, 'info');
            
            // ⚠️ CRITICAL: Always send 'false' explicitly for manual mode
            // This ensures PHP receives the string 'false' and treats it as manual
            const keepAutoModeStr = keepAutoMode === true ? 'true' : 'false';
            const payload = `winning_number=${number}&keep_auto_mode=${keepAutoModeStr}`;
            console.log('📤 Sending payload:', payload, 'keepAutoMode:', keepAutoMode, 'keepAutoModeStr:', keepAutoModeStr);
            console.log('🔒 isManualSelectionInProgress:', isManualSelectionInProgress);
            
            fetch('../api/set_winning_number.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: payload
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    console.log('✅ API Response:', data);
                    console.log('✅ Source from API:', data.data?.source);
                    
                    // Verify the source is correct
                    if (data.data?.source !== 'manual' && !keepAutoMode) {
                        console.error('❌ WARNING: API returned source="' + data.data?.source + '" but we sent keep_auto_mode=false');
                    }
                    
                    showToast('Success', `Winning number set to ${number}`, 'success');
                    
                    if (!keepAutoMode) {
                        isAutoMode = false;
                        document.getElementById('manualWinningNumber').value = '';
                        
                        // Update UI to reflect manual mode
                        document.getElementById('currentMode').textContent = 'Manual';
                        document.getElementById('modeToggleText').textContent = 'Switch to Auto';
                    }
                    
                    // Sync to Firestore for real-time TV display sync
                    if (window.FirestoreService && window.FirestoreService.isAvailable()) {
                        try {
                            const drawNumber = data.data?.draw_number || currentDrawNumber;
                            const winningColor = data.data?.winning_color || (number === 0 ? 'green' : 
                                ([1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36].includes(number) ? 'red' : 'black'));
                            
                            // Write winning number to Firestore
                            FirestoreService.writeWinningNumber(drawNumber, number, winningColor, keepAutoMode ? 'auto' : 'manual')
                                .then(() => {
                                    console.log('✅ Winning number synced to Firestore');
                                    
                                    // Create spin command for synchronized execution
                                    const syncTimestamp = new Date(Date.now() + 2000); // 2 seconds from now
                                    return FirestoreService.writeSpinCommand(number, drawNumber, syncTimestamp, 'admin');
                                })
                                .then((commandId) => {
                                    console.log('✅ Spin command created in Firestore:', commandId);
                                })
                                .catch((error) => {
                                    console.warn('⚠️ Firestore sync failed (non-critical):', error);
                                });
                        } catch (error) {
                            console.warn('⚠️ Firestore sync error (non-critical):', error);
                        }
                    } else {
                        console.log('ℹ️ Firestore not available, skipping sync');
                    }

                    // Reset timer and refresh data
                    resetTimer();
                    fetchDrawInfo();
                    
                    // ⚠️ CRITICAL: Check forced number for the draw that was just set
                    // The API returns the draw_number that was used, so check that specific draw
                    const setDrawNumber = data.data?.draw_number || (currentDrawNumber + 1); // Default to next draw if not specified
                    console.log('🔄 Refreshing forced number checker for draw #' + setDrawNumber + ' (just set number ' + number + ')');
                    console.log('✅ API confirmed source: ' + (data.data?.source || 'unknown'));
                    
                    // Clear the manual selection flag first so checkForcedNumber can run
                    isManualSelectionInProgress = false;
                    console.log('🔓 Cleared isManualSelectionInProgress to allow forced number check');
                    
                    // Immediately check forced number to update display
                    // Use a delay to ensure database has been updated
                    setTimeout(() => {
                        // Force check by calling checkForcedNumber which checks next draw
                        checkForcedNumber();
                    }, 500); // 500ms delay to ensure database update

                    console.log('Set winning number success:', data);
                } else {
                    showError(data.message || 'Failed to set winning number');
                    console.error('Set winning number failed:', data);
                }
            })
            .catch(error => {
                console.error('Error setting winning number:', error);
                showError(`Failed to set winning number: ${error.message}`);

                // Try to refresh the data anyway
                fetchDrawInfo();
            });
        }

        // Function to show toast notifications
        function showToast(title, message, type) {
            console.log(`${type.toUpperCase()}: ${title} - ${message}`);

            // Simple implementation - could be replaced with a prettier UI
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
                <div class="toast-header">${title}</div>
                <div class="toast-body">${message}</div>
            `;

            // Add some basic styling
            toast.style.position = 'fixed';
            toast.style.top = '20px';
            toast.style.right = '20px';
            toast.style.backgroundColor = type === 'error' ? '#e74a3b' : (type === 'success' ? '#1cc88a' : '#4e73df');
            toast.style.color = 'white';
            toast.style.padding = '15px';
            toast.style.borderRadius = '5px';
            toast.style.boxShadow = '0 2px 10px rgba(0,0,0,0.3)';
            toast.style.zIndex = '1000';

            // Append to body
            document.body.appendChild(toast);

            // Remove after 3 seconds
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transition = 'opacity 0.5s ease';
                setTimeout(() => document.body.removeChild(toast), 500);
            }, 3000);
        }
    </script>
</body>
</html>
