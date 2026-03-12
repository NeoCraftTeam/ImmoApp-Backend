/**
 * KeyHome PWA Service Worker
 *
 * Strategies:
 * - Cache-first for static assets (CSS, JS, images, fonts)
 * - Network-first with offline fallback for HTML/dynamic content
 * - Stale-while-revalidate for API GET requests
 * - Background sync for failed POST/PUT/DELETE requests
 */

const CACHE_VERSION = "keyhome-v3";
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const DYNAMIC_CACHE = `${CACHE_VERSION}-dynamic`;
const API_CACHE = `${CACHE_VERSION}-api`;
const OFFLINE_URL = "/pwa/offline.html";

/**
 * Static assets to precache during install.
 */
const PRECACHE_ASSETS = [
    OFFLINE_URL,
    "/pwa/icons/icon-192x192.png",
    "/pwa/icons/icon-512x512.png",
    "/manifest.json",
];

/**
 * Patterns for cache-first strategy (immutable static assets).
 */
const STATIC_PATTERNS = [
    /\.(css|js|woff2?|ttf|otf|eot)(\?.*)?$/,
    /\/build\//,
    /\/fonts\//,
    /\/pwa\//,
];

/**
 * Patterns to never cache.
 */
const NO_CACHE_PATTERNS = [
    /\/livewire\//,
    /\/sanctum\//,
    /\/_debugbar\//,
    /\/telescope\//,
    /\/pulse\//,
    /\/horizon\//,
    /\/broadcasting\//,
    /\/login/,
    /\/logout/,
    /\/register/,
    /\/password/,
    /\/forgot-password/,
    /\/reset-password/,
    /\/two-factor/,
    /\/email\/verify/,
    /csrf-cookie/,
    /hot$/,
];

/**
 * Background sync queue name.
 */
const SYNC_QUEUE = "keyhome-bg-sync";

// ─── Install ──────────────────────────────────────────────
self.addEventListener("install", (event) => {
    event.waitUntil(
        caches
            .open(STATIC_CACHE)
            .then((cache) => cache.addAll(PRECACHE_ASSETS))
            .then(() => self.skipWaiting()),
    );
});

// ─── Activate ─────────────────────────────────────────────
self.addEventListener("activate", (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter(
                            (key) =>
                                key.startsWith("keyhome-") &&
                                key !== STATIC_CACHE &&
                                key !== DYNAMIC_CACHE &&
                                key !== API_CACHE,
                        )
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => caches.open(DYNAMIC_CACHE))
            .then((cache) =>
                cache
                    .keys()
                    .then((requests) =>
                        Promise.all(
                            requests
                                .filter((req) =>
                                    /\/(login|logout|register|password|forgot-password|reset-password)/.test(
                                        new URL(req.url).pathname,
                                    ),
                                )
                                .map((req) => cache.delete(req)),
                        ),
                    ),
            )
            .then(() => self.clients.claim()),
    );
});

// ─── Fetch ────────────────────────────────────────────────
self.addEventListener("fetch", (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip cross-origin requests
    if (url.origin !== self.location.origin) {
        return;
    }

    // Completely bypass SW for auth-related URLs (all methods) to prevent CSRF issues
    if (NO_CACHE_PATTERNS.some((pattern) => pattern.test(url.pathname))) {
        return;
    }

    // Skip non-GET for caching (but queue for background sync)
    if (request.method !== "GET") {
        if (
            request.method === "POST" ||
            request.method === "PUT" ||
            request.method === "DELETE"
        ) {
            event.respondWith(
                fetch(request.clone()).catch(() => {
                    return enqueueSync(request);
                }),
            );
        }
        return;
    }

    // Strategy: Cache-first for static assets
    if (STATIC_PATTERNS.some((pattern) => pattern.test(url.pathname))) {
        event.respondWith(cacheFirst(request, STATIC_CACHE));
        return;
    }

    // Strategy: Stale-while-revalidate for API GETs
    if (url.pathname.startsWith("/api/")) {
        event.respondWith(staleWhileRevalidate(request, API_CACHE));
        return;
    }

    // Strategy: Network-first for navigation/HTML
    if (
        request.mode === "navigate" ||
        request.headers.get("accept")?.includes("text/html")
    ) {
        event.respondWith(networkFirst(request, DYNAMIC_CACHE));
        return;
    }

    // Strategy: Stale-while-revalidate for images
    if (/\.(png|jpe?g|gif|svg|webp|ico)(\?.*)?$/.test(url.pathname)) {
        event.respondWith(staleWhileRevalidate(request, STATIC_CACHE));
        return;
    }

    // Default: Network-first
    event.respondWith(networkFirst(request, DYNAMIC_CACHE));
});

// ─── Push Notifications ───────────────────────────────────
self.addEventListener("push", (event) => {
    let data = {
        title: "KeyHome",
        body: "Vous avez une nouvelle notification.",
        icon: "/pwa/icons/icon-192x192.png",
        badge: "/pwa/icons/icon-72x72.png",
        tag: "keyhome-notification",
        data: { url: "/admin" },
    };

    if (event.data) {
        try {
            const payload = event.data.json();
            data = { ...data, ...payload };
        } catch {
            data.body = event.data.text();
        }
    }

    event.waitUntil(
        self.registration.showNotification(data.title, {
            body: data.body,
            icon: data.icon,
            badge: data.badge,
            tag: data.tag,
            vibrate: [200, 100, 200],
            requireInteraction: false,
            actions: data.actions || [],
            data: data.data || {},
        }),
    );
});

// ─── Notification Click ───────────────────────────────────
self.addEventListener("notificationclick", (event) => {
    event.notification.close();

    const targetPath = event.notification.data?.url || "/admin";
    const targetUrl = new URL(targetPath, self.location.origin).href;

    event.waitUntil(
        self.clients
            .matchAll({ type: "window", includeUncontrolled: true })
            .then((clients) => {
                // Focus any existing app window and navigate it
                for (const client of clients) {
                    if ("focus" in client) {
                        return client.focus().then((focused) => {
                            if ("navigate" in focused) {
                                return focused.navigate(targetUrl);
                            }
                            return focused;
                        });
                    }
                }
                // No existing window — open a new one
                return self.clients.openWindow(targetUrl);
            }),
    );
});

// ─── Background Sync ──────────────────────────────────────
self.addEventListener("sync", (event) => {
    if (event.tag === SYNC_QUEUE) {
        event.waitUntil(replayQueuedRequests());
    }
});

// ─── Caching Strategies ───────────────────────────────────

/**
 * Cache-first: Serve from cache, fall back to network.
 * Best for static, versioned assets.
 */
async function cacheFirst(request, cacheName) {
    const cached = await caches.match(request);
    if (cached) {
        return cached;
    }

    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        return new Response("Offline", {
            status: 503,
            statusText: "Service Unavailable",
        });
    }
}

/**
 * Network-first: Try network, fall back to cache, then offline page.
 * Best for HTML/navigation requests.
 */
async function networkFirst(request, cacheName) {
    try {
        const response = await fetch(request);
        if (response.ok) {
            const url = new URL(request.url);
            const isAuthPage =
                /\/(login|logout|register|password|forgot-password|reset-password)/.test(
                    url.pathname,
                );
            if (!isAuthPage) {
                const cache = await caches.open(cacheName);
                cache.put(request, response.clone());
            }
        }
        return response;
    } catch {
        const cached = await caches.match(request);
        if (cached) {
            return cached;
        }

        // Return offline page for navigation requests
        if (request.mode === "navigate") {
            const offlinePage = await caches.match(OFFLINE_URL);
            if (offlinePage) {
                return offlinePage;
            }
        }

        return new Response("Offline", {
            status: 503,
            statusText: "Service Unavailable",
        });
    }
}

/**
 * Stale-while-revalidate: Serve from cache immediately, update in background.
 * Best for API GETs and images.
 */
async function staleWhileRevalidate(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);

    const fetchPromise = fetch(request)
        .then((response) => {
            if (response.ok) {
                cache.put(request, response.clone());
            }
            return response;
        })
        .catch(() => null);

    return (
        cached ||
        (await fetchPromise) ||
        new Response("Offline", { status: 503 })
    );
}

// ─── Background Sync Helpers ──────────────────────────────

/**
 * Queue a failed request for background sync replay.
 */
async function enqueueSync(request) {
    try {
        const body = await request.clone().text();
        const queueData = {
            url: request.url,
            method: request.method,
            headers: Object.fromEntries(request.headers.entries()),
            body: body,
            timestamp: Date.now(),
        };

        // Store in IndexedDB
        const db = await openSyncDB();
        const tx = db.transaction("requests", "readwrite");
        tx.objectStore("requests").add(queueData);
        await tx.complete;

        // Register sync
        if (self.registration.sync) {
            await self.registration.sync.register(SYNC_QUEUE);
        }

        return new Response(
            JSON.stringify({
                queued: true,
                message:
                    "Requête mise en file — sera envoyée quand la connexion sera rétablie.",
            }),
            {
                status: 202,
                headers: { "Content-Type": "application/json" },
            },
        );
    } catch {
        return new Response("Offline", { status: 503 });
    }
}

/**
 * Replay all queued requests (called on sync event).
 */
async function replayQueuedRequests() {
    const db = await openSyncDB();
    const tx = db.transaction("requests", "readonly");
    const store = tx.objectStore("requests");

    return new Promise((resolve, reject) => {
        const getAll = store.getAll();
        getAll.onsuccess = async () => {
            const requests = getAll.result;
            const deleteTx = db.transaction("requests", "readwrite");
            const deleteStore = deleteTx.objectStore("requests");

            for (const item of requests) {
                try {
                    await fetch(item.url, {
                        method: item.method,
                        headers: item.headers,
                        body: item.body || undefined,
                    });
                    deleteStore.delete(item.id);
                } catch {
                    // Keep in queue for next sync attempt
                }
            }
            resolve();
        };
        getAll.onerror = () => reject(getAll.error);
    });
}

/**
 * Open IndexedDB for background sync queue.
 */
function openSyncDB() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open("keyhome-sync", 1);
        req.onupgradeneeded = () => {
            const db = req.result;
            if (!db.objectStoreNames.contains("requests")) {
                db.createObjectStore("requests", {
                    keyPath: "id",
                    autoIncrement: true,
                });
            }
        };
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}
