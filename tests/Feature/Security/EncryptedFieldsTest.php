<?php

use App\Models\Appointment;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Order;
use App\Models\OrderNote;
use Illuminate\Support\Facades\DB;

/**
 * For each high-sensitivity field: write known plaintext through the model,
 * confirm the model still returns the original plaintext, then read the RAW
 * database value directly (bypassing Eloquent casts) and confirm it does
 * NOT contain the plaintext. See docs/data-security.md.
 */
test('Message.body is encrypted at rest', function () {
    [$workspace] = createWorkspaceWithUser();
    $channel = createMetaChannel($workspace);
    $conversation = Conversation::withoutGlobalScopes()->create([
        'workspace_id' => $workspace->id,
        'channel_id' => $channel->id,
        'external_conversation_id' => 'sender_enc',
        'status' => 'new_enquiry',
    ]);

    $plaintext = 'Živjo, ali imate na voljo termin jutri ob 14h? 😊';

    $message = $conversation->messages()->create([
        'sender_type' => 'customer',
        'body' => $plaintext,
        'message_type' => 'text',
        'status' => 'delivered',
        'sent_at' => now(),
    ]);

    expect($message->fresh()->body)->toBe($plaintext);

    $raw = DB::table('messages')->where('id', $message->id)->value('body');
    expect($raw)->not->toContain($plaintext);
    expect($raw)->not->toContain('termin');
});

test('Conversation.last_message_preview is encrypted at rest', function () {
    [$workspace] = createWorkspaceWithUser();
    $channel = createMetaChannel($workspace);

    $plaintext = 'Hvala, se vidimo v petek!';

    $conversation = Conversation::withoutGlobalScopes()->create([
        'workspace_id' => $workspace->id,
        'channel_id' => $channel->id,
        'external_conversation_id' => 'sender_enc2',
        'status' => 'new_enquiry',
        'last_message_preview' => $plaintext,
    ]);

    expect($conversation->fresh()->last_message_preview)->toBe($plaintext);

    $raw = DB::table('conversations')->where('id', $conversation->id)->value('last_message_preview');
    expect($raw)->not->toContain($plaintext);
});

test('Customer.notes is encrypted at rest', function () {
    [$workspace] = createWorkspaceWithUser();

    $plaintext = "Alergična na oreščke.\nProsi za dostavo po 17h.";

    $customer = Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Nina Kovač', 'notes' => $plaintext]);

    expect($customer->fresh()->notes)->toBe($plaintext);

    $raw = DB::table('customers')->where('id', $customer->id)->value('notes');
    expect($raw)->not->toContain($plaintext);
    expect($raw)->not->toContain('Alergična');
});

test('Order.internal_notes and customer_notes are encrypted at rest', function () {
    [$workspace] = createWorkspaceWithUser();
    $customer = Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Test Stranka']);

    $internal = 'Stranka je zamujala s plačilom prejšnjič.';
    $customerFacing = 'Naročilo bo pripravljeno do petka.';

    $order = Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Torta',
        'price' => 30,
        'status' => 'new',
        'payment_status' => 'unpaid',
        'internal_notes' => $internal,
        'customer_notes' => $customerFacing,
    ]);

    expect($order->fresh()->internal_notes)->toBe($internal);
    expect($order->fresh()->customer_notes)->toBe($customerFacing);

    $raw = DB::table('orders')->where('id', $order->id)->first();
    expect($raw->internal_notes)->not->toContain($internal);
    expect($raw->customer_notes)->not->toContain($customerFacing);
});

test('Appointment.internal_notes is encrypted at rest', function () {
    [$workspace] = createWorkspaceWithUser();
    $customer = Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Test Stranka 2']);

    $plaintext = 'Nosečnica — brez določenih tretmajev.';

    $appointment = Appointment::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'service_name' => 'Masaža',
        'appointment_date' => now()->addDay()->toDateString(),
        'start_time' => '10:00',
        'duration_minutes' => 60,
        'price' => 50,
        'status' => 'requested',
        'payment_status' => 'unpaid',
        'internal_notes' => $plaintext,
    ]);

    expect($appointment->fresh()->internal_notes)->toBe($plaintext);

    $raw = DB::table('appointments')->where('id', $appointment->id)->value('internal_notes');
    expect($raw)->not->toContain($plaintext);
});

test('OrderNote.body is encrypted at rest', function () {
    [$workspace] = createWorkspaceWithUser();
    $customer = Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Test Stranka 3']);
    $order = Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Naročilo',
        'price' => 10,
        'status' => 'new',
        'payment_status' => 'unpaid',
    ]);

    $plaintext = 'Poklicala stranka, spremenila datum prevzema.';

    $note = OrderNote::create(['order_id' => $order->id, 'body' => $plaintext]);

    expect($note->fresh()->body)->toBe($plaintext);

    $raw = DB::table('order_notes')->where('id', $note->id)->value('body');
    expect($raw)->not->toContain($plaintext);
});

test('FollowUp.note is encrypted at rest', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $customer = Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Test Stranka 4']);

    $plaintext = 'Poklicati glede zdravstvenih omejitev pred terminom.';

    $followUp = FollowUp::create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'followable_type' => Customer::class,
        'followable_id' => $customer->id,
        'note' => $plaintext,
        'due_at' => now()->addDay(),
    ]);

    expect($followUp->fresh()->note)->toBe($plaintext);

    $raw = DB::table('follow_ups')->where('id', $followUp->id)->value('note');
    expect($raw)->not->toContain($plaintext);
});

test('Message.metadata attachment paths are encrypted at rest', function () {
    [$workspace] = createWorkspaceWithUser();
    $channel = createMetaChannel($workspace);
    $conversation = Conversation::withoutGlobalScopes()->create([
        'workspace_id' => $workspace->id,
        'channel_id' => $channel->id,
        'external_conversation_id' => 'sender_meta',
        'status' => 'new_enquiry',
    ]);

    $secretPath = 'inbox-attachments/unique-marker-file-xyz.jpg';

    $message = $conversation->messages()->create([
        'sender_type' => 'business',
        'message_type' => 'image',
        'status' => 'sent',
        'metadata' => ['attachments' => [['type' => 'image', 'source' => 'local', 'path' => $secretPath]]],
        'sent_at' => now(),
    ]);

    expect($message->fresh()->metadata['attachments'][0]['path'])->toBe($secretPath);

    $raw = DB::table('messages')->where('id', $message->id)->value('metadata');
    expect($raw)->not->toContain($secretPath);
});
