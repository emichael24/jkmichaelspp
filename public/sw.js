/**
 * JK Michaels PWA Service Worker
 * Handles caching, offline support, and background sync.
 */

const CACHE_VERSION = 'jkm-v4';
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const DYNAMIC_CACHE = `${CACHE_VERSION}-dynamic`;
const API_CACHE = `${CACHE_VERSION}-api`;

// Static assets to pre-cache during install
const STATIC_ASSETS = [
    '/',
    '/offline.html',
    '/manifest.json',
];

// Cache strategies
const CACHE_FIRST_PATHS = [
    '/build/',
    '/icons/',
    '/images/',
    '/fonts/',
];

const NETWORK_FIRST_PATHS = [
    '/api/',
    '/dashboard',
    '/courses',
    '/forums',
];

// Install: Pre-cache static assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE).then((cache) => {
            console.log('[SW] Pre-caching static assets');
            return cache.addAll(STATIC_ASSETS).catch((err) => {
                console.warn('[SW] Some assets failed to cache:', err);
            });
        })
    );
    self.skipWaiting();
});

// Activate: Clean up old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys
                    .filter((key) => key !== STATIC_CACHE && key !== DYNAMIC_CACHE && key !== API_CACHE)
                    .map((key) => {
                        console.log('[SW] Removing old cache:', key);
                        return caches.delete(key);
                    })
            );
        })
    );
    self.clients.claim();
});

// Fetch: Apply cache strategies
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Skip non-GET requests
    if (event.request.method !== 'GET') return;

    // Skip external requests
    if (url.origin !== self.location.origin) return;

    // Cache-first for static assets
    if (CACHE_FIRST_PATHS.some((path) => url.pathname.startsWith(path))) {
        event.respondWith(cacheFirst(event.request));
        return;
    }

    // Network-first for dynamic content
    if (NETWORK_FIRST_PATHS.some((path) => url.pathname.startsWith(path))) {
        event.respondWith(networkFirst(event.request));
        return;
    }

    // Stale-while-revalidate for everything else
    event.respondWith(staleWhileRevalidate(event.request));
});

// Cache-first strategy
async function cacheFirst(request) {
    const cached = await caches.match(request);
    if (cached) return cached;

    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(STATIC_CACHE);
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        return offlineFallback(request);
    }
}

// Network-first strategy
async function networkFirst(request) {
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        const cached = await caches.match(request);
        return cached || offlineFallback(request);
    }
}

// Stale-while-revalidate strategy
async function staleWhileRevalidate(request) {
    const cached = await caches.match(request);

    const fetchPromise = fetch(request)
        .then((response) => {
            if (response.ok) {
                caches.open(DYNAMIC_CACHE).then((cache) => cache.put(request, response.clone()));
            }
            return response;
        })
        .catch(() => cached || offlineFallback(request));

    return cached || fetchPromise;
}

// Offline fallback
async function offlineFallback(request) {
    if (request.destination === 'document') {
        const offlinePage = await caches.match('/offline');
        if (offlinePage) return offlinePage;
    }

    return new Response('Offline', {
        status: 503,
        statusText: 'Service Unavailable',
        headers: { 'Content-Type': 'text/plain' },
    });
}

// Background Sync for queued actions
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-progress') {
        event.waitUntil(syncProgress());
    }
    if (event.tag === 'sync-quiz-attempts') {
        event.waitUntil(syncQuizAttempts());
    }
});

async function syncProgress() {
    try {
        const db = await openDB();
        const tx = db.transaction('pendingProgress', 'readonly');
        const store = tx.objectStore('pendingProgress');
        const items = await getAllFromStore(store);

        for (const item of items) {
            try {
                await fetch('/api/progress/sync', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(item),
                });
                // Remove synced item
                const deleteTx = db.transaction('pendingProgress', 'readwrite');
                deleteTx.objectStore('pendingProgress').delete(item.id);
            } catch {
                console.warn('[SW] Failed to sync progress item:', item.id);
            }
        }
    } catch (err) {
        console.error('[SW] Sync progress error:', err);
    }
}

async function syncQuizAttempts() {
    console.log('[SW] Syncing quiz attempts...');
    // Similar pattern as syncProgress
}

// Push Notifications
self.addEventListener('push', (event) => {
    const data = event.data?.json() || {};

    const options = {
        body: data.body || 'You have a new notification',
        icon: '/icons/icon-192x192.png',
        badge: '/icons/icon-72x72.png',
        vibrate: [100, 50, 100],
        data: {
            url: data.url || '/dashboard',
            dateOfArrival: Date.now(),
        },
        actions: data.actions || [
            { action: 'open', title: 'Open' },
            { action: 'dismiss', title: 'Dismiss' },
        ],
    };

    event.waitUntil(self.registration.showNotification(data.title || 'JK Michaels', options));
});

// Notification click handler
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    if (event.action === 'dismiss') return;

    const url = event.notification.data?.url || '/dashboard';
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            const existingClient = clients.find((c) => c.url.includes(url));
            if (existingClient) {
                return existingClient.focus();
            }
            return self.clients.openWindow(url);
        })
    );
});

// IndexedDB helper
function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('jkm-offline', 1);
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            if (!db.objectStoreNames.contains('pendingProgress')) {
                db.createObjectStore('pendingProgress', { keyPath: 'id', autoIncrement: true });
            }
            if (!db.objectStoreNames.contains('pendingQuizAttempts')) {
                db.createObjectStore('pendingQuizAttempts', { keyPath: 'id', autoIncrement: true });
            }
            if (!db.objectStoreNames.contains('downloadedLessons')) {
                db.createObjectStore('downloadedLessons', { keyPath: 'lessonId' });
            }
        };
    });
}

function getAllFromStore(store) {
    return new Promise((resolve, reject) => {
        const request = store.getAll();
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}
