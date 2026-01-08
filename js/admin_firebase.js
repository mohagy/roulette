/**
 * Admin Dashboard - Firebase Version
 * Full admin dashboard matching the original design
 */

let earningsChart = null;
let transactionTypesChart = null;
let currentUser = null;

document.addEventListener('DOMContentLoaded', function() {
    // Check authentication
    if (!window.FirebaseAuth || !window.FirebaseAuth.isInitialized()) {
        setTimeout(checkAuth, 1000);
    } else {
        checkAuth();
    }

    // Setup logout
    const logoutLink = document.getElementById('logout-link');
    if (logoutLink) {
        logoutLink.addEventListener('click', handleLogout);
    }
});

/**
 * Check authentication and admin role
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

        // Check if user is admin
        if (currentUser.role !== 'admin') {
            alert('Access denied. Admin privileges required.');
            window.location.href = 'index.html';
            return;
        }

        // Load admin statistics
        await loadAdminStats();

        // Initialize charts
        initializeCharts();

        // Setup real-time listeners
        setupRealTimeListeners();

    } catch (error) {
        console.error('Error initializing admin dashboard:', error);
        window.location.href = 'login.html';
    }
}

/**
 * Load admin statistics from Firebase
 */
async function loadAdminStats() {
    try {
        if (!window.FirebaseService || !window.FirebaseService.isOnline()) {
            console.error('Firebase service not available');
            return;
        }

        // Load users
        const usersSnapshot = await window.firebaseDatabase.ref('users').once('value');
        const users = usersSnapshot.val() || {};
        const userCount = Object.keys(users).length;
        document.getElementById('total-users').textContent = userCount;

        // Calculate total cash
        let totalCash = 0;
        Object.values(users).forEach(user => {
            totalCash += parseFloat(user.cash_balance || 0);
        });
        document.getElementById('total-cash').textContent = '$' + totalCash.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});

        // Calculate total bets and commission
        const transactionsSnapshot = await window.firebaseDatabase.ref('transactions').once('value');
        const transactions = transactionsSnapshot.val() || {};
        
        let totalBets = 0;
        let totalCommission = 0;
        
        Object.values(transactions).forEach(transaction => {
            if (transaction.transaction_type === 'bet') {
                totalBets += Math.abs(parseFloat(transaction.amount || 0));
            }
        });

        // Calculate commission from commission table
        const commissionSnapshot = await window.firebaseDatabase.ref('commission').once('value');
        const commissions = commissionSnapshot.val() || {};
        Object.values(commissions).forEach(commission => {
            totalCommission += parseFloat(commission.total_commission || 0);
        });

        document.getElementById('total-bets').textContent = '$' + totalBets.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('total-commission').textContent = '$' + totalCommission.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});

        // Display recent transactions
        displayRecentTransactions(transactions, users, 5);

        // Display recent users
        displayRecentUsers(users, 5);

        // Update charts with real data
        updateChartsWithData(transactions);

    } catch (error) {
        console.error('Error loading admin stats:', error);
    }
}

/**
 * Display recent users
 */
function displayRecentUsers(users, limit = 5) {
    const usersArray = Object.entries(users)
        .map(([username, userData], index) => ({
            user_id: index + 1,
            username: username,
            role: userData.role || 'cashier',
            cash_balance: userData.cash_balance || 0,
            created_at: userData.created_at || null
        }))
        .sort((a, b) => {
            const dateA = a.created_at ? new Date(a.created_at) : new Date(0);
            const dateB = b.created_at ? new Date(b.created_at) : new Date(0);
            return dateB - dateA;
        })
        .slice(0, limit);

    const tbody = document.getElementById('recent-users-tbody');
    
    if (usersArray.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">No users found.</td></tr>';
        return;
    }

    let html = '';
    usersArray.forEach(user => {
        const date = user.created_at ? new Date(user.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A';
        html += `
            <tr>
                <td>${user.user_id}</td>
                <td>${user.username}</td>
                <td>${user.role}</td>
                <td>$${parseFloat(user.cash_balance).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                <td>${date}</td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

/**
 * Display recent transactions
 */
function displayRecentTransactions(transactions, users, limit = 5) {
    const transactionsArray = Object.entries(transactions)
        .map(([id, transaction]) => {
            const username = users[transaction.user_id] ? users[transaction.user_id].username : transaction.user_id || 'Unknown';
            return {
                transaction_id: id.substring(0, 8),
                id: id,
                username: username,
                ...transaction
            };
        })
        .sort((a, b) => {
            const dateA = new Date(a.timestamp || a.created_at || 0);
            const dateB = new Date(b.timestamp || b.created_at || 0);
            return dateB - dateA;
        })
        .slice(0, limit);

    const tbody = document.getElementById('recent-transactions-tbody');
    
    if (transactionsArray.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">No transactions found.</td></tr>';
        return;
    }

    let html = '';
    transactionsArray.forEach(transaction => {
        const date = new Date(transaction.timestamp || transaction.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        const amountClass = transaction.amount >= 0 ? 'text-success' : 'text-danger';
        
        html += `
            <tr>
                <td>${transaction.transaction_id}</td>
                <td>${transaction.username}</td>
                <td class="${amountClass}">$${parseFloat(transaction.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                <td>${transaction.transaction_type || 'N/A'}</td>
                <td>${date}</td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

/**
 * Initialize charts
 */
function initializeCharts() {
    // Earnings Chart (Line Chart)
    const earningsCtx = document.getElementById('earningsChart');
    if (earningsCtx) {
        earningsChart = new Chart(earningsCtx, {
            type: 'line',
            data: {
                labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
                datasets: [{
                    label: "Earnings",
                    lineTension: 0.3,
                    backgroundColor: "rgba(78, 115, 223, 0.05)",
                    borderColor: "rgba(78, 115, 223, 1)",
                    pointRadius: 3,
                    pointBackgroundColor: "rgba(78, 115, 223, 1)",
                    pointBorderColor: "rgba(78, 115, 223, 1)",
                    pointHoverRadius: 3,
                    pointHoverBackgroundColor: "rgba(78, 115, 223, 1)",
                    pointHoverBorderColor: "rgba(78, 115, 223, 1)",
                    pointHitRadius: 10,
                    pointBorderWidth: 2,
                    data: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Earnings: $' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    // Transaction Types Chart (Doughnut Chart)
    const transactionCtx = document.getElementById('transactionTypesChart');
    if (transactionCtx) {
        transactionTypesChart = new Chart(transactionCtx, {
            type: 'doughnut',
            data: {
                labels: ["Bets", "Wins", "Vouchers", "Admin", "Refunds"],
                datasets: [{
                    data: [0, 0, 0, 0, 0],
                    backgroundColor: ['#e74a3b', '#1cc88a', '#4e73df', '#f6c23e', '#36b9cc'],
                    hoverBackgroundColor: ['#be3c2d', '#17a673', '#2e59d9', '#dda20a', '#2c9faf'],
                    hoverBorderColor: "rgba(234, 236, 244, 1)"
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: window.innerWidth < 768 ? 'right' : 'bottom',
                        labels: {
                            font: {
                                size: window.innerWidth < 768 ? 10 : 12
                            },
                            color: "#858796"
                        }
                    }
                },
                cutout: '70%'
            }
        });
    }
}

/**
 * Update charts with real data
 */
function updateChartsWithData(transactions) {
    // Calculate earnings by month (from transactions)
    const monthlyEarnings = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
    const currentMonth = new Date().getMonth();
    
    Object.values(transactions).forEach(transaction => {
        const date = new Date(transaction.timestamp || transaction.created_at);
        const month = date.getMonth();
        if (transaction.amount > 0) { // Only positive transactions (wins, vouchers, etc.)
            monthlyEarnings[month] += parseFloat(transaction.amount || 0);
        }
    });
    
    if (earningsChart) {
        earningsChart.data.datasets[0].data = monthlyEarnings;
        earningsChart.update();
    }

    // Calculate transaction types distribution
    const transactionTypes = {
        'bet': 0,
        'win': 0,
        'voucher': 0,
        'voucher_redemption': 0,
        'admin': 0,
        'refund': 0
    };
    
    Object.values(transactions).forEach(transaction => {
        const type = (transaction.transaction_type || 'other').toLowerCase();
        if (transactionTypes.hasOwnProperty(type)) {
            transactionTypes[type]++;
        } else if (type.includes('voucher')) {
            transactionTypes['voucher']++;
        } else if (type.includes('admin')) {
            transactionTypes['admin']++;
        } else if (type.includes('refund')) {
            transactionTypes['refund']++;
        }
    });
    
    if (transactionTypesChart) {
        transactionTypesChart.data.datasets[0].data = [
            transactionTypes['bet'] || 0,
            transactionTypes['win'] || 0,
            (transactionTypes['voucher'] || 0) + (transactionTypes['voucher_redemption'] || 0),
            transactionTypes['admin'] || 0,
            transactionTypes['refund'] || 0
        ];
        transactionTypesChart.update();
    }
}

/**
 * Setup real-time listeners
 */
function setupRealTimeListeners() {
    if (!window.FirebaseService || !window.FirebaseService.isOnline()) {
        return;
    }

    // Listen to users
    window.firebaseDatabase.ref('users').on('value', async (snapshot) => {
        const users = snapshot.val() || {};
        const userCount = Object.keys(users).length;
        document.getElementById('total-users').textContent = userCount;
        
        let totalCash = 0;
        Object.values(users).forEach(user => {
            totalCash += parseFloat(user.cash_balance || 0);
        });
        document.getElementById('total-cash').textContent = '$' + totalCash.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        
        displayRecentUsers(users, 5);
    });

    // Listen to transactions
    window.firebaseDatabase.ref('transactions').on('value', async (snapshot) => {
        const transactions = snapshot.val() || {};
        
        // Calculate total bets
        let totalBets = 0;
        Object.values(transactions).forEach(transaction => {
            if (transaction.transaction_type === 'bet') {
                totalBets += Math.abs(parseFloat(transaction.amount || 0));
            }
        });
        document.getElementById('total-bets').textContent = '$' + totalBets.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        
        // Get users for display
        const usersSnapshot = await window.firebaseDatabase.ref('users').once('value');
        const users = usersSnapshot.val() || {};
        
        displayRecentTransactions(transactions, users, 5);
        updateChartsWithData(transactions);
    });

    // Listen to commission
    window.firebaseDatabase.ref('commission').on('value', (snapshot) => {
        const commissions = snapshot.val() || {};
        let totalCommission = 0;
        Object.values(commissions).forEach(commission => {
            totalCommission += parseFloat(commission.total_commission || 0);
        });
        document.getElementById('total-commission').textContent = '$' + totalCommission.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
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

