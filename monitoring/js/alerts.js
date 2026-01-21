/**
 * Real-time Alerts System
 * Uses Firebase Firestore for real-time updates
 */

const AlertsModule = {
    alertsListener: null,
    shopsListener: null,
    statsListener: null,

    /**
     * Initialize real-time listeners
     */
    init() {
        if (!window.firestore) {
            console.warn('Firestore not available, alerts will not update in real-time');
            return;
        }

        this.setupAlertsListener();
        this.setupShopsListener();
        this.setupStatsListener();
    },

    /**
     * Setup alerts listener
     */
    setupAlertsListener() {
        if (!window.firestore) {
            console.warn('Firestore not available for alerts listener');
            return;
        }

        try {
            const alertsRef = window.firestore.collection('monitoring_alerts')
                .where('status', '==', 'new')
                .limit(50);

            this.alertsListener = alertsRef.onSnapshot((snapshot) => {
                const alerts = [];
                snapshot.forEach((doc) => {
                    alerts.push({ id: doc.id, ...doc.data() });
                });

                // Sort by created_at desc in JavaScript to avoid index requirement
                alerts.sort((a, b) => {
                    const timeA = a.created_at?.seconds || 0;
                    const timeB = b.created_at?.seconds || 0;
                    return timeB - timeA;
                });

                this.displayAlerts(alerts);

                // Play sound for new alerts
                snapshot.docChanges().forEach((change) => {
                    if (change.type === 'added') {
                        this.playAlertSound(change.doc.data().severity);
                    }
                });
            }, (error) => {
                if (error.code === 'permission-denied') {
                    console.warn('Firestore permission denied - using API fallback for alerts');
                } else {
                    console.error('Alerts listener error:', error);
                }
                // Fallback to API if Firebase fails or permissions denied
                this.loadAlertsFromAPI();
            });
        } catch (error) {
            console.error('Error setting up alerts listener:', error);
            this.loadAlertsFromAPI();
        }

        // Also try to load from API initially as fallback
        setTimeout(() => this.loadAlertsFromAPI(), 2000);
    },

    /**
     * Setup shops listener
     */
    setupShopsListener() {
        if (!window.firestore) {
            console.warn('Firestore not available for shops listener');
            return;
        }

        try {
            const shopsRef = window.firestore.collection('monitoring_shops');

            this.shopsListener = shopsRef.onSnapshot((snapshot) => {
                const shops = [];
                snapshot.forEach((doc) => {
                    shops.push({ id: doc.id, ...doc.data() });
                });

                this.displayShops(shops);
            }, (error) => {
                if (error.code === 'permission-denied') {
                    console.warn('Firestore permission denied - using API fallback for shops');
                } else {
                    console.error('Shops listener error:', error);
                }
                // Fallback to API if Firebase fails or permissions denied
                this.loadShopsFromAPI();
            });
        } catch (error) {
            console.error('Error setting up shops listener:', error);
            this.loadShopsFromAPI();
        }

        // Always load from database API (not Firebase)
        this.loadShopsFromAPI();
    },

    /**
     * Setup stats listener
     */
    setupStatsListener() {
        if (!window.firestore) {
            console.warn('Firestore not available for stats listener');
            return;
        }

        try {
            const statsRef = window.firestore.collection('monitoring_stats').doc('live');

            this.statsListener = statsRef.onSnapshot((docSnapshot) => {
                // Check if document exists - docSnapshot.exists is a property, not a function
                if (docSnapshot && docSnapshot.exists === true) {
                    const data = docSnapshot.data();
                    if (data) {
                        this.displayStats(data);
                    } else {
                        this.loadStatsFromAPI();
                    }
                } else {
                    // Document doesn't exist, try API fallback
                    this.loadStatsFromAPI();
                }
            }, (error) => {
                if (error.code === 'permission-denied') {
                    console.warn('Firestore permission denied - using API fallback for stats');
                } else {
                    console.error('Stats listener error:', error);
                }
                // Fallback to API if Firebase fails or permissions denied
                this.loadStatsFromAPI();
            });
        } catch (error) {
            console.error('Error setting up stats listener:', error);
            this.loadStatsFromAPI();
        }

        // Also try to load from API initially as fallback
        setTimeout(() => this.loadStatsFromAPI(), 2000);
    },

    /**
     * Display alerts
     */
    displayAlerts(alerts) {
        const container = document.getElementById('alertsContainer');
        if (!container) return;

        if (alerts.length === 0) {
            container.innerHTML = '<div class="alert alert-info"><i class="fas fa-info-circle"></i> No active alerts. All systems operational.</div>';
            return;
        }

        // Group by severity
        const critical = alerts.filter(a => a.severity === 'critical');
        const high = alerts.filter(a => a.severity === 'high');
        const medium = alerts.filter(a => a.severity === 'medium');
        const low = alerts.filter(a => a.severity === 'low');

        let html = '';

        if (critical.length > 0) {
            html += `<div class="alert alert-danger"><strong>🔴 CRITICAL (${critical.length})</strong></div>`;
            critical.forEach(alert => {
                html += this.formatAlert(alert);
            });
        }

        if (high.length > 0) {
            html += `<div class="alert alert-warning"><strong>⚠️ HIGH (${high.length})</strong></div>`;
            high.forEach(alert => {
                html += this.formatAlert(alert);
            });
        }

        if (medium.length > 0) {
            html += `<div class="alert alert-info"><strong>ℹ️ MEDIUM (${medium.length})</strong></div>`;
            medium.slice(0, 5).forEach(alert => {
                html += this.formatAlert(alert);
            });
        }

        container.innerHTML = html;
    },

    /**
     * Format alert HTML
     */
    formatAlert(alert) {
        const severityClass = getSeverityClass(alert.severity);
        return `
            <div class="alert-item alert-${alert.severity}">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <strong>${alert.title}</strong>
                        <div class="text-muted small">${alert.description}</div>
                        ${alert.related_shop_id ? `<div class="text-muted small">Shop ID: ${alert.related_shop_id}</div>` : ''}
                    </div>
                    <span class="badge ${severityClass}">${alert.severity}</span>
                </div>
                <div class="text-muted" style="font-size: 0.75rem; margin-top: 5px;">
                    ${formatDateTime(alert.created_at)}
                </div>
            </div>
        `;
    },

    /**
     * Display shops
     */
    displayShops(shops) {
        const container = document.getElementById('shopsContainer');
        if (!container) return;

        if (shops.length === 0) {
            container.innerHTML = '<div class="alert alert-info"><i class="fas fa-info-circle"></i> No shop performance data available for today.</div>';
            return;
        }

        let html = '<div class="row g-3">';
        shops.forEach(shop => {
            const payoutRatio = shop.today_bets > 0 ? ((shop.today_payouts / shop.today_bets) * 100).toFixed(1) : 0;
            const ratioClass = payoutRatio > 80 ? 'danger' : payoutRatio > 60 ? 'warning' : 'success';

            html += `
                <div class="col-md-6 col-lg-4">
                    <div class="card shop-card">
                        <div class="card-body">
                            <h6 class="card-title">${shop.shop_name || `Shop #${shop.shop_id}`}</h6>
                            <div class="mb-2">
                                <small class="text-muted">Today's Bets:</small>
                                <strong>${formatCurrency(shop.today_bets || 0)}</strong>
                            </div>
                            <div class="mb-2">
                                <small class="text-muted">Today's Payouts:</small>
                                <strong>${formatCurrency(shop.today_payouts || 0)}</strong>
                            </div>
                            <div class="mb-2">
                                <small class="text-muted">Payout Ratio:</small>
                                <span class="badge bg-${ratioClass}">${payoutRatio}%</span>
                            </div>
                            ${shop.active_alerts > 0 ? `
                                <div class="alert alert-warning py-1 px-2 mb-0">
                                    <small>${shop.active_alerts} active alert(s)</small>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';

        container.innerHTML = html;
    },

    /**
     * Display stats
     */
    displayStats(stats) {
        if (stats.total_active_alerts !== undefined) {
            const el = document.getElementById('totalAlerts');
            if (el) el.textContent = stats.total_active_alerts || 0;
        }

        if (stats.critical_alerts !== undefined) {
            const el = document.getElementById('criticalAlerts');
            if (el) el.textContent = stats.critical_alerts || 0;
        }

        if (stats.today_payouts_total !== undefined) {
            const el = document.getElementById('todayPayouts');
            if (el) el.textContent = formatCurrency(stats.today_payouts_total || 0);
        }

        if (stats.shops_under_monitoring !== undefined) {
            const el = document.getElementById('shopsMonitoring');
            if (el) el.textContent = stats.shops_under_monitoring || 0;
        }
    },

    /**
     * Play alert sound
     */
    playAlertSound(severity) {
        // Only play sound for critical and high severity
        if (severity === 'critical' || severity === 'high') {
            // Create audio context for beep
            try {
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();

                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);

                oscillator.frequency.value = severity === 'critical' ? 800 : 600;
                oscillator.type = 'sine';

                gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);

                oscillator.start(audioContext.currentTime);
                oscillator.stop(audioContext.currentTime + 0.5);
            } catch (e) {
                console.log('Could not play alert sound:', e);
            }
        }
    },

    /**
     * Acknowledge alert
     */
    async acknowledgeAlert(alertId) {
        if (!window.firestore) return;

        try {
            const user = MonitoringAuth.getCurrentUser();
            if (!user) return;

            await window.firestore.collection('monitoring_alerts')
                .doc(alertId)
                .update({
                    status: 'acknowledged',
                    acknowledged_by: user.username,
                    acknowledged_at: firebase.firestore.FieldValue.serverTimestamp(),
                    updated_at: firebase.firestore.FieldValue.serverTimestamp()
                });
        } catch (error) {
            console.error('Error acknowledging alert:', error);
        }
    },

    /**
     * Load alerts from API (fallback)
     */
    async loadAlertsFromAPI() {
        const container = document.getElementById('alertsContainer');
        if (!container) return;

        try {
            // Check if we already have Firebase data showing
            const existingContent = container.innerHTML.trim();
            if (existingContent && !existingContent.includes('Loading')) {
                // Already have data from Firebase, don't override
                return;
            }

            // Try to fetch from API if available
            // For now, just show empty state
            if (!existingContent || existingContent.includes('Loading')) {
                container.innerHTML = '<div class="alert alert-info">No active alerts. Firebase collections may be empty. Run the sync script to populate data.</div>';
            }
        } catch (error) {
            console.error('Error loading alerts from API:', error);
        }
    },

    /**
     * Load shops from API
     */
    async loadShopsFromAPI() {
        const container = document.getElementById('shopsContainer');
        if (!container) return;

        try {
            console.log('Loading shops from database API...');
            const data = await safeFetch(`${API_BASE}/monitoring/get_shop_performance.php`);
            
            console.log('Shop data response:', data);
            
            if (data.status === 'success' && data.data && Array.isArray(data.data) && data.data.length > 0) {
                this.displayShops(data.data);
            } else {
                container.innerHTML = '<div class="alert alert-info"><i class="fas fa-info-circle"></i> No shop performance data available for today.</div>';
            }
        } catch (error) {
            console.error('Error loading shops from API:', error);
            const existingContent = container.innerHTML.trim();
            if (!existingContent || existingContent.includes('Loading')) {
                container.innerHTML = `<div class="alert alert-warning">Error loading shop data: ${error.message || 'Please check API endpoint'}</div>`;
            }
        }
    },

    /**
     * Load stats from API (fallback)
     */
    async loadStatsFromAPI() {
        // Try to calculate stats from analytics API
        try {
            const data = await safeFetch(`${API_BASE}/get_analytics_history.php?limit=1`);
            if (data.status === 'success' && data.data && data.data.draws && data.data.draws.length > 0) {
                // At least we know there's activity
                const shopsEl = document.getElementById('shopsMonitoring');
                if (shopsEl && shopsEl.textContent === '0') {
                    shopsEl.textContent = 'N/A';
                }
            }
        } catch (error) {
            console.error('Error loading stats from API:', error);
        }
    },

    /**
     * Cleanup listeners
     */
    cleanup() {
        if (this.alertsListener) this.alertsListener();
        if (this.shopsListener) this.shopsListener();
        if (this.statsListener) this.statsListener();
    }
};

window.AlertsModule = AlertsModule;

