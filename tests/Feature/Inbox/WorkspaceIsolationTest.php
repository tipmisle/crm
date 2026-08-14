<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Http;

test('a user cannot view a conversation belonging to another workspace', function () {
    [$workspaceA, $userA] = createWorkspaceWithUser();
    [$workspaceB] = createWorkspaceWithUser();

    $channelB = createMetaChannel($workspaceB, 'instagram', 'ig_b');
    $conversationB = Conversation::create([
        'workspace_id' => $workspaceB->id,
        'channel_id' => $channelB->id,
        'external_conversation_id' => 'sender_b',
        'status' => 'new_enquiry',
    ]);

    $this->actingAs($userA)
        ->get(route('inbox.show', $conversationB->id))
        ->assertStatus(404);
});

test('a user cannot send a message through another workspace conversation', function () {
    Http::fake(['*' => Http::response(['message_id' => 'mid_1'], 200)]);

    [$workspaceA, $userA] = createWorkspaceWithUser();
    [$workspaceB] = createWorkspaceWithUser();

    $channelB = createMetaChannel($workspaceB, 'instagram', 'ig_b');
    $conversationB = Conversation::create([
        'workspace_id' => $workspaceB->id,
        'channel_id' => $channelB->id,
        'external_conversation_id' => 'sender_b',
        'status' => 'new_enquiry',
    ]);

    $this->actingAs($userA)
        ->post(route('inbox.messages.store', $conversationB->id), ['body' => 'Hi there'])
        ->assertStatus(404);

    Http::assertNothingSent();
});

test('conversation list only shows the current workspace conversations', function () {
    [$workspaceA, $userA] = createWorkspaceWithUser();
    [$workspaceB] = createWorkspaceWithUser();

    $channelA = createMetaChannel($workspaceA, 'instagram', 'ig_a');
    $channelB = createMetaChannel($workspaceB, 'instagram', 'ig_b');

    Conversation::create([
        'workspace_id' => $workspaceA->id,
        'channel_id' => $channelA->id,
        'external_conversation_id' => 'sender_a',
        'status' => 'new_enquiry',
    ]);

    Conversation::create([
        'workspace_id' => $workspaceB->id,
        'channel_id' => $channelB->id,
        'external_conversation_id' => 'sender_b',
        'status' => 'new_enquiry',
    ]);

    $response = $this->actingAs($userA)->get(route('inbox.index'));

    $response->assertInertia(fn ($page) => $page
        ->component('Inbox/Index')
        ->has('conversations', 1)
    );
});
