<?php

test('the demo picker page is publicly accessible', function () {
    $this->get(route('demo'))->assertOk();
});

test('visiting the demo picker page redirects to the dashboard when already authenticated', function () {
    [, $user] = createWorkspaceWithUser();

    $this->actingAs($user)
        ->get(route('demo'))
        ->assertRedirect(route('dashboard'));
});
