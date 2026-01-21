/**
 * Right Action Sidebar - Horizontal Position Override
 * Ensures the sidebar is positioned horizontally centered under the top-bar
 * This script runs after other scripts to override any inline styles
 */

(function() {
    'use strict';

    console.log('🎯 Right Action Sidebar Horizontal Position - Initializing...');

    /**
     * Apply horizontal positioning to the sidebar
     */
    function applyHorizontalPosition() {
        const sidebar = document.querySelector('.right-action-sidebar');
        
        if (!sidebar) {
            console.log('🎯 Sidebar not found, will retry...');
            return;
        }

        // Only apply if sidebar is visible
        if (sidebar.classList.contains('sidebar-visible') || sidebar.classList.contains('sidebar-showing')) {
            console.log('🎯 Applying horizontal positioning to sidebar...');
            
            // Override inline styles with horizontal positioning
            sidebar.style.setProperty('top', '120px', 'important'); // Moved down to avoid header (was 90px)
            sidebar.style.setProperty('left', '50%', 'important'); // Center horizontally
            sidebar.style.setProperty('right', 'auto', 'important');
            sidebar.style.setProperty('transform', 'translateX(-50%)', 'important'); // Center horizontally
            sidebar.style.setProperty('z-index', '2001', 'important'); // Higher than top-bar
            sidebar.style.setProperty('flex-direction', 'row', 'important'); // Horizontal layout
            sidebar.style.setProperty('align-items', 'center', 'important');
            sidebar.style.setProperty('justify-content', 'center', 'important');
            
            console.log('✅ Horizontal positioning applied');
        }
    }

    /**
     * Monitor for sidebar visibility changes
     */
    function startMonitoring() {
        // Apply immediately
        applyHorizontalPosition();

        // Monitor for class changes
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                    const sidebar = mutation.target;
                    if (sidebar.classList.contains('sidebar-visible') || sidebar.classList.contains('sidebar-showing')) {
                        // Small delay to ensure other scripts have finished
                        setTimeout(() => {
                            applyHorizontalPosition();
                        }, 50);
                    }
                }
                
                // Also check for style attribute changes
                if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                    const sidebar = mutation.target;
                    if (sidebar.classList.contains('sidebar-visible') || sidebar.classList.contains('sidebar-showing')) {
                        setTimeout(() => {
                            applyHorizontalPosition();
                        }, 50);
                    }
                }
            });
        });

        // Observe sidebar if it exists
        const sidebar = document.querySelector('.right-action-sidebar');
        if (sidebar) {
            observer.observe(sidebar, {
                attributes: true,
                attributeFilter: ['class', 'style']
            });
        } else {
            // Wait for sidebar to be created
            const bodyObserver = new MutationObserver((mutations) => {
                const sidebar = document.querySelector('.right-action-sidebar');
                if (sidebar) {
                    observer.observe(sidebar, {
                        attributes: true,
                        attributeFilter: ['class', 'style']
                    });
                    bodyObserver.disconnect();
                    applyHorizontalPosition();
                }
            });
            
            bodyObserver.observe(document.body, {
                childList: true,
                subtree: true
            });
        }

        // Periodic check to ensure positioning is maintained
        setInterval(() => {
            applyHorizontalPosition();
        }, 1000);
    }

    /**
     * Initialize when DOM is ready
     */
    function init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                setTimeout(startMonitoring, 500);
            });
        } else {
            setTimeout(startMonitoring, 500);
        }
    }

    // Start initialization
    init();

    console.log('✅ Right Action Sidebar Horizontal Position initialized');
})();

