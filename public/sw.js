/* Only public bundles and the anonymous reading shell enter Cache Storage. */
const BUILD = new URL(self.location.href).searchParams.get('build') || 'development';
const STATIC_CACHE = `jkm-v7-static-${BUILD}`;
const OFFLINE = '/offline';

self.addEventListener('install', event => {
    event.waitUntil((async () => {
        const cache = await caches.open(STATIC_CACHE);
        const shell = await fetch(OFFLINE, { credentials: 'omit', cache: 'reload' });
        if (!shell.ok) throw new Error('Offline reader could not be installed');
        const html = await shell.clone().text();
        const assets = [...html.matchAll(/(?:src|href)="([^"]+)"/g)].map(match => new URL(match[1], self.location.origin))
            .filter(url => url.origin === self.location.origin && url.pathname.startsWith('/build/'));
        await cache.addAll([...new Set(assets.map(url => url.href)), '/manifest.json']);
        await cache.put(OFFLINE, shell);
        await self.skipWaiting();
    })());
});

self.addEventListener('activate', event => {
    event.waitUntil((async () => {
        await Promise.all((await caches.keys()).filter(key => key.startsWith('jkm-') && key !== STATIC_CACHE).map(key => caches.delete(key)));
        indexedDB.deleteDatabase('jkm-offline');
        await self.clients.claim();
    })());
});

self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);
    if (event.request.method !== 'GET' || url.origin !== self.location.origin) return;
    if (['/build/', '/icons/', '/fonts/'].some(prefix => url.pathname.startsWith(prefix))) {
        event.respondWith((async () => {
            const cache = await caches.open(STATIC_CACHE);
            const cached = await cache.match(event.request);
            if (cached) return cached;
            const response = await fetch(event.request);
            if (response.ok && response.type === 'basic') await cache.put(event.request, response.clone());
            return response;
        })());
        return;
    }
    event.respondWith(fetch(event.request).catch(async () => {
        if (event.request.mode === 'navigate') {
            const shell = await caches.match(OFFLINE);
            if (shell) return shell;
        }
        return new Response('A connection is required for this action.', { status: 503, headers: { 'Content-Type': 'text/plain' } });
    }));
});

// A signed-in page owns the encryption key and CSRF token. The worker never replays private data.
self.addEventListener('sync', event => {
    if (event.tag === 'sync-progress') event.waitUntil(self.clients.matchAll({ type: 'window' }).then(clients => {
        clients.forEach(client => client.postMessage({ type: 'SYNC_PROGRESS' }));
    }));
});

self.addEventListener('push', event => {
    let data = {};
    try { data = event.data?.json() || {}; } catch { /* Generic, non-sensitive fallback. */ }
    event.waitUntil(self.registration.showNotification(data.title || 'JK Michaels', {
        body: data.body || 'You have an update in your learning workspace.',
        icon: '/icons/icon-192x192.png', badge: '/icons/icon-72x72.png',
        data: { url: '/notifications' },
        actions: [{ action: 'open', title: 'Open' }, { action: 'dismiss', title: 'Dismiss' }],
    }));
});

self.addEventListener('notificationclick', event => {
    event.notification.close();
    if (event.action === 'dismiss') return;
    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(async clients => {
        const existing = clients.find(client => new URL(client.url).origin === self.location.origin);
        if (existing) { await existing.navigate('/notifications'); return existing.focus(); }
        return self.clients.openWindow('/notifications');
    }));
});
