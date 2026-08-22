<?php

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentStatus;

test('owner can create a payment status', function () {
    [$workspace, $owner] = createWorkspaceWithUser();

    $this->actingAs($owner)
        ->post(route('settings.statuses.payment.store'), [
            'label' => 'Stornirano',
            'color' => '#B91C1C',
            'bg' => '#FEE2E2',
        ])
        ->assertRedirect();

    $status = PaymentStatus::where('workspace_id', $workspace->id)->where('label', 'Stornirano')->first();

    expect($status)->not->toBeNull();
    expect($status->key)->toBe('stornirano');
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
    $status = PaymentStatus::where('workspace_id', $workspace->id)->where('key', 'deposit_paid')->first();
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

test('the status flagged default, deposit default, paid, or refunded cannot be deleted, even with a reassignment target', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $default = PaymentStatus::where('workspace_id', $workspace->id)->where('key', 'unpaid')->first();
    $depositDefault = PaymentStatus::where('workspace_id', $workspace->id)->where('key', 'deposit_due')->first();
    $paid = PaymentStatus::where('workspace_id', $workspace->id)->where('key', 'paid')->first();
    $refunded = PaymentStatus::where('workspace_id', $workspace->id)->where('key', 'refunded')->first();
    $other = PaymentStatus::where('workspace_id', $workspace->id)->where('key', 'deposit_paid')->first();

    foreach ([$default, $depositDefault, $paid, $refunded] as $protected) {
        $this->actingAs($owner)
            ->delete(route('settings.statuses.payment.destroy', $protected->id), ['reassign_to' => $other->key])
            ->assertStatus(422);

        expect(PaymentStatus::find($protected->id))->not->toBeNull();
    }
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

test('a crafted request cannot flag one payment status with two mutually exclusive roles at once', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $status = PaymentStatus::where('workspace_id', $workspace->id)->where('key', 'deposit_paid')->first();

    $this->actingAs($owner)
        ->patch(route('settings.statuses.payment.update', $status->id), [
            'is_paid' => true,
            'is_refunded' => true,
        ])
        ->assertStatus(422);

    $status->refresh();
    expect($status->is_paid)->toBeFalse();
    expect($status->is_refunded)->toBeFalse();
});

test('a follow-up request cannot move a second exclusive role onto a payment status that already holds a different one', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $status = PaymentStatus::where('workspace_id', $workspace->id)->where('key', 'deposit_paid')->first();

    $this->actingAs($owner)
        ->patch(route('settings.statuses.payment.update', $status->id), ['is_paid' => true])
        ->assertRedirect();

    expect($status->fresh()->is_paid)->toBeTrue();

    $this->actingAs($owner)
        ->patch(route('settings.statuses.payment.update', $status->id), ['is_refunded' => true])
        ->assertStatus(422);

    $status->refresh();
    expect($status->is_refunded)->toBeFalse();
    expect($status->is_paid)->toBeTrue();

    expect(PaymentStatus::where('workspace_id', $workspace->id)->where('is_paid', true)->count())->toBe(1);
});

test('is_deposit_default is now a mandatory single-status role like is_default/is_paid/is_refunded', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $status = PaymentStatus::where('workspace_id', $workspace->id)->where('key', 'deposit_due')->first();
    expect($status->is_deposit_default)->toBeTrue();

    // Sending false is a no-op (can't unset a mandatory role directly, only
    // move it to another status) — the request still succeeds, but the
    // flag stays put.
    $this->actingAs($owner)
        ->patch(route('settings.statuses.payment.update', $status->id), ['is_deposit_default' => false])
        ->assertRedirect();

    expect($status->fresh()->is_deposit_default)->toBeTrue();
});

test('a crafted request cannot flag a payment status as both the deposit default and another exclusive role', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $status = PaymentStatus::where('workspace_id', $workspace->id)->where('key', 'deposit_paid')->first();

    $this->actingAs($owner)
        ->patch(route('settings.statuses.payment.update', $status->id), [
            'is_deposit_default' => true,
            'is_paid' => true,
        ])
        ->assertStatus(422);

    $status->refresh();
    expect($status->is_deposit_default)->toBeFalse();
    expect($status->is_paid)->toBeFalse();
});

test('a protected payment status label and color can be edited — the semantic flag is fixed, not the label', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $paid = PaymentStatus::where('workspace_id', $workspace->id)->where('key', 'paid')->first();

    $this->actingAs($owner)
        ->patch(route('settings.statuses.payment.update', $paid->id), ['label' => 'Vse poravnano', 'color' => '#123456'])
        ->assertRedirect();

    $paid->refresh();
    expect($paid->label)->toBe('Vse poravnano');
    expect($paid->color)->toBe('#123456');
    // The semantic flag itself is untouched by a label/color edit.
    expect($paid->is_paid)->toBeTrue();
});
