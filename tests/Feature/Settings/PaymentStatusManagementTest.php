<?php

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentStatus;

test('owner can create a payment status', function () {
    [$workspace, $owner] = createWorkspaceWithUser();

    $this->actingAs($owner)
        ->post(route('settings.statuses.payment.store'), [
            'label' => 'Vračilo',
            'color' => '#B91C1C',
            'bg' => '#FEE2E2',
        ])
        ->assertRedirect();

    $status = PaymentStatus::where('workspace_id', $workspace->id)->where('label', 'Vračilo')->first();

    expect($status)->not->toBeNull();
    expect($status->key)->toBe('vracilo');
});

test('setting a status as the deposit default unsets the deposit default flag on every other status', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $depositDue = PaymentStatus::where('workspace_id', $workspace->id)->where('key', 'deposit_due')->first();
    $depositPaid = PaymentStatus::where('workspace_id', $workspace->id)->where('key', 'deposit_paid')->first();

    expect($depositDue->is_deposit_default)->toBeTrue();

    $this->actingAs($owner)->patch(route('settings.statuses.payment.update', $depositPaid->id), ['is_deposit_default' => true]);

    expect($depositDue->fresh()->is_deposit_default)->toBeFalse();
    expect($depositPaid->fresh()->is_deposit_default)->toBeTrue();
});

test('a business that never flags a deposit default falls back to the plain default when creating an order with a deposit', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    PaymentStatus::where('workspace_id', $workspace->id)->update(['is_deposit_default' => false]);

    $this->actingAs($owner);

    expect(PaymentStatus::depositDefaultKey())->toBe(PaymentStatus::defaultKey());
    expect(PaymentStatus::depositDefaultKey())->toBe('unpaid');
});

test('a payment status referenced by an order cannot be deleted', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $status = PaymentStatus::where('workspace_id', $workspace->id)->where('key', 'unpaid')->first();

    Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Test Customer'])->id,
        'title' => 'Test order',
        'status' => 'new',
        'payment_status' => $status->key,
    ]);

    $this->actingAs($owner)
        ->delete(route('settings.statuses.payment.destroy', $status->id))
        ->assertStatus(422);

    expect(PaymentStatus::find($status->id))->not->toBeNull();
});

test('a payment status in use can be deleted when its orders are reassigned to another status', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $status = PaymentStatus::where('workspace_id', $workspace->id)->where('key', 'unpaid')->first();
    $otherStatus = PaymentStatus::where('workspace_id', $workspace->id)->where('key', 'paid')->first();

    $order = Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Test Customer'])->id,
        'title' => 'Test order',
        'status' => 'new',
        'payment_status' => $status->key,
    ]);

    $this->actingAs($owner)
        ->delete(route('settings.statuses.payment.destroy', $status->id), ['reassign_to' => $otherStatus->key])
        ->assertRedirect();

    expect(PaymentStatus::find($status->id))->toBeNull();
    expect($order->fresh()->payment_status)->toBe($otherStatus->key);
});

test('a payment status referenced by an appointment cannot be deleted', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $status = PaymentStatus::where('workspace_id', $workspace->id)->where('key', 'unpaid')->first();

    Appointment::create([
        'workspace_id' => $workspace->id,
        'customer_id' => Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Test Customer'])->id,
        'service_name' => 'Test service',
        'appointment_date' => now()->addDay()->toDateString(),
        'start_time' => '10:00',
        'status' => 'confirmed',
        'payment_status' => $status->key,
    ]);

    $this->actingAs($owner)
        ->delete(route('settings.statuses.payment.destroy', $status->id))
        ->assertStatus(422);

    expect(PaymentStatus::find($status->id))->not->toBeNull();
});

test('the last remaining payment status cannot be deleted', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    PaymentStatus::where('workspace_id', $workspace->id)->where('key', '!=', 'unpaid')->delete();
    $last = PaymentStatus::where('workspace_id', $workspace->id)->sole();

    $this->actingAs($owner)
        ->delete(route('settings.statuses.payment.destroy', $last->id))
        ->assertStatus(422);

    expect(PaymentStatus::find($last->id))->not->toBeNull();
});

test('reordering persists the new sort order', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $statuses = PaymentStatus::where('workspace_id', $workspace->id)->orderBy('sort_order')->get();
    $reversed = $statuses->reverse()->pluck('id')->values();

    $this->actingAs($owner)
        ->post(route('settings.statuses.payment.reorder'), ['ids' => $reversed->all()])
        ->assertRedirect();

    $reordered = PaymentStatus::where('workspace_id', $workspace->id)->orderBy('sort_order')->pluck('id');

    expect($reordered->all())->toBe($reversed->all());
});

test('a member of another workspace cannot edit or delete a payment status they do not own', function () {
    [$workspaceA] = createWorkspaceWithUser();
    [$workspaceB, $ownerB] = createWorkspaceWithUser();

    $statusA = PaymentStatus::where('workspace_id', $workspaceA->id)->where('key', 'unpaid')->first();

    $this->actingAs($ownerB)
        ->patch(route('settings.statuses.payment.update', $statusA->id), ['label' => 'Hacked'])
        ->assertStatus(404);

    $this->actingAs($ownerB)
        ->delete(route('settings.statuses.payment.destroy', $statusA->id))
        ->assertStatus(404);

    expect($statusA->fresh()->label)->toBe('Neplačano');
});
