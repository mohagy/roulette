/**
 * Maintenance Checks Module
 * Monitors system health and performance
 */

const MaintenanceModule = {
    checks: {
        database: { status: 'unknown', message: '', lastCheck: null },
        api: { status: 'unknown', message: '', lastCheck: null },
        firebase: { status: 'unknown', message: '', lastCheck: null },
        transactions: { status: 'unknown', message: '', lastCheck: null },
        performance: { status: 'unknown', message: '', lastCheck: null }
    },
    
    /**
     * Run all maintenance checks
     */
    async runAllChecks() {
        await Promise.all([
            this.checkDatabase(),
            this.checkAPI(),
            this.checkFirebase(),
            this.checkTransactions(),
            this.checkPerformance()
        ]);
        
        this.updateDisplay();
    },
    
    /**
     * Check database connectivity
     */
    async checkDatabase() {
        try {
            // Try to fetch data that requires database
            const startTime = Date.now();
            const data = await safeFetch(`${API_BASE}/get_current_draw.php`);
            const responseTime = Date.now() - startTime;
            
            if (data.status === 'success') {
                this.checks.database = {
                    status: 'healthy',
                    message: `Connected (${responseTime}ms)`,
                    lastCheck: new Date().toISOString()
                };
            } else {
                this.checks.database = {
                    status: 'warning',
                    message: 'API returned error',
                    lastCheck: new Date().toISOString()
                };
            }
        } catch (error) {
            this.checks.database = {
                status: 'error',
                message: 'Connection failed: ' + error.message,
                lastCheck: new Date().toISOString()
            };
        }
    },
    
    /**
     * Check API endpoints
     */
    async checkAPI() {
        const endpoints = [
            'get_current_draw.php',
            'get_analytics_history.php',
            'load_preset_schedule.php'
        ];
        
        let successCount = 0;
        let totalTime = 0;
        
        for (const endpoint of endpoints) {
            try {
                const startTime = Date.now();
                const data = await safeFetch(`${API_BASE}/${endpoint}`);
                const responseTime = Date.now() - startTime;
                totalTime += responseTime;
                
                if (data.status === 'success') {
                    successCount++;
                }
            } catch (error) {
                // Endpoint failed
            }
        }
        
        const avgTime = totalTime / endpoints.length;
        
        if (successCount === endpoints.length) {
            this.checks.api = {
                status: 'healthy',
                message: `All endpoints OK (avg ${Math.round(avgTime)}ms)`,
                lastCheck: new Date().toISOString()
            };
        } else if (successCount > 0) {
            this.checks.api = {
                status: 'warning',
                message: `${successCount}/${endpoints.length} endpoints working`,
                lastCheck: new Date().toISOString()
            };
        } else {
            this.checks.api = {
                status: 'error',
                message: 'All endpoints failed',
                lastCheck: new Date().toISOString()
            };
        }
    },
    
    /**
     * Check Firebase connection
     */
    async checkFirebase() {
        try {
            if (window.firestore) {
                // Try to read from Firestore - use an existing collection
                const startTime = Date.now();
                try {
                    // Try to read from monitoring_alerts collection (if it exists)
                    const testRef = window.firestore.collection('monitoring_alerts').limit(1);
                    const snapshot = await testRef.get();
                    const responseTime = Date.now() - startTime;
                    
                    this.checks.firebase = {
                        status: 'healthy',
                        message: `Connected (${responseTime}ms)`,
                        lastCheck: new Date().toISOString()
                    };
                } catch (permissionError) {
                    // If permission denied, Firestore is connected but rules need adjustment
                    const responseTime = Date.now() - startTime;
                    if (permissionError.code === 'permission-denied') {
                        this.checks.firebase = {
                            status: 'warning',
                            message: `Connected but permission denied. Check Firestore rules.`,
                            lastCheck: new Date().toISOString()
                        };
                    } else {
                        throw permissionError;
                    }
                }
            } else {
                this.checks.firebase = {
                    status: 'warning',
                    message: 'Firestore not initialized',
                    lastCheck: new Date().toISOString()
                };
            }
        } catch (error) {
            this.checks.firebase = {
                status: 'error',
                message: 'Connection failed: ' + (error.message || error.code || 'Unknown error'),
                lastCheck: new Date().toISOString()
            };
        }
    },
    
    /**
     * Check recent transaction sync
     */
    async checkTransactions() {
        try {
            const data = await safeFetch(`${API_BASE}/get_analytics_history.php?limit=1`);
            
            if (data.status === 'success' && data.data && data.data.draws && data.data.draws.length > 0) {
                const lastDraw = data.data.draws[0];
                const lastDrawTime = new Date(lastDraw.draw_time);
                const now = new Date();
                const minutesAgo = Math.floor((now - lastDrawTime) / 60000);
                
                if (minutesAgo < 10) {
                    this.checks.transactions = {
                        status: 'healthy',
                        message: `Last transaction ${minutesAgo} min ago`,
                        lastCheck: new Date().toISOString()
                    };
                } else if (minutesAgo < 30) {
                    this.checks.transactions = {
                        status: 'warning',
                        message: `Last transaction ${minutesAgo} min ago`,
                        lastCheck: new Date().toISOString()
                    };
                } else {
                    this.checks.transactions = {
                        status: 'error',
                        message: `Last transaction ${minutesAgo} min ago (stale)`,
                        lastCheck: new Date().toISOString()
                    };
                }
            } else {
                this.checks.transactions = {
                    status: 'warning',
                    message: 'No recent transactions found',
                    lastCheck: new Date().toISOString()
                };
            }
        } catch (error) {
            this.checks.transactions = {
                status: 'error',
                message: 'Check failed: ' + error.message,
                lastCheck: new Date().toISOString()
            };
        }
    },
    
    /**
     * Check system performance
     */
    async checkPerformance() {
        try {
            const startTime = Date.now();
            await Promise.all([
                safeFetch(`${API_BASE}/get_current_draw.php`),
                safeFetch(`${API_BASE}/get_analytics_history.php?limit=10`)
            ]);
            const totalTime = Date.now() - startTime;
            
            if (totalTime < 1000) {
                this.checks.performance = {
                    status: 'healthy',
                    message: `Fast response (${totalTime}ms)`,
                    lastCheck: new Date().toISOString()
                };
            } else if (totalTime < 3000) {
                this.checks.performance = {
                    status: 'warning',
                    message: `Slow response (${totalTime}ms)`,
                    lastCheck: new Date().toISOString()
                };
            } else {
                this.checks.performance = {
                    status: 'error',
                    message: `Very slow (${totalTime}ms)`,
                    lastCheck: new Date().toISOString()
                };
            }
        } catch (error) {
            this.checks.performance = {
                status: 'error',
                message: 'Performance check failed',
                lastCheck: new Date().toISOString()
            };
        }
    },
    
    /**
     * Update maintenance display
     */
    updateDisplay() {
        const container = document.getElementById('maintenanceChecks');
        if (!container) return;
        
        let html = '';
        
        for (const [key, check] of Object.entries(this.checks)) {
            const statusClass = {
                'healthy': 'success',
                'warning': 'warning',
                'error': 'danger',
                'unknown': 'secondary'
            }[check.status] || 'secondary';
            
            const icon = {
                'healthy': '✅',
                'warning': '⚠️',
                'error': '❌',
                'unknown': '⏳'
            }[check.status] || '⏳';
            
            html += `
                <div class="maintenance-check-item">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong>${this.formatCheckName(key)}</strong>
                        <span class="badge bg-${statusClass}">${icon} ${check.status}</span>
                    </div>
                    <div class="text-muted small">${check.message}</div>
                    ${check.lastCheck ? `<div class="text-muted" style="font-size: 0.75rem;">Last check: ${formatDateTime(check.lastCheck)}</div>` : ''}
                </div>
            `;
        }
        
        container.innerHTML = html;
    },
    
    /**
     * Format check name
     */
    formatCheckName(key) {
        const names = {
            'database': 'Database Connectivity',
            'api': 'API Endpoints',
            'firebase': 'Firebase Connection',
            'transactions': 'Transaction Sync',
            'performance': 'System Performance'
        };
        return names[key] || key;
    }
};

window.MaintenanceModule = MaintenanceModule;

