/**
 * Elegant Cancel Slip Button
 * A beautiful, movable floating button for canceling betting slips
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing Elegant Cancel Button...');

    // Create the elegant cancel button
    createElegantCancelButton();

    // Initialize the button functionality
    initElegantCancelFunctionality();
});

/**
 * Create the elegant cancel button and add it to the DOM
 */
function createElegantCancelButton() {
    // Create the button element
    const elegantCancelButton = document.createElement('div');
    elegantCancelButton.id = 'elegant-cancel-button';
    elegantCancelButton.className = 'elegant-cancel-button';
    elegantCancelButton.innerHTML = `
        <div class="drag-handle" title="Drag to move">
            <i class="fas fa-grip-lines"></i>
        </div>
        <div class="button-content" title="Cancel a betting slip">
            <i class="fas fa-ban"></i>
            <span>CANCEL SLIP</span>
        </div>
    `;

    // Add the button to the DOM
    document.body.appendChild(elegantCancelButton);

    // Add a pulsing animation effect to make it more noticeable
    setTimeout(() => {
        elegantCancelButton.classList.add('pulse');
        setTimeout(() => {
            elegantCancelButton.classList.remove('pulse');
        }, 3000);
    }, 1000);

    // Save the initial position to localStorage if not already set
    if (!localStorage.getItem('elegantCancelButtonPosition')) {
        localStorage.setItem('elegantCancelButtonPosition', JSON.stringify({
            top: 'auto',
            left: 'auto',
            right: '100px',
            bottom: '20px'
        }));
    }

    // Apply saved position
    const savedPosition = JSON.parse(localStorage.getItem('elegantCancelButtonPosition') || '{}');
    Object.keys(savedPosition).forEach(prop => {
        if (savedPosition[prop]) {
            elegantCancelButton.style[prop] = savedPosition[prop];
        }
    });

    // Force the button to be positioned in pixels for proper dragging
    setTimeout(() => {
        const rect = elegantCancelButton.getBoundingClientRect();
        elegantCancelButton.style.top = rect.top + 'px';
        elegantCancelButton.style.left = rect.left + 'px';
        elegantCancelButton.style.right = 'auto';
        elegantCancelButton.style.bottom = 'auto';
        console.log('Elegant Cancel Button positioned at:', {top: rect.top, left: rect.left});
    }, 100);

    console.log('Elegant Cancel Button created');
}

/**
 * Initialize the elegant cancel button functionality
 */
function initElegantCancelFunctionality() {
    // Wait for the button to be available in the DOM
    const checkForButton = setInterval(function() {
        const elegantCancelButton = document.getElementById('elegant-cancel-button');
        if (elegantCancelButton) {
            clearInterval(checkForButton);

            // Add click event listener to the button content
            const buttonContent = elegantCancelButton.querySelector('.button-content');
            if (buttonContent) {
                buttonContent.addEventListener('click', function(e) {
                    e.stopPropagation(); // Prevent drag from triggering
                    console.log('Elegant Cancel Button clicked');

                    // Show the cancel slip modal
                    showElegantCancelModal();

                    // Play sound if available
                    if (typeof playAudio !== 'undefined' && typeof selectSound !== 'undefined') {
                        if (playAudio) {
                            selectSound.play();
                        }
                    }
                });
            }

            // Make the button draggable
            makeElegantDraggable(elegantCancelButton);

            console.log('Elegant Cancel Button functionality initialized');
        }
    }, 500);
}

/**
 * Make an element draggable
 * @param {HTMLElement} element - The element to make draggable
 */
function makeElegantDraggable(element) {
    let pos1 = 0, pos2 = 0, pos3 = 0, pos4 = 0;
    const dragHandle = element.querySelector('.drag-handle');

    // First, ensure the element has a defined position
    const computedStyle = window.getComputedStyle(element);
    if (computedStyle.position === 'static') {
        element.style.position = 'absolute';
    }

    // Convert percentage or other units to pixels for initial position
    if (!element.style.left || element.style.left === 'auto') {
        const rect = element.getBoundingClientRect();
        element.style.left = rect.left + 'px';
        element.style.top = rect.top + 'px';
        element.style.right = 'auto';
        element.style.bottom = 'auto';
    }

    if (dragHandle) {
        // If present, the drag-handle is where you move the element from
        dragHandle.onmousedown = dragMouseDown;
        dragHandle.style.cursor = 'grab';
    } else {
        // Otherwise, move the element from anywhere inside it
        element.onmousedown = dragMouseDown;
    }

    function dragMouseDown(e) {
        e = e || window.event;
        e.preventDefault();

        // Get the mouse cursor position at startup
        pos3 = e.clientX;
        pos4 = e.clientY;

        // Change cursor style
        if (dragHandle) dragHandle.style.cursor = 'grabbing';

        // Add event listeners for mouse movement and release
        document.addEventListener('mousemove', elementDrag);
        document.addEventListener('mouseup', closeDragElement);

        // Add active class
        element.classList.add('dragging');

        console.log('Started dragging Elegant Cancel Button');
    }

    function elementDrag(e) {
        e = e || window.event;
        e.preventDefault();

        // Calculate the new cursor position
        pos1 = pos3 - e.clientX;
        pos2 = pos4 - e.clientY;
        pos3 = e.clientX;
        pos4 = e.clientY;

        // Set the element's new position
        const newTop = (element.offsetTop - pos2);
        const newLeft = (element.offsetLeft - pos1);

        // Apply the new position
        element.style.top = newTop + "px";
        element.style.left = newLeft + "px";
        element.style.right = 'auto';
        element.style.bottom = 'auto';
    }

    function closeDragElement() {
        // Stop moving when mouse button is released
        document.removeEventListener('mousemove', elementDrag);
        document.removeEventListener('mouseup', closeDragElement);

        // Reset cursor style
        if (dragHandle) dragHandle.style.cursor = 'grab';

        // Remove active class
        element.classList.remove('dragging');

        // Save the position
        saveElegantButtonPosition(element);

        console.log('Finished dragging Elegant Cancel Button');
    }
}

/**
 * Save the button position to localStorage
 * @param {HTMLElement} button - The button element
 */
function saveElegantButtonPosition(button) {
    const position = {
        top: button.style.top,
        left: button.style.left,
        right: button.style.right,
        bottom: button.style.bottom
    };

    localStorage.setItem('elegantCancelButtonPosition', JSON.stringify(position));
    console.log('Elegant Cancel Button position saved:', position);
}

/**
 * Show the elegant cancel modal
 */
function showElegantCancelModal() {
    // Check if the modal already exists
    let elegantCancelModal = document.getElementById('elegant-cancel-modal');

    if (!elegantCancelModal) {
        // Create the modal
        elegantCancelModal = document.createElement('div');
        elegantCancelModal.id = 'elegant-cancel-modal';
        elegantCancelModal.className = 'elegant-cancel-modal';
        elegantCancelModal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Cancel Betting Slip</h2>
                    <span class="modal-close">&times;</span>
                </div>
                <div class="modal-body">
                    <p>Enter the betting slip number you want to cancel:</p>
                    <div class="input-group">
                        <input type="text" id="elegant-slip-id-input" placeholder="Betting Slip Number" />
                    </div>
                    <div class="error-message" id="elegant-cancel-error"></div>
                </div>
                <div class="modal-footer">
                    <button id="elegant-cancel-button-cancel" class="btn-cancel">Close</button>
                    <button id="elegant-cancel-button-confirm" class="btn-primary">Cancel Slip</button>
                </div>
            </div>
        `;

        // Add the modal to the DOM
        document.body.appendChild(elegantCancelModal);

        // Add event listeners
        const closeButton = elegantCancelModal.querySelector('.modal-close');
        closeButton.addEventListener('click', function() {
            elegantCancelModal.style.display = 'none';
        });

        const cancelButton = document.getElementById('elegant-cancel-button-cancel');
        cancelButton.addEventListener('click', function() {
            elegantCancelModal.style.display = 'none';
        });

        const confirmButton = document.getElementById('elegant-cancel-button-confirm');
        confirmButton.addEventListener('click', function() {
            const slipId = document.getElementById('elegant-slip-id-input').value.trim();
            if (slipId) {
                elegantCancelBettingSlip(slipId);
            } else {
                document.getElementById('elegant-cancel-error').textContent = 'Please enter a valid betting slip number';
            }
        });

        // Close the modal when clicking outside of it
        window.addEventListener('click', function(event) {
            if (event.target === elegantCancelModal) {
                elegantCancelModal.style.display = 'none';
            }
        });

        // Handle Enter key press in the input field
        const slipIdInput = document.getElementById('elegant-slip-id-input');
        slipIdInput.addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                const slipId = this.value.trim();
                if (slipId) {
                    elegantCancelBettingSlip(slipId);
                } else {
                    document.getElementById('elegant-cancel-error').textContent = 'Please enter a valid betting slip number';
                }
            }
        });
    }

    // Show the modal
    elegantCancelModal.style.display = 'block';

    // Focus on the input field
    setTimeout(function() {
        document.getElementById('elegant-slip-id-input').focus();
    }, 100);
}

/**
 * Cancel a betting slip
 * @param {string} slipId - The ID of the betting slip to cancel
 */
function elegantCancelBettingSlip(slipId) {
    console.log('Cancelling betting slip:', slipId);

    // Get the current draw number
    const currentDrawNumber = getElegantCurrentDrawNumber();

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
            document.getElementById('elegant-cancel-error').textContent = '';

            // Create a success notification
            showElegantNotification(data.message || 'Betting slip cancelled successfully', 'success');

            // Close the modal
            document.getElementById('elegant-cancel-modal').style.display = 'none';

            // Update cash balance if available
            if (data.cashBalance && typeof CashManager !== 'undefined') {
                CashManager.updateBalance(data.cashBalance);
            }
        } else {
            // Show error message
            document.getElementById('elegant-cancel-error').textContent = data.message || 'Failed to cancel betting slip';
        }
    })
    .catch(error => {
        console.error('Error cancelling betting slip:', error);
        document.getElementById('elegant-cancel-error').textContent = 'An error occurred while cancelling the betting slip';
    });
}

/**
 * Get the current draw number
 * @returns {number} The current draw number
 */
function getElegantCurrentDrawNumber() {
    // Try to get the draw number from the UI
    const nextDrawElement = document.getElementById('next-draw-number');
    if (nextDrawElement) {
        const drawText = nextDrawElement.textContent;
        const match = drawText.match(/#(\d+)/);
        if (match && match[1]) {
            return parseInt(match[1], 10);
        }
    }

    // Try to get it from the window object
    if (window.drawHeader && window.drawHeader.currentDrawNumber) {
        return window.drawHeader.currentDrawNumber;
    }

    // Default to 19 if not found (based on the header in the screenshot)
    return 19;
}

/**
 * Show an elegant notification
 * @param {string} message - The message to display
 * @param {string} type - The type of notification (success, error, info)
 */
function showElegantNotification(message, type = 'info') {
    // Create notification element if it doesn't exist
    let notification = document.getElementById('elegant-notification');

    if (!notification) {
        notification = document.createElement('div');
        notification.id = 'elegant-notification';
        notification.className = 'elegant-notification';
        document.body.appendChild(notification);

        // Add styles
        const style = document.createElement('style');
        style.textContent = `
            .elegant-notification {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 8px;
                color: white;
                font-family: Arial, sans-serif;
                font-size: 16px;
                z-index: 2000;
                opacity: 0;
                transform: translateY(-20px);
                transition: all 0.3s ease;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
                max-width: 300px;
            }
            .elegant-notification.show {
                opacity: 1;
                transform: translateY(0);
            }
            .elegant-notification.success {
                background-color: #4CAF50;
            }
            .elegant-notification.error {
                background-color: #f44336;
            }
            .elegant-notification.info {
                background-color: #2196F3;
            }
        `;
        document.head.appendChild(style);
    }

    // Set content and type
    notification.textContent = message;
    notification.className = 'elegant-notification ' + type;

    // Show notification
    setTimeout(() => {
        notification.classList.add('show');

        // Hide after 3 seconds
        setTimeout(() => {
            notification.classList.remove('show');
        }, 3000);
    }, 10);
}
