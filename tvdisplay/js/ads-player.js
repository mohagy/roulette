/**
 * Draggable Video Ads Player
 * Creates a draggable video player that cycles through ads in the /tvdisplay/adds/ directory.
 */

(function() {
    'use strict';
    
    // List of available ads
    const adVideos = [
        'adds1.mp4',
        'adds2.mp4',
        'adds3.mp4', 
        'adds4.mp4'
    ];
    
    // Configuration
    const config = {
        basePath: '/tvdisplay/adds/',  // Changed from relative to absolute path
        phpServerPath: '/tvdisplay/serve-video.php?file=', // PHP server for direct serving
        initialPosition: { x: 20, y: 100 },  // Initial position from the left top corner
        width: 320,            // Default width of the player
        height: 240,           // Default height of the player
        autoplay: true,        // Auto-play videos
        loop: true,            // Loop the playlist
        muted: true,           // Start muted
        draggable: true,       // Allow dragging
        controls: true,        // Show video controls
        resizable: true,       // Allow resizing
        zIndex: 1000,          // Z-index for the player
        storageKey: 'adsPlayerSettings', // Local storage key
        usePHPServer: false   // Flag to track if PHP server was used
    };
    
    let currentAdIndex = 0;
    let isDragging = false;
    let dragOffset = { x: 0, y: 0 };
    let adContainer, videoElement;
    
    /**
     * Save player settings to localStorage
     */
    function savePlayerSettings() {
        if (!adContainer) return;
        
        const settings = {
            position: {
                x: adContainer.offsetLeft,
                y: adContainer.offsetTop
            },
            size: {
                width: adContainer.offsetWidth,
                height: adContainer.offsetHeight
            },
            muted: videoElement ? videoElement.muted : config.muted
        };
        
        try {
            localStorage.setItem(config.storageKey, JSON.stringify(settings));
        } catch (e) {
            console.error('Failed to save player settings:', e);
        }
    }
    
    /**
     * Load player settings from localStorage
     */
    function loadPlayerSettings() {
        try {
            const settingsStr = localStorage.getItem(config.storageKey);
            if (settingsStr) {
                return JSON.parse(settingsStr);
            }
        } catch (e) {
            console.error('Failed to load player settings:', e);
        }
        return null;
    }
    
    /**
     * Create the ads player container and append it to the document body
     */
    function createAdsPlayer() {
        // Load saved settings if available
        const savedSettings = loadPlayerSettings();
        
        // Create container element
        adContainer = document.createElement('div');
        adContainer.id = 'ads-player-container';
        
        // Apply saved position and size if available
        if (savedSettings) {
            adContainer.style.left = savedSettings.position.x + 'px';
            adContainer.style.top = savedSettings.position.y + 'px';
            adContainer.style.width = savedSettings.size.width + 'px';
            adContainer.style.height = savedSettings.size.height + 'px';
            
            // Update config with saved settings for later use
            config.initialPosition = savedSettings.position;
            config.width = savedSettings.size.width;
            config.height = savedSettings.size.height;
            config.muted = savedSettings.muted;
        } else {
            adContainer.style.left = config.initialPosition.x + 'px';
            adContainer.style.top = config.initialPosition.y + 'px';
            adContainer.style.width = config.width + 'px';
            adContainer.style.height = config.height + 'px';
        }
        
        // Create header/drag handle
        const header = document.createElement('div');
        header.id = 'ads-player-header';
        header.style.backgroundColor = '#FFD700';
        header.style.color = '#000';
        header.style.padding = '5px 10px';
        header.style.cursor = 'move';
        header.style.fontFamily = 'Arial, sans-serif';
        header.style.fontSize = '14px';
        header.style.fontWeight = 'bold';
        header.style.display = 'flex';
        header.style.justifyContent = 'space-between';
        header.style.alignItems = 'center';
        header.innerText = 'Video Ads';
        
        // Add buttons to the header
        const buttonGroup = document.createElement('div');
        
        // Toggle play/pause button
        const playPauseBtn = document.createElement('button');
        playPauseBtn.innerHTML = '⏸️';
        playPauseBtn.style.background = 'none';
        playPauseBtn.style.border = 'none';
        playPauseBtn.style.cursor = 'pointer';
        playPauseBtn.style.fontSize = '16px';
        playPauseBtn.style.marginRight = '5px';
        playPauseBtn.title = 'Pause/Play';
        
        // Mute/unmute button
        const muteBtn = document.createElement('button');
        muteBtn.innerHTML = config.muted ? '🔇' : '🔊';
        muteBtn.style.background = 'none';
        muteBtn.style.border = 'none';
        muteBtn.style.cursor = 'pointer';
        muteBtn.style.fontSize = '16px';
        muteBtn.style.marginRight = '5px';
        muteBtn.title = 'Mute/Unmute';
        
        // Close button
        const closeBtn = document.createElement('button');
        closeBtn.innerHTML = '✖️';
        closeBtn.style.background = 'none';
        closeBtn.style.border = 'none';
        closeBtn.style.cursor = 'pointer';
        closeBtn.style.fontSize = '16px';
        closeBtn.title = 'Close';
        
        buttonGroup.appendChild(playPauseBtn);
        buttonGroup.appendChild(muteBtn);
        buttonGroup.appendChild(closeBtn);
        header.appendChild(buttonGroup);
        
        // Create video element
        videoElement = document.createElement('video');
        videoElement.style.width = '100%';
        videoElement.style.height = 'calc(100% - 30px)';
        videoElement.style.backgroundColor = '#000';
        videoElement.muted = config.muted;
        videoElement.controls = config.controls;
        
        if (config.autoplay) {
            videoElement.autoplay = true;
        }
        
        // Create play button overlay (for autoplay issues)
        const playButtonOverlay = document.createElement('div');
        playButtonOverlay.className = 'play-button-overlay';
        playButtonOverlay.style.display = 'none'; // Hide initially
        
        // Use Font Awesome or text-based play button depending on availability
        if (typeof FontAwesome !== 'undefined' || document.querySelector('link[href*="fontawesome"]')) {
            playButtonOverlay.innerHTML = '<i class="fas fa-play-circle"></i>';
        } else {
            // Fallback to text-based play button
            const playIcon = document.createElement('div');
            playIcon.textContent = '▶';
            playIcon.style.fontSize = '48px';
            playIcon.style.color = 'white';
            playButtonOverlay.appendChild(playIcon);
        }
        
        // Create error message element
        const errorMessage = document.createElement('div');
        errorMessage.className = 'video-error-message';
        errorMessage.style.display = 'none';
        
        // Add resize handle
        const resizeHandle = document.createElement('div');
        resizeHandle.id = 'ads-player-resize';
        resizeHandle.style.position = 'absolute';
        resizeHandle.style.right = '0';
        resizeHandle.style.bottom = '0';
        resizeHandle.style.width = '15px';
        resizeHandle.style.height = '15px';
        resizeHandle.style.cursor = 'nwse-resize';
        resizeHandle.style.backgroundImage = 'linear-gradient(135deg, transparent 70%, #FFD700 70%, #FFD700 100%)';
        
        // Assemble the player
        adContainer.appendChild(header);
        adContainer.appendChild(videoElement);
        adContainer.appendChild(playButtonOverlay);
        adContainer.appendChild(errorMessage);
        
        if (config.resizable) {
            adContainer.appendChild(resizeHandle);
        }
        
        document.body.appendChild(adContainer);
        
        // Set up event listeners for the drag functionality
        setupDragFunctionality(header);
        
        // Set up event listeners for resize functionality
        if (config.resizable) {
            setupResizeFunctionality(resizeHandle);
        }
        
        // Set up button event listeners
        playPauseBtn.addEventListener('click', togglePlayPause);
        muteBtn.addEventListener('click', toggleMute);
        closeBtn.addEventListener('click', hideAdsPlayer);
        
        // Set up play button overlay click
        playButtonOverlay.addEventListener('click', function() {
            videoElement.play().then(() => {
                playButtonOverlay.style.display = 'none';
            }).catch(error => {
                console.error('Play failed after click:', error);
                // If it still fails, try muted playback
                videoElement.muted = true;
                muteBtn.innerHTML = '🔇';
                videoElement.play().then(() => {
                    playButtonOverlay.style.display = 'none';
                    errorMessage.style.display = 'block';
                    errorMessage.textContent = 'Audio muted due to browser policies. Click the unmute button to enable sound.';
                    setTimeout(() => {
                        errorMessage.style.display = 'none';
                    }, 5000);
                });
            });
        });
        
        // Handle video loading errors
        videoElement.addEventListener('error', function(e) {
            console.error('Video error:', e);
            errorMessage.style.display = 'block';
            errorMessage.textContent = 'Error loading video. Trying alternative source...';
            
            // Try different paths or fallback mechanism
            tryAlternatePaths();
        });
        
        // Load first video
        loadNextAd();
        
        // Set up video ended event to play next video
        videoElement.addEventListener('ended', function() {
            loadNextAd();
            
            // Add pulse animation to highlight the change
            adContainer.classList.add('pulse-border');
            setTimeout(() => {
                adContainer.classList.remove('pulse-border');
            }, 3000);
        });
        
        // Update play/pause button based on video state
        videoElement.addEventListener('play', function() {
            playPauseBtn.innerHTML = '⏸️';
            playButtonOverlay.style.display = 'none';
        });
        
        videoElement.addEventListener('pause', function() {
            playPauseBtn.innerHTML = '▶️';
        });
        
        // Handle autoplay failure
        videoElement.addEventListener('canplay', function() {
            if (videoElement.paused && config.autoplay) {
                playButtonOverlay.style.display = 'flex';
            }
        });
        
        // Handle window resize events to keep player in viewport
        window.addEventListener('resize', function() {
            // Ensure player remains visible in the viewport
            ensureInViewport();
            
            // Save new position
            savePlayerSettings();
        });
        
        // Save settings when window unloads
        window.addEventListener('beforeunload', savePlayerSettings);
    }
    
    /**
     * Ensures the player remains within the viewport
     */
    function ensureInViewport() {
        if (!adContainer) return;
        
        const rect = adContainer.getBoundingClientRect();
        
        // Check if the player is outside the viewport
        if (rect.right > window.innerWidth) {
            adContainer.style.left = (window.innerWidth - rect.width) + 'px';
        }
        
        if (rect.bottom > window.innerHeight) {
            adContainer.style.top = (window.innerHeight - rect.height) + 'px';
        }
        
        if (rect.left < 0) {
            adContainer.style.left = '0px';
        }
        
        if (rect.top < 0) {
            adContainer.style.top = '0px';
        }
    }
    
    /**
     * Set up drag functionality for the player
     */
    function setupDragFunctionality(dragHandle) {
        if (!config.draggable) return;
        
        dragHandle.addEventListener('mousedown', function(e) {
            e.preventDefault();
            
            isDragging = true;
            dragOffset.x = e.clientX - adContainer.offsetLeft;
            dragOffset.y = e.clientY - adContainer.offsetTop;
            
            // Add dragging class for visual feedback
            adContainer.classList.add('dragging');
            
            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        });
        
        // Touch support for mobile devices
        dragHandle.addEventListener('touchstart', function(e) {
            e.preventDefault();
            
            isDragging = true;
            const touch = e.touches[0];
            dragOffset.x = touch.clientX - adContainer.offsetLeft;
            dragOffset.y = touch.clientY - adContainer.offsetTop;
            
            // Add dragging class for visual feedback
            adContainer.classList.add('dragging');
            
            document.addEventListener('touchmove', onTouchMove);
            document.addEventListener('touchend', onTouchEnd);
        });
    }
    
    /**
     * Mouse move handler for dragging
     */
    function onMouseMove(e) {
        if (!isDragging) return;
        
        let newLeft = e.clientX - dragOffset.x;
        let newTop = e.clientY - dragOffset.y;
        
        // Ensure the player stays within the viewport
        newLeft = Math.max(0, newLeft);
        newTop = Math.max(0, newTop);
        newLeft = Math.min(window.innerWidth - adContainer.offsetWidth, newLeft);
        newTop = Math.min(window.innerHeight - adContainer.offsetHeight, newTop);
        
        adContainer.style.left = newLeft + 'px';
        adContainer.style.top = newTop + 'px';
    }
    
    /**
     * Touch move handler for dragging
     */
    function onTouchMove(e) {
        if (!isDragging) return;
        
        const touch = e.touches[0];
        let newLeft = touch.clientX - dragOffset.x;
        let newTop = touch.clientY - dragOffset.y;
        
        // Ensure the player stays within the viewport
        newLeft = Math.max(0, newLeft);
        newTop = Math.max(0, newTop);
        newLeft = Math.min(window.innerWidth - adContainer.offsetWidth, newLeft);
        newTop = Math.min(window.innerHeight - adContainer.offsetHeight, newTop);
        
        adContainer.style.left = newLeft + 'px';
        adContainer.style.top = newTop + 'px';
    }
    
    /**
     * Mouse up handler for dragging
     */
    function onMouseUp() {
        isDragging = false;
        
        // Remove dragging class
        adContainer.classList.remove('dragging');
        
        document.removeEventListener('mousemove', onMouseMove);
        document.removeEventListener('mouseup', onMouseUp);
        
        // Save the new position
        savePlayerSettings();
    }
    
    /**
     * Touch end handler for dragging
     */
    function onTouchEnd() {
        isDragging = false;
        
        // Remove dragging class
        adContainer.classList.remove('dragging');
        
        document.removeEventListener('touchmove', onTouchMove);
        document.removeEventListener('touchend', onTouchEnd);
        
        // Save the new position
        savePlayerSettings();
    }
    
    /**
     * Set up resize functionality for the player
     */
    function setupResizeFunctionality(resizeHandle) {
        let isResizing = false;
        let originalWidth, originalHeight, originalX, originalY;
        
        resizeHandle.addEventListener('mousedown', function(e) {
            e.preventDefault();
            
            isResizing = true;
            originalWidth = adContainer.offsetWidth;
            originalHeight = adContainer.offsetHeight;
            originalX = e.clientX;
            originalY = e.clientY;
            
            // Add resizing class for visual feedback
            adContainer.classList.add('resizing');
            
            document.addEventListener('mousemove', onResizeMove);
            document.addEventListener('mouseup', onResizeUp);
        });
        
        // Touch support for mobile devices
        resizeHandle.addEventListener('touchstart', function(e) {
            e.preventDefault();
            
            isResizing = true;
            const touch = e.touches[0];
            originalWidth = adContainer.offsetWidth;
            originalHeight = adContainer.offsetHeight;
            originalX = touch.clientX;
            originalY = touch.clientY;
            
            // Add resizing class for visual feedback
            adContainer.classList.add('resizing');
            
            document.addEventListener('touchmove', onResizeTouchMove);
            document.addEventListener('touchend', onResizeTouchEnd);
        });
        
        function onResizeMove(e) {
            if (!isResizing) return;
            
            const deltaX = e.clientX - originalX;
            const deltaY = e.clientY - originalY;
            
            const newWidth = Math.max(200, originalWidth + deltaX);
            const newHeight = Math.max(150, originalHeight + deltaY);
            
            adContainer.style.width = newWidth + 'px';
            adContainer.style.height = newHeight + 'px';
        }
        
        function onResizeTouchMove(e) {
            if (!isResizing) return;
            
            const touch = e.touches[0];
            const deltaX = touch.clientX - originalX;
            const deltaY = touch.clientY - originalY;
            
            const newWidth = Math.max(200, originalWidth + deltaX);
            const newHeight = Math.max(150, originalHeight + deltaY);
            
            adContainer.style.width = newWidth + 'px';
            adContainer.style.height = newHeight + 'px';
        }
        
        function onResizeUp() {
            isResizing = false;
            
            // Remove resizing class
            adContainer.classList.remove('resizing');
            
            document.removeEventListener('mousemove', onResizeMove);
            document.removeEventListener('mouseup', onResizeUp);
            
            // Save the new size
            savePlayerSettings();
        }
        
        function onResizeTouchEnd() {
            isResizing = false;
            
            // Remove resizing class
            adContainer.classList.remove('resizing');
            
            document.removeEventListener('touchmove', onResizeTouchMove);
            document.removeEventListener('touchend', onResizeTouchEnd);
            
            // Save the new size
            savePlayerSettings();
        }
    }
    
    /**
     * Load the next video in the playlist
     */
    function loadNextAd() {
        if (adVideos.length === 0) return;
        
        // If we've successfully used the PHP server before, use it again
        if (config.usePHPServer) {
            const videoPath = config.phpServerPath + adVideos[currentAdIndex];
            console.log('Using PHP server path (already known to work):', videoPath);
            videoElement.src = videoPath;
            videoElement.load();
            
            // Check if autoplay is enabled
            if (config.autoplay) {
                console.log('Attempting autoplay with PHP server...');
                const playPromise = videoElement.play();
                
                if (playPromise !== undefined) {
                    playPromise.then(() => {
                        console.log('Autoplay successful!');
                    }).catch(error => {
                        console.error('Autoplay failed:', error);
                        // Try playing without sound as a fallback (to comply with browser policies)
                        console.log('Trying autoplay without sound...');
                        videoElement.muted = true;
                        videoElement.play().catch(e => {
                            console.error('Autoplay still failed even with mute:', e);
                        });
                    });
                }
            }
            
            // Move to the next video for the next time
            currentAdIndex = (currentAdIndex + 1) % adVideos.length;
            return;
        }
        
        // Otherwise try the configured basePath first
        const videoPath = config.basePath + adVideos[currentAdIndex];
        console.log('Attempting to load video from:', videoPath);
        
        // Check if file exists
        fetch(videoPath)
            .then(response => {
                if (!response.ok) {
                    console.error('Video file not found or inaccessible:', videoPath);
                    console.error('Status:', response.status);
                    // Try alternate path
                    tryAlternatePaths();
                } else {
                    console.log('Video file found, loading:', videoPath);
                    videoElement.src = videoPath;
                    videoElement.load();
                    
                    // Check if autoplay is enabled
                    if (config.autoplay) {
                        console.log('Attempting autoplay...');
                        const playPromise = videoElement.play();
                        
                        if (playPromise !== undefined) {
                            playPromise.then(() => {
                                console.log('Autoplay successful!');
                            }).catch(error => {
                                console.error('Autoplay failed:', error);
                                // Try playing without sound as a fallback (to comply with browser policies)
                                console.log('Trying autoplay without sound...');
                                videoElement.muted = true;
                                videoElement.play().catch(e => {
                                    console.error('Autoplay still failed even with mute:', e);
                                });
                            });
                        }
                    }
                    
                    // Move to the next video for the next time
                    currentAdIndex = (currentAdIndex + 1) % adVideos.length;
                }
            })
            .catch(error => {
                console.error('Error checking video file:', error);
                tryAlternatePaths();
            });
    }
    
    /**
     * Try different path combinations to find the video files
     */
    function tryAlternatePaths() {
        console.log('Trying alternate paths...');
        
        // Try the PHP server approach first before trying other paths
        const phpServerPath = config.phpServerPath + adVideos[currentAdIndex];
        console.log('Trying PHP server path:', phpServerPath);
        
        // Try the PHP server first
        fetch(phpServerPath, { method: 'HEAD' })
            .then(response => {
                if (response.ok) {
                    console.log('PHP server can serve the video, using it!');
                    // Load the video with this path
                    videoElement.src = phpServerPath;
                    videoElement.load();
                    
                    if (config.autoplay) {
                        videoElement.muted = true; // Ensure muted for autoplay
                        videoElement.play().catch(e => console.log('Autoplay still failed with PHP server:', e));
                    }
                    
                    // Remember this approach for next time
                    config.usePHPServer = true;
                    
                    // Move to the next video for next time
                    currentAdIndex = (currentAdIndex + 1) % adVideos.length;
                } else {
                    console.log('PHP server cannot serve the video, trying direct paths');
                    tryDirectPaths();
                }
            })
            .catch(error => {
                console.error('Error checking PHP server path:', error);
                tryDirectPaths();
            });
    }
    
    /**
     * Try direct path approaches after PHP server fails
     */
    function tryDirectPaths() {
        // Array of possible paths to try (from most likely to least likely)
        const pathsToTry = [
            // Absolute paths
            '/tvdisplay/adds/' + adVideos[currentAdIndex],
            '/slipp/tvdisplay/adds/' + adVideos[currentAdIndex],
            '/xampp1/htdocs/tvdisplay/adds/' + adVideos[currentAdIndex],
            '/xampp1/htdocs/slipp/tvdisplay/adds/' + adVideos[currentAdIndex],
            
            // Relative paths
            'adds/' + adVideos[currentAdIndex],
            './adds/' + adVideos[currentAdIndex],
            '../adds/' + adVideos[currentAdIndex],
            '../../tvdisplay/adds/' + adVideos[currentAdIndex]
        ];
        
        console.log('Trying these direct paths:', pathsToTry);
        
        // Try each path sequentially
        let pathIndex = 0;
        
        function tryNextPath() {
            if (pathIndex >= pathsToTry.length) {
                console.log('All paths failed, using fallback method');
                useFallbackVideo();
                return;
            }
            
            const path = pathsToTry[pathIndex];
            console.log('Trying path:', path);
            
            // Use fetch to check if the file exists
            fetch(path, { method: 'HEAD' })
                .then(response => {
                    if (response.ok) {
                        console.log('Found accessible path:', path);
                        // Update config path for future loads
                        config.basePath = path.replace(adVideos[currentAdIndex], '');
                        console.log('Updated basePath to:', config.basePath);
                        
                        // Load the video with this path
                        videoElement.src = path;
                        videoElement.load();
                        
                        if (config.autoplay) {
                            videoElement.muted = true; // Ensure muted for autoplay
                            videoElement.play().catch(e => console.log('Autoplay still failed with new path:', e));
                        }
                        
                        // Move to the next video for next time
                        currentAdIndex = (currentAdIndex + 1) % adVideos.length;
                    } else {
                        // Try the next path
                        pathIndex++;
                        tryNextPath();
                    }
                })
                .catch(error => {
                    console.error('Error checking path:', path, error);
                    // Try the next path
                    pathIndex++;
                    tryNextPath();
                });
        }
        
        // Start trying paths
        tryNextPath();
    }
    
    /**
     * Last resort fallback - use base64 encoded placeholder or data URI
     */
    function useFallbackVideo() {
        console.log('Using fallback video method');
        
        // Show error message 
        const errorMessage = adContainer.querySelector('.video-error-message');
        if (errorMessage) {
            errorMessage.style.display = 'block';
            errorMessage.textContent = 'Could not load videos. Using direct embed method.';
            
            // Hide error message after 5 seconds
            setTimeout(() => {
                errorMessage.style.display = 'none';
            }, 5000);
        }
        
        // Create download links for an easy fix
        createDownloadHelpLinks();
        
        // Create extremely simple fallback video content
        const fallbackContent = document.createElement('div');
        fallbackContent.style.width = '100%';
        fallbackContent.style.height = 'calc(100% - 30px)';
        fallbackContent.style.backgroundColor = '#000';
        fallbackContent.style.color = '#fff';
        fallbackContent.style.display = 'flex';
        fallbackContent.style.flexDirection = 'column';
        fallbackContent.style.justifyContent = 'center';
        fallbackContent.style.alignItems = 'center';
        fallbackContent.style.padding = '20px';
        fallbackContent.style.textAlign = 'center';
        fallbackContent.innerHTML = `
            <div style="margin-bottom: 10px;">Video Ad ${currentAdIndex + 1} of ${adVideos.length}</div>
            <div style="font-size: 12px; margin-bottom: 20px;">File not accessible: ${adVideos[currentAdIndex]}</div>
            <div style="border: 1px solid #FFD700; padding: 8px; border-radius: 4px; cursor: pointer;" class="fix-paths-btn">
                Fix Path Issues
            </div>
        `;
        
        // Replace video with fallback content
        videoElement.style.display = 'none';
        adContainer.insertBefore(fallbackContent, videoElement.nextSibling);
        
        // Add click handler to fix paths button
        const fixPathsBtn = fallbackContent.querySelector('.fix-paths-btn');
        if (fixPathsBtn) {
            fixPathsBtn.addEventListener('click', function() {
                showPathFixDialog();
            });
        }
        
        // Move to the next video in a few seconds
        setTimeout(() => {
            // Remove fallback content
            if (adContainer.contains(fallbackContent)) {
                adContainer.removeChild(fallbackContent);
            }
            
            // Show video element again
            videoElement.style.display = '';
            
            // Try next video
            currentAdIndex = (currentAdIndex + 1) % adVideos.length;
            loadNextAd();
        }, 5000);
    }
    
    /**
     * Create download help links to assist user in fixing the issue
     */
    function createDownloadHelpLinks() {
        // Only create if not already there
        if (document.getElementById('video-fix-help')) return;
        
        const helpPanel = document.createElement('div');
        helpPanel.id = 'video-fix-help';
        helpPanel.style.position = 'fixed';
        helpPanel.style.bottom = '10px';
        helpPanel.style.right = '10px';
        helpPanel.style.backgroundColor = 'rgba(0, 0, 0, 0.8)';
        helpPanel.style.color = 'white';
        helpPanel.style.padding = '15px';
        helpPanel.style.borderRadius = '5px';
        helpPanel.style.zIndex = '9999';
        helpPanel.style.maxWidth = '400px';
        helpPanel.style.boxShadow = '0 0 10px rgba(0, 0, 0, 0.5)';
        helpPanel.style.fontFamily = 'Arial, sans-serif';
        
        helpPanel.innerHTML = `
            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                <strong>Video Loading Helper</strong>
                <span style="cursor: pointer;" id="close-help-panel">✖</span>
            </div>
            <p style="margin-bottom: 10px; font-size: 14px;">
                The video files could not be loaded. Please check that these files exist in the correct location:
            </p>
            <ul style="margin-bottom: 15px; padding-left: 20px; font-size: 12px;">
                ${adVideos.map(file => `<li>C:\\xampp1\\htdocs\\tvdisplay\\adds\\${file}</li>`).join('')}
            </ul>
            <button style="background: #FFD700; color: black; border: none; padding: 5px 10px; cursor: pointer; width: 100%;" id="path-fix-btn">
                Show Path Fix Dialog
            </button>
        `;
        
        document.body.appendChild(helpPanel);
        
        // Add event listeners
        document.getElementById('close-help-panel').addEventListener('click', function() {
            helpPanel.style.display = 'none';
        });
        
        document.getElementById('path-fix-btn').addEventListener('click', function() {
            showPathFixDialog();
        });
    }
    
    /**
     * Show dialog to fix path issues
     */
    function showPathFixDialog() {
        // Only create if not already there
        if (document.getElementById('path-fix-dialog')) {
            document.getElementById('path-fix-dialog').style.display = 'flex';
            return;
        }
        
        const dialog = document.createElement('div');
        dialog.id = 'path-fix-dialog';
        dialog.style.position = 'fixed';
        dialog.style.top = '0';
        dialog.style.left = '0';
        dialog.style.right = '0';
        dialog.style.bottom = '0';
        dialog.style.backgroundColor = 'rgba(0, 0, 0, 0.8)';
        dialog.style.display = 'flex';
        dialog.style.justifyContent = 'center';
        dialog.style.alignItems = 'center';
        dialog.style.zIndex = '10000';
        
        const content = document.createElement('div');
        content.style.backgroundColor = '#fff';
        content.style.padding = '20px';
        content.style.borderRadius = '5px';
        content.style.maxWidth = '500px';
        content.style.width = '90%';
        
        content.innerHTML = `
            <h2 style="margin-top: 0; color: #333;">Fix Video Paths</h2>
            <p>Enter the correct path to your video files:</p>
            <div style="margin-bottom: 15px;">
                <label for="video-path-input" style="display: block; margin-bottom: 5px; font-weight: bold;">Video Path:</label>
                <input type="text" id="video-path-input" style="width: 100%; padding: 8px; box-sizing: border-box;" 
                    value="${config.basePath}" placeholder="/tvdisplay/adds/">
            </div>
            <p style="font-size: 14px; margin-bottom: 15px;">Tips:
                <ul style="font-size: 13px; margin-top: 5px; padding-left: 20px;">
                    <li>Try absolute paths starting with a slash (/)</li>
                    <li>Example: /tvdisplay/adds/</li>
                    <li>Check that your video files exist at C:\\xampp1\\htdocs\\tvdisplay\\adds\\</li>
                </ul>
            </p>
            <div style="display: flex; justify-content: space-between;">
                <button id="cancel-path-fix" style="padding: 8px 15px; background: #ccc; border: none; border-radius: 3px; cursor: pointer;">Cancel</button>
                <button id="apply-path-fix" style="padding: 8px 15px; background: #FFD700; border: none; border-radius: 3px; cursor: pointer;">Apply & Test</button>
            </div>
        `;
        
        dialog.appendChild(content);
        document.body.appendChild(dialog);
        
        // Add event listeners
        document.getElementById('cancel-path-fix').addEventListener('click', function() {
            dialog.style.display = 'none';
        });
        
        document.getElementById('apply-path-fix').addEventListener('click', function() {
            const newPath = document.getElementById('video-path-input').value;
            // Make sure path ends with slash
            config.basePath = newPath.endsWith('/') ? newPath : newPath + '/';
            console.log('Updated path to:', config.basePath);
            
            // Test with this path
            loadNextAd();
            
            // Hide dialog
            dialog.style.display = 'none';
        });
    }
    
    /**
     * Toggle play/pause state of the video
     */
    function togglePlayPause() {
        if (videoElement.paused) {
            videoElement.play();
        } else {
            videoElement.pause();
        }
    }
    
    /**
     * Toggle mute/unmute state of the video
     */
    function toggleMute() {
        videoElement.muted = !videoElement.muted;
        
        const muteBtn = document.querySelector('#ads-player-header button:nth-child(2)');
        if (videoElement.muted) {
            muteBtn.innerHTML = '🔇';
        } else {
            muteBtn.innerHTML = '🔊';
        }
        
        // Save the mute state
        savePlayerSettings();
    }
    
    /**
     * Hide the ads player
     */
    function hideAdsPlayer() {
        if (adContainer) {
            videoElement.pause();
            adContainer.style.display = 'none';
        }
    }
    
    /**
     * Show the ads player
     */
    function showAdsPlayer() {
        if (adContainer) {
            adContainer.style.display = 'block';
            if (config.autoplay) {
                videoElement.play().catch(e => console.log('Auto-play prevented:', e));
            }
        }
    }
    
    // Create the ads player when the document is fully loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', createAdsPlayer);
    } else {
        createAdsPlayer();
    }
    
    // Make functions available globally
    window.adsPlayer = {
        show: showAdsPlayer,
        hide: hideAdsPlayer,
        togglePlayPause: togglePlayPause,
        toggleMute: toggleMute,
        loadNextAd: loadNextAd
    };
})(); 