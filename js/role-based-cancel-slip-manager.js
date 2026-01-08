/**
 * Role-Based Cancel Slip Manager
 * Manages visibility of cancel slip buttons based on user role
 * - Regular users: Only see edge toggle
 * - Admin users: Can see both edge toggle and floating button
 */

(function() {
    'use strict';

    console.log('🔐 Role-Based Cancel Slip Manager - Initializing...');

    let currentUserRole = null;
    let isInitialized = false;
    let floatingButtonsHidden = false;

    /**
     * Initialize the role-based cancel slip manager
     */
    function init() {
        console.log('🔐 Initializing role-based cancel slip manager...');
        
        // Wait for UserRoleManager to be available
        if (typeof window.UserRoleManager === 'undefined') {
            setTimeout(init, 100);
            return;
        }
        
        // Register for role updates
        window.UserRoleManager.onRoleCheck(handleRoleUpdate);
        
        isInitialized = true;
        console.log('🔐 Role-based cancel slip manager initialized');
    }

    /**
     * Handle user role update
     */
    function handleRoleUpdate(roleInfo) {
        console.log('🔐 Handling role update:', roleInfo);
        
        currentUserRole = roleInfo.role;
        
        if (roleInfo.isAdmin) {
            showFloatingButtonsForAdmin();
        } else {
            hideFloatingButtonsForRegularUsers();
        }
    }

    /**
     * Show floating cancel slip buttons for admin users
     */
    function showFloatingButtonsForAdmin() {
        console.log('🔐 Showing floating buttons for admin user');
        
        // Find and show all cancel slip buttons
        const selectors = [
            '.elegant-cancel-button',
            '#elegant-cancel-button',
            '.cancel-slip-button',
            '#cancel-slip-button'
        ];
        
        let shownCount = 0;
        
        selectors.forEach(selector => {
            try {
                const elements = document.querySelectorAll(selector);
                elements.forEach(element => {
                    showElement(element);
                    shownCount++;
                });
            } catch (e) {
                // Ignore selector errors
            }
        });
        
        // Show right action sidebar if it was hidden
        const sidebar = document.querySelector('.right-action-sidebar');
        if (sidebar) {
            showElement(sidebar);
            console.log('🔐 Showed right action sidebar for admin');
        }
        
        // Show cancel slip edge toggle for admin users
        const edgeToggleSelectors = [
            '.cancel-slip-edge-toggle-control',
            '#cancel-slip-edge-toggle'
        ];

        edgeToggleSelectors.forEach(selector => {
            try {
                const elements = document.querySelectorAll(selector);
                elements.forEach(element => {
                    showElement(element);
                    shownCount++;
                    console.log('🔐 Showed cancel slip edge toggle for admin user');
                });
            } catch (e) {
                // Ignore selector errors
            }
        });

        // Remove any hiding CSS classes
        removeHidingClasses();

        floatingButtonsHidden = false;
        console.log(`🔐 Showed ${shownCount} floating buttons and edge toggle for admin`);
    }

    /**
     * Hide floating cancel slip buttons for regular users
     */
    function hideFloatingButtonsForRegularUsers() {
        console.log('🔐 Hiding floating buttons for regular user');
        
        // Find and hide all cancel slip buttons
        const selectors = [
            '.elegant-cancel-button',
            '#elegant-cancel-button',
            '.cancel-slip-button',
            '#cancel-slip-button',
            '.right-action-sidebar .elegant-cancel-button',
            '.right-action-sidebar .cancel-slip-button'
        ];
        
        let hiddenCount = 0;
        
        selectors.forEach(selector => {
            try {
                const elements = document.querySelectorAll(selector);
                elements.forEach(element => {
                    hideElement(element);
                    hiddenCount++;
                });
            } catch (e) {
                // Ignore selector errors
            }
        });
        
        // Hide cancel slip edge toggle for regular users
        const edgeToggleSelectors = [
            '.cancel-slip-edge-toggle-control',
            '#cancel-slip-edge-toggle'
        ];

        edgeToggleSelectors.forEach(selector => {
            try {
                const elements = document.querySelectorAll(selector);
                elements.forEach(element => {
                    hideElement(element);
                    hiddenCount++;
                    console.log('🔐 Hidden cancel slip edge toggle for regular user');
                });
            } catch (e) {
                // Ignore selector errors
            }
        });

        // Hide specific button content structures
        hideButtonsWithSpecificContent();

        // Hide right action sidebar if it contains cancel buttons
        hideRightActionSidebarIfNeeded();

        // Add hiding CSS classes
        addHidingClasses();
        
        floatingButtonsHidden = true;
        console.log(`🔐 Hidden ${hiddenCount} floating buttons for regular user`);
    }

    /**
     * Hide buttons with specific content structure
     */
    function hideButtonsWithSpecificContent() {
        const buttonContents = document.querySelectorAll('.button-content');
        
        buttonContents.forEach(content => {
            const hasTitle = content.getAttribute('title');
            const hasSpan = content.querySelector('span');
            const hasBanIcon = content.querySelector('[data-icon="ban"]') || 
                             content.querySelector('.fa-ban') ||
                             content.querySelector('svg[data-icon="ban"]') ||
                             content.querySelector('.svg-inline--fa.fa-ban');
            
            // Check if this is a cancel slip button
            if ((hasTitle && hasTitle.toLowerCase().includes('cancel')) ||
                (hasSpan && hasSpan.textContent.includes('CANCEL SLIP')) ||
                (hasBanIcon && hasSpan && hasSpan.textContent.includes('CANCEL'))) {
                
                const parentDiv = content.closest('div');
                if (parentDiv) {
                    hideElement(parentDiv);
                    console.log('🔐 Hidden button with cancel content');
                }
            }
        });
    }

    /**
     * Hide right action sidebar if it contains cancel buttons
     */
    function hideRightActionSidebarIfNeeded() {
        const sidebar = document.querySelector('.right-action-sidebar');
        if (sidebar) {
            const hasCancelButton = sidebar.querySelector('.elegant-cancel-button') ||
                                  sidebar.querySelector('.cancel-slip-button') ||
                                  sidebar.querySelector('[title*="Cancel"]');
            
            if (hasCancelButton) {
                hideElement(sidebar);
                console.log('🔐 Hidden right action sidebar containing cancel buttons');
            }
        }
    }

    /**
     * Show edge toggle for admin users only
     */
    function showEdgeToggleForAdmin() {
        const edgeToggle = document.querySelector('.cancel-slip-edge-toggle-control');
        if (edgeToggle && currentUserRole === 'admin') {
            showElement(edgeToggle);
            console.log('🔐 Showed edge toggle for admin user');
        }
    }

    /**
     * Show an element
     */
    function showElement(element) {
        if (!element) return;
        
        element.style.display = '';
        element.style.visibility = 'visible';
        element.style.opacity = '1';
        element.style.pointerEvents = 'auto';
        element.style.position = '';
        element.style.left = '';
        element.style.top = '';
        element.style.width = '';
        element.style.height = '';
        element.style.overflow = '';
        element.style.zIndex = '';
        element.style.transform = '';
        element.style.clip = '';
        
        // Remove hiding classes
        element.classList.remove('cancel-slip-hidden', 'role-hidden');
    }

    /**
     * Hide an element
     */
    function hideElement(element) {
        if (!element) return;
        
        element.style.display = 'none';
        element.style.visibility = 'hidden';
        element.style.opacity = '0';
        element.style.pointerEvents = 'none';
        element.style.position = 'absolute';
        element.style.left = '-9999px';
        element.style.top = '-9999px';
        element.style.width = '0';
        element.style.height = '0';
        element.style.overflow = 'hidden';
        element.style.zIndex = '-9999';
        element.style.transform = 'scale(0)';
        
        // Add hiding classes
        element.classList.add('cancel-slip-hidden', 'role-hidden');
    }

    /**
     * Add CSS classes for hiding buttons from regular users
     */
    function addHidingClasses() {
        document.body.classList.add('hide-cancel-buttons-for-regular-users');
    }

    /**
     * Remove CSS classes that hide buttons
     */
    function removeHidingClasses() {
        document.body.classList.remove('hide-cancel-buttons-for-regular-users');
    }

    /**
     * Monitor for new buttons and apply role-based visibility
     */
    function startButtonMonitoring() {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        // Check if the node is a cancel button
                        if (isCancelSlipButton(node)) {
                            applyRoleBasedVisibility(node);
                        }
                        
                        // Check for cancel buttons and edge toggles in added subtree
                        if (node.querySelectorAll) {
                            const cancelElements = node.querySelectorAll('.elegant-cancel-button, .cancel-slip-button, #elegant-cancel-button, #cancel-slip-button, .cancel-slip-edge-toggle-control, #cancel-slip-edge-toggle');
                            cancelElements.forEach(element => {
                                applyRoleBasedVisibility(element);
                            });
                        }
                    }
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        console.log('🔐 Button monitoring started');
    }

    /**
     * Check if a node is a cancel slip button or edge toggle
     */
    function isCancelSlipButton(node) {
        if (!node.classList) return false;

        return node.classList.contains('elegant-cancel-button') ||
               node.classList.contains('cancel-slip-button') ||
               node.classList.contains('cancel-slip-edge-toggle-control') ||
               node.id === 'cancel-slip-button' ||
               node.id === 'elegant-cancel-button' ||
               node.id === 'cancel-slip-edge-toggle' ||
               (node.getAttribute('title') && node.getAttribute('title').toLowerCase().includes('cancel'));
    }

    /**
     * Apply role-based visibility to a specific element
     */
    function applyRoleBasedVisibility(element) {
        if (currentUserRole === 'admin') {
            showElement(element);
            console.log('🔐 Showed cancel button for admin user');
        } else {
            hideElement(element);
            console.log('🔐 Hidden cancel button for regular user');
        }
    }

    /**
     * Get current visibility state
     */
    function getVisibilityState() {
        return {
            userRole: currentUserRole,
            floatingButtonsHidden: floatingButtonsHidden,
            isAdmin: currentUserRole === 'admin'
        };
    }

    /**
     * Force refresh of role-based visibility
     */
    function refreshVisibility() {
        console.log('🔐 Refreshing role-based visibility...');
        
        if (window.UserRoleManager) {
            window.UserRoleManager.refreshRole();
        }
    }

    /**
     * Initialize when DOM is ready
     */
    function initialize() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                setTimeout(init, 200);
                setTimeout(startButtonMonitoring, 500);
            });
        } else {
            setTimeout(init, 200);
            setTimeout(startButtonMonitoring, 500);
        }
    }

    // Initialize
    initialize();

    // Export public API
    window.RoleBasedCancelSlipManager = {
        getVisibilityState,
        refreshVisibility,
        showFloatingButtonsForAdmin,
        hideFloatingButtonsForRegularUsers
    };

    console.log('🔐 Role-Based Cancel Slip Manager - Loaded');

})();
