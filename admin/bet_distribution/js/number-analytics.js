/**
 * Number Analytics Module
 * Displays uncalled numbers, lowest/highest payout numbers
 */

class NumberAnalytics {
    constructor() {
        this.currentData = null;
        this.refreshInterval = null;
    }

    /**
     * Initialize the module
     */
    init() {
        console.log('📊 NumberAnalytics module initialized');
        this.loadAnalytics();
        // Auto-refresh every 30 seconds
        this.startAutoRefresh(30000);
    }

    /**
     * Load number analytics
     */
    async loadAnalytics(drawNumber = null) {
        try {
            console.log('📊 Loading number analytics...', drawNumber ? `for draw #${drawNumber}` : '');
            
            const response = await apiClient.getNumberAnalytics(drawNumber);
            
            if (response.status === 'success' && response.data) {
                this.currentData = response.data;
                this.updateDisplay();
                return this.currentData;
            } else {
                throw new Error(response.message || 'Failed to load number analytics');
            }
        } catch (error) {
            console.error('❌ Error loading number analytics:', error);
            this.showError(error.message);
            throw error;
        }
    }

    /**
     * Update the display
     */
    updateDisplay() {
        const container = Utils.$('#numberAnalyticsContent');
        if (!container || !this.currentData) return;

        const data = this.currentData;
        
        // Helper function to get number color class
        const getNumberColorClass = (number) => {
            if (number === 0) return 'success'; // Green
            const redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
            return redNumbers.includes(number) ? 'danger' : 'dark'; // Red or Black
        };

        // Build HTML
        let html = '';

        // Uncalled Numbers Section
        html += `
            <div class="mb-3">
                <h6 class="text-muted mb-2">
                    <i class="fas fa-snowflake"></i> Uncalled Numbers
                    <span class="badge bg-secondary ms-2">${data.uncalled_count}</span>
                </h6>
                <div class="uncalled-numbers-container" style="max-height: 120px; overflow-y: auto;">
        `;
        
        if (data.uncalled_numbers && data.uncalled_numbers.length > 0) {
            html += '<div class="d-flex flex-wrap gap-1">';
            data.uncalled_numbers.forEach(num => {
                const colorClass = getNumberColorClass(num);
                html += `<span class="badge bg-${colorClass}" style="font-size: 0.85rem;">${num}</span>`;
            });
            html += '</div>';
        } else {
            html += '<p class="text-muted small mb-0">All numbers have been called in recent draws</p>';
        }
        
        html += '</div></div>';

        // Lowest Payout Numbers Section
        html += `
            <div class="mb-3">
                <h6 class="text-muted mb-2">
                    <i class="fas fa-arrow-down text-success"></i> Lowest Payout Numbers
                </h6>
        `;
        
        if (data.lowest_payout_numbers && data.lowest_payout_numbers.length > 0) {
            const minPayout = data.lowest_payout_numbers[0].payout;
            html += `<div class="small text-muted mb-1">Payout: $${minPayout.toFixed(2)}</div>`;
            html += '<div class="d-flex flex-wrap gap-1">';
            data.lowest_payout_numbers.forEach(item => {
                const colorClass = getNumberColorClass(item.number);
                html += `<span class="badge bg-${colorClass}" style="font-size: 0.85rem;" title="Payout: $${item.payout.toFixed(2)}">${item.number}</span>`;
            });
            html += '</div>';
        } else {
            html += '<p class="text-muted small mb-0">No bets placed for this draw</p>';
        }
        
        html += '</div>';

        // Highest Payout Numbers Section
        html += `
            <div class="mb-3">
                <h6 class="text-muted mb-2">
                    <i class="fas fa-arrow-up text-danger"></i> Highest Payout Numbers
                </h6>
        `;
        
        if (data.highest_payout_numbers && data.highest_payout_numbers.length > 0) {
            const maxPayout = data.highest_payout_numbers[0].payout;
            html += `<div class="small text-muted mb-1">Payout: $${maxPayout.toFixed(2)}</div>`;
            html += '<div class="d-flex flex-wrap gap-1">';
            data.highest_payout_numbers.forEach(item => {
                const colorClass = getNumberColorClass(item.number);
                html += `<span class="badge bg-${colorClass}" style="font-size: 0.85rem;" title="Payout: $${item.payout.toFixed(2)}">${item.number}</span>`;
            });
            html += '</div>';
        } else {
            html += '<p class="text-muted small mb-0">No bets placed for this draw</p>';
        }
        
        html += '</div>';

        // Summary
        html += `
            <div class="border-top pt-2 mt-2">
                <small class="text-muted">
                    <i class="fas fa-info-circle"></i> 
                    Draw #${data.draw_number} | 
                    ${data.numbers_with_bets_count} numbers with bets
                </small>
            </div>
        `;

        container.innerHTML = html;
    }

    /**
     * Show error message
     */
    showError(message) {
        const container = Utils.$('#numberAnalyticsContent');
        if (container) {
            container.innerHTML = `
                <div class="alert alert-danger mb-0">
                    <i class="fas fa-exclamation-triangle"></i> 
                    ${message}
                </div>
            `;
        }
    }

    /**
     * Start auto-refresh
     */
    startAutoRefresh(interval = 30000) {
        this.stopAutoRefresh();
        this.refreshInterval = setInterval(() => {
            // Get selected draw if available
            let drawNumber = null;
            try {
                if (typeof drawSelection !== 'undefined' && drawSelection && typeof drawSelection.getSelectedDraw === 'function') {
                    const selectedDraw = drawSelection.getSelectedDraw();
                    if (selectedDraw && selectedDraw.draw_number) {
                        drawNumber = selectedDraw.draw_number;
                    }
                }
            } catch (e) {
                console.warn('⚠️ Could not get selected draw for analytics:', e);
            }
            this.loadAnalytics(drawNumber);
        }, interval);
    }

    /**
     * Stop auto-refresh
     */
    stopAutoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
    }
}

// Create global instance
const numberAnalytics = new NumberAnalytics();

