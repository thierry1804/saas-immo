/* Veille PWA — service worker placeholder (L00).
 * Remplacé en L03 par une version migrable avec IndexedDB et file d'envoi.
 */
const CACHE = 'veille-shell-v0';

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(['/'])));
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
  event.respondWith(
    caches.match(event.request).then((cached) => cached || fetch(event.request)),
  );
});
