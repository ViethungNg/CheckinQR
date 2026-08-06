// Service Worker cho PMT Checkin PWA
const CACHE_NAME = 'pmt-checkin-v1';
const urlsToCache = [
  './',
  './index.php',
  './assets/css/frontend.css',
  './assets/css/pwa-app.css',
  './assets/js/pwa-standalone.js',
  './img/logo pmt.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(urlsToCache).catch(err => console.log('PWA cache add failed', err));
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  // Đối với ứng dụng động PHP (Post/API requests), ưu tiên Network First
  if (event.request.method !== 'GET') return;
  
  event.respondWith(
    fetch(event.request).catch(() => {
      return caches.match(event.request);
    })
  );
});
