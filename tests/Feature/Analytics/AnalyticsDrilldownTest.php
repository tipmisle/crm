<?php

use App\Models\Appointment;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;

test('the top product with a catalog link drills down to exactly the orders for that product', function () {
    [$workspace, $user] = createWorkspaceWithUser();

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Ana Novak',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $product = Product::create(['workspace_id' => $workspace->id, 'name' => 'Rojstnodnevna torta', 'active' => true]);

    $linked = Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => $product->name,
        'price' => 90,
        'status' => 'confirmed',
    ]);
    $linked->items()->create(['catalog_item_id' => $product->id, 'title' => $product->name, 'quantity' => 1, 'unit_price' => 90]);

    // A one-off custom order with no catalog link — must stay non-clickable.
    $custom = Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Enkratno naročilo po meri',
        'price' => 200,
        'status' => 'confirmed',
    ]);
    $custom->items()->create(['title' => 'Enkratno naročilo po meri', 'quantity' => 1, 'unit_price' => 200]);

    // A cancelled order for the same product must not be counted in the
    // revenue total or leak into the drill-down.
    $cancelled = Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => $product->name,
        'price' => 90,
        'status' => 'cancelled',
    ]);
    $cancelled->items()->create(['catalog_item_id' => $product->id, 'title' => $product->name, 'quantity' => 1, 'unit_price' => 90]);

    $response = $this->actingAs($user)->get(route('analytics.index'));

    $topProducts = collect($response->inertiaProps('topProducts'));
    $linkedRow = $topProducts->firstWhere('name', $product->name);
    $customRow = $topProducts->firstWhere('name', 'Enkratno naročilo po meri');

    expect($linkedRow['href'])->not->toBeNull();
    expect((float) $linkedRow['revenue'])->toBe(90.0);
    expect($customRow['href'])->toBeNull();

    $filtered = $this->actingAs($user)->get($linkedRow['href']);
    $filtered->assertInertia(fn ($page) => $page
        ->has('orders.data', 1)
        ->where('orders.data.0.id', $linked->id)
    );

    expect(Order::find($cancelled->id))->not->toBeNull();
});

test('the top service with a catalog link drills down to exactly the appointments for that service', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['appointments_enabled' => true]);

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Zala Ferlan',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $service = Service::create(['workspace_id' => $workspace->id, 'name' => 'Gel manikura', 'default_duration_minutes' => 60, 'active' => true]);

    $linked = Appointment::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'service_name' => $service->name,
        'appointment_date' => now(),
        'start_time' => '10:00',
        'duration_minutes' => 60,
        'price' => 35,
        'status' => 'confirmed',
    ]);
    $linked->items()->create(['catalog_item_id' => $service->id, 'title' => $service->name, 'quantity' => 1, 'unit_price' => 35]);

    $custom = Appointment::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'service_name' => 'Individualna storitev',
        'appointment_date' => now(),
        'start_time' => '12:00',
        'duration_minutes' => 30,
        'price' => 50,
        'status' => 'confirmed',
    ]);
    $custom->items()->create(['title' => 'Individualna storitev', 'quantity' => 1, 'unit_price' => 50]);

    $response = $this->actingAs($user)->get(route('analytics.index'));

    $topServices = collect($response->inertiaProps('topServices'));
    $linkedRow = $topServices->firstWhere('name', $service->name);
    $customRow = $topServices->firstWhere('name', 'Individualna storitev');

    expect($linkedRow['href'])->not->toBeNull();
    expect($customRow['href'])->toBeNull();

    $filtered = $this->actingAs($user)->get($linkedRow['href']);
    $filtered->assertInertia(fn ($page) => $page
        ->has('appointments.data', 1)
        ->where('appointments.data.0.id', $linked->id)
    );
});

test('channel revenue only links out when a single module produced it, not a mixed total', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['appointments_enabled' => true]);

    $channel = createMetaChannel($workspace, 'instagram');
    // createMetaChannel creates a new Integration per call, keyed uniquely
    // by workspace — reuse that same integration for a second channel of a
    // different type within the same workspace instead of calling it twice.
    $otherChannel = Channel::create([
        'workspace_id' => $workspace->id,
        'integration_id' => $channel->integration_id,
        'type' => 'whatsapp',
        'external_account_id' => 'wa_123',
        'display_name' => 'Test WhatsApp',
        'handle' => '@test-wa',
        'status' => 'connected',
        'connected_at' => now(),
        'access_token' => 'test-page-token',
    ]);

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Ana Novak',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    // Instagram: only an order — exact drill-down should exist.
    Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'channel_id' => $channel->id,
        'title' => 'Torta',
        'price' => 60,
        'status' => 'confirmed',
    ]);

    // WhatsApp: both an order and an appointment — mixed, must stay non-clickable.
    Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'channel_id' => $otherChannel->id,
        'title' => 'Drugo naročilo',
        'price' => 40,
        'status' => 'confirmed',
    ]);
    Appointment::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'channel_id' => $otherChannel->id,
        'service_name' => 'Manikura',
        'appointment_date' => now(),
        'start_time' => '10:00',
        'duration_minutes' => 60,
        'price' => 25,
        'status' => 'confirmed',
    ]);

    $response = $this->actingAs($user)->get(route('analytics.index'));
    $channelRevenue = collect($response->inertiaProps('channelRevenue'));

    $instagramRow = $channelRevenue->firstWhere('type', 'instagram');
    $whatsappRow = $channelRevenue->firstWhere('type', 'whatsapp');

    expect($instagramRow['href'])->not->toBeNull();
    expect($whatsappRow['href'])->toBeNull();

    $filtered = $this->actingAs($user)->get($instagramRow['href']);
    $filtered->assertInertia(fn ($page) => $page->has('orders.data', 1));
});

test('channel inquiries drill down to Inbox filtered by that channel, isolated per workspace', function () {
    [$workspaceA, $userA] = createWorkspaceWithUser();
    [$workspaceB] = createWorkspaceWithUser();

    $channelA = createMetaChannel($workspaceA, 'instagram', 'ig_a');
    $channelB = createMetaChannel($workspaceB, 'instagram', 'ig_b');

    $conversationA = Conversation::create([
        'workspace_id' => $workspaceA->id,
        'channel_id' => $channelA->id,
        'external_conversation_id' => 'sender_a',
        'customer_display_name' => 'Stranka A',
        'status' => 'new_enquiry',
    ]);

    Conversation::create([
        'workspace_id' => $workspaceB->id,
        'channel_id' => $channelB->id,
        'external_conversation_id' => 'sender_b',
        'customer_display_name' => 'Stranka B',
        'status' => 'new_enquiry',
    ]);

    $response = $this->actingAs($userA)->get(route('analytics.index'));
    $channelInquiries = collect($response->inertiaProps('channelInquiries'));
    $instagramRow = $channelInquiries->firstWhere('type', 'instagram');

    expect($instagramRow['href'])->toBe(route('inbox.index', ['channel_type' => 'instagram']));

    $filtered = $this->actingAs($userA)->get($instagramRow['href']);
    $filtered->assertInertia(fn ($page) => $page
        ->has('conversations', 1)
        ->where('conversations.0.id', $conversationA->id)
    );
});
