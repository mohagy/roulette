/**
 * Redeem Voucher Page - Firebase Version
 */

$(document).ready(function() {
    // Check authentication
    if (!window.FirebaseAuth || !window.FirebaseAuth.isInitialized()) {
        setTimeout(checkAuth, 1000);
    } else {
        checkAuth();
    }

    // Setup form handler
    $('#voucher-form').on('submit', handleVoucherRedemption);
});

let currentUser = null;

/**
 * Check authentication and load user info
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

        // Load user balance
        await loadUserBalance();
    } catch (error) {
        console.error('Error checking auth:', error);
        window.location.href = 'login.html';
    }
}

/**
 * Load user balance from Firebase
 */
async function loadUserBalance() {
    try {
        if (!window.FirebaseService || !window.FirebaseService.isOnline()) {
            return;
        }

        const userData = await window.FirebaseService.read(`users/${currentUser.username}`);
        if (userData && userData.cash_balance !== undefined) {
            $('#current-balance').text('$' + parseFloat(userData.cash_balance).toFixed(2));
            $('#balance-display').show();
        }

        // Listen for balance updates
        window.firebaseDatabase.ref(`users/${currentUser.username}/cash_balance`).on('value', (snapshot) => {
            const balance = snapshot.val() || 0;
            $('#current-balance').text('$' + parseFloat(balance).toFixed(2));
        });
    } catch (error) {
        console.error('Error loading balance:', error);
    }
}

/**
 * Handle voucher redemption
 */
async function handleVoucherRedemption(e) {
    e.preventDefault();

    const voucherCode = $('#voucher_code').val().trim().toUpperCase();
    
    if (!voucherCode) {
        showMessage('Please enter a voucher code', 'warning');
        return;
    }

    if (!window.FirebaseVouchers || !window.FirebaseVouchers.isInitialized()) {
        showMessage('Firebase service not available. Please refresh the page.', 'danger');
        return;
    }

    // Disable form
    const submitBtn = $('#voucher-form button[type="submit"]');
    submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Redeeming...');

    try {
        // Check voucher status first
        const status = await window.FirebaseVouchers.checkVoucherStatus(voucherCode);

        if (!status.valid) {
            showMessage(status.message, 'warning');
            submitBtn.prop('disabled', false).html('<i class="fas fa-check-circle"></i> Redeem Voucher');
            return;
        }

        // Find voucher by code to get its ID
        const vouchers = await window.FirebaseVouchers.getVouchers({ code: voucherCode });
        if (vouchers.length === 0) {
            showMessage('Voucher not found', 'danger');
            submitBtn.prop('disabled', false).html('<i class="fas fa-check-circle"></i> Redeem Voucher');
            return;
        }

        const voucher = vouchers[0];

        // Redeem the voucher
        const redeemedVoucher = await window.FirebaseVouchers.redeemVoucher(
            voucher.voucher_id,
            currentUser.username
        );

        // Success!
        showMessage(
            `Voucher redeemed successfully! $${parseFloat(redeemedVoucher.amount).toFixed(2)} has been added to your account.`,
            'success'
        );

        // Clear form
        $('#voucher_code').val('');

        // Update balance display
        await loadUserBalance();

    } catch (error) {
        console.error('Error redeeming voucher:', error);
        showMessage('Error redeeming voucher: ' + error.message, 'danger');
    } finally {
        submitBtn.prop('disabled', false).html('<i class="fas fa-check-circle"></i> Redeem Voucher');
    }
}

/**
 * Show message alert
 */
function showMessage(message, type) {
    const alert = $('#message-alert');
    alert.removeClass('alert-success alert-warning alert-danger alert-info')
          .addClass('alert alert-' + type)
          .html(`
            <div class="d-flex align-items-center">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'times-circle'} me-2"></i>
                <span>${message}</span>
            </div>
          `)
          .fadeIn();

    // Auto-hide after 5 seconds
    setTimeout(() => {
        alert.fadeOut();
    }, 5000);
}

