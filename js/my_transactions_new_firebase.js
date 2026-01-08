/**
 * My Transactions Page - Firebase Version
 * Handles all transaction display and management using Firebase
 */

$(document).ready(function() {
    // Check authentication first
    if (!window.FirebaseAuth || !window.FirebaseAuth.isInitialized()) {
        setTimeout(checkAuthAndLoad, 1000);
    } else {
        checkAuthAndLoad();
    }

    // Setup event listeners
    $('#refresh-slips').on('click', loadBettingSlips);
    $('#refresh-transactions').on('click', loadTransactions);
    $('#logout-link').on('click', handleLogout);
});

let currentUser = null;
let transactions = [];
let bettingSlips = [];

/**
 * Check authentication and load data
 */
async function checkAuthAndLoad() {
    try {
        // Check if user is authenticated
        if (!window.FirebaseAuth || !window.FirebaseAuth.isInitialized()) {
            window.location.href = 'login.html';
            return;
        }

        currentUser = window.FirebaseAuth.getCurrentUser();
        if (!currentUser) {
            window.location.href = 'login.html';
            return;
        }

        // Hide loading overlay
        $('#loading-overlay').fadeOut();

        // Load user info
        await loadUserInfo();

        // Load transactions and betting slips
        await Promise.all([
            loadTransactions(),
            loadBettingSlips()
        ]);

        // Setup real-time listeners
        setupRealTimeListeners();

        // Update last updated time
        updateLastUpdated();

    } catch (error) {
        console.error('Error initializing page:', error);
        $('#loading-overlay').fadeOut();
        alert('Error loading page. Please refresh.');
    }
}

/**
 * Load user information from Firebase
 */
async function loadUserInfo() {
    try {
        if (!window.FirebaseService || !window.FirebaseService.isOnline()) {
            throw new Error('Firebase service not available');
        }

        const userData = await window.FirebaseService.read(`users/${currentUser.username}`);
        
        if (userData) {
            // Update UI
            $('#username-display').text(currentUser.username);
            $('#user-role').text(currentUser.role || 'cashier');
            $('#balance-amount').text('$' + parseFloat(userData.cash_balance || 0).toFixed(2));
            $('#user-balance-card').show();

            // Calculate win rate from transactions
            const stats = await window.FirebaseTransactions.getTransactionStats(currentUser.username);
            const winRate = stats.total_bets > 0 
                ? ((stats.total_wins / stats.total_bets) * 100).toFixed(1)
                : '0';
            $('#win-rate').text(winRate + '%');
        }
    } catch (error) {
        console.error('Error loading user info:', error);
    }
}

/**
 * Load transactions from Firebase
 */
async function loadTransactions() {
    try {
        if (!window.FirebaseTransactions || !window.FirebaseTransactions.isInitialized()) {
            console.error('FirebaseTransactions not initialized');
            return;
        }

        $('#loading-overlay').show();
        
        transactions = await window.FirebaseTransactions.getTransactions(currentUser.username, {
            limit: 100
        });

        displayTransactions(transactions);
        updateStats();

        $('#loading-overlay').fadeOut();
    } catch (error) {
        console.error('Error loading transactions:', error);
        $('#loading-overlay').fadeOut();
        $('#transactions-empty').show();
    }
}

/**
 * Display transactions in the table
 */
function displayTransactions(transactionsList) {
    const tbody = $('#transactions-tbody');
    tbody.empty();

    if (transactionsList.length === 0) {
        $('#transactions-empty').show();
        $('#transactions-table-container').hide();
        return;
    }

    $('#transactions-empty').hide();
    $('#transactions-table-container').show();

    transactionsList.forEach(transaction => {
        const date = new Date(transaction.timestamp || transaction.created_at);
        const typeClass = transaction.amount >= 0 ? 'text-success' : 'text-danger';
        const typeIcon = transaction.amount >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
        const typeLabel = transaction.transaction_type || 'other';

        const row = `
            <tr>
                <td>${date.toLocaleString()}</td>
                <td><span class="badge bg-secondary">${typeLabel}</span></td>
                <td class="${typeClass}">
                    <i class="fas ${typeIcon}"></i> $${Math.abs(transaction.amount || 0).toFixed(2)}
                </td>
                <td>$${parseFloat(transaction.balance_after || 0).toFixed(2)}</td>
                <td>${transaction.description || '-'}</td>
                <td>${transaction.reference_id || '-'}</td>
            </tr>
        `;
        tbody.append(row);
    });
}

/**
 * Load betting slips from Firebase
 */
async function loadBettingSlips() {
    try {
        if (!window.FirebaseTransactions || !window.FirebaseTransactions.isInitialized()) {
            console.error('FirebaseTransactions not initialized');
            return;
        }

        $('#loading-overlay').show();

        bettingSlips = await window.FirebaseTransactions.getBettingSlips(currentUser.username, {
            limit: 100
        });

        displayBettingSlips(bettingSlips);

        $('#loading-overlay').fadeOut();
    } catch (error) {
        console.error('Error loading betting slips:', error);
        $('#loading-overlay').fadeOut();
        $('#betting-slips-empty').show();
    }
}

/**
 * Display betting slips in the table
 */
function displayBettingSlips(slips) {
    const tbody = $('#betting-slips-tbody');
    tbody.empty();

    if (slips.length === 0) {
        $('#betting-slips-empty').show();
        $('#betting-slips-table-container').hide();
        return;
    }

    $('#betting-slips-empty').hide();
    $('#betting-slips-table-container').show();

    slips.forEach(slip => {
        const date = new Date(slip.created_at || slip.timestamp);
        const isWinner = slip.status === 'won' || slip.is_winner;
        const resultBadge = slip.status === 'pending' 
            ? '<span class="badge badge-pending">Pending</span>'
            : isWinner
            ? '<span class="badge badge-win">WIN</span>'
            : '<span class="badge badge-loss">LOSS</span>';

        const winningNumberBadge = slip.winningNumber 
            ? `<span class="badge bg-${slip.color === 'red' ? 'danger' : slip.color === 'black' ? 'dark' : 'success'}">${slip.winningNumber}</span>`
            : '<span class="badge bg-secondary">Pending</span>';

        const row = `
            <tr data-slip-id="${slip.slip_id}">
                <td>${slip.slip_number || slip.barcodeNumber || slip.slip_id}</td>
                <td>${date.toLocaleString()}</td>
                <td>${slip.drawNumber || '-'}</td>
                <td>${slip.drawTime || date.toLocaleString()}</td>
                <td>$${parseFloat(slip.totalStakes || slip.total_stake || 0).toFixed(2)}</td>
                <td>$${parseFloat(slip.potentialPayout || slip.potential_payout || 0).toFixed(2)}</td>
                <td>${winningNumberBadge}</td>
                <td class="result-cell">${resultBadge}</td>
                <td>
                    ${isWinner 
                        ? `<span class="text-success fw-bold">$${parseFloat(slip.paidOutAmount || slip.winning_amount || 0).toFixed(2)}</span>`
                        : '<span class="text-muted">$0.00</span>'
                    }
                </td>
                <td>
                    <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#slip-${slip.slip_id}">
                        <i class="fas fa-eye"></i> View
                    </button>
                </td>
            </tr>
            <tr class="collapse" id="slip-${slip.slip_id}">
                <td colspan="10">
                    <div class="card card-body bg-light m-2">
                        <h6 class="mb-3">Bets for Slip #${slip.slip_number || slip.slip_id}</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
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
                                <tbody>
                                    ${(slip.bets || []).map(bet => `
                                        <tr>
                                            <td>${bet.bet_type || 'N/A'}</td>
                                            <td>${bet.bet_description || bet.description || '-'}</td>
                                            <td>$${parseFloat(bet.bet_amount || bet.amount || 0).toFixed(2)}</td>
                                            <td>${bet.multiplier || '1'}:1</td>
                                            <td>$${parseFloat(bet.potential_return || bet.potentialReturn || 0).toFixed(2)}</td>
                                            <td>
                                                ${slip.status === 'pending' 
                                                    ? '<span class="badge badge-pending">Pending</span>'
                                                    : (bet.is_winner || bet.isWinner)
                                                    ? '<span class="badge badge-win">WIN</span>'
                                                    : '<span class="badge badge-loss">LOSS</span>'
                                                }
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </td>
            </tr>
        `;
        tbody.append(row);
    });
}

/**
 * Update statistics
 */
function updateStats() {
    let totalBets = 0;
    let totalWins = 0;
    let totalLosses = 0;

    transactions.forEach(t => {
        const amount = Math.abs(t.amount || 0);
        if (t.transaction_type === 'bet') {
            totalBets += amount;
        } else if (t.transaction_type === 'win') {
            totalWins += amount;
        } else if (t.transaction_type === 'loss') {
            totalLosses += amount;
        }
    });

    const netProfit = totalWins - totalBets;
    const roi = totalBets > 0 ? ((netProfit / totalBets) * 100).toFixed(1) : '0';

    $('#total-bets').text('$' + totalBets.toFixed(2));
    $('#total-wins').text('$' + totalWins.toFixed(2));
    $('#net-profit').text('$' + netProfit.toFixed(2));
    $('#roi').text(roi + '%');
}

/**
 * Setup real-time listeners
 */
function setupRealTimeListeners() {
    if (!window.FirebaseTransactions || !window.FirebaseTransactions.isInitialized()) {
        return;
    }

    // Listen to transactions
    window.FirebaseTransactions.listenToTransactions(currentUser.username, (updatedTransactions) => {
        transactions = updatedTransactions;
        displayTransactions(transactions);
        updateStats();
        updateLastUpdated();
    });

    // Update balance in real-time
    if (window.FirebaseService && window.FirebaseService.isOnline()) {
        window.firebaseDatabase.ref(`users/${currentUser.username}/cash_balance`).on('value', (snapshot) => {
            const balance = snapshot.val() || 0;
            $('#balance-amount').text('$' + parseFloat(balance).toFixed(2));
        });
    }
}

/**
 * Update last updated time
 */
function updateLastUpdated() {
    const now = new Date();
    $('#last-updated').text(now.toLocaleTimeString());
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

