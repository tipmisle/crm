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
    $status = OrderStatus::where('workspace_id', $workspace->id)->where('key', 'confirmed')->first();
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

test('the status flagged default, completed, cancelled, or refunded cannot be deleted, even with a reassignment target', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $default = OrderStatus::where('workspace_id', $workspace->id)->where('key', 'new')->first();
    $completed = OrderStatus::where('workspace_id', $workspace->id)->where('key', 'completed')->first();
    $cancelled = OrderStatus::where('workspace_id', $workspace->id)->where('key', 'cancelled')->first();
    $refunded = OrderStatus::where('workspace_id', $workspace->id)->where('key', 'refunded')->first();
    $other = OrderStatus::where('workspace_id', $workspace->id)->where('key', 'quote_needed')->first();

    foreach ([$default, $completed, $cancelled, $refunded] as $protected) {
        $this->actingAs($owner)
            ->delete(route('settings.statuses.order.destroy', $protected->id), ['reassign_to' => $other->key])
            ->assertStatus(422);

        expect(OrderStatus::find($protected->id))->not->toBeNull();
    }
});

test('moving the cancelled flag to another status frees the previous status for deletion', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $cancelled = OrderStatus::where('workspace_id', $workspace->id)->where('key', 'cancelled')->first();
    $quoteNeeded = OrderStatus::where('workspace_id', $workspace->id)->where('key', 'quote_needed')->first();

    $this->actingAs($owner)->patch(route('settings.statuses.order.update', $quoteNeeded->id), ['is_cancelled' => true]);

    expect($cancelled->fresh()->is_cancelled)->toBeFalse();
    expect($quoteNeeded->fresh()->is_cancelled)->toBeTrue();

    $this->actingAs($owner)
        ->delete(route('settings.statuses.order.destroy', $cancelled->id))
        ->assertRedirect();

    expect(OrderStatus::find($cancelled->id))->toBeNull();
});

test('moving the completed flag to another status frees the previous status for deletion', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $completed = OrderStatus::where('workspace_id', $workspace->id)->where('key', 'completed')->first();
    $ready = OrderStatus::where('workspace_id', $workspace->id)->where('key', 'ready')->first();

    $this->actingAs($owner)->patch(route('settings.statuses.order.update', $ready->id), ['is_completed' => true]);

    expect($completed->fresh()->is_completed)->toBeFalse();
    expect($ready->fresh()->is_completed)->toBeTrue();

    $this->actingAs($owner)
        ->delete(route('settings.statuses.order.destroy', $completed->id))
        ->assertRedirect();

    expect(OrderStatus::find($completed->id))->toBeNull();
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

test('a crafted request cannot flag one order status with two mutually exclusive lifecycle roles at once', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $status = OrderStatus::where('workspace_id', $workspace->id)->where('key', 'ready')->first();

    $this->actingAs($owner)
        ->patch(route('settings.statuses.order.update', $status->id), [
            'is_completed' => true,
            'is_cancelled' => true,
        ])
        ->assertStatus(422);

    $status->refresh();
    expect($status->is_completed)->toBeFalse();
    expect($status->is_cancelled)->toBeFalse();
});

test('a follow-up request cannot move a second lifecycle role onto a status that already holds a different one', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $status = OrderStatus::where('workspace_id', $workspace->id)->where('key', 'ready')->first();

    // First request: legitimately move is_cancelled onto this status.
    $this->actingAs($owner)
        ->patch(route('settings.statuses.order.update', $status->id), ['is_cancelled' => true])
        ->assertRedirect();

    expect($status->fresh()->is_cancelled)->toBeTrue();

    // Second request: move is_completed onto the SAME status, without
    // mentioning is_cancelled at all. Silently clearing is_cancelled here
    // would drop the workspace to zero statuses holding that role, so this
    // must be rejected instead — the conflict has to be resolved first by
    // moving is_cancelled to a different status.
    $this->actingAs($owner)
        ->patch(route('settings.statuses.order.update', $status->id), ['is_completed' => true])
        ->assertStatus(422);

    $status->refresh();
    expect($status->is_completed)->toBeFalse();
    expect($status->is_cancelled)->toBeTrue();

    // Exactly one status still holds is_cancelled workspace-wide.
    expect(OrderStatus::where('workspace_id', $workspace->id)->where('is_cancelled', true)->count())->toBe(1);
});
