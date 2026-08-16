<?php

use App\Enums\MessageStatus;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Http;

test('a failed meta send is never marked as sent and shows an error', function () {
    Http::fake([
        '*/messages*' => Http::response([
            'error' => ['message' => 'Message outside of allowed window', 'code' => 10, 'error_subcode' => 2018278],
        ], 400),
    ]);

    [$workspace, $user] = createWorkspaceWithUser();
    $channel = createMetaChannel($workspace, 'instagram', 'ig_123');
    $conversation = Conversation::create([
        'workspace_id' => $workspace->id,
        'channel_id' => $channel->id,
        'external_conversation_id' => 'sender_ext_1',
        'status' => 'new_enquiry',
    ]);

    $response = $this->actingAs($user)->post(route('inbox.messages.store', $conversation->id), [
        'body' => 'This should fail to send',
    ]);

    $response->assertSessionHas('error');

    $message = Message::where('conversation_id', $conversation->id)->latest()->first();
    expect($message->status)->toBe(MessageStatus::Failed);
    expect($message->failure_reason)->not->toBeNull();
});

test('a successful meta send is marked as sent with the external message id', function () {
    Http::fake([
        '*/messages*' => Http::response(['message_id' => 'mid_sent_1'], 200),
    ]);

    [$workspace, $user] = createWorkspaceWithUser();
    $channel = createMetaChannel($workspace, 'instagram', 'ig_123');
    $conversation = Conversation::create([
        'workspace_id' => $workspace->id,
        'channel_id' => $channel->id,
        'external_conversation_id' => 'sender_ext_1',
        'status' => 'new_enquiry',
    ]);

    $this->actingAs($user)->post(route('inbox.messages.store', $conversation->id), [
        'body' => 'This should succeed',
    ])->assertSessionMissing('error');

    $message = Message::where('conversation_id', $conversation->id)->latest()->first();
    expect($message->status)->toBe(MessageStatus::Sent);
    expect($message->external_message_id)->toBe('mid_sent_1');
});

test('sending is blocked when the channel is disconnected', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $channel = createMetaChannel($workspace, 'instagram', 'ig_123', 'token', 'disconnected');
    $conversation = Conversation::create([
        'workspace_id' => $workspace->id,
        'channel_id' => $channel->id,
        'external_conversation_id' => 'sender_ext_1',
        'status' => 'new_enquiry',
    ]);

    $this->actingAs($user)->post(route('inbox.messages.store', $conversation->id), [
        'body' => 'Should not send',
    ])->assertSessionHas('error');

    expect(Message::where('conversation_id', $conversation->id)->count())->toBe(0);
});
