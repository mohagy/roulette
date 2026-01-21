/**
 * Forced Numbers Module
 * Handles checking, displaying, and applying forced numbers
 */

class ForcedNumbers {
    constructor() {
        this.currentForcedNumber = null;
        this.checkInterval = null;
        this.autoApply = false;
    }

    /**
     * Initialize the module
     */
    init() {
        console.log('🔒 ForcedNumbers module initialized');
        
        // Load saved auto-apply state - try database first, fallback to localStorage
        this.loadAutoApplySetting();
        
        // Update checkbox state
        const autoApplyCheckbox = Utils.$('#autoApplyForcedNumber');
        if (autoApplyCheckbox) {
            autoApplyCheckbox.checked = this.autoApply;
            console.log('✅ Auto-apply checkbox found and set to:', this.autoApply);
        } else {
            console.warn('⚠️ Auto-apply checkbox not found');
        }
        
        this.setupEventListeners();
        
        // Initial check (but don't auto-apply on init unless enabled)
        this.checkForcedNumber(false); // Pass false to skip auto-apply on init
        
        // If auto-apply is enabled, start auto-check
        if (this.autoApply) {
            this.startAutoCheck(5000); // Check every 5 seconds when auto-apply is enabled
        }
    }

    /**
     * Load auto-apply setting from database
     */
    async loadAutoApplySetting() {
        try {
            // First try to load from database
            const response = await fetch('../api/get_auto_apply_setting.php?_cb=' + Date.now());
            
            if (response.ok) {
                const result = await response.json();
                if (result.status === 'success' && result.data) {
                    this.autoApply = result.data.auto_apply === true;
                    console.log('✅ Auto-apply setting loaded from database:', this.autoApply);
                    
                    // Update localStorage to match database
                    localStorage.setItem('autoApplyForcedNumber', this.autoApply.toString());
                    
                    // Update checkbox if it exists
                    const autoApplyCheckbox = Utils.$('#autoApplyForcedNumber');
                    if (autoApplyCheckbox) {
                        autoApplyCheckbox.checked = this.autoApply;
                    }
                    
                    return;
                }
            }
        } catch (error) {
            console.warn('⚠️ Error loading auto-apply setting from database:', error);
        }
        
        // Fallback to localStorage if database fails
        const savedAutoApply = localStorage.getItem('autoApplyForcedNumber');
        if (savedAutoApply !== null) {
            this.autoApply = savedAutoApply === 'true';
            console.log('✅ Auto-apply setting loaded from localStorage:', this.autoApply);
        }
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        const checkBtn = Utils.$('#checkForcedNumberBtn');
        if (checkBtn) {
            checkBtn.addEventListener('click', () => {
                console.log('🔘 Check Forced Number button clicked');
                // When manually clicking, check shouldAutoApply based on checkbox state
                // Pass false to shouldAutoApply so manual clicks don't auto-apply unless checkbox is checked
                this.checkForcedNumber(this.autoApply);
            });
        }

        const applyBtn = Utils.$('#applyForcedNumberBtn');
        if (applyBtn) {
            applyBtn.addEventListener('click', () => this.applyForcedNumber());
        }

        const autoApplyCheckbox = Utils.$('#autoApplyForcedNumber');
        if (autoApplyCheckbox) {
            autoApplyCheckbox.addEventListener('change', async (e) => {
                this.autoApply = e.target.checked;
                
                // Save state to localStorage for immediate use
                localStorage.setItem('autoApplyForcedNumber', this.autoApply.toString());
                console.log('✅ Auto-apply state saved to localStorage:', this.autoApply);
                
                // Save to database for persistence
                try {
                    const response = await fetch('../api/save_auto_apply_setting.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `auto_apply=${this.autoApply ? 1 : 0}`
                    });
                    
                    const result = await response.json();
                    if (result.status === 'success') {
                        console.log('✅ Auto-apply setting saved to database:', this.autoApply);
                    } else {
                        console.warn('⚠️ Failed to save auto-apply setting to database:', result.message);
                    }
                } catch (error) {
                    console.error('❌ Error saving auto-apply setting to database:', error);
                }
                
                if (this.autoApply) {
                    this.startAutoCheck(5000); // Check every 5 seconds when auto-apply is enabled
                    // Immediately check for forced number
                    this.checkForcedNumber();
                } else {
                    this.stopAutoCheck();
                    console.log('⏸️ Auto-apply disabled, stopped auto-check');
                }
            });
        }
    }

    /**
     * Check for forced number
     * @param {boolean} shouldAutoApply - Whether to auto-apply if found (default: true)
     */
    async checkForcedNumber(shouldAutoApply = true) {
        try {
            console.log('🔍 Checking for forced number...', { autoApply: this.autoApply, shouldAutoApply });
            
            // Get current draw number and calculate NEXT draw number
            let currentDraw = null;
            let nextDraw = null;
            try {
                const drawInfo = await apiClient.getDrawInfo();
                if (drawInfo.status === 'success' && drawInfo.data) {
                    currentDraw = drawInfo.data.current_draw || drawInfo.data.current_draw_number || 1;
                    // Calculate next draw (wrap at 480)
                    nextDraw = (currentDraw >= 480) ? 1 : (currentDraw + 1);
                    console.log('📊 Current draw number:', currentDraw, 'Next draw:', nextDraw);
                } else {
                    console.warn('⚠️ Could not get draw info:', drawInfo);
                    // Fallback: calculate from time
                    const now = new Date();
                    const hours = now.getHours();
                    const minutes = now.getMinutes();
                    const totalMinutes = (hours * 60) + minutes;
                    const completedIntervals = Math.floor(totalMinutes / 3);
                    currentDraw = completedIntervals + 1;
                    if (currentDraw > 480) currentDraw = 480;
                    nextDraw = (currentDraw >= 480) ? 1 : (currentDraw + 1);
                }
            } catch (drawInfoError) {
                console.warn('⚠️ Error getting draw info:', drawInfoError);
                // Fallback calculation
                const now = new Date();
                const hours = now.getHours();
                const minutes = now.getMinutes();
                const totalMinutes = (hours * 60) + minutes;
                const completedIntervals = Math.floor(totalMinutes / 3);
                currentDraw = completedIntervals + 1;
                if (currentDraw > 480) currentDraw = 480;
                nextDraw = (currentDraw >= 480) ? 1 : (currentDraw + 1);
            }
            
            // Use NEXT draw number for preset schedule (upcoming draw)
            const targetDraw = nextDraw || (currentDraw + 1);
            
            // Also check if there's a selected draw from the draw selection (user might have selected a specific draw)
            let selectedDrawNumber = null;
            try {
                if (typeof drawSelection !== 'undefined' && drawSelection && typeof drawSelection.getSelectedDraw === 'function') {
                    const selectedDraw = drawSelection.getSelectedDraw();
                    if (selectedDraw && selectedDraw.draw_number) {
                        selectedDrawNumber = selectedDraw.draw_number;
                        console.log('📌 User has selected draw #' + selectedDrawNumber);
                    }
                }
            } catch (e) {
                console.warn('⚠️ Could not get selected draw:', e);
            }
            
            // Priority: Use selected draw if available, otherwise check current draw (if it's the active one), then next draw
            // This ensures we check the draw the user is actually viewing
            const checkDrawNumber = selectedDrawNumber || currentDraw || targetDraw;
            
            // Log which draw we're checking
            if (selectedDrawNumber) {
                console.log('🎯 Checking selected draw #' + selectedDrawNumber);
            } else if (currentDraw) {
                console.log('🎯 Checking current draw #' + currentDraw + ' (no specific draw selected)');
            } else {
                console.log('🎯 Checking next draw #' + targetDraw);
            }
            
            // PRIORITY LOGIC:
            // If auto-apply is enabled, prioritize preset schedule over database
            // If auto-apply is disabled, prioritize database over preset schedule
            const prioritizePreset = this.autoApply;
            
            // Check database for manually set forced numbers FIRST (especially if auto-apply is disabled)
            // This ensures manually set numbers are always found, even for specific draws
            console.log('🔍 Checking database for manually set forced numbers for draw #' + checkDrawNumber + '...');
            let databaseForcedNumber = null;
            try {
                // Pass the specific draw number to the API to check for that exact draw
                const response = await apiClient.getForcedNumber(checkDrawNumber);
                console.log('📦 Database forced number response for draw #' + checkDrawNumber + ':', response);
                
                if (response.status === 'success' && response.has_forced_number && response.forced_number !== null) {
                    const dbDrawNumber = response.draw_number;
                    // If we requested a specific draw, only accept if it matches
                    if (dbDrawNumber === checkDrawNumber) {
                        databaseForcedNumber = {
                            draw_number: dbDrawNumber,
                            winning_number: response.forced_number,
                            source: 'database',
                            pattern: 'Manually set in database',
                            color: response.forced_color || 'red'
                        };
                        console.log('✅ Found database forced number for draw #' + dbDrawNumber + ':', databaseForcedNumber);
                    } else {
                        console.log('ℹ️ Database forced number is for draw #' + dbDrawNumber + ', but we\'re checking draw #' + checkDrawNumber);
                    }
                } else {
                    console.log('ℹ️ No forced number found in database for draw #' + checkDrawNumber);
                }
            } catch (dbError) {
                console.warn('⚠️ Error checking database:', dbError);
            }
            
            // Check preset schedule (only if no database forced number found, or if auto-apply is enabled)
            let presetForcedNumber = null;
            if ((prioritizePreset || !databaseForcedNumber) && checkDrawNumber) {
                try {
                    console.log('📅 Checking preset schedule for draw #' + checkDrawNumber + '...');
                    const presetResponse = await apiClient.getCurrentPreset(checkDrawNumber);
                    console.log('📅 Preset response:', presetResponse);
                    
                    if (presetResponse.status === 'success' && presetResponse.data && presetResponse.data.winning_number !== null) {
                        presetForcedNumber = {
                            draw_number: checkDrawNumber,
                            winning_number: presetResponse.data.winning_number,
                            source: 'preset_schedule',
                            pattern: presetResponse.data.pattern || 'Preset schedule',
                            color: presetResponse.data.color || 'red',
                            scheduled_time: presetResponse.data.scheduled_time || null
                        };
                        console.log('✅ Found preset number for draw #' + checkDrawNumber + ':', presetForcedNumber);
                    } else {
                        console.log('ℹ️ No preset number found for draw #' + checkDrawNumber);
                    }
                } catch (presetError) {
                    console.warn('⚠️ Error checking preset schedule:', presetError);
                }
            }
            
            // If auto-apply is disabled, check preset schedule as fallback
            if (!prioritizePreset && !databaseForcedNumber && targetDraw) {
                try {
                    console.log('📅 Checking preset schedule for NEXT draw #' + targetDraw + ' (fallback)...');
                    const presetResponse = await apiClient.getCurrentPreset(targetDraw);
                    console.log('📅 Preset response:', presetResponse);
                    
                    if (presetResponse.status === 'success' && presetResponse.data && presetResponse.data.winning_number !== null) {
                        presetForcedNumber = {
                            draw_number: targetDraw,
                            winning_number: presetResponse.data.winning_number,
                            source: 'preset_schedule',
                            pattern: presetResponse.data.pattern || 'Preset schedule',
                            color: presetResponse.data.color || 'red',
                            scheduled_time: presetResponse.data.scheduled_time || null
                        };
                        console.log('✅ Found preset number (fallback):', presetForcedNumber);
                    }
                } catch (presetError) {
                    console.warn('⚠️ Error checking preset schedule:', presetError);
                }
            }
            
            // Determine which forced number to use based on priority
            // When auto-apply is DISABLED: Database forced numbers take priority (manual override)
            // When auto-apply is ENABLED: Preset schedule takes priority
            if (!prioritizePreset && databaseForcedNumber) {
                // Auto-apply disabled: Use database forced number (manual override takes priority)
                this.currentForcedNumber = databaseForcedNumber;
                console.log('✅ Using database forced number (manual override, auto-apply disabled):', this.currentForcedNumber);
                this.displayForcedNumber();
                
                // Don't auto-apply when auto-apply is disabled
                return this.currentForcedNumber;
            } else if (prioritizePreset && presetForcedNumber) {
                // Auto-apply enabled: Use preset schedule
                this.currentForcedNumber = presetForcedNumber;
                console.log('✅ Using preset number (auto-apply enabled):', this.currentForcedNumber);
                this.displayForcedNumber();
                
                if (shouldAutoApply) {
                    console.log('⚡ Auto-applying preset number...');
                    await this.applyForcedNumber();
                }
                return this.currentForcedNumber;
            } else if (databaseForcedNumber) {
                // Database forced number (when auto-apply is enabled but no preset found)
                this.currentForcedNumber = databaseForcedNumber;
                console.log('✅ Using database forced number (fallback):', this.currentForcedNumber);
                this.displayForcedNumber();
                
                if (this.autoApply && shouldAutoApply) {
                    console.log('⚡ Auto-applying database forced number...');
                    await this.applyForcedNumber();
                }
                return this.currentForcedNumber;
            } else if (presetForcedNumber) {
                // Preset schedule (when auto-apply is disabled but no database forced number found)
                this.currentForcedNumber = presetForcedNumber;
                console.log('✅ Using preset number (fallback, auto-apply disabled):', this.currentForcedNumber);
                this.displayForcedNumber();
                
                // Don't auto-apply when auto-apply is disabled
                return this.currentForcedNumber;
            } else {
                // No forced number found
                console.log('ℹ️ No forced number found in database or preset schedule for draw #' + checkDrawNumber);
                this.currentForcedNumber = null;
                this.clearDisplay();
            }
        } catch (error) {
            console.error('❌ Error checking forced number:', error);
            console.error('Error details:', error.stack);
            this.currentForcedNumber = null;
            this.clearDisplay();
        }
    }

    /**
     * Display forced number info
     */
    displayForcedNumber() {
        if (!this.currentForcedNumber) {
            this.clearDisplay();
            return;
        }

        const container = Utils.$('#forcedNumberInfo');
        if (!container) {
            console.warn('⚠️ forcedNumberInfo container not found');
            return;
        }

        const drawNumber = this.currentForcedNumber.draw_number;
        const winningNumber = this.currentForcedNumber.winning_number;
        const source = this.currentForcedNumber.source || 'forced_number';
        const pattern = this.currentForcedNumber.pattern || 'Forced number';
        const color = Utils.getNumberColor(winningNumber);
        
        // Use scheduled_time from preset if available, otherwise calculate from draw number
        let scheduledTime = this.currentForcedNumber.scheduled_time;
        if (!scheduledTime) {
            // Calculate scheduled time for this draw number
            // Draw #1 = 00:00, Draw #2 = 00:03, ..., Draw #480 = 23:57
            const totalMinutes = (drawNumber - 1) * 3;
            const hours = Math.floor(totalMinutes / 60) % 24;
            const minutes = totalMinutes % 60;
            scheduledTime = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
        }
        
        const sourceLabel = source === 'preset_schedule' ? 'Preset Schedule' : 'Database Forced Number';
        const autoApplyBadge = this.autoApply ? '<span class="badge bg-success ms-2">Auto-Apply</span>' : '';
        const colorClass = color === 'red' ? 'danger' : color === 'green' ? 'success' : 'dark';

        container.innerHTML = `
            <div class="alert alert-info mb-0">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="mb-0">
                        <i class="fas fa-info-circle"></i> ${sourceLabel} Found ${autoApplyBadge}
                    </h6>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-3">
                        <small class="text-muted d-block">Draw #</small>
                        <strong>${drawNumber}</strong>
                    </div>
                    <div class="col-3">
                        <small class="text-muted d-block">Scheduled Time</small>
                        <strong>${scheduledTime}</strong>
                    </div>
                    <div class="col-3">
                        <small class="text-muted d-block">Number</small>
                        <span class="badge bg-${colorClass}">${winningNumber}</span>
                    </div>
                    <div class="col-3">
                        <small class="text-muted d-block">Pattern</small>
                        <small class="text-muted" title="${pattern}">${pattern.length > 15 ? pattern.substring(0, 15) + '...' : pattern}</small>
                    </div>
                </div>
                <div>
                    <button id="applyForcedNumberBtn" class="btn btn-primary btn-sm">
                        <i class="fas fa-check"></i> Apply Now
                    </button>
                    ${this.autoApply ? '<small class="text-muted ms-2">(Will auto-apply in background)</small>' : ''}
                </div>
            </div>
        `;

        // Re-attach event listener
        const applyBtn = Utils.$('#applyForcedNumberBtn');
        if (applyBtn) {
            applyBtn.addEventListener('click', () => this.applyForcedNumber());
        }
    }

    /**
     * Clear forced number display
     */
    clearDisplay() {
        const container = Utils.$('#forcedNumberInfo');
        if (container) {
            container.innerHTML = `
                <div class="alert alert-secondary mb-0">
                    <i class="fas fa-info-circle"></i> No forced number found
                    <br>
                    <small class="text-muted">
                        Checked: Preset schedule and database for current draw
                    </small>
                </div>
            `;
        }
    }

    /**
     * Apply forced number
     */
    async applyForcedNumber() {
        if (!this.currentForcedNumber) {
            console.warn('⚠️ No forced number to apply');
            return;
        }

        try {
            const drawNumber = this.currentForcedNumber.draw_number;
            const winningNumber = this.currentForcedNumber.winning_number;

            console.log(`✅ Applying forced number ${winningNumber} for draw #${drawNumber}...`);

            // Get current mode (check if drawControl exists and is available)
            let keepAutoMode = false;
            try {
                if (typeof drawControl !== 'undefined' && drawControl) {
                    keepAutoMode = drawControl.isAutoMode === true;
                }
            } catch (e) {
                console.warn('⚠️ Could not get draw control mode, defaulting to keep auto mode');
                keepAutoMode = true;
            }

            const response = await apiClient.setWinningNumber(
                drawNumber,
                winningNumber,
                keepAutoMode
            );

            if (response.status === 'success') {
                console.log('✅ Forced number applied successfully');
                
                // Sync to Firestore for real-time TV display sync
                if (window.FirestoreService && typeof window.FirestoreService.isAvailable === 'function' && window.FirestoreService.isAvailable()) {
                    try {
                        const winningColor = response.data?.winning_color || this.currentForcedNumber.color || this.getNumberColor(winningNumber);
                        const source = keepAutoMode ? 'auto' : 'manual';
                        
                        // Write winning number to Firestore
                        await window.FirestoreService.writeWinningNumber(drawNumber, winningNumber, winningColor, source);
                        console.log('✅ Forced number synced to Firestore');
                        
                        // Create spin command for synchronized execution (2 seconds from now)
                        const syncTimestamp = new Date(Date.now() + 2000);
                        await window.FirestoreService.writeSpinCommand(winningNumber, drawNumber, syncTimestamp, 'preset_schedule');
                        console.log('✅ Spin command created in Firestore');
                    } catch (firestoreError) {
                        console.warn('⚠️ Firestore sync failed (non-critical):', firestoreError);
                    }
                } else {
                    console.log('ℹ️ Firestore not available, skipping sync');
                }
                
                // Show success message (but not alert if auto-applying)
                if (!this.autoApply) {
                    alert(`Forced number ${winningNumber} applied for draw #${drawNumber}`);
                } else {
                    console.log(`✅ Auto-applied forced number ${winningNumber} for draw #${drawNumber}`);
                }
                
                // Don't clear forced number if auto-applying (it might be needed again)
                if (!this.autoApply) {
                    this.currentForcedNumber = null;
                    this.clearDisplay();
                }
            } else {
                throw new Error(response.message || 'Failed to apply forced number');
            }
        } catch (error) {
            console.error('❌ Error applying forced number:', error);
            if (!this.autoApply) {
                alert(`Failed to apply forced number: ${error.message}`);
            }
            throw error;
        }
    }

    /**
     * Start auto-check
     */
    startAutoCheck(interval = 30000) {
        this.stopAutoCheck();
        this.checkInterval = setInterval(() => {
            this.checkForcedNumber();
        }, interval);
    }

    /**
     * Stop auto-check
     */
    stopAutoCheck() {
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
            this.checkInterval = null;
        }
    }

    /**
     * Get current forced number
     */
    getCurrentForcedNumber() {
        return this.currentForcedNumber;
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
}

// Create global instance
const forcedNumbers = new ForcedNumbers();

