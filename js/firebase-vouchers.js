/**
 * Firebase Vouchers Service
 * Handles voucher operations using Firebase Realtime Database
 */

const FirebaseVouchers = (function() {
    let database = null;
    const VOUCHERS_PATH = 'vouchers';

    /**
     * Initialize Firebase Vouchers service
     */
    function initialize() {
        if (typeof firebase === 'undefined' || !window.firebaseDatabase) {
            console.error('Firebase not initialized. Make sure firebase-config.js is loaded first.');
            return false;
        }
        database = window.firebaseDatabase;
        console.log('FirebaseVouchers initialized');
        return true;
    }

    /**
     * Get all vouchers
     * @param {object} filters - Optional filters
     * @returns {Promise<Array>} Array of vouchers
     */
    async function getVouchers(filters = {}) {
        try {
            const snapshot = await database.ref(VOUCHERS_PATH).once('value');
            let vouchers = [];

            if (snapshot.exists()) {
                snapshot.forEach((child) => {
                    const voucher = child.val();
                    voucher.voucher_id = child.key;
                    vouchers.push(voucher);
                });
            }

            // Apply filters
            if (filters.status) {
                vouchers = vouchers.filter(v => v.status === filters.status);
            }
            if (filters.code) {
                vouchers = vouchers.filter(v => v.code === filters.code);
            }
            if (filters.redeemed_by) {
                vouchers = vouchers.filter(v => v.redeemed_by === filters.redeemed_by);
            }

            // Sort by created date (newest first)
            vouchers.sort((a, b) => {
                const timeA = new Date(a.created_at || a.timestamp || 0).getTime();
                const timeB = new Date(b.created_at || b.timestamp || 0).getTime();
                return timeB - timeA;
            });

            if (filters.limit) {
                vouchers = vouchers.slice(0, filters.limit);
            }

            return vouchers;
        } catch (error) {
            console.error('Error getting vouchers:', error);
            throw error;
        }
    }

    /**
     * Get a voucher by code
     * @param {string} code - Voucher code
     * @returns {Promise<object|null>} Voucher data or null
     */
    async function getVoucherByCode(code) {
        try {
            const snapshot = await database.ref(VOUCHERS_PATH)
                .orderByChild('code')
                .equalTo(code)
                .once('value');

            if (snapshot.exists()) {
                let voucher = null;
                snapshot.forEach((child) => {
                    voucher = child.val();
                    voucher.voucher_id = child.key;
                });
                return voucher;
            }
            return null;
        } catch (error) {
            console.error('Error getting voucher by code:', error);
            throw error;
        }
    }

    /**
     * Create a new voucher
     * @param {object} voucherData - Voucher data
     * @returns {Promise<string>} Voucher ID
     */
    async function createVoucher(voucherData) {
        try {
            const voucher = {
                code: voucherData.code,
                amount: parseFloat(voucherData.amount),
                status: 'active',
                created_by: voucherData.created_by || null,
                created_at: new Date().toISOString(),
                expires_at: voucherData.expires_at || null,
                redeemed_by: null,
                redeemed_at: null
            };

            const newRef = database.ref(VOUCHERS_PATH).push();
            await newRef.set(voucher);
            
            console.log('Voucher created:', newRef.key);
            return newRef.key;
        } catch (error) {
            console.error('Error creating voucher:', error);
            throw error;
        }
    }

    /**
     * Redeem a voucher
     * @param {string} voucherId - Voucher ID
     * @param {string} userId - User ID redeeming the voucher
     * @returns {Promise<object>} Updated voucher data
     */
    async function redeemVoucher(voucherId, userId) {
        try {
            const voucherRef = database.ref(`${VOUCHERS_PATH}/${voucherId}`);
            const snapshot = await voucherRef.once('value');

            if (!snapshot.exists()) {
                throw new Error('Voucher not found');
            }

            const voucher = snapshot.val();

            if (voucher.status !== 'active') {
                throw new Error('Voucher is not active');
            }

            if (voucher.expires_at && new Date(voucher.expires_at) < new Date()) {
                throw new Error('Voucher has expired');
            }

            // Update voucher
            const updates = {
                status: 'redeemed',
                redeemed_by: userId,
                redeemed_at: new Date().toISOString()
            };

            await voucherRef.update(updates);

            // Update user's cash balance
            if (window.FirebaseService && window.FirebaseService.isOnline()) {
                const userRef = database.ref(`users/${userId}/cash_balance`);
                const balanceSnapshot = await userRef.once('value');
                const currentBalance = balanceSnapshot.val() || 0;
                const newBalance = parseFloat(currentBalance) + parseFloat(voucher.amount);
                await userRef.set(newBalance);

                // Create transaction record
                if (window.FirebaseTransactions) {
                    await window.FirebaseTransactions.createTransaction({
                        user_id: userId,
                        amount: voucher.amount,
                        balance_after: newBalance,
                        transaction_type: 'voucher_redemption',
                        reference_id: voucherId,
                        description: `Voucher redemption: ${voucher.code}`
                    });
                }
            }

            return {
                ...voucher,
                ...updates,
                voucher_id: voucherId
            };
        } catch (error) {
            console.error('Error redeeming voucher:', error);
            throw error;
        }
    }

    /**
     * Check voucher status
     * @param {string} code - Voucher code
     * @returns {Promise<object>} Voucher status info
     */
    async function checkVoucherStatus(code) {
        try {
            const voucher = await getVoucherByCode(code);
            
            if (!voucher) {
                return {
                    valid: false,
                    message: 'Voucher not found'
                };
            }

            if (voucher.status !== 'active') {
                return {
                    valid: false,
                    message: 'Voucher has already been redeemed',
                    voucher: voucher
                };
            }

            if (voucher.expires_at && new Date(voucher.expires_at) < new Date()) {
                return {
                    valid: false,
                    message: 'Voucher has expired',
                    voucher: voucher
                };
            }

            return {
                valid: true,
                message: 'Voucher is valid',
                voucher: voucher
            };
        } catch (error) {
            console.error('Error checking voucher status:', error);
            throw error;
        }
    }

    return {
        initialize: initialize,
        getVouchers: getVouchers,
        getVoucherByCode: getVoucherByCode,
        createVoucher: createVoucher,
        redeemVoucher: redeemVoucher,
        checkVoucherStatus: checkVoucherStatus,
        isInitialized: () => database !== null
    };
})();

// Auto-initialize if Firebase is available
if (typeof firebase !== 'undefined' && window.firebaseDatabase) {
    FirebaseVouchers.initialize();
} else {
    setTimeout(() => {
        if (typeof firebase !== 'undefined' && window.firebaseDatabase) {
            FirebaseVouchers.initialize();
        }
    }, 1000);
}

