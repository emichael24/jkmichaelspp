/**
 * PWA Service Worker Registration & Utilities
 */

export function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) {
        console.warn('Service workers not supported');
        return;
    }

    window.addEventListener('load', async () => {
        try {
            const registration = await navigator.serviceWorker.register('/sw.js', {
                scope: '/',
            });

            console.log('[PWA] Service worker registered:', registration.scope);

            // Check for updates periodically
            setInterval(() => registration.update(), 60 * 60 * 1000); // Every hour

            // Handle updates silently
            registration.addEventListener('updatefound', () => {
                const newWorker = registration.installing;
                newWorker?.addEventListener('statechange', () => {
                    if (newWorker.state === 'activated' && navigator.serviceWorker.controller) {
                        console.log('[PWA] New version available');
                    }
                });
            });
        } catch (err) {
            console.error('[PWA] Service worker registration failed:', err);
        }
    });
}

/**
 * Request push notification permission
 */
export async function requestNotificationPermission() {
    if (!('Notification' in window)) return 'unsupported';
    if (Notification.permission === 'granted') return 'granted';
    if (Notification.permission === 'denied') return 'denied';

    return Notification.requestPermission();
}

/**
 * Subscribe to push notifications
 */
export async function subscribeToPush() {
    const registration = await navigator.serviceWorker.ready;
    const subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(
            import.meta.env.VITE_VAPID_PUBLIC_KEY || ''
        ),
    });

    // Send subscription to server
    await fetch('/api/push/subscribe', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(subscription),
    });

    return subscription;
}

/**
 * Check if app can be installed (Add to Home Screen)
 * Capture the event at module load time so it's never missed.
 */
let deferredPrompt = null;
let installCallbacks = [];

// Capture beforeinstallprompt immediately at module load
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    installCallbacks.forEach((cb) => cb(true));
});

window.addEventListener('appinstalled', () => {
    deferredPrompt = null;
    installCallbacks.forEach((cb) => cb(false));
});

export function setupInstallPrompt(callback) {
    installCallbacks.push(callback);
    // If the event already fired before the component mounted, notify immediately
    if (deferredPrompt) {
        callback(true);
    }
}

export async function installApp() {
    if (!deferredPrompt) return false;
    deferredPrompt.prompt();
    const { outcome } = await deferredPrompt.userChoice;
    deferredPrompt = null;
    return outcome === 'accepted';
}

/**
 * Online/Offline status tracking
 */
export function setupNetworkStatus(onOnline, onOffline) {
    window.addEventListener('online', onOnline);
    window.addEventListener('offline', onOffline);

    // Return initial status
    return navigator.onLine;
}

/**
 * IndexedDB helpers for offline data
 */
export function openOfflineDB() {
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

export async function saveProgressOffline(progressData) {
    const db = await openOfflineDB();
    const tx = db.transaction('pendingProgress', 'readwrite');
    tx.objectStore('pendingProgress').add({
        ...progressData,
        timestamp: Date.now(),
    });
}

export async function saveLessonOffline(lessonId, lessonData) {
    const db = await openOfflineDB();
    const tx = db.transaction('downloadedLessons', 'readwrite');
    tx.objectStore('downloadedLessons').put({
        lessonId,
        ...lessonData,
        downloadedAt: Date.now(),
    });
}

export async function getOfflineLesson(lessonId) {
    const db = await openOfflineDB();
    return new Promise((resolve) => {
        const tx = db.transaction('downloadedLessons', 'readonly');
        const request = tx.objectStore('downloadedLessons').get(lessonId);
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => resolve(null);
    });
}

export async function getDownloadedLessons() {
    const db = await openOfflineDB();
    return new Promise((resolve) => {
        const tx = db.transaction('downloadedLessons', 'readonly');
        const request = tx.objectStore('downloadedLessons').getAll();
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => resolve([]);
    });
}

export async function removeOfflineLesson(lessonId) {
    const db = await openOfflineDB();
    const tx = db.transaction('downloadedLessons', 'readwrite');
    tx.objectStore('downloadedLessons').delete(lessonId);
}

export async function cacheLessonAssets(lesson) {
    if (!('caches' in window)) return;

    const urls = [
        lesson?.video_url,
        lesson?.audio_url,
        lesson?.pdf_url,
        lesson?.slides_url,
    ].filter(Boolean);

    if (!urls.length) return;

    const cache = await caches.open('jkm-offline-assets');
    const origin = window.location.origin;

    for (const url of urls) {
        try {
            const resolved = new URL(url, origin);
            if (resolved.origin !== origin) continue;
            await cache.add(resolved.toString());
        } catch (err) {
            console.warn('[PWA] Failed to cache asset', url, err);
        }
    }
}

// Trigger background sync
export async function requestBackgroundSync(tag = 'sync-progress') {
    const registration = await navigator.serviceWorker.ready;
    if ('sync' in registration) {
        await registration.sync.register(tag);
    }
}

// Utility
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; i++) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}
