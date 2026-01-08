/**
 * Draw Control Dashboard
 * 
 * This module handles the functionality of the Draw Control management dashboard.
 * It allows administrators to toggle between automatic and manual winning number 
 * selection modes and provides feedback on the connection status with the TV display.
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('Draw control dashboard loaded');
    
    // Elements
    const autoModeToggle = document.getElementById('autoModeToggle');
    const modeLabel = document.getElementById('modeLabel');
    const automaticModeSection = document.getElementById('automaticModeSection');
    const manualModeSection = document.getElementById('manualModeSection');
    const testAutoSelection = document.getElementById('testAutoSelection');
    const testingArea = document.getElementById('testingArea');
    const testResults = document.getElementById('testResults');
    const selectedNumberSpan = document.getElementById('selectedNumber');
    const saveNumberBtn = document.getElementById('saveNumber');
    const currentDrawNumberSpan = document.getElementById('currentDrawNumber');
    const nextDrawNumberSpan = document.getElementById('nextDrawNumber');
    const tvStatusIndicator = document.getElementById('tvConnectionStatus');
    
    // Add TV connection status indicator if not already present
    addTvConnectionStatusIfNeeded();
    
    // Initialize number grid for manual selection
    const numberGrid = document.querySelectorAll('.number-grid')[1];
    const redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
    
    // Only initialize grid if it's empty
    if (numberGrid && numberGrid.children.length === 0) {
        for (let i = 1; i <= 36; i++) {
            const isRed = redNumbers.includes(i);
            const btn = document.createElement('button');
            btn.className = `number-btn ${isRed ? 'number-red' : 'number-black'}`;
            btn.dataset.number = i;
            btn.textContent = i;
            numberGrid.appendChild(btn);
        }
    }
    
    // Variables
    let selectedNumber = null;
    let currentDrawNumber = null;
    let nextDrawNumber = null;
    let isAutoMode = true;
    let tvConnectionCheckTimer = null;
    let lastTvConnectionCheck = null;
    
    // Toggle between automatic and manual modes
    if (autoModeToggle) {
        autoModeToggle.addEventListener('change', function() {
            isAutoMode = this.checked;
            modeLabel.textContent = `Automatic Selection Mode (${isAutoMode ? 'ON' : 'OFF'})`;
            
            if (isAutoMode) {
                automaticModeSection.style.display = 'block';
                manualModeSection.style.display = 'none';
                // When switching to auto mode, save the setting to the server
                toggleAutomaticMode(true);
            } else {
                automaticModeSection.style.display = 'none';
                manualModeSection.style.display = 'block';
                // When switching to manual mode, save the setting to the server
                toggleAutomaticMode(false);
            }
        });
    }
    
    // Number selection
    document.querySelectorAll('.number-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            // Remove selection from all numbers
            document.querySelectorAll('.number-btn').forEach(b => {
                b.classList.remove('selected-number');
            });
            
            // Add selection to clicked number
            this.classList.add('selected-number');
            selectedNumber = parseInt(this.dataset.number);
            selectedNumberSpan.textContent = selectedNumber;
            saveNumberBtn.disabled = false;
        });
    });
    
    // Save winning number
    if (saveNumberBtn) {
        saveNumberBtn.addEventListener('click', function() {
            if (selectedNumber !== null) {
                setWinningNumber(selectedNumber);
            }
        });
    }
    
    // Test automatic selection
    if (testAutoSelection) {
        testAutoSelection.addEventListener('click', function() {
            testAutoSelectionAlgorithm();
        });
    }
    
    // Start TV connection status checker
    startTvConnectionCheck();
    
    // Load current draw information
    loadDrawInfo();
    
    /**
     * Add the TV connection status indicator if it doesn't exist
     */
    function addTvConnectionStatusIfNeeded() {
        if (!tvStatusIndicator) {
            // Find the alert info element
            const drawInfo = document.getElementById('drawInfo');
            
            if (drawInfo) {
                // Create the status indicator
                const statusElement = document.createElement('div');
                statusElement.id = 'tvConnectionStatus';
                statusElement.className = 'tv-connection-status unknown';
                statusElement.innerHTML = `
                    <strong>TV Display Connection: </strong>
                    <span class="status-text">Checking...</span>
                `;
                
                // Add to the page
                drawInfo.parentNode.insertBefore(statusElement, drawInfo.nextSibling);
                
                // Add the needed CSS
                const style = document.createElement('style');
                style.textContent = `
                    .tv-connection-status {
                        padding: 10px;
                        border-radius: 5px;
                        margin-bottom: 20px;
                    }
                    .tv-connection-status.unknown {
                        background-color: #f8f9fa;
                        border: 1px solid #ddd;
                    }
                    .tv-connection-status.connected {
                        background-color: #d4edda;
                        border: 1px solid #c3e6cb;
                    }
                    .tv-connection-status.disconnected {
                        background-color: #f8d7da;
                        border: 1px solid #f5c6cb;
                    }
                `;
                document.head.appendChild(style);
                
                tvStatusIndicator = statusElement;
            }
        }
    }
    
    /**
     * Start checking the TV connection status
     */
    function startTvConnectionCheck() {
        // Check immediately
        checkTvConnection();
        
        // Set up interval for subsequent checks
        if (tvConnectionCheckTimer) {
            clearInterval(tvConnectionCheckTimer);
        }
        
        tvConnectionCheckTimer = setInterval(checkTvConnection, 5000);
    }
    
    /**
     * Check if the TV display is currently connected
     */
    function checkTvConnection() {
        // Skip if we're still waiting for the previous check
        if (lastTvConnectionCheck && Date.now() - lastTvConnectionCheck < 3000) {
            return;
        }
        
        lastTvConnectionCheck = Date.now();
        
        // Use the ping endpoint to check connection
        fetch('../php/ping.php?target=tv', { timeout: 3000 })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    updateTvConnectionStatus(true);
                } else {
                    updateTvConnectionStatus(false);
                }
            })
            .catch(error => {
                console.error('Error checking TV connection:', error);
                updateTvConnectionStatus(false);
            });
    }
    
    /**
     * Update the TV connection status indicator
     */
    function updateTvConnectionStatus(isConnected) {
        if (!tvStatusIndicator) return;
        
        if (isConnected) {
            tvStatusIndicator.className = 'tv-connection-status connected';
            tvStatusIndicator.querySelector('.status-text').textContent = 'Connected';
        } else {
            tvStatusIndicator.className = 'tv-connection-status disconnected';
            tvStatusIndicator.querySelector('.status-text').textContent = 'Disconnected';
        }
    }
    
    // Load current draw information
    function loadDrawInfo() {
        showLoading();
        
        fetch('../php/get_draw_details.php?current=1')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    currentDrawNumber = data.draw_number;
                    nextDrawNumber = currentDrawNumber + 1;
                    
                    currentDrawNumberSpan.textContent = currentDrawNumber;
                    nextDrawNumberSpan.textContent = nextDrawNumber;
                    
                    // Also check the current automatic mode setting
                    checkAutomaticModeSetting();
                } else {
                    showError('Failed to load draw information');
                }
                hideLoading();
            })
            .catch(error => {
                console.error('Error loading draw info:', error);
                showError('Failed to load draw information');
                hideLoading();
            });
    }
    
    // Check automatic mode setting
    function checkAutomaticModeSetting() {
        showLoading();
        
        fetch('../php/get_next_draw_info.php')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Update toggle based on server setting
                    isAutoMode = data.auto_mode_enabled;
                    autoModeToggle.checked = isAutoMode;
                    modeLabel.textContent = `Automatic Selection Mode (${isAutoMode ? 'ON' : 'OFF'})`;
                    
                    if (isAutoMode) {
                        automaticModeSection.style.display = 'block';
                        manualModeSection.style.display = 'none';
                    } else {
                        automaticModeSection.style.display = 'none';
                        manualModeSection.style.display = 'block';
                    }
                    
                    // If there's a manually set winning number, select it in the UI
                    if (data.has_manual_winning_number) {
                        selectNumberInUI(data.winning_number);
                    }
                }
                hideLoading();
            })
            .catch(error => {
                console.error('Error checking automatic mode:', error);
                hideLoading();
            });
    }
    
    // Select a number in the UI
    function selectNumberInUI(number) {
        document.querySelectorAll('.number-btn').forEach(btn => {
            btn.classList.remove('selected-number');
            if (parseInt(btn.dataset.number) === number) {
                btn.classList.add('selected-number');
                selectedNumber = number;
                selectedNumberSpan.textContent = number;
                saveNumberBtn.disabled = false;
            }
        });
    }
    
    // Toggle automatic mode
    function toggleAutomaticMode(enabled) {
        showLoading();
        
        const formData = new FormData();
        formData.append('enabled', enabled ? '1' : '0');
        
        fetch('../php/toggle_automatic_mode.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showSuccess(`Automatic mode ${enabled ? 'enabled' : 'disabled'}`);
                } else {
                    showError(data.message || 'Failed to update automatic mode');
                    // Revert UI if there was an error
                    autoModeToggle.checked = !enabled;
                    modeLabel.textContent = `Automatic Selection Mode (${!enabled ? 'ON' : 'OFF'})`;
                    if (!enabled) {
                        automaticModeSection.style.display = 'block';
                        manualModeSection.style.display = 'none';
                    } else {
                        automaticModeSection.style.display = 'none';
                        manualModeSection.style.display = 'block';
                    }
                }
                hideLoading();
            })
            .catch(error => {
                console.error('Error toggling automatic mode:', error);
                showError('Failed to update automatic mode');
                // Revert UI on error
                autoModeToggle.checked = !enabled;
                hideLoading();
            });
    }
    
    // Set winning number manually
    function setWinningNumber(number) {
        showLoading();
        
        const formData = new FormData();
        formData.append('winning_number', number.toString());
        
        fetch('../php/set_winning_number.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showSuccess(`Winning number set to ${number}`);
                } else {
                    showError(data.message || 'Failed to set winning number');
                }
                hideLoading();
            })
            .catch(error => {
                console.error('Error setting winning number:', error);
                showError('Failed to set winning number');
                hideLoading();
            });
    }
    
    // Test automatic selection algorithm
    function testAutoSelectionAlgorithm() {
        showLoading();
        testingArea.style.display = 'block';
        
        fetch('../php/auto_winning_number.php')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    let resultHTML = '';
                    
                    switch (data.selection_reason) {
                        case 'random':
                            resultHTML = `
                                <div class="alert alert-info">
                                    <h5>Random Selection</h5>
                                    <p>No bets have been placed for draw #${data.draw_number}, so a random number was selected.</p>
                                    <h4>Selected Number: <span class="badge ${getNumberColorClass(data.selected_number)}">${data.selected_number}</span></h4>
                                </div>
                            `;
                            break;
                        
                        case 'no_bets':
                            resultHTML = `
                                <div class="alert alert-success">
                                    <h5>Number With No Bets</h5>
                                    <p>Some numbers have no bets for draw #${data.draw_number}, so one of those numbers was randomly selected.</p>
                                    <h4>Selected Number: <span class="badge ${getNumberColorClass(data.selected_number)}">${data.selected_number}</span></h4>
                                </div>
                            `;
                            break;
                        
                        case 'lowest_payout':
                            resultHTML = `
                                <div class="alert alert-warning">
                                    <h5>Lowest Payout</h5>
                                    <p>All numbers have bets for draw #${data.draw_number}, so the number with the lowest potential payout was selected.</p>
                                    <h4>Selected Number: <span class="badge ${getNumberColorClass(data.selected_number)}">${data.selected_number}</span></h4>
                                    <p>Potential Payout: €${data.lowest_payout.toFixed(2)}</p>
                                </div>
                            `;
                            break;
                            
                        case 'manual':
                            resultHTML = `
                                <div class="alert alert-warning">
                                    <h5>Manual Selection</h5>
                                    <p>A winning number has been manually set for draw #${data.draw_number}.</p>
                                    <h4>Selected Number: <span class="badge ${getNumberColorClass(data.selected_number)}">${data.selected_number}</span></h4>
                                </div>
                            `;
                            break;
                    }
                    
                    testResults.innerHTML = resultHTML;
                } else {
                    testResults.innerHTML = `
                        <div class="alert alert-danger">
                            <p>Failed to test selection algorithm: ${data.message}</p>
                        </div>
                    `;
                }
                hideLoading();
            })
            .catch(error => {
                console.error('Error testing selection algorithm:', error);
                testResults.innerHTML = `
                    <div class="alert alert-danger">
                        <p>Failed to test selection algorithm. Check the console for details.</p>
                    </div>
                `;
                hideLoading();
            });
    }
    
    // Helper function to get badge class based on number
    function getNumberColorClass(number) {
        if (number === 0) return 'bg-success';
        const redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
        return redNumbers.includes(parseInt(number)) ? 'bg-danger' : 'bg-dark';
    }
    
    // Show loading overlay (modified to do nothing)
    function showLoading() {
        console.log('Loading operation started');
        // Loading overlay removed per user request
    }
    
    // Hide loading overlay (modified to do nothing)
    function hideLoading() {
        console.log('Loading operation completed');
        // Loading overlay removed per user request
    }
    
    // Show success message
    function showSuccess(message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-success alert-dismissible fade show';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        // Find a container element
        const container = document.querySelector('.container') || document.querySelector('.card-body');
        if (container) {
            container.insertBefore(alertDiv, container.firstChild);
            
            setTimeout(() => {
                alertDiv.classList.remove('show');
                setTimeout(() => alertDiv.remove(), 300);
            }, 3000);
        }
    }
    
    // Show error message
    function showError(message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-danger alert-dismissible fade show';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        // Find a container element
        const container = document.querySelector('.container') || document.querySelector('.card-body');
        if (container) {
            container.insertBefore(alertDiv, container.firstChild);
            
            setTimeout(() => {
                alertDiv.classList.remove('show');
                setTimeout(() => alertDiv.remove(), 300);
            }, 5000);
        }
    }
}); 