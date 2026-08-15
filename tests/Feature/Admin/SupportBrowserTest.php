<?php

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Order;

test('the support browser requires an active workspace_content session', function () {
    $admin = createPlatformAdmin();
    [$workspace] = createWorkspaceWithUser();

    $this->actingAs($admin)
        ->get(route('admin.workspaces.support.browse', $workspace))
        ->assertForbidden();
});

test('the support browser lists conversations, customers, orders and appointments for the exact session workspace', function () {
    $admin = createPlatformAdmin();
    [$workspace, $owner] = createWorkspaceWithUser();
    $channel = createMetaChannel($workspace);

    $customer = Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Nina Browser']);
    Conversation::withoutGlobalScopes()->create([
        'workspace_id' => $workspace->id,
        'channel_id' => $channel->id,
        'customer_id' => $customer->id,
        'external_conversation_id' => 'sender_browse',
        'status' => 'new_enquiry',
    ]);
    Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Torta',
        'price' => 20,
        'status' => 'new',
        'payment_status' => 'unpaid',
    ]);
    Appointment::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'service_name' => 'Striženje',
        'appointment_date' => now()->addDay()->toDateString(),
        'start_time' => '10:00',
        'duration_minutes' => 30,
        'price' => 15,
        'status' => 'requested',
        'payment_status' => 'unpaid',
    ]);

    createSupportGrant($workspace, $owner, 'workspace_content');
    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.workspaces.support.start', $workspace));

    $response = $this->get(route('admin.workspaces.support.browse', $workspace));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('conversations', 1)
        ->has('customers', 1)
        ->has('orders', 1)
        ->has('appointments', 1)
        ->where('customers.0.full_name', 'Nina Browser')
        ->where('orders.0.customer.full_name', 'Nina Browser')
        ->where('appointments.0.customer.full_name', 'Nina Browser')
    );
});

test('the support browser does not leak content from another workspace', function () {
    $admin = createPlatformAdmin();
    [$workspaceA, $ownerA] = createWorkspaceWithUser();
    [$workspaceB] = createWorkspaceWithUser();

    Customer::create(['workspace_id' => $workspaceB->id, 'full_name' => 'Should Not Appear']);

    createSupportGrant($workspaceA, $ownerA, 'workspace_content');
    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.workspaces.support.start', $workspaceA));

    $response = $this->get(route('admin.workspaces.support.browse', $workspaceA));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->has('customers', 0));
});

test('an appointment support detail page is available and audited', function () {
    $admin = createPlatformAdmin();
    [$workspace, $owner] = createWorkspaceWithUser();
    $customer = Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Appt Customer']);

    $appointment = Appointment::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'service_name' => 'Masaža',
        'appointment_date' => now()->addDay()->toDateString(),
        'start_time' => '11:00',
        'duration_minutes' => 45,
        'price' => 40,
        'status' => 'requested',
        'payment_status' => 'unpaid',
        'internal_notes' => 'Interna opomba',
    ]);

    createSupportGrant($workspace, $owner, 'workspace_content');
    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.workspaces.support.start', $workspace));

    $response = $this->get(route('admin.workspaces.support.appointment', [$workspace, $appointment]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('appointment.customer.full_name', 'Appt Customer')
        ->where('appointment.internal_notes', 'Interna opomba')
    );

    expect(AuditLog::where('event', 'support.content_access')->where('target_type', Appointment::class)->exists())->toBeTrue();
});

test('an appointment from another workspace cannot be opened via the support route', function () {
    $admin = createPlatformAdmin();
    [$workspaceA, $ownerA] = createWorkspaceWithUser();
    [$workspaceB] = createWorkspaceWithUser();
    $customerB = Customer::create(['workspace_id' => $workspaceB->id, 'full_name' => 'B Customer']);

    $appointmentB = Appointment::create([
        'workspace_id' => $workspaceB->id,
        'customer_id' => $customerB->id,
        'service_name' => 'Masaža',
        'appointment_date' => now()->addDay()->toDateString(),
        'start_time' => '11:00',
        'duration_minutes' => 45,
        'price' => 40,
        'status' => 'requested',
        'payment_status' => 'unpaid',
    ]);

    createSupportGrant($workspaceA, $ownerA, 'workspace_content');
    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.workspaces.support.start', $workspaceA));

    $this->get(route('admin.workspaces.support.appointment', [$workspaceA, $appointmentB]))
        ->assertStatus(404);
});
