/**
 * Roulette Betting Slip Automatic Printing Integration
 * Specifically designed for the roulette application at index.php
 */

class RoulettePrintIntegration {
    constructor() {
        this.apiUrl = 'api/print_slip_api.php';
        this.init();
    }
    
    async init() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setupPrintButton());
        } else {
            this.setupPrintButton();
        }
    }
    
    setupPrintButton() {
        // Find the print button
        const printButton = document.querySelector('.print-action-button');
        
        if (printButton) {
            // Remove any existing click handlers
            printButton.removeEventListener('click', this.handlePrintClick);
            
            // Add our automatic print handler
            printButton.addEventListener('click', (e) => this.handlePrintClick(e));
            
            console.log('✅ Automatic printing enabled for betting slip');
        } else {
            console.warn('⚠️ Print button not found, retrying in 1 second...');
            setTimeout(() => this.setupPrintButton(), 1000);
        }
    }
    
    async handlePrintClick(event) {
        event.preventDefault();
        event.stopPropagation();
        
        const button = event.currentTarget;
        
        try {
            // Show loading state
            this.setButtonLoading(button, true);
            
            // Extract slip data from DOM
            const slipData = this.extractSlipDataFromDOM();
            
            // Validate data
            if (!slipData.bets || slipData.bets.length === 0) {
                throw new Error('No bets found in betting slip');
            }
            
            // Print the slip
            await this.printSlip(slipData);
            
            // Show success message
            this.showNotification('Betting slip printed successfully!', 'success');
            
        } catch (error) {
            console.error('Print error:', error);
            this.showNotification('Print failed: ' + error.message, 'error');
        } finally {
            this.setButtonLoading(button, false);
        }
    }
    
    extractSlipDataFromDOM() {
        const slipBody = document.querySelector('.print-slip-body');
        if (!slipBody) {
            throw new Error('Betting slip content not found');
        }
        
        const slipText = slipBody.textContent;
        
        // Extract header information
        const dateMatch = slipText.match(/(\d{2}\/\d{2}\/\d{4}\s+\d{1,2}:\d{2}:\d{2}\s+[ap]m)/i);
        const playerMatch = slipText.match(/Player ID:\s*(\w+)/i);
        const drawMatch = slipText.match(/Draw #:\s*(\d+)/i);
        
        // Extract bets using more specific pattern matching
        const bets = [];
        const lines = slipText.split('\n').map(line => line.trim()).filter(line => line);
        
        for (let i = 0; i < lines.length; i++) {
            const line = lines[i];
            
            // Look for bet number pattern: "1. STRAIGHT: Straight Up on 4"
            const betHeaderMatch = line.match(/^(\d+)\.\s*(\w+):\s*(.+)/);
            
            if (betHeaderMatch) {
                const betNumber = betHeaderMatch[1];
                const betType = betHeaderMatch[2];
                const betDescription = betHeaderMatch[3];
                
                // Look for stake in next few lines
                let stake = '0.00';
                let odds = '1:1';
                let returns = '0.00';
                
                for (let j = i + 1; j < Math.min(i + 5, lines.length); j++) {
                    const nextLine = lines[j];
                    
                    const stakeMatch = nextLine.match(/Stake:\s*\$?([\d.]+)/i);
                    if (stakeMatch) stake = stakeMatch[1];
                    
                    const oddsMatch = nextLine.match(/Pays:\s*([\d:]+)/i);
                    if (oddsMatch) odds = oddsMatch[1];
                    
                    const returnMatch = nextLine.match(/Return:\s*\$?([\d.]+)/i);
                    if (returnMatch) returns = returnMatch[1];
                }
                
                bets.push({
                    type: betType.toLowerCase(),
                    description: betDescription,
                    amount: stake,
                    odds: odds,
                    potential_return: returns
                });
            }
        }
        
        // Extract totals
        const totalStakeMatch = slipText.match(/Total Stakes:\s*\$?([\d.]+)/i);
        const drawNumberMatch = slipText.match(/Draw Number:\s*#?(\d+)/i);
        const slipNumberMatch = slipText.match(/(\d{7,8})/); // 7-8 digit slip number
        
        // Calculate potential win
        const potentialWin = bets.reduce((sum, bet) => sum + parseFloat(bet.potential_return || 0), 0);
        
        const slipData = {
            slip_number: slipNumberMatch ? slipNumberMatch[1] : 'SLIP_' + Date.now(),
            date: dateMatch ? dateMatch[1] : new Date().toLocaleString(),
            draw_number: drawMatch ? drawMatch[1] : (drawNumberMatch ? drawNumberMatch[1] : 'UNKNOWN'),
            player_id: playerMatch ? playerMatch[1] : 'GUEST',
            total_stake: totalStakeMatch ? totalStakeMatch[1] : '0.00',
            potential_win: potentialWin.toFixed(2),
            bets: bets
        };
        
        console.log('Extracted slip data:', slipData);
        return slipData;
    }
    
    async printSlip(slipData, printerName = null) {
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
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'Print failed');
        }
        
        return result;
    }
    
    setButtonLoading(button, loading) {
        if (loading) {
            button.disabled = true;
            button.style.opacity = '0.7';
            
            // Store original content
            button.dataset.originalContent = button.innerHTML;
            
            // Show loading state
            button.innerHTML = `
                <i class="fas fa-spinner fa-spin"></i> 
                Printing...
            `;
        } else {
            button.disabled = false;
            button.style.opacity = '1';
            
            // Restore original content
            if (button.dataset.originalContent) {
                button.innerHTML = button.dataset.originalContent;
                delete button.dataset.originalContent;
            }
        }
    }
    
    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `print-notification print-notification-${type}`;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            background: ${type === 'success' ? '#28a745' : '#dc3545'};
            color: white;
            padding: 15px 20px;
            border-radius: 5px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            max-width: 400px;
            font-family: Arial, sans-serif;
            font-size: 14px;
            animation: slideInRight 0.3s ease-out;
        `;
        
        notification.innerHTML = `
            <div style="display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" 
                        style="background: none; border: none; color: white; font-size: 18px; cursor: pointer; margin-left: auto;">
                    &times;
                </button>
            </div>
        `;
        
        // Add to page
        document.body.appendChild(notification);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }
}

// Add CSS animation
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
`;
document.head.appendChild(style);

// Initialize the integration
window.roulettePrintIntegration = new RoulettePrintIntegration();
