/**
 * Stake Input Handler
 * Adds functionality to update all bets when pressing Enter in the stake input
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize the stake input handler
    initStakeInputHandler();
});

/**
 * Initialize the stake input handler
 */
function initStakeInputHandler() {
    // Wait for the global stake input to be available
    const checkForStakeInput = setInterval(function() {
        const stakeInput = document.getElementById('global-stake-input');
        if (stakeInput) {
            clearInterval(checkForStakeInput);

            // Add event listener for the Enter key
            stakeInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault(); // Prevent form submission

                    // Get the stake amount
                    const amount = parseInt(this.value);

                    // Get the minimum and maximum bet limits
                    const minBetLimit = window.minBet || 100;
                    const maxBetLimit = window.maxBet || 5000;

                    // Validate the amount
                    if (amount >= minBetLimit && amount <= maxBetLimit) {
                        // Update all bets with this amount
                        if (typeof betTracker !== 'undefined' && typeof betTracker.updateAllBetStakes === 'function') {
                            console.log('Updating all bets to amount:', amount);
                            betTracker.updateAllBetStakes(amount);

                            // Play sound if available
                            if (typeof playAudio !== 'undefined' && typeof selectSound !== 'undefined') {
                                if (playAudio) {
                                    selectSound.play();
                                }
                            }
                        } else {
                            console.error('betTracker or updateAllBetStakes function not found');
                        }
                    } else {
                        // Show error message
                        alert(`Stake amount must be between $${minBetLimit} and $${maxBetLimit}`);
                    }
                }
            });

            console.log('Stake input handler initialized');
        }
    }, 500);
}
