/**
 * Upcoming Draws Header Visibility Fix
 * Ensures the upcoming draws header is always visible and functional
 */

(function() {
    'use strict';

    console.log('🔧 Upcoming Draws Header Fix - Initializing...');

    let headerCheckInterval = null;
    let headerElement = null;
    let panelElement = null;

    /**
     * Force header visibility and fix any issues
     */
    function forceHeaderVisibility() {
        // Find the header element
        headerElement = document.querySelector('.upcoming-draws-header');
        panelElement = document.querySelector('.upcoming-draws-panel');

        if (headerElement) {
            console.log('🔧 Found upcoming draws header, applying visibility fixes...');

            // Force visibility styles
            headerElement.style.display = 'flex';
            headerElement.style.visibility = 'visible';
            headerElement.style.opacity = '1';
            headerElement.style.position = 'relative';
            headerElement.style.zIndex = '1501';
            headerElement.style.overflow = 'visible';
            headerElement.style.pointerEvents = 'auto';

            // Add restored class for animation
            headerElement.classList.add('restored');
            setTimeout(() => {
                headerElement.classList.remove('restored');
            }, 500);

            // Fix title section
            const titleElement = headerElement.querySelector('.upcoming-draws-title');
            if (titleElement) {
                titleElement.style.display = 'flex';
                titleElement.style.visibility = 'visible';
                titleElement.style.opacity = '1';
                titleElement.style.zIndex = '1502';

                // Fix icon
                const iconElement = titleElement.querySelector('i');
                if (iconElement) {
                    iconElement.style.visibility = 'visible';
                    iconElement.style.opacity = '1';
                }
            }

            // Fix controls section
            const controlsElement = headerElement.querySelector('.upcoming-draws-controls');
            if (controlsElement) {
                controlsElement.style.display = 'flex';
                controlsElement.style.visibility = 'visible';
                controlsElement.style.opacity = '1';
                controlsElement.style.zIndex = '1502';
                controlsElement.style.pointerEvents = 'auto';

                // Fix individual control buttons
                const controlButtons = controlsElement.querySelectorAll('.upcoming-draws-control');
                controlButtons.forEach(button => {
                    button.style.display = 'flex';
                    button.style.visibility = 'visible';
                    button.style.opacity = '1';
                    button.style.zIndex = '1503';
                    button.style.pointerEvents = 'auto';
                    button.style.position = 'relative';

                    // Fix button icons
                    const buttonIcon = button.querySelector('i');
                    if (buttonIcon) {
                        buttonIcon.style.visibility = 'visible';
                        buttonIcon.style.opacity = '1';
                    }
                });
            }

            console.log('🔧 Header visibility fixes applied successfully');
            return true;
        }

        return false;
    }

    /**
     * Fix panel positioning and visibility
     */
    function fixPanelPositioning() {
        if (panelElement) {
            // Ensure panel is visible
            panelElement.style.display = 'block';
            panelElement.style.visibility = 'visible';
            panelElement.style.opacity = '1';
            panelElement.style.zIndex = '1500';
            panelElement.style.pointerEvents = 'auto';

            // Fix content area
            const contentElement = panelElement.querySelector('.upcoming-draws-content');
            if (contentElement) {
                contentElement.style.display = 'block';
                contentElement.style.visibility = 'visible';
                contentElement.style.opacity = '1';
                contentElement.style.overflow = 'visible';
            }

            console.log('🔧 Panel positioning fixes applied');
        }
    }

    /**
     * Ensure control buttons are functional
     */
    function ensureControlsFunctionality() {
        const refreshBtn = document.querySelector('.upcoming-draws-controls .refresh-btn');
        const toggleBtn = document.querySelector('.upcoming-draws-controls .toggle-btn');

        if (refreshBtn) {
            // Remove any existing event listeners and add new ones
            refreshBtn.style.cursor = 'pointer';
            refreshBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('🔧 Refresh button clicked');
                
                // Add visual feedback
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 150);

                // Trigger refresh functionality
                if (window.UpcomingDrawDisplay && window.UpcomingDrawDisplay.syncUpcomingDraws) {
                    window.UpcomingDrawDisplay.syncUpcomingDraws();
                }
            });
            console.log('🔧 Refresh button functionality restored');
        }

        if (toggleBtn) {
            // Remove any existing event listeners and add new ones
            toggleBtn.style.cursor = 'pointer';
            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('🔧 Toggle button clicked');
                
                // Add visual feedback
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 150);

                // Trigger toggle functionality
                const content = document.querySelector('.upcoming-draws-content');
                if (content) {
                    const isVisible = content.style.display !== 'none';
                    if (isVisible) {
                        content.style.display = 'none';
                        this.querySelector('i').className = 'fas fa-chevron-down';
                    } else {
                        content.style.display = 'block';
                        this.querySelector('i').className = 'fas fa-chevron-up';
                    }
                }
            });
            console.log('🔧 Toggle button functionality restored');
        }
    }

    /**
     * Check for conflicts with toggle controls
     */
    function checkToggleConflicts() {
        const leftToggle = document.querySelector('.left-side-toggle-control');
        const rightToggle = document.querySelector('.right-side-toggle-control');

        if (leftToggle) {
            leftToggle.style.zIndex = '10002';
        }

        if (rightToggle) {
            rightToggle.style.zIndex = '10002';
        }

        if (panelElement) {
            panelElement.style.zIndex = '1500';
        }

        console.log('🔧 Z-index conflicts resolved');
    }

    /**
     * Continuous monitoring for header visibility
     */
    function startHeaderMonitoring() {
        headerCheckInterval = setInterval(() => {
            const header = document.querySelector('.upcoming-draws-header');
            
            if (header) {
                // Check if header is visible
                const rect = header.getBoundingClientRect();
                const isVisible = rect.width > 0 && rect.height > 0 && 
                                 window.getComputedStyle(header).visibility !== 'hidden' &&
                                 window.getComputedStyle(header).display !== 'none';

                if (!isVisible) {
                    console.log('🔧 Header visibility issue detected, applying fixes...');
                    forceHeaderVisibility();
                    fixPanelPositioning();
                    ensureControlsFunctionality();
                }
            }
        }, 2000); // Check every 2 seconds

        console.log('🔧 Header monitoring started');
    }

    /**
     * Initialize all fixes
     */
    function initialize() {
        console.log('🔧 Applying upcoming draws header fixes...');

        // Apply fixes immediately
        setTimeout(() => {
            forceHeaderVisibility();
            fixPanelPositioning();
            ensureControlsFunctionality();
            checkToggleConflicts();
        }, 100);

        // Apply fixes after a delay to catch late-loading elements
        setTimeout(() => {
            forceHeaderVisibility();
            fixPanelPositioning();
            ensureControlsFunctionality();
            checkToggleConflicts();
        }, 1000);

        // Apply fixes after an even longer delay
        setTimeout(() => {
            forceHeaderVisibility();
            fixPanelPositioning();
            ensureControlsFunctionality();
            checkToggleConflicts();
            startHeaderMonitoring();
        }, 3000);

        console.log('🔧 Header fix initialization complete');
    }

    /**
     * Force immediate fix on demand
     */
    function forceImmediateFix() {
        console.log('🔧 Forcing immediate header fix...');
        forceHeaderVisibility();
        fixPanelPositioning();
        ensureControlsFunctionality();
        checkToggleConflicts();
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }

    // Also initialize after window load
    window.addEventListener('load', () => {
        setTimeout(forceImmediateFix, 500);
    });

    // Expose global function for manual fixing
    window.fixUpcomingDrawsHeader = forceImmediateFix;

    // Handle visibility change events
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            setTimeout(forceImmediateFix, 100);
        }
    });

    console.log('🔧 Upcoming Draws Header Fix - Loaded');

})();
