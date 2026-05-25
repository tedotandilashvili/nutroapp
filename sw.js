// Service Worker — NutroApp
const CACHE = 'nutroapp-v3';

// Only cache static assets — never PHP pages
const STATIC = [
  '/assets/css/main.css',
  '/assets/img/icon-192.png',
];

self.addEventListener('install', function(e) {
  e.waitUntil(
    caches.open(CACHE).then(function(c) {
      return c.addAll(STATIC);
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', function(e) {
  // Delete ALL old caches
  e.waitUntil(
    caches.keys().then(function(keys) {
      return Promise.all(
        keys.map(function(k) { return caches.delete(k); })
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', function(e) {
  var url = e.request.url;

  // NEVER cache PHP pages or API calls
  if (url.indexOf('.php') !== -1 || url.indexOf('/api/') !== -1) {
    e.respondWith(fetch(e.request));
    return;
  }

  // For static assets — network first, cache fallback
  e.respondWith(
    fetch(e.request)
      .then(function(resp) {
        var clone = resp.clone();
        caches.open(CACHE).then(function(c) { c.put(e.request, clone); });
        return resp;
      })
      .catch(function() {
        return caches.match(e.request);
      })
  );
});