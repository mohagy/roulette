0/**
 * Main Dashboard Logic
 * Coordinates all modules
 */

const Dashboard = {
    currentTab: 'dashboard',
    
    /**
     * Initialize dashboard
     */
    async init() {
        // Check authentication
        if (!MonitoringAuth.init() || !MonitoringAuth.isAuthenticated()) {
            window.location.href = 'login.html';
            return;
        }
        
        // Wait for Firebase
        if (typeof firebase === 'undefined') {
            window.addEventListener('firebase-ready', () => this.start());
        } else {
            this.start();
        }
    },
    
    /**
     * Start dashboard
     */
    async start() {
        // Setup tab navigation
        this.setupTabs();
        
        // Check which tab is initially active and load it
        const activeTab = document.querySelector('.nav-tab.active')?.dataset.tab || 'dashboard';
        this.currentTab = activeTab;
        
        // Load initial tab content
        if (this.currentTab === 'dashboard') {
            await this.loadDashboardTab();
        } else if (this.currentTab === 'analytics') {
            await AnalyticsModule.loadAll();
        } else if (this.currentTab === 'draws') {
            await DrawMonitorModule.init();
        } else if (this.currentTab === 'tvPreview') {
            await TVDisplayPreviewModule.init();
        } else if (this.currentTab === 'cashiers') {
            await CashierModule.loadCashiers();
        } else if (this.currentTab === 'maintenance') {
            await MaintenanceModule.runAllChecks();
        }
        
        // Setup auto-refresh
        setInterval(() => {
            if (this.currentTab === 'dashboard') {
                this.loadDashboardTab();
            } else if (this.currentTab === 'analytics') {
                AnalyticsModule.loadAll();
            } else if (this.currentTab === 'draws') {
                DrawMonitorModule.init();
            } else if (this.currentTab === 'tvPreview') {
                TVDisplayPreviewModule.loadAll();
            } else if (this.currentTab === 'cashiers') {
                CashierModule.loadCashiers();
            } else if (this.currentTab === 'maintenance') {
                MaintenanceModule.runAllChecks();
            }
        }, 30000); // Refresh every 30 seconds
    },
    
    /**
     * Setup tab navigation
     */
    setupTabs() {
        const tabs = document.querySelectorAll('.nav-tab');
        tabs.forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                const tabName = tab.dataset.tab;
                this.switchTab(tabName);
            });
        });
    },
    
    /**
     * Switch tab
     */
    switchTab(tabName) {
        this.currentTab = tabName;
        
        // Update active tab
        document.querySelectorAll('.nav-tab').forEach(t => {
            t.classList.remove('active');
        });
        document.querySelector(`[data-tab="${tabName}"]`)?.classList.add('active');
        
        // Show/hide tab content
        document.querySelectorAll('.tab-content').forEach(content => {
            content.style.display = 'none';
        });
        document.getElementById(`${tabName}Tab`)?.style.setProperty('display', 'block');
        
        // Load tab content
        if (tabName === 'dashboard') {
            this.loadDashboardTab();
        } else if (tabName === 'analytics') {
            AnalyticsModule.loadAll();
        } else if (tabName === 'draws') {
            DrawMonitorModule.init();
        } else if (tabName === 'tvPreview') {
            TVDisplayPreviewModule.init();
        } else if (tabName === 'cashiers') {
            CashierModule.loadCashiers();
        } else if (tabName === 'maintenance') {
            MaintenanceModule.runAllChecks();
        }
    },
    
    /**
     * Load dashboard tab
     */
    async loadDashboardTab() {
        // Load stats from database API
        await this.loadStats();
        
        // Load shops from database API (always fetch from database, not Firebase)
        await this.loadShops();
        
        // Initialize Firebase alerts (for real-time alerts only, not data storage)
        AlertsModule.init();
    },
    
    /**
     * Load dashboard stats from database
     */
    async loadStats() {
        try {
            const data = await safeFetch(`${API_BASE}/monitoring/get_dashboard_stats.php`);
            
            if (data.status === 'success' && data.data) {
                const stats = data.data;
                
                const totalAlertsEl = document.getElementById('totalAlerts');
                if (totalAlertsEl) totalAlertsEl.textContent = stats.total_active_alerts || 0;
                
                const criticalAlertsEl = document.getElementById('criticalAlerts');
                if (criticalAlertsEl) criticalAlertsEl.textContent = stats.critical_alerts || 0;
                
                const todayPayoutsEl = document.getElementById('todayPayouts');
                if (todayPayoutsEl) todayPayoutsEl.textContent = formatCurrency(stats.today_payouts_total || 0);
                
                const shopsMonitoringEl = document.getElementById('shopsMonitoring');
                if (shopsMonitoringEl) shopsMonitoringEl.textContent = stats.shops_under_monitoring || 0;
            }
        } catch (error) {
            console.error('Error loading stats:', error);
        }
    },
    
    /**
     * Load shops from database
     */
    async loadShops() {
        const container = document.getElementById('shopsContainer');
        if (!container) return;
        
        try {
            console.log('Loading shops from database...');
            const data = await safeFetch(`${API_BASE}/monitoring/get_shop_performance.php`);
            
            console.log('Shops API response:', data);
            
            if (data.status === 'success' && data.data && Array.isArray(data.data)) {
                if (data.data.length > 0) {
                    AlertsModule.displayShops(data.data);
                } else {
                    container.innerHTML = '<div class="alert alert-info"><i class="fas fa-info-circle"></i> No active shops found in database.</div>';
                }
            } else {
                container.innerHTML = `<div class="alert alert-warning">API returned: ${data.status || 'unknown status'}</div>`;
            }
        } catch (error) {
            console.error('Error loading shops:', error);
            const container = document.getElementById('shopsContainer');
            if (container) {
                container.innerHTML = `<div class="alert alert-danger">Error loading shops: ${error.message || 'Unknown error'}</div>`;
            }
        }
    },
    
    /**
     * Logout
     */
    logout() {
        MonitoringAuth.logout();
    }
};

// Initialize on page load
window.addEventListener('DOMContentLoaded', () => {
    Dashboard.init();
});

window.Dashboard = Dashboard;

