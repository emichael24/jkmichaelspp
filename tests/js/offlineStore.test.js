import { test, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import 'fake-indexeddb/auto';
import axios from 'axios';

function storage() {
    const values = new Map();
    return { getItem: key => values.get(key) ?? null, setItem: (key, value) => values.set(key, String(value)), removeItem: key => values.delete(key) };
}
globalThis.window = new EventTarget();
window.indexedDB = indexedDB;
window.location = { replace: () => {} };
globalThis.localStorage = storage();
globalThis.sessionStorage = storage();
const offline = await import('../../resources/js/lib/offlineStore.js');
const auth = { user: { uuid: 'a9f32109-6d03-435c-80a5-9713e4e8ad98' }, offline_context: 'session-one' };
let sends;
let failure;

function response(data, config) { return { data, status: 200, statusText: 'OK', headers: {}, config }; }
beforeEach(async () => {
    sends = 0;
    failure = null;
    await offline.clearOfflineData();
    await offline.setOfflineAccount(auth);
    axios.defaults.adapter = async config => {
        if (config.url === '/api/offline/session') return response({ owner: auth.user.uuid, context: auth.offline_context }, config);
        if (config.method === 'post') {
            sends++;
            if (failure) { const error = new Error('Synthetic failure'); error.response = failure; throw error; }
            return response({ accepted: true }, config);
        }
        return response({ lessonId: 1, title: 'Private negotiation notes', text_content: '<p>Confidential reading</p>', owner: auth.user.uuid, expiresAt: Date.now() + 86400000, lease: 'synthetic-lease' }, config);
    };
});

async function rows() {
    const db = await new Promise(resolve => { const request = indexedDB.open('jkm-private-offline-v2', 1); request.onsuccess = () => resolve(request.result); });
    const tx = db.transaction('records', 'readonly');
    const data = await new Promise(resolve => { const request = tx.objectStore('records').getAll(); request.onsuccess = () => resolve(request.result); });
    db.close();
    return data;
}

test('stored readings contain ciphertext and can be read only with the tab key', async () => {
    await offline.saveLessonOffline(1);
    const saved = await rows();
    assert.equal(saved.length, 1);
    assert.ok(saved[0].ciphertext instanceof ArrayBuffer);
    assert.equal(JSON.stringify(saved).includes('Private negotiation'), false);
    assert.equal(new TextDecoder().decode(saved[0].ciphertext).includes('Confidential'), false);
    assert.equal((await offline.getOfflineLesson(1)).title, 'Private negotiation notes');
    sessionStorage.removeItem('jkm-offline-session-v2');
    await assert.rejects(offline.getDownloadedLessons(), /Sign in online/);
});

test('switching accounts purges old readings and queued progress', async () => {
    await offline.saveLessonOffline(1);
    await offline.saveProgressOffline(1);
    await offline.setOfflineAccount({ user: { uuid: 'different-user' }, offline_context: 'different-session' });
    assert.deepEqual(await offline.getDownloadedLessons(), []);
    assert.deepEqual(await offline.getPendingProgress(), []);
    assert.deepEqual(await rows(), []);
});

test('signout removes records and the decryption key', async () => {
    await offline.saveLessonOffline(1);
    await offline.clearOfflineData();
    assert.deepEqual(await rows(), []);
    assert.equal(sessionStorage.getItem('jkm-offline-session-v2'), null);
    await assert.rejects(offline.saveProgressOffline(1), /Sign in online/);
});

test('failed sends preserve events and successful retries remove only acknowledged events', async () => {
    await offline.saveLessonOffline(1);
    const id = await offline.saveProgressOffline(1);
    failure = { status: 503 };
    await assert.rejects(offline.syncOfflineProgress());
    assert.equal((await offline.getPendingProgress())[0].event_id, id);
    failure = null;
    assert.deepEqual(await offline.syncOfflineProgress(), { accepted: 1, blocked: 0, pending: 0 });
    assert.equal(sends, 2);
    assert.deepEqual(await offline.getPendingProgress(), []);
});

test('revoked progress keeps an explicit pending event and removes reading content', async () => {
    await offline.saveLessonOffline(1);
    await offline.saveProgressOffline(1);
    failure = { status: 403 };
    assert.deepEqual(await offline.syncOfflineProgress(), { accepted: 0, blocked: 1, pending: 1 });
    assert.deepEqual(await offline.getDownloadedLessons(), []);
    assert.equal((await offline.getPendingProgress()).length, 1);
});

test('expired reading permissions remove data and prevent offline completion', async () => {
    await offline.saveLessonOffline(1);
    const realNow = Date.now;
    const future = realNow() + 86400001;
    try {
        Date.now = () => future;
        assert.deepEqual(await offline.getDownloadedLessons(), []);
        await assert.rejects(offline.saveProgressOffline(1), /Save this reading online/);
    } finally { Date.now = realNow; }
});

test('a changed server session cannot replay the previous account queue', async () => {
    await offline.saveLessonOffline(1);
    await offline.saveProgressOffline(1);
    axios.defaults.adapter = async config => response({ owner: 'another-account', context: 'another-session' }, config);
    await assert.rejects(offline.syncOfflineProgress(), /sign-in changed/);
    assert.deepEqual(await rows(), []);
    assert.equal(sends, 0);
});
