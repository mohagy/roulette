/**
 * Firebase Transactions Service
 * Handles all transaction-related operations using Firebase Realtime Database
 */

const FirebaseTransactions = (function() {
    let database = null;
    const TRANSACTIONS_PATH = 'transactions';
    const BETTING_SLIPS_PATH = 'bettingSlips';

    /**
     * Initialize Firebase Transactions service
     */
    function initialize() {
        if (typeof firebase === 'undefined' || !window.firebaseDatabase) {
            console.error('Firebase not initialized. Make sure firebase-config.js is loaded first.');
            return false;
        }
        database = window.firebaseDatabase;
        console.log('FirebaseTransactions initialized');
        return true;
    }

    /**
     * Get all transactions for a user
     * @param {string} userId - User ID (username)
     * @param {object} filters - Optional filters (limit, orderBy, startDate, endDate)
     * @returns {Promise<Array>} Array of transactions
     */
    async function getTransactions(userId, filters = {}) {
        try {
            let query = database.ref(TRANSACTIONS_PATH)
                .orderByChild('user_id')
                .equalTo(userId);

            const snapshot = await query.once('value');
            let transactions = [];

            if (snapshot.exists()) {
                snapshot.forEach((child) => {
                    const transaction = child.val();
                    transaction.transaction_id = child.key;
                    transactions.push(transaction);
                });
            }

            // Apply filters
            if (filters.startDate) {
                transactions = transactions.filter(t => 
                    new Date(t.timestamp || t.created_at) >= new Date(filters.startDate)
                );
            }
            if (filters.endDate) {
                transactions = transactions.filter(t => 
                    new Date(t.timestamp || t.created_at) <= new Date(filters.endDate)
                );
            }
            if (filters.transactionType) {
                transactions = transactions.filter(t => 
                    t.transaction_type === filters.transactionType
                );
            }

            // Sort by timestamp (newest first)
            transactions.sort((a, b) => {
                const timeA = new Date(a.timestamp || a.created_at || 0).getTime();
                const timeB = new Date(b.timestamp || b.created_at || 0).getTime();
                return timeB - timeA;
            });

            // Apply limit
            if (filters.limit) {
                transactions = transactions.slice(0, filters.limit);
            }

            return transactions;
        } catch (error) {
            console.error('Error getting transactions:', error);
            throw error;
        }
    }

    /**
     * Get a single transaction by ID
     * @param {string} transactionId
     * @returns {Promise<object|null>} Transaction data or null
     */
    async function getTransaction(transactionId) {
        try {
            const snapshot = await database.ref(`${TRANSACTIONS_PATH}/${transactionId}`).once('value');
            if (snapshot.exists()) {
                const transaction = snapshot.val();
                transaction.transaction_id = transactionId;
                return transaction;
            }
            return null;
        } catch (error) {
            console.error('Error getting transaction:', error);
            throw error;
        }
    }

    /**
     * Create a new transaction
     * @param {object} transactionData - Transaction data
     * @returns {Promise<string>} Transaction ID
     */
    async function createTransaction(transactionData) {
        try {
            const transaction = {
                user_id: transactionData.user_id,
                amount: parseFloat(transactionData.amount),
                balance_after: parseFloat(transactionData.balance_after),
                transaction_type: transactionData.transaction_type || 'bet',
                reference_id: transactionData.reference_id || null,
                description: transactionData.description || '',
                timestamp: new Date().toISOString(),
                created_at: new Date().toISOString()
            };

            const newRef = database.ref(TRANSACTIONS_PATH).push();
            await newRef.set(transaction);
            
            console.log('Transaction created:', newRef.key);
            return newRef.key;
        } catch (error) {
            console.error('Error creating transaction:', error);
            throw error;
        }
    }

    /**
     * Get betting slips for a user
     * @param {string} userId - User ID
     * @param {object} filters - Optional filters
     * @returns {Promise<Array>} Array of betting slips
     */
    async function getBettingSlips(userId, filters = {}) {
        try {
            let query = database.ref(BETTING_SLIPS_PATH)
                .orderByChild('player_id')
                .equalTo(userId);

            const snapshot = await query.once('value');
            let slips = [];

            if (snapshot.exists()) {
                snapshot.forEach((child) => {
                    const slip = child.val();
                    slip.slip_id = child.key;
                    slips.push(slip);
                });
            }

            // Apply filters
            if (filters.status) {
                slips = slips.filter(s => s.status === filters.status);
            }
            if (filters.drawNumber) {
                slips = slips.filter(s => s.drawNumber === filters.drawNumber);
            }

            // Sort by timestamp (newest first)
            slips.sort((a, b) => {
                const timeA = new Date(a.created_at || a.timestamp || 0).getTime();
                const timeB = new Date(b.created_at || b.timestamp || 0).getTime();
                return timeB - timeA;
            });

            if (filters.limit) {
                slips = slips.slice(0, filters.limit);
            }

            return slips;
        } catch (error) {
            console.error('Error getting betting slips:', error);
            throw error;
        }
    }

    /**
     * Get transaction statistics for a user
     * @param {string} userId
     * @returns {Promise<object>} Statistics object
     */
    async function getTransactionStats(userId) {
        try {
            const transactions = await getTransactions(userId);
            
            const stats = {
                total: transactions.length,
                total_amount: 0,
                total_bets: 0,
                total_wins: 0,
                total_losses: 0,
                by_type: {}
            };

            transactions.forEach(transaction => {
                stats.total_amount += Math.abs(transaction.amount || 0);
                
                const type = transaction.transaction_type || 'other';
                if (!stats.by_type[type]) {
                    stats.by_type[type] = { count: 0, total: 0 };
                }
                stats.by_type[type].count++;
                stats.by_type[type].total += Math.abs(transaction.amount || 0);

                if (type === 'bet') {
                    stats.total_bets++;
                } else if (type === 'win') {
                    stats.total_wins++;
                } else if (type === 'loss') {
                    stats.total_losses++;
                }
            });

            return stats;
        } catch (error) {
            console.error('Error getting transaction stats:', error);
            throw error;
        }
    }

    /**
     * Listen to transactions in real-time
     * @param {string} userId
     * @param {function} callback
     * @returns {function} Unsubscribe function
     */
    function listenToTransactions(userId, callback) {
        const query = database.ref(TRANSACTIONS_PATH)
            .orderByChild('user_id')
            .equalTo(userId);

        const listener = query.on('value', (snapshot) => {
            const transactions = [];
            if (snapshot.exists()) {
                snapshot.forEach((child) => {
                    const transaction = child.val();
                    transaction.transaction_id = child.key;
                    transactions.push(transaction);
                });
            }
            callback(transactions);
        });

        // Return unsubscribe function
        return () => {
            query.off('value', listener);
        };
    }

    return {
        initialize: initialize,
        getTransactions: getTransactions,
        getTransaction: getTransaction,
        createTransaction: createTransaction,
        getBettingSlips: getBettingSlips,
        getTransactionStats: getTransactionStats,
        listenToTransactions: listenToTransactions,
        isInitialized: () => database !== null
    };
})();

// Auto-initialize if Firebase is available
if (typeof firebase !== 'undefined' && window.firebaseDatabase) {
    FirebaseTransactions.initialize();
} else {
    setTimeout(() => {
        if (typeof firebase !== 'undefined' && window.firebaseDatabase) {
            FirebaseTransactions.initialize();
        }
    }, 1000);
}

