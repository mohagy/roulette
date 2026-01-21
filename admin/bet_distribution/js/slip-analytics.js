/**
 * Slip Analytics Module
 * Displays betting slips and shows which would win for a test number
 */

class SlipAnalytics {
    constructor() {
        this.currentData = null;
        this.currentDrawNumber = null;
        this.testNumber = null;
        this.refreshInterval = null;
    }

    /**
     * Initialize the module
     */
    init() {
        console.log('🎫 SlipAnalytics module initialized');
        this.setupEventListeners();
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        const testBtn = Utils.$('#testNumberBtn');
        const clearBtn = Utils.$('#clearTestBtn');
        const testInput = Utils.$('#testWinningNumber');
        
        if (testBtn) {
            testBtn.addEventListener('click', () => this.testNumber());
        }
        
        if (clearBtn) {
            clearBtn.addEventListener('click', () => this.clearTest());
        }
        
        if (testInput) {
            testInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.testNumber();
                }
            });
        }
    }

    /**
     * Load slip analytics
     */
    async loadSlipAnalytics(drawNumber, testNumber = null) {
        try {
            this.currentDrawNumber = drawNumber;
            this.testNumber = testNumber;
            
            console.log('🎫 Loading slip analytics for draw #' + drawNumber, testNumber !== null ? `(testing number ${testNumber})` : '');
            
            const response = await apiClient.getSlipAnalytics(drawNumber, testNumber);
            
            if (response.status === 'success' && response.data) {
                this.currentData = response.data;
                this.updateDisplay();
                return this.currentData;
            } else {
                throw new Error(response.message || 'Failed to load slip analytics');
            }
        } catch (error) {
            console.error('❌ Error loading slip analytics:', error);
            this.showError(error.message);
            throw error;
        }
    }

    /**
     * Test a winning number
     */
    async testNumber() {
        const testInput = Utils.$('#testWinningNumber');
        if (!testInput) return;
        
        const number = parseInt(testInput.value.trim());
        if (isNaN(number) || number < 0 || number > 36) {
            alert('Please enter a valid number (0-36)');
            return;
        }
        
        if (!this.currentDrawNumber) {
            // Try to get current draw number
            try {
                const drawInfo = await apiClient.getDrawInfo();
                if (drawInfo.status === 'success' && drawInfo.data) {
                    this.currentDrawNumber = drawInfo.data.current_draw || drawInfo.data.current_draw_number;
                }
            } catch (e) {
                console.warn('⚠️ Could not get draw number:', e);
            }
        }
        
        if (!this.currentDrawNumber) {
            alert('Please select a draw first');
            return;
        }
        
        await this.loadSlipAnalytics(this.currentDrawNumber, number);
        
        // Show clear button
        const clearBtn = Utils.$('#clearTestBtn');
        if (clearBtn) {
            clearBtn.style.display = 'inline-block';
        }
    }

    /**
     * Clear test number
     */
    async clearTest() {
        const testInput = Utils.$('#testWinningNumber');
        const clearBtn = Utils.$('#clearTestBtn');
        
        if (testInput) {
            testInput.value = '';
        }
        if (clearBtn) {
            clearBtn.style.display = 'none';
        }
        
        this.testNumber = null;
        
        if (this.currentDrawNumber) {
            await this.loadSlipAnalytics(this.currentDrawNumber, null);
        }
    }

    /**
     * Update the display
     */
    updateDisplay() {
        const container = Utils.$('#slipAnalyticsContent');
        const summary = Utils.$('#slipAnalyticsSummary');
        
        if (!container || !this.currentData) return;

        const data = this.currentData;
        
        // Update summary
        if (summary) {
            Utils.$('#totalSlipsCount').textContent = data.total_slips || 0;
            Utils.$('#winningSlipsCount').textContent = data.winning_slips_count || 0;
            Utils.$('#totalPayoutAmount').textContent = Utils.formatCurrency(data.total_potential_payout || 0);
            summary.style.display = 'block';
        }
        
        // Helper function to get number color class
        const getNumberColorClass = (number) => {
            if (number === 0) return 'success'; // Green
            const redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
            return redNumbers.includes(number) ? 'danger' : 'dark'; // Red or Black
        };

        // Build HTML
        let html = '';
        
        if (data.test_number !== null) {
            const colorClass = getNumberColorClass(data.test_number);
            const drawNumber = this.currentDrawNumber || data.draw_number || 'N/A';
            html += `
                <div class="alert alert-info mb-3">
                    <strong>Testing Number:</strong> 
                    <span class="badge bg-${colorClass}">${data.test_number}</span>
                    <span class="badge bg-${colorClass === 'success' ? 'success' : colorClass === 'danger' ? 'danger' : 'dark'} ms-1">${data.test_color}</span>
                    <span class="badge bg-secondary ms-2">Draw #${drawNumber}</span>
                    <br>
                    <small class="text-muted">
                        ${data.winning_slips_count} slip(s) would win | 
                        Total Payout: ${Utils.formatCurrency(data.winning_slips_payout || 0)}
                    </small>
                </div>
            `;
        }
        
        if (data.slips && data.slips.length > 0) {
            html += '<div class="slips-list" style="max-height: 400px; overflow-y: auto;">';
            
            data.slips.forEach((slip, index) => {
                const wouldWin = slip.would_win || false;
                const slipClass = wouldWin ? 'border-success' : 'border-secondary';
                const slipBg = wouldWin ? 'bg-light' : '';
                
                html += `
                    <div class="card mb-2 ${slipClass} ${slipBg}" style="border-width: 2px;">
                        <div class="card-body p-2">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <strong>Slip #${slip.slip_number}</strong>
                                    ${wouldWin ? '<span class="badge bg-success ms-2">Would Win</span>' : ''}
                                </div>
                                <div class="text-end">
                                    <div class="small text-muted">Stake</div>
                                    <div class="fw-bold">${Utils.formatCurrency(slip.total_stake)}</div>
                                </div>
                            </div>
                            
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <div class="small text-muted">Potential Payout</div>
                                    <div class="fw-bold ${wouldWin ? 'text-success' : ''}">${Utils.formatCurrency(slip.total_potential_payout)}</div>
                                </div>
                                <div class="col-6">
                                    <div class="small text-muted">Bets</div>
                                    <div>${slip.bet_count} bet(s)</div>
                                </div>
                            </div>
                `;
                
                if (wouldWin && slip.winning_bets_count > 0) {
                    html += `
                        <div class="alert alert-success py-1 mb-2">
                            <small>
                                <i class="fas fa-check-circle"></i> 
                                ${slip.winning_bets_count} winning bet(s) | 
                                Payout: ${Utils.formatCurrency(slip.winning_payout)}
                            </small>
                        </div>
                    `;
                }
                
                // Show bets (collapsible)
                if (slip.bets && slip.bets.length > 0) {
                    html += `
                        <div class="bets-details">
                            <button class="btn btn-sm btn-outline-secondary w-100" type="button" data-bs-toggle="collapse" data-bs-target="#bets-${slip.slip_id}" aria-expanded="false">
                                <i class="fas fa-chevron-down"></i> View ${slip.bets.length} Bet(s)
                            </button>
                            <div class="collapse mt-2" id="bets-${slip.slip_id}">
                                <div class="small">
                    `;
                    
                    slip.bets.forEach(bet => {
                        const betWins = bet.would_win || false;
                        const betClass = betWins ? 'text-success' : 'text-muted';
                        html += `
                            <div class="mb-1 ${betClass}">
                                <i class="fas ${betWins ? 'fa-check-circle' : 'fa-circle'}"></i>
                                <strong>${bet.bet_type}</strong>: ${bet.bet_description}
                                <span class="float-end">${Utils.formatCurrency(bet.bet_amount)} → ${Utils.formatCurrency(bet.potential_return)}</span>
                            </div>
                        `;
                    });
                    
                    html += `
                                </div>
                            </div>
                        </div>
                    `;
                }
                
                html += `
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
        } else {
            html += `
                <div class="alert alert-secondary mb-0">
                    <i class="fas fa-info-circle"></i> 
                    No betting slips found for this draw
                </div>
            `;
        }

        container.innerHTML = html;
    }

    /**
     * Show error message
     */
    showError(message) {
        const container = Utils.$('#slipAnalyticsContent');
        if (container) {
            container.innerHTML = `
                <div class="alert alert-danger mb-0">
                    <i class="fas fa-exclamation-triangle"></i> 
                    ${message}
                </div>
            `;
        }
    }
}

// Create global instance
const slipAnalytics = new SlipAnalytics();

