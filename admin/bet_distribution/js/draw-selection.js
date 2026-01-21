/**
 * Draw Selection Module
 * Handles draw selection, tabs, navigation, and overview table
 */

class DrawSelection {
    constructor() {
        this.draws = [];
        this.selectedIndex = 0;
        this.refreshInterval = null;
    }

    /**
     * Initialize the module
     */
    init() {
        console.log('🎯 DrawSelection module initialized');
        this.setupNavigation();
    }

    /**
     * Setup navigation buttons
     */
    setupNavigation() {
        const prevBtn = Utils.$('#prevDraw');
        const nextBtn = Utils.$('#nextDraw');

        if (prevBtn) {
            prevBtn.addEventListener('click', () => this.selectPrevious());
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', () => this.selectNext());
        }
    }

    /**
     * Load upcoming draws
     */
    async loadUpcomingDraws(count = 10) {
        try {
            console.log(`📋 Loading ${count} upcoming draws...`);
            
            const response = await apiClient.getUpcomingDraws(count);
            
            if (response.status === 'success' && response.data) {
                this.draws = response.data.upcoming_draws || [];
                this.updateOverviewTable();
                this.updateDrawTabs();
                
                // Select first draw by default
                if (this.draws.length > 0) {
                    this.selectDraw(0);
                }
                
                return this.draws;
            } else {
                throw new Error(response.message || 'Failed to load upcoming draws');
            }
        } catch (error) {
            console.error('❌ Error loading upcoming draws:', error);
            Utils.showError(Utils.$('#upcomingDrawsTable tbody'), `Failed to load draws: ${error.message}`);
            throw error;
        }
    }

    /**
     * Update overview table
     */
    updateOverviewTable() {
        const tbody = Utils.$('#upcomingDrawsTable tbody');
        if (!tbody) return;

        tbody.innerHTML = '';

        this.draws.forEach((draw, index) => {
            const row = Utils.createElement('tr', {
                class: `draw-row ${draw.is_next ? 'current' : ''}`,
                'data-draw-index': index
            });

            row.innerHTML = `
                <td>
                    <strong>#${draw.draw_number}</strong>
                    ${draw.is_next ? '<span class="badge bg-success ms-1">Current</span>' : ''}
                </td>
                <td>${draw.estimated_time || 'TBD'}</td>
                <td>
                    <span class="badge bg-info">${draw.betting_slips_count}</span>
                </td>
                <td>${Utils.formatCurrency(draw.total_stake_amount)}</td>
                <td>
                    <button class="btn btn-sm btn-primary" data-draw-index="${index}">
                        <i class="fas fa-eye"></i> View
                    </button>
                </td>
            `;

            // Add click handler
            row.addEventListener('click', () => this.selectDraw(index));
            const viewBtn = row.querySelector('button');
            if (viewBtn) {
                viewBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.selectDraw(index);
                });
            }

            tbody.appendChild(row);
        });
    }

    /**
     * Update draw tabs
     */
    updateDrawTabs() {
        const container = Utils.$('#drawTabs');
        if (!container) return;

        container.innerHTML = '';

        this.draws.forEach((draw, index) => {
            const tab = Utils.createElement('div', {
                class: `draw-tab ${draw.is_next ? 'current' : ''} ${index === this.selectedIndex ? 'active' : ''}`,
                'data-draw-index': index
            });

            tab.innerHTML = `
                <div class="draw-tab-number">#${draw.draw_number}</div>
                <div class="draw-tab-time">${draw.estimated_time || 'TBD'}</div>
                <div class="draw-tab-stats">${draw.betting_slips_count} slips</div>
            `;

            tab.addEventListener('click', () => this.selectDraw(index));
            container.appendChild(tab);
        });
    }

    /**
     * Select a draw
     */
    async selectDraw(index) {
        if (index < 0 || index >= this.draws.length) return;

        this.selectedIndex = index;
        const draw = this.draws[index];
        
        // Update global state
        if (typeof AppState !== 'undefined') {
            AppState.currentDraw = draw.draw_number;
        }

        console.log(`🎯 Selecting draw #${draw.draw_number} (index ${index})`);

        // Update tab active state
        Utils.$$('.draw-tab').forEach((tab, i) => {
            tab.classList.toggle('active', i === index);
        });

        // Update navigation buttons
        const prevBtn = Utils.$('#prevDraw');
        const nextBtn = Utils.$('#nextDraw');
        if (prevBtn) prevBtn.disabled = index === 0;
        if (nextBtn) nextBtn.disabled = index === this.draws.length - 1;

        // Update draw number display
        const drawNumberEl = Utils.$('#upcomingDrawNumber');
        if (drawNumberEl) {
            drawNumberEl.textContent = `#${draw.draw_number}`;
        }

        // Update status badge
        const statusBadge = Utils.$('#drawStatus');
        if (statusBadge) {
            if (draw.is_next) {
                statusBadge.textContent = 'Current';
                statusBadge.className = 'badge bg-success';
            } else {
                statusBadge.textContent = `+${draw.minutes_from_now} min`;
                statusBadge.className = 'badge bg-primary';
            }
        }

        // Load bet distribution for this draw
        try {
            await betDistribution.loadBetDistribution(draw.draw_number);
        } catch (error) {
            console.error('Error loading bet distribution:', error);
        }

        // Trigger custom event
        window.dispatchEvent(new CustomEvent('drawSelected', {
            detail: { draw, index }
        }));
        
        // Refresh forced number checker for the selected draw
        if (typeof forcedNumbers !== 'undefined' && forcedNumbers) {
            console.log('🔄 Refreshing forced number checker for selected draw #' + draw.draw_number);
            const autoApplyCheckbox = Utils.$('#autoApplyForcedNumber');
            const shouldAutoApply = autoApplyCheckbox ? autoApplyCheckbox.checked : false;
            forcedNumbers.checkForcedNumber(shouldAutoApply);
        }
        
        // Refresh number analytics for the selected draw
        if (typeof numberAnalytics !== 'undefined' && numberAnalytics) {
            console.log('🔄 Refreshing number analytics for selected draw #' + draw.draw_number);
            numberAnalytics.loadAnalytics(draw.draw_number);
        }
        
        // Refresh slip analytics for the selected draw
        if (typeof slipAnalytics !== 'undefined' && slipAnalytics) {
            console.log('🔄 Refreshing slip analytics for selected draw #' + draw.draw_number);
            // Keep test number if one was set
            const testInput = Utils.$('#testWinningNumber');
            const testNumber = testInput && testInput.value ? parseInt(testInput.value) : null;
            slipAnalytics.loadSlipAnalytics(draw.draw_number, testNumber);
        }
    }

    /**
     * Select previous draw
     */
    selectPrevious() {
        if (this.selectedIndex > 0) {
            this.selectDraw(this.selectedIndex - 1);
        }
    }

    /**
     * Select next draw
     */
    selectNext() {
        if (this.selectedIndex < this.draws.length - 1) {
            this.selectDraw(this.selectedIndex + 1);
        }
    }

    /**
     * Get selected draw
     */
    getSelectedDraw() {
        return this.draws[this.selectedIndex] || null;
    }

    /**
     * Get all draws
     */
    getDraws() {
        return this.draws;
    }

    /**
     * Start auto-refresh
     */
    startAutoRefresh(interval = 15000) {
        this.stopAutoRefresh();
        this.refreshInterval = setInterval(() => {
            this.loadUpcomingDraws();
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
const drawSelection = new DrawSelection();

