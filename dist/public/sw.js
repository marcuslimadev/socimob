const CACHE_VERSION = "socimob-pwa-v2";
const APP_SHELL_CACHE = `${CACHE_VERSION}:shell`;
const RUNTIME_CACHE = `${CACHE_VERSION}:runtime`;
const APP_SHELL = ["/offline.html", "/manifest.webmanifest"];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches
      .open(APP_SHELL_CACHE)
      .then((cache) => cache.addAll(APP_SHELL))
      .then(() => self.skipWaiting()),
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) => Promise.all(keys.filter((key) => !key.startsWith(CACHE_VERSION)).map((key) => caches.delete(key))))
      .then(() => self.clients.claim()),
  );
});

const isHtmlNavigation = (request) =>
  request.mode === "navigate" || (request.headers.get("accept") || "").includes("text/html");

const shouldCacheAsset = (url) =>
  url.origin === self.location.origin &&
  (url.pathname.startsWith("/assets/") ||
    url.pathname.startsWith("/icons/") ||
    url.pathname === "/favicon.ico" ||
    url.pathname === "/manifest.webmanifest");

self.addEventListener("fetch", (event) => {
  const { request } = event;

  if (request.method !== "GET") {
    return;
  }

  const url = new URL(request.url);

  if (url.pathname.startsWith("/api") || url.pathname.startsWith("/webhook") || url.pathname.startsWith("/storage") || url.pathname.startsWith("/uploads")) {
    return;
  }

  if (isHtmlNavigation(request)) {
    event.respondWith(
      fetch(request)
        .then((response) => response)
        .catch(() => caches.match("/offline.html")),
    );
    return;
  }

  if (shouldCacheAsset(url)) {
    event.respondWith(
      caches.match(request).then((cached) => {
        const fetchAndCache = fetch(request).then((response) => {
          if (!response || response.status !== 200) return response;
          const copy = response.clone();
          caches.open(RUNTIME_CACHE).then((cache) => cache.put(request, copy));
          return response;
        });

        return cached || fetchAndCache;
      }),
    );
  }
});
