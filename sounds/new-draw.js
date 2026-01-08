/**
 * Audio notification for new draws
 * This uses the draw_notifications.wav file for sound alerts
 */

(function() {
    // Store the last used sound time to prevent multiple sounds in quick succession
    let lastSoundTime = 0;
    
    // Create audio element for notifications
    const notificationSound = new Audio('sounds/draw_notifications.wav');
    notificationSound.preload = 'auto';
    
    // Set volume to a reasonable level
    notificationSound.volume = 0.7;

    /**
     * Play a notification sound for new draws
     */
    function playNewDrawSound() {
        // Prevent multiple sounds in quick succession (within 2 seconds)
        const now = Date.now();
        if (now - lastSoundTime < 2000) {
            return;
        }
        
        lastSoundTime = now;
        
        try {
            // Check if muted in localStorage
            if (localStorage.getItem('drawSoundMuted') === 'true') {
                return;
            }
            
            // If the audio is already playing, stop it first
            notificationSound.pause();
            notificationSound.currentTime = 0;
            
            // Play the notification sound
            notificationSound.play().catch(e => {
                // Ignore errors - browser might block autoplay
                console.log('Could not play notification sound:', e.message);
            });
        } catch (e) {
            console.error('Error playing notification sound:', e);
        }
    }

    /**
     * Add a sound toggle button to the draw header
     */
    function addSoundToggle() {
        // Find the draw header container
        const drawHeaderContainer = document.getElementById('drawHeaderContainer');
        if (!drawHeaderContainer) {
            return;
        }
        
        // Create sound toggle button
        const soundToggle = document.createElement('div');
        soundToggle.className = 'sound-indicator';
        
        // Set initial state based on localStorage
        if (localStorage.getItem('drawSoundMuted') === 'true') {
            soundToggle.classList.add('muted');
        }
        
        // Add click event listener
        soundToggle.addEventListener('click', () => {
            const isMuted = soundToggle.classList.contains('muted');
            
            if (isMuted) {
                // Unmute
                soundToggle.classList.remove('muted');
                localStorage.setItem('drawSoundMuted', 'false');
                
                // Play test sound
                playNewDrawSound();
            } else {
                // Mute
                soundToggle.classList.add('muted');
                localStorage.setItem('drawSoundMuted', 'true');
            }
        });
        
        // Append to draw header
        drawHeaderContainer.appendChild(soundToggle);
    }

    // Listen for new draw events from DrawHeader
    document.addEventListener('drawNumbersUpdated', (event) => {
        if (event.detail?.isNewDraw) {
            playNewDrawSound();
        }
    });

    // Initialize sound toggle when DOM is loaded
    document.addEventListener('DOMContentLoaded', addSoundToggle);

    // Expose the function globally (in case we need to call it manually)
    window.playNewDrawSound = playNewDrawSound;
})(); 