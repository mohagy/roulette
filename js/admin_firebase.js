/**
 * Admin Dashboard - Firebase Version
 */

$(document).ready(function() {
    // Check authentication and admin role
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

        // Update welcome message
        $('#welcome-message').text(`Welcome, ${currentUser.username} (Admin)`);

        // Load admin statistics
        await loadAdminStats();

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
        $('#total-users').text(userCount);

        // Calculate total cash
        let totalCash = 0;
        Object.values(users).forEach(user => {
            totalCash += parseFloat(user.cash_balance || 0);
        });
        $('#total-cash').text('$' + totalCash.toFixed(2));

        // Load transactions
        const transactionsSnapshot = await window.firebaseDatabase.ref('transactions').once('value');
        const transactions = transactionsSnapshot.val() || {};
        const transactionCount = Object.keys(transactions).length;
        $('#total-transactions').text(transactionCount);

        // Display recent transactions
        displayRecentTransactions(transactions, 5);

        // Load vouchers
        if (window.FirebaseVouchers && window.FirebaseVouchers.isInitialized()) {
            const vouchers = await window.FirebaseVouchers.getVouchers({ status: 'active' });
            $('#total-vouchers').text(vouchers.length);
        }

        // Display recent users
        displayRecentUsers(users, 5);

    } catch (error) {
        console.error('Error loading admin stats:', error);
    }
}

/**
 * Display recent users
 */
function displayRecentUsers(users, limit = 5) {
    const usersArray = Object.entries(users)
        .map(([username, userData]) => ({
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

    const container = $('#recent-users');
    
    if (usersArray.length === 0) {
        container.html('<p class="text-muted">No users found.</p>');
        return;
    }

    let html = '<ul class="list-group">';
    usersArray.forEach(user => {
        const date = user.created_at ? new Date(user.created_at).toLocaleDateString() : 'N/A';
        html += `
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <strong>${user.username}</strong>
                    <br>
                    <small class="text-muted">${user.role} • Created: ${date}</small>
                </div>
                <span class="badge bg-primary">$${parseFloat(user.cash_balance).toFixed(2)}</span>
            </li>
        `;
    });
    html += '</ul>';
    container.html(html);
}

/**
 * Display recent transactions
 */
function displayRecentTransactions(transactions, limit = 5) {
    const transactionsArray = Object.entries(transactions)
        .map(([id, transaction]) => ({
            id: id,
            ...transaction
        }))
        .sort((a, b) => {
            const dateA = new Date(a.timestamp || a.created_at || 0);
            const dateB = new Date(b.timestamp || b.created_at || 0);
            return dateB - dateA;
        })
        .slice(0, limit);

    const container = $('#recent-transactions');
    
    if (transactionsArray.length === 0) {
        container.html('<p class="text-muted">No transactions found.</p>');
        return;
    }

    let html = '<ul class="list-group">';
    transactionsArray.forEach(transaction => {
        const date = new Date(transaction.timestamp || transaction.created_at).toLocaleString();
        const amountClass = transaction.amount >= 0 ? 'text-success' : 'text-danger';
        const amountIcon = transaction.amount >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
        
        html += `
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <strong>${transaction.user_id || 'Unknown'}</strong>
                    <br>
                    <small class="text-muted">${transaction.transaction_type || 'N/A'} • ${date}</small>
                    <br>
                    <small>${transaction.description || ''}</small>
                </div>
                <span class="badge ${amountClass}">
                    <i class="fas ${amountIcon}"></i> $${Math.abs(transaction.amount || 0).toFixed(2)}
                </span>
            </li>
        `;
    });
    html += '</ul>';
    container.html(html);
}

/**
 * Setup real-time listeners
 */
function setupRealTimeListeners() {
    if (!window.FirebaseService || !window.FirebaseService.isOnline()) {
        return;
    }

    // Listen to users
    window.firebaseDatabase.ref('users').on('value', (snapshot) => {
        const users = snapshot.val() || {};
        const userCount = Object.keys(users).length;
        $('#total-users').text(userCount);
        
        let totalCash = 0;
        Object.values(users).forEach(user => {
            totalCash += parseFloat(user.cash_balance || 0);
        });
        $('#total-cash').text('$' + totalCash.toFixed(2));
        
        displayRecentUsers(users, 5);
    });

    // Listen to transactions
    window.firebaseDatabase.ref('transactions').on('value', (snapshot) => {
        const transactions = snapshot.val() || {};
        const transactionCount = Object.keys(transactions).length;
        $('#total-transactions').text(transactionCount);
        displayRecentTransactions(transactions, 5);
    });

    // Listen to vouchers
    if (window.FirebaseVouchers && window.FirebaseVouchers.isInitialized()) {
        // Update vouchers count periodically
        setInterval(async () => {
            try {
                const vouchers = await window.FirebaseVouchers.getVouchers({ status: 'active' });
                $('#total-vouchers').text(vouchers.length);
            } catch (error) {
                console.error('Error updating vouchers count:', error);
            }
        }, 10000); // Every 10 seconds
    }
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

