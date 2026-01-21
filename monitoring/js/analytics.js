/**
 * Analytics Verification Module
 * Features from test_analytics_verification.html
 */

const AnalyticsModule = {
    /**
     * Load server info
     */
    async loadServerInfo() {
        try {
            const data = await safeFetch(`${API_BASE}/get_current_draw.php`);
            
            if (data.status === 'success' && data.data) {
                document.getElementById('serverTime').textContent = data.data.server_time?.formatted || 'N/A';
                document.getElementById('serverTimezone').textContent = data.data.server_time?.timezone || 'N/A';
                document.getElementById('currentDrawNumber').textContent = '#' + (data.data.current_draw_number || 'N/A');
                document.getElementById('nextDrawNumber').textContent = '#' + ((data.data.current_draw_number || 0) + 1);
            } else {
                document.getElementById('serverTime').textContent = 'Error loading';
                document.getElementById('serverTimezone').textContent = 'Error loading';
                document.getElementById('currentDrawNumber').textContent = '#N/A';
                document.getElementById('nextDrawNumber').textContent = '#N/A';
            }
        } catch (error) {
            console.error('Error loading server info:', error);
            const errorMsg = error.message || 'Failed to connect';
            document.getElementById('serverTime').textContent = `Error: ${errorMsg}`;
            document.getElementById('serverTimezone').textContent = 'API Error';
            document.getElementById('currentDrawNumber').textContent = '#Error';
            document.getElementById('nextDrawNumber').textContent = '#Error';
            console.error('API URL attempted:', `${API_BASE}/get_current_draw.php`);
        }
    },
    
    /**
     * Load analytics history
     */
    async loadAnalyticsHistory() {
        const loadingEl = document.getElementById('analyticsLoading');
        const errorEl = document.getElementById('analyticsError');
        const tableEl = document.getElementById('analyticsTable');
        const bodyEl = document.getElementById('analyticsBody');
        
        showLoading('analyticsLoading');
        errorEl.style.display = 'none';
        tableEl.style.display = 'none';
        
        try {
            const data = await safeFetch(`${API_BASE}/get_analytics_history.php?limit=20`);
            
            document.getElementById('analyticsLoading').style.display = 'none';
            
            document.getElementById('analyticsLoading').style.display = 'none';
            
            if (data.status === 'success' && data.data && Array.isArray(data.data.draws) && data.data.draws.length > 0) {
                bodyEl.innerHTML = '';
                
                data.data.draws.forEach(draw => {
                    const row = document.createElement('tr');
                    const color = draw.winning_color || getColorForNumber(draw.winning_number);
                    row.innerHTML = `
                        <td class="draw-number">#${draw.draw_number}</td>
                        <td>${formatNumberBadge(draw.winning_number, color)}</td>
                        <td><span class="badge bg-${color === 'red' ? 'danger' : color === 'black' ? 'dark' : 'success'}">${color}</span></td>
                        <td>${draw.draw_time || 'N/A'}</td>
                        <td><span class="badge bg-${draw.source === 'preset_schedule' ? 'primary' : draw.source === 'manual' ? 'warning' : 'secondary'}">${draw.source || 'unknown'}</span></td>
                        <td>${draw.is_preset ? '✅ Yes' : '❌ No'}</td>
                        <td>${draw.pattern_type || '-'}</td>
                    `;
                    bodyEl.appendChild(row);
                });
                
                tableEl.style.display = 'table';
            } else {
                showError('analyticsError', 'No analytics data available. API may be down or no draws exist yet.');
                errorEl.style.display = 'block';
            }
        } catch (error) {
            document.getElementById('analyticsLoading').style.display = 'none';
            const errorMsg = error.message || 'Unknown error';
            showError('analyticsError', `Error loading analytics: ${errorMsg}. Check API endpoint: ${API_BASE}/get_analytics_history.php`);
            errorEl.style.display = 'block';
        }
    },
    
    /**
     * Load preset schedule
     */
    async loadPresetSchedule() {
        const loadingEl = document.getElementById('presetLoading');
        const errorEl = document.getElementById('presetError');
        const tableEl = document.getElementById('presetTable');
        const bodyEl = document.getElementById('presetBody');
        
        showLoading('presetLoading');
        errorEl.style.display = 'none';
        tableEl.style.display = 'none';
        
        try {
            // Get current draw first
            const currentDrawRes = await safeFetch(`${API_BASE}/get_current_draw.php`);
            const currentDraw = currentDrawRes.data?.current_draw_number || 1;
            
            // Load preset schedule
            const data = await safeFetch(`${API_BASE}/load_preset_schedule.php?current_draw=${currentDraw}`);
            
            document.getElementById('presetLoading').style.display = 'none';
            
            if (data.status === 'success' && data.data) {
                const preset = data.data;
                
                // Update preset info
                document.getElementById('presetStatus').innerHTML = preset.is_active ? 
                    '<span class="badge bg-success">Active</span>' : 
                    '<span class="badge bg-warning">Inactive</span>';
                document.getElementById('presetStart').textContent = '#' + (preset.start_draw_number || '-');
                document.getElementById('presetEnd').textContent = '#' + (preset.end_draw_number || '-');
                document.getElementById('presetTotal').textContent = preset.total_draws || '-';
                
                if (preset.schedule_data && Array.isArray(preset.schedule_data)) {
                    bodyEl.innerHTML = '';
                    
                    const startDraw = preset.start_draw_number || 1;
                    const endDraw = preset.end_draw_number || 480;
                    const scheduleData = preset.schedule_data;
                    
                    // Show draws around current draw
                    const showStart = Math.max(startDraw, currentDraw - 10);
                    const showEnd = Math.min(endDraw, currentDraw + 10);
                    
                    for (let drawNum = showStart; drawNum <= showEnd && drawNum <= endDraw; drawNum++) {
                        const index = drawNum - startDraw;
                        if (index < 0 || index >= scheduleData.length) continue;
                        
                        const scheduleItem = scheduleData[index];
                        let winningNumber, color, scheduledTime, pattern;
                        
                        if (typeof scheduleItem === 'object' && scheduleItem !== null) {
                            winningNumber = scheduleItem.winning_number || scheduleItem;
                            color = scheduleItem.color || getColorForNumber(winningNumber);
                            scheduledTime = scheduleItem.scheduled_time || calculateDrawTime(drawNum);
                            pattern = scheduleItem.pattern || '-';
                        } else {
                            winningNumber = scheduleItem;
                            color = getColorForNumber(winningNumber);
                            scheduledTime = calculateDrawTime(drawNum);
                            pattern = '-';
                        }
                        
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td class="draw-number">#${drawNum}</td>
                            <td>${formatNumberBadge(winningNumber, color)}</td>
                            <td><span class="badge bg-${color === 'red' ? 'danger' : color === 'black' ? 'dark' : 'success'}">${color}</span></td>
                            <td>${scheduledTime}</td>
                            <td>${pattern}</td>
                        `;
                        bodyEl.appendChild(row);
                    }
                    
                    tableEl.style.display = 'table';
                } else {
                    showError('presetError', 'No schedule data available');
                }
            } else {
                showError('presetError', 'No active preset schedule found');
            }
        } catch (error) {
            document.getElementById('presetLoading').style.display = 'none';
            showError('presetError', 'Error loading preset schedule: ' + error.message);
        }
    },
    
    /**
     * Load forced numbers
     */
    async loadForcedNumbers() {
        const loadingEl = document.getElementById('forcedLoading');
        const errorEl = document.getElementById('forcedError');
        const tableEl = document.getElementById('forcedTable');
        const bodyEl = document.getElementById('forcedBody');
        
        showLoading('forcedLoading');
        errorEl.style.display = 'none';
        tableEl.style.display = 'none';
        
        try {
            const currentDrawRes = await safeFetch(`${API_BASE}/get_current_draw.php`);
            const currentDraw = currentDrawRes.data?.current_draw_number || 1;
            
            const presetRes = await safeFetch(`${API_BASE}/load_preset_schedule.php?current_draw=${currentDraw}`);
            const preset = presetRes.data;
            
            bodyEl.innerHTML = '';
            let hasAnyNumber = false;
            
            for (let i = 0; i < 10; i++) {
                const drawNum = currentDraw + i;
                try {
                    const data = await safeFetch(`${API_BASE}/direct_forced_number.php?draw_number=${drawNum}`);
                    
                    if (data.status === 'success' && data.has_forced_number) {
                        hasAnyNumber = true;
                        const isManual = data.source === 'manual';
                        const sourceText = isManual ? 'Manually Set' : (data.source === 'preset_schedule' ? 'Preset Schedule' : 'Automatic');
                        const sourceClass = isManual ? 'warning' : (data.source === 'preset_schedule' ? 'primary' : 'secondary');
                        const color = data.forced_color || getColorForNumber(data.forced_number);
                        
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td class="draw-number">#${drawNum}</td>
                            <td>${formatNumberBadge(data.forced_number, color)}</td>
                            <td><span class="badge bg-${color === 'red' ? 'danger' : color === 'black' ? 'dark' : 'success'}">${color}</span></td>
                            <td><span class="badge bg-${sourceClass}">${sourceText}</span></td>
                            <td>${isManual ? (data.forced_number_reason || 'Set by administrator') : (data.source === 'preset_schedule' ? 'From preset schedule' : '-')}</td>
                        `;
                        bodyEl.appendChild(row);
                    } else if (preset && preset.is_active && preset.schedule_data) {
                        const startDraw = preset.start_draw_number || 1;
                        const endDraw = preset.end_draw_number || 480;
                        if (drawNum >= startDraw && drawNum <= endDraw) {
                            const index = drawNum - startDraw;
                            if (index >= 0 && index < preset.schedule_data.length) {
                                const scheduleItem = preset.schedule_data[index];
                                const presetNum = typeof scheduleItem === 'object' ? (scheduleItem.winning_number || scheduleItem) : scheduleItem;
                                const presetColor = getColorForNumber(parseInt(presetNum));
                                
                                hasAnyNumber = true;
                                const row = document.createElement('tr');
                                row.innerHTML = `
                                    <td class="draw-number">#${drawNum}</td>
                                    <td>${formatNumberBadge(presetNum, presetColor)}</td>
                                    <td><span class="badge bg-${presetColor === 'red' ? 'danger' : presetColor === 'black' ? 'dark' : 'success'}">${presetColor}</span></td>
                                    <td><span class="badge bg-primary">Preset Schedule</span></td>
                                    <td>From preset schedule</td>
                                `;
                                bodyEl.appendChild(row);
                            }
                        }
                    }
                } catch (error) {
                    // Skip errors
                }
            }
            
            document.getElementById('forcedLoading').style.display = 'none';
            
            if (hasAnyNumber) {
                tableEl.style.display = 'table';
            } else {
                showError('forcedError', 'No preset schedule or forced numbers found for upcoming draws');
            }
        } catch (error) {
            document.getElementById('forcedLoading').style.display = 'none';
            showError('forcedError', 'Error loading forced numbers: ' + error.message);
        }
    },
    
    /**
     * Load comparison
     */
    async loadComparison() {
        const loadingEl = document.getElementById('comparisonLoading');
        const errorEl = document.getElementById('comparisonError');
        const tableEl = document.getElementById('comparisonTable');
        const bodyEl = document.getElementById('comparisonBody');
        
        showLoading('comparisonLoading');
        errorEl.style.display = 'none';
        tableEl.style.display = 'none';
        
        try {
            const currentDrawRes = await safeFetch(`${API_BASE}/get_current_draw.php`);
            const currentDraw = currentDrawRes.data?.current_draw_number || 1;
            
            const [analyticsRes, presetRes] = await Promise.all([
                safeFetch(`${API_BASE}/get_analytics_history.php?limit=20`),
                safeFetch(`${API_BASE}/load_preset_schedule.php?current_draw=${currentDraw}`)
            ]);
            
            const analyticsDraws = analyticsRes.data?.draws || [];
            const preset = presetRes.data;
            
            if (analyticsDraws.length === 0) {
                document.getElementById('comparisonLoading').style.display = 'none';
                showError('comparisonError', 'No analytics data available for comparison');
                return;
            }
            
            bodyEl.innerHTML = '';
            
            const analyticsDrawNumbers = analyticsDraws.map(d => d.draw_number).sort((a, b) => b - a);
            const drawsToCompare = analyticsDrawNumbers.slice(0, 10);
            
            for (const drawNum of drawsToCompare) {
                const analyticsDraw = analyticsDraws.find(d => d.draw_number === drawNum);
                
                let presetNumber = null;
                if (preset && preset.is_active && preset.schedule_data) {
                    const startDraw = preset.start_draw_number || 1;
                    const endDraw = preset.end_draw_number || 480;
                    if (drawNum >= startDraw && drawNum <= endDraw) {
                        const index = drawNum - startDraw;
                        if (index >= 0 && index < preset.schedule_data.length) {
                            const scheduleItem = preset.schedule_data[index];
                            presetNumber = typeof scheduleItem === 'object' ? (scheduleItem.winning_number || scheduleItem) : scheduleItem;
                            presetNumber = parseInt(presetNumber);
                        }
                    }
                }
                
                let forcedNumber = null;
                let forcedSource = null;
                try {
                    const forcedRes = await safeFetch(`${API_BASE}/direct_forced_number.php?draw_number=${drawNum}`);
                    if (forcedRes.has_forced_number && forcedRes.draw_number === drawNum) {
                        forcedNumber = parseInt(forcedRes.forced_number);
                        forcedSource = forcedRes.source || 'unknown';
                    }
                } catch (e) {
                    // Skip
                }
                
                const analyticsNum = analyticsDraw ? parseInt(analyticsDraw.winning_number) : null;
                
                let matchStatus = 'unknown';
                let matchClass = '';
                if (analyticsNum !== null) {
                    const inPresetRange = preset && preset.is_active && 
                                        drawNum >= (preset.start_draw_number || 1) && 
                                        drawNum <= (preset.end_draw_number || 480);
                    
                    if (presetNumber !== null && forcedNumber !== null) {
                        const isManualForced = forcedSource === 'manual';
                        
                        if (analyticsNum === forcedNumber && analyticsNum === presetNumber) {
                            matchStatus = '✅ All Match';
                            matchClass = 'text-success';
                        } else if (isManualForced && analyticsNum === forcedNumber) {
                            matchStatus = '✅ Match (Manual Override)';
                            matchClass = 'text-success';
                        } else if (!isManualForced && analyticsNum === presetNumber) {
                            matchStatus = '✅ Match (Preset Override)';
                            matchClass = 'text-success';
                        } else {
                            matchStatus = '❌ Mismatch';
                            matchClass = 'text-danger';
                        }
                    } else if (presetNumber !== null) {
                        matchStatus = (analyticsNum === presetNumber) ? '✅ Match' : '❌ Mismatch';
                        matchClass = (analyticsNum === presetNumber) ? 'text-success' : 'text-danger';
                    } else if (forcedNumber !== null) {
                        matchStatus = (analyticsNum === forcedNumber) ? '✅ Match' : '❌ Mismatch';
                        matchClass = (analyticsNum === forcedNumber) ? 'text-success' : 'text-danger';
                    } else {
                        if (!inPresetRange) {
                            matchStatus = 'ℹ️ Outside preset range';
                            matchClass = 'text-info';
                        } else {
                            matchStatus = 'ℹ️ No preset/forced';
                            matchClass = 'text-info';
                        }
                    }
                } else {
                    matchStatus = '⚠️ No analytics data';
                    matchClass = 'text-warning';
                }
                
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td class="draw-number">#${drawNum}</td>
                    <td>${analyticsNum !== null ? 
                        formatNumberBadge(analyticsNum, analyticsDraw.winning_color) : 
                        '<span class="text-muted">N/A</span>'}</td>
                    <td>${presetNumber !== null ? 
                        formatNumberBadge(presetNumber, getColorForNumber(presetNumber)) : 
                        '<span class="text-muted">N/A</span>'}</td>
                    <td>${forcedNumber !== null ? 
                        formatNumberBadge(forcedNumber, getColorForNumber(forcedNumber)) : 
                        '<span class="text-muted">N/A</span>'}</td>
                    <td><span class="${matchClass} fw-bold">${matchStatus}</span></td>
                `;
                bodyEl.appendChild(row);
            }
            
            document.getElementById('comparisonLoading').style.display = 'none';
            tableEl.style.display = 'table';
        } catch (error) {
            document.getElementById('comparisonLoading').style.display = 'none';
            showError('comparisonError', 'Error loading comparison: ' + error.message);
        }
    },
    
    /**
     * Load all analytics data
     */
    async loadAll() {
        console.log('AnalyticsModule.loadAll() called');
        try {
            await Promise.all([
                this.loadServerInfo(),
                this.loadAnalyticsHistory(),
                this.loadPresetSchedule(),
                this.loadForcedNumbers(),
                this.loadComparison()
            ]);
            console.log('AnalyticsModule.loadAll() completed');
        } catch (error) {
            console.error('Error in AnalyticsModule.loadAll():', error);
        }
    }
};

window.AnalyticsModule = AnalyticsModule;

