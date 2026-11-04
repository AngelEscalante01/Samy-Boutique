import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const original = await readFile(new URL('../../resources/js/services/web-push.js', import.meta.url), 'utf8');
let sequence = 0;
async function registration({ permission = 'granted', token = 'test-fcm-token', tokenError = false } = {}) {
    const calls = [];
    globalThis.window = { axios: { post: async (url, payload) => { calls.push({ url, payload }); } } };
    Object.defineProperty(globalThis, 'navigator', { configurable: true, value: { platform: 'QA', serviceWorker: { ready: Promise.resolve({ scope: 'http://localhost/', active: null }) } } });
    globalThis.Notification = { permission, requestPermission: async () => permission };
    globalThis.window.Notification = globalThis.Notification;
    globalThis.location = { hostname: 'localhost' };
    globalThis.localStorage = { getItem: () => 'qa-installation', setItem: () => {} };
    globalThis.__fcmTest = {
        initializeApp: () => ({}), getApps: () => [], deleteToken: async () => {},
        getMessaging: () => ({}), isSupported: async () => true, onMessage: () => {},
        getToken: async () => { if (tokenError) throw new Error('FCM unavailable'); return token; },
    };
    const source = original
        .replace(/^import .* from 'firebase\/app';$/m, 'const { initializeApp, getApps } = globalThis.__fcmTest;')
        .replace(/^import .* from 'firebase\/messaging';$/m, 'const { deleteToken, getMessaging, getToken, isSupported, onMessage } = globalThis.__fcmTest;')
        .replaceAll('import.meta.env', JSON.stringify({ VITE_FIREBASE_API_KEY: 'public-test-key', VITE_FIREBASE_VAPID_KEY: 'public-test-vapid' }));
    const module = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}#${++sequence}`);
    return { result: await module.enableWebPush(), calls };
}

test('push registration only reports enabled after obtaining and saving an FCM token', async () => {
    const { result, calls } = await registration();
    assert.equal(result.enabled, true);
    assert.equal(calls.length, 1);
    assert.equal(calls[0].payload.token, 'test-fcm-token');
    assert.equal(calls[0].payload.permission_status, 'granted');
});

test('permission alone never reports a linked device when FCM fails or returns no token', async () => {
    for (const options of [{ tokenError: true }, { token: null }]) {
        const { result, calls } = await registration(options);
        assert.equal(result.enabled, false);
        assert.equal(result.permission, 'granted');
        assert.equal(calls[0].payload.token, null);
    }
});

test('denied permission is preserved without claiming push delivery is enabled', async () => {
    const { result, calls } = await registration({ permission: 'denied' });
    assert.equal(result.enabled, false);
    assert.equal(calls[0].payload.permission_status, 'denied');
    assert.equal(calls[0].payload.token, null);
});
