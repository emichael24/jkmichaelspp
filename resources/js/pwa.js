import axios from 'axios';
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
            const registration = await navigator.serviceWorker.register(`/sw.js?build=${__JK_BUILD_ID__}`, {
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
    const { data } = await axios.get('/api/push/key');
    const permission = await requestNotificationPermission();
    if (permission !== 'granted') throw new Error('Allow notifications in your browser settings to enable this device.');
    const registration = await navigator.serviceWorker.ready;
    const subscription = await registration.pushManager.getSubscription() || await registration.pushManager.subscribe({
        userVisibleOnly: true, applicationServerKey: urlBase64ToUint8Array(data.public_key),
    });
    await axios.post('/api/push/subscribe', subscription.toJSON());
    return subscription;
}

export async function unsubscribeFromPush() {
    if (!('serviceWorker' in navigator)) return;
    const registration = await navigator.serviceWorker.getRegistration();
    const subscription = await registration?.pushManager?.getSubscription();
    if (!subscription) return;
    await axios.delete('/api/push/subscribe', { data: { endpoint: subscription.endpoint } });
    await subscription.unsubscribe();
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

export { saveLessonOffline, getOfflineLesson, getDownloadedLessons, removeOfflineLesson, saveProgressOffline } from './lib/offlineStore';

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
