/**
 * Draw Control Module
 * Handles manual/auto mode, timer, winning number selection, and draw info
 */

class DrawControl {
    constructor() {
        this.isAutoMode = true;
        this.timerValue = 60;
        this.timerRunning = false;
        this.timerInterval = null;
        this.drawInfo = null;
        this.refreshInterval = null;
    }

    /**
     * Initialize the module
     */
    init() {
        console.log('🎮 DrawControl module initialized');
        this.setupEventListeners();
        this.loadDrawInfo();
        this.startDrawInfoRefresh();
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        const toggleBtn = Utils.$('#toggleAutoMode');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => this.toggleMode());
        }

        const timerInput = Utils.$('#timerInterval');
        if (timerInput) {
            timerInput.addEventListener('change', (e) => {
                this.timerValue = parseInt(e.target.value) || 60;
            });
        }

        const setWinningNumberBtn = Utils.$('#setWinningNumberBtn');
        if (setWinningNumberBtn) {
            setWinningNumberBtn.addEventListener('click', () => this.setWinningNumber());
        }
    }

    /**
     * Load draw info
     */
    async loadDrawInfo() {
        try {
            console.log('📊 Loading draw info from API...');
            const data = await apiClient.getDrawInfo();
            
            console.log('📊 Full API response:', data);
            
            if (data.status === 'success') {
                // Handle both nested (data.data) and flat (data) structures
                this.drawInfo = data.data || data;
                console.log('📊 Extracted draw info:', this.drawInfo);
                
                // Update UI with the draw info
                this.updateUI();
                
                return this.drawInfo;
            } else {
                console.error('❌ API returned error status:', data.message || 'Unknown error');
                this.showError('Failed to load: ' + (data.message || 'Unknown error'));
                return null;
            }
        } catch (error) {
            console.error('❌ Error loading draw info:', error);
            console.error('Error details:', {
                message: error.message,
                stack: error.stack
            });
            this.showError('Error: ' + error.message);
            return null;
        }
    }

    /**
     * Show error message in UI
     */
    showError(message) {
        console.error('🚨 Showing error:', message);
        const drawNumberEl = Utils.$('#currentDrawNumber');
        const lastDrawTimeEl = Utils.$('#lastDrawTime');
        const nextDrawTimeEl = Utils.$('#nextDrawTime');
        
        if (drawNumberEl) drawNumberEl.textContent = 'Error';
        if (lastDrawTimeEl) lastDrawTimeEl.textContent = message.substring(0, 30) + '...';
        if (nextDrawTimeEl) nextDrawTimeEl.textContent = '-';
    }

    /**
     * Update UI with draw info
     */
    updateUI() {
        if (!this.drawInfo) {
            console.warn('⚠️ No draw info available to update UI');
            return;
        }

        console.log('🔄 Updating UI with draw info:', this.drawInfo);

        // Update mode
        this.isAutoMode = this.drawInfo.is_automatic || false;
        this.updateModeDisplay();

        // Update timer
        if (this.drawInfo.timer_seconds) {
            this.timerValue = this.drawInfo.timer_seconds;
            const timerInput = Utils.$('#timerInterval');
            if (timerInput) {
                timerInput.value = this.timerValue;
            }
        }

        // Update current draw number (API returns 'current_draw' in data object)
        const drawNumberEl = Utils.$('#currentDrawNumber');
        if (drawNumberEl) {
            // Check all possible field names
            const drawNumber = this.drawInfo.current_draw || 
                               this.drawInfo.current_draw_number || 
                               this.drawInfo.currentDrawNumber || 
                               null;
            if (drawNumber !== null && drawNumber !== undefined && drawNumber !== '') {
                drawNumberEl.textContent = `#${drawNumber}`;
                console.log('✅ Updated current draw number:', drawNumber);
            } else {
                drawNumberEl.textContent = '-';
                console.warn('⚠️ No current draw number available. Draw info:', this.drawInfo);
            }
        } else {
            console.warn('⚠️ currentDrawNumber element not found in DOM');
        }

        // Update last draw time (API returns 'last_draw' in data object)
        const lastDrawTimeEl = Utils.$('#lastDrawTime');
        if (lastDrawTimeEl) {
            // Check all possible field names
            const lastDrawTime = this.drawInfo.last_draw || 
                                 this.drawInfo.last_draw_time || 
                                 this.drawInfo.lastDrawTime || 
                                 null;
            if (lastDrawTime && lastDrawTime !== '' && lastDrawTime !== '-') {
                try {
                    const formatted = Utils.formatDateTime(lastDrawTime);
                    lastDrawTimeEl.textContent = formatted;
                    console.log('✅ Updated last draw time:', lastDrawTime, '→', formatted);
                } catch (e) {
                    lastDrawTimeEl.textContent = lastDrawTime;
                    console.warn('⚠️ Could not format last draw time:', e);
                }
            } else {
                lastDrawTimeEl.textContent = '-';
                console.warn('⚠️ No last draw time available. Draw info:', this.drawInfo);
            }
        } else {
            console.warn('⚠️ lastDrawTime element not found in DOM');
        }

        // Update next draw time (API returns 'next_draw' in data object)
        const nextDrawTimeEl = Utils.$('#nextDrawTime');
        if (nextDrawTimeEl) {
            // Check all possible field names
            const nextDrawTime = this.drawInfo.next_draw || 
                                 this.drawInfo.next_draw_time || 
                                 this.drawInfo.nextDrawTime || 
                                 null;
            if (nextDrawTime && nextDrawTime !== '' && nextDrawTime !== '-') {
                try {
                    const formatted = Utils.formatDateTime(nextDrawTime);
                    nextDrawTimeEl.textContent = formatted;
                    console.log('✅ Updated next draw time:', nextDrawTime, '→', formatted);
                } catch (e) {
                    nextDrawTimeEl.textContent = nextDrawTime;
                    console.warn('⚠️ Could not format next draw time:', e);
                }
            } else {
                // Calculate next draw time if not provided (3 minutes from now)
                const calculatedTime = this.calculateNextDrawTime();
                nextDrawTimeEl.textContent = calculatedTime;
                console.log('✅ Calculated next draw time:', calculatedTime);
            }
        } else {
            console.warn('⚠️ nextDrawTime element not found in DOM');
        }
    }

    /**
     * Calculate next draw time (3 minutes from now)
     */
    calculateNextDrawTime() {
        const now = new Date();
        const nextDraw = new Date(now.getTime() + (3 * 60 * 1000)); // 3 minutes
        return Utils.formatTime(nextDraw);
    }

    /**
     * Update mode display
     */
    updateModeDisplay() {
        const toggleBtn = Utils.$('#toggleAutoMode');
        const modeDisplay = Utils.$('#currentMode');
        
        if (toggleBtn) {
            toggleBtn.textContent = this.isAutoMode ? 'Switch to Manual' : 'Switch to Auto';
        }
        
        if (modeDisplay) {
            modeDisplay.textContent = this.isAutoMode ? 'Automatic' : 'Manual';
        }
    }

    /**
     * Toggle manual/auto mode
     */
    async toggleMode() {
        try {
            // Determine the new mode (toggle from current)
            const newMode = this.isAutoMode ? 'manual' : 'automatic';
            console.log(`🔄 Toggling mode from ${this.isAutoMode ? 'Automatic' : 'Manual'} to ${newMode}...`);
            
            // Send mode parameter to API
            const data = await apiClient.toggleMode(newMode);
            
            if (data.status === 'success') {
                // Update local state
                this.isAutoMode = !this.isAutoMode;
                this.updateModeDisplay();
                
                // Show success message
                console.log('✅ Mode toggled successfully to:', this.isAutoMode ? 'Automatic' : 'Manual');
            } else {
                throw new Error(data.message || 'Failed to toggle mode');
            }
        } catch (error) {
            console.error('❌ Error toggling mode:', error);
            alert(`Failed to toggle mode: ${error.message}`);
            throw error;
        }
    }

    /**
     * Set winning number
     */
    async setWinningNumber() {
        const winningNumberInput = Utils.$('#winningNumberInput');
        if (!winningNumberInput) {
            alert('Winning number input field not found');
            return;
        }

        const winningNumberValue = winningNumberInput.value.trim();
        if (!winningNumberValue) {
            alert('Please enter a winning number (0-36)');
            return;
        }

        const winningNumber = parseInt(winningNumberValue);
        if (isNaN(winningNumber) || !Utils.validateNumber(winningNumber)) {
            alert('Please enter a valid number (0-36)');
            return;
        }

        const selectedDraw = drawSelection.getSelectedDraw();
        if (!selectedDraw) {
            alert('Please select a draw first');
            return;
        }

        try {
            console.log(`🎲 Setting winning number ${winningNumber} for draw #${selectedDraw.draw_number}...`);
            console.log('📤 Sending data:', {
                draw_number: selectedDraw.draw_number,
                winning_number: winningNumber,
                keep_auto_mode: this.isAutoMode
            });
            
            const data = await apiClient.setWinningNumber(
                selectedDraw.draw_number,
                winningNumber,
                this.isAutoMode
            );
            
            if (data.status === 'success') {
                console.log('✅ Winning number set successfully');
                
                // Sync to Firestore for real-time TV display sync
                if (window.FirestoreService && typeof window.FirestoreService.isAvailable === 'function' && window.FirestoreService.isAvailable()) {
                    try {
                        const drawNumber = selectedDraw.draw_number;
                        const winningColor = data.data?.winning_color || this.getNumberColor(winningNumber);
                        const source = this.isAutoMode ? 'auto' : 'manual';
                        
                        // Write winning number to Firestore
                        await window.FirestoreService.writeWinningNumber(drawNumber, winningNumber, winningColor, source);
                        console.log('✅ Winning number synced to Firestore');
                        
                        // Create spin command for synchronized execution (2 seconds from now)
                        const syncTimestamp = new Date(Date.now() + 2000);
                        await window.FirestoreService.writeSpinCommand(winningNumber, drawNumber, syncTimestamp, 'admin');
                        console.log('✅ Spin command created in Firestore');
                    } catch (firestoreError) {
                        console.warn('⚠️ Firestore sync failed (non-critical):', firestoreError);
                    }
                } else {
                    console.log('ℹ️ Firestore not available, skipping sync');
                }
                
                alert(`Winning number ${winningNumber} set for draw #${selectedDraw.draw_number}`);
                winningNumberInput.value = '';
                
                // Reload draw info
                await this.loadDrawInfo();
                
                // Refresh forced number checker to show the newly set number
                if (typeof forcedNumbers !== 'undefined' && forcedNumbers) {
                    console.log('🔄 Refreshing forced number checker after setting winning number...');
                    // Use the checkbox state to determine if we should auto-apply
                    const autoApplyCheckbox = Utils.$('#autoApplyForcedNumber');
                    const shouldAutoApply = autoApplyCheckbox ? autoApplyCheckbox.checked : false;
                    await forcedNumbers.checkForcedNumber(shouldAutoApply);
                }
            } else {
                throw new Error(data.message || 'Failed to set winning number');
            }
        } catch (error) {
            console.error('❌ Error setting winning number:', error);
            console.error('Error details:', {
                message: error.message,
                stack: error.stack
            });
            alert(`Failed to set winning number: ${error.message}`);
        }
    }

    /**
     * Helper function to get roulette number color
     */
    getNumberColor(number) {
        if (number === 0) {
            return 'green';
        }
        const redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
        return redNumbers.includes(number) ? 'red' : 'black';
    }

    /**
     * Start draw info refresh
     */
    startDrawInfoRefresh(interval = 5000) {
        this.stopDrawInfoRefresh();
        this.refreshInterval = setInterval(() => {
            this.loadDrawInfo();
        }, interval);
    }

    /**
     * Stop draw info refresh
     */
    stopDrawInfoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
    }

    /**
     * Get current mode
     */
    getMode() {
        return this.isAutoMode ? 'auto' : 'manual';
    }
}

// Create global instance
const drawControl = new DrawControl();

