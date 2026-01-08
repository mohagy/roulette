/**
 * Right Action Sidebar Toggle Control
 * Provides toggle functionality for the right-action-sidebar
 * Follows the same pattern as existing toggle controls
 */

(function() {
    'use strict';

    console.log('🎛️ Right Action Sidebar Toggle - Initializing...');

    let rightActionToggle = null;
    let isVisible = false;
    const sessionStorageKey = 'rightActionSidebarVisible';
    const styleId = 'right-action-sidebar-toggle-styles';

    /**
     * Initialize the toggle control
     */
    function init() {
        console.log('🎛️ Initializing right action sidebar toggle...');
        
        // Load saved state
        loadSavedState();
        
        // Create toggle control
        createRightActionSidebarToggle();
        
        // Apply initial state
        setTimeout(() => {
            applyInitialState();
        }, 500);
        
        console.log('🎛️ Right action sidebar toggle initialized');
    }

    /**
     * Create the right action sidebar toggle control
     */
    function createRightActionSidebarToggle() {
        console.log('🎛️ Creating right action sidebar toggle control...');

        // Remove existing toggle if any
        const existing = document.getElementById('right-action-sidebar-toggle');
        if (existing) {
            console.log('🎛️ Removing existing right action sidebar toggle');
            existing.remove();
        }

        // Check for other similar toggles to avoid conflicts
        checkForToggleConflicts();

        // Create toggle element
        rightActionToggle = document.createElement('div');
        rightActionToggle.id = 'right-action-sidebar-toggle';
        rightActionToggle.className = 'right-action-sidebar-toggle-control';
        rightActionToggle.setAttribute('role', 'button');
        rightActionToggle.setAttribute('tabindex', '0');
        rightActionToggle.setAttribute('aria-label', 'Toggle right action sidebar');
        rightActionToggle.innerHTML = `
            <div class="right-action-sidebar-toggle-tab">
                <div class="right-action-sidebar-toggle-icon">
                    <i class="fas fa-tools"></i>
                </div>
                <div class="right-action-sidebar-toggle-text">
                    <span class="right-action-sidebar-toggle-label">ACTIONS</span>
                    <span class="right-action-sidebar-toggle-status">HIDDEN</span>
                </div>
                <div class="right-action-sidebar-toggle-arrow">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </div>
        `;

        // Add event listeners
        rightActionToggle.addEventListener('click', toggleSidebarFromToggle);
        rightActionToggle.addEventListener('keydown', handleKeydown);

        // Add to document
        document.body.appendChild(rightActionToggle);

        console.log('✅ Right action sidebar toggle control created');
    }

    /**
     * Check for toggle conflicts and log them
     */
    function checkForToggleConflicts() {
        const toggles = document.querySelectorAll('[class*="toggle-control"]');
        console.log('🎛️ Found toggle controls:', toggles.length);

        toggles.forEach((toggle, index) => {
            const rect = toggle.getBoundingClientRect();
            console.log(`🎛️ Toggle ${index + 1}:`, {
                id: toggle.id,
                className: toggle.className,
                position: {
                    top: rect.top,
                    right: rect.right,
                    zIndex: getComputedStyle(toggle).zIndex
                }
            });
        });
    }

    /**
     * Handle keydown events for accessibility
     */
    function handleKeydown(event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            toggleSidebarFromToggle();
        }
    }

    /**
     * Toggle sidebar visibility from toggle control
     */
    function toggleSidebarFromToggle() {
        console.log('🎛️ Right action sidebar toggle clicked - current state:', isVisible);
        
        const sidebar = document.querySelector('.right-action-sidebar');
        if (!sidebar) {
            console.warn('🎛️ Right action sidebar not found');
            return;
        }

        isVisible = !isVisible;
        
        if (isVisible) {
            showSidebarFromToggle(sidebar);
        } else {
            hideSidebarFromToggle(sidebar);
        }

        // Save state
        sessionStorage.setItem(sessionStorageKey, isVisible.toString());
    }

    /**
     * Show the right action sidebar from toggle control
     */
    function showSidebarFromToggle(sidebar) {
        console.log('🎛️ Showing right action sidebar from toggle...');

        // Remove hiding classes and add showing class
        sidebar.classList.remove('sidebar-hiding', 'sidebar-hidden');
        sidebar.classList.add('sidebar-showing', 'sidebar-visible');

        // Force visibility with direct styles (maximum override)
        sidebar.style.setProperty('display', 'flex', 'important');
        sidebar.style.setProperty('visibility', 'visible', 'important');
        sidebar.style.setProperty('opacity', '1', 'important');
        sidebar.style.setProperty('pointer-events', 'auto', 'important');
        sidebar.style.setProperty('position', 'fixed', 'important');
        sidebar.style.setProperty('top', '50%', 'important');
        sidebar.style.setProperty('right', '20px', 'important');
        sidebar.style.setProperty('transform', 'translateY(-50%)', 'important');
        sidebar.style.setProperty('z-index', '1500', 'important');
        sidebar.style.setProperty('left', 'auto', 'important');
        sidebar.style.setProperty('width', 'auto', 'important');
        sidebar.style.setProperty('height', 'auto', 'important');
        sidebar.style.setProperty('overflow', 'visible', 'important');

        // Update toggle control
        rightActionToggle.classList.remove('sidebar-hidden');
        rightActionToggle.classList.add('sidebar-visible');
        rightActionToggle.setAttribute('aria-pressed', 'true');
        rightActionToggle.querySelector('.right-action-sidebar-toggle-status').textContent = 'VISIBLE';
        rightActionToggle.querySelector('.right-action-sidebar-toggle-icon i').className = 'fas fa-tools';
        rightActionToggle.querySelector('.right-action-sidebar-toggle-arrow i').className = 'fas fa-chevron-left';

        // Debug current state
        console.log('🎛️ Sidebar classes after show:', sidebar.className);
        console.log('🎛️ Sidebar computed display:', getComputedStyle(sidebar).display);
        console.log('🎛️ Sidebar computed visibility:', getComputedStyle(sidebar).visibility);
        console.log('🎛️ Sidebar computed opacity:', getComputedStyle(sidebar).opacity);

        console.log('🎛️ Right action sidebar shown from toggle');
    }

    /**
     * Hide the right action sidebar from toggle control
     */
    function hideSidebarFromToggle(sidebar) {
        console.log('🎛️ Hiding right action sidebar from toggle...');

        // Add hiding class and remove showing class
        sidebar.classList.remove('sidebar-showing', 'sidebar-visible');
        sidebar.classList.add('sidebar-hiding');

        // Force hiding with direct styles (maximum override to counter CSS !important)
        sidebar.style.setProperty('display', 'none', 'important');
        sidebar.style.setProperty('visibility', 'hidden', 'important');
        sidebar.style.setProperty('opacity', '0', 'important');
        sidebar.style.setProperty('pointer-events', 'none', 'important');
        sidebar.style.setProperty('position', 'absolute', 'important');
        sidebar.style.setProperty('left', '-9999px', 'important');
        sidebar.style.setProperty('top', '-9999px', 'important');
        sidebar.style.setProperty('width', '0', 'important');
        sidebar.style.setProperty('height', '0', 'important');
        sidebar.style.setProperty('overflow', 'hidden', 'important');
        sidebar.style.setProperty('z-index', '-9999', 'important');
        sidebar.style.setProperty('transform', 'scale(0)', 'important');

        // Update toggle control
        rightActionToggle.classList.remove('sidebar-visible');
        rightActionToggle.classList.add('sidebar-hidden');
        rightActionToggle.setAttribute('aria-pressed', 'false');
        rightActionToggle.querySelector('.right-action-sidebar-toggle-status').textContent = 'HIDDEN';
        rightActionToggle.querySelector('.right-action-sidebar-toggle-icon i').className = 'fas fa-eye-slash';
        rightActionToggle.querySelector('.right-action-sidebar-toggle-arrow i').className = 'fas fa-chevron-right';

        // Remove hiding class after animation (but keep the inline styles)
        setTimeout(() => {
            sidebar.classList.remove('sidebar-hiding');
            // Keep the hiding styles applied via inline styles
        }, 400);

        // Debug current state
        console.log('🎛️ Sidebar classes after hide:', sidebar.className);
        console.log('🎛️ Sidebar computed display:', getComputedStyle(sidebar).display);
        console.log('🎛️ Sidebar computed visibility:', getComputedStyle(sidebar).visibility);
        console.log('🎛️ Sidebar computed opacity:', getComputedStyle(sidebar).opacity);

        console.log('🙈 Right action sidebar hidden via toggle');
    }

    /**
     * Load saved state from sessionStorage
     */
    function loadSavedState() {
        try {
            const savedState = sessionStorage.getItem(sessionStorageKey);
            if (savedState !== null) {
                isVisible = savedState === 'true';
                console.log('🎛️ Loaded saved state:', isVisible ? 'visible' : 'hidden');
            } else {
                // Default to hidden
                isVisible = false;
                console.log('🎛️ No saved state found, defaulting to hidden');
            }
        } catch (e) {
            console.warn('🎛️ Error loading saved state:', e);
            isVisible = false;
        }
    }

    /**
     * Apply initial state based on saved preferences
     */
    function applyInitialState() {
        const sidebar = document.querySelector('.right-action-sidebar');
        if (!sidebar || !rightActionToggle) {
            console.warn('🎛️ Sidebar or toggle not found for initial state');
            return;
        }

        console.log('🎛️ Applying initial state:', isVisible ? 'visible' : 'hidden');

        if (isVisible) {
            showSidebarFromToggle(sidebar);
        } else {
            hideSidebarFromToggle(sidebar);
        }
    }

    /**
     * Monitor for sidebar creation and apply state
     */
    function startSidebarMonitoring() {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        if (node.classList && node.classList.contains('right-action-sidebar')) {
                            console.log('🎛️ Right action sidebar detected, applying state...');
                            setTimeout(() => {
                                applyInitialState();
                            }, 100);
                        }
                    }
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        console.log('🎛️ Sidebar monitoring started');
    }

    /**
     * Get current visibility state
     */
    function getSidebarVisibility() {
        return isVisible;
    }

    /**
     * Set sidebar visibility programmatically
     */
    function setSidebarVisibility(visible) {
        if (visible !== isVisible) {
            toggleSidebarFromToggle();
        }
    }

    /**
     * Force show sidebar
     */
    function showSidebar() {
        if (!isVisible) {
            toggleSidebarFromToggle();
        }
    }

    /**
     * Force hide sidebar
     */
    function hideSidebar() {
        if (isVisible) {
            toggleSidebarFromToggle();
        }
    }

    /**
     * Initialize when DOM is ready
     */
    function initialize() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                setTimeout(init, 100);
                startSidebarMonitoring();
            });
        } else {
            setTimeout(init, 100);
            startSidebarMonitoring();
        }
    }

    /**
     * Debug function to check current state
     */
    function debugSidebarState() {
        console.log('🎛️ === Right Action Sidebar Debug ===');

        const sidebar = document.querySelector('.right-action-sidebar');
        const toggle = document.getElementById('right-action-sidebar-toggle');

        console.log('🎛️ Current visibility state:', isVisible);
        console.log('🎛️ Sidebar element found:', !!sidebar);
        console.log('🎛️ Toggle element found:', !!toggle);

        if (sidebar) {
            console.log('🎛️ Sidebar classes:', sidebar.className);
            console.log('🎛️ Sidebar computed styles:', {
                display: getComputedStyle(sidebar).display,
                visibility: getComputedStyle(sidebar).visibility,
                opacity: getComputedStyle(sidebar).opacity,
                position: getComputedStyle(sidebar).position,
                zIndex: getComputedStyle(sidebar).zIndex
            });
            console.log('🎛️ Sidebar inline styles:', sidebar.style.cssText);
        }

        if (toggle) {
            console.log('🎛️ Toggle classes:', toggle.className);
            console.log('🎛️ Toggle aria-pressed:', toggle.getAttribute('aria-pressed'));
        }

        // Check body classes for role-based CSS
        console.log('🎛️ Body classes:', document.body.className);

        console.log('🎛️ === End Debug ===');
    }

    /**
     * Force show sidebar for debugging
     */
    function forceShowSidebar() {
        console.log('🎛️ Force showing sidebar...');

        const sidebar = document.querySelector('.right-action-sidebar');
        if (sidebar) {
            // Add force-visible class
            sidebar.classList.add('force-visible', 'sidebar-visible');

            // Force all styles
            sidebar.style.setProperty('display', 'flex', 'important');
            sidebar.style.setProperty('visibility', 'visible', 'important');
            sidebar.style.setProperty('opacity', '1', 'important');
            sidebar.style.setProperty('pointer-events', 'auto', 'important');
            sidebar.style.setProperty('position', 'fixed', 'important');
            sidebar.style.setProperty('top', '50%', 'important');
            sidebar.style.setProperty('right', '20px', 'important');
            sidebar.style.setProperty('transform', 'translateY(-50%)', 'important');
            sidebar.style.setProperty('z-index', '1500', 'important');

            isVisible = true;

            console.log('🎛️ Sidebar force shown');
            debugSidebarState();
        } else {
            console.error('🎛️ Sidebar not found for force show');
        }
    }

    // Initialize
    initialize();

    /**
     * Debug function to find duplicate toggles
     */
    function findDuplicateToggles() {
        console.log('🎛️ === Duplicate Toggle Detection ===');

        const allToggles = document.querySelectorAll('[class*="toggle-control"]');
        const rightToggles = document.querySelectorAll('[id*="right"], [class*="right-"]');

        console.log('🎛️ All toggle controls found:', allToggles.length);
        console.log('🎛️ Right-side elements found:', rightToggles.length);

        allToggles.forEach((toggle, index) => {
            const rect = toggle.getBoundingClientRect();
            const styles = getComputedStyle(toggle);

            console.log(`🎛️ Toggle ${index + 1}:`, {
                id: toggle.id || 'no-id',
                className: toggle.className,
                innerHTML: toggle.innerHTML.substring(0, 100) + '...',
                position: {
                    top: styles.top,
                    right: styles.right,
                    zIndex: styles.zIndex,
                    display: styles.display,
                    visibility: styles.visibility
                },
                boundingRect: {
                    top: rect.top,
                    right: rect.right,
                    width: rect.width,
                    height: rect.height
                }
            });
        });

        // Check for overlapping positions
        const rightSideToggles = Array.from(allToggles).filter(toggle => {
            const rect = toggle.getBoundingClientRect();
            return rect.right > window.innerWidth - 100; // Within 100px of right edge
        });

        console.log('🎛️ Right-side positioned toggles:', rightSideToggles.length);

        if (rightSideToggles.length > 1) {
            console.warn('🎛️ Multiple right-side toggles detected - potential duplication!');
            rightSideToggles.forEach((toggle, index) => {
                console.warn(`🎛️ Right toggle ${index + 1}:`, toggle.id, toggle.className);
            });
        }

        console.log('🎛️ === End Duplicate Detection ===');

        return {
            allToggles: allToggles.length,
            rightSideToggles: rightSideToggles.length,
            duplicates: rightSideToggles.length > 1
        };
    }

    // Export public API
    window.RightActionSidebarToggle = {
        getSidebarVisibility,
        setSidebarVisibility,
        showSidebar,
        hideSidebar,
        toggle: toggleSidebarFromToggle,
        debug: debugSidebarState,
        forceShow: forceShowSidebar,
        findDuplicates: findDuplicateToggles,
        checkConflicts: checkForToggleConflicts
    };

    console.log('🎛️ Right Action Sidebar Toggle - Loaded');

})();
