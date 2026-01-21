/**
 * Bet Distribution Module
 * Handles fetching and displaying bet distribution data
 */

class BetDistribution {
    constructor() {
        this.chart = null;
        this.betTypeChart = null;
        this.currentData = null;
        this.currentView = 'chart'; // 'chart' or 'grid'
    }

    /**
     * Initialize the module
     */
    init() {
        console.log('📊 BetDistribution module initialized');
        this.setupViewToggle();
    }

    /**
     * Setup view toggle (chart/grid)
     */
    setupViewToggle() {
        const viewTabs = Utils.$$('.view-tab');
        viewTabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const view = tab.getAttribute('data-view');
                this.switchView(view);
            });
        });
    }

    /**
     * Switch between chart and grid view
     */
    switchView(view) {
        this.currentView = view;
        
        // Update tabs
        Utils.$$('.view-tab').forEach(tab => {
            tab.classList.toggle('active', tab.getAttribute('data-view') === view);
        });

        // Show/hide containers
        const chartView = Utils.$('#chartView');
        const gridView = Utils.$('#gridView');
        
        if (chartView && gridView) {
            chartView.classList.toggle('active', view === 'chart');
            gridView.classList.toggle('active', view === 'grid');
        }

        // Re-render if data exists
        if (this.currentData) {
            if (view === 'chart') {
                this.renderChart();
            } else {
                this.renderGrid();
            }
        }
    }

    /**
     * Load and display bet distribution for a draw
     */
    async loadBetDistribution(drawNumber) {
        try {
            console.log(`📊 Loading bet distribution for draw #${drawNumber}...`);
            
            const data = await apiClient.getBetDistribution(drawNumber);
            
            if (data.status === 'success') {
                this.currentData = data;
                this.updateUI(data);
                return data;
            } else {
                throw new Error(data.message || 'Failed to load bet distribution');
            }
        } catch (error) {
            console.error('❌ Error loading bet distribution:', error);
            Utils.showError(Utils.$('#chartContainer'), `Failed to load bet distribution: ${error.message}`);
            throw error;
        }
    }

    /**
     * Update UI with bet distribution data
     */
    updateUI(data) {
        // Update draw number display
        const drawNumberEl = Utils.$('#upcomingDrawNumber');
        if (drawNumberEl) {
            drawNumberEl.textContent = `#${data.draw_number}`;
        }

        // Render based on current view
        if (this.currentView === 'chart') {
            this.renderChart();
        } else {
            this.renderGrid();
        }

        // Render bet type chart
        this.renderBetTypeChart(data);
    }

    /**
     * Render chart view
     */
    renderChart() {
        if (!this.currentData) return;

        const container = Utils.$('#chartContainer');
        if (!container) return;

        const numbers = this.currentData.numbers || [];
        const chartData = [];
        const colors = [];

        // Prepare data for chart
        for (let i = 0; i <= 36; i++) {
            const numData = Array.isArray(numbers) ? numbers[i] : numbers[i] || {};
            const betCount = numData.bet_count || 0;
            const totalPayout = numData.total_payout || 0;
            
            chartData.push({
                x: i.toString(),
                y: betCount,
                payout: totalPayout
            });

            // Color based on bet presence
            colors.push(betCount > 0 ? '#4e73df' : '#e3e6f0');
        }

        // Destroy existing chart
        if (this.chart) {
            this.chart.destroy();
        }

        // Create new chart
        const options = {
            series: [{
                name: 'Bet Count',
                data: chartData.map(d => d.y)
            }],
            chart: {
                type: 'bar',
                height: 400,
                toolbar: { show: false }
            },
            plotOptions: {
                bar: {
                    distributed: true,
                    borderRadius: 4,
                    dataLabels: {
                        position: 'top'
                    }
                }
            },
            dataLabels: {
                enabled: true,
                formatter: (val) => val > 0 ? val : '',
                offsetY: -20,
                style: {
                    fontSize: '12px',
                    colors: ['#5a5c69']
                }
            },
            xaxis: {
                categories: chartData.map(d => d.x),
                title: { text: 'Number' }
            },
            yaxis: {
                title: { text: 'Bet Count' }
            },
            colors: colors,
            tooltip: {
                custom: ({ seriesIndex, dataPointIndex, w }) => {
                    const data = chartData[dataPointIndex];
                    return `
                        <div style="padding: 10px;">
                            <div><strong>Number:</strong> ${data.x}</div>
                            <div><strong>Bets:</strong> ${data.y}</div>
                            <div><strong>Payout:</strong> ${Utils.formatCurrency(data.payout)}</div>
                        </div>
                    `;
                }
            },
            legend: { show: false }
        };

        this.chart = new ApexCharts(container, options);
        this.chart.render();
    }

    /**
     * Render grid view
     */
    renderGrid() {
        if (!this.currentData) return;

        const container = Utils.$('#betInfoGrid');
        if (!container) return;

        const numbers = this.currentData.numbers || [];
        container.innerHTML = '';

        // Create grid
        for (let i = 0; i <= 36; i++) {
            const numData = Array.isArray(numbers) ? numbers[i] : numbers[i] || {};
            const betCount = numData.bet_count || 0;
            const totalStake = numData.total_stake || 0;
            const totalPayout = numData.total_payout || 0;
            const color = Utils.getNumberColor(i);
            const hasBets = betCount > 0;

            const cell = Utils.createElement('div', {
                class: `bet-number-cell ${hasBets ? 'has-bets' : 'no-bets'}`,
                'data-number': i
            });

            cell.innerHTML = `
                <div class="number-header">
                    <span class="number-value">${i}</span>
                    <span class="number-color ${color}"></span>
                </div>
                <div class="bet-info">
                    <div class="bet-stat">
                        <span class="label">Bets:</span>
                        <span class="value">${betCount}</span>
                    </div>
                    <div class="bet-stat">
                        <span class="label">Stake:</span>
                        <span class="value">${Utils.formatCurrency(totalStake)}</span>
                    </div>
                    <div class="bet-stat">
                        <span class="label">Payout:</span>
                        <span class="value">${Utils.formatCurrency(totalPayout)}</span>
                    </div>
                </div>
            `;

            container.appendChild(cell);
        }
    }

    /**
     * Render bet type distribution chart
     */
    renderBetTypeChart(data) {
        const container = Utils.$('#betTypeChartContainer');
        if (!container) return;

        const betTypes = data.bet_types || {};
        const categories = [];
        const series = [];

        Object.entries(betTypes).forEach(([type, stats]) => {
            if (stats.bet_count > 0) {
                categories.push(type.charAt(0).toUpperCase() + type.slice(1));
                series.push(stats.bet_count);
            }
        });

        if (categories.length === 0) {
            container.innerHTML = '<div class="text-center py-5"><p>No bet type data available</p></div>';
            return;
        }

        // Destroy existing chart
        if (this.betTypeChart) {
            this.betTypeChart.destroy();
        }

        const options = {
            series: series,
            chart: {
                type: 'pie',
                height: 350
            },
            labels: categories,
            legend: {
                position: 'bottom'
            },
            tooltip: {
                y: {
                    formatter: (val) => `${val} bets`
                }
            }
        };

        this.betTypeChart = new ApexCharts(container, options);
        this.betTypeChart.render();
    }

    /**
     * Get current data
     */
    getCurrentData() {
        return this.currentData;
    }

    /**
     * Clear data
     */
    clear() {
        if (this.chart) {
            this.chart.destroy();
            this.chart = null;
        }
        if (this.betTypeChart) {
            this.betTypeChart.destroy();
            this.betTypeChart = null;
        }
        this.currentData = null;
    }
}

// Create global instance
const betDistribution = new BetDistribution();

