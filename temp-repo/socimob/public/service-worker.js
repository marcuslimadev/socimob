// Bump this value to invalidate old caches.
// NOTE: Do NOT aggressively cache HTML pages; we want latest UI after deploy.
const CACHE_NAME = 'socimob-v20260109-1';
const urlsToCache = [
  '/js/login-utils.js',
  '/manifest.json',
  '/favicon.ico',
  '/images/icon-192.png',
  '/images/icon-512.png'
];

self.addEventListener('install', event => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(urlsToCache))
  );
});

self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // Never cache API calls.
  if (url.pathname.startsWith('/api/')) {
    return;
  }

  // For navigations (HTML), prefer network to avoid stale UI after deploy.
  if (event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request).catch(() => caches.match('/app/login.html'))
    );
    return;
  }

  // For other assets, cache-first.
  event.respondWith(
    caches.match(event.request).then(response => response || fetch(event.request))
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Push notifications
self.addEventListener('push', event => {
  const data = event.data.json();
  const options = {
    body: data.body,
    icon: '/images/icon-192.png',
    badge: '/images/badge.png',
    vibrate: [200, 100, 200],
    data: {
      url: data.url || '/app/chat.html'
    }
  };
  
  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  event.waitUntil(
    clients.openWindow(event.notification.data.url)
  );
});
