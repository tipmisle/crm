<?php

test('an appointment-only workspace does not expose order routes', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['orders_enabled' => false, 'appointments_enabled' => true]);

    $this->actingAs($user)->get(route('orders.index'))->assertStatus(404);
    $this->actingAs($user)->get(route('orders.create'))->assertStatus(404);
});

test('an appointment-only workspace does not expose product management routes', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['orders_enabled' => false, 'appointments_enabled' => true]);

    $this->actingAs($user)->post(route('products.store'), [
        'name' => 'Torta',
    ])->assertStatus(404);
});
