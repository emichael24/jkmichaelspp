import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';

async function worker() {
    const handlers = new Map();
    const stores = new Map([['jkm-offline-assets', new Map([['https://lms.test/media/private', new Response('old private file')]])]]);
    const messages = [];
    const requests = [];
    const origin = 'https://lms.test';
    let connected = true;
    const key = input => new URL(typeof input === 'string' ? input : input.url, origin).href;
    const caches = {
        keys: async () => [...stores.keys()], delete: async name => stores.delete(name),
        open: async name => {
            if (!stores.has(name)) stores.set(name, new Map());
            const store = stores.get(name);
            return { match: async input => store.get(key(input))?.clone(), put: async (input, response) => store.set(key(input), response.clone()),
                addAll: async inputs => { for (const input of inputs) store.set(key(input), await fetchMock(input)); } };
        },
        match: async input => { for (const store of stores.values()) if (store.has(key(input))) return store.get(key(input)).clone(); },
    };
    async function fetchMock(input) {
        requests.push(key(input));
        if (!connected) throw new Error('Offline');
        return new Response(key(input).endsWith('/offline') ? '<html><script type="module" src="/build/reader-hash.js"></script><link rel="modulepreload" href="/build/shared-hash.js"></html>' : 'network response');
    }
    let legacyDeleted = false;
    vm.runInNewContext(await readFile(new URL('../../public/sw.js', import.meta.url), 'utf8'), {
        URL, Response, caches, fetch: fetchMock, indexedDB: { deleteDatabase: name => { legacyDeleted = name === 'jkm-offline'; } },
        self: { location: { origin, href: `${origin}/sw.js?build=release-test` }, addEventListener: (name, handler) => handlers.set(name, handler), skipWaiting: async () => {},
            clients: { claim: async () => {}, matchAll: async () => [{ postMessage: message => messages.push(message) }] } },
    });
    async function event(name, data = {}) {
        let result;
        handlers.get(name)({ ...data, waitUntil: promise => { result = promise; }, respondWith: promise => { result = promise; } });
        return result;
    }
    return { event, stores, messages, requests, disconnect: () => { connected = false; }, legacyDeleted: () => legacyDeleted };
}

test('installation caches only the anonymous shell and public bundles; activation purges legacy private caches', async () => {
    const sw = await worker();
    await sw.event('install'); await sw.event('activate');
    assert.equal(sw.stores.has('jkm-offline-assets'), false);
    assert.equal(sw.legacyDeleted(), true);
    assert.ok(sw.stores.has('jkm-v7-static-release-test'));
    assert.deepEqual([...sw.stores.get('jkm-v7-static-release-test').keys()].sort(), [
        'https://lms.test/build/reader-hash.js', 'https://lms.test/build/shared-hash.js', 'https://lms.test/manifest.json', 'https://lms.test/offline',
    ]);
});

test('authenticated pages and private media are never cached; failed navigation returns the anonymous reader', async () => {
    const sw = await worker(); await sw.event('install'); await sw.event('activate');
    for (const path of ['/dashboard', '/api/profile', '/media/private']) await sw.event('fetch', { request: { url: `https://lms.test${path}`, method: 'GET', mode: 'cors' } });
    assert.equal([...sw.stores.values()].some(store => [...store.keys()].some(url => /dashboard|profile|media/.test(url))), false);
    sw.disconnect();
    const page = await sw.event('fetch', { request: { url: 'https://lms.test/dashboard', method: 'GET', mode: 'navigate' } });
    assert.match(await page.text(), /reader-hash/);
    const media = await sw.event('fetch', { request: { url: 'https://lms.test/media/private', method: 'GET', mode: 'cors' } });
    assert.equal(media.status, 503);
});

test('background synchronization delegates to authenticated clients without sending queued private data', async () => {
    const sw = await worker();
    await sw.event('sync', { tag: 'sync-progress' });
    assert.equal(sw.messages.length, 1);
    assert.equal(sw.messages[0].type, 'SYNC_PROGRESS');
    assert.equal(sw.requests.length, 0);
    assert.equal(await sw.event('fetch', { request: { url: 'https://lms.test/api/progress/sync', method: 'POST' } }), undefined);
});
