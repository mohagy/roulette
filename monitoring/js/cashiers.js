/**
 * Cashier Monitoring Module
 * Monitors cashier POS till balances and activity
 */

const CashierModule = {
    /**
     * Load cashiers data
     */
    async loadCashiers() {
        const container = document.getElementById('cashiersContainer');
        const loadingEl = document.getElementById('cashiersLoading');
        const errorEl = document.getElementById('cashiersError');
        
        if (!container) return;
        
        if (loadingEl) loadingEl.style.display = 'block';
        if (errorEl) errorEl.style.display = 'none';
        
        try {
            const data = await safeFetch(`${API_BASE}/monitoring/get_cashiers.php`);
            
            if (loadingEl) loadingEl.style.display = 'none';
            
            if (data.status === 'success' && data.data && Array.isArray(data.data)) {
                this.displayCashiers(data.data, container);
            } else {
                if (errorEl) {
                    errorEl.textContent = 'No cashier data available';
                    errorEl.style.display = 'block';
                }
            }
        } catch (error) {
            if (loadingEl) loadingEl.style.display = 'none';
            if (errorEl) {
                errorEl.textContent = `Error loading cashiers: ${error.message}`;
                errorEl.style.display = 'block';
            }
            console.error('Error loading cashiers:', error);
        }
    },
    
    /**
     * Generate cashier alerts
     */
    generateAlerts(cashiers) {
        const alerts = [];
        
        cashiers.forEach(cashier => {
            // Negative balance alert
            if (cashier.cash_balance < 0) {
                alerts.push({
                    severity: 'critical',
                    title: `Critical: Negative Till Balance`,
                    description: `Cashier ${cashier.username} has negative balance: ${formatCurrency(cashier.cash_balance)}`,
                    cashier_id: cashier.user_id,
                    cashier_name: cashier.username
                });
            }
            
            // Low balance alert
            if (cashier.cash_balance < 100 && cashier.cash_balance >= 0) {
                alerts.push({
                    severity: 'high',
                    title: `Low Till Balance Warning`,
                    description: `Cashier ${cashier.username} has low balance: ${formatCurrency(cashier.cash_balance)}`,
                    cashier_id: cashier.user_id,
                    cashier_name: cashier.username
                });
            }
            
            // Inactivity alert (no login in 24h but has balance)
            if (cashier.cash_balance > 0 && cashier.last_login) {
                const hoursSinceLogin = (Date.now() - new Date(cashier.last_login).getTime()) / 3600000;
                if (hoursSinceLogin > 24) {
                    alerts.push({
                        severity: 'medium',
                        title: `Inactive Cashier`,
                        description: `Cashier ${cashier.username} hasn't logged in for ${Math.floor(hoursSinceLogin)} hours. Till balance: ${formatCurrency(cashier.cash_balance)}`,
                        cashier_id: cashier.user_id,
                        cashier_name: cashier.username
                    });
                }
            }
            
            // No recent activity but logged in (possible till issue)
            if (cashier.recent_transactions_1h == 0 && cashier.last_login) {
                const minutesSinceLogin = (Date.now() - new Date(cashier.last_login).getTime()) / 60000;
                if (minutesSinceLogin < 60 && minutesSinceLogin > 5) {
                    alerts.push({
                        severity: 'low',
                        title: `No Recent Activity`,
                        description: `Cashier ${cashier.username} logged in ${Math.floor(minutesSinceLogin)} minutes ago but has no transactions`,
                        cashier_id: cashier.user_id,
                        cashier_name: cashier.username
                    });
                }
            }
        });
        
        return alerts;
    },
    
    /**
     * Display cashiers
     */
    displayCashiers(cashiers, container) {
        if (!cashiers || cashiers.length === 0) {
            container.innerHTML = '<div class="alert alert-info">No cashiers found in database.</div>';
            return;
        }
        
        // Calculate summary stats
        const totalBalance = cashiers.reduce((sum, c) => sum + (c.cash_balance || 0), 0);
        const lowBalanceCount = cashiers.filter(c => c.cash_balance < 100 && c.cash_balance >= 0).length;
        const negativeBalanceCount = cashiers.filter(c => c.cash_balance < 0).length;
        const activeCashiers = cashiers.filter(c => c.last_login && (Date.now() - new Date(c.last_login).getTime()) < 86400000).length;
        
        let html = `
            <div class="row mb-3">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-label">Total Cashiers</div>
                        <div class="stat-value">${cashiers.length}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-label">Total Till Balance</div>
                        <div class="stat-value">${formatCurrency(totalBalance)}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-label">Low Balance Alerts</div>
                        <div class="stat-value ${lowBalanceCount > 0 ? 'text-warning' : ''}">${lowBalanceCount}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-label">Negative Balance</div>
                        <div class="stat-value ${negativeBalanceCount > 0 ? 'text-danger' : ''}">${negativeBalanceCount}</div>
                    </div>
                </div>
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>Cashier ID</th>
                        <th>Username</th>
                        <th>Till Balance</th>
                        <th>Today's Bets</th>
                        <th>Today's Wins</th>
                        <th>Last Login</th>
                        <th>Last Transaction</th>
                        <th>Status</th>
                        <th>Issues</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        cashiers.forEach(cashier => {
            const balanceClass = cashier.cash_balance < 0 ? 'text-danger' : 
                                cashier.cash_balance < 100 ? 'text-warning' : 'text-success';
            const statusBadge = `<span class="badge bg-${cashier.status_class}">${cashier.status}</span>`;
            const issuesHtml = cashier.issues.length > 0 
                ? cashier.issues.map(i => `<span class="badge bg-warning">${i}</span>`).join(' ')
                : '<span class="badge bg-success">OK</span>';
            
            const lastLogin = cashier.last_login ? formatDateTime(cashier.last_login) : 'Never';
            const lastTransaction = cashier.last_transaction_time ? formatDateTime(cashier.last_transaction_time) : 'None';
            
            html += `
                <tr>
                    <td>#${cashier.user_id}</td>
                    <td><strong>${cashier.username}</strong></td>
                    <td><span class="${balanceClass} fw-bold">${formatCurrency(cashier.cash_balance)}</span></td>
                    <td>${cashier.today_bets_count} (${formatCurrency(cashier.today_bets_total)})</td>
                    <td>${cashier.today_wins_count} (${formatCurrency(cashier.today_wins_total)})</td>
                    <td><small>${lastLogin}</small></td>
                    <td><small>${lastTransaction}</small></td>
                    <td>${statusBadge}</td>
                    <td>${issuesHtml}</td>
                    <td>
                        <button onclick="CashierModule.viewTransactions(${cashier.user_id})" class="btn btn-sm btn-primary">View TX</button>
                    </td>
                </tr>
            `;
        });
        
        html += `
                </tbody>
            </table>
        `;
        
        container.innerHTML = html;
        
        // Generate and display alerts
        const alerts = this.generateAlerts(cashiers);
        this.displayAlerts(alerts);
        
        // Display till balance health
        this.displayTillHealth(cashiers);
    },
    
    /**
     * Display cashier alerts
     */
    displayAlerts(alerts) {
        const container = document.getElementById('cashierAlerts');
        if (!container) return;
        
        if (alerts.length === 0) {
            container.innerHTML = '<div class="alert alert-success">✅ No alerts. All cashiers operational.</div>';
            return;
        }
        
        const critical = alerts.filter(a => a.severity === 'critical');
        const high = alerts.filter(a => a.severity === 'high');
        const medium = alerts.filter(a => a.severity === 'medium');
        const low = alerts.filter(a => a.severity === 'low');
        
        let html = '';
        
        if (critical.length > 0) {
            html += `<div class="alert alert-danger"><strong>🔴 CRITICAL (${critical.length})</strong></div>`;
            critical.forEach(alert => {
                html += `<div class="alert-item alert-${alert.severity}">
                    <strong>${alert.title}</strong><br>
                    <small>${alert.description}</small>
                </div>`;
            });
        }
        
        if (high.length > 0) {
            html += `<div class="alert alert-warning"><strong>⚠️ HIGH (${high.length})</strong></div>`;
            high.forEach(alert => {
                html += `<div class="alert-item alert-${alert.severity}">
                    <strong>${alert.title}</strong><br>
                    <small>${alert.description}</small>
                </div>`;
            });
        }
        
        if (medium.length > 0) {
            html += `<div class="alert alert-info"><strong>ℹ️ MEDIUM (${medium.length})</strong></div>`;
            medium.slice(0, 5).forEach(alert => {
                html += `<div class="alert-item alert-${alert.severity}">
                    <strong>${alert.title}</strong><br>
                    <small>${alert.description}</small>
                </div>`;
            });
        }
        
        container.innerHTML = html || '<div class="alert alert-info">No alerts</div>';
    },
    
    /**
     * Display till balance health
     */
    displayTillHealth(cashiers) {
        const container = document.getElementById('tillBalanceHealth');
        if (!container) return;
        
        const totalBalance = cashiers.reduce((sum, c) => sum + (c.cash_balance || 0), 0);
        const avgBalance = cashiers.length > 0 ? totalBalance / cashiers.length : 0;
        const minBalance = Math.min(...cashiers.map(c => c.cash_balance || 0));
        const maxBalance = Math.max(...cashiers.map(c => c.cash_balance || 0));
        
        const healthyCount = cashiers.filter(c => c.cash_balance >= 100).length;
        const lowCount = cashiers.filter(c => c.cash_balance < 100 && c.cash_balance >= 0).length;
        const negativeCount = cashiers.filter(c => c.cash_balance < 0).length;
        
        const healthPercentage = cashiers.length > 0 ? (healthyCount / cashiers.length * 100).toFixed(1) : 0;
        const healthClass = healthPercentage >= 80 ? 'success' : healthPercentage >= 50 ? 'warning' : 'danger';
        
        let html = `
            <div class="info-grid">
                <div class="info-card">
                    <label>Overall Health</label>
                    <div class="value">
                        <span class="badge bg-${healthClass}">${healthPercentage}%</span>
                        <small class="text-muted">(${healthyCount}/${cashiers.length} healthy)</small>
                    </div>
                </div>
                <div class="info-card">
                    <label>Total Till Balance</label>
                    <div class="value">${formatCurrency(totalBalance)}</div>
                </div>
                <div class="info-card">
                    <label>Average Till Balance</label>
                    <div class="value">${formatCurrency(avgBalance)}</div>
                </div>
                <div class="info-card">
                    <label>Balance Range</label>
                    <div class="value">${formatCurrency(minBalance)} - ${formatCurrency(maxBalance)}</div>
                </div>
            </div>
            
            <div class="mt-3">
                <h6>Health Breakdown</h6>
                <div class="row g-2">
                    <div class="col-md-4">
                        <div class="alert alert-success">
                            <strong>Healthy:</strong> ${healthyCount} cashiers<br>
                            <small>Balance ≥ $100</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="alert alert-warning">
                            <strong>Low Balance:</strong> ${lowCount} cashiers<br>
                            <small>Balance < $100</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="alert alert-danger">
                            <strong>Negative:</strong> ${negativeCount} cashiers<br>
                            <small>Balance < $0</small>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        container.innerHTML = html;
    },
    
    /**
     * View cashier transactions
     */
    async viewTransactions(userId) {
        // You can implement a modal or separate page for this
        alert(`View transactions for cashier #${userId}. This feature can be expanded with a modal.`);
        console.log('View transactions for user:', userId);
    }
};

window.CashierModule = CashierModule;

