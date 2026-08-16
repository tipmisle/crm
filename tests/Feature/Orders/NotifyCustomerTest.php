<?php

use App\Events\InboxMessageReceived;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Order;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

function notifyPayload(array $overrides = []): array
{
    return array_merge([
        'type' => 'pickup',
        'body' => 'Živjo Nina 😊 Tvoje naročilo je pripravljeno za prevzem.',
    ], $overrides);
}

test('a pickup notification can be sent', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    [$order] = createOrderWithConversation($workspace);

    Http::fakeSequence('*/messages*')->push(['message_id' => 'mid_1'], 200);

    $this->actingAs($user)->post(route('orders.notify.store', $order), notifyPayload())
        ->assertSessionHas('success');

    expect($order->fresh()->delivery_method)->toBe('pickup');
});

test('a demo notification without a connected channel is recorded in the chat using the mock provider', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['is_demo' => true]);
    [$order, $conversation, $channel] = createOrderWithConversation($workspace);
    $channel->update(['status' => 'not_connected', 'connected_at' => null]);

    Http::fake();
    Event::fake([InboxMessageReceived::class]);

    $this->actingAs($user)->post(route('orders.notify.store', $order), notifyPayload())
        ->assertSessionHas('success')
        ->assertSessionMissing('error');

    Http::assertNothingSent();
    Event::assertNotDispatched(InboxMessageReceived::class);
    expect(Message::where('conversation_id', $conversation->id)->latest()->first()?->body)
        ->toBe(notifyPayload()['body']);
});

test('a demo order without a linked conversation creates a local chat when notified', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['is_demo' => true]);
    [$order, $oldConversation, $channel] = createOrderWithConversation($workspace);
    $order->update(['conversation_id' => null]);
    $channel->update(['status' => 'not_connected', 'connected_at' => null]);

    Http::fake();

    $this->actingAs($user)->post(route('orders.notify.store', $order), notifyPayload())
        ->assertSessionHas('success');

    $order->refresh();
    expect($order->conversation_id)->not->toBeNull()->not->toBe($oldConversation->id);
    expect(Message::where('conversation_id', $order->conversation_id)->latest()->first()?->body)
        ->toBe(notifyPayload()['body']);
    Http::assertNothingSent();
});

test('a shipping notification can be sent and tracking data is stored on the order', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    [$order] = createOrderWithConversation($workspace);

    Http::fakeSequence('*/messages*')->push(['message_id' => 'mid_1'], 200);

    $this->actingAs($user)->post(route('orders.notify.store', $order), notifyPayload([
        'type' => 'shipped',
        'body' => 'Živjo Nina 😊 Tvoje naročilo je bilo poslano in je na poti do tebe. Številka za sledenje: 123456789.',
        'tracking_number' => '123456789',
        'tracking_url' => 'https://posta.si/sledenje/123456789',
    ]))->assertSessionHas('success');

    $order->refresh();
    expect($order->tracking_number)->toBe('123456789');
    expect($order->tracking_url)->toBe('https://posta.si/sledenje/123456789');
    expect($order->shipped_at)->not->toBeNull();
    expect($order->delivery_method)->toBe('mail');
});

test('delivery choice and tracking data can be saved before notifying the customer', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    [$order] = createOrderWithConversation($workspace);

    $this->actingAs($user)->patch(route('orders.update', $order), [
        'delivery_method' => 'mail',
        'tracking_number' => 'PRE-123',
        'tracking_url' => 'https://posta.si/PRE-123',
    ])->assertSessionHas('success');

    $order->refresh();
    expect($order->delivery_method)->toBe('mail');
    expect($order->tracking_number)->toBe('PRE-123');
    expect($order->tracking_url)->toBe('https://posta.si/PRE-123');
});

test('existing tracking data is returned so it can be prefilled next time', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    [$order] = createOrderWithConversation($workspace);
    $order->update(['tracking_number' => 'OLD-1', 'tracking_url' => 'https://posta.si/OLD-1', 'shipped_at' => now()]);

    $response = $this->actingAs($user)->get(route('orders.show', $order));

    $response->assertInertia(fn ($page) => $page
        ->where('order.tracking_number', 'OLD-1')
        ->where('order.tracking_url', 'https://posta.si/OLD-1'));
});

test('notification requires an order with a linked conversation', function () {
    [$workspace, $user] = createWorkspaceWithUser();

    $order = Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Brez pogovora'])->id,
        'title' => 'Naročilo brez pogovora',
        'price' => 50,
        'amount_paid' => 0,
        'payment_status' => 'unpaid',
        'status' => 'new',
    ]);

    $this->actingAs($user)->post(route('orders.notify.store', $order), notifyPayload())
        ->assertStatus(422);
});

test('a user from a different workspace cannot notify a customer for an order they do not own', function () {
    [$workspaceA, $userA] = createWorkspaceWithUser();
    [$orderA] = createOrderWithConversation($workspaceA);

    [, $userB] = createWorkspaceWithUser();

    $this->actingAs($userB)->post(route('orders.notify.store', $orderA), notifyPayload())
        ->assertStatus(404);
});

test('tracking number and url are validated', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    [$order] = createOrderWithConversation($workspace);

    $this->actingAs($user)->post(route('orders.notify.store', $order), notifyPayload([
        'type' => 'shipped',
        'tracking_number' => str_repeat('x', 200),
        'tracking_url' => 'not-a-url',
    ]))->assertSessionHasErrors(['tracking_number', 'tracking_url']);
});

test('a provider send failure does not lose the order tracking data', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    [$order] = createOrderWithConversation($workspace);

    Http::fakeSequence('*/messages*')->push(['error' => ['message' => 'boom']], 400);

    $this->actingAs($user)->post(route('orders.notify.store', $order), notifyPayload([
        'type' => 'shipped',
        'tracking_number' => '999',
        'tracking_url' => 'https://posta.si/999',
    ]))->assertSessionHas('error');

    $order->refresh();
    expect($order->tracking_number)->toBe('999');
    expect($order->tracking_url)->toBe('https://posta.si/999');
});

test('no message is sent to the provider before this endpoint is explicitly called', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    [$order] = createOrderWithConversation($workspace);

    Http::fake();

    $this->actingAs($user)->get(route('orders.show', $order))->assertOk();

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/messages'));
});
