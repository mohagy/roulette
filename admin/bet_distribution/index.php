<?php
/**
 * Bet Distribution & Draw Control
 * Main entry point for bet distribution management
 */

require_once 'includes/auth-check.php';
require_once 'config.php';

// Get current page for sidebar
$current_page = 'bet_distribution/index.php';

include 'includes/header.php';
?>

<!-- Action Buttons -->
<div class="action-buttons">
    <button id="refreshButton" class="btn btn-primary">
        <i class="fas fa-sync-alt"></i> Refresh Data
    </button>
    <button id="toggleDrawControlButton" class="btn btn-info">
        <i class="fas fa-sliders-h"></i> <span id="toggleDrawControlText">Show Draw Control</span>
    </button>
</div>

<!-- View Tabs -->
<div class="view-tabs">
    <button class="view-tab active" data-view="chart">
        <i class="fas fa-chart-bar"></i> Chart View
    </button>
    <button class="view-tab" data-view="grid">
        <i class="fas fa-th"></i> Grid View
    </button>
</div>

<div class="row">
    <!-- LEFT COLUMN: Main Content -->
    <div class="col-lg-8">
        <!-- Bet Distribution Container -->
        <div class="bet-distribution-container">
            <div class="auto-refresh-status">
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

            <!-- Draw Info Header -->
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

            <!-- Chart View -->
            <div class="view-container active" id="chartView">
                <div id="chartContainer" class="chart-container">
                    <div class="loading-indicator">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p>Loading bet distribution data...</p>
                    </div>
                </div>
            </div>

            <!-- Grid View -->
            <div class="view-container" id="gridView">
                <div id="betInfoGrid" class="bet-info-grid">
                    <div class="loading-indicator">
                        <div class="spinner-border text-primary" role="status"></div>
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
                    <div class="spinner-border text-primary" role="status"></div>
                    <p>Loading bet type distribution...</p>
                </div>
            </div>
        </div>

        <!-- Number Analytics -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Number Analytics</h6>
            </div>
            <div class="card-body">
                <div id="numberAnalyticsContent">
                    <div class="text-center py-3">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <span class="ms-2">Loading analytics...</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Forced Number Checker -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Forced Number Checker</h6>
            </div>
            <div class="card-body">
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="autoApplyForcedNumber">
                    <label class="form-check-label" for="autoApplyForcedNumber">
                        Auto-Apply from Preset Schedule
                    </label>
                </div>
                <button id="checkForcedNumberBtn" class="btn btn-info btn-sm mb-3">
                    <i class="fas fa-search"></i> Check Forced Number
                </button>
                <div id="forcedNumberInfo">
                    <p class="text-muted">No forced number set</p>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT COLUMN: Sidebar -->
    <div class="col-lg-4">
        <!-- Upcoming Draws Overview -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">10 Upcoming Draws Overview</h6>
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
                                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                    <span class="ms-2">Loading upcoming draws...</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Draw Control Section (Collapsible) -->
        <div id="drawControlSection" class="draw-control-section" style="display: none;">
            <div class="draw-control-header">
                <h5>Draw Control</h5>
            </div>

            <!-- Mode Toggle -->
            <div class="mode-toggle mb-3">
                <div>
                    <label class="form-label">Current Mode:</label>
                    <div class="current-mode" id="currentMode">Automatic</div>
                </div>
                <button id="toggleAutoMode" class="btn btn-primary">
                    Switch to Manual
                </button>
            </div>

            <!-- Current Draw Info -->
            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="card-title">Current Draw Information</h6>
                    <div class="draw-info-item">
                        <span class="label">Draw Number:</span>
                        <span class="value" id="currentDrawNumber">-</span>
                    </div>
                    <div class="draw-info-item">
                        <span class="label">Last Draw Time:</span>
                        <span class="value" id="lastDrawTime">-</span>
                    </div>
                    <div class="draw-info-item">
                        <span class="label">Next Draw Time:</span>
                        <span class="value" id="nextDrawTime">-</span>
                    </div>
                </div>
            </div>

            <!-- Winning Number Input -->
            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="card-title">Set Winning Number</h6>
                    <div class="input-group mb-2">
                        <input type="number" id="winningNumberInput" class="form-control" min="0" max="36" placeholder="0-36">
                        <button id="setWinningNumberBtn" class="btn btn-success">
                            <i class="fas fa-check"></i> Set
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Preset Schedule Section -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Preset Schedule</h6>
            </div>
            <div class="card-body">
                <div id="presetScheduleStatus" class="mb-2 text-muted">Ready</div>
                <button id="generatePresetScheduleBtn" class="btn btn-primary btn-sm mb-3">
                    <i class="fas fa-magic"></i> Generate Smart Schedule
                </button>
                <div class="preset-schedule-table" style="max-height: 300px; overflow-y: auto;">
                    <table class="table table-sm table-bordered" id="presetScheduleTable">
                        <thead class="table-light">
                            <tr>
                                <th>Draw #</th>
                                <th>Time</th>
                                <th>Number</th>
                                <th>Pattern/Puzzle</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="4" class="text-center text-muted">No schedule generated</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Slip Analytics -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Betting Slips Analysis</h6>
            </div>
            <div class="card-body">
                <!-- Test Number Selector -->
                <div class="mb-3">
                    <label class="form-label small">Test Winning Number:</label>
                    <div class="input-group input-group-sm">
                        <input type="number" id="testWinningNumber" class="form-control" min="0" max="36" placeholder="0-36">
                        <button id="testNumberBtn" class="btn btn-primary btn-sm">
                            <i class="fas fa-search"></i> Test
                        </button>
                        <button id="clearTestBtn" class="btn btn-secondary btn-sm" style="display: none;">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                    <small class="text-muted">Select a number to see which slips would win</small>
                </div>
                
                <!-- Summary -->
                <div id="slipAnalyticsSummary" class="mb-3" style="display: none;">
                    <div class="row g-2 text-center">
                        <div class="col-4">
                            <div class="small text-muted">Total Slips</div>
                            <div class="fw-bold" id="totalSlipsCount">0</div>
                        </div>
                        <div class="col-4">
                            <div class="small text-muted">Would Win</div>
                            <div class="fw-bold text-success" id="winningSlipsCount">0</div>
                        </div>
                        <div class="col-4">
                            <div class="small text-muted">Total Payout</div>
                            <div class="fw-bold" id="totalPayoutAmount">$0.00</div>
                        </div>
                    </div>
                </div>
                
                <!-- Slips List -->
                <div id="slipAnalyticsContent">
                    <div class="text-center py-3">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <span class="ms-2">Loading slips...</span>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>

