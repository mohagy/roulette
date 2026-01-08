/**
 * Upcoming Draws Layout Toggle
 * 
 * Allows switching between horizontal header and vertical panel layouts
 */

(function() {
    console.log('📐 Upcoming Draws Layout Toggle - Initializing...');
    
    let isHorizontalMode = true; // Start in horizontal mode
    let horizontalStylesheet = null;
    
    /**
     * Initialize the layout toggle
     */
    function initialize() {
        // Find the horizontal stylesheet
        horizontalStylesheet = document.querySelector('link[href*="upcoming-draws-horizontal-header.css"]');
        
        // Create toggle button
        createToggleButton();
        
        // Apply initial layout
        setLayout(isHorizontalMode);
        
        console.log('📐 Layout toggle initialized');
    }
    
    /**
     * Create floating toggle button
     */
    function createToggleButton() {
        const toggleButton = document.createElement('div');
        toggleButton.id = 'upcoming-draws-layout-toggle';
        toggleButton.innerHTML = `
            <div class="toggle-inner">
                <i class="fas fa-arrows-alt-h"></i>
                <span>LAYOUT</span>
            </div>
        `;
        
        // Style the toggle button
        Object.assign(toggleButton.style, {
            position: 'fixed',
            top: '120px',
            right: '20px',
            width: '60px',
            height: '60px',
            background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
            borderRadius: '50%',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            cursor: 'pointer',
            zIndex: '99998',
            boxShadow: '0 4px 15px rgba(0, 0, 0, 0.3)',
            transition: 'all 0.3s ease',
            userSelect: 'none',
            border: '2px solid rgba(255, 255, 255, 0.2)'
        });
        
        // Style inner content
        const inner = toggleButton.querySelector('.toggle-inner');
        Object.assign(inner.style, {
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            justifyContent: 'center',
            color: 'white',
            fontSize: '10px',
            fontWeight: 'bold',
            textAlign: 'center',
            pointerEvents: 'none'
        });
        
        const icon = toggleButton.querySelector('i');
        Object.assign(icon.style, {
            fontSize: '16px',
            marginBottom: '2px'
        });
        
        // Add hover effects
        toggleButton.addEventListener('mouseenter', () => {
            toggleButton.style.transform = 'scale(1.1)';
            toggleButton.style.boxShadow = '0 6px 20px rgba(0, 0, 0, 0.4)';
        });
        
        toggleButton.addEventListener('mouseleave', () => {
            toggleButton.style.transform = 'scale(1)';
            toggleButton.style.boxShadow = '0 4px 15px rgba(0, 0, 0, 0.3)';
        });
        
        // Add click handler
        toggleButton.addEventListener('click', toggleLayout);
        
        document.body.appendChild(toggleButton);
        
        console.log('📐 Toggle button created');
    }
    
    /**
     * Toggle between horizontal and vertical layouts
     */
    function toggleLayout() {
        isHorizontalMode = !isHorizontalMode;
        setLayout(isHorizontalMode);
        
        // Update button appearance
        updateToggleButton();
        
        // Save preference
        localStorage.setItem('upcoming_draws_horizontal_mode', isHorizontalMode);
        
        console.log(`📐 Layout switched to: ${isHorizontalMode ? 'Horizontal Header' : 'Vertical Panel'}`);
    }
    
    /**
     * Set the layout mode
     */
    function setLayout(horizontal) {
        if (horizontal) {
            // Enable horizontal layout
            if (horizontalStylesheet) {
                horizontalStylesheet.disabled = false;
            }

            // Remove vertical class from body
            document.body.classList.remove('upcoming-draws-vertical');

            // Adjust body padding for header
            document.body.style.paddingTop = '100px';
            
        } else {
            // Disable horizontal layout (revert to vertical)
            if (horizontalStylesheet) {
                horizontalStylesheet.disabled = true;
            }

            // Add vertical class to body (triggers vertical CSS)
            document.body.classList.add('upcoming-draws-vertical');

            // Remove body padding
            document.body.style.paddingTop = '';
        }
        
        // Trigger a resize event to help panels adjust
        setTimeout(() => {
            window.dispatchEvent(new Event('resize'));
        }, 100);
    }
    
    /**
     * Update toggle button appearance
     */
    function updateToggleButton() {
        const toggleButton = document.getElementById('upcoming-draws-layout-toggle');
        if (!toggleButton) return;
        
        const icon = toggleButton.querySelector('i');
        const text = toggleButton.querySelector('span');
        
        if (isHorizontalMode) {
            // Horizontal mode - show vertical icon
            icon.className = 'fas fa-arrows-alt-v';
            text.textContent = 'VERTICAL';
            toggleButton.style.background = 'linear-gradient(135deg, #11998e 0%, #38ef7d 100%)';
        } else {
            // Vertical mode - show horizontal icon
            icon.className = 'fas fa-arrows-alt-h';
            text.textContent = 'HORIZONTAL';
            toggleButton.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
        }
    }
    
    /**
     * Load saved preference
     */
    function loadSavedPreference() {
        const saved = localStorage.getItem('upcoming_draws_horizontal_mode');
        if (saved !== null) {
            isHorizontalMode = saved === 'true';
        }
    }
    
    /**
     * Add keyboard shortcut
     */
    function setupKeyboardShortcut() {
        document.addEventListener('keydown', (e) => {
            // Ctrl + Shift + L to toggle layout
            if (e.ctrlKey && e.shiftKey && e.key === 'L') {
                e.preventDefault();
                toggleLayout();
            }
        });
        
        console.log('📐 Keyboard shortcut added: Ctrl+Shift+L');
    }
    
    // Expose API for manual control
    window.UpcomingDrawsLayoutToggle = {
        toggleLayout,
        setHorizontal: () => {
            isHorizontalMode = true;
            setLayout(true);
            updateToggleButton();
        },
        setVertical: () => {
            isHorizontalMode = false;
            setLayout(false);
            updateToggleButton();
        },
        isHorizontal: () => isHorizontalMode,
        getCurrentMode: () => isHorizontalMode ? 'horizontal' : 'vertical'
    };
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                loadSavedPreference();
                initialize();
                setupKeyboardShortcut();
            }, 1000);
        });
    } else {
        setTimeout(() => {
            loadSavedPreference();
            initialize();
            setupKeyboardShortcut();
        }, 1000);
    }
    
    console.log('📐 Upcoming Draws Layout Toggle loaded successfully!');
})();
