/**
 * Automatic Print Override
 * This script forcefully overrides all existing print handlers to enable automatic printing
 * It takes priority over all other print button implementations
 */

(function() {
    'use strict';
    
    console.log('🖨️ Automatic Print Override Loading...');
    
    let isInitialized = false;
    let printAPI = 'api/php_print_solution.php?v=' + Date.now();
    
    /**
     * Extract betting slip data from DOM
     */
    function extractSlipDataFromDOM() {
        const slipBody = document.querySelector('.print-slip-body');
        if (!slipBody) {
            throw new Error('Betting slip content not found');
        }
        
        const slipText = slipBody.textContent;
        
        // Extract header information
        const dateMatch = slipText.match(/(\d{2}\/\d{2}\/\d{4}\s+\d{1,2}:\d{2}:\d{2}\s+[ap]m)/i);
        const playerMatch = slipText.match(/Player ID:\s*(\w+)/i);
        const drawMatch = slipText.match(/Draw #:\s*(\d+)/i);
        
        // Extract bets
        const bets = [];
        const lines = slipText.split('\n').map(line => line.trim()).filter(line => line);
        
        for (let i = 0; i < lines.length; i++) {
            const line = lines[i];
            const betHeaderMatch = line.match(/^(\d+)\.\s*(\w+):\s*(.+)/);
            
            if (betHeaderMatch) {
                const betType = betHeaderMatch[2];
                const betDescription = betHeaderMatch[3];
                
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
        const slipNumberMatch = slipText.match(/(\d{7,8})/);
        
        const potentialWin = bets.reduce((sum, bet) => sum + parseFloat(bet.potential_return || 0), 0);
        
        return {
            slip_number: slipNumberMatch ? slipNumberMatch[1] : 'AUTO_' + Date.now(),
            date: dateMatch ? dateMatch[1] : new Date().toLocaleString(),
            draw_number: drawMatch ? drawMatch[1] : (drawNumberMatch ? drawNumberMatch[1] : 'UNKNOWN'),
            player_id: playerMatch ? playerMatch[1] : 'GUEST',
            total_stake: totalStakeMatch ? totalStakeMatch[1] : '0.00',
            potential_win: potentialWin.toFixed(2),
            bets: bets
        };
    }
    
    /**
     * Print slip using automatic printing API
     */
    async function automaticPrint(slipData) {
        try {
            console.log('🔗 API Endpoint:', printAPI);
            console.log('📤 Sending data:', slipData);

            const formData = new FormData();
            formData.append('action', 'print_slip_data');
            formData.append('slip_data', JSON.stringify(slipData));

            console.log('📡 Making fetch request to:', printAPI);

            const response = await fetch(printAPI, {
                method: 'POST',
                body: formData
            });

            console.log('📥 Response status:', response.status);
            console.log('📥 Response headers:', response.headers);

            const responseText = await response.text();
            console.log('📄 Raw response:', responseText);

            let result;
            try {
                result = JSON.parse(responseText);
            } catch (parseError) {
                console.error('❌ JSON parse error:', parseError);
                console.error('📄 Response text:', responseText);
                throw new Error('Invalid JSON response from server');
            }

            console.log('✅ Parsed result:', result);

            if (!result.success) {
                throw new Error(result.error || 'Print failed');
            }

            return result;
        } catch (error) {
            console.error('❌ Automatic print failed:', error);
            throw error;
        }
    }
    
    /**
     * Show notification
     */
    function showNotification(message, type = 'info') {
        // Remove existing notifications
        const existing = document.querySelectorAll('.auto-print-notification');
        existing.forEach(n => n.remove());
        
        const notification = document.createElement('div');
        notification.className = `auto-print-notification auto-print-${type}`;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#007bff'};
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
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'print'}"></i>
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" 
                        style="background: none; border: none; color: white; font-size: 18px; cursor: pointer; margin-left: auto;">
                    &times;
                </button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }
    
    /**
     * Handle automatic print
     */
    async function handleAutomaticPrint(event) {
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
        
        const button = event.currentTarget;
        const originalContent = button.innerHTML;
        
        try {
            // Show loading state
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Printing...';
            
            console.log('🖨️ Starting automatic print...');
            
            // Extract slip data
            const slipData = extractSlipDataFromDOM();
            console.log('📄 Extracted slip data:', slipData);
            
            // Validate data
            if (!slipData.bets || slipData.bets.length === 0) {
                throw new Error('No bets found in betting slip');
            }
            
            // Print automatically
            const result = await automaticPrint(slipData);
            console.log('✅ Print result:', result);
            
            showNotification('Betting slip printed automatically!', 'success');
            
        } catch (error) {
            console.error('❌ Automatic print failed:', error);
            showNotification('Print failed: ' + error.message, 'error');
        } finally {
            // Restore button
            button.disabled = false;
            button.innerHTML = originalContent;
        }
        
        return false;
    }
    
    /**
     * Override all print button handlers
     */
    function overridePrintButtons() {
        const printButtons = document.querySelectorAll('.print-action-button, [data-action="print"], #print-betting-slip-btn');
        
        console.log(`🔄 Found ${printButtons.length} print button(s) to override`);
        
        printButtons.forEach((button, index) => {
            // Remove all existing event listeners by cloning the button
            const newButton = button.cloneNode(true);
            button.parentNode.replaceChild(newButton, button);
            
            // Add our automatic print handler with highest priority
            newButton.addEventListener('click', handleAutomaticPrint, true);
            
            // Also add as regular listener for fallback
            newButton.addEventListener('click', handleAutomaticPrint);
            
            console.log(`✅ Override applied to print button ${index + 1}`);
        });
    }
    
    /**
     * Initialize automatic print override
     */
    function init() {
        if (isInitialized) return;
        
        console.log('🚀 Initializing Automatic Print Override...');
        
        // Override existing buttons
        overridePrintButtons();
        
        // Watch for new print buttons
        const observer = new MutationObserver(function(mutations) {
            let shouldOverride = false;
            
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) {
                        if (node.classList && (node.classList.contains('print-action-button') || node.dataset.action === 'print')) {
                            shouldOverride = true;
                        } else if (node.querySelector && node.querySelector('.print-action-button, [data-action="print"]')) {
                            shouldOverride = true;
                        }
                    }
                });
            });
            
            if (shouldOverride) {
                setTimeout(overridePrintButtons, 100);
            }
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        
        isInitialized = true;
        console.log('✅ Automatic Print Override initialized successfully!');
        
        // Test notification
        setTimeout(() => {
            showNotification('Automatic printing system active!', 'success');
        }, 1000);
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    // Also initialize after a delay to catch dynamically added buttons
    setTimeout(init, 2000);
    setTimeout(init, 5000);
    
    // Add CSS for animations
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
    
    console.log('🖨️ Automatic Print Override loaded!');
    
})();
