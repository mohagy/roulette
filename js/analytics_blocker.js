
// Analytics Update Blocker
(function() {
    console.log('🚫 ANALYTICS BLOCKER: Preventing unauthorized analytics updates');

    // Block localStorage analytics updates
    const originalSetItem = Storage.prototype.setItem;
    Storage.prototype.setItem = function(key, value) {
        if (key.includes('rouletteAnalytics') || key.includes('analytics')) {
            console.warn('🚫 BLOCKED: Analytics localStorage update attempt', key, value);
            throw new Error('Analytics localStorage updates are blocked for security');
        }
        return originalSetItem.call(this, key, value);
    };

    // Block analytics object updates
    if (window.rouletteAnalytics) {
        Object.freeze(window.rouletteAnalytics);
        console.log('🔒 LOCKED: rouletteAnalytics object frozen');
    }

    // Monitor for analytics update attempts (but allow display requests)
    const originalFetch = window.fetch;
    window.fetch = function(url, options = {}) {
        // Allow GET requests to load_analytics.php for display purposes
        if (url.includes('load_analytics.php') && (!options.method || options.method === 'GET')) {
            console.log('✅ ALLOWED: Analytics display request', url);
            return originalFetch.apply(this, arguments);
        }

        // Block POST requests to analytics endpoints (these are updates)
        if ((url.includes('analytics') || url.includes('save_winning_number')) &&
            options.method && options.method.toUpperCase() === 'POST') {
            console.warn('🚫 BLOCKED: Analytics update attempt', url, options.method);
            return Promise.reject(new Error('Analytics updates blocked for security'));
        }

        // Allow all other requests
        return originalFetch.apply(this, arguments);
    };

    console.log('✅ Analytics blocker initialized');
})();
