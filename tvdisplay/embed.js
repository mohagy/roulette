/**
 * Roulette TV Display Embedding Script
 *
 * This script allows easy embedding of the roulette TV display
 * on any website with a simple script tag.
 */

(function() {
    // Configuration
    const config = {
        width: '100%',
        height: '600px',
        refreshInterval: 0, // Disabled by default as the view now auto-refreshes
        source: window.location.origin + '/slipp/tvdisplay/share.php', // Use share.php instead of direct HTML
        responsive: true,
        allowFullscreen: true,
        title: 'Roulette TV Display'
    };

    // Create the iframe
    function createEmbed() {
        // Find the script tag
        const scripts = document.getElementsByTagName('script');
        const currentScript = scripts[scripts.length - 1];

        // Get custom attributes
        const width = currentScript.getAttribute('data-width') || config.width;
        const height = currentScript.getAttribute('data-height') || config.height;
        const refreshInterval = parseInt(currentScript.getAttribute('data-refresh') || config.refreshInterval);
        const responsive = currentScript.getAttribute('data-responsive') !== 'false';
        const title = currentScript.getAttribute('data-title') || config.title;

        // Create container with responsive wrapper if needed
        const container = document.createElement('div');
        container.className = 'roulette-tv-embed';

        if (responsive) {
            // Create responsive wrapper
            container.style.position = 'relative';
            container.style.width = '100%';
            container.style.height = '0';
            container.style.paddingBottom = typeof height === 'string' && height.endsWith('%')
                ? height
                : '56.25%'; // 16:9 aspect ratio by default
            container.style.overflow = 'hidden';
            container.style.maxWidth = width;
            container.style.margin = '0 auto';
            container.style.borderRadius = '4px';
            container.style.boxShadow = '0 2px 10px rgba(0,0,0,0.2)';
        } else {
            // Fixed size container
            container.style.width = width;
            container.style.height = height;
            container.style.overflow = 'hidden';
            container.style.border = 'none';
            container.style.borderRadius = '4px';
            container.style.boxShadow = '0 2px 10px rgba(0,0,0,0.2)';
        }

        // Create iframe
        const iframe = document.createElement('iframe');
        iframe.src = config.source;
        iframe.title = title;

        if (responsive) {
            iframe.style.position = 'absolute';
            iframe.style.top = '0';
            iframe.style.left = '0';
            iframe.style.width = '100%';
            iframe.style.height = '100%';
        } else {
            iframe.width = '100%';
            iframe.height = '100%';
        }

        iframe.style.border = 'none';
        iframe.style.overflow = 'hidden';
        iframe.setAttribute('allowfullscreen', 'true');
        iframe.setAttribute('scrolling', 'no');
        iframe.setAttribute('loading', 'lazy');

        // Add iframe to container
        container.appendChild(iframe);

        // Create loading indicator
        const loadingIndicator = document.createElement('div');
        loadingIndicator.style.position = 'absolute';
        loadingIndicator.style.top = '0';
        loadingIndicator.style.left = '0';
        loadingIndicator.style.width = '100%';
        loadingIndicator.style.height = '100%';
        loadingIndicator.style.display = 'flex';
        loadingIndicator.style.alignItems = 'center';
        loadingIndicator.style.justifyContent = 'center';
        loadingIndicator.style.backgroundColor = '#000';
        loadingIndicator.style.color = '#fff';
        loadingIndicator.style.fontSize = '16px';
        loadingIndicator.style.zIndex = '1';
        loadingIndicator.textContent = 'Loading Roulette Display...';

        container.appendChild(loadingIndicator);

        // Hide loading indicator when iframe loads
        iframe.addEventListener('load', function() {
            loadingIndicator.style.display = 'none';
        });

        // Insert after the script tag
        currentScript.parentNode.insertBefore(container, currentScript.nextSibling);

        // Set up auto-refresh if needed (though not recommended as the view auto-refreshes)
        if (refreshInterval > 0) {
            console.warn('Manual refresh is not recommended as the view auto-refreshes. Consider setting data-refresh="0".');
            setInterval(() => {
                iframe.src = config.source + '?refresh=' + new Date().getTime();
            }, refreshInterval);
        }

        // Return the container for potential further manipulation
        return container;
    }

    // Initialize when the DOM is ready
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        setTimeout(createEmbed, 1);
    } else {
        document.addEventListener('DOMContentLoaded', createEmbed);
    }
})();
