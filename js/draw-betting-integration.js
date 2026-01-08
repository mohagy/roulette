/**
 * Draw Header and Betting System Integration
 * This file contains code to integrate the draw number selection with the betting system
 */

// Initialize the draw numbers immediately
function initializeDrawNumbers() {
    console.log('Draw header has been disabled');

    // Create a dummy drawHeader object if it doesn't exist
    if (!window.drawHeader) {
        window.drawHeader = {
            loadSavedState: function() {},
            updateDrawNumbers: function() {},
            setupDrawNumberSelection: function() {},
            show: function() {},
            toggleVisibility: function() {},
            toggleMinimize: function() {},
            currentDrawNumber: 1
        };
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Initialize draw numbers
    initializeDrawNumbers();

    // Listen for draw number selection events
    document.addEventListener('drawNumberSelected', (e) => {
        const drawNumber = e.detail.drawNumber;
        console.log(`Draw number selected: ${drawNumber}`);

        // Ensure the draw number is saved as a number
        window.selectedDrawNumber = parseInt(drawNumber);
        console.log(`Set window.selectedDrawNumber to ${window.selectedDrawNumber}`);

        // Update UI to indicate the selected draw number
        updateBettingUIForDraw(drawNumber);
    });

    // Hook into the existing saveBettingSlipToDatabase function
    if (typeof window.originalSaveBettingSlipToDatabase === 'undefined') {
        // Store the original function
        window.originalSaveBettingSlipToDatabase = window.saveBettingSlipToDatabase;

        // Override with our new function that includes the draw number
        window.saveBettingSlipToDatabase = function(barcodeNumber, bets, totalStakes, potentialReturn) {
            // Get the selected draw number (or default to current draw)
            const selectedDrawNumber = window.selectedDrawNumber || getCurrentDrawNumber();

            console.log(`Saving betting slip for draw #${selectedDrawNumber}`);

            // Get form data from the original function parameters
            const formData = new FormData();
            formData.append('action', 'save_slip');
            formData.append('barcode', barcodeNumber);
            formData.append('bets', JSON.stringify(bets));
            formData.append('total_stakes', totalStakes);
            formData.append('potential_return', potentialReturn);
            formData.append('date', new Date().toISOString());

            // Add the draw number
            formData.append('draw_number', selectedDrawNumber);

            // Show saving indicator
            const savingMessage = document.createElement('div');
            savingMessage.style.position = 'fixed';
            savingMessage.style.top = '20px';
            savingMessage.style.left = '50%';
            savingMessage.style.transform = 'translateX(-50%)';
            savingMessage.style.backgroundColor = 'rgba(0, 0, 0, 0.8)';
            savingMessage.style.color = '#fff';
            savingMessage.style.padding = '10px 20px';
            savingMessage.style.borderRadius = '5px';
            savingMessage.style.zIndex = '10000';
            savingMessage.style.fontFamily = 'Arial, sans-serif';
            savingMessage.style.fontSize = '14px';
            savingMessage.textContent = `Saving betting slip for Draw #${selectedDrawNumber}...`;
            document.body.appendChild(savingMessage);

            // Make the AJAX request
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'php/slip_api.php', true);
            xhr.onload = function() {
                // Remove saving indicator
                document.body.removeChild(savingMessage);

                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        console.log('Save slip response:', response);

                        if (response.status === 'success') {
                            console.log('Slip saved successfully:', response.barcode);

                            // Show success message
                            const successMessage = document.createElement('div');
                            successMessage.style.position = 'fixed';
                            successMessage.style.top = '20px';
                            successMessage.style.left = '50%';
                            successMessage.style.transform = 'translateX(-50%)';
                            successMessage.style.backgroundColor = 'rgba(50, 180, 50, 0.9)';
                            successMessage.style.color = '#fff';
                            successMessage.style.padding = '10px 20px';
                            successMessage.style.borderRadius = '5px';
                            successMessage.style.zIndex = '10000';
                            successMessage.style.fontFamily = 'Arial, sans-serif';
                            successMessage.style.fontSize = '14px';
                            successMessage.textContent = `Betting slip for Draw #${selectedDrawNumber} saved successfully!`;
                            document.body.appendChild(successMessage);

                            // Remove the message after 3 seconds
                            setTimeout(() => {
                                document.body.removeChild(successMessage);
                            }, 3000);
                        } else {
                            console.error('Error saving slip:', response.message);

                            // Show error message
                            showErrorMessage('Failed to save betting slip: ' + response.message);
                        }
                    } catch (e) {
                        console.error('Error parsing response:', e, xhr.responseText);
                        showErrorMessage('Error processing server response');
                    }
                } else {
                    console.error('Request failed with status:', xhr.status);
                    showErrorMessage('Network error: ' + xhr.status);
                }
            };
            xhr.onerror = function() {
                // Remove saving indicator
                document.body.removeChild(savingMessage);

                console.error('Request failed');
                showErrorMessage('Network error: Could not connect to server');
            };
            xhr.send(formData);

            // Helper function to show error messages
            function showErrorMessage(message) {
                const errorMessage = document.createElement('div');
                errorMessage.style.position = 'fixed';
                errorMessage.style.top = '20px';
                errorMessage.style.left = '50%';
                errorMessage.style.transform = 'translateX(-50%)';
                errorMessage.style.backgroundColor = 'rgba(200, 50, 50, 0.9)';
                errorMessage.style.color = '#fff';
                errorMessage.style.padding = '10px 20px';
                errorMessage.style.borderRadius = '5px';
                errorMessage.style.zIndex = '10000';
                errorMessage.style.fontFamily = 'Arial, sans-serif';
                errorMessage.style.fontSize = '14px';
                errorMessage.textContent = message;
                document.body.appendChild(errorMessage);

                // Remove the message after 5 seconds
                setTimeout(() => {
                    document.body.removeChild(errorMessage);
                }, 5000);
            }
        };
    }

    // Add a button to show the draw header if it's hidden
    addDrawHeaderToggleButton();

    // Retry initialization if it fails the first time
    setTimeout(() => {
        if (!window.drawHeader || !window.drawHeader.currentDrawNumber) {
            console.log('Retrying draw header initialization...');
            initializeDrawNumbers();
        }
    }, 2000);
});

/**
 * Update the betting UI to indicate which draw number is selected
 * (This function has been disabled)
 */
function updateBettingUIForDraw(drawNumber) {
    console.log('Draw number indicator has been disabled');
    return;
}

/**
 * Get the next draw number (upcoming draw for new betting slips)
 * This ensures betting slips are always assigned to future draws
 */
function getCurrentDrawNumber() {
    return getNextDrawNumber();
}

/**
 * Get the next draw number from the header or UI
 */
function getNextDrawNumber() {
    // First try to get the next draw number from the UI
    const nextDrawElement = document.getElementById('next-draw-number');
    if (nextDrawElement) {
        const nextDrawText = nextDrawElement.textContent;
        const match = nextDrawText.match(/#(\d+)/);
        if (match && match[1]) {
            console.log('Using next draw number from UI:', match[1]);
            return parseInt(match[1], 10);
        }
    }

    // Try to get from database
    return getNextDrawFromDatabase();
}

/**
 * Get next draw number from database
 */
function getNextDrawFromDatabase() {
    try {
        // Synchronous request to get next draw number
        const xhr = new XMLHttpRequest();
        xhr.open('GET', 'php/get_next_draw_number.php', false); // Synchronous
        xhr.send();

        if (xhr.status === 200) {
            const response = JSON.parse(xhr.responseText);
            if (response.status === 'success' && response.next_draw_number) {
                console.log('Using next draw number from database:', response.next_draw_number);
                return parseInt(response.next_draw_number, 10);
            }
        }
    } catch (error) {
        console.error('Error getting next draw from database:', error);
    }

    // Final fallback
    console.warn('Could not determine next draw number, using fallback value 1');
    return 1;
}

/**
 * Add a button to show the draw header
 * (This function has been disabled)
 */
function addDrawHeaderToggleButton() {
    console.log('Draw header toggle button has been disabled');
    return;
}