/**
 * Draw Monitoring Module
 * Features from tvdisplay/draw-monitor.html
 */

const DrawMonitorModule = {
    currentDrawNumber: 0,
    scheduleData: [],
    isManualMode: false,
    
    /**
     * Initialize draw monitor
     */
    async init() {
        await this.updateServerTime();
        await this.fetchCurrentDraw();
        await this.loadSchedule();
        await this.checkForcedNumber();
        
        // Update every 30 seconds
        setInterval(() => {
            this.updateServerTime();
            this.fetchCurrentDraw();
        }, 30000);
        
        // Update schedule every 5 minutes
        setInterval(() => {
            this.loadSchedule();
        }, 300000);
    },
    
    /**
     * Update server time display
     */
    async updateServerTime() {
        try {
            const data = await safeFetch(`${API_BASE}/get_current_draw.php`);
            if (data.status === 'success' && data.data) {
                const time = data.data.server_time?.formatted?.split(' ')[1] || '--:--:--';
                const timeEl = document.getElementById('serverTimeDraws');
                if (timeEl) timeEl.textContent = time;
                
                // Calculate next draw countdown
                const now = new Date();
                const currentMinute = now.getMinutes();
                const currentSecond = now.getSeconds();
                const minutesToNext = 3 - (currentMinute % 3);
                const secondsToNext = (minutesToNext * 60) - currentSecond;
                const mins = Math.floor(secondsToNext / 60);
                const secs = secondsToNext % 60;
                const nextDrawEl = document.getElementById('nextDrawIn');
                if (nextDrawEl) {
                    nextDrawEl.textContent = `${mins}:${String(secs).padStart(2, '0')}`;
                }
            } else {
                const timeEl = document.getElementById('serverTimeDraws');
                if (timeEl) timeEl.textContent = 'API Error';
            }
        } catch (error) {
            console.error('Error updating server time:', error);
            const timeEl = document.getElementById('serverTimeDraws');
            if (timeEl) timeEl.textContent = 'API Error';
        }
    },
    
    /**
     * Fetch current draw number
     */
    async fetchCurrentDraw() {
        try {
            const data = await safeFetch(`${API_BASE}/get_current_draw.php`);
            if (data.status === 'success') {
                this.currentDrawNumber = parseInt(data.data.current_draw_number);
                document.getElementById('currentDrawDisplay').textContent = `#${this.currentDrawNumber}`;
                return this.currentDrawNumber;
            }
        } catch (error) {
            console.error('Error fetching current draw:', error);
        }
        return 0;
    },
    
    /**
     * Load preset schedule
     */
    async loadSchedule() {
        const drawNum = await this.fetchCurrentDraw();
        if (!drawNum) return;
        
        const bodyEl = document.getElementById('scheduleBody');
        if (!bodyEl) return;
        
        showLoading('scheduleBody');
        
        try {
            const data = await safeFetch(`${API_BASE}/load_preset_schedule.php`);
            
            if (data.status === 'success' && data.data) {
                const scheduleData = data.data.schedule_data || [];
                const patternData = data.data.pattern_data || [];
                const startDraw = data.data.start_draw_number || 1;
                
                this.scheduleData = scheduleData;
                
                let html = '';
                const displayStart = Math.max(1, drawNum - 10);
                const displayEnd = Math.min(480, drawNum + 40);
                
                for (let i = displayStart; i <= displayEnd; i++) {
                    const isCurrent = i === drawNum;
                    const rowClass = isCurrent ? 'table-warning' : (i < drawNum ? 'table-secondary' : '');
                    
                    const dataIndex = i - startDraw;
                    const winningNumber = (dataIndex >= 0 && dataIndex < scheduleData.length) ? scheduleData[dataIndex] : '--';
                    const pattern = (dataIndex >= 0 && dataIndex < patternData.length) ? patternData[dataIndex] : 'N/A';
                    
                    const totalMinutes = (i - 1) * 3;
                    const hours = Math.floor(totalMinutes / 60) % 24;
                    const minutes = totalMinutes % 60;
                    const timeStr = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
                    
                    const colorClass = winningNumber !== '--' ? getColorForNumber(winningNumber) : 'black';
                    
                    html += `
                        <tr class="${rowClass}" id="draw-row-${i}">
                            <td>${i} ${isCurrent ? '<span class="badge bg-warning text-dark">Current</span>' : ''}</td>
                            <td>${timeStr}</td>
                            <td>${formatNumberBadge(winningNumber, colorClass)}</td>
                            <td class="text-muted small">${pattern}</td>
                        </tr>
                    `;
                }
                
                bodyEl.innerHTML = html || '<tr><td colspan="4" class="text-center">No schedule data found</td></tr>';
                
                // Scroll to current draw
                const currentDrawRow = document.getElementById(`draw-row-${drawNum}`);
                if (currentDrawRow) {
                    currentDrawRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            } else {
                bodyEl.innerHTML = '<tr><td colspan="4" class="text-center">No active schedule found</td></tr>';
            }
        } catch (error) {
            console.error('Error loading schedule:', error);
            bodyEl.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Error loading schedule</td></tr>';
        }
    },
    
    /**
     * Check forced number
     */
    async checkForcedNumber() {
        const infoEl = document.getElementById('forcedNumberInfo');
        if (!infoEl) return;
        
        showLoading('forcedNumberInfo');
        
        const drawNum = await this.fetchCurrentDraw();
        if (!drawNum) return;
        
        try {
            const nextDraw = drawNum + 1;
            for (let i = nextDraw; i <= nextDraw + 5; i++) {
                const data = await safeFetch(`${API_BASE}/direct_forced_number.php?draw_number=${i}`);
                
                if (data.status === 'success' && data.has_forced_number) {
                    const source = data.source === 'preset_schedule' ? 'Preset Schedule' : 'Database Forced Number';
                    const sourceClass = data.source === 'manual' ? 'warning' : 'primary';
                    
                    infoEl.innerHTML = `
                        <div class="alert alert-info mb-0">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="mb-0">
                                    <i class="fas fa-info-circle"></i> ${source} Found 
                                </h6>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <small class="text-muted d-block">Draw #</small>
                                    <strong>${data.draw_number}</strong>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block">Number</small>
                                    <span class="badge bg-success">${data.forced_number}</span>
                                </div>
                            </div>
                            <div>
                                <small class="text-muted">Source: <span class="badge bg-${sourceClass}">${data.source || 'unknown'}</span></small>
                            </div>
                        </div>
                    `;
                    return;
                }
            }
            
            infoEl.innerHTML = '<div class="alert alert-secondary mb-0"><small>No active forced number detected in next 5 draws.</small></div>';
        } catch (error) {
            console.error('Error checking forced number:', error);
            infoEl.innerHTML = '<div class="alert alert-danger mb-0"><small>Error checking forced numbers.</small></div>';
        }
    },
    
    /**
     * Toggle manual mode
     */
    toggleMode() {
        this.isManualMode = !this.isManualMode;
        const modeDisplay = document.getElementById('currentModeDisplay');
        const toggleBtn = document.getElementById('toggleModeBtn');
        
        if (modeDisplay) {
            modeDisplay.textContent = this.isManualMode ? 'Manual' : 'Auto';
            modeDisplay.className = `badge bg-${this.isManualMode ? 'warning' : 'secondary'}`;
        }
        
        if (toggleBtn) {
            toggleBtn.innerHTML = this.isManualMode ? 
                '<i class="fas fa-toggle-on"></i> Switch to Auto' : 
                '<i class="fas fa-toggle-off"></i> Switch to Manual';
        }
    },
    
    /**
     * Set manual number
     */
    async setManualNumber() {
        const numberInput = document.getElementById('manualNumberInput');
        const statusDiv = document.getElementById('manualNumberStatus');
        
        if (!numberInput || !statusDiv) return;
        
        const number = parseInt(numberInput.value);
        
        if (isNaN(number) || number < 0 || number > 36) {
            statusDiv.innerHTML = '<span class="text-danger">Invalid number. Must be 0-36.</span>';
            return;
        }
        
        statusDiv.innerHTML = '<span class="text-info">Setting number...</span>';
        
        try {
            if (!this.isManualMode) {
                this.toggleMode();
                await new Promise(resolve => setTimeout(resolve, 100));
            }
            
            const currentDraw = await this.fetchCurrentDraw();
            const targetDrawNumber = currentDraw + 1;
            
            const response = await fetch(`${API_BASE}/set_winning_number.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `winning_number=${number}&draw_number=${targetDrawNumber}&keep_auto_mode=false`
            });
            
            const data = await response.json();
            if (data.status === 'success') {
                statusDiv.innerHTML = `<span class="text-success">✅ Number ${number} set for draw #${targetDrawNumber}<br>Source: ${data.data.source}</span>`;
                this.checkForcedNumber();
            } else {
                statusDiv.innerHTML = `<span class="text-danger">❌ Error: ${data.message}</span>`;
            }
        } catch (error) {
            console.error('Error setting manual number:', error);
            statusDiv.innerHTML = '<span class="text-danger">❌ Failed to set number.</span>';
        }
    }
};

window.DrawMonitorModule = DrawMonitorModule;

