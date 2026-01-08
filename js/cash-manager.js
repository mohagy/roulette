/**
 * Cash Manager
 * Handles cash balance operations for the betting system
 */

const CashManager = (function() {
    // Private variables
    let cashBalance = 0;
    let isInitialized = false;
    let updateCallbacks = [];

    // Private methods
    const formatCash = (amount) => {
        return parseFloat(amount).toFixed(2);
    };

    const updateCashDisplay = () => {
        // Update the cash display in the UI
        $('.cash-total').html(`${formatCash(cashBalance)}`);

        // Call any registered callbacks
        updateCallbacks.forEach(callback => callback(cashBalance));
    };

    const loadCashBalance = () => {
        return new Promise((resolve, reject) => {
            // Fetch cash balance from server
            fetch('get_cash_balance.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        cashBalance = parseFloat(data.cash_balance);
                        updateCashDisplay();
                        console.log('Cash balance loaded:', cashBalance);
                        resolve(cashBalance);
                    } else {
                        console.error('Error loading cash balance:', data.message);
                        reject(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error fetching cash balance:', error);
                    reject(error);
                });
        });
    };

    const updateCashBalance = (amount, transactionType, referenceId, description) => {
        return new Promise((resolve, reject) => {
            // Validate amount
            if (isNaN(amount) || amount === 0) {
                reject('Invalid amount');
                return;
            }

            // Prepare data for the API call
            const data = {
                amount: amount,
                transaction_type: transactionType || 'bet',
                reference_id: referenceId || null,
                description: description || null
            };

            // Send update request to server
            fetch('update_cash_balance.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Update local cash balance
                    cashBalance = parseFloat(data.new_balance);
                    updateCashDisplay();
                    console.log(`Cash balance updated: ${data.previous_balance} + ${data.amount} = ${data.new_balance}`);
                    resolve(cashBalance);
                } else {
                    console.error('Error updating cash balance:', data.message);
                    reject(data.message);
                }
            })
            .catch(error => {
                console.error('Error in API call:', error);
                reject(error);
            });
        });
    };

    // Synchronous version of updateCashBalance for use during page unload
    const updateCashBalanceSync = (amount, transactionType, referenceId, description) => {
        // Validate amount
        if (isNaN(amount) || amount === 0) {
            console.error('Invalid amount for sync cash update');
            return false;
        }

        // Prepare data for the API call
        const data = {
            amount: amount,
            transaction_type: transactionType || 'bet',
            reference_id: referenceId || null,
            description: description || null
        };

        try {
            // Use synchronous XMLHttpRequest (deprecated but necessary for beforeunload)
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'update_cash_balance.php', false); // false makes it synchronous
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.send(JSON.stringify(data));

            if (xhr.status === 200) {
                const response = JSON.parse(xhr.responseText);
                if (response.status === 'success') {
                    // Update local cash balance
                    cashBalance = parseFloat(response.new_balance);
                    // Don't call updateCashDisplay() as the page is unloading
                    console.log(`Cash balance updated synchronously: ${response.previous_balance} + ${response.amount} = ${response.new_balance}`);
                    return true;
                } else {
                    console.error('Error updating cash balance synchronously:', response.message);
                    return false;
                }
            } else {
                console.error('HTTP error in synchronous cash update:', xhr.status);
                return false;
            }
        } catch (error) {
            console.error('Exception in synchronous cash update:', error);
            return false;
        }
    };

    // Public API
    return {
        // Initialize the cash manager
        init: function() {
            if (isInitialized) return Promise.resolve(cashBalance);

            return loadCashBalance()
                .then(balance => {
                    isInitialized = true;
                    return balance;
                });
        },

        // Get current cash balance
        getBalance: function() {
            return cashBalance;
        },

        // Refresh cash balance from server
        refreshBalance: function() {
            return loadCashBalance();
        },

        // Add cash (positive amount)
        addCash: function(amount, transactionType, referenceId, description) {
            // Ensure amount is positive
            const positiveAmount = Math.abs(parseFloat(amount));
            return updateCashBalance(positiveAmount, transactionType, referenceId, description);
        },

        // Add cash synchronously (for use during page unload)
        addCashSync: function(amount, transactionType, referenceId, description) {
            // Ensure amount is positive
            const positiveAmount = Math.abs(parseFloat(amount));
            return updateCashBalanceSync(positiveAmount, transactionType, referenceId, description);
        },

        // Remove cash (negative amount)
        removeCash: function(amount, transactionType, referenceId, description) {
            // Ensure amount is negative
            const negativeAmount = -Math.abs(parseFloat(amount));
            return updateCashBalance(negativeAmount, transactionType, referenceId, description);
        },

        // Remove cash synchronously (for use during page unload)
        removeCashSync: function(amount, transactionType, referenceId, description) {
            // Ensure amount is negative
            const negativeAmount = -Math.abs(parseFloat(amount));
            return updateCashBalanceSync(negativeAmount, transactionType, referenceId, description);
        },

        // Register callback for balance updates
        onBalanceUpdate: function(callback) {
            if (typeof callback === 'function') {
                updateCallbacks.push(callback);
            }
        },

        // Format cash amount for display
        formatCash: formatCash
    };
})();

// Initialize cash manager when document is ready
$(document).ready(function() {
    // Initialize cash manager after checking authentication
    setTimeout(() => {
        CashManager.init()
            .then(balance => {
                console.log('Cash manager initialized with balance:', balance);
            })
            .catch(error => {
                console.error('Failed to initialize cash manager:', error);
            });
    }, 500); // Short delay to ensure auth check completes first
});
