/**
 * Reprint Slip Button
 *
 * This script adds a floating, movable "Reprint Slip" button to the roulette system
 * that allows cashiers to reprint betting slips.
 */

class ReprintSlipButton {
    constructor() {
        this.isDragging = false;
        this.dragOffset = { x: 0, y: 0 };
        this.position = { x: 20, y: 20 };
        this.currentSlipData = null;

        // Create the button and modal
        this.createButton();
        this.createModal();

        // Initialize event listeners
        this.initEventListeners();

        // Load saved position from localStorage
        this.loadSavedPosition();

        // Ensure the button has fixed positioning
        this.button.style.position = 'fixed';

        // Make sure right and bottom are set to auto to avoid conflicts
        this.button.style.right = 'auto';
        this.button.style.bottom = 'auto';

        console.log('Reprint Slip Button initialized with fixed positioning');
    }

    /**
     * Create the reprint slip button
     */
    createButton() {
        // Create the button element
        const button = document.createElement('div');
        button.className = 'reprint-slip-button';
        button.innerHTML = `
            <div class="drag-handle">
                <i class="fas fa-grip-lines-vertical"></i>
            </div>
            <div class="button-content">
                <i class="fas fa-print icon"></i>
                <span class="text">Reprint Slip</span>
            </div>
        `;

        // Add the button to the document
        document.body.appendChild(button);

        // Store reference to the button
        this.button = button;
    }

    /**
     * Create the reprint slip modal
     */
    createModal() {
        // Create the modal element
        const modal = document.createElement('div');
        modal.className = 'reprint-slip-modal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Reprint Betting Slip</h2>
                    <span class="modal-close">&times;</span>
                </div>
                <div class="modal-body">
                    <div class="input-group">
                        <label for="slip-number-input">Enter Slip Number or Scan Barcode</label>
                        <div class="input-container">
                            <div class="barcode-icon"><i class="fas fa-barcode"></i></div>
                            <input type="text" id="slip-number-input" placeholder="Enter slip number..." autocomplete="off">
                        </div>
                    </div>

                    <div class="reprint-keypad">
                        <div class="reprint-key" data-key="1">1</div>
                        <div class="reprint-key" data-key="2">2</div>
                        <div class="reprint-key" data-key="3">3</div>
                        <div class="reprint-key" data-key="4">4</div>
                        <div class="reprint-key" data-key="5">5</div>
                        <div class="reprint-key" data-key="6">6</div>
                        <div class="reprint-key" data-key="7">7</div>
                        <div class="reprint-key" data-key="8">8</div>
                        <div class="reprint-key" data-key="9">9</div>
                        <div class="reprint-key key-clear" data-key="clear">Clear</div>
                        <div class="reprint-key" data-key="0">0</div>
                        <div class="reprint-key key-enter" data-key="enter">Enter</div>
                    </div>

                    <div class="error-message" id="reprint-error"></div>

                    <div class="loading">
                        <div class="spinner"></div>
                        <div class="loading-text">Loading slip information...</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button id="reprint-cancel" class="btn btn-secondary">Cancel</button>
                </div>
            </div>
        `;

        // Add the modal to the document
        document.body.appendChild(modal);

        // Store reference to the modal
        this.modal = modal;

        // Ensure the print slip modal exists for reuse
        this.ensurePrintSlipModalExists();
    }

    /**
     * Ensure the print slip modal exists for reuse
     */
    ensurePrintSlipModalExists() {
        // Check if print modal already exists, if not, create it
        if (!document.querySelector('.print-slip-modal')) {
            // Create the print modal structure
            const printModal = document.createElement('div');
            printModal.className = 'print-slip-modal';
            printModal.innerHTML = `
                <div class="print-slip-container">
                    <div class="print-slip-header">
                        <h2>Betting Slip Preview</h2>
                        <div class="print-slip-close"><i class="fas fa-times"></i></div>
                    </div>
                    <div class="print-slip-body">
                        <div class="print-slip-content"></div>
                        <div class="print-slip-actions">
                            <button class="print-action-button"><i class="fas fa-print"></i> Print Slip</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(printModal);

            // Add event listeners for the new modal
            document.querySelector('.print-slip-close').addEventListener('click', () => {
                document.querySelector('.print-slip-modal').classList.remove('visible');
                // Restore the default print action when closing the modal
                this.restoreDefaultPrintAction();
            });

            // Add default print action for regular betting slips
            this.setupDefaultPrintAction();
        } else {
            // If modal exists but we're initializing, make sure the default print action is set up
            this.setupDefaultPrintAction();
        }
    }

    /**
     * Set up the default print action for regular betting slips
     */
    setupDefaultPrintAction() {
        const printActionButton = document.querySelector('.print-action-button');

        // Store the original function reference if it doesn't exist yet
        if (!window.originalPrintActionFunction) {
            window.originalPrintActionFunction = () => {
                // Create a hidden iframe for printing just the receipt
                const iframe = document.createElement('iframe');
                iframe.style.display = 'none';
                document.body.appendChild(iframe);

                const slipContent = document.querySelector('.print-slip-content').innerHTML;

                // Write the content to the iframe
                iframe.contentDocument.write(`
                    <html>
                        <head>
                            <title>Betting Slip</title>
                            <style>
                                body {
                                    font-family: 'Courier New', monospace;
                                    width: 300px;
                                    margin: 0 auto;
                                    padding: 10px;
                                }
                                .header {
                                    text-align: center;
                                    margin-bottom: 15px;
                                }
                                .header h1 {
                                    font-size: 18px;
                                    margin: 0;
                                }
                                .header p {
                                    margin: 5px 0;
                                    font-size: 12px;
                                }
                                .bet-item {
                                    margin-bottom: 10px;
                                    border-bottom: 1px dashed #ccc;
                                    padding-bottom: 5px;
                                }
                                .bet-type {
                                    font-weight: bold;
                                }
                                .bet-details {
                                    display: flex;
                                    justify-content: space-between;
                                    font-size: 12px;
                                }
                                .totals {
                                    margin-top: 15px;
                                    font-weight: bold;
                                    border-top: 1px solid #000;
                                    padding-top: 5px;
                                }
                                .footer {
                                    margin-top: 15px;
                                    font-size: 12px;
                                    text-align: center;
                                }
                                .disclaimer {
                                    font-size: 10px;
                                    font-style: italic;
                                    margin-top: 5px;
                                }
                            </style>
                        </head>
                        <body>
                            ${slipContent}
                        </body>
                    </html>
                `);

                // Print the iframe content
                iframe.contentWindow.print();

                // Remove the iframe after printing
                setTimeout(() => {
                    document.body.removeChild(iframe);
                    document.querySelector('.print-slip-modal').classList.remove('visible');

                    // Clear the bets if this is a regular betting slip (not a reprint)
                    if (typeof betTracker !== 'undefined' && !document.querySelector('.reprint-watermark')) {
                        betTracker.clearBoardForNewBets();
                    }
                }, 500);
            };
        }

        // Remove any existing event listeners by cloning and replacing
        const newPrintButton = printActionButton.cloneNode(true);
        printActionButton.parentNode.replaceChild(newPrintButton, printActionButton);

        // Add the default print action
        newPrintButton.addEventListener('click', window.originalPrintActionFunction);
    }





    /**
     * Initialize event listeners
     */
    initEventListeners() {
        // Button click event
        this.button.querySelector('.button-content').addEventListener('click', () => {
            this.openModal();
        });

        // Make the button draggable
        this.makeButtonDraggable();

        // Modal close button
        this.modal.querySelector('.modal-close').addEventListener('click', () => {
            this.closeModal();
        });

        // Cancel button
        this.modal.querySelector('#reprint-cancel').addEventListener('click', () => {
            this.closeModal();
        });

        // Keypad keys
        this.modal.querySelectorAll('.reprint-key').forEach(key => {
            key.addEventListener('click', () => {
                this.handleKeyPress(key.dataset.key);
            });
        });

        // Enter key in input field
        this.modal.querySelector('#slip-number-input').addEventListener('keyup', (e) => {
            if (e.key === 'Enter') {
                this.fetchSlipInfo();
            }
        });
    }

    /**
     * Make the button draggable
     */
    makeButtonDraggable() {
        const element = this.button;
        const dragHandle = element.querySelector('.drag-handle');
        let pos1 = 0, pos2 = 0, pos3 = 0, pos4 = 0;

        // First, ensure the element has a defined position
        const computedStyle = window.getComputedStyle(element);
        if (computedStyle.position === 'static') {
            element.style.position = 'fixed';
        }

        // Convert percentage or other units to pixels for initial position
        if (!element.style.left || element.style.left === 'auto') {
            const rect = element.getBoundingClientRect();
            element.style.left = rect.left + 'px';
            element.style.top = rect.top + 'px';
            element.style.right = 'auto';
            element.style.bottom = 'auto';
        }

        // Set up mouse events - Make entire button draggable as fallback
        if (dragHandle) {
            dragHandle.onmousedown = dragMouseDown;
            dragHandle.ontouchstart = dragTouchStart;
            dragHandle.style.cursor = 'grab';
            dragHandle.title = 'Drag to move button';

            // Also make the entire button draggable as fallback
            element.onmousedown = dragMouseDown;
            element.ontouchstart = dragTouchStart;
        } else {
            element.onmousedown = dragMouseDown;
            element.ontouchstart = dragTouchStart;
        }

        console.log('🎰 Reprint button drag functionality initialized');

        function dragMouseDown(e) {
            e = e || window.event;
            e.preventDefault();
            e.stopPropagation();

            // Get the mouse cursor position at startup
            pos3 = e.clientX;
            pos4 = e.clientY;

            // Change cursor style
            if (dragHandle) dragHandle.style.cursor = 'grabbing';
            element.style.cursor = 'grabbing';

            // Add event listeners for mouse movement and release
            document.onmousemove = elementDrag;
            document.onmouseup = closeDragElement;

            // Add active class
            element.classList.add('dragging');

            console.log('🎰 Started dragging Reprint Button');
        }

        function dragTouchStart(e) {
            e = e || window.event;
            e.preventDefault();

            // Get the touch position at startup
            pos3 = e.touches[0].clientX;
            pos4 = e.touches[0].clientY;

            // Change cursor style
            if (dragHandle) dragHandle.style.cursor = 'grabbing';

            // Add event listeners for touch movement and end
            document.ontouchmove = elementTouchDrag;
            document.ontouchend = closeTouchDragElement;

            // Add active class
            element.classList.add('dragging');

            console.log('Started touch dragging Reprint Button');
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

            // Get the dimensions of the viewport and the button
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;
            const buttonWidth = element.offsetWidth;
            const buttonHeight = element.offsetHeight;

            // Ensure the button stays within the viewport bounds
            const left = Math.max(0, Math.min(newLeft, viewportWidth - buttonWidth));
            const top = Math.max(0, Math.min(newTop, viewportHeight - buttonHeight));

            // Apply the new position
            element.style.top = top + "px";
            element.style.left = left + "px";
            element.style.right = 'auto';
            element.style.bottom = 'auto';
        }

        function elementTouchDrag(e) {
            e = e || window.event;
            e.preventDefault();

            // Calculate the new touch position
            pos1 = pos3 - e.touches[0].clientX;
            pos2 = pos4 - e.touches[0].clientY;
            pos3 = e.touches[0].clientX;
            pos4 = e.touches[0].clientY;

            // Set the element's new position
            const newTop = (element.offsetTop - pos2);
            const newLeft = (element.offsetLeft - pos1);

            // Get the dimensions of the viewport and the button
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;
            const buttonWidth = element.offsetWidth;
            const buttonHeight = element.offsetHeight;

            // Ensure the button stays within the viewport bounds
            const left = Math.max(0, Math.min(newLeft, viewportWidth - buttonWidth));
            const top = Math.max(0, Math.min(newTop, viewportHeight - buttonHeight));

            // Apply the new position
            element.style.top = top + "px";
            element.style.left = left + "px";
            element.style.right = 'auto';
            element.style.bottom = 'auto';
        }

        function closeDragElement() {
            // Stop moving when mouse button is released
            document.onmousemove = null;
            document.onmouseup = null;

            // Reset cursor style
            if (dragHandle) dragHandle.style.cursor = 'grab';
            element.style.cursor = 'default';

            // Remove active class
            element.classList.remove('dragging');

            // Save the position
            saveButtonPosition();

            console.log('🎰 Finished dragging Reprint Button');
        }

        function closeTouchDragElement() {
            // Stop moving when touch ends
            document.ontouchmove = null;
            document.ontouchend = null;

            // Reset cursor style
            if (dragHandle) dragHandle.style.cursor = 'grab';

            // Remove active class
            element.classList.remove('dragging');

            // Save the position
            saveButtonPosition();

            console.log('Finished touch dragging Reprint Button');
        }

        const saveButtonPosition = () => {
            const position = {
                x: parseInt(element.style.left),
                y: parseInt(element.style.top)
            };

            localStorage.setItem('reprintSlipButtonPosition', JSON.stringify(position));
            console.log('Reprint Button position saved:', position);
        };
    }

    /**
     * Save the button position to localStorage
     */
    savePosition() {
        // Get the current position from the button's style
        const position = {
            x: parseInt(this.button.style.left),
            y: parseInt(this.button.style.top)
        };

        // Update the internal position property
        this.position = position;

        // Save to localStorage
        localStorage.setItem('reprintSlipButtonPosition', JSON.stringify(position));
        console.log('Saved button position:', position);
    }

    /**
     * Load the button position from localStorage
     */
    loadSavedPosition() {
        const savedPosition = localStorage.getItem('reprintSlipButtonPosition');
        if (savedPosition) {
            try {
                const position = JSON.parse(savedPosition);
                this.position = position;

                // Set the position with pixels
                this.button.style.left = (typeof position.x === 'number' ? position.x : parseInt(position.x)) + 'px';
                this.button.style.top = (typeof position.y === 'number' ? position.y : parseInt(position.y)) + 'px';

                // Ensure right and bottom are set to auto
                this.button.style.right = 'auto';
                this.button.style.bottom = 'auto';

                console.log('Loaded saved position:', position);
            } catch (e) {
                console.error('Error loading saved position:', e);

                // Set default position if there's an error
                this.button.style.left = '20px';
                this.button.style.top = '20px';
            }
        } else {
            // Set default position if no saved position
            this.button.style.left = '20px';
            this.button.style.top = '20px';
            console.log('No saved position found, using default position');
        }
    }

    /**
     * Open the reprint modal
     */
    openModal() {
        this.modal.style.display = 'block';
        this.modal.querySelector('#slip-number-input').focus();

        // Reset the modal
        this.resetModal();
    }

    /**
     * Close the reprint modal
     */
    closeModal() {
        this.modal.style.display = 'none';

        // If we have slip data, restore the default print action
        if (this.currentSlipData) {
            this.restoreDefaultPrintAction();
        }

        this.currentSlipData = null;
    }

    /**
     * Reset the modal to its initial state
     */
    resetModal() {
        this.modal.querySelector('#slip-number-input').value = '';
        this.modal.querySelector('.error-message').style.display = 'none';
        this.modal.querySelector('.loading').style.display = 'none';

        // Reset the current slip data
        this.currentSlipData = null;
    }

    /**
     * Handle keypad key press
     */
    handleKeyPress(key) {
        const input = this.modal.querySelector('#slip-number-input');
        const currentValue = input.value;

        if (key === 'clear') {
            // Clear the input
            input.value = '';
        } else if (key === 'enter') {
            // Fetch slip information
            this.fetchSlipInfo();
        } else {
            // Add the number to the input
            input.value = currentValue + key;
        }
    }

    /**
     * Fetch slip information from the server
     */
    fetchSlipInfo() {
        const slipNumber = this.modal.querySelector('#slip-number-input').value.trim();

        if (!slipNumber) {
            this.showError('Please enter a slip number');
            return;
        }

        // Show loading indicator
        this.modal.querySelector('.error-message').style.display = 'none';
        this.modal.querySelector('.loading').style.display = 'block';

        // Fetch slip information from the server
        console.log('Fetching slip info for slip number:', slipNumber);
        fetch('php/reprint_slip_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=get_slip_info&slip_number=${slipNumber}`
        })
        .then(response => response.json())
        .then(data => {
            // Hide loading indicator
            this.modal.querySelector('.loading').style.display = 'none';

            if (!data.success) {
                this.showError(data.message || 'Failed to retrieve slip information');
                return;
            }

            // Store the slip data
            this.currentSlipData = data;

            // Display the slip preview using the existing betting slip preview modal
            this.displaySlipPreview(data);

            // Close the reprint modal since we'll be using the betting slip preview modal
            this.closeModal();
        })
        .catch(error => {
            console.error('Error fetching slip information:', error);
            this.modal.querySelector('.loading').style.display = 'none';
            this.showError('Network error. Please try again.');
        });
    }

    /**
     * Display the slip preview using the existing betting slip preview modal
     */
    displaySlipPreview(data) {
        // Get the print slip modal
        const printSlipModal = document.querySelector('.print-slip-modal');
        const printSlipContent = printSlipModal.querySelector('.print-slip-content');

        // Format the slip data for display
        const slip = data.slip;
        const bets = data.bets;

        // Format date
        const slipDate = new Date(slip.created_at);
        const formattedDate = slipDate.toLocaleDateString() + ' ' + slipDate.toLocaleTimeString();

        // Generate the receipt HTML with REPRINT watermark
        let receiptHTML = `
            <div class="header">
                <div class="reprint-watermark">REPRINT</div>
                <h1>ROULETTE BETTING SLIP</h1>
                <p>${formattedDate}</p>
                <p>Player ID: GUEST</p>
                <p>Original Draw #: ${slip.draw_number}</p>
                <p>Draw #: ${data.next_draw_number}</p>
            </div>

            <div class="bets-list">
        `;

        // Add each bet to the slip
        bets.forEach((bet, index) => {
            receiptHTML += `
                <div class="bet-item">
                    <div class="bet-type">${index + 1}. ${bet.bet_type.toUpperCase()}: ${bet.bet_description}</div>
                    <div class="bet-details">
                        <div>Stake: $${parseFloat(bet.bet_amount).toFixed(2)}</div>
                        <div>Pays: ${parseFloat(bet.multiplier).toFixed(0)}:1</div>
                    </div>
                    <div class="bet-details">
                        <div></div>
                        <div>Return: $${parseFloat(bet.potential_return).toFixed(2)}</div>
                    </div>
                </div>
            `;
        });

        // Add total stake and potential return
        receiptHTML += `
            <div class="totals">
                <div class="total-stake">Total Staked: $${parseFloat(slip.total_stake).toFixed(2)}</div>
                <div class="potential-return">Potential Payout: $${parseFloat(slip.potential_payout).toFixed(2)}</div>
            </div>

            <div class="footer">
                <p>Draw Number: ${data.next_draw_number}</p>
                <p>Slip Number: ${slip.slip_number}</p>
                <p>Good luck!</p>
                <p class="disclaimer">This betting slip is for entertainment purposes only.</p>
                <p class="disclaimer">Not redeemable for real money.</p>
            </div>
        `;

        // Update the modal content
        printSlipContent.innerHTML = receiptHTML;

        // Add reprint-specific styles if they don't exist
        this.addReprintStyles();

        // Get the print action button
        const printActionButton = printSlipModal.querySelector('.print-action-button');

        // Remove any existing event listeners (using cloneNode trick)
        const newPrintButton = printActionButton.cloneNode(true);
        printActionButton.parentNode.replaceChild(newPrintButton, printActionButton);

        // Add our custom event listener for reprinting
        newPrintButton.addEventListener('click', () => {
            this.printReprintedSlip();
        });

        // Show the modal
        printSlipModal.classList.add('visible');
    }

    /**
     * Add reprint-specific styles to the document if they don't exist
     */
    addReprintStyles() {
        if (!document.getElementById('reprint-styles')) {
            const styleElement = document.createElement('style');
            styleElement.id = 'reprint-styles';
            styleElement.textContent = `
                .reprint-watermark {
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%) rotate(-30deg);
                    font-size: 48px;
                    font-weight: bold;
                    color: rgba(255, 0, 0, 0.2);
                    pointer-events: none;
                    z-index: 10;
                    text-transform: uppercase;
                    letter-spacing: 5px;
                    white-space: nowrap;
                }

                .header {
                    position: relative;
                }
            `;
            document.head.appendChild(styleElement);
        }
    }

    /**
     * Print the reprinted slip
     */
    printReprintedSlip() {
        if (!this.currentSlipData) {
            console.error('No slip data available for reprinting');
            return;
        }

        console.log('Reprinting slip with data:', this.currentSlipData);
        console.log('Slip ID:', this.currentSlipData.slip.slip_id);
        console.log('Draw Number:', this.currentSlipData.next_draw_number);

        // Show a loading indicator
        this.showLoadingIndicator('Processing reprint...');

        // Send the reprint request to the server
        const requestBody = `action=reprint_slip&slip_id=${this.currentSlipData.slip.slip_id}&draw_number=${this.currentSlipData.next_draw_number}`;
        console.log('Request body:', requestBody);

        // Log the URL we're fetching
        const apiUrl = 'php/reprint_slip_api.php';
        console.log('Sending reprint request to:', apiUrl);

        fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache',
                'Expires': '0'
            },
            body: requestBody,
            credentials: 'same-origin' // Include cookies in the request
        })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);

            if (!response.ok) {
                throw new Error(`Server responded with status ${response.status}`);
            }

            return response.text().then(text => {
                try {
                    console.log('Raw response:', text);
                    // Check if the response is empty
                    if (!text.trim()) {
                        throw new Error('Empty response from server');
                    }

                    // Try to parse the JSON
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Error parsing JSON:', e);
                    console.error('Raw response text:', text);

                    // Create a fallback JSON response
                    return {
                        success: false,
                        message: 'Invalid JSON response from server. Please try again or contact support.'
                    };
                }
            });
        })
        .then(data => {
            console.log('Parsed response data:', data);

            // Hide loading indicator
            this.hideLoadingIndicator();

            if (!data.success) {
                console.error('Failed to reprint slip:', data.message);
                this.showError(data.message || 'Failed to reprint slip');
                return;
            }

            console.log('Slip reprinted successfully!');
            console.log('New slip ID:', data.new_slip_id);
            console.log('New slip number:', data.new_slip_number);
            console.log('Transaction ID:', data.transaction_id);
            console.log('New balance:', data.new_balance);

            // Close the print slip modal
            document.querySelector('.print-slip-modal').classList.remove('visible');

            // Update the cash balance if provided
            if (data.new_balance !== undefined && typeof CashManager !== 'undefined') {
                console.log('Updating cash balance to:', data.new_balance);
                CashManager.setBalance(data.new_balance);

                // Update the UI display of cash balance
                const cashTotalElement = document.querySelector('.cash-total');
                if (cashTotalElement) {
                    cashTotalElement.textContent = CashManager.formatCash(data.new_balance);
                }
            }

            // Store the success data for use after modal is closed
            const successData = {
                slipNumber: data.new_slip_number,
                drawNumber: data.draw_number
            };

            // Show a success message to the user immediately
            this.showSuccess(`Slip #${successData.slipNumber} reprinted successfully for draw #${successData.drawNumber}`);

            // Reset the current slip data
            this.currentSlipData = null;

            // Restore the original print functionality for future regular betting slips
            this.restoreDefaultPrintAction();

            // Open the print dialog if a print URL is provided
            // Do this after everything else is done
            if (data.print_url) {
                console.log('Opening print URL:', data.print_url);

                // Verify the new slip was created by checking the database
                fetch('check_slip_exists.php?slip_id=' + data.new_slip_id, {
                    method: 'GET',
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(checkResult => {
                    console.log('Slip existence check result:', checkResult);

                    if (checkResult.exists) {
                        console.log('Slip confirmed to exist in database, opening print dialog');
                        setTimeout(() => {
                            window.open(data.print_url, '_blank');
                        }, 500);
                    } else {
                        console.error('Slip does not exist in database!');
                        this.showError('Error: The reprinted slip was not found in the database.');
                    }
                })
                .catch(error => {
                    console.error('Error checking slip existence:', error);
                    // Open the print dialog anyway
                    setTimeout(() => {
                        window.open(data.print_url, '_blank');
                    }, 500);
                });
            }
        })
        .catch(error => {
            console.error('Error reprinting slip:', error);
            this.hideLoadingIndicator();
            this.showError('Network error: ' + error.message);
        });
    }

    /**
     * Show a loading indicator
     */
    showLoadingIndicator(message = 'Loading...') {
        // Create a loading overlay if it doesn't exist
        let loadingOverlay = document.getElementById('reprint-loading-overlay');
        if (!loadingOverlay) {
            loadingOverlay = document.createElement('div');
            loadingOverlay.id = 'reprint-loading-overlay';
            loadingOverlay.style.position = 'fixed';
            loadingOverlay.style.top = '0';
            loadingOverlay.style.left = '0';
            loadingOverlay.style.width = '100%';
            loadingOverlay.style.height = '100%';
            loadingOverlay.style.backgroundColor = 'rgba(0, 0, 0, 0.7)';
            loadingOverlay.style.display = 'flex';
            loadingOverlay.style.justifyContent = 'center';
            loadingOverlay.style.alignItems = 'center';
            loadingOverlay.style.zIndex = '10001';

            const loadingContent = document.createElement('div');
            loadingContent.style.backgroundColor = 'white';
            loadingContent.style.padding = '20px';
            loadingContent.style.borderRadius = '5px';
            loadingContent.style.textAlign = 'center';

            const spinner = document.createElement('div');
            spinner.className = 'loading-spinner';
            spinner.style.border = '4px solid #f3f3f3';
            spinner.style.borderTop = '4px solid #3498db';
            spinner.style.borderRadius = '50%';
            spinner.style.width = '30px';
            spinner.style.height = '30px';
            spinner.style.animation = 'spin 2s linear infinite';
            spinner.style.margin = '0 auto 10px';

            const messageElement = document.createElement('div');
            messageElement.id = 'reprint-loading-message';
            messageElement.textContent = message;

            loadingContent.appendChild(spinner);
            loadingContent.appendChild(messageElement);
            loadingOverlay.appendChild(loadingContent);

            // Add the spinner animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            `;
            document.head.appendChild(style);

            document.body.appendChild(loadingOverlay);
        } else {
            document.getElementById('reprint-loading-message').textContent = message;
            loadingOverlay.style.display = 'flex';
        }
    }

    /**
     * Hide the loading indicator
     */
    hideLoadingIndicator() {
        const loadingOverlay = document.getElementById('reprint-loading-overlay');
        if (loadingOverlay) {
            loadingOverlay.style.display = 'none';
        }
    }

    /**
     * Restore the default print action after reprinting is complete
     */
    restoreDefaultPrintAction() {
        // Wait a short time to ensure the modal is fully closed
        setTimeout(() => {
            // Set up the default print action again
            this.setupDefaultPrintAction();
        }, 500);
    }





    /**
     * Show an error message
     */
    showError(message) {
        const errorElement = this.modal.querySelector('.error-message');
        errorElement.textContent = message;
        errorElement.style.display = 'block';
    }

    /**
     * Show a success message
     */
    showSuccess(message) {
        // Create a floating notification if it doesn't exist
        let notification = document.getElementById('reprint-notification');
        if (!notification) {
            notification = document.createElement('div');
            notification.id = 'reprint-notification';
            notification.style.position = 'fixed';
            notification.style.bottom = '20px';
            notification.style.right = '20px';
            notification.style.padding = '15px 20px';
            notification.style.backgroundColor = 'rgba(76, 175, 80, 0.9)';
            notification.style.color = 'white';
            notification.style.borderRadius = '5px';
            notification.style.boxShadow = '0 2px 10px rgba(0, 0, 0, 0.2)';
            notification.style.zIndex = '10000';
            notification.style.transition = 'opacity 0.5s ease-in-out';
            notification.style.opacity = '0';
            document.body.appendChild(notification);
        }

        // Set the message and show the notification
        notification.textContent = message;
        notification.style.opacity = '1';

        // Hide the notification after 5 seconds
        setTimeout(() => {
            notification.style.opacity = '0';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 500);
        }, 5000);

        // Also update the error message in the modal if it's visible
        if (this.modal.style.display === 'block') {
            const errorElement = this.modal.querySelector('.error-message');
            errorElement.textContent = message;
            errorElement.style.display = 'block';
            errorElement.style.color = '#4CAF50';
            errorElement.style.background = 'rgba(76, 175, 80, 0.1)';
        }
    }
}

// Initialize the reprint slip button when the DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Wait a bit to ensure all other scripts are loaded
    setTimeout(() => {
        window.reprintSlipButton = new ReprintSlipButton();
    }, 1000);
});
