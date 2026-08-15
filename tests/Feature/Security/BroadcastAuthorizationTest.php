<?php

// The test environment's default broadcaster is 'null' (no websocket
// infra in CI), whose auth() is a total no-op — it would make every
// assertion below pass trivially regardless of routes/channels.php.
// Switch to 'reverb' (same Pusher-protocol auth flow used in production)
// so the registered channel-authorization callback actually runs; auth
// signing is local HMAC, no network call needed for these assertions.
beforeEach(function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
        'broadcasting.connections.reverb.options.host' => '127.0.0.1',
        'broadcasting.connections.reverb.options.port' => 8080,
        'broadcasting.connections.reverb.options.scheme' => 'http',
        'broadcasting.connections.reverb.options.useTLS' => false,
    ]);

    // routes/channels.php already ran once at app boot against whatever
    // was the default driver then (the test env's 'null' broadcaster,
    // which never enforces channel callbacks at all). Re-require it now
    // that a real Pusher-protocol driver is active, so the callbacks in
    // routes/channels.php actually get registered on it.
    require base_path('routes/channels.php');
});

test('a user can authorize their own workspace inbox channel', function () {
    [$workspace, $user] = createWorkspaceWithUser();

    $this->actingAs($user)
        ->post('/broadcasting/auth', [
            'channel_name' => "private-workspace.{$workspace->id}.inbox",
            'socket_id' => '1234.1234',
        ])
        ->assertOk();
});

test('a user cannot authorize another workspace inbox channel', function () {
    [, $userA] = createWorkspaceWithUser();
    [$workspaceB] = createWorkspaceWithUser();

    $this->actingAs($userA)
        ->post('/broadcasting/auth', [
            'channel_name' => "private-workspace.{$workspaceB->id}.inbox",
            'socket_id' => '1234.1234',
        ])
        ->assertStatus(403);
});

test('an anonymous user cannot authorize any workspace inbox channel', function () {
    [$workspace] = createWorkspaceWithUser();

    $this->post('/broadcasting/auth', [
        'channel_name' => "private-workspace.{$workspace->id}.inbox",
        'socket_id' => '1234.1234',
    ])->assertForbidden();
});
