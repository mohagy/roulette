/**
 * Global Stake Keypad
 * Adds a floating numeric keypad for the global stake input
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize the global stake keypad
    initGlobalStakeKeypad();
});

/**
 * Initialize the global stake keypad
 */
function initGlobalStakeKeypad() {
    // Wait for the global stake input to be available
    const checkForStakeInput = setInterval(function() {
        const stakeInput = document.getElementById('global-stake-input');
        if (stakeInput) {
            clearInterval(checkForStakeInput);

            // Create the numeric keypad
            createNumericKeypad(stakeInput);

            // Add event listeners for the stake input
            stakeInput.addEventListener('focus', function() {
                showNumericKeypad(this);
            });

            // Add click event to show keypad
            stakeInput.addEventListener('click', function(e) {
                e.stopPropagation(); // Prevent event bubbling
                showNumericKeypad(this);
            });

            console.log('Global stake keypad initialized');
        }
    }, 500);
}

/**
 * Create the numeric keypad for the stake input
 */
function createNumericKeypad(inputElement) {
    // Check if keypad already exists
    if (document.getElementById('global-stake-keypad')) {
        return;
    }

    // Create the keypad container
    const keypad = document.createElement('div');
    keypad.className = 'numeric-keypad';
    keypad.id = 'global-stake-keypad';

    // Create the keys
    const keys = ['1', '2', '3', '4', '5', '6', '7', '8', '9', 'C', '0', '⏎'];

    // Add the keys to the keypad
    keys.forEach(key => {
        const keyElement = document.createElement('div');
        keyElement.className = 'numeric-key';

        // Add special classes for special keys
        if (key === 'C') {
            keyElement.className += ' key-clear';
            keyElement.textContent = 'Clear';
        } else if (key === '⏎') {
            keyElement.className += ' key-enter';
            keyElement.textContent = 'Enter';
        } else {
            keyElement.textContent = key;
        }

        // Add event listener for the key
        keyElement.addEventListener('click', function() {
            handleNumericKeyPress(key, inputElement);
        });

        // Add the key to the keypad
        keypad.appendChild(keyElement);
    });

    // Add the keypad to the input's parent
    inputElement.parentNode.appendChild(keypad);

    // Add event listener to close the keypad when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.stake-input-wrapper') && !e.target.closest('.numeric-keypad')) {
            hideNumericKeypad();
        }
    });
}

/**
 * Show the numeric keypad for the given input
 */
function showNumericKeypad(inputElement) {
    // Hide any visible keypads
    hideNumericKeypad();

    // Show the keypad for this input
    const keypad = inputElement.parentNode.querySelector('.numeric-keypad');
    if (keypad) {
        keypad.classList.add('visible');
    }
}

/**
 * Hide all numeric keypads
 */
function hideNumericKeypad() {
    document.querySelectorAll('.numeric-keypad').forEach(keypad => {
        keypad.classList.remove('visible');
    });
}

/**
 * Handle a numeric key press
 */
function handleNumericKeyPress(key, inputElement) {
    // Get the current value
    let currentValue = inputElement.value;

    // Handle the key press
    if (key === 'C') {
        // Clear the input
        inputElement.value = '';
    } else if (key === '⏎') {
        // Hide the keypad
        hideNumericKeypad();

        // Get the stake amount
        const amount = parseInt(inputElement.value);

        // Validate the amount
        const minBetLimit = window.minBet || 100;
        const maxBetLimit = window.maxBet || 5000;

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
    } else {
        // Add the number to the input
        inputElement.value = currentValue + key;

        // Trigger input event to update other inputs
        inputElement.dispatchEvent(new Event('input', { bubbles: true }));
    }
}
