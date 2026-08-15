<?php

use App\Models\Customer;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Database\Seeders\FotoStudioLunaSeeder;

test('the mixed demo scenario seeds both orders and appointments for the same customer', function () {
    $workspace = Workspace::create([
        'name' => 'Foto studio Luna',
        'slug' => 'foto-studio-luna-test-'.uniqid(),
        'orders_enabled' => true,
        'appointments_enabled' => true,
        'is_demo' => true,
        'demo_variant' => 'both',
        'demo_expires_at' => now()->addHours(4),
    ]);

    $user = User::factory()->create(['current_workspace_id' => $workspace->id, 'is_demo' => true]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => 'owner']);

    (new FotoStudioLunaSeeder($workspace, $user))->run();

    expect($workspace->orders()->count())->toBeGreaterThan(0);
    expect($workspace->appointments()->count())->toBeGreaterThan(0);

    $nina = Customer::withoutGlobalScopes()
        ->where('workspace_id', $workspace->id)
        ->where('full_name', 'Nina Kovač')
        ->first();

    expect($nina)->not->toBeNull();

    $ninaAppointment = $workspace->appointments()->where('customer_id', $nina->id)->first();
    $ninaOrder = $workspace->orders()->where('customer_id', $nina->id)->first();

    expect($ninaAppointment)->not->toBeNull();
    expect($ninaAppointment->service_name)->toBe('Družinsko fotografiranje');

    expect($ninaOrder)->not->toBeNull();
    expect($ninaOrder->title)->toBe('Foto album');
});
