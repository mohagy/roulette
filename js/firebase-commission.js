/**
 * Firebase Commission Service
 * Handles commission-related operations using Firebase Realtime Database
 */

const FirebaseCommission = (function() {
    let database = null;
    const COMMISSION_PATH = 'commission';

    /**
     * Initialize Firebase Commission service
     */
    function initialize() {
        if (typeof firebase === 'undefined' || !window.firebaseDatabase) {
            console.error('Firebase not initialized. Make sure firebase-config.js is loaded first.');
            return false;
        }
        database = window.firebaseDatabase;
        console.log('FirebaseCommission initialized');
        return true;
    }

    /**
     * Get commission for a user
     * @param {string} userId - User ID (username)
     * @returns {Promise<object>} Commission data
     */
    async function getCommission(userId) {
        try {
            const snapshot = await database.ref(`${COMMISSION_PATH}/${userId}`).once('value');
            if (snapshot.exists()) {
                return snapshot.val();
            }
            return {
                total_commission: 0,
                total_bets: 0,
                transactions: [],
                last_updated: null
            };
        } catch (error) {
            console.error('Error getting commission:', error);
            throw error;
        }
    }

    /**
     * Calculate commission from a transaction
     * @param {object} transactionData - Transaction data
     * @param {number} commissionRate - Commission rate (default 0.05 = 5%)
     * @returns {Promise<number>} Commission amount
     */
    async function calculateCommission(transactionData, commissionRate = 0.05) {
        try {
            // Commission is typically calculated from bet transactions
            if (transactionData.transaction_type === 'bet' && transactionData.amount < 0) {
                const betAmount = Math.abs(transactionData.amount);
                return betAmount * commissionRate;
            }
            return 0;
        } catch (error) {
            console.error('Error calculating commission:', error);
            throw error;
        }
    }

    /**
     * Update commission for a user
     * @param {string} userId - User ID
     * @param {number} amount - Commission amount to add
     * @param {object} transactionData - Related transaction data
     * @returns {Promise<object>} Updated commission data
     */
    async function updateCommission(userId, amount, transactionData = {}) {
        try {
            const commissionRef = database.ref(`${COMMISSION_PATH}/${userId}`);
            const snapshot = await commissionRef.once('value');
            
            let currentCommission = {
                total_commission: 0,
                total_bets: 0,
                transactions: [],
                last_updated: new Date().toISOString()
            };

            if (snapshot.exists()) {
                currentCommission = snapshot.val();
            }

            // Update commission
            currentCommission.total_commission = parseFloat(currentCommission.total_commission || 0) + parseFloat(amount);
            currentCommission.total_bets = parseFloat(currentCommission.total_bets || 0) + Math.abs(transactionData.amount || 0);
            currentCommission.last_updated = new Date().toISOString();

            // Add transaction record
            if (!currentCommission.transactions) {
                currentCommission.transactions = [];
            }
            currentCommission.transactions.push({
                amount: amount,
                transaction_id: transactionData.transaction_id || null,
                timestamp: new Date().toISOString(),
                description: transactionData.description || 'Commission from bet'
            });

            // Keep only last 100 transactions
            if (currentCommission.transactions.length > 100) {
                currentCommission.transactions = currentCommission.transactions.slice(-100);
            }

            await commissionRef.set(currentCommission);
            
            console.log('Commission updated:', currentCommission);
            return currentCommission;
        } catch (error) {
            console.error('Error updating commission:', error);
            throw error;
        }
    }

    /**
     * Get commission history for a user
     * @param {string} userId - User ID
     * @param {number} days - Number of days to retrieve (default 30)
     * @returns {Promise<Array>} Commission history
     */
    async function getCommissionHistory(userId, days = 30) {
        try {
            const commission = await getCommission(userId);
            const transactions = commission.transactions || [];
            
            // Filter by date range
            const cutoffDate = new Date();
            cutoffDate.setDate(cutoffDate.getDate() - days);
            
            return transactions.filter(t => {
                const transactionDate = new Date(t.timestamp);
                return transactionDate >= cutoffDate;
            }).sort((a, b) => {
                return new Date(b.timestamp) - new Date(a.timestamp);
            });
        } catch (error) {
            console.error('Error getting commission history:', error);
            throw error;
        }
    }

    /**
     * Get daily commission summary
     * @param {string} userId - User ID
     * @returns {Promise<object>} Daily summary
     */
    async function getDailySummary(userId) {
        try {
            const history = await getCommissionHistory(userId, 1);
            const today = new Date().toISOString().split('T')[0];
            
            const todayTransactions = history.filter(t => {
                const transactionDate = new Date(t.timestamp).toISOString().split('T')[0];
                return transactionDate === today;
            });

            return {
                date: today,
                total_commission: todayTransactions.reduce((sum, t) => sum + parseFloat(t.amount || 0), 0),
                total_bets: todayTransactions.length,
                transactions: todayTransactions
            };
        } catch (error) {
            console.error('Error getting daily summary:', error);
            throw error;
        }
    }

    return {
        initialize: initialize,
        getCommission: getCommission,
        calculateCommission: calculateCommission,
        updateCommission: updateCommission,
        getCommissionHistory: getCommissionHistory,
        getDailySummary: getDailySummary,
        isInitialized: () => database !== null
    };
})();

// Auto-initialize if Firebase is available
if (typeof firebase !== 'undefined' && window.firebaseDatabase) {
    FirebaseCommission.initialize();
} else {
    setTimeout(() => {
        if (typeof firebase !== 'undefined' && window.firebaseDatabase) {
            FirebaseCommission.initialize();
        }
    }, 1000);
}

