/**
 * Fullscreen Manager
 * Handles fullscreen functionality for the casino TV display
 */
(function() {
    console.log('🖥️ Fullscreen Manager: Initializing...');
    
    let isFullscreen = false;
    let fullscreenButton = null;
    
    /**
     * Initialize fullscreen functionality
     */
    function initFullscreenManager() {
        setupFullscreenButton();
        setupFullscreenEvents();
        setupKeyboardShortcuts();
        
        console.log('🖥️ Fullscreen Manager: Initialized successfully');
    }
    
    /**
     * Setup fullscreen toggle button
     */
    function setupFullscreenButton() {
        fullscreenButton = document.getElementById('fullscreen-toggle-button');
        
        if (fullscreenButton) {
            fullscreenButton.addEventListener('click', toggleFullscreen);
            fullscreenButton.title = 'Toggle Fullscreen (F11)';
            console.log('🖥️ Fullscreen Manager: Button setup complete');
        } else {
            console.warn('🖥️ Fullscreen Manager: Button not found, creating dynamically');
            createFullscreenButton();
        }
    }
    
    /**
     * Create fullscreen button dynamically if not found
     */
    function createFullscreenButton() {
        fullscreenButton = document.createElement('div');
        fullscreenButton.id = 'fullscreen-toggle-button';
        fullscreenButton.className = 'fullscreen-toggle-button';
        fullscreenButton.innerHTML = '<i class="fas fa-expand"></i>';
        fullscreenButton.title = 'Toggle Fullscreen (F11)';
        
        // Apply styles directly
        fullscreenButton.style.position = 'fixed';
        fullscreenButton.style.right = '20px';
        fullscreenButton.style.bottom = '20px';
        fullscreenButton.style.backgroundColor = '#28a745';
        fullscreenButton.style.color = 'white';
        fullscreenButton.style.width = '50px';
        fullscreenButton.style.height = '50px';
        fullscreenButton.style.borderRadius = '50%';
        fullscreenButton.style.display = 'flex';
        fullscreenButton.style.alignItems = 'center';
        fullscreenButton.style.justifyContent = 'center';
        fullscreenButton.style.cursor = 'pointer';
        fullscreenButton.style.boxShadow = '0 4px 8px rgba(0, 0, 0, 0.3)';
        fullscreenButton.style.zIndex = '9998';
        fullscreenButton.style.transition = 'all 0.3s ease';
        
        fullscreenButton.addEventListener('click', toggleFullscreen);
        fullscreenButton.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.1)';
            this.style.boxShadow = '0 6px 12px rgba(0, 0, 0, 0.4)';
        });
        fullscreenButton.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
            this.style.boxShadow = '0 4px 8px rgba(0, 0, 0, 0.3)';
        });
        
        document.body.appendChild(fullscreenButton);
        console.log('🖥️ Fullscreen Manager: Button created dynamically');
    }
    
    /**
     * Setup fullscreen event listeners
     */
    function setupFullscreenEvents() {
        // Listen for fullscreen changes
        document.addEventListener('fullscreenchange', handleFullscreenChange);
        document.addEventListener('webkitfullscreenchange', handleFullscreenChange);
        document.addEventListener('mozfullscreenchange', handleFullscreenChange);
        document.addEventListener('MSFullscreenChange', handleFullscreenChange);
        
        console.log('🖥️ Fullscreen Manager: Event listeners setup');
    }
    
    /**
     * Setup keyboard shortcuts
     */
    function setupKeyboardShortcuts() {
        document.addEventListener('keydown', function(event) {
            // F11 key for fullscreen toggle
            if (event.key === 'F11') {
                event.preventDefault();
                toggleFullscreen();
            }
            
            // Escape key to exit fullscreen
            if (event.key === 'Escape' && isFullscreen) {
                exitFullscreen();
            }
        });
        
        console.log('🖥️ Fullscreen Manager: Keyboard shortcuts setup (F11 to toggle, Escape to exit)');
    }
    
    /**
     * Toggle fullscreen mode
     */
    function toggleFullscreen() {
        if (isFullscreen) {
            exitFullscreen();
        } else {
            enterFullscreen();
        }
    }
    
    /**
     * Enter fullscreen mode
     */
    function enterFullscreen() {
        const element = document.documentElement;
        
        try {
            if (element.requestFullscreen) {
                element.requestFullscreen();
            } else if (element.webkitRequestFullscreen) {
                element.webkitRequestFullscreen();
            } else if (element.mozRequestFullScreen) {
                element.mozRequestFullScreen();
            } else if (element.msRequestFullscreen) {
                element.msRequestFullscreen();
            } else {
                // Fallback for browsers that don't support fullscreen API
                simulateFullscreen();
            }
            
            console.log('🖥️ Fullscreen Manager: Entering fullscreen mode');
        } catch (error) {
            console.error('🖥️ Fullscreen Manager: Error entering fullscreen:', error);
            simulateFullscreen();
        }
    }
    
    /**
     * Exit fullscreen mode
     */
    function exitFullscreen() {
        try {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            } else if (document.mozCancelFullScreen) {
                document.mozCancelFullScreen();
            } else if (document.msExitFullscreen) {
                document.msExitFullscreen();
            } else {
                // Fallback
                exitSimulatedFullscreen();
            }
            
            console.log('🖥️ Fullscreen Manager: Exiting fullscreen mode');
        } catch (error) {
            console.error('🖥️ Fullscreen Manager: Error exiting fullscreen:', error);
            exitSimulatedFullscreen();
        }
    }
    
    /**
     * Handle fullscreen change events
     */
    function handleFullscreenChange() {
        const isCurrentlyFullscreen = !!(
            document.fullscreenElement ||
            document.webkitFullscreenElement ||
            document.mozFullScreenElement ||
            document.msFullscreenElement
        );
        
        isFullscreen = isCurrentlyFullscreen;
        updateFullscreenButton();
        updateBodyClass();
        
        console.log('🖥️ Fullscreen Manager: Fullscreen state changed:', isFullscreen);
    }
    
    /**
     * Update fullscreen button appearance
     */
    function updateFullscreenButton() {
        if (fullscreenButton) {
            const icon = fullscreenButton.querySelector('i');
            if (icon) {
                if (isFullscreen) {
                    icon.className = 'fas fa-compress';
                    fullscreenButton.style.backgroundColor = '#dc3545';
                    fullscreenButton.title = 'Exit Fullscreen (F11 or Escape)';
                } else {
                    icon.className = 'fas fa-expand';
                    fullscreenButton.style.backgroundColor = '#28a745';
                    fullscreenButton.title = 'Enter Fullscreen (F11)';
                }
            }
        }
    }
    
    /**
     * Update body class for fullscreen styling
     */
    function updateBodyClass() {
        if (isFullscreen) {
            document.documentElement.classList.add('fullscreen-mode');
            document.body.classList.add('fullscreen-mode');

            // Force remove any margins/padding that might interfere
            document.documentElement.style.margin = '0';
            document.documentElement.style.padding = '0';
            document.documentElement.style.width = '100vw';
            document.documentElement.style.height = '100vh';
            document.documentElement.style.overflow = 'hidden';

            document.body.style.margin = '0';
            document.body.style.padding = '0';
            document.body.style.width = '100vw';
            document.body.style.height = '100vh';
            document.body.style.overflow = 'hidden';
            document.body.style.position = 'fixed';
            document.body.style.top = '0';
            document.body.style.left = '0';

        } else {
            document.documentElement.classList.remove('fullscreen-mode');
            document.body.classList.remove('fullscreen-mode');

            // Restore original styles
            document.documentElement.style.margin = '';
            document.documentElement.style.padding = '';
            document.documentElement.style.width = '';
            document.documentElement.style.height = '';
            document.documentElement.style.overflow = '';

            document.body.style.margin = '';
            document.body.style.padding = '';
            document.body.style.width = '';
            document.body.style.height = '';
            document.body.style.overflow = '';
            document.body.style.position = '';
            document.body.style.top = '';
            document.body.style.left = '';
        }
    }
    
    /**
     * Simulate fullscreen for browsers that don't support it
     */
    function simulateFullscreen() {
        // Apply to both html and body
        document.documentElement.style.margin = '0';
        document.documentElement.style.padding = '0';
        document.documentElement.style.width = '100vw';
        document.documentElement.style.height = '100vh';
        document.documentElement.style.overflow = 'hidden';

        document.body.style.position = 'fixed';
        document.body.style.top = '0';
        document.body.style.left = '0';
        document.body.style.right = '0';
        document.body.style.bottom = '0';
        document.body.style.width = '100vw';
        document.body.style.height = '100vh';
        document.body.style.margin = '0';
        document.body.style.padding = '0';
        document.body.style.zIndex = '9999';
        document.body.style.backgroundColor = '#000';
        document.body.style.overflow = 'hidden';

        isFullscreen = true;
        updateFullscreenButton();
        updateBodyClass();

        console.log('🖥️ Fullscreen Manager: Simulated fullscreen activated');
    }
    
    /**
     * Exit simulated fullscreen
     */
    function exitSimulatedFullscreen() {
        // Restore html element
        document.documentElement.style.margin = '';
        document.documentElement.style.padding = '';
        document.documentElement.style.width = '';
        document.documentElement.style.height = '';
        document.documentElement.style.overflow = '';

        // Restore body element
        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.left = '';
        document.body.style.right = '';
        document.body.style.bottom = '';
        document.body.style.width = '';
        document.body.style.height = '';
        document.body.style.margin = '';
        document.body.style.padding = '';
        document.body.style.zIndex = '';
        document.body.style.backgroundColor = '';
        document.body.style.overflow = '';

        isFullscreen = false;
        updateFullscreenButton();
        updateBodyClass();

        console.log('🖥️ Fullscreen Manager: Simulated fullscreen deactivated');
    }
    
    /**
     * Check if fullscreen is supported
     */
    function isFullscreenSupported() {
        return !!(
            document.fullscreenEnabled ||
            document.webkitFullscreenEnabled ||
            document.mozFullScreenEnabled ||
            document.msFullscreenEnabled
        );
    }
    
    /**
     * Get current fullscreen status
     */
    function getFullscreenStatus() {
        return {
            isFullscreen: isFullscreen,
            isSupported: isFullscreenSupported(),
            element: document.fullscreenElement || document.webkitFullscreenElement || 
                    document.mozFullScreenElement || document.msFullscreenElement
        };
    }
    
    // Public API
    window.FullscreenManager = {
        toggle: toggleFullscreen,
        enter: enterFullscreen,
        exit: exitFullscreen,
        isFullscreen: () => isFullscreen,
        isSupported: isFullscreenSupported,
        getStatus: getFullscreenStatus
    };
    
    // Console helper functions
    window.toggleFullscreen = toggleFullscreen;
    window.enterFullscreen = enterFullscreen;
    window.exitFullscreen = exitFullscreen;
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFullscreenManager);
    } else {
        initFullscreenManager();
    }
    
    console.log('🖥️ Fullscreen Manager: Loaded successfully!');
    console.log('💡 Use these console commands:');
    console.log('   toggleFullscreen() - Toggle fullscreen mode');
    console.log('   enterFullscreen() - Enter fullscreen mode');
    console.log('   exitFullscreen() - Exit fullscreen mode');
    console.log('💡 Keyboard shortcuts:');
    console.log('   F11 - Toggle fullscreen');
    console.log('   Escape - Exit fullscreen');
})();
