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

            <!-- Bet Distribution Container -->
            <div class="bet-distribution-container">
                <div class="auto-refresh-status mb-3">
                    <i class="fas fa-circle-notch fa-spin"></i>
                    <span>Auto-refreshing data for 10 upcoming draws every 15 seconds</span>
                </div>

                <!-- Upcoming Draws Overview Panel -->
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
                    <h2>Draw: <span id="upcomingDrawNumber">Loading...</span> <span id="drawStatus" class="badge bg-primary">Current</span></h2>
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

            <!-- Draw Control Section -->
            <div id="drawControlSection" class="draw-control-section" style="display: none;">
                <!-- Mobile-friendly collapsible sections -->
                <div class="d-block d-md-none">
                    <!-- Current Draw Information (Mobile) -->
                    <div class="card shadow mb-3">
                        <div class="mobile-collapsible-header" data-target="currentDrawContent">
                            <h6 class="m-0 font-weight-bold">Current Draw Information</h6>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="mobile-collapsible-content" id="currentDrawContent">
                            <div class="card-body">
                                <div class="h3 mb-0 font-weight-bold text-gray-800" id="currentDrawNumber-mobile">-</div>
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Current Draw Number</div>

                                <table class="table table-sm mt-3">
                                    <tr>
                                        <td>Last Draw:</td>
                                        <td id="lastDrawTime-mobile">-</td>
                                    </tr>
                                    <tr>
                                        <td>Next Draw:</td>
                                        <td id="nextDrawTime-mobile">-</td>
                                    </tr>
                                    <tr>
                                        <td>Mode:</td>
                                        <td id="currentMode-mobile">-</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Timer (Mobile) -->
                    <div class="card shadow mb-3">
                        <div class="mobile-collapsible-header active" data-target="timerContent">
                            <h6 class="m-0 font-weight-bold">Draw Timer</h6>
                            <i class="fas fa-chevron-up"></i>
                        </div>
                        <div class="mobile-collapsible-content expanded" id="timerContent">
                            <div class="card-body p-0">
                                <!-- 3D Timer Container -->
                                <div class="timer-3d-container">
                                    <div id="timer3dScene-mobile" class="timer-3d-scene"></div>
                                    <div id="timer3dDisplay-mobile" class="timer-3d-display">00:00</div>
                                    <div id="timerSyncIndicator-mobile" class="timer-sync-indicator" style="display: none;">
                                        <i class="fas fa-sync-alt fa-spin"></i>
                                        <span class="sync-text">Synced</span>
                                    </div>
                                    <div class="next-draw-time" style="position: absolute; bottom: 50px; left: 0; right: 0; text-align: center; color: white; font-size: 0.9rem; text-shadow: 0 0 5px rgba(0,0,0,0.5);">
                                        Next: <span id="nextDrawTimeDisplay-mobile">--:--:--</span>
                                    </div>
                                    <div id="particles-mobile" class="particles"></div>
                                    <div class="timer-3d-controls">
                                        <button id="startTimer3d-mobile" class="timer-3d-btn start">
                                            <i class="fas fa-play"></i>
                                        </button>
                                        <button id="pauseTimer3d-mobile" class="timer-3d-btn pause">
                                            <i class="fas fa-pause"></i>
                                        </button>
                                        <button id="resetTimer3d-mobile" class="timer-3d-btn reset">
                                            <i class="fas fa-redo"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="timer-3d-settings">
                                    <div class="row g-2">
                                        <div class="col-8">
                                            <input type="number" id="timerInterval3d-mobile" class="form-control" value="60" min="10" max="300">
                                        </div>
                                        <div class="col-4">
                                            <button id="updateTimerSettings3d-mobile" class="btn btn-primary w-100">
                                                <i class="fas fa-save"></i> Save
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Winning Number (Mobile) -->
                    <div class="card shadow mb-3">
                        <div class="mobile-collapsible-header" data-target="winningNumberContent">
                            <h6 class="m-0 font-weight-bold">Winning Number</h6>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="mobile-collapsible-content" id="winningNumberContent">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="number-circle" id="winningNumberDisplay-mobile">-</div>
                                    <button class="btn btn-primary btn-sm" id="toggleAutoMode-mobile">
                                        <i class="fas fa-robot"></i> <span id="modeToggleText-mobile">Auto</span>
                                    </button>
                                </div>
                                <div id="winningNumberSource-mobile" class="small">Source: -</div>
                                <div id="winningNumberReason-mobile" class="small mb-3">Reason: -</div>

                                <div class="input-group mb-2">
                                    <input type="number" id="manualWinningNumber-mobile" class="form-control" value="0" min="0" max="36">
                                    <button class="btn btn-primary" id="setManualWinningNumber-mobile">
                                        <i class="fas fa-hand-pointer"></i> Set
                                    </button>
                                </div>

                                <!-- Recommended Numbers Section (Mobile) -->
                                <div class="recommended-numbers-container mt-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="recommended-numbers-title small">Recommended Numbers</div>
                                        <button class="btn btn-sm btn-outline-primary" id="refreshRecommendations-mobile">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                    </div>

                                    <div class="recommended-numbers-tabs">
                                        <div class="recommended-tab active" data-type="no-bets" data-mobile="true">No Bets</div>
                                        <div class="recommended-tab" data-type="lowest-payout" data-mobile="true">Lowest</div>
                                        <div class="recommended-tab" data-type="highest-payout" data-mobile="true">Highest</div>
                                    </div>

                                    <div id="recommendedNumbersGrid-mobile" class="recommended-numbers-grid">
                                        <div class="no-recommendations">
                                            <i class="fas fa-info-circle"></i> Loading...
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Forced Number Checker (Mobile) -->
                    <div class="card shadow mb-3">
                        <div class="mobile-collapsible-header" data-target="forcedNumberContent">
                            <h6 class="m-0 font-weight-bold">Forced Number Checker</h6>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="mobile-collapsible-content" id="forcedNumberContent">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="forced-number-status small" id="forcedNumberStatus-mobile">Check for forced numbers</div>
                                    <button class="btn btn-primary btn-sm" id="checkForcedNumber-mobile">
                                        <i class="fas fa-sync-alt"></i> Check
                                    </button>
                                </div>

                                <div class="forced-number-display text-center">
                                    <div class="forced-number-badge-container">
                                        <div class="forced-number-badge" id="forcedNumberBadge-mobile">?</div>
                                        <div class="forced-number-glow"></div>
                                    </div>
                                    <div class="forced-number-info mt-2">
                                        <div class="forced-number-draw">Draw: <span id="forcedNumberDraw-mobile">-</span></div>
                                    </div>
                                </div>

                                <div class="forced-number-details mt-3">
                                    <div class="forced-number-detail-item">
                                        <i class="fas fa-info-circle"></i>
                                        <span id="forcedNumberMessage-mobile" class="small">No information available</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Roll History (Mobile) -->
                    <div class="card shadow mb-3">
                        <div class="mobile-collapsible-header" data-target="rollHistoryContent">
                            <h6 class="m-0 font-weight-bold">Recent Roll History</h6>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="mobile-collapsible-content" id="rollHistoryContent">
                            <div class="card-body">
                                <div class="roll-history" id="rollHistory-mobile">
                                    <!-- Roll history items will be added here -->
                                </div>
                                <div class="auto-refresh-status mt-3 small">
                                    <span>Auto-refreshing every 15s</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Desktop layout -->
                <div class="d-none d-md-block">
                    <div class="row">
                        <div class="col-md-4">
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

                            <!-- Forced Number Checker Card -->
                            <div class="card shadow mb-4">
                                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-primary">Forced Number Checker</h6>
                                    <button class="btn btn-primary btn-sm" id="checkForcedNumber">
                                        <i class="fas fa-sync-alt"></i> Check Now
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div class="forced-number-container">
                                        <div class="forced-number-status mb-2" id="forcedNumberStatus">Click to check for forced numbers</div>

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
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
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
                                        <div class="next-draw-time" style="position: absolute; bottom: 50px; left: 0; right: 0; text-align: center; color: white; font-size: 0.9rem; text-shadow: 0 0 5px rgba(0,0,0,0.5);">
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
                        </div>

                        <div class="col-md-4">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-primary">Winning Number</h6>
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
                                        <input type="number" id="manualWinningNumber" class="form-control" value="0" min="0" max="36">
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
                                </div>
                            </div>
                        </div>
                    </div>

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

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.35.0/dist/apexcharts.min.js"></script>

    <script>
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
        const forcedNumberCheckInterval = 30000; // Check forced number every 30 seconds

        // 3D Timer variables
        let scene, camera, renderer;
        let particles = [];
        let clock = new THREE.Clock();
        let particleSystem;
        let animationFrameId;
        let timerMesh;
        let isTimerInitialized = false;
        let isTimerSynced = false;

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Bet distribution and draw control page loaded');

            // Cache DOM elements
            loadingOverlay = document.getElementById('loadingOverlay');

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
            fetchBetDistribution();

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

            // Forced Number Checker controls
            document.getElementById('checkForcedNumber').addEventListener('click', checkForcedNumber);

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
        function checkForcedNumber() {
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

            // Add timestamp to prevent caching
            fetch('../api/direct_forced_number.php?t=' + Date.now())
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

                    // Update forced number display
                    if (data.has_forced_number) {
                        // Set number and color
                        forcedNumberBadge.textContent = data.forced_number;
                        forcedNumberBadge.className = 'forced-number-badge ' + data.forced_color + ' has-forced found';

                        // Set draw number
                        forcedNumberDraw.textContent = '#' + data.draw_number;

                        // Update message with more details
                        forcedNumberMessage.innerHTML = `<strong>Forced number ${data.forced_number} (${data.forced_color})</strong> is set for draw #${data.draw_number}`;

                        // If in manual mode, offer to use this number
                        if (!isAutoMode) {
                            // Set the manual winning number input to this value
                            document.getElementById('manualWinningNumber').value = data.forced_number;

                            // Show toast notification
                            showToast('Forced Number Found', `Number ${data.forced_number} is forced for draw #${data.draw_number}. You can set it as the winning number.`, 'info');
                        }
                    } else {
                        // No forced number
                        forcedNumberBadge.textContent = '?';
                        forcedNumberBadge.className = 'forced-number-badge';
                        forcedNumberDraw.textContent = data.draw_number ? '#' + data.draw_number : '-';
                        forcedNumberMessage.textContent = 'No forced number is currently set';
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

            // Set up new interval
            forcedNumberCheckIntervalId = setInterval(checkForcedNumber, forcedNumberCheckInterval);
            console.log(`Started forced number checking every ${forcedNumberCheckInterval/1000} seconds`);

            // Update status to show auto-checking
            const forcedNumberStatus = document.getElementById('forcedNumberStatus');
            if (forcedNumberStatus) {
                forcedNumberStatus.innerHTML = 'Auto-checking every 30 seconds <i class="fas fa-sync-alt fa-spin fa-sm"></i>';
            }
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

        // Function to fetch bet distribution data for multiple draws
        async function fetchBetDistribution() {
            console.log('Starting fetchBetDistribution...');
            showLoading(true);

            try {
                // First, get the upcoming draws data from the existing API
                console.log('Fetching upcoming draws data...');
                const upcomingResponse = await fetch('../api/upcoming_draws_stats.php?count=10');

                if (!upcomingResponse.ok) {
                    throw new Error(`HTTP error! status: ${upcomingResponse.status}`);
                }

                const upcomingData = await upcomingResponse.json();
                console.log('Fetched upcoming draws data:', upcomingData);

                if (upcomingData.status === 'success') {
                    const upcomingDraws = upcomingData.data.upcoming_draws;
                    currentDrawNumber = upcomingData.data.base_draw;

                    // Fetch bet distribution for each draw
                    const drawPromises = upcomingDraws.map(async (draw) => {
                        try {
                            const betResponse = await fetch(`../php/get_bet_distribution.php?draw=${draw.draw_number}`);
                            const betData = await betResponse.json();

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
                    allDrawsData = await Promise.all(drawPromises);

                    // Update the overview table
                    updateUpcomingDrawsOverview(allDrawsData);

                    // Update the draw tabs
                    updateDrawTabs(allDrawsData);

                    // Select the first draw by default
                    if (allDrawsData.length > 0) {
                        selectDraw(0);
                    }

                    updateLastUpdated();
                } else {
                    console.error('Failed to fetch upcoming draws data:', upcomingData);
                    // Fallback to single draw mode
                    await fetchSingleDrawFallback();
                }
            } catch (error) {
                console.error('Error fetching bet distribution:', error);
                // Fallback to single draw mode
                await fetchSingleDrawFallback();
            } finally {
                showLoading(false);
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
                showError('Failed to fetch bet distribution data. Please try again later.');
            }
        }

        // Function to update the upcoming draws overview table
        function updateUpcomingDrawsOverview(draws) {
            const tableBody = document.querySelector('#upcomingDrawsTable tbody');
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
            // Update upcoming draw number and status
            document.getElementById('upcomingDrawNumber').textContent = `#${data.draw_number}`;

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
                item.addEventListener('click', function() {
                    const number = parseInt(this.getAttribute('data-number'));
                    if (isMobile) {
                        // For mobile, set the mobile input value first
                        document.getElementById('manualWinningNumber-mobile').value = number;
                        document.getElementById('setManualWinningNumber-mobile').click();
                    } else {
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
            // Set the input value
            document.getElementById('manualWinningNumber').value = number;

            // Call the set winning number function
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
            loadingOverlay.style.display = show ? 'flex' : 'none';
        }

        // Function to show error message
        function showError(message) {
            const chartContainer = document.getElementById('chartContainer');
            const gridContainer = document.getElementById('betInfoGrid');

            // Show error in chart view
            chartContainer.innerHTML = `
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <p>${message}</p>
                    <button class="btn btn-primary btn-sm" onclick="fetchBetDistribution()">
                        <i class="fas fa-sync-alt"></i> Try Again
                    </button>
                </div>
            `;

            // Show error in grid view
            gridContainer.innerHTML = `
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <p>${message}</p>
                </div>
            `;
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
            currentDrawNumber = data.current_draw || '-';

            // Update desktop elements
            document.getElementById('currentDrawNumber').textContent = currentDrawNumber;

            // Update mobile elements if they exist
            if (document.getElementById('currentDrawNumber-mobile')) {
                document.getElementById('currentDrawNumber-mobile').textContent = currentDrawNumber;
            }

            // Update draw number displays
            const lastDraw = data.last_draw || '-';
            document.getElementById('lastDrawTime').textContent = lastDraw;
            if (document.getElementById('lastDrawTime-mobile')) {
                document.getElementById('lastDrawTime-mobile').textContent = lastDraw;
            }

            const nextDraw = data.next_draw || '-';
            document.getElementById('nextDrawTime').textContent = nextDraw;
            if (document.getElementById('nextDrawTime-mobile')) {
                document.getElementById('nextDrawTime-mobile').textContent = nextDraw;
            }

            // Update mode
            isAutoMode = data.is_automatic;
            const modeText = isAutoMode ? 'Automatic' : 'Manual';
            const toggleText = isAutoMode ? 'Switch to Manual' : 'Switch to Auto';

            // Update desktop elements
            document.getElementById('currentMode').textContent = modeText;
            document.getElementById('modeToggleText').textContent = toggleText;

            // Update mobile elements if they exist
            if (document.getElementById('currentMode-mobile')) {
                document.getElementById('currentMode-mobile').textContent = modeText;
            }
            if (document.getElementById('modeToggleText-mobile')) {
                document.getElementById('modeToggleText-mobile').textContent = isAutoMode ? 'Manual' : 'Auto';
            }

            // Update winning number
            if (data.winning_number !== null) {
                currentWinningNumber = data.winning_number;
                const numberClass = 'number-circle ' + data.winning_color;

                // Update desktop elements
                document.getElementById('winningNumberDisplay').textContent = data.winning_number;
                document.getElementById('winningNumberDisplay').className = numberClass;
                document.getElementById('winningNumberSource').textContent = `Source: ${data.winning_number_source}`;
                document.getElementById('winningNumberReason').textContent = `Reason: ${data.winning_number_reason}`;

                // Update mobile elements if they exist
                if (document.getElementById('winningNumberDisplay-mobile')) {
                    document.getElementById('winningNumberDisplay-mobile').textContent = data.winning_number;
                    document.getElementById('winningNumberDisplay-mobile').className = numberClass;
                }
                if (document.getElementById('winningNumberSource-mobile')) {
                    document.getElementById('winningNumberSource-mobile').textContent = `Source: ${data.winning_number_source}`;
                }
                if (document.getElementById('winningNumberReason-mobile')) {
                    document.getElementById('winningNumberReason-mobile').textContent = `Reason: ${data.winning_number_reason}`;
                }
            } else {
                // Update desktop elements
                document.getElementById('winningNumberDisplay').textContent = '-';
                document.getElementById('winningNumberDisplay').className = 'number-circle';
                document.getElementById('winningNumberSource').textContent = 'Source: -';
                document.getElementById('winningNumberReason').textContent = 'Reason: -';

                // Update mobile elements if they exist
                if (document.getElementById('winningNumberDisplay-mobile')) {
                    document.getElementById('winningNumberDisplay-mobile').textContent = '-';
                    document.getElementById('winningNumberDisplay-mobile').className = 'number-circle';
                }
                if (document.getElementById('winningNumberSource-mobile')) {
                    document.getElementById('winningNumberSource-mobile').textContent = 'Source: -';
                }
                if (document.getElementById('winningNumberReason-mobile')) {
                    document.getElementById('winningNumberReason-mobile').textContent = 'Reason: -';
                }
            }

            // Update manual winning number input
            if (data.manual_winning_number) {
                document.getElementById('manualWinningNumber').value = data.manual_winning_number;
                if (document.getElementById('manualWinningNumber-mobile')) {
                    document.getElementById('manualWinningNumber-mobile').value = data.manual_winning_number;
                }
            }

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
                        // Timer has reached zero, reset
                        resetTimer();
                        // Fetch new draw info
                        fetchDrawInfo();
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

        function setManualWinningNumber() {
            const number = parseInt(document.getElementById('manualWinningNumber').value);

            if (isNaN(number) || number < 0 || number > 36) {
                showError('Please enter a valid number (0-36)');
                return;
            }

            // Show a loading message
            showToast('Processing', `Setting winning number to ${number}...`, 'info');

            // If we're in auto mode, switch to manual mode first
            const switchToManualFirst = isAutoMode;

            const setNumber = () => {
                // The parameter name in the API is 'winning_number', not 'number'
                fetch('../api/set_winning_number.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `winning_number=${number}`
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Network response was not ok: ${response.status} ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success') {
                        showToast('Success', data.message || `Successfully set winning number to ${number}`, 'success');
                        document.getElementById('manualWinningNumber').value = '';

                        // Make sure we're in manual mode
                        isAutoMode = false;

                        // Refresh the data to show the changes
                        fetchDrawInfo();

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
            };

            // If we need to switch to manual mode first
            if (switchToManualFirst) {
                fetch('../api/toggle_mode.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'mode=manual'
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success') {
                        showToast('Mode Changed', 'Switched to manual mode', 'success');
                        isAutoMode = false;

                        // Now set the winning number
                        setNumber();
                    } else {
                        showError(data.message || 'Failed to switch to manual mode');
                    }
                })
                .catch(error => {
                    console.error('Error toggling mode:', error);
                    showError('Failed to switch to manual mode. Please try again.');
                });
            } else {
                // Already in manual mode, just set the number
                setNumber();
            }
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
