/**
 * Cashout Button and Modal Functionality
 * This module handles the verification and processing of betting slip cashouts
 */

class CashoutManager {
    constructor() {
        this.init();
    }

    init() {
        this.createCashoutButton();
        this.createCashoutModal();
        this.setupEventListeners();
        this.makeButtonDraggable();
    }

    createCashoutButton() {
        // Create the cashout button element
        const button = document.createElement('div');
        button.className = 'cashout-button';
        button.innerHTML = `
            <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="text">Cashout</div>
        `;
        document.body.appendChild(button);
    }

    createCashoutModal() {
        // Create the cashout modal
        const modal = document.createElement('div');
        modal.className = 'cashout-modal';
        modal.innerHTML = `
            <div class="cashout-container">
                <div class="cashout-header">
                    <h2>Cashout Betting Slip</h2>
                    <div class="cashout-close"><i class="fas fa-times"></i></div>
                </div>
                <div class="cashout-body">
                    <div class="cashout-input-group">
                        <label for="slip-number-input">Enter Slip Number or Scan Barcode</label>
                        <div class="input-container">
                            <div class="barcode-icon"><i class="fas fa-barcode"></i></div>
                            <input type="text" id="slip-number-input" placeholder="Enter slip number..." autocomplete="off" autofocus>
                        </div>
                        <div class="cashout-note">Note: Betting slips are valid for 7 days after purchase.</div>
                        <div class="cashout-error" id="cashout-error"></div>
                    </div>

                    <div class="cashout-actions">
                        <button class="cashout-button-action cashout-cancel-button">Cancel</button>
                        <button class="cashout-button-action cashout-verify-button">Verify Slip</button>
                    </div>

                    <div class="cashout-loading">
                        <div class="spinner"></div>
                        <div class="message">Verifying slip...</div>
                    </div>

                    <div class="cashout-results">
                        <div class="cashout-results-header">
                            <div class="slip-number">Slip #<span id="result-slip-number"></span></div>
                            <div class="draw-number">Draw #<span id="result-draw-number"></span></div>
                        </div>

                        <div class="slip-date" id="slip-date"></div>

                        <div class="winning-number-display">
                            <div class="winning-number" id="winning-number">0</div>
                        </div>

                        <div class="bet-results">
                            <div class="bet-results-title">Bet Results</div>
                            <div class="bet-list" id="bet-list"></div>
                        </div>

                        <div class="cashout-summary">
                            <div class="cashout-total" id="cashout-total">
                                <span class="label">Total Winnings:</span>
                                <span class="amount">$0.00</span>
                            </div>
                        </div>

                        <div class="cashout-message" id="cashout-message"></div>

                        <button class="cashout-process-button" id="cashout-process-button">Process Cashout</button>
                    </div>
                </div>
            </div>

            <div class="receipt-container" id="receipt-container">
                <div class="receipt-header">
                    <h1>ROULETTE CASHOUT RECEIPT</h1>
                    <p id="receipt-date"></p>
                </div>
                <div class="receipt-body" id="receipt-body">
                    <!-- Receipt items will be added here -->
                </div>
                <div class="receipt-barcode">
                    <div id="receipt-barcode-graphic"></div>
                    <div class="receipt-barcode-number" id="receipt-barcode-number"></div>
                </div>
                <div class="receipt-footer">
                    Thank you for playing!
                    <div>This receipt is proof of payment</div>
                </div>
                <button class="print-receipt-button" id="print-receipt">Print Receipt</button>
            </div>
        `;
        document.body.appendChild(modal);
    }

    setupEventListeners() {
        // Button to open the modal
        document.querySelector('.cashout-button').addEventListener('click', () => {
            this.openModal();
        });

        // Close button
        document.querySelector('.cashout-close').addEventListener('click', () => {
            this.closeModal();
        });

        // Cancel button
        document.querySelector('.cashout-cancel-button').addEventListener('click', () => {
            this.closeModal();
        });

        // Verify slip button
        document.querySelector('.cashout-verify-button').addEventListener('click', () => {
            this.verifySlip();
        });

        // Process cashout button
        document.getElementById('cashout-process-button').addEventListener('click', () => {
            this.processCashout();
        });

        // Print receipt button
        document.getElementById('print-receipt').addEventListener('click', () => {
            this.printReceipt();
        });

        // Enter key in input field
        document.getElementById('slip-number-input').addEventListener('keyup', (e) => {
            if (e.key === 'Enter') {
                this.verifySlip();
            }
        });

        // Automatically focus the input field when modal opens
        document.querySelector('.cashout-modal').addEventListener('transitionend', () => {
            if (document.querySelector('.cashout-modal').classList.contains('visible')) {
                document.getElementById('slip-number-input').focus();
            }
        });
    }

    openModal() {
        document.querySelector('.cashout-modal').classList.add('visible');
        document.getElementById('slip-number-input').focus();

        // Reset the modal
        document.querySelector('.cashout-results').style.display = 'none';
        document.querySelector('.cashout-loading').style.display = 'none';
        document.getElementById('cashout-error').style.display = 'none';
        document.getElementById('slip-number-input').value = '';
        document.getElementById('receipt-container').style.display = 'none';
    }

    closeModal() {
        document.querySelector('.cashout-modal').classList.remove('visible');
    }

    verifySlip() {
        const slipNumber = document.getElementById('slip-number-input').value.trim();

        if (!slipNumber) {
            this.showError('Please enter a slip number');
            return;
        }

        // Hide error and show loading
        document.getElementById('cashout-error').style.display = 'none';
        document.querySelector('.cashout-loading').style.display = 'block';
        document.querySelector('.cashout-results').style.display = 'none';

        // Call the API to verify the slip
        this.callVerifyAPI(slipNumber)
            .then(response => {
                document.querySelector('.cashout-loading').style.display = 'none';

                if (response.status === 'error') {
                    this.showError(response.message);
                    return;
                }

                // Show the results
                this.displayResults(response);
            })
            .catch(error => {
                document.querySelector('.cashout-loading').style.display = 'none';
                this.showError('Network error: ' + error.message);
            });
    }

    callVerifyAPI(slipNumber) {
        // Create form data
        const formData = new FormData();
        formData.append('action', 'verify_cashout');
        formData.append('slip_number', slipNumber);

        // Make the API call with absolute path
        return fetch('/slipp/php/cashout_api.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response ok:', response.ok);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return response.text().then(text => {
                console.log('Raw response:', text);
                try {
                    return JSON.parse(text);
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    throw new Error('Invalid JSON response: ' + text);
                }
            });
        })
        .catch(error => {
            console.error('API Error:', error);
            return { status: 'error', message: 'Network error: ' + error.message };
        });
    }

    displayResults(data) {
        document.querySelector('.cashout-results').style.display = 'block';

        // Set slip and draw numbers
        document.getElementById('result-slip-number').textContent = data.slip.slip_number;
        document.getElementById('result-draw-number').textContent = data.draw_number;

        // Display slip date
        const slipDate = new Date(data.slip.created_at);
        const formattedDate = slipDate.toLocaleDateString() + ' ' + slipDate.toLocaleTimeString();

        // Calculate days remaining for validity
        const currentDate = new Date();
        const daysDifference = Math.floor((currentDate - slipDate) / (1000 * 60 * 60 * 24));
        const daysRemaining = 7 - daysDifference;

        let validityInfo = '';
        if (daysRemaining > 0) {
            validityInfo = ` (Valid for ${daysRemaining} more day${daysRemaining !== 1 ? 's' : ''})`;
        }

        const slipDateEl = document.getElementById('slip-date');
        slipDateEl.innerHTML = `<div class="slip-purchase-date">Purchase date: ${formattedDate}${validityInfo}</div>`;
        slipDateEl.style.display = 'block';

        // Set winning number and color
        const winningNumberEl = document.getElementById('winning-number');
        winningNumberEl.textContent = data.winning_number;
        winningNumberEl.className = 'winning-number ' + data.winning_color;

        // Clear previous bet list
        const betList = document.getElementById('bet-list');
        betList.innerHTML = '';

        // Add each bet to the list
        data.bets.forEach(bet => {
            const isWinner = data.winning_bets.some(winBet => winBet.bet_id === bet.bet_id);

            const betEl = document.createElement('div');
            betEl.className = 'bet-item';
            betEl.innerHTML = `
                <div class="bet-description">
                    <div class="type">${this.capitalizeFirstLetter(bet.bet_type)}</div>
                    <div class="details">${bet.bet_description}</div>
                </div>
                <div class="bet-amount">$${parseFloat(bet.bet_amount).toFixed(2)}</div>
                <div class="bet-result ${isWinner ? 'win' : 'lose'}">
                    ${isWinner ? '$' + parseFloat(bet.potential_return).toFixed(2) : '-'}
                </div>
            `;
            betList.appendChild(betEl);
        });

        // Set total winnings
        const totalEl = document.getElementById('cashout-total');
        const amountEl = totalEl.querySelector('.amount');
        amountEl.textContent = '$' + parseFloat(data.total_winnings).toFixed(2);

        if (data.total_winnings <= 0) {
            totalEl.classList.add('no-win');
            document.getElementById('cashout-process-button').style.display = 'none';

            // Show no winning message
            const messageEl = document.getElementById('cashout-message');
            messageEl.className = 'cashout-message error';
            messageEl.textContent = 'Sorry, none of your bets won for this draw.';
            messageEl.style.display = 'block';
        } else {
            totalEl.classList.remove('no-win');
            document.getElementById('cashout-process-button').style.display = 'block';
            document.getElementById('cashout-message').style.display = 'none';
        }

        // Store the data for future use
        this.currentVerificationData = data;
    }

    processCashout() {
        if (!this.currentVerificationData) {
            this.showError('No verification data available');
            return;
        }

        const slipNumber = this.currentVerificationData.slip.slip_number;

        // Show loading
        document.getElementById('cashout-process-button').disabled = true;
        document.getElementById('cashout-process-button').textContent = 'Processing...';

        // Call the API to process the cashout
        this.callProcessAPI(slipNumber)
            .then(response => {
                if (response.status === 'error') {
                    this.showError(response.message);
                    document.getElementById('cashout-process-button').disabled = false;
                    document.getElementById('cashout-process-button').textContent = 'Process Cashout';
                    return;
                }

                // Show success message
                const messageEl = document.getElementById('cashout-message');
                messageEl.className = 'cashout-message success';
                messageEl.textContent = 'Cashout processed successfully!';
                messageEl.style.display = 'block';

                // Hide process button
                document.getElementById('cashout-process-button').style.display = 'none';

                // Show receipt
                this.displayReceipt();
            })
            .catch(error => {
                document.getElementById('cashout-process-button').disabled = false;
                document.getElementById('cashout-process-button').textContent = 'Process Cashout';
                this.showError('Network error: ' + error.message);
            });
    }

    callProcessAPI(slipNumber) {
        // Create form data
        const formData = new FormData();
        formData.append('action', 'process_cashout');
        formData.append('slip_number', slipNumber);

        // Make the API call with absolute path
        return fetch('/slipp/php/cashout_api.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Process Response status:', response.status);
            console.log('Process Response ok:', response.ok);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return response.text().then(text => {
                console.log('Process Raw response:', text);
                try {
                    return JSON.parse(text);
                } catch (parseError) {
                    console.error('Process JSON parse error:', parseError);
                    throw new Error('Invalid JSON response: ' + text);
                }
            });
        })
        .catch(error => {
            console.error('Process API Error:', error);
            return { status: 'error', message: 'Network error: ' + error.message };
        });
    }

    displayReceipt() {
        const data = this.currentVerificationData;
        const receiptContainer = document.getElementById('receipt-container');
        const receiptBody = document.getElementById('receipt-body');

        // Show the receipt container
        receiptContainer.style.display = 'block';

        // Set receipt date
        document.getElementById('receipt-date').textContent = new Date().toLocaleString();

        // Clear receipt body
        receiptBody.innerHTML = '';

        // Add receipt items
        const purchaseDate = new Date(data.slip.created_at).toLocaleString();

        const items = [
            { label: 'Slip Number', value: data.slip.slip_number },
            { label: 'Draw Number', value: '#' + data.draw_number },
            { label: 'Purchase Date', value: purchaseDate },
            { label: 'Draw Result', value: data.winning_number + ' ' + this.capitalizeFirstLetter(data.winning_color) },
            { label: 'Original Stake', value: '$' + parseFloat(data.slip.total_stake).toFixed(2) }
        ];

        items.forEach(item => {
            const itemEl = document.createElement('div');
            itemEl.className = 'receipt-item';
            itemEl.innerHTML = `
                <span class="label">${item.label}:</span>
                <span class="value">${item.value}</span>
            `;
            receiptBody.appendChild(itemEl);
        });

        // Add winning bets
        if (data.winning_bets.length > 0) {
            const winningHeader = document.createElement('div');
            winningHeader.className = 'receipt-item';
            winningHeader.innerHTML = '<span class="label">Winning Bets:</span>';
            receiptBody.appendChild(winningHeader);

            data.winning_bets.forEach(bet => {
                const betEl = document.createElement('div');
                betEl.className = 'receipt-item';
                betEl.innerHTML = `
                    <span class="label">${bet.bet_description}</span>
                    <span class="value">$${parseFloat(bet.winnings).toFixed(2)}</span>
                `;
                receiptBody.appendChild(betEl);
            });
        }

        // Add total
        const totalEl = document.createElement('div');
        totalEl.className = 'receipt-item total';
        totalEl.innerHTML = `
            <span class="label">Total Payout:</span>
            <span class="value">$${parseFloat(data.total_winnings).toFixed(2)}</span>
        `;
        receiptBody.appendChild(totalEl);

        // Add cashout date
        const cashoutDateEl = document.createElement('div');
        cashoutDateEl.className = 'receipt-item';
        cashoutDateEl.innerHTML = `
            <span class="label">Cashout Date:</span>
            <span class="value">${new Date().toLocaleString()}</span>
        `;
        receiptBody.appendChild(cashoutDateEl);

        // Set barcode
        document.getElementById('receipt-barcode-number').textContent = data.slip.slip_number;
        this.generateBarcode(data.slip.slip_number);
    }

    generateBarcode(barcodeNumber) {
        const container = document.getElementById('receipt-barcode-graphic');
        container.innerHTML = '';

        // Create a simple CSS barcode
        const barcodeEl = document.createElement('div');
        barcodeEl.className = 'css-barcode';

        // Convert the barcode number to a visual representation
        const digits = barcodeNumber.toString().split('');

        // Add start marker
        const startMarker = document.createElement('div');
        startMarker.className = 'bar thick';
        barcodeEl.appendChild(startMarker);

        // Add bars for each digit
        digits.forEach(digit => {
            const num = parseInt(digit);

            // Add 3 bars per digit with varying widths
            for (let i = 0; i < 3; i++) {
                const bar = document.createElement('div');

                // Determine width based on digit and position
                if (i === 0) {
                    bar.className = 'bar ' + (num % 3 === 0 ? 'thick' : 'thin');
                } else if (i === 1) {
                    bar.className = 'bar ' + (num % 2 === 0 ? 'medium' : 'thin');
                } else {
                    bar.className = 'bar ' + (num % 3 === 1 ? 'thick' : 'medium');
                }

                barcodeEl.appendChild(bar);
            }

            // Add spacer
            const spacer = document.createElement('div');
            spacer.style.width = '2px';
            spacer.style.display = 'inline-block';
            barcodeEl.appendChild(spacer);
        });

        // Add end marker
        const endMarker = document.createElement('div');
        endMarker.className = 'bar thick';
        barcodeEl.appendChild(endMarker);

        container.appendChild(barcodeEl);
    }

    printReceipt() {
        const receiptContent = document.getElementById('receipt-container').innerHTML;

        // Create a hidden iframe for printing
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        document.body.appendChild(iframe);

        // Write the content to the iframe
        const doc = iframe.contentDocument || iframe.contentWindow.document;
        doc.write(`
            <html>
            <head>
                <title>Cashout Receipt</title>
                <style>
                    body {
                        font-family: 'Courier New', monospace;
                        padding: 20px;
                        max-width: 300px;
                        margin: 0 auto;
                        background-color: white;
                        color: black;
                    }
                    .receipt-header {
                        text-align: center;
                        border-bottom: 1px dashed #999;
                        padding-bottom: 10px;
                        margin-bottom: 15px;
                    }
                    .receipt-header h1 {
                        margin: 0;
                        font-size: 16px;
                    }
                    .receipt-header p {
                        margin: 5px 0;
                        font-size: 12px;
                    }
                    .receipt-body {
                        padding: 10px 0;
                    }
                    .receipt-item {
                        margin-bottom: 10px;
                        border-bottom: 1px dotted #eee;
                        padding-bottom: 10px;
                        clear: both;
                        display: block;
                    }
                    .receipt-item .label {
                        font-weight: bold;
                    }
                    .receipt-item .value {
                        float: right;
                    }
                    .receipt-item.total {
                        font-weight: bold;
                        border-bottom: 1px dashed #999;
                        padding-bottom: 15px;
                        margin-bottom: 15px;
                    }
                    .receipt-barcode {
                        text-align: center;
                        margin: 20px 0;
                    }
                    .receipt-barcode-number {
                        font-size: 12px;
                        margin-top: 5px;
                    }
                    .receipt-footer {
                        text-align: center;
                        font-size: 12px;
                        margin-top: 20px;
                        border-top: 1px dashed #999;
                        padding-top: 10px;
                    }
                    .css-barcode {
                        display: flex;
                        justify-content: center;
                        height: 40px;
                        width: 95%;
                        margin: 10px auto;
                    }
                    .bar {
                        height: 100%;
                        width: 2px;
                        display: inline-block;
                        background: black;
                        margin-right: 1px;
                    }
                    .bar.thin {
                        width: 1px;
                    }
                    .bar.medium {
                        width: 2px;
                    }
                    .bar.thick {
                        width: 3px;
                    }
                    button {
                        display: none;
                    }
                </style>
            </head>
            <body>
                ${receiptContent}
            </body>
            </html>
        `);

        doc.close();

        // Print the iframe content
        iframe.contentWindow.focus();
        iframe.contentWindow.print();

        // Remove the iframe after printing
        setTimeout(() => {
            document.body.removeChild(iframe);
        }, 1000);
    }

    showError(message) {
        const errorEl = document.getElementById('cashout-error');
        errorEl.textContent = message;
        errorEl.style.display = 'block';
    }

    capitalizeFirstLetter(string) {
        return string.charAt(0).toUpperCase() + string.slice(1);
    }

    makeButtonDraggable() {
        const cashoutButton = document.querySelector('.cashout-button');
        let isDragging = false;
        let offsetX, offsetY;

        // Mouse events
        cashoutButton.addEventListener('mousedown', (e) => {
            isDragging = true;
            offsetX = e.clientX - cashoutButton.getBoundingClientRect().left;
            offsetY = e.clientY - cashoutButton.getBoundingClientRect().top;
            cashoutButton.style.cursor = 'grabbing';
        });

        document.addEventListener('mousemove', (e) => {
            if (!isDragging) return;

            const left = e.clientX - offsetX;
            const top = e.clientY - offsetY;

            // Keep button within viewport bounds
            const maxX = window.innerWidth - cashoutButton.offsetWidth;
            const maxY = window.innerHeight - cashoutButton.offsetHeight;

            cashoutButton.style.left = Math.max(0, Math.min(left, maxX)) + 'px';
            cashoutButton.style.top = Math.max(0, Math.min(top, maxY)) + 'px';
            cashoutButton.style.right = 'auto';
            cashoutButton.style.bottom = 'auto';

            // Prevent default drag behavior
            e.preventDefault();
        });

        document.addEventListener('mouseup', () => {
            if (isDragging) {
                isDragging = false;
                cashoutButton.style.cursor = 'pointer';
            }
        });

        // Touch events for mobile
        cashoutButton.addEventListener('touchstart', (e) => {
            isDragging = true;
            offsetX = e.touches[0].clientX - cashoutButton.getBoundingClientRect().left;
            offsetY = e.touches[0].clientY - cashoutButton.getBoundingClientRect().top;
        }, { passive: false });

        document.addEventListener('touchmove', (e) => {
            if (!isDragging) return;

            const left = e.touches[0].clientX - offsetX;
            const top = e.touches[0].clientY - offsetY;

            // Keep button within viewport bounds
            const maxX = window.innerWidth - cashoutButton.offsetWidth;
            const maxY = window.innerHeight - cashoutButton.offsetHeight;

            cashoutButton.style.left = Math.max(0, Math.min(left, maxX)) + 'px';
            cashoutButton.style.top = Math.max(0, Math.min(top, maxY)) + 'px';
            cashoutButton.style.right = 'auto';
            cashoutButton.style.bottom = 'auto';

            // Prevent default scroll behavior
            e.preventDefault();
        }, { passive: false });

        document.addEventListener('touchend', () => {
            isDragging = false;
        });

        // Make the modal draggable
        const header = document.querySelector('.cashout-header');
        const container = document.querySelector('.cashout-container');
        let modalIsDragging = false;
        let modalOffsetX, modalOffsetY;

        header.addEventListener('mousedown', (e) => {
            // Don't drag if clicking the close button
            if (e.target.closest('.cashout-close')) return;

            modalIsDragging = true;
            modalOffsetX = e.clientX - container.getBoundingClientRect().left;
            modalOffsetY = e.clientY - container.getBoundingClientRect().top;
            header.style.cursor = 'grabbing';
        });

        document.addEventListener('mousemove', (e) => {
            if (!modalIsDragging) return;

            const left = e.clientX - modalOffsetX;
            const top = e.clientY - modalOffsetY;

            // Keep modal within viewport bounds
            const maxX = window.innerWidth - container.offsetWidth;
            const maxY = window.innerHeight - container.offsetHeight;

            container.style.left = Math.max(0, Math.min(left, maxX)) + 'px';
            container.style.top = Math.max(0, Math.min(top, maxY)) + 'px';
            container.style.margin = '0';
            container.style.position = 'absolute';

            // Prevent default drag behavior
            e.preventDefault();
        });

        document.addEventListener('mouseup', () => {
            if (modalIsDragging) {
                modalIsDragging = false;
                header.style.cursor = 'move';
            }
        });
    }
}

// Initialize the cashout manager when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.cashoutManager = new CashoutManager();
});