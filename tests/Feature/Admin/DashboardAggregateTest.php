<?php

test('the admin dashboard counts connected integrations across real workspaces correctly', function () {
    $admin = createPlatformAdmin();

    [$workspaceA] = createWorkspaceWithUser();
    [$workspaceB] = createWorkspaceWithUser();

    createMetaChannel($workspaceA, 'instagram', 'ig_dash_a');
    createMetaChannel($workspaceB, 'facebook_messenger', 'fb_dash_b');

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('stats.instagram_connected', 1)
        ->where('stats.facebook_connected', 1)
    );
});
