/**
 * Multi-Stream Manager
 * Manages multiple YouTube live stream players simultaneously
 */
(function() {
    console.log('🎥 Multi-Stream Manager: Initializing...');
    
    // Configuration for multiple streams
    const streamConfigs = [
        {
            url: 'https://www.youtube.com/live/-g-E-eXp8iY?si=QdW6qLLLqmYyLKS-',
            label: 'CRICKET 1',
            position: { x: 50, y: 100 },
            size: { width: 400, height: 225 }
        },
        {
            url: 'https://www.youtube.com/live/LV9-E3qaHkg?si=6YV_zrpMXgLuw5EP',
            label: 'CRICKET 2',
            position: { x: 500, y: 100 },
            size: { width: 400, height: 225 }
        }
    ];
    
    let activePlayerIds = [];
    let isMultiPlayerMode = false;
    
    /**
     * Initialize multi-player system
     */
    function initMultiPlayerSystem() {
        // Wait for MultiStreamPlayer to be available
        if (typeof window.MultiStreamPlayer === 'undefined') {
            console.log('🎥 Multi-Stream Manager: Waiting for MultiStreamPlayer...');
            setTimeout(initMultiPlayerSystem, 500);
            return;
        }
        
        console.log('🎥 Multi-Stream Manager: MultiStreamPlayer available');
        setupMultiPlayerControls();
    }
    
    /**
     * Setup controls for multi-player system
     */
    function setupMultiPlayerControls() {
        // Create multi-player toggle button
        const multiToggleButton = document.createElement('div');
        multiToggleButton.id = 'multi-stream-toggle-button';
        multiToggleButton.className = 'multi-stream-toggle-button';
        multiToggleButton.innerHTML = '<i class="fas fa-th"></i>';
        multiToggleButton.style.position = 'fixed';
        multiToggleButton.style.right = '20px';
        multiToggleButton.style.bottom = '140px'; // Below the single stream button
        multiToggleButton.style.backgroundColor = '#6f42c1';
        multiToggleButton.style.color = 'white';
        multiToggleButton.style.width = '50px';
        multiToggleButton.style.height = '50px';
        multiToggleButton.style.borderRadius = '50%';
        multiToggleButton.style.display = 'flex';
        multiToggleButton.style.alignItems = 'center';
        multiToggleButton.style.justifyContent = 'center';
        multiToggleButton.style.cursor = 'pointer';
        multiToggleButton.style.boxShadow = '0 4px 8px rgba(0, 0, 0, 0.3)';
        multiToggleButton.style.zIndex = '9998';
        multiToggleButton.style.transition = 'all 0.3s ease';
        
        multiToggleButton.addEventListener('click', toggleMultiPlayerMode);
        multiToggleButton.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.1)';
            this.style.boxShadow = '0 6px 12px rgba(0, 0, 0, 0.4)';
        });
        multiToggleButton.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
            this.style.boxShadow = '0 4px 8px rgba(0, 0, 0, 0.3)';
        });
        
        document.body.appendChild(multiToggleButton);
        console.log('🎥 Multi-Stream Manager: Toggle button created');
    }
    
    /**
     * Toggle multi-player mode
     */
    function toggleMultiPlayerMode() {
        if (isMultiPlayerMode) {
            hideAllPlayers();
        } else {
            showAllPlayers();
        }
    }
    
    /**
     * Show all configured players
     */
    function showAllPlayers() {
        console.log('🎥 Multi-Stream Manager: Showing all players');
        
        // Hide single player if visible
        if (window.LiveStreamPlayer && window.LiveStreamPlayer.isVisible()) {
            window.LiveStreamPlayer.hide();
        }
        
        // Create all configured players
        streamConfigs.forEach((config, index) => {
            const playerId = window.MultiStreamPlayer.createPlayer(config.url, {
                position: config.position,
                size: config.size,
                label: config.label
            });
            activePlayerIds.push(playerId);
        });
        
        isMultiPlayerMode = true;
        updateToggleButton();
        
        console.log('✅ Multi-Stream Manager: All players shown');
    }
    
    /**
     * Hide all players
     */
    function hideAllPlayers() {
        console.log('🎥 Multi-Stream Manager: Hiding all players');
        
        window.MultiStreamPlayer.removeAllPlayers();
        activePlayerIds = [];
        isMultiPlayerMode = false;
        updateToggleButton();
        
        console.log('✅ Multi-Stream Manager: All players hidden');
    }
    
    /**
     * Update toggle button appearance
     */
    function updateToggleButton() {
        const button = document.getElementById('multi-stream-toggle-button');
        if (button) {
            const icon = button.querySelector('i');
            if (isMultiPlayerMode) {
                icon.className = 'fas fa-times';
                button.style.backgroundColor = '#dc3545';
                button.title = 'Hide Multiple Streams';
            } else {
                icon.className = 'fas fa-th';
                button.style.backgroundColor = '#6f42c1';
                button.title = 'Show Multiple Streams';
            }
        }
    }
    
    /**
     * Add a new stream to the multi-player system
     */
    function addStream(url, label, position) {
        if (!isMultiPlayerMode) {
            showAllPlayers();
        }
        
        const config = {
            position: position || { x: 50 + (activePlayerIds.length * 50), y: 100 + (activePlayerIds.length * 50) },
            size: { width: 400, height: 225 },
            label: label || 'STREAM ' + (activePlayerIds.length + 1)
        };
        
        const playerId = window.MultiStreamPlayer.createPlayer(url, config);
        activePlayerIds.push(playerId);
        
        console.log('✅ Multi-Stream Manager: Added new stream:', label);
        return playerId;
    }
    
    /**
     * Remove a specific stream
     */
    function removeStream(playerId) {
        window.MultiStreamPlayer.removePlayer(playerId);
        activePlayerIds = activePlayerIds.filter(id => id !== playerId);
        
        if (activePlayerIds.length === 0) {
            isMultiPlayerMode = false;
            updateToggleButton();
        }
        
        console.log('✅ Multi-Stream Manager: Removed stream:', playerId);
    }
    
    // Public API
    window.MultiStreamManager = {
        showAll: showAllPlayers,
        hideAll: hideAllPlayers,
        toggle: toggleMultiPlayerMode,
        addStream: addStream,
        removeStream: removeStream,
        isActive: () => isMultiPlayerMode,
        getActivePlayerIds: () => [...activePlayerIds],
        
        // Quick access functions
        showCricketStreams: showAllPlayers,
        hideCricketStreams: hideAllPlayers
    };
    
    // Console helper functions
    window.showMultiStreams = showAllPlayers;
    window.hideMultiStreams = hideAllPlayers;
    window.toggleMultiStreams = toggleMultiPlayerMode;
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMultiPlayerSystem);
    } else {
        initMultiPlayerSystem();
    }
    
    console.log('🎥 Multi-Stream Manager: Loaded successfully!');
    console.log('💡 Use these console commands:');
    console.log('   showMultiStreams() - Show all streams');
    console.log('   hideMultiStreams() - Hide all streams');
    console.log('   toggleMultiStreams() - Toggle multi-stream mode');
})();
