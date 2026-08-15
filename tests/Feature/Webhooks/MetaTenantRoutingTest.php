<?php

use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\QueryException;

beforeEach(function () {
    config(['meta.webhook_verify_token' => 'test-verify-token', 'meta.app_secret' => 'test-secret']);
    config(['queue.default' => 'sync']);
});

function postSignedMetaWebhookForTenantTest(array $payload)
{
    $body = json_encode($payload);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'test-secret');

    return test()->call('POST', '/webhooks/meta', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_Hub_Signature_256' => $signature,
    ], $body);
}

test('the same external_account_id cannot be connected to two real workspaces at once', function () {
    [$workspaceA] = createWorkspaceWithUser();
    [$workspaceB] = createWorkspaceWithUser();

    createMetaChannel($workspaceA, 'instagram', 'ig_shared');

    expect(fn () => createMetaChannel($workspaceB, 'instagram', 'ig_shared'))
        ->toThrow(QueryException::class);
});

test('an inbound meta message only ever reaches the workspace that owns that account id', function () {
    [$workspaceA] = createWorkspaceWithUser();
    [$workspaceB] = createWorkspaceWithUser();

    createMetaChannel($workspaceA, 'instagram', 'ig_a');
    createMetaChannel($workspaceB, 'instagram', 'ig_b');

    $payload = [
        'object' => 'instagram',
        'entry' => [[
            'id' => 'ig_a',
            'messaging' => [[
                'sender' => ['id' => 'sender_x'],
                'recipient' => ['id' => 'ig_a'],
                'timestamp' => 1700000000000,
                'message' => ['mid' => 'mid_tenant_routing', 'text' => 'Hello A'],
            ]],
        ]],
    ];

    postSignedMetaWebhookForTenantTest($payload)->assertStatus(200);

    $message = Message::where('external_message_id', 'mid_tenant_routing')->first();
    expect($message)->not->toBeNull();

    $conversation = Conversation::withoutGlobalScopes()->find($message->conversation_id);
    expect($conversation->workspace_id)->toBe($workspaceA->id);
    expect($conversation->workspace_id)->not->toBe($workspaceB->id);
});

test('disconnecting a channel frees its external_account_id for another workspace', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $channel = createMetaChannel($workspace, 'instagram', 'ig_freed');

    $this->actingAs($user)->delete(route('integrations.meta.disconnect', $channel))->assertRedirect();

    expect($channel->fresh()->external_account_id)->toBeNull();

    [$otherWorkspace] = createWorkspaceWithUser();

    // No longer throws — the slot was freed by disconnecting above.
    createMetaChannel($otherWorkspace, 'instagram', 'ig_freed');

    expect(Channel::withoutGlobalScopes()->where('external_account_id', 'ig_freed')->where('status', 'connected')->count())->toBe(1);
});

test('webhook ingestion for an unowned account id never creates a message', function () {
    [$workspace] = createWorkspaceWithUser();
    createMetaChannel($workspace, 'instagram', 'ig_owned');

    $payload = [
        'object' => 'instagram',
        'entry' => [[
            'id' => 'ig_not_connected_anywhere',
            'messaging' => [[
                'sender' => ['id' => 'sender_y'],
                'recipient' => ['id' => 'ig_not_connected_anywhere'],
                'timestamp' => 1700000000000,
                'message' => ['mid' => 'mid_orphan', 'text' => 'Nobody owns this account'],
            ]],
        ]],
    ];

    postSignedMetaWebhookForTenantTest($payload)->assertStatus(200);

    expect(Message::where('external_message_id', 'mid_orphan')->exists())->toBeFalse();
});
