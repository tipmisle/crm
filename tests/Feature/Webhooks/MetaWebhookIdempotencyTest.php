<?php

use App\Models\Message;

beforeEach(function () {
    config(['meta.webhook_verify_token' => 'test-verify-token', 'meta.app_secret' => 'test-secret']);
    config(['queue.default' => 'sync']);
});

function postSignedMetaWebhook(array $payload)
{
    $body = json_encode($payload);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'test-secret');

    return test()->call('POST', '/webhooks/meta', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_Hub_Signature_256' => $signature,
    ], $body);
}

test('the same meta message is never ingested twice', function () {
    [$workspace] = createWorkspaceWithUser();
    createMetaChannel($workspace, 'instagram', 'ig_123');

    $payload = [
        'object' => 'instagram',
        'entry' => [[
            'id' => 'ig_123',
            'messaging' => [[
                'sender' => ['id' => 'sender_ext_1'],
                'recipient' => ['id' => 'ig_123'],
                'timestamp' => 1700000000000,
                'message' => ['mid' => 'mid_duplicate_1', 'text' => 'Hello there'],
            ]],
        ]],
    ];

    postSignedMetaWebhook($payload)->assertStatus(200);
    postSignedMetaWebhook($payload)->assertStatus(200); // Meta retry

    expect(Message::where('external_message_id', 'mid_duplicate_1')->count())->toBe(1);
});

test('unknown meta account ids are ignored without error', function () {
    $payload = [
        'object' => 'instagram',
        'entry' => [[
            'id' => 'unknown_account',
            'messaging' => [[
                'sender' => ['id' => 'sender_ext_1'],
                'recipient' => ['id' => 'unknown_account'],
                'timestamp' => 1700000000000,
                'message' => ['mid' => 'mid_unknown', 'text' => 'Hello'],
            ]],
        ]],
    ];

    postSignedMetaWebhook($payload)->assertStatus(200);

    expect(Message::where('external_message_id', 'mid_unknown')->exists())->toBeFalse();
});

test('echo messages are not ingested as inbound', function () {
    [$workspace] = createWorkspaceWithUser();
    createMetaChannel($workspace, 'instagram', 'ig_123');

    $payload = [
        'object' => 'instagram',
        'entry' => [[
            'id' => 'ig_123',
            'messaging' => [[
                'sender' => ['id' => 'ig_123'],
                'recipient' => ['id' => 'sender_ext_1'],
                'timestamp' => 1700000000000,
                'message' => ['mid' => 'mid_echo', 'text' => 'Our own reply', 'is_echo' => true],
            ]],
        ]],
    ];

    postSignedMetaWebhook($payload)->assertStatus(200);

    expect(Message::where('external_message_id', 'mid_echo')->exists())->toBeFalse();
});
