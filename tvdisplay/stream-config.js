/**
 * Live Stream Configuration
 * Easy configuration for YouTube live streams in the casino TV display
 */

window.StreamConfig = {
    // Current YouTube live stream URL
    // You can easily change this to any YouTube live stream URL
    currentStream: 'https://www.youtube.com/live/-g-E-eXp8iY?si=QdW6qLLLqmYyLKS-',
    
    // Alternative stream URLs (for easy switching)
    alternativeStreams: {
        cricket1: 'https://www.youtube.com/live/-g-E-eXp8iY?si=QdW6qLLLqmYyLKS-',
        cricket2: 'https://www.youtube.com/live/LV9-E3qaHkg?si=6YV_zrpMXgLuw5EP',
        sports: 'https://www.youtube.com/watch?v=SPORTS_VIDEO_ID',
        news: 'https://www.youtube.com/watch?v=NEWS_VIDEO_ID'
    },
    
    // Player settings
    playerSettings: {
        position: { x: 479, y: 100 },  // Position on screen
        size: { width: 400, height: 225 },  // Player size
        autoShow: true,  // Automatically show player when page loads
        muted: false,    // Start muted or unmuted
        label: 'CRICKET' // Label to show on player
    },
    
    // Display settings
    displaySettings: {
        showToggleButton: true,  // Show the video toggle button
        buttonPosition: 'bottom-right',  // Position of toggle button
        enableDragging: true,    // Allow dragging the player
        enableResizing: true     // Allow resizing the player
    },
    
    /**
     * Get the current stream URL
     * @returns {string} Current stream URL
     */
    getCurrentStream: function() {
        return this.currentStream;
    },
    
    /**
     * Set a new stream URL
     * @param {string} url - New YouTube stream URL
     */
    setCurrentStream: function(url) {
        this.currentStream = url;
        console.log('Stream URL updated to:', url);
    },
    
    /**
     * Switch to an alternative stream
     * @param {string} streamKey - Key from alternativeStreams
     */
    switchToAlternative: function(streamKey) {
        if (this.alternativeStreams[streamKey]) {
            this.setCurrentStream(this.alternativeStreams[streamKey]);
            return true;
        } else {
            console.error('Alternative stream not found:', streamKey);
            return false;
        }
    },
    
    /**
     * Add a new alternative stream
     * @param {string} key - Stream key
     * @param {string} url - YouTube URL
     */
    addAlternativeStream: function(key, url) {
        this.alternativeStreams[key] = url;
        console.log('Added alternative stream:', key, url);
    },
    
    /**
     * Get player settings
     * @returns {object} Player settings
     */
    getPlayerSettings: function() {
        return this.playerSettings;
    },
    
    /**
     * Update player settings
     * @param {object} newSettings - New settings to merge
     */
    updatePlayerSettings: function(newSettings) {
        this.playerSettings = { ...this.playerSettings, ...newSettings };
        console.log('Player settings updated:', this.playerSettings);
    },
    
    /**
     * Validate YouTube URL
     * @param {string} url - URL to validate
     * @returns {boolean} True if valid YouTube URL
     */
    isValidYouTubeUrl: function(url) {
        const patterns = [
            /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/live\/)([^&\n?#]+)/,
            /youtube\.com\/embed\/([^&\n?#]+)/
        ];
        
        return patterns.some(pattern => pattern.test(url));
    },
    
    /**
     * Extract video ID from YouTube URL
     * @param {string} url - YouTube URL
     * @returns {string|null} Video ID or null
     */
    extractVideoId: function(url) {
        const patterns = [
            /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/live\/)([^&\n?#]+)/,
            /youtube\.com\/embed\/([^&\n?#]+)/
        ];
        
        for (const pattern of patterns) {
            const match = url.match(pattern);
            if (match) {
                return match[1];
            }
        }
        return null;
    }
};

// Console helper functions for easy configuration
window.StreamConfigHelper = {
    /**
     * Quick function to change the current stream
     * Usage: changeStream('https://www.youtube.com/live/NEW_VIDEO_ID')
     */
    changeStream: function(url) {
        if (window.StreamConfig.isValidYouTubeUrl(url)) {
            window.StreamConfig.setCurrentStream(url);
            
            // If live stream player is available, update it
            if (window.LiveStreamPlayer && window.LiveStreamPlayer.isVisible()) {
                window.LiveStreamPlayer.hide();
                setTimeout(() => {
                    window.LiveStreamPlayer.show(url);
                }, 500);
            }
            
            console.log('✅ Stream changed successfully!');
            return true;
        } else {
            console.error('❌ Invalid YouTube URL provided');
            return false;
        }
    },
    
    /**
     * Show current configuration
     */
    showConfig: function() {
        console.log('📺 Current Stream Configuration:');
        console.log('Current Stream:', window.StreamConfig.getCurrentStream());
        console.log('Player Settings:', window.StreamConfig.getPlayerSettings());
        console.log('Alternative Streams:', window.StreamConfig.alternativeStreams);
    },
    
    /**
     * Quick switch to alternative stream
     * Usage: switchStream('cricket2')
     */
    switchStream: function(streamKey) {
        if (window.StreamConfig.switchToAlternative(streamKey)) {
            const newUrl = window.StreamConfig.getCurrentStream();
            
            // Update live stream player if visible
            if (window.LiveStreamPlayer && window.LiveStreamPlayer.isVisible()) {
                window.LiveStreamPlayer.hide();
                setTimeout(() => {
                    window.LiveStreamPlayer.show(newUrl);
                }, 500);
            }
            
            console.log('✅ Switched to alternative stream:', streamKey);
            return true;
        }
        return false;
    }
};

// Make helper functions available globally for console use
window.changeStream = window.StreamConfigHelper.changeStream;
window.switchStream = window.StreamConfigHelper.switchStream;
window.showStreamConfig = window.StreamConfigHelper.showConfig;

console.log('🎥 Stream Configuration loaded!');
console.log('💡 Use these console commands:');
console.log('   changeStream("https://youtube.com/live/VIDEO_ID") - Change current stream');
console.log('   switchStream("cricket1") - Switch to first cricket stream');
console.log('   switchStream("cricket2") - Switch to second cricket stream');
console.log('   showStreamConfig() - Show current configuration');
