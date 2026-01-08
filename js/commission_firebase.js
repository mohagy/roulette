/**
 * Commission Page - Firebase Version
 */

let commissionChart = null;

$(document).ready(function() {
    // Check authentication
    if (!window.FirebaseAuth || !window.FirebaseAuth.isInitialized()) {
        setTimeout(checkAuth, 1000);
    } else {
        checkAuth();
    }

    // Setup logout
    $('#logout-link').on('click', handleLogout);
});

let currentUser = null;

/**
 * Check authentication and load data
 */
async function checkAuth() {
    try {
        if (!window.FirebaseAuth || !window.FirebaseAuth.isInitialized()) {
            window.location.href = 'login.html';
            return;
        }

        currentUser = window.FirebaseAuth.getCurrentUser();
        if (!currentUser) {
            window.location.href = 'login.html';
            return;
        }

        // Load commission data
        await loadCommissionData();
    } catch (error) {
        console.error('Error initializing page:', error);
        window.location.href = 'login.html';
    }
}

/**
 * Load commission data from Firebase
 */
async function loadCommissionData() {
    try {
        if (!window.FirebaseCommission || !window.FirebaseCommission.isInitialized()) {
            console.error('FirebaseCommission not initialized');
            return;
        }

        // Get commission summary
        const commission = await window.FirebaseCommission.getCommission(currentUser.username);
        
        // Update stats
        $('#total-commission').text('$' + parseFloat(commission.total_commission || 0).toFixed(2));
        $('#total-bets').text(commission.total_bets || 0);

        // Get today's summary
        const dailySummary = await window.FirebaseCommission.getDailySummary(currentUser.username);
        $('#today-commission').text('$' + parseFloat(dailySummary.total_commission || 0).toFixed(2));

        // Load history
        const history = await window.FirebaseCommission.getCommissionHistory(currentUser.username, 30);
        displayCommissionHistory(history);

        // Update chart
        updateChart(history);

    } catch (error) {
        console.error('Error loading commission data:', error);
        $('#commission-history-empty').show();
    }
}

/**
 * Display commission history
 */
function displayCommissionHistory(history) {
    const tbody = $('#commission-history-tbody');
    tbody.empty();

    if (history.length === 0) {
        $('#commission-history-empty').show();
        $('#commission-history-table-container').hide();
        return;
    }

    $('#commission-history-empty').hide();
    $('#commission-history-table-container').show();

    history.forEach(item => {
        const date = new Date(item.timestamp);
        const row = `
            <tr>
                <td>${date.toLocaleDateString()}</td>
                <td class="text-success font-weight-bold">+$${parseFloat(item.amount || 0).toFixed(2)}</td>
                <td>${item.description || 'Commission from bet'}</td>
            </tr>
        `;
        tbody.append(row);
    });
}

/**
 * Update commission chart
 */
function updateChart(history) {
    const ctx = document.getElementById('commission-chart');
    if (!ctx) return;

    // Group by date
    const dailyData = {};
    history.forEach(item => {
        const date = new Date(item.timestamp).toISOString().split('T')[0];
        if (!dailyData[date]) {
            dailyData[date] = 0;
        }
        dailyData[date] += parseFloat(item.amount || 0);
    });

    const dates = Object.keys(dailyData).sort();
    const amounts = dates.map(date => dailyData[date]);

    if (commissionChart) {
        commissionChart.destroy();
    }

    commissionChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: dates,
            datasets: [{
                label: 'Daily Commission',
                data: amounts,
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                tension: 0.4,
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
                            return '$' + value.toFixed(2);
                        }
                    }
                }
            }
        }
    });
}

/**
 * Handle logout
 */
function handleLogout(e) {
    e.preventDefault();
    if (confirm('Are you sure you want to logout?')) {
        if (window.FirebaseAuth) {
            window.FirebaseAuth.logout();
        }
        window.location.href = 'login.html';
    }
}

