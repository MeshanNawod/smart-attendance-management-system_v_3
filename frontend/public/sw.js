const CACHE = "sentinel-swarm-v1";
const ASSETS = [
  "/frontend/",
  "/frontend/index.html",
];

self.addEventListener("install", (e) => {
  e.waitUntil(
    caches.open(CACHE).then((c) => c.addAll(ASSETS)).then(() => self.skipWaiting())
  );
});

self.addEventListener("activate", (e) => {
  e.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener("fetch", (e) => {
  const { request } = e;
  const url = new URL(request.url);

  // Only cache same-origin GET requests
  if (request.method !== "GET" || !url.origin.startsWith(self.location.origin)) {
    return e.respondWith(fetch(request));
  }

  // API calls — network first
  if (url.pathname.startsWith("/api/") || url.pathname.includes("api")) {
    return e.respondWith(
      fetch(request).catch(() => new Response(JSON.stringify({ offline: true }), {
        status: 503,
        headers: { "Content-Type": "application/json" },
      }))
    );
  }

  // Static assets — cache first
  e.respondWith(
    caches.match(request).then((cached) => cached || fetch(request).then((res) => {
      const clone = res.clone();
      caches.open(CACHE).then((c) => c.put(request, clone));
      return res;
    }))
  );
});
