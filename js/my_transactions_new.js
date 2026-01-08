/**
 * my_transactions_new.js
 * JavaScript for the new My Transactions page with real-time updates
 */

// Global variables
let lastUpdated = 0;
let updateInterval;
let countdownIntervals = [];
let toastInstance;
let chartInstances = {};

// Initialize when document is ready
$(document).ready(function() {
    // Initialize toast
    toastInstance = new bootstrap.Toast(document.getElementById('update-toast'));

    // Set up real-time updates
    setupRealTimeUpdates();

    // Initialize countdown timers
    initializeCountdowns();

    // Set up event listeners
    setupEventListeners();

    // Initialize charts
    initializeCharts();

    // POS System: Loading overlay removed for instant access
});

/**
 * Set up real-time updates using AJAX
 */
function setupRealTimeUpdates() {
    // Start with immediate update
    updateData();

    // Set interval for regular updates (every 5 seconds)
    updateInterval = setInterval(updateData, 5000);

    // Update the "last updated" text every minute
    setInterval(updateLastUpdatedText, 60000);
}

/**
 * Update data via AJAX using the new API with corrected total wins calculation and real-time cashout updates
 */
function updateData() {
    // Use full update API for comprehensive real-time updates
    $.ajax({
        url: 'php/my_transactions_api.php?action=full_update',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                const data = response.data;

                // Update summary with corrected total wins
                if (data.summary) {
                    updateSummary(data.summary);
                }

                // Update balance with real-time changes
                if (data.balance) {
                    updateUserBalanceWithFeedback(data.balance);
                }

                // Update recent transactions
                if (data.transactions) {
                    updateRecentTransactions(data.transactions);
                }

                // POS System: Cashout notifications disabled for cashier interface

                // Update last updated timestamp
                lastUpdated = Math.floor(Date.now() / 1000);
                updateLastUpdatedText();

                console.log('✅ Full Update Complete:', data);
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Error fetching full update:', error);

            // Fallback to individual API calls if full update fails
            updateDataFallback();
        }
    });
}

/**
 * Fallback update method using individual API calls
 */
function updateDataFallback() {
    // Update summary statistics
    $.ajax({
        url: 'php/my_transactions_api.php?action=summary',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                updateSummary(response.data);
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Error fetching summary:', error);
        }
    });

    // Update balance
    $.ajax({
        url: 'php/my_transactions_api.php?action=balance',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                updateUserBalanceWithFeedback(response.data);
            }
        },
        error: function(xhr, status, error) {
            console.error('❌ Error fetching balance:', error);
        }
    });

    // POS System: Cashout notification polling disabled for cashier interface
}

/**
 * Process data updates from the server
 */
function processDataUpdates(data) {
    let hasUpdates = false;

    // Update user info if available
    if (data.user) {
        updateUserInfo(data.user);
        hasUpdates = true;
    }

    // Update transactions if available
    if (data.transactions && data.transactions.length > 0) {
        updateTransactions(data.transactions);
        hasUpdates = true;
    }

    // Update betting slips if available
    if (data.betting_slips && data.betting_slips.length > 0) {
        updateBettingSlips(data.betting_slips);
        hasUpdates = true;
    }

    // Update summary if available
    if (data.summary) {
        updateSummary(data.summary);
        hasUpdates = true;
    }

    // Show notification if there were updates
    if (hasUpdates) {
        showUpdateNotification('Your data has been updated');
    }
}

/**
 * Update user information
 */
function updateUserInfo(user) {
    // Update balance
    $('#balance-amount').text('$' + formatNumber(user.cash_balance));

    // Add pulse animation to balance
    $('#balance-amount').addClass('pulse');
    setTimeout(function() {
        $('#balance-amount').removeClass('pulse');
    }, 1000);
}

/**
 * Update transactions table
 */
function updateTransactions(transactions) {
    // Sort transactions by ID (descending)
    transactions.sort((a, b) => b.transaction_id - a.transaction_id);

    // Get the transactions table body
    const tableBody = $('#transactions-table tbody');

    // Add new transactions to the table
    transactions.forEach(function(transaction) {
        // Check if this transaction already exists in the table
        const existingRow = tableBody.find(`tr[data-transaction-id="${transaction.transaction_id}"]`);

        if (existingRow.length === 0) {
            // Create a new row for this transaction
            const newRow = $('<tr>').attr('data-transaction-id', transaction.transaction_id);

            // Determine badge class based on transaction type
            let badgeClass = 'bg-warning';
            if (transaction.transaction_type === 'bet') badgeClass = 'bg-danger';
            else if (transaction.transaction_type === 'win') badgeClass = 'bg-success';
            else if (transaction.transaction_type === 'refund') badgeClass = 'bg-info';
            else if (transaction.transaction_type === 'voucher') badgeClass = 'bg-primary';

            // Add cells to the row
            newRow.append(`<td>${transaction.transaction_id}</td>`);
            newRow.append(`<td><span class="badge ${badgeClass}">${capitalizeFirstLetter(transaction.transaction_type)}</span></td>`);
            newRow.append(`<td class="${parseFloat(transaction.amount) >= 0 ? 'text-success' : 'text-danger'} fw-bold">$${formatNumber(transaction.amount)}</td>`);
            newRow.append(`<td>$${formatNumber(transaction.balance_after)}</td>`);
            newRow.append(`<td>${transaction.description}</td>`);
            newRow.append(`<td>${transaction.created_at}</td>`);

            // Add highlight class for animation
            newRow.addClass('highlight-update');

            // Add the row to the table (at the top)
            tableBody.prepend(newRow);

            // Remove highlight after animation completes
            setTimeout(function() {
                newRow.removeClass('highlight-update');
            }, 3000);
        }
    });
}

/**
 * Update betting slips table
 */
function updateBettingSlips(slips) {
    // Get the betting slips table body
    const tableBody = $('#betting-slips-table tbody');

    // Process each slip
    slips.forEach(function(slip) {
        // Check if this slip already exists in the table
        const existingRow = tableBody.find(`tr[data-slip-id="${slip.slip_id}"]`);

        if (existingRow.length > 0) {
            // Update existing row
            updateExistingSlipRow(existingRow, slip);
        } else {
            // Create a new row for this slip
            createNewSlipRow(tableBody, slip);
        }
    });

    // Reinitialize countdowns
    initializeCountdowns();
}

/**
 * Update an existing slip row
 */
function updateExistingSlipRow(row, slip) {
    // Check if status has changed
    const resultCell = row.find('.result-cell');
    const currentStatus = resultCell.find('.badge').text().toLowerCase();
    let newStatus = 'pending';

    if (slip.actual_winning_number !== null) {
        newStatus = slip.is_winner ? 'win' : 'loss';
    }

    // Only update if status has changed
    if (currentStatus !== newStatus) {
        // Update winning number cell
        const winningNumberCell = row.find('td:nth-child(7)');
        if (slip.actual_winning_number !== null) {
            const badgeClass = slip.winning_color === 'red' ? 'bg-danger' : (slip.winning_color === 'black' ? 'bg-dark' : 'bg-success');
            winningNumberCell.html(`<span class="badge ${badgeClass}">${slip.actual_winning_number}</span>`);
        }

        // Update result cell
        if (newStatus === 'pending') {
            resultCell.html('<span class="badge badge-pending">Pending</span>');
        } else if (newStatus === 'win') {
            resultCell.html('<span class="badge badge-win">WIN</span>');
        } else {
            resultCell.html('<span class="badge badge-loss">LOSS</span>');
        }

        // Update actual win cell
        const winCell = row.find('td:nth-child(9)');
        if (slip.is_winner) {
            winCell.html(`<span class="text-success fw-bold">$${formatNumber(slip.winning_amount)}</span>`);
        } else {
            winCell.html('<span class="text-muted">$0.00</span>');
        }

        // Add highlight class for animation
        row.addClass('highlight-update');

        // Remove highlight after animation completes
        setTimeout(function() {
            row.removeClass('highlight-update');
        }, 3000);

        // POS System: Winner notifications completely disabled for cashier interface
    }

    // Update draw time/countdown if needed
    const drawTimeCell = row.find('td:nth-child(4)');
    if (slip.draw_time) {
        if (slip.time_remaining > 0) {
            drawTimeCell.html(`<span class="countdown" data-time="${slip.time_remaining}">${formatTime(slip.time_remaining)}</span>`);
        } else {
            drawTimeCell.text(formatDate(slip.draw_time));
        }
    }
}

/**
 * Create a new slip row
 */
function createNewSlipRow(tableBody, slip) {
    // Create main row
    const newRow = $('<tr>').attr({
        'data-slip-number': slip.slip_number,
        'data-slip-id': slip.slip_id
    });

    // Determine result badge
    let resultBadge = '<span class="badge badge-pending">Pending</span>';
    if (slip.actual_winning_number !== null) {
        resultBadge = slip.is_winner ?
            '<span class="badge badge-win">WIN</span>' :
            '<span class="badge badge-loss">LOSS</span>';
    }

    // Determine winning number badge
    let winningNumberBadge = '<span class="badge bg-secondary">Pending</span>';
    if (slip.actual_winning_number !== null) {
        const badgeClass = slip.winning_color === 'red' ? 'bg-danger' : (slip.winning_color === 'black' ? 'bg-dark' : 'bg-success');
        winningNumberBadge = `<span class="badge ${badgeClass}">${slip.actual_winning_number}</span>`;
    }

    // Determine draw time/countdown
    let drawTimeDisplay = '<span class="text-muted">Pending</span>';
    if (slip.draw_time) {
        if (slip.time_remaining > 0) {
            drawTimeDisplay = `<span class="countdown" data-time="${slip.time_remaining}">${formatTime(slip.time_remaining)}</span>`;
        } else {
            drawTimeDisplay = formatDate(slip.draw_time);
        }
    }

    // Add cells to the row
    newRow.append(`<td>${slip.slip_number}</td>`);
    newRow.append(`<td>${formatDate(slip.created_at)}</td>`);
    newRow.append(`<td>${slip.draw_number}</td>`);
    newRow.append(`<td>${drawTimeDisplay}</td>`);
    newRow.append(`<td>$${formatNumber(slip.total_stake)}</td>`);
    newRow.append(`<td>$${formatNumber(slip.potential_payout)}</td>`);
    newRow.append(`<td>${winningNumberBadge}</td>`);
    newRow.append(`<td class="result-cell">${resultBadge}</td>`);
    newRow.append(`<td>${slip.is_winner ? `<span class="text-success fw-bold">$${formatNumber(slip.winning_amount)}</span>` : '<span class="text-muted">$0.00</span>'}</td>`);
    newRow.append(`<td><button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#slip-${slip.slip_id}"><i class="fas fa-eye"></i> View</button></td>`);

    // Add highlight class for animation
    newRow.addClass('highlight-update');

    // Create details row
    const detailsRow = $('<tr>').addClass('collapse').attr('id', `slip-${slip.slip_id}`);
    const detailsCell = $('<td>').attr('colspan', 10);
    const detailsCard = $('<div>').addClass('card card-body bg-light m-2');

    // Add heading
    detailsCard.append(`<h6 class="mb-3">Bets for Slip #${slip.slip_number} (Draw #${slip.draw_number})</h6>`);

    // Create bets table
    const betsTable = $('<table>').addClass('table table-sm');
    betsTable.append(`
        <thead>
            <tr>
                <th>Type</th>
                <th>Description</th>
                <th>Amount</th>
                <th>Multiplier</th>
                <th>Potential Return</th>
                <th>Result</th>
            </tr>
        </thead>
    `);

    const betsTableBody = $('<tbody>');

    // Add rows for each bet
    slip.bets.forEach(function(bet) {
        const isWinningBet = bet.is_winner || false;
        let betResultBadge = '<span class="badge badge-pending">Pending</span>';

        if (slip.actual_winning_number !== null) {
            betResultBadge = isWinningBet ?
                '<span class="badge badge-win">WIN</span>' :
                '<span class="badge badge-loss">LOSS</span>';
        }

        betsTableBody.append(`
            <tr>
                <td>${capitalizeFirstLetter(bet.bet_type)}</td>
                <td>${bet.bet_description}</td>
                <td>$${formatNumber(bet.bet_amount)}</td>
                <td>${bet.multiplier}:1</td>
                <td>$${formatNumber(bet.potential_return)}</td>
                <td>${betResultBadge}</td>
            </tr>
        `);
    });

    betsTable.append(betsTableBody);
    detailsCard.append($('<div>').addClass('table-responsive').append(betsTable));
    detailsCell.append(detailsCard);
    detailsRow.append(detailsCell);

    // Add rows to table
    tableBody.prepend(newRow);
    tableBody.find(`tr[data-slip-id="${slip.slip_id}"]`).after(detailsRow);

    // Remove highlight after animation completes
    setTimeout(function() {
        newRow.removeClass('highlight-update');
    }, 3000);
}

/**
 * Update summary statistics with corrected total wins calculation
 */
function updateSummary(summary) {
    // Update total bets
    $('#total-bets').text('$' + formatNumber(summary.total_bets));

    // Update total wins (now correctly calculated from betting slips)
    $('#total-wins').text('$' + formatNumber(summary.total_wins));

    // Update net profit
    $('#net-profit').text('$' + formatNumber(summary.net_profit));

    // Update ROI
    $('#roi').text(formatNumber(summary.roi) + '%');

    // Add visual feedback for wins update
    if (summary.total_wins > 0) {
        $('#total-wins').addClass('pulse-success');
        setTimeout(function() {
            $('#total-wins').removeClass('pulse-success');
        }, 2000);
    }

    // Update charts if they exist
    updateCharts(summary);

    // Show notification if wins were updated
    if (summary.calculation_method === 'betting_slips') {
        console.log('🎯 Total Wins calculated from betting slips:', summary.total_wins);
    }
}

/**
 * Update user balance with enhanced feedback
 */
function updateUserBalanceWithFeedback(balanceData) {
    const newBalance = balanceData.balance || balanceData;
    const currentBalanceText = $('#balance-amount').text().replace(/[$,]/g, '');
    const currentBalance = parseFloat(currentBalanceText) || 0;

    // Update balance display
    $('#balance-amount').text('$' + formatNumber(newBalance));

    // POS System: Simple balance update without winner celebrations
    $('#balance-amount').addClass('pulse');
    setTimeout(function() {
        $('#balance-amount').removeClass('pulse');
    }, 1000);
}

/**
 * Legacy function for backward compatibility
 */
function updateUserBalance(balance) {
    updateUserBalanceWithFeedback({ balance: balance });
}

/**
 * Update recent transactions table
 */
function updateRecentTransactions(transactions) {
    const tableBody = $('#transactions-table tbody');

    // Clear existing rows
    tableBody.empty();

    // Add new transactions
    transactions.forEach(function(transaction) {
        const newRow = $('<tr>').attr('data-transaction-id', transaction.transaction_id);

        // Determine badge class based on transaction type
        let badgeClass = 'bg-warning';
        if (transaction.transaction_type === 'bet') badgeClass = 'bg-danger';
        else if (transaction.transaction_type === 'win') badgeClass = 'bg-success';
        else if (transaction.transaction_type === 'refund') badgeClass = 'bg-info';
        else if (transaction.transaction_type === 'voucher') badgeClass = 'bg-primary';

        // Add cells to the row
        newRow.append(`<td>${transaction.transaction_id}</td>`);
        newRow.append(`<td><span class="badge ${badgeClass}">${capitalizeFirstLetter(transaction.transaction_type)}</span></td>`);
        newRow.append(`<td class="${parseFloat(transaction.amount) >= 0 ? 'text-success' : 'text-danger'} fw-bold">$${formatNumber(transaction.amount)}</td>`);
        newRow.append(`<td>$${formatNumber(transaction.balance_after)}</td>`);
        newRow.append(`<td>${transaction.description || ''}</td>`);
        newRow.append(`<td>${transaction.created_at}</td>`);

        // POS System: Win transaction highlighting removed for cashier interface

        tableBody.append(newRow);
    });
}

/**
 * POS System: All winner notification functions completely removed for cashier interface
 * No popups, overlays, celebrations, or winner announcements in Point of Sale environment
 */

/**
 * Update betting slips from API response
 */
function updateBettingSlipsFromAPI(slips) {
    // Process each slip for real-time updates
    slips.forEach(function(slip) {
        const existingRow = $(`tr[data-slip-id="${slip.slip_id}"]`);

        if (existingRow.length > 0) {
            // Update existing slip if status changed
            updateExistingSlipFromAPI(existingRow, slip);
        }
    });

    // Reinitialize countdowns
    initializeCountdowns();
}

/**
 * Capitalize first letter of a string
 */
function capitalizeFirstLetter(string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
}

/**
 * Update existing slip from API response
 */
function updateExistingSlipFromAPI(row, slip) {
    // Check if draw status has changed
    const currentStatus = row.find('.result-cell .badge').text().toLowerCase();
    let newStatus = 'pending';

    if (slip.draw_status === 'completed' && slip.winning_number !== null) {
        // Determine if slip won based on API data
        newStatus = (slip.status === 'won' || slip.winning_amount > 0) ? 'win' : 'loss';
    }

    // Only update if status has changed
    if (currentStatus !== newStatus) {
        // Update winning number cell
        const winningNumberCell = row.find('td:nth-child(7)');
        if (slip.winning_number !== null) {
            const badgeClass = slip.winning_color === 'red' ? 'bg-danger' :
                              (slip.winning_color === 'black' ? 'bg-dark' : 'bg-success');
            winningNumberCell.html(`<span class="badge ${badgeClass}">${slip.winning_number}</span>`);
        }

        // Update result cell
        const resultCell = row.find('.result-cell');
        if (newStatus === 'pending') {
            resultCell.html('<span class="badge badge-pending">Pending</span>');
        } else if (newStatus === 'win') {
            resultCell.html('<span class="badge badge-win">WIN</span>');
        } else {
            resultCell.html('<span class="badge badge-loss">LOSS</span>');
        }

        // Update actual win cell
        const winCell = row.find('td:nth-child(9)');
        if (newStatus === 'win' && slip.winning_amount > 0) {
            winCell.html(`<span class="text-success fw-bold">$${formatNumber(slip.winning_amount)}</span>`);
        } else {
            winCell.html('<span class="text-muted">$0.00</span>');
        }

        // Add highlight animation
        row.addClass('highlight-update');
        setTimeout(function() {
            row.removeClass('highlight-update');
        }, 3000);

        // POS System: Winner notifications disabled for cashier interface
    }
}

/**
 * Initialize countdown timers
 */
function initializeCountdowns() {
    // Clear any existing intervals
    countdownIntervals.forEach(clearInterval);
    countdownIntervals = [];

    // Find all countdown elements
    $('.countdown').each(function() {
        const $this = $(this);
        let timeRemaining = parseInt($this.data('time'));

        // Update immediately
        $this.text(formatTime(timeRemaining));

        // Set interval to update every second
        const interval = setInterval(function() {
            timeRemaining--;

            if (timeRemaining <= 0) {
                clearInterval(interval);
                $this.removeClass('countdown').addClass('text-muted').text('Completed');

                // Trigger an update to get the result
                setTimeout(updateData, 2000);
            } else {
                $this.text(formatTime(timeRemaining));
            }
        }, 1000);

        countdownIntervals.push(interval);
    });
}

/**
 * Initialize charts
 */
function initializeCharts() {
    // Only initialize if we have chart data
    if (!chartData) return;

    // Monthly performance chart
    const monthlyCtx = document.getElementById('monthlyChart');
    if (monthlyCtx) {
        const monthlyData = prepareMonthlyChartData();
        chartInstances.monthly = new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: monthlyData.labels,
                datasets: [
                    {
                        label: 'Bets',
                        data: monthlyData.bets,
                        backgroundColor: 'rgba(220, 53, 69, 0.7)',
                        borderColor: 'rgba(220, 53, 69, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Wins',
                        data: monthlyData.wins,
                        backgroundColor: 'rgba(40, 167, 69, 0.7)',
                        borderColor: 'rgba(40, 167, 69, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Net Profit/Loss',
                        data: monthlyData.net,
                        type: 'line',
                        borderColor: 'rgba(0, 123, 255, 1)',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        borderWidth: 2,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += '$' + formatNumber(context.raw);
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + formatNumber(value);
                            }
                        }
                    }
                }
            }
        });
    }

    // Bet type distribution chart
    const betTypeCtx = document.getElementById('betTypeChart');
    if (betTypeCtx) {
        const betTypeData = prepareBetTypeChartData();
        chartInstances.betType = new Chart(betTypeCtx, {
            type: 'pie',
            data: {
                labels: betTypeData.labels,
                datasets: [{
                    data: betTypeData.values,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)',
                        'rgba(255, 159, 64, 0.7)',
                        'rgba(199, 199, 199, 0.7)',
                        'rgba(83, 102, 255, 0.7)',
                        'rgba(40, 167, 69, 0.7)',
                        'rgba(220, 53, 69, 0.7)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)',
                        'rgba(199, 199, 199, 1)',
                        'rgba(83, 102, 255, 1)',
                        'rgba(40, 167, 69, 1)',
                        'rgba(220, 53, 69, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${percentage}% (${value} bets)`;
                            }
                        }
                    }
                }
            }
        });
    }
}

/**
 * Update charts with new data
 */
function updateCharts(summary) {
    // Update monthly chart if it exists
    if (chartInstances.monthly) {
        const monthlyData = prepareMonthlyChartData();
        chartInstances.monthly.data.labels = monthlyData.labels;
        chartInstances.monthly.data.datasets[0].data = monthlyData.bets;
        chartInstances.monthly.data.datasets[1].data = monthlyData.wins;
        chartInstances.monthly.data.datasets[2].data = monthlyData.net;
        chartInstances.monthly.update();
    }

    // Update bet type chart if it exists
    if (chartInstances.betType) {
        const betTypeData = prepareBetTypeChartData();
        chartInstances.betType.data.labels = betTypeData.labels;
        chartInstances.betType.data.datasets[0].data = betTypeData.values;
        chartInstances.betType.update();
    }
}

/**
 * Prepare data for monthly chart
 */
function prepareMonthlyChartData() {
    const labels = [];
    const bets = [];
    const wins = [];
    const net = [];

    // Process monthly data
    if (chartData && chartData.monthly) {
        Object.keys(chartData.monthly).forEach(function(month) {
            labels.push(month);
            bets.push(chartData.monthly[month].bets);
            wins.push(chartData.monthly[month].wins);
            net.push(chartData.monthly[month].net);
        });
    }

    return { labels, bets, wins, net };
}

/**
 * Prepare data for bet type chart
 */
function prepareBetTypeChartData() {
    const labels = [];
    const values = [];

    // Process bet type data
    if (chartData && chartData.betTypes) {
        Object.keys(chartData.betTypes).forEach(function(type) {
            labels.push(capitalizeFirstLetter(type));
            values.push(chartData.betTypes[type].count);
        });
    }

    return { labels, values };
}

/**
 * Set up event listeners
 */
function setupEventListeners() {
    // Refresh buttons
    $('#refresh-slips').on('click', function() {
        updateData();
        showUpdateNotification('Refreshing betting slips...');
    });

    $('#refresh-transactions').on('click', function() {
        updateData();
        showUpdateNotification('Refreshing transactions...');
    });

    // Tab change events
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
        // Refresh data when switching to a tab
        updateData();
    });
}

/**
 * Update the "last updated" text
 */
function updateLastUpdatedText() {
    const now = new Date();
    const lastUpdatedDate = new Date(lastUpdated * 1000);
    const diffInSeconds = Math.floor((now - lastUpdatedDate) / 1000);

    let timeText;
    if (diffInSeconds < 10) {
        timeText = 'Just now';
    } else if (diffInSeconds < 60) {
        timeText = diffInSeconds + ' seconds ago';
    } else if (diffInSeconds < 3600) {
        const minutes = Math.floor(diffInSeconds / 60);
        timeText = minutes + ' minute' + (minutes > 1 ? 's' : '') + ' ago';
    } else {
        const hours = Math.floor(diffInSeconds / 3600);
        timeText = hours + ' hour' + (hours > 1 ? 's' : '') + ' ago';
    }

    $('#last-updated').text(timeText);
}

/**
 * Show update notification - POS System: Winner notifications disabled
 */
function showUpdateNotification(message, type = 'primary') {
    // POS System: Block winner-related notifications for cashier interface
    if (message.includes('won') || message.includes('WIN') || message.includes('winner') ||
        message.includes('did not win') || message.includes('LOSS') || type === 'success' && message.includes('$')) {
        console.log('POS System: Winner notification blocked:', message);
        return; // Don't show winner notifications
    }

    // Allow non-winner notifications (like refresh messages)
    $('#toast-message').text(message);
    const toast = $('#update-toast');
    toast.removeClass('bg-primary bg-success bg-danger bg-warning bg-info');
    toast.addClass('bg-' + type);
    toastInstance.show();
}

/**
 * Format number with commas and 2 decimal places
 */
function formatNumber(number) {
    return parseFloat(number).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

/**
 * Format time in MM:SS format
 */
function formatTime(seconds) {
    return new Date(seconds * 1000).toISOString().substr(14, 5);
}

/**
 * Format date in YYYY-MM-DD HH:MM format
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toISOString().slice(0, 16).replace('T', ' ');
}

/**
 * Capitalize first letter of a string
 */
function capitalizeFirstLetter(string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
}
