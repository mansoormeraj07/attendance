/* Minimal service worker: makes the app installable. It never caches pages or data. */
self.addEventListener('install', function () { self.skipWaiting(); });
self.addEventListener('activate', function (e) { e.waitUntil(self.clients.claim()); });
self.addEventListener('fetch', function (e) {
  if (e.request.mode !== 'navigate') return;
  e.respondWith(fetch(e.request).catch(function () {
    return new Response('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Offline</title><body style="margin:0;min-height:100vh;display:grid;place-items:center;background:#070b16;color:#e8ecf8;font:16px system-ui;text-align:center;padding:24px"><div><h1 style="font-size:22px">You are offline</h1><p style="color:#8f9bb8">Check your internet connection, then reload this page.</p><button onclick="location.reload()" style="font:inherit;color:#fff;border:0;border-radius:10px;padding:10px 18px;background:linear-gradient(135deg,#5b8cff,#8a5bff)">Try again</button></div></body>', { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
  }));
});
