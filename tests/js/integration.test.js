import test from 'node:test';
import assert from 'node:assert/strict';
import { integrationError, whatsappQrSource } from '../../resources/js/lib/integration.js';

test('QR accepts provider data URI without double prefix and supports raw base64', () => {
    assert.equal(whatsappQrSource('data:image/png;base64,YWJj'), 'data:image/png;base64,YWJj');
    assert.equal(whatsappQrSource('YWJj'), 'data:image/png;base64,YWJj');
    for (const value of [null, '', 'https://external.example/image', '<svg />', 'data:image/svg+xml;base64,YWJj']) assert.equal(whatsappQrSource(value), '');
});

test('validation and session failures provide actionable feedback', () => {
    assert.equal(integrationError({ response: { data: { errors: { voice_id: ['Choose a voice.'] } } } }), 'Choose a voice.');
    assert.match(integrationError({ response: { status: 419 } }), /sign in/);
    assert.match(integrationError({ response: { status: 429 } }), /Wait a minute/);
    assert.equal(integrationError({ response: { data: { error: 'Service unavailable.' } } }), 'Service unavailable.');
    assert.match(integrationError(new Error('internal detail')) , /retry/);
});
