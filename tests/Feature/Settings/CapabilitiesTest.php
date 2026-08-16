<?php

test('accepts_deposit defaults to true and can be turned off', function () {
    [$workspace, $owner] = createWorkspaceWithUser();

    expect($workspace->fresh()->accepts_deposit)->toBeTrue();

    $this->actingAs($owner)->patch(route('settings.capabilities.update'), [
        'orders_enabled' => true,
        'appointments_enabled' => false,
        'accepts_deposit' => false,
    ])->assertRedirect();

    expect($workspace->fresh()->accepts_deposit)->toBeFalse();
});

test('the order detail page exposes accepts_deposit via the shared workspace prop', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    [$order] = createOrderWithConversation($workspace);

    $workspace->update(['accepts_deposit' => false]);

    $response = $this->actingAs($owner)->get(route('orders.show', $order));

    $response->assertInertia(fn ($page) => $page->where('workspace.accepts_deposit', false));
});
