/**
 * Update Stake Limits
 * This script updates the stake limits to be between $100 and $50000
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('Updating stake limits to $100-$50000...');

    // Update the minimum and maximum bet limits
    window.minBet = 100;
    window.maxBet = 50000;
    console.log('Bet limits set to:', { min: window.minBet, max: window.maxBet });

    // Update the min-max bet display in the UI
    const minBetDisplay = document.querySelector('.min-bet');
    if (minBetDisplay) {
        minBetDisplay.innerHTML = '<span class="text-color">MIN:</span> $100.00';
        console.log('Updated min bet display in UI');
    }

    const maxBetDisplay = document.querySelector('.max-bet');
    if (maxBetDisplay) {
        maxBetDisplay.innerHTML = '<span class="text-color">MAX:</span> $50000.00';
        console.log('Updated max bet display in UI');
    }

    // Update the stake input min and max values
    const stakeInput = document.getElementById('global-stake-input');
    if (stakeInput) {
        stakeInput.setAttribute('min', '100');
        stakeInput.setAttribute('max', '50000');
        stakeInput.setAttribute('placeholder', '100');
        stakeInput.value = '100';
        console.log('Updated stake input min and max values');
    }

    // Update the update stake modal
    const updateStakeInput = document.getElementById('update-stake-input');
    if (updateStakeInput) {
        updateStakeInput.setAttribute('min', '100');
        updateStakeInput.setAttribute('max', '50000');
        updateStakeInput.setAttribute('placeholder', 'Enter new amount (100-50000)');
        console.log('Updated update stake input min and max values');
    }

    // Update the error message in the update stake modal
    const updateStakeError = document.querySelector('.update-stake-error');
    if (updateStakeError) {
        updateStakeError.textContent = 'Amount must be between $100 and $50000';
        console.log('Updated update stake error message');
    }

    // Update the max bet alert message
    const maxBetAlert = document.querySelector('.alert-max-bet .alert-message');
    if (maxBetAlert) {
        maxBetAlert.textContent = 'YOU SHOULD NOT EXCEED MAXIMUM BET OF $50000';
        console.log('Updated max bet alert message');
    }

    // Set default active chip number to 100 instead of 5
    window.activeChipNumber = 100;
    console.log('Set default active chip number to:', window.activeChipNumber);

    // Override the stake input handler to enforce new limits
    const originalStakeInputHandler = window.initStakeInputHandler;
    if (typeof window.initStakeInputHandler === 'function') {
        window.initStakeInputHandler = function() {
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

                            // Get the maximum bet limit
                            const maxBetLimit = window.maxBet || 50000;

                            // Validate the amount with new limits
                            if (amount >= 100 && amount <= maxBetLimit) {
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
                                // Show error message with new limits
                                alert(`Stake amount must be between $100 and $${maxBetLimit}`);
                            }
                        }
                    });

                    console.log('Stake input handler initialized with new limits');
                }
            }, 500);
        };

        // Call the new handler
        window.initStakeInputHandler();
    }

    console.log('Stake limits update completed successfully');
});
