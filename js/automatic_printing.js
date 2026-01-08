/**
 * Automatic Betting Slip Printing
 * Handles client-side printing requests without browser dialogs
 */

class AutomaticPrinter {
    constructor() {
        this.apiUrl = 'api/print_slip_api.php';
        this.defaultPrinter = null;
        this.availablePrinters = [];
        this.init();
    }
    
    async init() {
        await this.loadAvailablePrinters();
        this.setupPrintButtons();
    }
    
    /**
     * Load available printers from server
     */
    async loadAvailablePrinters() {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_printers'
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.availablePrinters = result.printers;
                if (this.availablePrinters.length > 0) {
                    this.defaultPrinter = this.availablePrinters[0];
                }
            }
        } catch (error) {
            console.error('Failed to load printers:', error);
        }
    }
    
    /**
     * Setup print button event listeners
     */
    setupPrintButtons() {
        // Find all print buttons
        const printButtons = document.querySelectorAll('.print-action-button, [data-action="print"]');
        
        printButtons.forEach(button => {
            // Remove existing click handlers
            button.removeEventListener('click', this.handlePrintClick);
            
            // Add new automatic print handler
            button.addEventListener('click', (e) => this.handlePrintClick(e));
        });
    }
    
    /**
     * Handle print button click
     */
    async handlePrintClick(event) {
        event.preventDefault();
        event.stopPropagation();

        const button = event.currentTarget;

        // Show loading state
        this.setButtonLoading(button, true);

        try {
            // Extract slip data directly from DOM
            const slipData = this.extractSlipDataFromDOM();

            // Print using DOM data
            await this.printSlipFromData(slipData);
            this.showSuccess('Slip printed successfully!');
        } catch (error) {
            this.showError('Print failed: ' + error.message);
        } finally {
            this.setButtonLoading(button, false);
        }
    }
    
    /**
     * Extract slip data directly from DOM elements
     */
    extractSlipDataFromDOM() {
        const slipBody = document.querySelector('.print-slip-body');
        if (!slipBody) {
            throw new Error('Betting slip content not found');
        }

        // Extract header information
        const headerText = slipBody.textContent;
        const dateMatch = headerText.match(/(\d{2}\/\d{2}\/\d{4}\s+\d{1,2}:\d{2}:\d{2}\s+[ap]m)/i);
        const playerMatch = headerText.match(/Player ID:\s*(\w+)/i);
        const drawMatch = headerText.match(/Draw #:\s*(\d+)/i);

        // Extract bet details
        const bets = [];
        const betElements = slipBody.querySelectorAll('div');

        for (let element of betElements) {
            const text = element.textContent.trim();

            // Look for bet patterns like "1. STRAIGHT: Straight Up on 4"
            const betMatch = text.match(/(\d+)\.\s*(\w+):\s*(.+?)Stake:\s*\$?([\d.]+).*?Pays:\s*([\d:]+).*?Return:\s*\$?([\d.]+)/s);

            if (betMatch) {
                bets.push({
                    type: betMatch[2].toLowerCase(),
                    description: betMatch[3].trim(),
                    amount: betMatch[4],
                    odds: betMatch[5],
                    potential_return: betMatch[6]
                });
            }
        }

        // Extract totals
        const totalStakeMatch = headerText.match(/Total Stakes:\s*\$?([\d.]+)/i);
        const drawNumberMatch = headerText.match(/Draw Number:\s*#?(\d+)/i);
        const slipNumberMatch = headerText.match(/(\d{8})/); // 8-digit number like 78697706

        // Calculate potential win (sum of all returns)
        const potentialWin = bets.reduce((sum, bet) => sum + parseFloat(bet.potential_return || 0), 0);

        return {
            slip_number: slipNumberMatch ? slipNumberMatch[1] : 'UNKNOWN',
            date: dateMatch ? dateMatch[1] : new Date().toLocaleString(),
            draw_number: drawMatch ? drawMatch[1] : (drawNumberMatch ? drawNumberMatch[1] : 'UNKNOWN'),
            player_id: playerMatch ? playerMatch[1] : 'GUEST',
            total_stake: totalStakeMatch ? totalStakeMatch[1] : '0.00',
            potential_win: potentialWin.toFixed(2),
            bets: bets
        };
    }

    /**
     * Extract slip ID from button or surrounding elements
     */
    getSlipIdFromButton(button) {
        // For DOM-based printing, we don't need a slip ID from database
        // Instead, we'll use a generated ID based on slip number or timestamp
        try {
            const slipData = this.extractSlipDataFromDOM();
            return slipData.slip_number || 'DOM_' + Date.now();
        } catch (error) {
            return 'DOM_' + Date.now();
        }
    }
    
    /**
     * Resolve slip number to slip ID (if needed)
     */
    async resolveSlipNumberToId(slipNumber) {
        // This would make an API call to convert slip number to slip ID
        // Implementation depends on your database structure
        return slipNumber; // Placeholder
    }
    
    /**
     * Print betting slip using DOM data
     */
    async printSlipFromData(slipData, printerName = null) {
        const formData = new FormData();
        formData.append('action', 'print_slip_data');
        formData.append('slip_data', JSON.stringify(slipData));

        if (printerName) {
            formData.append('printer_name', printerName);
        }

        const response = await fetch(this.apiUrl, {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Print failed');
        }

        return result;
    }

    /**
     * Print betting slip (legacy method for database lookup)
     */
    async printSlip(slipId, printerName = null) {
        const formData = new FormData();
        formData.append('action', 'print_slip');
        formData.append('slip_id', slipId);

        if (printerName) {
            formData.append('printer_name', printerName);
        }

        const response = await fetch(this.apiUrl, {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Print failed');
        }

        return result;
    }
    
    /**
     * Test print functionality
     */
    async testPrint(printerName = null) {
        const formData = new FormData();
        formData.append('action', 'test_print');
        
        if (printerName) {
            formData.append('printer_name', printerName);
        }
        
        const response = await fetch(this.apiUrl, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'Test print failed');
        }
        
        return result;
    }
    
    /**
     * Set button loading state
     */
    setButtonLoading(button, loading) {
        if (loading) {
            button.disabled = true;
            button.classList.add('printing');
            
            // Store original content
            button.dataset.originalContent = button.innerHTML;
            
            // Show loading state
            button.innerHTML = `
                <i class="fas fa-spinner fa-spin"></i> 
                Printing...
            `;
        } else {
            button.disabled = false;
            button.classList.remove('printing');
            
            // Restore original content
            if (button.dataset.originalContent) {
                button.innerHTML = button.dataset.originalContent;
                delete button.dataset.originalContent;
            }
        }
    }
    
    /**
     * Show success message
     */
    showSuccess(message) {
        this.showNotification(message, 'success');
    }
    
    /**
     * Show error message
     */
    showError(message) {
        this.showNotification(message, 'error');
    }
    
    /**
     * Show notification
     */
    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `print-notification print-notification-${type}`;
        notification.innerHTML = `
            <div class="print-notification-content">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <span>${message}</span>
                <button class="print-notification-close">&times;</button>
            </div>
        `;
        
        // Add styles
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            background: ${type === 'success' ? '#4CAF50' : '#f44336'};
            color: white;
            padding: 15px 20px;
            border-radius: 5px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            max-width: 400px;
            animation: slideIn 0.3s ease-out;
        `;
        
        // Add to page
        document.body.appendChild(notification);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
        
        // Close button handler
        const closeBtn = notification.querySelector('.print-notification-close');
        closeBtn.addEventListener('click', () => notification.remove());
    }
    
    /**
     * Show printer selection dialog
     */
    showPrinterDialog(callback) {
        if (this.availablePrinters.length === 0) {
            callback(null);
            return;
        }
        
        const dialog = document.createElement('div');
        dialog.className = 'printer-selection-dialog';
        dialog.innerHTML = `
            <div class="printer-dialog-overlay">
                <div class="printer-dialog-content">
                    <h3>Select Printer</h3>
                    <select id="printer-select">
                        <option value="">Default Printer</option>
                        ${this.availablePrinters.map(printer => 
                            `<option value="${printer}">${printer}</option>`
                        ).join('')}
                    </select>
                    <div class="printer-dialog-buttons">
                        <button id="printer-print-btn" class="btn btn-primary">Print</button>
                        <button id="printer-cancel-btn" class="btn btn-secondary">Cancel</button>
                    </div>
                </div>
            </div>
        `;
        
        // Add styles
        dialog.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 10001;
        `;
        
        document.body.appendChild(dialog);
        
        // Event handlers
        document.getElementById('printer-print-btn').addEventListener('click', () => {
            const selectedPrinter = document.getElementById('printer-select').value;
            dialog.remove();
            callback(selectedPrinter || null);
        });
        
        document.getElementById('printer-cancel-btn').addEventListener('click', () => {
            dialog.remove();
        });
    }
}

// Initialize automatic printer when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.automaticPrinter = new AutomaticPrinter();
});

// Add CSS for animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    .print-notification-content {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .print-notification-close {
        background: none;
        border: none;
        color: white;
        font-size: 18px;
        cursor: pointer;
        margin-left: auto;
    }
    
    .printer-dialog-overlay {
        background: rgba(0,0,0,0.5);
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .printer-dialog-content {
        background: white;
        padding: 20px;
        border-radius: 8px;
        min-width: 300px;
        text-align: center;
    }
    
    .printer-dialog-buttons {
        margin-top: 15px;
        display: flex;
        gap: 10px;
        justify-content: center;
    }
    
    .btn {
        padding: 8px 16px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }
    
    .btn-primary {
        background: #007bff;
        color: white;
    }
    
    .btn-secondary {
        background: #6c757d;
        color: white;
    }
    
    .printing {
        opacity: 0.7;
        pointer-events: none;
    }
`;
document.head.appendChild(style);
