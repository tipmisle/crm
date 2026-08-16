<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatus;

test('owner can create an order status', function () {
    [$workspace, $owner] = createWorkspaceWithUser();

    $this->actingAs($owner)
        ->post(route('settings.statuses.order.store'), [
            'label' => 'Čaka na material',
            'color' => '#B45309',
            'bg' => '#FEF3C7',
        ])
        ->assertRedirect();

    $status = OrderStatus::where('workspace_id', $workspace->id)->where('label', 'Čaka na material')->first();

    expect($status)->not->toBeNull();
    expect($status->key)->toBe('caka_na_material');
});

test('creating a status with a label that collides with an existing key gets a unique suffix', function () {
    [$workspace, $owner] = createWorkspaceWithUser();

    $this->actingAs($owner)->post(route('settings.statuses.order.store'), [
        'label' => 'Novo',
        'color' => '#4B5563',
        'bg' => '#F1F2F4',
    ]);

    $keys = OrderStatus::where('workspace_id', $workspace->id)->pluck('key');

    expect($keys)->toContain('new');
    expect($keys)->toContain('novo');
});

test('owner can rename a status and existing orders keep referencing it by key', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $status = OrderStatus::where('workspace_id', $workspace->id)->where('key', 'new')->first();

    $order = Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Test Customer'])->id,
        'title' => 'Test order',
        'status' => $status->key,
        'payment_status' => 'unpaid',
    ]);

    $this->actingAs($owner)
        ->patch(route('settings.statuses.order.update', $status->id), ['label' => 'Sveže'])
        ->assertRedirect();

    expect($status->fresh()->label)->toBe('Sveže');
    expect($order->fresh()->status)->toBe('new');
    expect($order->fresh()->orderStatus->label)->toBe('Sveže');
});

test('setting a status as default unsets the default flag on every other status', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $new = OrderStatus::where('workspace_id', $workspace->id)->where('key', 'new')->first();
    $confirmed = OrderStatus::where('workspace_id', $workspace->id)->where('key', 'confirmed')->first();

    expect($new->is_default)->toBeTrue();

    $this->actingAs($owner)->patch(route('settings.statuses.order.update', $confirmed->id), ['is_default' => true]);

    expect($new->fresh()->is_default)->toBeFalse();
    expect($confirmed->fresh()->is_default)->toBeTrue();
});

test('a status currently used by an order cannot be deleted', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $status = OrderStatus::where('workspace_id', $workspace->id)->where('key', 'new')->first();

    Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Test Customer'])->id,
        'title' => 'Test order',
        'status' => $status->key,
        'payment_status' => 'unpaid',
    ]);

    $this->actingAs($owner)
        ->delete(route('settings.statuses.order.destroy', $status->id))
        ->assertStatus(422);

    expect(OrderStatus::find($status->id))->not->toBeNull();
});

test('a status in use can be deleted when its orders are reassigned to another status', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $status = OrderStatus::where('workspace_id', $workspace->id)->where('key', 'new')->first();
    $otherStatus = OrderStatus::where('workspace_id', $workspace->id)->where('key', 'quote_needed')->first();

    $order = Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Test Customer'])->id,
        'title' => 'Test order',
        'status' => $status->key,
        'payment_status' => 'unpaid',
    ]);

    $this->actingAs($owner)
        ->delete(route('settings.statuses.order.destroy', $status->id), ['reassign_to' => $otherStatus->key])
        ->assertRedirect();

    expect(OrderStatus::find($status->id))->toBeNull();
    expect($order->fresh()->status)->toBe($otherStatus->key);
});

test('an unused status can be deleted', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $status = OrderStatus::where('workspace_id', $workspace->id)->where('key', 'quote_needed')->first();

    $this->actingAs($owner)
        ->delete(route('settings.statuses.order.destroy', $status->id))
        ->assertRedirect();

    expect(OrderStatus::find($status->id))->toBeNull();
});

test('the last remaining order status cannot be deleted', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    OrderStatus::where('workspace_id', $workspace->id)->where('key', '!=', 'new')->delete();
    $last = OrderStatus::where('workspace_id', $workspace->id)->sole();

    $this->actingAs($owner)
        ->delete(route('settings.statuses.order.destroy', $last->id))
        ->assertStatus(422);

    expect(OrderStatus::find($last->id))->not->toBeNull();
});

test('reordering persists the new sort order', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $statuses = OrderStatus::where('workspace_id', $workspace->id)->orderBy('sort_order')->get();
    $reversed = $statuses->reverse()->pluck('id')->values();

    $this->actingAs($owner)
        ->post(route('settings.statuses.order.reorder'), ['ids' => $reversed->all()])
        ->assertRedirect();

    $reordered = OrderStatus::where('workspace_id', $workspace->id)->orderBy('sort_order')->pluck('id');

    expect($reordered->all())->toBe($reversed->all());
});

test('a member of another workspace cannot edit or delete a status they do not own', function () {
    [$workspaceA] = createWorkspaceWithUser();
    [$workspaceB, $ownerB] = createWorkspaceWithUser();

    $statusA = OrderStatus::where('workspace_id', $workspaceA->id)->where('key', 'new')->first();

    $this->actingAs($ownerB)
        ->patch(route('settings.statuses.order.update', $statusA->id), ['label' => 'Hacked'])
        ->assertStatus(404);

    $this->actingAs($ownerB)
        ->delete(route('settings.statuses.order.destroy', $statusA->id))
        ->assertStatus(404);

    expect($statusA->fresh()->label)->toBe('Novo');
});

test('another workspaces order statuses never appear in a users shared inertia props', function () {
    [$workspaceA, $ownerA] = createWorkspaceWithUser();
    [$workspaceB] = createWorkspaceWithUser();

    $response = $this->actingAs($ownerA)->get(route('settings.statuses.edit'));

    $response->assertInertia(fn ($page) => $page
        ->where('orderStatuses', fn ($statuses) => collect($statuses)->pluck('id')->intersect(
            OrderStatus::where('workspace_id', $workspaceB->id)->pluck('id')
        )->isEmpty())
    );
});
