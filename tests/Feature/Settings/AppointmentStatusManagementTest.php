<?php

use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\Customer;

test('owner can create an appointment status', function () {
    [$workspace, $owner] = createWorkspaceWithUser();

    $this->actingAs($owner)
        ->post(route('settings.statuses.appointment.store'), [
            'label' => 'Na čakalni listi',
            'color' => '#B45309',
            'bg' => '#FEF3C7',
        ])
        ->assertRedirect();

    $status = AppointmentStatus::where('workspace_id', $workspace->id)->where('label', 'Na čakalni listi')->first();

    expect($status)->not->toBeNull();
    expect($status->key)->toBe('na_cakalni_listi');
});

test('creating a status with a label that collides with an existing key gets a unique suffix', function () {
    [$workspace, $owner] = createWorkspaceWithUser();

    $this->actingAs($owner)->post(route('settings.statuses.appointment.store'), [
        'label' => 'Confirmed',
        'color' => '#4B5563',
        'bg' => '#F1F2F4',
    ]);

    $this->actingAs($owner)->post(route('settings.statuses.appointment.store'), [
        'label' => 'confirmed',
        'color' => '#4B5563',
        'bg' => '#F1F2F4',
    ]);

    $keys = AppointmentStatus::where('workspace_id', $workspace->id)->where('label', 'Confirmed')->orWhere('label', 'confirmed')->pluck('key');

    expect($keys)->toContain('confirmed_1');
});

function createAppointmentForStatus($workspace, string $statusKey): Appointment
{
    return Appointment::create([
        'workspace_id' => $workspace->id,
        'customer_id' => Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Test Customer'])->id,
        'service_name' => 'Test service',
        'appointment_date' => now()->addDay()->toDateString(),
        'start_time' => '10:00',
        'duration_minutes' => 30,
        'status' => $statusKey,
        'payment_status' => 'unpaid',
    ]);
}

test('owner can rename a status and existing appointments keep referencing it by key', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $status = AppointmentStatus::where('workspace_id', $workspace->id)->where('key', 'requested')->first();

    $appointment = createAppointmentForStatus($workspace, $status->key);

    $this->actingAs($owner)
        ->patch(route('settings.statuses.appointment.update', $status->id), ['label' => 'Povpraševanje (novo)'])
        ->assertRedirect();

    expect($status->fresh()->label)->toBe('Povpraševanje (novo)');
    expect($appointment->fresh()->status)->toBe('requested');
    expect($appointment->fresh()->appointmentStatusRecord->label)->toBe('Povpraševanje (novo)');
});

test('setting a status as default unsets the default flag on every other status', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $requested = AppointmentStatus::where('workspace_id', $workspace->id)->where('key', 'requested')->first();
    $confirmed = AppointmentStatus::where('workspace_id', $workspace->id)->where('key', 'confirmed')->first();

    expect($requested->is_default)->toBeTrue();

    $this->actingAs($owner)->patch(route('settings.statuses.appointment.update', $confirmed->id), ['is_default' => true]);

    expect($requested->fresh()->is_default)->toBeFalse();
    expect($confirmed->fresh()->is_default)->toBeTrue();
});

test('a status currently used by an appointment cannot be deleted', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $status = AppointmentStatus::where('workspace_id', $workspace->id)->where('key', 'requested')->first();

    createAppointmentForStatus($workspace, $status->key);

    $this->actingAs($owner)
        ->delete(route('settings.statuses.appointment.destroy', $status->id))
        ->assertStatus(422);

    expect(AppointmentStatus::find($status->id))->not->toBeNull();
});

test('a status in use can be deleted when its appointments are reassigned to another status', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $status = AppointmentStatus::where('workspace_id', $workspace->id)->where('key', 'confirmed')->first();
    $otherStatus = AppointmentStatus::where('workspace_id', $workspace->id)->where('key', 'requested')->first();

    $appointment = createAppointmentForStatus($workspace, $status->key);

    $this->actingAs($owner)
        ->delete(route('settings.statuses.appointment.destroy', $status->id), ['reassign_to' => $otherStatus->key])
        ->assertRedirect();

    expect(AppointmentStatus::find($status->id))->toBeNull();
    expect($appointment->fresh()->status)->toBe($otherStatus->key);
});

test('an unused status can be deleted', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $status = AppointmentStatus::where('workspace_id', $workspace->id)->where('key', 'confirmed')->first();

    $this->actingAs($owner)
        ->delete(route('settings.statuses.appointment.destroy', $status->id))
        ->assertRedirect();

    expect(AppointmentStatus::find($status->id))->toBeNull();
});

test('the status flagged default, completed, cancelled, no-show, or refunded cannot be deleted, even with a reassignment target', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $default = AppointmentStatus::where('workspace_id', $workspace->id)->where('key', 'requested')->first();
    $completed = AppointmentStatus::where('workspace_id', $workspace->id)->where('key', 'completed')->first();
    $cancelled = AppointmentStatus::where('workspace_id', $workspace->id)->where('key', 'cancelled')->first();
    $noShow = AppointmentStatus::where('workspace_id', $workspace->id)->where('key', 'no_show')->first();
    $refunded = AppointmentStatus::where('workspace_id', $workspace->id)->where('key', 'refunded')->first();
    $other = AppointmentStatus::where('workspace_id', $workspace->id)->where('key', 'confirmed')->first();

    foreach ([$default, $completed, $cancelled, $noShow, $refunded] as $protected) {
        $this->actingAs($owner)
            ->delete(route('settings.statuses.appointment.destroy', $protected->id), ['reassign_to' => $other->key])
            ->assertStatus(422);

        expect(AppointmentStatus::find($protected->id))->not->toBeNull();
    }
});

test('moving the cancelled flag to another status frees the previous status for deletion', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $cancelled = AppointmentStatus::where('workspace_id', $workspace->id)->where('key', 'cancelled')->first();
    $confirmed = AppointmentStatus::where('workspace_id', $workspace->id)->where('key', 'confirmed')->first();

    $this->actingAs($owner)->patch(route('settings.statuses.appointment.update', $confirmed->id), ['is_cancelled' => true]);

    expect($cancelled->fresh()->is_cancelled)->toBeFalse();
    expect($confirmed->fresh()->is_cancelled)->toBeTrue();

    $this->actingAs($owner)
        ->delete(route('settings.statuses.appointment.destroy', $cancelled->id))
        ->assertRedirect();

    expect(AppointmentStatus::find($cancelled->id))->toBeNull();
});

test('the last remaining appointment status cannot be deleted', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    AppointmentStatus::where('workspace_id', $workspace->id)->where('key', '!=', 'requested')->delete();
    $last = AppointmentStatus::where('workspace_id', $workspace->id)->sole();

    $this->actingAs($owner)
        ->delete(route('settings.statuses.appointment.destroy', $last->id))
        ->assertStatus(422);

    expect(AppointmentStatus::find($last->id))->not->toBeNull();
});

test('reordering persists the new sort order', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $statuses = AppointmentStatus::where('workspace_id', $workspace->id)->orderBy('sort_order')->get();
    $reversed = $statuses->reverse()->pluck('id')->values();

    $this->actingAs($owner)
        ->post(route('settings.statuses.appointment.reorder'), ['ids' => $reversed->all()])
        ->assertRedirect();

    $reordered = AppointmentStatus::where('workspace_id', $workspace->id)->orderBy('sort_order')->pluck('id');

    expect($reordered->all())->toBe($reversed->all());
});

test('a member of another workspace cannot edit or delete a status they do not own', function () {
    [$workspaceA] = createWorkspaceWithUser();
    [, $ownerB] = createWorkspaceWithUser();

    $statusA = AppointmentStatus::where('workspace_id', $workspaceA->id)->where('key', 'requested')->first();

    $this->actingAs($ownerB)
        ->patch(route('settings.statuses.appointment.update', $statusA->id), ['label' => 'Hacked'])
        ->assertStatus(404);

    $this->actingAs($ownerB)
        ->delete(route('settings.statuses.appointment.destroy', $statusA->id))
        ->assertStatus(404);

    expect($statusA->fresh()->label)->toBe('Povpraševanje');
});

test('another workspaces appointment statuses never appear in a users shared inertia props', function () {
    [$workspaceA, $ownerA] = createWorkspaceWithUser();
    [$workspaceB] = createWorkspaceWithUser();

    $response = $this->actingAs($ownerA)->get(route('settings.statuses.edit'));

    $response->assertInertia(fn ($page) => $page
        ->where('appointmentStatuses', fn ($statuses) => collect($statuses)->pluck('id')->intersect(
            AppointmentStatus::where('workspace_id', $workspaceB->id)->pluck('id')
        )->isEmpty())
    );
});
