import axios from 'axios';

const DB_NAME = 'jkm-private-offline-v2';
const SESSION_KEY = 'jkm-offline-session-v2';
const ACCOUNT_KEY = 'jkm-offline-account-v2';
const MAX_AGE = 24 * 60 * 60 * 1000;
const MAX_BYTES = 20 * 1024 * 1024;
let initializing = Promise.resolve();
let syncing;
const encoder = new TextEncoder();
const decoder = new TextDecoder();

function session() {
    try {
        const value = JSON.parse(sessionStorage.getItem(SESSION_KEY));
        return value?.account === localStorage.getItem(ACCOUNT_KEY) ? value : null;
    } catch { return null; }
}

async function database() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, 1);
        request.onupgradeneeded = () => request.result.createObjectStore('records', { keyPath: 'id' });
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(new Error('This browser could not open protected offline storage.'));
    });
}

async function query(mode, operation) {
    const db = await database();
    return new Promise((resolve, reject) => {
        const tx = db.transaction('records', mode);
        const request = operation(tx.objectStore('records'));
        tx.oncomplete = () => { db.close(); resolve(request?.result); };
        tx.onabort = tx.onerror = () => { db.close(); reject(new Error('Offline storage is unavailable or full.')); };
    });
}

async function purgeLegacy() {
    const legacy = indexedDB.deleteDatabase('jkm-offline');
    legacy.onblocked = () => window.dispatchEvent(new Event('offline-storage-blocked'));
    if ('caches' in window) {
        await Promise.all((await caches.keys()).filter(k => k.startsWith('jkm-') && !k.startsWith('jkm-v7-static')).map(k => caches.delete(k)));
    }
}

export async function clearOfflineData() {
    sessionStorage.removeItem(SESSION_KEY);
    localStorage.removeItem(ACCOUNT_KEY);
    await query('readwrite', store => store.clear());
    await purgeLegacy();
    window.dispatchEvent(new Event('offline-data-changed'));
}

export function setOfflineAccount(auth) {
    initializing = initializing.catch(() => {}).then(async () => {
        if (!crypto.subtle || !window.indexedDB) return;
        await purgeLegacy();
        const account = auth?.user?.uuid && auth.offline_context ? `${auth.user.uuid}:${auth.offline_context}` : null;
        if (!account) { await clearOfflineData(); return; }
        if (localStorage.getItem(ACCOUNT_KEY) !== account) {
            await clearOfflineData();
            localStorage.setItem(ACCOUNT_KEY, account);
        }
        if (!session()) {
            const key = crypto.getRandomValues(new Uint8Array(32));
            sessionStorage.setItem(SESSION_KEY, JSON.stringify({ account, owner: auth.user.uuid, context: auth.offline_context, namespace: crypto.randomUUID(), key: Array.from(key) }));
        }
        const records = await query('readonly', store => store.getAll());
        for (const record of records) {
            if (record.account !== account || record.expires <= Date.now()) await query('readwrite', store => store.delete(record.id));
        }
    });
    return initializing;
}

async function context() {
    await initializing;
    const value = session();
    if (!value || !crypto.subtle) throw new Error('Sign in online to enable protected reading in this tab.');
    return value;
}

async function keyFor(ctx) {
    return crypto.subtle.importKey('raw', new Uint8Array(ctx.key), 'AES-GCM', false, ['encrypt', 'decrypt']);
}

function additionalData(record) {
    return encoder.encode(JSON.stringify([record.id, record.account, record.expires]));
}

async function put(kind, id, value, expires) {
    const ctx = await context();
    const encoded = encoder.encode(JSON.stringify(value));
    if (encoded.length > 2 * 1024 * 1024) throw new Error('This reading is too large to save offline (2 MB limit).');
    const record = { id: `${ctx.namespace}:${kind}:${id}`, account: ctx.account, namespace: ctx.namespace, kind, expires: Math.min(expires, Date.now() + MAX_AGE), iv: crypto.getRandomValues(new Uint8Array(12)), size: encoded.length };
    const records = await query('readonly', store => store.getAll());
    if (records.filter(r => r.id !== record.id).reduce((sum, r) => sum + r.size, 0) + record.size > MAX_BYTES || records.filter(r => r.kind === kind && r.id !== record.id).length >= (kind === 'lesson' ? 50 : 200)) throw new Error('Offline storage limit reached. Remove saved readings or sync pending progress.');
    record.ciphertext = await crypto.subtle.encrypt({ name: 'AES-GCM', iv: record.iv, additionalData: additionalData(record) }, await keyFor(ctx), encoded);
    if (session()?.namespace !== ctx.namespace) throw new Error('Your sign-in changed. Please reopen this page.');
    await query('readwrite', store => store.put(record));
    window.dispatchEvent(new Event('offline-data-changed'));
}

async function list(kind) {
    const ctx = await context();
    const records = await query('readonly', store => store.getAll());
    const values = [];
    for (const record of records.filter(r => r.namespace === ctx.namespace && r.kind === kind)) {
        if (record.expires <= Date.now()) { await query('readwrite', store => store.delete(record.id)); continue; }
        try {
            const bytes = await crypto.subtle.decrypt({ name: 'AES-GCM', iv: record.iv, additionalData: additionalData(record) }, await keyFor(ctx), record.ciphertext);
            values.push({ ...JSON.parse(decoder.decode(bytes)), size: record.size });
        } catch { await query('readwrite', store => store.delete(record.id)); }
    }
    if (session()?.namespace !== ctx.namespace) return [];
    return values;
}

async function remove(kind, id) {
    const ctx = await context();
    await query('readwrite', store => store.delete(`${ctx.namespace}:${kind}:${id}`));
    window.dispatchEvent(new Event('offline-data-changed'));
}

export async function saveLessonOffline(lessonId) {
    const ctx = await context();
    const { data } = await axios.get(`/lessons/${lessonId}/offline`);
    if (data.owner !== ctx.owner) throw new Error('Your sign-in changed. Please reopen this page.');
    await put('lesson', lessonId, { ...data, downloadedAt: Date.now() }, data.expiresAt);
}

export const getDownloadedLessons = () => list('lesson');
export const getOfflineLesson = async id => (await list('lesson')).find(lesson => Number(lesson.lessonId) === Number(id));
export const removeOfflineLesson = id => remove('lesson', id);
export const getPendingProgress = () => list('progress');

export async function saveProgressOffline(lessonId) {
    const lesson = await getOfflineLesson(lessonId);
    if (!lesson) throw new Error('Save this reading online before recording offline completion.');
    const data = { event_id: crypto.randomUUID(), owner: lesson.owner, lesson_id: Number(lessonId), lease: lesson.lease, complete: true, progress_pct: 100, time_spent_seconds: 0 };
    await put('progress', data.event_id, data, lesson.expiresAt);
    return data.event_id;
}

async function sync() {
    const ctx = await context();
    const { data: auth } = await axios.get('/api/offline/session');
    if (auth.owner !== ctx.owner || auth.context !== ctx.context) { await clearOfflineData(); throw new Error('Your sign-in changed. Sign in again before syncing.'); }
    let accepted = 0;
    let blocked = 0;
    for (const item of await list('progress')) {
        if (session()?.namespace !== ctx.namespace) throw new Error('Your sign-in changed.');
        try {
            const { size, ...payload } = item;
            const { data } = await axios.post('/api/progress/sync', payload);
            if (data.accepted !== true) throw new Error('The server did not confirm this progress.');
            await remove('progress', item.event_id);
            accepted++;
        } catch (error) {
            if ([403, 404, 409, 410, 422].includes(error.response?.status)) {
                // Preserve the encrypted event for explicit removal or retry, but remove revoked content.
                await removeOfflineLesson(item.lesson_id);
                blocked++;
            } else { throw error; }
        }
    }
    for (const lesson of await list('lesson')) {
        try { await axios.get(`/lessons/${lesson.lessonId}/offline`); }
        catch (error) { if ([403, 404, 410, 422].includes(error.response?.status)) await removeOfflineLesson(lesson.lessonId); else throw error; }
    }
    return { accepted, blocked, pending: (await list('progress')).length };
}

export function syncOfflineProgress() {
    if (!syncing) syncing = sync().finally(() => { syncing = null; });
    return syncing;
}

export async function discardPendingProgress() {
    for (const item of await list('progress')) await remove('progress', item.event_id);
}

window.addEventListener('storage', event => {
    if (event.key === ACCOUNT_KEY && !session()) {
        sessionStorage.removeItem(SESSION_KEY);
        window.location.replace('/login');
    }
});
window.addEventListener('online', () => { if (session()) syncOfflineProgress().catch(() => window.dispatchEvent(new Event('offline-sync-failed'))); });
navigator.serviceWorker?.addEventListener('message', event => {
    if (event.data?.type === 'SYNC_PROGRESS' && session()) syncOfflineProgress().catch(() => window.dispatchEvent(new Event('offline-sync-failed')));
});
