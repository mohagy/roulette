/**
 * Straight Up Bet Confirmation
 * This script adds a confirmation dialog for straight up bets over $1600
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing straight up bet confirmation for bets over $1600...');

    // Add CSS for the confirmation dialog
    addConfirmationDialogStyles();

    // Override the addBet method in betTracker to add confirmation for straight up bets
    overrideBetTrackerAddBet();

    // Override the updateBetStake method in betTracker to add confirmation for straight up bets
    overrideBetTrackerUpdateBetStake();

    // Override the updateAllBetStakes method in betTracker to add confirmation for straight up bets
    overrideBetTrackerUpdateAllBetStakes();

    console.log('Straight up bet confirmation initialized');
});

/**
 * Add CSS styles for the confirmation dialog
 */
function addConfirmationDialogStyles() {
    const style = document.createElement('style');
    style.textContent = `
        .straight-up-confirmation-dialog {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        .confirmation-content {
            background-color: #2c3e50;
            border: 2px solid #f39c12;
            border-radius: 10px;
            padding: 20px;
            width: 400px;
            max-width: 90%;
            color: white;
            text-align: center;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
        }
        .confirmation-content h3 {
            color: #f39c12;
            margin-top: 0;
            font-size: 24px;
        }
        .confirmation-buttons {
            display: flex;
            justify-content: space-around;
            margin-top: 20px;
        }
        .confirmation-buttons button {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
        }
        .confirm-yes {
            background-color: #2ecc71;
            color: white;
        }
        .confirm-yes:hover {
            background-color: #27ae60;
        }
        .confirm-no {
            background-color: #e74c3c;
            color: white;
        }
        .confirm-no:hover {
            background-color: #c0392b;
        }
    `;
    document.head.appendChild(style);
    console.log('Added confirmation dialog styles');
}

/**
 * Override the addBet method in betTracker to add confirmation for straight up bets
 */
function overrideBetTrackerAddBet() {
    // Store the original addBet method
    const originalAddBet = betTracker.addBet;

    // Override the addBet method
    betTracker.addBet = function(element, amount) {
        const betType = this.getBetType(element);
        const betInfo = this.getBetInfo(element, betType);
        const multiplier = this.getMultiplier(betType);
        const potentialReturn = amount * multiplier;

        // Check if this is a straight up bet with amount over 1600
        if (betType === 'straight' && amount > 1600) {
            // Create a confirmation dialog
            showConfirmationDialog(
                betInfo,
                amount,
                potentialReturn,
                () => {
                    // Yes callback - proceed with adding the bet
                    originalAddBet.call(this, element, amount);
                },
                () => {
                    // No callback - refund the bet amount
                    cashSum = cashSum + amount;
                    $(".cash-total").html(`${cashSum.toFixed(2)}`);

                    // Reduce the bet sum
                    betSum = betSum - amount;
                    $(".bet-total").html(`${betSum.toFixed(2)}`);

                    // Remove any chip that might have been placed
                    if (element) {
                        element.innerHTML = '';
                    }
                }
            );
        } else {
            // For all other bets, proceed normally
            originalAddBet.call(this, element, amount);
        }
    };

    console.log('Overrode betTracker.addBet method');
}

/**
 * Override the updateBetStake method in betTracker to add confirmation for straight up bets
 */
function overrideBetTrackerUpdateBetStake() {
    // Store the original updateBetStake method
    const originalUpdateBetStake = betTracker.updateBetStake;

    // Override the updateBetStake method
    betTracker.updateBetStake = function(betId, newAmount) {
        const betIndex = this.bets.findIndex(bet => bet.id === betId);

        if (betIndex !== -1) {
            const bet = this.bets[betIndex];
            const originalAmount = bet.amount;

            // Calculate difference for cash adjustment
            const difference = newAmount - originalAmount;

            // Check if we have enough cash
            if (difference > 0 && cashSum < difference) {
                return false; // Not enough cash
            }

            // Check if this is a straight up bet with amount over 1600
            if (bet.type === 'straight' && newAmount > 1600) {
                // Calculate new potential return
                const newPotentialReturn = newAmount * bet.multiplier;

                // Check if we're in the update stake modal
                const updateStakeModal = document.querySelector('.update-stake-modal');
                if (updateStakeModal && updateStakeModal.classList.contains('visible')) {
                    // Close the update stake modal first
                    updateStakeModal.classList.remove('visible');

                    // Then show the confirmation dialog
                    setTimeout(() => {
                        showConfirmationDialog(
                            bet.description,
                            newAmount,
                            newPotentialReturn,
                            () => {
                                // Yes callback - proceed with updating the bet
                                originalUpdateBetStake.call(this, betId, newAmount);
                            },
                            () => {
                                // No callback - don't update the bet
                                console.log('Bet update cancelled by user');
                            }
                        );
                    }, 100);
                } else {
                    // Not in update stake modal, show confirmation directly
                    showConfirmationDialog(
                        bet.description,
                        newAmount,
                        newPotentialReturn,
                        () => {
                            // Yes callback - proceed with updating the bet
                            originalUpdateBetStake.call(this, betId, newAmount);
                        },
                        () => {
                            // No callback - don't update the bet
                            console.log('Bet update cancelled by user');
                        }
                    );
                }

                return true; // Return true to indicate the update is being processed
            }

            // For all other bets, proceed normally
            return originalUpdateBetStake.call(this, betId, newAmount);
        }

        return false;
    };

    console.log('Overrode betTracker.updateBetStake method');
}

/**
 * Override the updateAllBetStakes method in betTracker to add confirmation for straight up bets
 */
function overrideBetTrackerUpdateAllBetStakes() {
    // Store the original updateAllBetStakes method
    const originalUpdateAllBetStakes = betTracker.updateAllBetStakes;

    // Override the updateAllBetStakes method
    betTracker.updateAllBetStakes = function(newAmount) {
        if (this.bets.length === 0) return false;

        // Calculate the total difference in stake amount
        let totalDifference = 0;
        this.bets.forEach(bet => {
            totalDifference += (newAmount - bet.amount);
        });

        // Check if we have enough cash for all updates
        if (totalDifference > 0 && cashSum < totalDifference) {
            alert("Not enough cash to update all bets to this stake amount.");
            return false;
        }

        // Check if any straight up bets will exceed 1600
        let hasStraightUpBetsOver1600 = false;
        let highestPotentialPayout = 0;
        let straightUpBetDescription = '';

        this.bets.forEach(bet => {
            if (bet.type === 'straight' && newAmount > 1600) {
                hasStraightUpBetsOver1600 = true;
                const potentialReturn = newAmount * bet.multiplier;
                if (potentialReturn > highestPotentialPayout) {
                    highestPotentialPayout = potentialReturn;
                    straightUpBetDescription = bet.description;
                }
            }
        });

        // If there are straight up bets over 1600, show confirmation dialog
        if (hasStraightUpBetsOver1600) {
            // Create a confirmation dialog with special message for multiple bets
            showConfirmationDialog(
                straightUpBetDescription,
                newAmount,
                highestPotentialPayout,
                () => {
                    // Yes callback - proceed with updating all bets
                    originalUpdateAllBetStakes.call(this, newAmount);
                },
                () => {
                    // No callback - don't update the bets
                    console.log('Bet update cancelled by user');
                },
                true // This is a multi-bet update
            );

            return true; // Return true to indicate the update is being processed
        }

        // For all other cases, proceed normally
        return originalUpdateAllBetStakes.call(this, newAmount);
    };

    console.log('Overrode betTracker.updateAllBetStakes method');
}

/**
 * Show a confirmation dialog for high payout bets
 */
function showConfirmationDialog(betDescription, amount, potentialReturn, yesCallback, noCallback, isMultiBet = false) {
    // Create the confirmation dialog
    const confirmDialog = document.createElement('div');
    confirmDialog.className = 'straight-up-confirmation-dialog';

    // Create the content based on whether this is a multi-bet update or not
    let dialogContent = '';

    if (isMultiBet) {
        dialogContent = `
            <div class="confirmation-content">
                <h3>High Payout Warning</h3>
                <p>You are updating all bets to $${amount.toFixed(2)}, including straight up bets.</p>
                <p>The highest potential payout would be $${potentialReturn.toFixed(2)} for ${betDescription}.</p>
                <p>Are you sure you want to update all bets?</p>
                <div class="confirmation-buttons">
                    <button class="confirm-yes">Yes, Update All Bets</button>
                    <button class="confirm-no">Cancel</button>
                </div>
            </div>
        `;
    } else {
        dialogContent = `
            <div class="confirmation-content">
                <h3>High Payout Warning</h3>
                <p>You are placing a straight up bet of $${amount.toFixed(2)} on ${betDescription}.</p>
                <p>If this bet wins, the payout will be $${potentialReturn.toFixed(2)}.</p>
                <p>Are you sure you want to place this bet?</p>
                <div class="confirmation-buttons">
                    <button class="confirm-yes">Yes, Place Bet</button>
                    <button class="confirm-no">Cancel</button>
                </div>
            </div>
        `;
    }

    confirmDialog.innerHTML = dialogContent;
    document.body.appendChild(confirmDialog);

    // Handle button clicks
    const yesButton = confirmDialog.querySelector('.confirm-yes');
    const noButton = confirmDialog.querySelector('.confirm-no');

    yesButton.addEventListener('click', () => {
        // Remove the dialog
        document.body.removeChild(confirmDialog);

        // Call the yes callback
        if (typeof yesCallback === 'function') {
            yesCallback();
        }
    });

    noButton.addEventListener('click', () => {
        // Remove the dialog
        document.body.removeChild(confirmDialog);

        // Call the no callback
        if (typeof noCallback === 'function') {
            noCallback();
        }
    });

    console.log('Showing confirmation dialog for bet:', betDescription, 'amount:', amount);
}
