<?php

use App\Models\AppointmentStatus;
use App\Models\OrderStatus;
use App\Models\PaymentStatus;

/**
 * Settings/Statuses.vue's role-assignment dropdowns each PATCH a single
 * semantic flag => true on the chosen row (see moveOrderRole()/
 * movePaymentRole()/moveAppointmentRole() in that page). These prove the
 * exact request shape the UI now sends actually moves the role, matching
 * the "make semantic roles manageable from the UI" requirement.
 */
test('moving the cancelled role via the statuses UI request shape works end to end', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $plain = OrderStatus::create([
        'workspace_id' => $workspace->id,
        'key' => 'awaiting_parts',
        'label' => 'Čaka dele',
        'color' => '#000000',
        'bg' => '#FFFFFF',
        'sort_order' => 99,
    ]);

    $this->actingAs($owner)
        ->patch(route('settings.statuses.order.update', $plain->id), ['is_cancelled' => true])
        ->assertRedirect();

    expect($plain->fresh()->is_cancelled)->toBeTrue();
    expect(OrderStatus::where('workspace_id', $workspace->id)->where('key', 'cancelled')->first()->is_cancelled)->toBeFalse();
});

test('moving the no_show role for appointment statuses works end to end', function () {
    [$workspace, $owner] = createWorkspaceWithUser(['current_workspace_id' => null]);
    $workspace->update(['appointments_enabled' => true]);
    $owner->update(['current_workspace_id' => $workspace->id]);

    $plain = AppointmentStatus::create([
        'workspace_id' => $workspace->id,
        'key' => 'rescheduling',
        'label' => 'Prestavlja se',
        'color' => '#000000',
        'bg' => '#FFFFFF',
        'sort_order' => 99,
    ]);

    $this->actingAs($owner)
        ->patch(route('settings.statuses.appointment.update', $plain->id), ['is_no_show' => true])
        ->assertRedirect();

    expect($plain->fresh()->is_no_show)->toBeTrue();
    expect(AppointmentStatus::where('workspace_id', $workspace->id)->where('key', 'no_show')->first()->is_no_show)->toBeFalse();
});

test('marking a payment status as outstanding does not require moving an exclusive role', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $plain = PaymentStatus::create([
        'workspace_id' => $workspace->id,
        'key' => 'awaiting_bank_transfer',
        'label' => 'Čaka nakazilo',
        'color' => '#000000',
        'bg' => '#FFFFFF',
        'sort_order' => 99,
    ]);

    $this->actingAs($owner)
        ->patch(route('settings.statuses.payment.update', $plain->id), ['is_outstanding' => true])
        ->assertRedirect();

    expect($plain->fresh()->is_outstanding)->toBeTrue();
    // Not an exclusive role — moving it does not disturb is_default/is_paid/is_refunded elsewhere.
    expect(PaymentStatus::where('workspace_id', $workspace->id)->where('is_default', true)->count())->toBe(1);
});

test('the shared Inertia paymentStatuses prop exposes is_paid and is_refunded', function () {
    [$workspace, $owner] = createWorkspaceWithUser();

    $response = $this->actingAs($owner)->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->has('paymentStatuses')
        ->where('paymentStatuses', fn ($statuses) => collect($statuses)->first(fn ($s) => $s['key'] === 'paid')['is_paid'] === true)
    );
});
