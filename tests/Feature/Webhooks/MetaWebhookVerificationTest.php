<?php

beforeEach(function () {
    config(['meta.webhook_verify_token' => 'test-verify-token']);
});

test('meta webhook verification succeeds with correct token', function () {
    $response = $this->get('/webhooks/meta?'.http_build_query([
        'hub_mode' => 'subscribe',
        'hub_verify_token' => 'test-verify-token',
        'hub_challenge' => 'challenge-123',
    ]));

    $response->assertStatus(200);
    $response->assertSee('challenge-123');
});

test('meta webhook verification fails with incorrect token', function () {
    $response = $this->get('/webhooks/meta?'.http_build_query([
        'hub_mode' => 'subscribe',
        'hub_verify_token' => 'wrong-token',
        'hub_challenge' => 'challenge-123',
    ]));

    $response->assertStatus(403);
});

test('meta webhook verification fails without subscribe mode', function () {
    $response = $this->get('/webhooks/meta?'.http_build_query([
        'hub_mode' => 'unsubscribe',
        'hub_verify_token' => 'test-verify-token',
        'hub_challenge' => 'challenge-123',
    ]));

    $response->assertStatus(403);
});

test('meta webhook post rejects requests with invalid signature', function () {
    config(['meta.app_secret' => 'test-secret']);

    $response = $this->postJson('/webhooks/meta', ['object' => 'instagram', 'entry' => []], [
        'X-Hub-Signature-256' => 'sha256=invalid',
    ]);

    $response->assertStatus(403);
});

test('meta webhook post accepts requests with a valid signature', function () {
    config(['meta.app_secret' => 'test-secret']);

    $payload = json_encode(['object' => 'instagram', 'entry' => []]);
    $signature = 'sha256='.hash_hmac('sha256', $payload, 'test-secret');

    $response = $this->call('POST', '/webhooks/meta', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_Hub_Signature_256' => $signature,
    ], $payload);

    $response->assertStatus(200);
    $response->assertSee('EVENT_RECEIVED');
});
