/**
 * TV Display Preview Module
 * Shows animation results, preset schedule, and forced numbers
 */

const TVDisplayPreviewModule = {
    currentDrawNumber: 0,
    lastWinningNumber: null,
    lastWinningColor: null,
    presetSchedule: [],
    forcedNumbers: [],
    refreshInterval: null,
    
    /**
     * Initialize TV display preview
     */
    async init() {
        await this.loadAll();
        
        // Auto-refresh every 10 seconds
        this.refreshInterval = setInterval(() => {
            this.loadAll();
        }, 10000);
    },
    
    /**
     * Load all data
     */
    async loadAll() {
        await Promise.all([
            this.loadCurrentDraw(),
            this.loadLastWinningNumber(),
            this.loadPresetSchedule(),
            this.loadForcedNumbers()
        ]);
    },
    
    /**
     * Load current draw number
     */
    async loadCurrentDraw() {
        try {
            const data = await safeFetch(`${API_BASE}/get_current_draw.php`);
            if (data.status === 'success' && data.data) {
                this.currentDrawNumber = parseInt(data.data.current_draw_number) || 0;
                const nextDraw = parseInt(data.data.next_draw_number) || (this.currentDrawNumber + 1);
                
                const currentDrawEl = document.getElementById('tvCurrentDraw');
                const nextDrawEl = document.getElementById('tvNextDraw');
                if (currentDrawEl) currentDrawEl.textContent = `#${this.currentDrawNumber}`;
                if (nextDrawEl) nextDrawEl.textContent = `#${nextDraw}`;
            }
        } catch (error) {
            console.error('Error loading current draw:', error);
        }
    },
    
    /**
     * Load last winning number from analytics
     */
    async loadLastWinningNumber() {
        try {
            console.log('Loading last winning number from:', `${API_BASE}/get_analytics_history.php?limit=1`);
            const data = await safeFetch(`${API_BASE}/get_analytics_history.php?limit=1`);
            console.log('Analytics API response:', data);
            
            if (data.status === 'success' && data.data && data.data.length > 0) {
                const lastDraw = data.data[0];
                this.lastWinningNumber = parseInt(lastDraw.winning_number);
                this.lastWinningColor = lastDraw.winning_color || this.getNumberColor(this.lastWinningNumber);
                
                console.log('Last winning number:', this.lastWinningNumber, 'Color:', this.lastWinningColor);
                
                // Update display
                const numberEl = document.getElementById('tvLastWinningNumber');
                const colorEl = document.getElementById('tvLastWinningColor');
                const drawEl = document.getElementById('tvLastDrawNumber');
                
                if (numberEl) {
                    numberEl.textContent = this.lastWinningNumber;
                    numberEl.className = `number-circle number-${this.lastWinningColor}`;
                    console.log('Updated number element:', numberEl.textContent);
                } else {
                    console.error('tvLastWinningNumber element not found!');
                }
                
                if (colorEl) {
                    colorEl.textContent = this.lastWinningColor.toUpperCase();
                    // Apply color to badge using number-badge classes
                    colorEl.className = `badge number-badge number-${this.lastWinningColor}`;
                    colorEl.style.fontSize = '1rem';
                    colorEl.style.padding = '0.5rem 1rem';
                }
                if (drawEl) {
                    drawEl.textContent = `Draw #${lastDraw.draw_number}`;
                }
                
                // Update time
                const timeEl = document.getElementById('tvLastDrawTime');
                if (timeEl && lastDraw.draw_time) {
                    const drawTime = new Date(lastDraw.draw_time);
                    timeEl.textContent = drawTime.toLocaleTimeString();
                }
            } else {
                console.warn('No analytics data found:', data);
                // No data yet - show message
                const numberEl = document.getElementById('tvLastWinningNumber');
                if (numberEl) {
                    numberEl.textContent = '--';
                    numberEl.className = 'number-circle number-black';
                }
                const drawEl = document.getElementById('tvLastDrawNumber');
                if (drawEl) drawEl.textContent = 'No data available';
            }
        } catch (error) {
            console.error('Error loading last winning number:', error);
            const numberEl = document.getElementById('tvLastWinningNumber');
            if (numberEl) {
                numberEl.textContent = 'Error';
                numberEl.className = 'number-circle number-black';
            }
        }
    },
    
    /**
     * Load preset schedule
     */
    async loadPresetSchedule() {
        try {
            const currentDraw = this.currentDrawNumber || 1;
            const data = await safeFetch(`${API_BASE}/load_preset_schedule.php?current_draw=${currentDraw}`);
            
            if (data.status === 'success' && data.data) {
                this.presetSchedule = data.data.schedule_data || [];
                const patternData = data.data.pattern_data || [];
                const startDraw = data.data.start_draw_number || 1;
                
                // Show next 10 draws
                const tbody = document.getElementById('tvPresetScheduleBody');
                if (tbody) {
                    tbody.innerHTML = '';
                    
                    const nextDraw = this.currentDrawNumber + 1;
                    for (let i = 0; i < 10; i++) {
                        const drawNum = nextDraw + i;
                        const index = drawNum - startDraw;
                        
                        if (index >= 0 && index < this.presetSchedule.length) {
                            const number = this.presetSchedule[index];
                            const pattern = patternData[index] || 'N/A';
                            const color = this.getNumberColor(number);
                            
                            // Calculate time
                            const totalMinutes = (drawNum - 1) * 3;
                            const hours = Math.floor(totalMinutes / 60) % 24;
                            const minutes = totalMinutes % 60;
                            const timeStr = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
                            
                            const isNext = i === 0;
                            const row = document.createElement('tr');
                            row.className = isNext ? 'table-warning' : '';
                            const patternDisplay = pattern && pattern.length > 50 ? pattern.substring(0, 50) + '...' : (pattern || 'N/A');
                            row.innerHTML = `
                                <td><strong>#${drawNum}</strong> ${isNext ? '<span class="badge bg-warning">Next</span>' : ''}</td>
                                <td>${timeStr}</td>
                                <td><span class="number-badge number-${color}">${number}</span></td>
                                <td><small class="text-muted">${patternDisplay}</small></td>
                            `;
                            tbody.appendChild(row);
                        }
                    }
                }
            }
        } catch (error) {
            console.error('Error loading preset schedule:', error);
        }
    },
    
    /**
     * Load forced numbers
     */
    async loadForcedNumbers() {
        try {
            const currentDraw = this.currentDrawNumber || 1;
            const nextDraw = currentDraw + 1;
            
            // Check next 5 draws for forced numbers
            const tbody = document.getElementById('tvForcedNumbersBody');
            if (tbody) {
                tbody.innerHTML = '';
                
                for (let i = 0; i < 5; i++) {
                    const drawNum = nextDraw + i;
                    try {
                        const data = await safeFetch(`${API_BASE}/direct_forced_number.php?draw_number=${drawNum}`);
                        
                        if (data.status === 'success' && data.has_forced_number) {
                            const number = parseInt(data.forced_number);
                            const color = this.getNumberColor(number);
                            const source = data.source || 'unknown';
                            const reason = data.reason || '';
                            
                            // Calculate time
                            const totalMinutes = (drawNum - 1) * 3;
                            const hours = Math.floor(totalMinutes / 60) % 24;
                            const minutes = totalMinutes % 60;
                            const timeStr = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
                            
                            const row = document.createElement('tr');
                            const sourceBadge = source === 'manual' ? 'bg-danger' : (source === 'preset_schedule' ? 'bg-success' : 'bg-secondary');
                            row.innerHTML = `
                                <td><strong>#${drawNum}</strong></td>
                                <td>${timeStr}</td>
                                <td><span class="number-badge number-${color}">${number}</span></td>
                                <td><span class="badge ${sourceBadge}">${this.formatSource(source)}</span></td>
                                <td><small class="text-muted">${reason || '-'}</small></td>
                            `;
                            tbody.appendChild(row);
                        } else {
                            // No forced number - show preset if available
                            const index = drawNum - 1; // Assuming start_draw_number = 1
                            if (this.presetSchedule[index]) {
                                const number = this.presetSchedule[index];
                                const color = this.getNumberColor(number);
                                
                                const totalMinutes = (drawNum - 1) * 3;
                                const hours = Math.floor(totalMinutes / 60) % 24;
                                const minutes = totalMinutes % 60;
                                const timeStr = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
                                
                                const row = document.createElement('tr');
                                row.innerHTML = `
                                    <td><strong>#${drawNum}</strong></td>
                                    <td>${timeStr}</td>
                                    <td><span class="number-badge number-${color}">${number}</span></td>
                                    <td><span class="badge bg-success">Preset Schedule</span></td>
                                    <td><small class="text-muted">-</small></td>
                                `;
                                tbody.appendChild(row);
                            }
                        }
                    } catch (error) {
                        console.error(`Error loading forced number for draw #${drawNum}:`, error);
                    }
                }
            }
        } catch (error) {
            console.error('Error loading forced numbers:', error);
        }
    },
    
    /**
     * Get number color
     */
    getNumberColor(number) {
        if (number === 0) return 'green';
        const redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
        return redNumbers.includes(number) ? 'red' : 'black';
    },
    
    /**
     * Format source
     */
    formatSource(source) {
        const sources = {
            'manual': 'Manually Set',
            'preset_schedule': 'Preset Schedule',
            'automatic': 'Automatic'
        };
        return sources[source] || source;
    },
    
    /**
     * Cleanup
     */
    destroy() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
    }
};

