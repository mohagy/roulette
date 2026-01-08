/**
 * Cancel Slip Edge Toggle Control (Role-Based Version)
 * Provides edge toggle for cancel slip functionality
 * Works with role-based visibility system
 */

(function() {
    'use strict';

    console.log('🚫 Cancel Slip Edge Toggle (Role-Based) - Initializing...');

    let cancelSlipToggle = null;
    let isProcessing = false;

    /**
     * Initialize the cancel slip edge toggle
     */
    function init() {
        console.log('🚫 Initializing cancel slip edge toggle...');

        // Check if UserRoleManager is available
        if (typeof window.UserRoleManager !== 'undefined') {
            console.log('🚫 UserRoleManager found - registering role callback');
            // Register callback with UserRoleManager for proper role detection
            window.UserRoleManager.onRoleCheck(handleRoleUpdate);
        } else {
            console.log('🚫 UserRoleManager not found - using fallback role detection');
            // Fallback to manual role checking
            checkUserRoleAndCreateToggle();
        }

        console.log('🚫 Cancel slip edge toggle initialized');
    }

    /**
     * Handle role update from UserRoleManager
     */
    function handleRoleUpdate(roleInfo) {
        console.log('🚫 Received role update:', roleInfo);

        if (roleInfo.isAdmin) {
            console.log('🚫 Admin user confirmed - creating cancel slip edge toggle');
            createCancelSlipEdgeToggle();
        } else if (roleInfo.isAuthenticated && !roleInfo.isAdmin) {
            console.log('🚫 Regular user confirmed - cancel slip edge toggle will not be created');
            // Remove any existing toggle
            const existing = document.getElementById('cancel-slip-edge-toggle');
            if (existing) {
                existing.remove();
                console.log('🚫 Removed existing toggle for regular user');
            }
        } else {
            console.log('🚫 Unauthenticated user confirmed - cancel slip edge toggle will not be created');
            // Remove any existing toggle
            const existing = document.getElementById('cancel-slip-edge-toggle');
            if (existing) {
                existing.remove();
                console.log('🚫 Removed existing toggle for unauthenticated user');
            }
        }
    }

    /**
     * Check user role and only create toggle for admin users (fallback method)
     */
    function checkUserRoleAndCreateToggle() {
        console.log('🚫 Checking user role for cancel slip edge toggle (fallback)...');

        // Check if body has role classes
        const isAdmin = document.body.classList.contains('user-admin');
        const isRegular = document.body.classList.contains('user-regular');
        const isUnauthenticated = document.body.classList.contains('user-unauthenticated');

        if (isAdmin) {
            console.log('🚫 Admin user detected - creating cancel slip edge toggle');
            createCancelSlipEdgeToggle();
        } else if (isRegular) {
            console.log('🚫 Regular user detected - cancel slip edge toggle will not be created');
            return;
        } else if (isUnauthenticated) {
            console.log('🚫 Unauthenticated user detected - cancel slip edge toggle will not be created');
            return;
        } else {
            // Role not determined yet, wait and try again
            console.log('🚫 User role not determined yet, waiting...');
            setTimeout(checkUserRoleAndCreateToggle, 500);
        }
    }

    /**
     * Create the cancel slip edge toggle control
     */
    function createCancelSlipEdgeToggle() {
        console.log('🚫 Creating cancel slip edge toggle control...');
        
        // Remove existing toggle if any
        const existing = document.getElementById('cancel-slip-edge-toggle');
        if (existing) {
            existing.remove();
        }
        
        // Create toggle element
        cancelSlipToggle = document.createElement('div');
        cancelSlipToggle.id = 'cancel-slip-edge-toggle';
        cancelSlipToggle.className = 'cancel-slip-edge-toggle-control';
        cancelSlipToggle.setAttribute('role', 'button');
        cancelSlipToggle.setAttribute('tabindex', '0');
        cancelSlipToggle.setAttribute('aria-label', 'Cancel betting slip');
        cancelSlipToggle.innerHTML = `
            <div class="cancel-slip-edge-toggle-tab">
                <div class="cancel-slip-edge-toggle-icon">
                    <i class="fas fa-ban"></i>
                </div>
                <div class="cancel-slip-edge-toggle-text">
                    <span class="cancel-slip-edge-toggle-label">CANCEL</span>
                    <span class="cancel-slip-edge-toggle-status">SLIP</span>
                </div>
                <div class="cancel-slip-edge-toggle-arrow">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </div>
        `;

        // Add event listeners
        cancelSlipToggle.addEventListener('click', handleCancelSlipClick);
        cancelSlipToggle.addEventListener('keydown', handleKeydown);

        // Add to document
        document.body.appendChild(cancelSlipToggle);
        
        console.log('✅ Cancel slip edge toggle control created');
    }

    /**
     * Handle keydown events for accessibility
     */
    function handleKeydown(event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            handleCancelSlipClick();
        }
    }

    /**
     * Handle cancel slip click
     */
    function handleCancelSlipClick() {
        if (isProcessing) {
            console.log('🚫 Cancel slip already processing, ignoring click');
            return;
        }

        console.log('🚫 Cancel slip edge toggle clicked');

        // Add active state
        cancelSlipToggle.classList.add('active');
        isProcessing = true;

        // Play sound if available
        playClickSound();

        // Show the cancel slip modal
        showCancelSlipModal();

        // Remove active state after animation
        setTimeout(() => {
            cancelSlipToggle.classList.remove('active');
            isProcessing = false;
        }, 600);
    }

    /**
     * Show the cancel slip modal using the elegant system
     */
    function showCancelSlipModal() {
        // First, try to use the elegant cancel modal function
        if (typeof showElegantCancelModal === 'function') {
            showElegantCancelModal();
            console.log('🚫 Elegant cancel modal shown via function');
            return;
        }

        // Try to find and show the elegant cancel modal directly
        const elegantModal = document.getElementById('elegant-cancel-modal');
        if (elegantModal) {
            elegantModal.style.display = 'block';
            
            // Clear any previous error messages
            const errorElement = document.getElementById('elegant-cancel-error');
            if (errorElement) {
                errorElement.textContent = '';
            }
            
            // Focus on the input field after a short delay
            setTimeout(() => {
                const slipIdInput = document.getElementById('elegant-slip-id-input');
                if (slipIdInput) {
                    slipIdInput.focus();
                    slipIdInput.value = ''; // Clear any previous value
                }
            }, 100);
            
            console.log('🚫 Elegant cancel modal shown directly');
            return;
        }

        // If elegant modal doesn't exist, create it using the elegant system
        createElegantCancelModalIfNeeded();
    }

    /**
     * Create the elegant cancel modal if it doesn't exist
     */
    function createElegantCancelModalIfNeeded() {
        console.log('🚫 Creating elegant cancel modal...');
        
        // Create the modal
        const elegantCancelModal = document.createElement('div');
        elegantCancelModal.id = 'elegant-cancel-modal';
        elegantCancelModal.className = 'elegant-cancel-modal';
        elegantCancelModal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Cancel Betting Slip</h2>
                    <span class="modal-close">&times;</span>
                </div>
                <div class="modal-body">
                    <p>Enter the betting slip number you want to cancel:</p>
                    <div class="input-group">
                        <input type="text" id="elegant-slip-id-input" placeholder="Betting Slip Number" />
                    </div>
                    <div class="error-message" id="elegant-cancel-error"></div>
                </div>
                <div class="modal-footer">
                    <button id="elegant-cancel-button-cancel" class="btn-cancel">Close</button>
                    <button id="elegant-cancel-button-confirm" class="btn-primary">Cancel Slip</button>
                </div>
            </div>
        `;

        // Add the modal to the DOM
        document.body.appendChild(elegantCancelModal);

        // Add event listeners
        setupElegantModalEventListeners(elegantCancelModal);

        // Show the modal
        elegantCancelModal.style.display = 'block';

        // Focus on the input field
        setTimeout(() => {
            const slipIdInput = document.getElementById('elegant-slip-id-input');
            if (slipIdInput) {
                slipIdInput.focus();
            }
        }, 100);

        console.log('🚫 Elegant cancel modal created and shown');
    }

    /**
     * Setup event listeners for the elegant modal
     */
    function setupElegantModalEventListeners(modal) {
        // Close button
        const closeButton = modal.querySelector('.modal-close');
        closeButton.addEventListener('click', () => {
            modal.style.display = 'none';
        });

        // Cancel button
        const cancelButton = document.getElementById('elegant-cancel-button-cancel');
        cancelButton.addEventListener('click', () => {
            modal.style.display = 'none';
        });

        // Confirm button
        const confirmButton = document.getElementById('elegant-cancel-button-confirm');
        confirmButton.addEventListener('click', () => {
            const slipId = document.getElementById('elegant-slip-id-input').value.trim();
            if (slipId) {
                processElegantCancelSlip(slipId);
            } else {
                document.getElementById('elegant-cancel-error').textContent = 'Please enter a valid betting slip number';
            }
        });

        // Close on background click
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });

        // Handle Enter key in input field
        const slipIdInput = document.getElementById('elegant-slip-id-input');
        slipIdInput.addEventListener('keypress', (event) => {
            if (event.key === 'Enter') {
                const slipId = slipIdInput.value.trim();
                if (slipId) {
                    processElegantCancelSlip(slipId);
                } else {
                    document.getElementById('elegant-cancel-error').textContent = 'Please enter a valid betting slip number';
                }
            }
        });
    }

    /**
     * Process cancel slip using the elegant system
     */
    function processElegantCancelSlip(slipId) {
        console.log('🚫 Processing elegant cancel slip:', slipId);

        // Use the elegant cancel function if available
        if (typeof elegantCancelBettingSlip === 'function') {
            elegantCancelBettingSlip(slipId);
            return;
        }

        // Fallback to our own implementation
        const currentDrawNumber = getCurrentDrawNumber();

        fetch('php/cancel_betting_slip.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `slip_id=${encodeURIComponent(slipId)}&draw_number=${encodeURIComponent(currentDrawNumber)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Clear error message
                document.getElementById('elegant-cancel-error').textContent = '';

                // Show success notification if function exists
                if (typeof showElegantNotification === 'function') {
                    showElegantNotification(data.message || 'Betting slip cancelled successfully', 'success');
                } else {
                    alert(data.message || 'Betting slip cancelled successfully');
                }

                // Close the modal
                document.getElementById('elegant-cancel-modal').style.display = 'none';

                // Update cash balance if available
                if (data.cashBalance && typeof CashManager !== 'undefined') {
                    CashManager.updateBalance(data.cashBalance);
                }
            } else {
                // Show error message
                document.getElementById('elegant-cancel-error').textContent = data.message || 'Failed to cancel betting slip';
            }
        })
        .catch(error => {
            console.error('🚫 Error cancelling betting slip:', error);
            document.getElementById('elegant-cancel-error').textContent = 'Error cancelling betting slip. Please try again.';
        });
    }

    /**
     * Get current draw number
     */
    function getCurrentDrawNumber() {
        // Try to get from various sources
        const nextDrawElement = document.getElementById('next-draw-number');
        if (nextDrawElement && nextDrawElement.textContent) {
            const match = nextDrawElement.textContent.match(/\d+/);
            if (match) {
                return parseInt(match[0]);
            }
        }

        // Try to get from upcoming draws panel
        if (window.UpcomingDrawsPanel && typeof window.UpcomingDrawsPanel.getSelectedDraw === 'function') {
            const selectedDraw = window.UpcomingDrawsPanel.getSelectedDraw();
            if (selectedDraw) {
                return selectedDraw;
            }
        }

        // Fallback to a default value
        return 1;
    }

    /**
     * Play click sound if available
     */
    function playClickSound() {
        try {
            if (typeof playAudio !== 'undefined' && playAudio && typeof selectSound !== 'undefined') {
                selectSound.play();
            }
        } catch (e) {
            // Ignore sound errors
        }
    }

    /**
     * Initialize when DOM is ready
     */
    function initialize() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                setTimeout(init, 100);
            });
        } else {
            setTimeout(init, 100);
        }
    }

    // Initialize
    initialize();

    // Export public API
    window.CancelSlipEdgeToggle = {
        show: showCancelSlipModal
    };

    console.log('🚫 Cancel Slip Edge Toggle (Role-Based) - Loaded');

})();
