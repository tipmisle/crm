<?php

use App\Models\Conversation;

test('admin workspace detail never exposes integration tokens', function () {
    $admin = createPlatformAdmin();
    [$workspace] = createWorkspaceWithUser();
    createMetaChannel($workspace);

    $response = $this->actingAs($admin)->get(route('admin.workspaces.show', $workspace));

    $response->assertOk();
    $json = $response->getContent();

    expect($json)->not->toContain('test-user-token');
    expect($json)->not->toContain('test-page-token');
    expect($json)->not->toContain('access_token');
});

test('admin without a support grant cannot open a conversation with content', function () {
    $admin = createPlatformAdmin();
    [$workspace] = createWorkspaceWithUser();
    $channel = createMetaChannel($workspace);

    $conversation = Conversation::withoutGlobalScopes()->create([
        'workspace_id' => $workspace->id,
        'channel_id' => $channel->id,
        'external_conversation_id' => 'sender_x',
        'status' => 'new_enquiry',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.workspaces.support.conversation', [$workspace, $conversation]))
        ->assertForbidden();
});

test('normal admin metadata routes work with no support grant at all', function () {
    $admin = createPlatformAdmin();
    [$workspace] = createWorkspaceWithUser();

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    $this->actingAs($admin)->get(route('admin.workspaces.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.workspaces.show', $workspace))->assertOk();
    $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.integrations.index'))->assertOk();
});
