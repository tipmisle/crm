<?php

use App\Enums\MessageSenderType;
use App\Models\Conversation;
use App\Models\Message;

test('viewing a conversation with a customer message computes reply availability without error', function () {
    [$workspace, $user] = createWorkspaceWithUser();

    $channel = createMetaChannel($workspace, 'instagram', 'ig_reply_availability');
    $conversation = Conversation::create([
        'workspace_id' => $workspace->id,
        'channel_id' => $channel->id,
        'external_conversation_id' => 'sender_reply_availability',
        'status' => 'new_enquiry',
    ]);

    Message::create([
        'conversation_id' => $conversation->id,
        'sender_type' => MessageSenderType::Customer,
        'body' => 'Hi, is this available?',
        'message_type' => 'text',
        'status' => 'delivered',
        'sent_at' => now()->subHours(2),
    ]);

    $response = $this->actingAs($user)->get(route('inbox.show', $conversation));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('conversation.can_reply', true));
});
