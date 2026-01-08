/**
 * Live Stream Player
 * A draggable, resizable video player for streaming live content using HLS.js
 */
(function() {
    // Configuration
    const config = {
        streamUrl: '', // Will be set when showing the player
        position: { x: 10, y: 10 },  // Default position
        size: { width: 320, height: 180 },  // Default size
        autoplay: true,
        muted: true,
        draggable: true,
        resizable: true,
        storageKey: 'liveStreamPlayerSettings',
        zIndex: 9999
    };

    // DOM Elements
    let playerContainer = null;
    let videoElement = null;
    let controlsContainer = null;
    let playPauseButton = null;
    let muteButton = null;
    let fullscreenButton = null;
    let closeButton = null;
    let resizeHandle = null;
    let loadingIndicator = null;
    let errorDisplay = null;
    let hls = null;

    // State
    let isPlaying = false;
    let isMuted = config.muted;
    let isDragging = false;
    let isResizing = false;
    let dragOffset = { x: 0, y: 0 };
    let resizeStartSize = { width: 0, height: 0 };
    let resizeStartPos = { x: 0, y: 0 };
    let isVisible = false;

    /**
     * Load settings from local storage
     */
    function loadSettings() {
        try {
            const savedSettings = localStorage.getItem(config.storageKey);
            if (savedSettings) {
                const parsedSettings = JSON.parse(savedSettings);
                config.position = parsedSettings.position || config.position;
                config.size = parsedSettings.size || config.size;
                config.muted = parsedSettings.muted !== undefined ? parsedSettings.muted : config.muted;
            }
        } catch (error) {
            console.error('Error loading live stream player settings:', error);
        }
    }

    /**
     * Save settings to local storage
     */
    function saveSettings() {
        try {
            const settings = {
                position: config.position,
                size: config.size,
                muted: isMuted
            };
            localStorage.setItem(config.storageKey, JSON.stringify(settings));
        } catch (error) {
            console.error('Error saving live stream player settings:', error);
        }
    }

    /**
     * Create player elements
     */
    function createPlayerElements() {
        // Container
        playerContainer = document.createElement('div');
        playerContainer.className = 'live-stream-player';
        playerContainer.style.position = 'fixed';
        playerContainer.style.top = config.position.y + 'px';
        playerContainer.style.left = config.position.x + 'px';
        playerContainer.style.width = config.size.width + 'px';
        playerContainer.style.height = config.size.height + 'px';
        playerContainer.style.backgroundColor = '#000';
        playerContainer.style.border = '1px solid #444';
        playerContainer.style.borderRadius = '4px';
        playerContainer.style.boxShadow = '0 0 10px rgba(0, 0, 0, 0.5)';
        playerContainer.style.overflow = 'hidden';
        playerContainer.style.zIndex = config.zIndex;
        playerContainer.style.display = 'none'; // Hidden by default

        // Video element
        videoElement = document.createElement('video');
        videoElement.style.width = '100%';
        videoElement.style.height = '100%';
        videoElement.style.objectFit = 'contain';
        videoElement.muted = config.muted;
        isMuted = config.muted;

        // Loading indicator
        loadingIndicator = document.createElement('div');
        loadingIndicator.className = 'live-stream-loading';
        loadingIndicator.style.position = 'absolute';
        loadingIndicator.style.top = '0';
        loadingIndicator.style.left = '0';
        loadingIndicator.style.width = '100%';
        loadingIndicator.style.height = '100%';
        loadingIndicator.style.display = 'flex';
        loadingIndicator.style.alignItems = 'center';
        loadingIndicator.style.justifyContent = 'center';
        loadingIndicator.style.backgroundColor = 'rgba(0, 0, 0, 0.7)';
        loadingIndicator.style.color = '#fff';
        loadingIndicator.style.fontSize = '14px';
        loadingIndicator.innerHTML = 'Loading stream...';

        // Error display
        errorDisplay = document.createElement('div');
        errorDisplay.className = 'live-stream-error';
        errorDisplay.style.position = 'absolute';
        errorDisplay.style.top = '0';
        errorDisplay.style.left = '0';
        errorDisplay.style.width = '100%';
        errorDisplay.style.height = '100%';
        errorDisplay.style.display = 'none';
        errorDisplay.style.alignItems = 'center';
        errorDisplay.style.justifyContent = 'center';
        errorDisplay.style.backgroundColor = 'rgba(0, 0, 0, 0.7)';
        errorDisplay.style.color = '#ff5555';
        errorDisplay.style.padding = '10px';
        errorDisplay.style.textAlign = 'center';
        errorDisplay.style.fontSize = '14px';

        // Controls container
        controlsContainer = document.createElement('div');
        controlsContainer.className = 'live-stream-controls';
        controlsContainer.style.position = 'absolute';
        controlsContainer.style.bottom = '0';
        controlsContainer.style.left = '0';
        controlsContainer.style.width = '100%';
        controlsContainer.style.padding = '5px';
        controlsContainer.style.backgroundColor = 'rgba(0, 0, 0, 0.7)';
        controlsContainer.style.display = 'flex';
        controlsContainer.style.alignItems = 'center';
        controlsContainer.style.justifyContent = 'space-between';
        controlsContainer.style.opacity = '0';
        controlsContainer.style.transition = 'opacity 0.3s ease';

        // Control buttons
        playPauseButton = document.createElement('button');
        playPauseButton.className = 'live-stream-button play';
        playPauseButton.innerHTML = '▶';
        playPauseButton.style.background = 'none';
        playPauseButton.style.border = 'none';
        playPauseButton.style.color = '#fff';
        playPauseButton.style.fontSize = '16px';
        playPauseButton.style.cursor = 'pointer';
        playPauseButton.style.padding = '5px';

        muteButton = document.createElement('button');
        muteButton.className = 'live-stream-button mute';
        muteButton.innerHTML = isMuted ? '🔇' : '🔊';
        muteButton.style.background = 'none';
        muteButton.style.border = 'none';
        muteButton.style.color = '#fff';
        muteButton.style.fontSize = '16px';
        muteButton.style.cursor = 'pointer';
        muteButton.style.padding = '5px';

        fullscreenButton = document.createElement('button');
        fullscreenButton.className = 'live-stream-button fullscreen';
        fullscreenButton.innerHTML = '⛶';
        fullscreenButton.style.background = 'none';
        fullscreenButton.style.border = 'none';
        fullscreenButton.style.color = '#fff';
        fullscreenButton.style.fontSize = '16px';
        fullscreenButton.style.cursor = 'pointer';
        fullscreenButton.style.padding = '5px';

        closeButton = document.createElement('button');
        closeButton.className = 'live-stream-button close';
        closeButton.innerHTML = '×';
        closeButton.style.background = 'none';
        closeButton.style.border = 'none';
        closeButton.style.color = '#fff';
        closeButton.style.fontSize = '20px';
        closeButton.style.cursor = 'pointer';
        closeButton.style.padding = '5px';
        closeButton.style.position = 'absolute';
        closeButton.style.top = '0';
        closeButton.style.right = '0';
        closeButton.style.zIndex = '2';

        // Left controls group
        const leftControls = document.createElement('div');
        leftControls.appendChild(playPauseButton);
        leftControls.appendChild(muteButton);

        // Right controls group
        const rightControls = document.createElement('div');
        rightControls.appendChild(fullscreenButton);

        controlsContainer.appendChild(leftControls);
        controlsContainer.appendChild(rightControls);

        // Resize handle
        if (config.resizable) {
            resizeHandle = document.createElement('div');
            resizeHandle.className = 'live-stream-resize-handle';
            resizeHandle.style.position = 'absolute';
            resizeHandle.style.bottom = '0';
            resizeHandle.style.right = '0';
            resizeHandle.style.width = '15px';
            resizeHandle.style.height = '15px';
            resizeHandle.style.cursor = 'nwse-resize';
            resizeHandle.style.background = 'linear-gradient(135deg, transparent 70%, rgba(255, 255, 255, 0.7) 70%)';
            resizeHandle.style.zIndex = '3';
        }

        // Append elements
        playerContainer.appendChild(videoElement);
        playerContainer.appendChild(loadingIndicator);
        playerContainer.appendChild(errorDisplay);
        playerContainer.appendChild(controlsContainer);
        playerContainer.appendChild(closeButton);
        if (resizeHandle) {
            playerContainer.appendChild(resizeHandle);
        }

        document.body.appendChild(playerContainer);
    }

    /**
     * Initialize HLS.js
     */
    function initHls() {
        if (Hls.isSupported()) {
            hls = new Hls({
                maxBufferLength: 30,
                maxMaxBufferLength: 600,
                liveSyncDuration: 3,
                liveMaxLatencyDuration: 10,
                liveDurationInfinity: true
            });
            
            hls.on(Hls.Events.MEDIA_ATTACHED, function() {
                console.log('Live stream: Media attached');
            });
            
            hls.on(Hls.Events.MANIFEST_PARSED, function() {
                console.log('Live stream: Manifest parsed');
                loadingIndicator.style.display = 'none';
                if (config.autoplay) {
                    videoElement.play().catch(e => {
                        console.warn('Live stream: Autoplay prevented:', e);
                        isPlaying = false;
                        playPauseButton.innerHTML = '▶';
                    });
                }
            });
            
            hls.on(Hls.Events.ERROR, function(event, data) {
                if (data.fatal) {
                    switch(data.type) {
                        case Hls.ErrorTypes.NETWORK_ERROR:
                            showError('Network error: Please check your connection');
                            console.error('Live stream: Fatal network error', data);
                            hls.startLoad(); // Try to recover
                            break;
                        case Hls.ErrorTypes.MEDIA_ERROR:
                            showError('Media error: Please try again');
                            console.error('Live stream: Fatal media error', data);
                            hls.recoverMediaError(); // Try to recover
                            break;
                        default:
                            showError('Error loading stream');
                            console.error('Live stream: Fatal error', data);
                            break;
                    }
                }
            });
            
            hls.attachMedia(videoElement);
        } else if (videoElement.canPlayType('application/vnd.apple.mpegurl')) {
            // For Safari which has native HLS support
            videoElement.src = config.streamUrl;
        } else {
            showError('Your browser does not support HLS streaming');
        }
    }

    /**
     * Extract YouTube video ID from URL
     * @param {string} url - YouTube URL
     * @returns {string|null} Video ID or null if not found
     */
    function extractYouTubeId(url) {
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

    /**
     * Check if URL is a YouTube URL
     * @param {string} url - URL to check
     * @returns {boolean} True if YouTube URL
     */
    function isYouTubeUrl(url) {
        return /(?:youtube\.com|youtu\.be)/.test(url);
    }

    /**
     * Load a stream
     * @param {string} url - The stream URL
     */
    function loadStream(url) {
        config.streamUrl = url || config.streamUrl;

        if (!config.streamUrl) {
            showError('No stream URL provided');
            return;
        }

        loadingIndicator.style.display = 'flex';
        errorDisplay.style.display = 'none';

        // Check if it's a YouTube URL
        if (isYouTubeUrl(config.streamUrl)) {
            loadYouTubeStream(config.streamUrl);
        } else {
            // Handle regular HLS streams
            if (hls) {
                hls.destroy();
                initHls();
                hls.loadSource(config.streamUrl);
            } else {
                initHls();
                hls.loadSource(config.streamUrl);
            }
        }
    }

    /**
     * Load YouTube stream using iframe
     * @param {string} url - YouTube URL
     */
    function loadYouTubeStream(url) {
        const videoId = extractYouTubeId(url);

        if (!videoId) {
            showError('Invalid YouTube URL');
            return;
        }

        // Hide the video element and create iframe
        videoElement.style.display = 'none';

        // Remove existing iframe if any
        const existingIframe = playerContainer.querySelector('.youtube-iframe');
        if (existingIframe) {
            existingIframe.remove();
        }

        // Create YouTube iframe
        const iframe = document.createElement('iframe');
        iframe.className = 'youtube-iframe';
        iframe.src = `https://www.youtube.com/embed/${videoId}?autoplay=1&mute=1&controls=1&rel=0&modestbranding=1&iv_load_policy=3`;
        iframe.style.width = '100%';
        iframe.style.height = '100%';
        iframe.style.border = 'none';
        iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
        iframe.allowFullscreen = true;

        // Add proper load event handling for YouTube iframe
        iframe.onload = function() {
            console.log('YouTube iframe loaded successfully');
            // Hide loading indicator immediately when iframe loads
            loadingIndicator.style.display = 'none !important';
            loadingIndicator.style.visibility = 'hidden !important';
            loadingIndicator.style.opacity = '0 !important';
            loadingIndicator.classList.add('force-hidden');

            // Add class to player container to trigger CSS hiding
            playerContainer.classList.add('youtube-loaded');

            errorDisplay.style.display = 'none';
        };

        iframe.onerror = function() {
            console.error('YouTube iframe failed to load');
            showError('Failed to load YouTube stream');
        };

        // Insert iframe before controls
        playerContainer.insertBefore(iframe, controlsContainer);

        // IMMEDIATE: Add CSS class and force hide loading indicator
        playerContainer.classList.add('youtube-loaded');
        loadingIndicator.classList.add('force-hidden');
        loadingIndicator.style.display = 'none !important';
        loadingIndicator.style.visibility = 'hidden !important';
        loadingIndicator.style.opacity = '0 !important';

        // Fallback: Hide loading indicator after 3 seconds if onload doesn't fire
        const fallbackTimer = setTimeout(() => {
            console.log('Fallback: Hiding loading indicator after timeout');
            loadingIndicator.style.display = 'none';
        }, 3000);

        // Clear fallback timer if iframe loads properly
        iframe.addEventListener('load', () => {
            clearTimeout(fallbackTimer);
        });

        // Update controls for YouTube
        updateControlsForYouTube(iframe);

        // Additional safety check: Monitor iframe and hide loading indicator
        const checkIframeLoaded = setInterval(() => {
            const iframeInDOM = playerContainer.querySelector('.youtube-iframe');
            if (iframeInDOM && iframeInDOM.contentWindow) {
                try {
                    // If we can access the iframe (even if cross-origin), it's likely loaded
                    console.log('YouTube iframe detected in DOM, hiding loading indicator');
                    loadingIndicator.style.display = 'none';
                    clearInterval(checkIframeLoaded);
                } catch (e) {
                    // Cross-origin error is expected, but iframe is there
                    console.log('YouTube iframe cross-origin detected (normal), hiding loading indicator');
                    loadingIndicator.style.display = 'none';
                    clearInterval(checkIframeLoaded);
                }
            }
        }, 500);

        // Clear the check after 10 seconds to prevent infinite checking
        setTimeout(() => {
            clearInterval(checkIframeLoaded);
        }, 10000);

        console.log('YouTube stream loading:', videoId);
    }

    /**
     * Update controls for YouTube iframe
     * @param {HTMLIFrameElement} iframe - YouTube iframe
     */
    function updateControlsForYouTube(iframe) {
        // Hide play/pause and mute buttons since YouTube iframe has its own controls
        playPauseButton.style.display = 'none';
        muteButton.style.display = 'none';

        // Update fullscreen button to work with iframe
        fullscreenButton.onclick = function() {
            if (iframe.requestFullscreen) {
                iframe.requestFullscreen();
            } else if (iframe.webkitRequestFullscreen) {
                iframe.webkitRequestFullscreen();
            } else if (iframe.msRequestFullscreen) {
                iframe.msRequestFullscreen();
            }
        };
    }

    /**
     * Show error message
     * @param {string} message - Error message to display
     */
    function showError(message) {
        loadingIndicator.style.display = 'none';
        errorDisplay.innerHTML = message;
        errorDisplay.style.display = 'flex';
    }

    /**
     * Add event listeners
     */
    function addEventListeners() {
        // Mouse events for dragging
        if (config.draggable) {
            playerContainer.addEventListener('mousedown', function(e) {
                // Ignore if clicking on a control or the video element directly
                if (e.target === videoElement || 
                    e.target === playPauseButton || 
                    e.target === muteButton || 
                    e.target === fullscreenButton || 
                    e.target === closeButton ||
                    e.target === resizeHandle) {
                    return;
                }
                
                isDragging = true;
                dragOffset.x = e.clientX - playerContainer.offsetLeft;
                dragOffset.y = e.clientY - playerContainer.offsetTop;
                playerContainer.style.cursor = 'grabbing';
            });
        }
        
        // Resize events
        if (config.resizable && resizeHandle) {
            resizeHandle.addEventListener('mousedown', function(e) {
                isResizing = true;
                resizeStartSize.width = playerContainer.offsetWidth;
                resizeStartSize.height = playerContainer.offsetHeight;
                resizeStartPos.x = e.clientX;
                resizeStartPos.y = e.clientY;
                e.preventDefault();
                e.stopPropagation();
            });
        }
        
        // Global mouse events
        document.addEventListener('mousemove', function(e) {
            if (isDragging) {
                const newLeft = e.clientX - dragOffset.x;
                const newTop = e.clientY - dragOffset.y;
                
                // Constrain to window
                const maxLeft = window.innerWidth - playerContainer.offsetWidth;
                const maxTop = window.innerHeight - playerContainer.offsetHeight;
                
                playerContainer.style.left = Math.max(0, Math.min(newLeft, maxLeft)) + 'px';
                playerContainer.style.top = Math.max(0, Math.min(newTop, maxTop)) + 'px';
                
                config.position.x = parseInt(playerContainer.style.left);
                config.position.y = parseInt(playerContainer.style.top);
            }
            
            if (isResizing) {
                const deltaX = e.clientX - resizeStartPos.x;
                const deltaY = e.clientY - resizeStartPos.y;
                
                const newWidth = Math.max(160, resizeStartSize.width + deltaX);
                const newHeight = Math.max(90, resizeStartSize.height + deltaY);
                
                playerContainer.style.width = newWidth + 'px';
                playerContainer.style.height = newHeight + 'px';
                
                config.size.width = newWidth;
                config.size.height = newHeight;
            }
        });
        
        document.addEventListener('mouseup', function() {
            if (isDragging || isResizing) {
                isDragging = false;
                isResizing = false;
                playerContainer.style.cursor = '';
                saveSettings();
            }
        });
        
        // Show/hide controls on hover
        playerContainer.addEventListener('mouseenter', function() {
            controlsContainer.style.opacity = '1';
        });
        
        playerContainer.addEventListener('mouseleave', function() {
            if (!videoElement.paused) {
                controlsContainer.style.opacity = '0';
            }
        });
        
        // Button events
        playPauseButton.addEventListener('click', function() {
            if (videoElement.paused) {
                videoElement.play().then(() => {
                    isPlaying = true;
                    playPauseButton.innerHTML = '⏸';
                }).catch(e => {
                    console.error('Live stream: Play error:', e);
                });
            } else {
                videoElement.pause();
                isPlaying = false;
                playPauseButton.innerHTML = '▶';
            }
        });
        
        muteButton.addEventListener('click', function() {
            videoElement.muted = !videoElement.muted;
            isMuted = videoElement.muted;
            muteButton.innerHTML = isMuted ? '🔇' : '🔊';
            saveSettings();
        });
        
        fullscreenButton.addEventListener('click', function() {
            if (videoElement.requestFullscreen) {
                videoElement.requestFullscreen();
            } else if (videoElement.webkitRequestFullscreen) {
                videoElement.webkitRequestFullscreen();
            } else if (videoElement.msRequestFullscreen) {
                videoElement.msRequestFullscreen();
            }
        });
        
        closeButton.addEventListener('click', hidePlayer);
        
        // Video events
        videoElement.addEventListener('play', function() {
            isPlaying = true;
            playPauseButton.innerHTML = '⏸';
        });
        
        videoElement.addEventListener('pause', function() {
            isPlaying = false;
            playPauseButton.innerHTML = '▶';
        });
        
        videoElement.addEventListener('error', function(e) {
            console.error('Live stream: Video error:', e);
            showError('Error playing video');
        });
    }

    /**
     * Initialize player
     */
    function initPlayer() {
        loadSettings();
        createPlayerElements();
        addEventListeners();
    }

    /**
     * Show the player
     * @param {string} url - Stream URL
     */
    function showPlayer(url) {
        if (!playerContainer) {
            initPlayer();
        }

        if (!url && !config.streamUrl) {
            console.error('Live stream: No URL provided');
            return;
        }

        playerContainer.style.display = 'block';
        isVisible = true;

        // For YouTube URLs, add immediate loading indicator management
        if (url && isYouTubeUrl(url)) {
            console.log('Showing YouTube stream, managing loading indicator');

            // Show loading indicator initially
            loadingIndicator.style.display = 'flex';
            errorDisplay.style.display = 'none';

            // Hide loading indicator more aggressively for YouTube
            setTimeout(() => {
                console.log('Aggressively hiding loading indicator for YouTube');
                loadingIndicator.style.display = 'none';
            }, 1000);
        }

        loadStream(url);
    }

    /**
     * Hide the player
     */
    function hidePlayer() {
        if (playerContainer) {
            playerContainer.style.display = 'none';
            isVisible = false;

            // Pause the video when hiding
            if (videoElement && !videoElement.paused) {
                videoElement.pause();
            }

            // Remove YouTube iframe if present
            const youtubeIframe = playerContainer.querySelector('.youtube-iframe');
            if (youtubeIframe) {
                youtubeIframe.remove();
                // Show video element again
                videoElement.style.display = 'block';
                // Restore original controls
                playPauseButton.style.display = 'inline-block';
                muteButton.style.display = 'inline-block';
            }
        }
    }

    /**
     * Toggle player visibility
     * @param {string} url - Optional stream URL
     */
    function togglePlayer(url) {
        if (isVisible) {
            hidePlayer();
        } else {
            showPlayer(url);
        }
    }

    /**
     * Check if player is visible
     * @returns {boolean} True if player is visible
     */
    function isPlayerVisible() {
        return isVisible;
    }

    /**
     * Force hide loading indicator (utility function)
     */
    function forceHideLoadingIndicator() {
        if (loadingIndicator) {
            loadingIndicator.style.display = 'none';
            console.log('Loading indicator forcefully hidden');
        }
    }

    /**
     * Force show loading indicator (utility function)
     */
    function forceShowLoadingIndicator() {
        if (loadingIndicator) {
            loadingIndicator.style.display = 'flex';
            console.log('Loading indicator forcefully shown');
        }
    }

    // Initialize on load
    window.addEventListener('DOMContentLoaded', function() {
        // Create player elements but don't show yet
        initPlayer();
    });

    // Public API
    window.LiveStreamPlayer = {
        show: showPlayer,
        hide: hidePlayer,
        toggle: togglePlayer,
        isVisible: isPlayerVisible,
        forceHideLoading: forceHideLoadingIndicator,
        forceShowLoading: forceShowLoadingIndicator
    };

    // Multi-Player System
    window.MultiStreamPlayer = {
        players: new Map(),
        nextPlayerId: 1,

        /**
         * Create a new player instance
         * @param {string} streamUrl - YouTube stream URL
         * @param {object} options - Player options (position, size, label)
         * @returns {string} Player ID
         */
        createPlayer: function(streamUrl, options = {}) {
            const playerId = 'player_' + this.nextPlayerId++;

            const defaultOptions = {
                position: { x: 50 + (this.players.size * 50), y: 50 + (this.players.size * 50) },
                size: { width: 400, height: 225 },
                label: 'STREAM ' + this.players.size + 1,
                autoShow: true
            };

            const playerOptions = { ...defaultOptions, ...options };

            // Create player container
            const playerContainer = document.createElement('div');
            playerContainer.id = playerId;
            playerContainer.className = 'multi-stream-player';
            playerContainer.style.position = 'fixed';
            playerContainer.style.top = playerOptions.position.y + 'px';
            playerContainer.style.left = playerOptions.position.x + 'px';
            playerContainer.style.width = playerOptions.size.width + 'px';
            playerContainer.style.height = playerOptions.size.height + 'px';
            playerContainer.style.backgroundColor = '#000';
            playerContainer.style.border = '3px solid #FF5555';
            playerContainer.style.borderRadius = '5px';
            playerContainer.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.6)';
            playerContainer.style.overflow = 'hidden';
            playerContainer.style.zIndex = 9999 + this.players.size;

            // Create header
            const header = document.createElement('div');
            header.className = 'multi-stream-header';
            header.style.backgroundColor = '#222';
            header.style.color = 'white';
            header.style.padding = '5px 10px';
            header.style.fontSize = '12px';
            header.style.fontFamily = 'Arial, sans-serif';
            header.style.display = 'flex';
            header.style.justifyContent = 'space-between';
            header.style.alignItems = 'center';
            header.style.cursor = 'grab';
            header.style.userSelect = 'none';

            const label = document.createElement('span');
            label.textContent = playerOptions.label;
            header.appendChild(label);

            const closeButton = document.createElement('button');
            closeButton.innerHTML = '×';
            closeButton.style.background = 'none';
            closeButton.style.border = 'none';
            closeButton.style.color = 'white';
            closeButton.style.fontSize = '16px';
            closeButton.style.cursor = 'pointer';
            closeButton.style.padding = '0 5px';
            closeButton.onclick = () => this.removePlayer(playerId);
            header.appendChild(closeButton);

            // Create iframe for YouTube
            const iframe = document.createElement('iframe');
            iframe.className = 'multi-stream-iframe';
            iframe.style.width = '100%';
            iframe.style.height = 'calc(100% - 30px)';
            iframe.style.border = 'none';
            iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
            iframe.allowFullscreen = true;

            // Extract video ID and set source
            const videoId = this.extractYouTubeId(streamUrl);
            if (videoId) {
                iframe.src = `https://www.youtube.com/embed/${videoId}?autoplay=1&mute=1&controls=1&rel=0&modestbranding=1`;
            }

            // Assemble player
            playerContainer.appendChild(header);
            playerContainer.appendChild(iframe);
            document.body.appendChild(playerContainer);

            // Make draggable
            this.makeDraggable(playerContainer, header);

            // Store player info
            this.players.set(playerId, {
                container: playerContainer,
                iframe: iframe,
                streamUrl: streamUrl,
                options: playerOptions,
                visible: true
            });

            console.log('✅ Created multi-stream player:', playerId, 'for', streamUrl);
            return playerId;
        },

        /**
         * Remove a player
         * @param {string} playerId - Player ID to remove
         */
        removePlayer: function(playerId) {
            const player = this.players.get(playerId);
            if (player) {
                player.container.remove();
                this.players.delete(playerId);
                console.log('✅ Removed multi-stream player:', playerId);
            }
        },

        /**
         * Remove all players
         */
        removeAllPlayers: function() {
            this.players.forEach((player, playerId) => {
                player.container.remove();
            });
            this.players.clear();
            console.log('✅ Removed all multi-stream players');
        },

        /**
         * Extract YouTube video ID
         */
        extractYouTubeId: function(url) {
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
        },

        /**
         * Make player draggable
         */
        makeDraggable: function(container, header) {
            let isDragging = false;
            let dragOffset = { x: 0, y: 0 };

            header.addEventListener('mousedown', function(e) {
                isDragging = true;
                dragOffset.x = e.clientX - container.offsetLeft;
                dragOffset.y = e.clientY - container.offsetTop;
                header.style.cursor = 'grabbing';
            });

            document.addEventListener('mousemove', function(e) {
                if (isDragging) {
                    const newLeft = e.clientX - dragOffset.x;
                    const newTop = e.clientY - dragOffset.y;

                    const maxLeft = window.innerWidth - container.offsetWidth;
                    const maxTop = window.innerHeight - container.offsetHeight;

                    container.style.left = Math.max(0, Math.min(newLeft, maxLeft)) + 'px';
                    container.style.top = Math.max(0, Math.min(newTop, maxTop)) + 'px';
                }
            });

            document.addEventListener('mouseup', function() {
                if (isDragging) {
                    isDragging = false;
                    header.style.cursor = 'grab';
                }
            });
        },

        /**
         * Get all active players
         */
        getPlayers: function() {
            return Array.from(this.players.keys());
        },

        /**
         * Show/hide a specific player
         */
        togglePlayer: function(playerId) {
            const player = this.players.get(playerId);
            if (player) {
                const isVisible = player.container.style.display !== 'none';
                player.container.style.display = isVisible ? 'none' : 'block';
                player.visible = !isVisible;
                console.log('✅ Toggled player:', playerId, 'visible:', player.visible);
            }
        }
    };
})(); 