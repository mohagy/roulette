/**
 * PWA Installer
 * Handles the installation of the Progressive Web App
 */

// Store the install prompt event
let deferredPrompt;
let installButton;

// Initialize when the document is ready
document.addEventListener('DOMContentLoaded', function() {
    // Create the install button if it doesn't exist
    if (!document.getElementById('pwa-install-button')) {
        createInstallButton();
    } else {
        installButton = document.getElementById('pwa-install-button');
        setupInstallButton();
    }
    
    // Check if service worker is supported
    if ('serviceWorker' in navigator) {
        registerServiceWorker();
    }
    
    // Check for updates to the service worker
    checkForUpdates();
});

/**
 * Create the install button
 */
function createInstallButton() {
    // Create the button element
    installButton = document.createElement('div');
    installButton.id = 'pwa-install-button';
    installButton.className = 'pwa-install-button';
    installButton.innerHTML = '<i class="fas fa-download"></i> Install App';
    installButton.style.display = 'none'; // Hide by default
    
    // Add styles for the button
    const style = document.createElement('style');
    style.textContent = `
        .pwa-install-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: rgb(247, 176, 46);
            color: #000;
            padding: 12px 20px;
            border-radius: 50px;
            font-weight: bold;
            cursor: pointer;
            z-index: 9999;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-family: Arial, sans-serif;
        }
        
        .pwa-install-button:hover {
            background-color: #ffd700;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
        }
        
        .pwa-install-button i {
            font-size: 1.2em;
        }
        
        @media (max-width: 768px) {
            .pwa-install-button {
                bottom: 15px;
                right: 15px;
                padding: 10px 16px;
                font-size: 0.9em;
            }
        }
    `;
    
    // Add the button and styles to the document
    document.head.appendChild(style);
    document.body.appendChild(installButton);
    
    // Setup the install button
    setupInstallButton();
}

/**
 * Setup the install button event listeners
 */
function setupInstallButton() {
    installButton.addEventListener('click', async () => {
        // Hide the install button
        installButton.style.display = 'none';
        
        // Show the install prompt
        if (deferredPrompt) {
            deferredPrompt.prompt();
            
            // Wait for the user to respond to the prompt
            const { outcome } = await deferredPrompt.userChoice;
            console.log(`User response to the install prompt: ${outcome}`);
            
            // Clear the deferred prompt variable
            deferredPrompt = null;
        }
    });
}

/**
 * Register the service worker
 */
function registerServiceWorker() {
    navigator.serviceWorker.register('/slipp/service-worker.js')
        .then(registration => {
            console.log('Service Worker registered with scope:', registration.scope);
        })
        .catch(error => {
            console.error('Service Worker registration failed:', error);
        });
}

/**
 * Check for updates to the service worker
 */
function checkForUpdates() {
    // Check if there's an update available
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.ready.then(registration => {
            registration.addEventListener('updatefound', () => {
                // A new service worker is being installed
                const newWorker = registration.installing;
                
                newWorker.addEventListener('statechange', () => {
                    if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                        // New content is available, show update notification
                        showUpdateNotification();
                    }
                });
            });
        });
    }
}

/**
 * Show update notification
 */
function showUpdateNotification() {
    // Create the notification element
    const notification = document.createElement('div');
    notification.className = 'pwa-update-notification';
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-sync-alt"></i>
            <span>New version available!</span>
        </div>
        <button id="update-button">Update</button>
    `;
    
    // Add styles for the notification
    const style = document.createElement('style');
    style.textContent = `
        .pwa-update-notification {
            position: fixed;
            bottom: 20px;
            left: 20px;
            background-color: #202020;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            z-index: 9999;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            font-family: Arial, sans-serif;
            border: 1px solid rgb(247, 176, 46);
        }
        
        .notification-content {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .pwa-update-notification i {
            color: rgb(247, 176, 46);
        }
        
        #update-button {
            background-color: rgb(247, 176, 46);
            color: #000;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        
        #update-button:hover {
            background-color: #ffd700;
        }
    `;
    
    // Add the notification and styles to the document
    document.head.appendChild(style);
    document.body.appendChild(notification);
    
    // Add event listener to the update button
    document.getElementById('update-button').addEventListener('click', () => {
        // Send a message to the service worker to skip waiting
        navigator.serviceWorker.ready.then(registration => {
            registration.waiting.postMessage({ type: 'SKIP_WAITING' });
        });
        
        // Reload the page to get the new version
        window.location.reload();
    });
}

// Listen for the beforeinstallprompt event
window.addEventListener('beforeinstallprompt', (e) => {
    // Prevent the default browser install prompt
    e.preventDefault();
    
    // Store the event for later use
    deferredPrompt = e;
    
    // Show the install button
    if (installButton) {
        installButton.style.display = 'flex';
    }
});
