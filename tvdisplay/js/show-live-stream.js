/**
 * Show Live Stream Player
 * This script automatically displays the live stream player on the right side of the screen
 */
(function() {
    // Get stream URL from configuration or use default
    function getStreamUrl() {
        if (window.StreamConfig && window.StreamConfig.getCurrentStream) {
            return window.StreamConfig.getCurrentStream();
        }
        // Fallback to default YouTube Cricket stream URL
        return 'https://www.youtube.com/live/-g-E-eXp8iY?si=QdW6qLLLqmYyLKS-';
    }
    
    // Wait for the document and LiveStreamPlayer to be ready
    function checkAndShowPlayer() {
        // For YouTube streams, we don't need HLS.js, just check for LiveStreamPlayer
        console.log('Setting up YouTube live stream player...');
        checkLiveStreamPlayer();
    }
    
    function checkLiveStreamPlayer() {
        if (window.LiveStreamPlayer) {
            console.log('LiveStreamPlayer found, setting up...');
            setupToggleButton();
            setupPlayer();
        } else {
            console.log('LiveStreamPlayer not found yet, waiting...');
            setTimeout(checkLiveStreamPlayer, 500);
        }
    }
    
    function showErrorAlert(message) {
        // Create a simple alert to show errors
        const alertElement = document.createElement('div');
        alertElement.style.position = 'fixed';
        alertElement.style.top = '20px';
        alertElement.style.right = '20px';
        alertElement.style.backgroundColor = '#FF5555';
        alertElement.style.color = 'white';
        alertElement.style.padding = '10px 20px';
        alertElement.style.borderRadius = '5px';
        alertElement.style.zIndex = '10000';
        alertElement.style.fontFamily = 'Arial, sans-serif';
        alertElement.style.boxShadow = '0 4px 8px rgba(0, 0, 0, 0.3)';
        alertElement.textContent = message;
        
        document.body.appendChild(alertElement);
        
        // Remove after 5 seconds
        setTimeout(() => {
            if (alertElement.parentNode) {
                alertElement.parentNode.removeChild(alertElement);
            }
        }, 5000);
    }
    
    function setupPlayer() {
        // Position the player on the right side of the screen
        const rightPosition = window.innerWidth - 450; // 400px width + 50px margin
        
        // Always reset the position to ensure it's on the right side
        localStorage.setItem('liveStreamPlayerSettings', JSON.stringify({
            position: { x: rightPosition, y: 100 },
            size: { width: 400, height: 225 }, // Larger size for better viewing
            muted: false // Unmuted by default for sports content
        }));
        
        // Show the player with the cricket stream URL
        setTimeout(() => {
            try {
                const streamUrl = getStreamUrl();
                LiveStreamPlayer.show(streamUrl);
                console.log('YouTube live stream player should now be visible on the right side');
                
                // Add a class to the player to make it more visible for debugging
                const playerElement = document.querySelector('.live-stream-player');
                if (playerElement) {
                    playerElement.style.border = '3px solid #FF5555';

                    // Force the position again just to be sure
                    playerElement.style.left = rightPosition + 'px';
                    playerElement.style.top = '100px';

                    // IMMEDIATE FIX: Hide loading indicator right after player is shown
                    const loadingIndicator = playerElement.querySelector('.live-stream-loading');
                    if (loadingIndicator) {
                        console.log('🔧 IMMEDIATE FIX: Hiding loading indicator');
                        loadingIndicator.style.display = 'none !important';
                        loadingIndicator.style.visibility = 'hidden !important';
                        loadingIndicator.style.opacity = '0 !important';
                    }

                    // Also use API if available
                    if (window.LiveStreamPlayer && window.LiveStreamPlayer.forceHideLoading) {
                        console.log('🔧 IMMEDIATE FIX: Using API to hide loading');
                        window.LiveStreamPlayer.forceHideLoading();
                    }

                    // Add YouTube cricket-specific label
                    const labelElement = document.createElement('div');
                    labelElement.style.position = 'absolute';
                    labelElement.style.top = '5px';
                    labelElement.style.right = '30px';
                    labelElement.style.backgroundColor = 'rgba(255, 0, 0, 0.7)';
                    labelElement.style.color = 'white';
                    labelElement.style.padding = '2px 8px';
                    labelElement.style.fontSize = '12px';
                    labelElement.style.fontFamily = 'Arial, sans-serif';
                    labelElement.style.borderRadius = '3px';
                    labelElement.style.zIndex = '10';
                    labelElement.textContent = 'CRICKET';
                    playerElement.appendChild(labelElement);

                    console.log('Enhanced player visibility and position');

                    // AGGRESSIVE FIX: Keep checking and hiding loading indicator
                    const aggressiveHideLoading = setInterval(() => {
                        const currentLoadingIndicator = playerElement.querySelector('.live-stream-loading');
                        if (currentLoadingIndicator && currentLoadingIndicator.style.display !== 'none') {
                            console.log('🔧 AGGRESSIVE FIX: Found visible loading indicator, hiding it');
                            currentLoadingIndicator.style.display = 'none !important';
                            currentLoadingIndicator.style.visibility = 'hidden !important';
                            currentLoadingIndicator.style.opacity = '0 !important';
                        }

                        // Also check for any element with "Loading stream" text
                        const allElements = playerElement.querySelectorAll('*');
                        allElements.forEach(element => {
                            if (element.textContent && element.textContent.includes('Loading stream')) {
                                console.log('🔧 AGGRESSIVE FIX: Found loading text, hiding element');
                                element.style.display = 'none !important';
                                element.style.visibility = 'hidden !important';
                                element.style.opacity = '0 !important';
                            }
                        });
                    }, 100); // Check every 100ms

                    // Stop aggressive checking after 10 seconds
                    setTimeout(() => {
                        clearInterval(aggressiveHideLoading);
                        console.log('🔧 AGGRESSIVE FIX: Stopped after 10 seconds');
                    }, 10000);

                } else {
                    console.warn('Could not find player element after showing');
                    showErrorAlert('Live stream player element not found after initialization.');
                }
            } catch (error) {
                console.error('Error showing live stream player:', error);
                showErrorAlert('Error showing live stream player: ' + error.message);
            }
        }, 1000); // Slight delay to ensure DOM is ready
    }
    
    function setupToggleButton() {
        const toggleButton = document.getElementById('live-stream-toggle-button');
        
        if (toggleButton) {
            toggleButton.addEventListener('click', function() {
                if (window.LiveStreamPlayer) {
                    console.log('Toggle button clicked');
                    
                    // If player is not visible, first position it correctly
                    if (!LiveStreamPlayer.isVisible()) {
                        const rightPosition = window.innerWidth - 450;
                        localStorage.setItem('liveStreamPlayerSettings', JSON.stringify({
                            position: { x: rightPosition, y: 100 },
                            size: { width: 400, height: 225 },
                            muted: false
                        }));
                    }
                    
                    // Toggle player visibility
                    const streamUrl = getStreamUrl();
                    LiveStreamPlayer.toggle(streamUrl);
                    
                    // Update button appearance
                    const iconElement = toggleButton.querySelector('i');
                    if (iconElement) {
                        if (LiveStreamPlayer.isVisible()) {
                            iconElement.className = 'fas fa-video-slash';
                            toggleButton.style.backgroundColor = '#555';
                        } else {
                            iconElement.className = 'fas fa-video';
                            toggleButton.style.backgroundColor = '#FF5555';
                        }
                    }
                }
            });
            
            console.log('Toggle button event listener set up');
        } else {
            console.warn('Toggle button not found');
            
            // Create the toggle button dynamically if it doesn't exist
            const newToggleButton = document.createElement('div');
            newToggleButton.id = 'live-stream-toggle-button';
            newToggleButton.className = 'live-stream-toggle-button';
            newToggleButton.innerHTML = '<i class="fas fa-video"></i>';
            newToggleButton.style.position = 'fixed';
            newToggleButton.style.right = '20px';
            newToggleButton.style.bottom = '80px';
            newToggleButton.style.backgroundColor = '#FF5555';
            newToggleButton.style.color = 'white';
            newToggleButton.style.width = '50px';
            newToggleButton.style.height = '50px';
            newToggleButton.style.borderRadius = '50%';
            newToggleButton.style.display = 'flex';
            newToggleButton.style.alignItems = 'center';
            newToggleButton.style.justifyContent = 'center';
            newToggleButton.style.cursor = 'pointer';
            newToggleButton.style.boxShadow = '0 4px 8px rgba(0, 0, 0, 0.3)';
            newToggleButton.style.zIndex = '9998';
            
            document.body.appendChild(newToggleButton);
            console.log('Created toggle button dynamically');
            
            // Recursively call setupToggleButton to attach event listener
            setTimeout(setupToggleButton, 100);
        }
    }
    
    // Start checking once the document is loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', checkAndShowPlayer);
    } else {
        checkAndShowPlayer();
    }
})(); 