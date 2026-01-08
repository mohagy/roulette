// Roulette PWA Service Worker
const CACHE_NAME = 'roulette-pwa-v1';

// Assets to cache on install
const STATIC_ASSETS = [
  '/slipp/',
  '/slipp/index.html',
  '/slipp/offline.html',
  '/slipp/css/style.css',
  '/slipp/css/custom-styles.css',
  '/slipp/css/auth.css',
  '/slipp/css/draw-header.css',
  '/slipp/css/fixed-sidebar.css',
  '/slipp/css/movable-sidebar.css',
  '/slipp/css/global-stake-keypad.css',
  '/slipp/css/change-calculator.css',
  '/slipp/css/elegant-cancel-button.css',
  '/slipp/css/reprint-slip-button.css',
  '/slipp/css/reprint-slip-keypad.css',
  '/slipp/css/tv-style-draw-display.css',
  '/slipp/js/scripts.js',
  '/slipp/js/auth-check.js',
  '/slipp/js/cash-manager.js',
  '/slipp/js/draw-header.js',
  '/slipp/js/draw-betting-integration.js',
  '/slipp/js/betting-slip-patch.js',
  '/slipp/js/feature-removal-patch.js',
  '/slipp/js/complete-bets-fix.js',
  '/slipp/js/complete-bet-fix.js',
  '/slipp/js/update-stake-limits.js',
  '/slipp/js/timer-sync.js',
  '/slipp/js/reprint-slip-button.js',
  '/slipp/js/payout-button-remover.js',
  '/slipp/js/fixed-buttons.js',
  '/slipp/js/draw-sync.js',
  '/slipp/js/cashout.js',
  '/slipp/js/roll-history-sync.js',
  '/slipp/js/tv-betting-integration.js',
  '/slipp/js/movable-sidebar.js',
  '/slipp/js/change-calculator.js',
  '/slipp/js/stake-input-handler.js',
  '/slipp/js/global-stake-keypad.js',
  '/slipp/js/elegant-cancel-button.js',
  '/slipp/js/straight-up-confirmation.js',
  '/slipp/js/tv-style-draw-display.js',
  '/slipp/images/roulette-wheel.png',
  '/slipp/images/roulette-wheel-center.png',
  '/slipp/images/roulette-wheel-cross.png',
  '/slipp/images/roulette-wheel-cross-shadow.png',
  '/slipp/images/roulette-ball.png',
  '/slipp/images/roulette-number-glow.png',
  '/slipp/images/chips-5.png',
  '/slipp/images/chips-10.png',
  '/slipp/images/chips-20.png',
  '/slipp/images/chips-50.png',
  '/slipp/images/chips-100.png',
  '/slipp/images/chips-200.png',
  '/slipp/images/icons/android/android-launchericon-48-48.png',
  '/slipp/images/icons/android/android-launchericon-72-72.png',
  '/slipp/images/icons/android/android-launchericon-96-96.png',
  '/slipp/images/icons/android/android-launchericon-144-144.png',
  '/slipp/images/icons/android/android-launchericon-192-192.png',
  '/slipp/images/icons/android/android-launchericon-512-512.png',
  '/slipp/manifest.json',
  'https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js',
  'https://use.fontawesome.com/releases/v5.13.0/js/all.js'
];

// Install event - cache static assets
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Caching static assets');
        return cache.addAll(STATIC_ASSETS);
      })
      .then(() => self.skipWaiting())
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.filter(cacheName => {
          return cacheName !== CACHE_NAME;
        }).map(cacheName => {
          console.log('Deleting old cache:', cacheName);
          return caches.delete(cacheName);
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch event - serve from cache or network
self.addEventListener('fetch', event => {
  // Skip cross-origin requests
  if (!event.request.url.startsWith(self.location.origin) &&
      !event.request.url.startsWith('https://ajax.googleapis.com') &&
      !event.request.url.startsWith('https://use.fontawesome.com')) {
    return;
  }

  // Handle API requests differently
  if (event.request.url.includes('.php')) {
    // For PHP files, use network first, then cache
    event.respondWith(
      fetch(event.request)
        .then(response => {
          // Cache the response if it's valid
          if (response.status === 200) {
            const responseClone = response.clone();
            caches.open(CACHE_NAME).then(cache => {
              cache.put(event.request, responseClone);
            });
          }
          return response;
        })
        .catch(() => {
          // If network fails, try to serve from cache
          return caches.match(event.request)
            .then(cachedResponse => {
              if (cachedResponse) {
                return cachedResponse;
              }
              // If not in cache and we're offline, show offline page for HTML requests
              if (event.request.headers.get('accept').includes('text/html')) {
                return caches.match('/slipp/offline.html');
              }
              return new Response('Network error', { status: 408, headers: { 'Content-Type': 'text/plain' } });
            });
        })
    );
    return;
  }

  // For other assets, use cache first, then network
  event.respondWith(
    caches.match(event.request)
      .then(cachedResponse => {
        if (cachedResponse) {
          return cachedResponse;
        }

        return fetch(event.request)
          .then(response => {
            // Cache the response if it's valid
            if (response.status === 200) {
              const responseClone = response.clone();
              caches.open(CACHE_NAME).then(cache => {
                cache.put(event.request, responseClone);
              });
            }
            return response;
          })
          .catch(error => {
            // If network fails and we're requesting an HTML page, show offline page
            if (event.request.headers.get('accept').includes('text/html')) {
              return caches.match('/slipp/offline.html');
            }
            throw error;
          });
      })
  );
});

// Handle messages from clients
self.addEventListener('message', event => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});
