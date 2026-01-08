/**
 * Cancel Slip Button
 * Adds a floating button to cancel betting slips for the upcoming draw
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing Cancel Slip Button...');

    // Create the cancel slip button
    createCancelSlipButton();

    // Initialize the cancel slip functionality
    initCancelSlipFunctionality();
});

/**
 * Create the cancel slip button and add it to the DOM
 */
function createCancelSlipButton() {
    // Create the button element
    const cancelSlipButton = document.createElement('div');
    cancelSlipButton.id = 'cancel-slip-button';
    cancelSlipButton.className = 'floating-button cancel-slip-button';
    cancelSlipButton.innerHTML = `
        <div class="button-content">
            <i class="fas fa-times-circle"></i>
            <span>CANCEL SLIP</span>
        </div>
    `;

    // Add the button to the DOM
    document.body.appendChild(cancelSlipButton);

    console.log('Cancel slip button created');
}

/**
 * Initialize the cancel slip functionality
 */
function initCancelSlipFunctionality() {
    // Wait for the button to be available in the DOM
    const checkForButton = setInterval(function() {
        const cancelSlipButton = document.getElementById('cancel-slip-button');
        if (cancelSlipButton) {
            clearInterval(checkForButton);

            // Add click event listener
            cancelSlipButton.addEventListener('click', function() {
                console.log('Cancel slip button clicked');

                // Show the cancel slip modal
                showCancelSlipModal();

                // Play sound if available
                if (typeof playAudio !== 'undefined' && typeof selectSound !== 'undefined') {
                    if (playAudio) {
                        selectSound.play();
                    }
                }
            });

            console.log('Cancel slip button functionality initialized');
        }
    }, 500);
}

/**
 * Show the cancel slip modal
 */
function showCancelSlipModal() {
    // Check if the modal already exists
    let cancelSlipModal = document.getElementById('cancel-slip-modal');

    if (!cancelSlipModal) {
        // Create the modal
        cancelSlipModal = document.createElement('div');
        cancelSlipModal.id = 'cancel-slip-modal';
        cancelSlipModal.className = 'modal cancel-slip-modal';
        cancelSlipModal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Cancel Betting Slip</h2>
                    <span class="modal-close">&times;</span>
                </div>
                <div class="modal-body">
                    <p>Enter the betting slip ID to cancel:</p>
                    <div class="input-group">
                        <input type="text" id="slip-id-input" placeholder="Betting Slip ID" />
                    </div>
                    <div class="error-message" id="cancel-slip-error"></div>
                </div>
                <div class="modal-footer">
                    <button id="cancel-slip-confirm" class="btn btn-primary">Cancel Slip</button>
                </div>
            </div>
        `;

        // Add the modal to the DOM
        document.body.appendChild(cancelSlipModal);

        // Add event listeners
        const closeButton = cancelSlipModal.querySelector('.modal-close');
        closeButton.addEventListener('click', function() {
            cancelSlipModal.style.display = 'none';
        });

        const confirmButton = document.getElementById('cancel-slip-confirm');
        confirmButton.addEventListener('click', function() {
            const slipId = document.getElementById('slip-id-input').value.trim();
            if (slipId) {
                cancelBettingSlip(slipId);
            } else {
                document.getElementById('cancel-slip-error').textContent = 'Please enter a valid betting slip ID';
            }
        });

        // Close the modal when clicking outside of it
        window.addEventListener('click', function(event) {
            if (event.target === cancelSlipModal) {
                cancelSlipModal.style.display = 'none';
            }
        });
    }

    // Show the modal
    cancelSlipModal.style.display = 'block';

    // Focus on the input field
    setTimeout(function() {
        document.getElementById('slip-id-input').focus();
    }, 100);
}

/**
 * Cancel a betting slip
 * @param {string} slipId - The ID of the betting slip to cancel
 */
function cancelBettingSlip(slipId) {
    console.log('Cancelling betting slip:', slipId);

    // Get the current draw number
    const currentDrawNumber = getCurrentDrawNumber();

    // Make an AJAX request to cancel the slip
    fetch('php/cancel_betting_slip.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `slip_id=${encodeURIComponent(slipId)}&draw_number=${encodeURIComponent(currentDrawNumber)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            document.getElementById('cancel-slip-error').textContent = '';
            alert(data.message || 'Betting slip cancelled successfully');

            // Close the modal
            document.getElementById('cancel-slip-modal').style.display = 'none';

            // Update cash balance if available
            if (data.cashBalance && typeof CashManager !== 'undefined') {
                CashManager.updateBalance(data.cashBalance);
            }
        } else {
            // Show error message
            document.getElementById('cancel-slip-error').textContent = data.message || 'Failed to cancel betting slip';
        }
    })
    .catch(error => {
        console.error('Error cancelling betting slip:', error);
        document.getElementById('cancel-slip-error').textContent = 'An error occurred while cancelling the betting slip';
    });
}

/**
 * Get the current draw number
 * @returns {number} The current draw number
 */
function getCurrentDrawNumber() {
    // First try to get the next draw number from the UI
    const nextDrawElement = document.getElementById('next-draw-number');
    if (nextDrawElement) {
        const nextDrawText = nextDrawElement.textContent;
        const match = nextDrawText.match(/#(\d+)/);
        if (match && match[1]) {
            return parseInt(match[1], 10);
        }
    }

    // Try to get the draw number from the header UI
    const drawHeaderText = document.querySelector('.draw-header-number')?.textContent || '';
    const drawMatch = drawHeaderText.match(/#(\d+)/);

    if (drawMatch && drawMatch[1]) {
        return parseInt(drawMatch[1], 10);
    }

    // Try to get it from the window object
    if (window.drawHeader && window.drawHeader.currentDrawNumber) {
        return window.drawHeader.currentDrawNumber;
    }

    // Default to 19 if not found (based on the header in the screenshot)
    return 19;
}
