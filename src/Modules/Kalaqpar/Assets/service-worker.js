/* KQ_CONFIG */
'use strict';
const absolute = path => new URL(path, self.location.origin).href;
const allowed = new Set(CONFIG.assets.map(absolute));
const offlineURL = absolute(CONFIG.offline);
self.addEventListener('install', event => {
 event.waitUntil((async () => {
  const cache = await caches.open(CONFIG.cache);
  for (const url of [...allowed, offlineURL]) {
   const response = await fetch(url, {cache: 'no-store', credentials: 'omit', redirect: 'error'});
   if (!response.ok || response.headers.get('X-KQ-Public-Asset') !== '1') throw new Error('Public PWA resource unavailable');
   await cache.put(url, response);
  }
 })());
 // Deliberately no skipWaiting: every old tab must close before activation.
});
self.addEventListener('activate', event => {
 event.waitUntil((async () => {
  for (const key of await caches.keys()) {
   if (key.startsWith(CONFIG.prefix) && key !== CONFIG.cache) await caches.delete(key);
  }
 })());
 // No clients.claim: never switch a running page to a different worker.
});
self.addEventListener('fetch', event => {
 const request = event.request;
 const url = new URL(request.url);
 if (request.method !== 'GET' || url.origin !== self.location.origin) return;
 // Authentication and token-bearing recovery URLs always use the network.
 if (/^\/(?:account|register|login|logout|forgot-password|reset-password)(?:\/|$)/.test(url.pathname.slice(CONFIG.base.length)) && url.pathname.startsWith(CONFIG.base + '/')) return;
 // Exact allowlist only. Every /api/* request (including GET), unversioned
 // asset, item image, manifest and unrelated Pano path bypasses Cache Storage.
 if (allowed.has(url.href)) {
  event.respondWith((async () => {
   const cache = await caches.open(CONFIG.cache);
   const saved = await cache.match(request);
   return saved || fetch(request); // Do not populate caches from runtime responses.
  })());
  return;
 }
 const gamePage = url.pathname === CONFIG.base || url.pathname === CONFIG.base + '/' ||
  /^\/(?:join|r)\/[^/]+$/.test(url.pathname.slice(CONFIG.base.length)) && url.pathname.startsWith(CONFIG.base + '/');
 if (request.mode === 'navigate' && gamePage) {
  event.respondWith((async () => {
   try { return await fetch(request); } // Real 4xx/5xx pass through unchanged.
   catch {
    const saved = await (await caches.open(CONFIG.cache)).match(offlineURL);
    return saved || Response.error();
   }
  })());
 }
});
