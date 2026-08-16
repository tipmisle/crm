<?php

use App\Models\Appointment;
use App\Models\Message;
use Illuminate\Support\Facades\Http;

test('a demo appointment reminder is recorded in the customer chat without an external request', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['appointments_enabled' => true, 'is_demo' => true]);
    [$order, $conversation, $channel] = createOrderWithConversation($workspace);
    $channel->update(['status' => 'not_connected', 'connected_at' => null]);

    $appointment = Appointment::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $order->customer_id,
        'conversation_id' => $conversation->id,
        'channel_id' => $channel->id,
        'service_name' => 'Fotografiranje',
        'appointment_date' => now()->addDay(),
        'start_time' => '10:00',
        'duration_minutes' => 60,
        'price' => 120,
        'payment_status' => 'unpaid',
        'status' => 'confirmed',
    ]);

    Http::fake();

    $this->actingAs($user)->post(route('appointments.notify.store', $appointment), [
        'body' => 'Živjo Nina, jutri imaš termin ob 10:00.',
    ])->assertSessionHas('success');

    expect(Message::where('conversation_id', $conversation->id)->latest()->first()?->body)
        ->toBe('Živjo Nina, jutri imaš termin ob 10:00.');
    Http::assertNothingSent();
});
