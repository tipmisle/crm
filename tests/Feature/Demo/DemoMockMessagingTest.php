<?php

use App\Enums\MessageSenderType;
use App\Enums\MessageStatus;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\Http;

test('replying in a demo conversation never calls a real external messaging API', function () {
    Http::fake();

    $workspace = Workspace::create([
        'name' => 'Demo Test Biz',
        'slug' => 'demo-test-biz-'.uniqid(),
        'is_demo' => true,
        'demo_variant' => 'services',
        'demo_expires_at' => now()->addHours(4),
    ]);

    $user = User::factory()->create(['current_workspace_id' => $workspace->id, 'is_demo' => true]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => 'owner']);

    $channel = Channel::create([
        'workspace_id' => $workspace->id,
        'type' => 'instagram',
        'display_name' => 'Demo IG',
        'status' => 'connected',
        'connected_at' => now(),
    ]);

    $conversation = Conversation::create([
        'workspace_id' => $workspace->id,
        'channel_id' => $channel->id,
        'external_conversation_id' => 'demo_sender_1',
        'status' => 'new_enquiry',
    ]);

    Message::create([
        'conversation_id' => $conversation->id,
        'sender_type' => MessageSenderType::Customer,
        'body' => 'Živjo, imate prost termin?',
        'message_type' => 'text',
        'sent_at' => now()->subMinutes(5),
    ]);

    $response = $this->actingAs($user)->post(route('inbox.messages.store', $conversation->id), [
        'body' => 'Živjo! Da, imam prost termin.',
    ]);

    $response->assertSessionMissing('error');

    Http::assertNothingSent();

    $reply = Message::where('conversation_id', $conversation->id)
        ->where('sender_type', MessageSenderType::Business)
        ->latest()
        ->first();

    expect($reply)->not->toBeNull();
    expect($reply->status)->toBe(MessageStatus::Sent);
});
