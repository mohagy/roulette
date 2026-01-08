/**
 * Right-Side Sidebar Action Buttons
 * Creates a sidebar container and organizes floating action buttons
 * Preserves all existing functionality while providing clean organization
 */

class RightSidebarManager {
    constructor() {
        this.sidebar = null;
        this.buttons = {
            complete: null,
            reprint: null,
            cancelSlip: null,
            cashout: null
        };
        this.init();
    }

    init() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setupSidebar());
        } else {
            this.setupSidebar();
        }
    }

    setupSidebar() {
        // Small delay to ensure other scripts have loaded and created their buttons
        setTimeout(() => {
            this.createSidebar();
            this.findAndMoveButtons();
            this.startMonitoring();
        }, 1000);
    }

    createSidebar() {
        // Remove any existing sidebar
        const existingSidebar = document.querySelector('.right-action-sidebar');
        if (existingSidebar) {
            existingSidebar.remove();
        }

        // Create the sidebar container
        this.sidebar = document.createElement('div');
        this.sidebar.className = 'right-action-sidebar';
        this.sidebar.setAttribute('data-sidebar-manager', 'true');

        // Add the sidebar to the body
        document.body.appendChild(this.sidebar);
        
        console.log('✅ Right-side action sidebar created');
    }

    findAndMoveButtons() {
        // Find and move each button to the sidebar
        this.moveCompleteButton();
        this.moveReprintButton();
        this.moveCancelSlipButton();
        this.moveCashoutButton();
    }

    moveCompleteButton() {
        // Look for floating complete button
        const selectors = [
            '#independent-floating-complete-btn',
            '.floating-complete-button',
            '.independent-floating-complete-btn'
        ];

        for (const selector of selectors) {
            const button = document.querySelector(selector);
            if (button && !button.closest('.right-action-sidebar')) {
                this.sidebar.appendChild(button);
                this.buttons.complete = button;
                console.log('✅ Complete button moved to sidebar');
                break;
            }
        }
    }

    moveReprintButton() {
        // Look for reprint button
        const button = document.querySelector('.reprint-slip-button');
        if (button && !button.closest('.right-action-sidebar')) {
            this.sidebar.appendChild(button);
            this.buttons.reprint = button;
            console.log('✅ Reprint button moved to sidebar');
        }
    }

    moveCancelSlipButton() {
        // Look for cancel slip button (NOT the one in bet display footer)
        const buttons = document.querySelectorAll('.cancel-slip-button');
        
        // Find the floating cancel slip button (not the one in bet display footer)
        for (const button of buttons) {
            const isInFooter = button.closest('.bet-display-footer');
            const isInModal = button.closest('.cancel-slip-modal');
            
            if (!isInFooter && !isInModal && !button.closest('.right-action-sidebar')) {
                this.sidebar.appendChild(button);
                this.buttons.cancelSlip = button;
                console.log('✅ Cancel slip button moved to sidebar');
                break;
            }
        }
    }

    moveCashoutButton() {
        // Look for cashout button
        const button = document.querySelector('.cashout-button');
        if (button && !button.closest('.right-action-sidebar')) {
            this.sidebar.appendChild(button);
            this.buttons.cashout = button;
            console.log('✅ Cashout button moved to sidebar');
        }
    }

    startMonitoring() {
        // Monitor for new buttons that might be created dynamically
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        // Check if new buttons were added
                        this.checkForNewButtons(node);
                    }
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        // Periodic check to ensure buttons stay in sidebar
        setInterval(() => {
            this.ensureButtonsInSidebar();
        }, 5000);
    }

    checkForNewButtons(element) {
        // Check if the added element or its children contain our target buttons
        const selectors = [
            '#independent-floating-complete-btn',
            '.floating-complete-button',
            '.reprint-slip-button',
            '.cancel-slip-button',
            '.cashout-button'
        ];

        selectors.forEach(selector => {
            const button = element.matches && element.matches(selector) ? element : element.querySelector && element.querySelector(selector);
            if (button && !button.closest('.right-action-sidebar')) {
                // Determine button type and move to sidebar
                if (selector.includes('complete')) {
                    this.moveCompleteButton();
                } else if (selector.includes('reprint')) {
                    this.moveReprintButton();
                } else if (selector.includes('cancel-slip')) {
                    this.moveCancelSlipButton();
                } else if (selector.includes('cashout')) {
                    this.moveCashoutButton();
                }
            }
        });
    }

    ensureButtonsInSidebar() {
        // Ensure all buttons are still in the sidebar
        if (!this.sidebar || !document.body.contains(this.sidebar)) {
            this.createSidebar();
        }

        // Re-move any buttons that might have been moved elsewhere
        this.findAndMoveButtons();
    }

    // Method to get sidebar element for external access
    getSidebar() {
        return this.sidebar;
    }

    // Method to get specific button
    getButton(type) {
        return this.buttons[type];
    }
}

// Initialize the sidebar manager
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        window.rightSidebarManager = new RightSidebarManager();
        console.log('🚀 Right-side sidebar manager initialized');
    }, 500);
});

// Export for global access
window.RightSidebarManager = RightSidebarManager;
