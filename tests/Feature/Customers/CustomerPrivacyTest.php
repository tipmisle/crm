<?php

use App\Models\AuditLog;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\CustomerIdentity;
use App\Models\Message;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function customerPrivacyActingAsConfirmed($test, $owner)
{
    return $test->actingAs($owner)->withSession(['auth.password_confirmed_at' => time()]);
}

function makeCustomerWithConversation(int $workspaceId, string $name = 'Jane Doe'): array
{
    $customer = Customer::create([
        'workspace_id' => $workspaceId,
        'full_name' => $name,
        'email' => 'jane@example.com',
        'phone' => '123456',
        'notes' => 'private note',
    ]);

    CustomerIdentity::create([
        'customer_id' => $customer->id,
        'workspace_id' => $workspaceId,
        'channel_type' => 'instagram',
        'external_id' => 'ig_'.$customer->id,
        'username' => 'jane_ig',
    ]);

    $channel = Channel::create([
        'workspace_id' => $workspaceId,
        'type' => 'instagram',
        'display_name' => 'x',
        'status' => 'connected',
    ]);

    $conversation = Conversation::withoutGlobalScopes()->create([
        'workspace_id' => $workspaceId,
        'channel_id' => $channel->id,
        'customer_id' => $customer->id,
        'external_conversation_id' => 'conv_'.$customer->id,
        'status' => 'new_enquiry',
        'last_message_preview' => 'hello there',
    ]);

    $message = Message::create([
        'conversation_id' => $conversation->id,
        'sender_type' => 'customer',
        'body' => 'a private message',
        'message_type' => 'text',
        'status' => 'sent',
        'sent_at' => now(),
    ]);

    return [$customer, $conversation, $message];
}

test('customer export is scoped to only that customer', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    [$customerA] = makeCustomerWithConversation($workspace->id, 'Customer A');
    [$customerB] = makeCustomerWithConversation($workspace->id, 'Customer B');

    $response = customerPrivacyActingAsConfirmed($this, $owner)->post(route('customers.privacy.export', $customerA->id));
    $response->assertOk();

    $zipBytes = $response->streamedContent();
    $tmpZip = tempnam(sys_get_temp_dir(), 'test-export-').'.zip';
    file_put_contents($tmpZip, $zipBytes);

    $zip = new ZipArchive;
    $zip->open($tmpZip);
    $content = $zip->getFromName('customer.json');
    $zip->close();
    unlink($tmpZip);

    expect($content)->toContain('a private message');
    expect($content)->not->toContain('Customer B');

    expect(AuditLog::where('event', 'privacy.customer.export')->exists())->toBeTrue();
});

test('customer erasure anonymizes only that customer', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    [$customerA, $conversationA, $messageA] = makeCustomerWithConversation($workspace->id, 'Customer A');
    [$customerB] = makeCustomerWithConversation($workspace->id, 'Customer B');

    customerPrivacyActingAsConfirmed($this, $owner)
        ->post(route('customers.privacy.erase', $customerA->id), ['confirm' => true])
        ->assertRedirect();

    $customerA->refresh();
    expect($customerA->full_name)->toBe('Izbrisana stranka');
    expect($customerA->email)->toBeNull();
    expect($customerA->phone)->toBeNull();
    expect($customerA->notes)->toBeNull();
    expect(CustomerIdentity::where('customer_id', $customerA->id)->count())->toBe(0);
    expect($messageA->fresh()->body)->toBeNull();
    expect($conversationA->fresh()->last_message_preview)->toBeNull();

    $customerB->refresh();
    expect($customerB->full_name)->toBe('Customer B');
    expect($customerB->email)->not->toBeNull();
    expect(CustomerIdentity::where('customer_id', $customerB->id)->count())->toBe(1);

    expect(AuditLog::where('event', 'privacy.customer.erased')->exists())->toBeTrue();

    $auditLog = AuditLog::where('event', 'privacy.customer.erased')->first();
    expect(json_encode($auditLog->metadata))->not->toContain('private note');
});

test('customer export with orders does not fatal on the plain-string Order status and includes billing fields', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    [$customer] = makeCustomerWithConversation($workspace->id, 'Customer A');
    $customer->update([
        'address_line' => 'Testna cesta 1',
        'postal_code' => '1000',
        'city' => 'Ljubljana',
        'country' => 'Slovenija',
        'tax_number' => 'SI12345678',
    ]);

    [$order] = createOrderWithConversation($workspace);
    $order->update(['customer_id' => $customer->id]);

    $response = customerPrivacyActingAsConfirmed($this, $owner)->post(route('customers.privacy.export', $customer->id));
    $response->assertOk();

    $zipBytes = $response->streamedContent();
    $tmpZip = tempnam(sys_get_temp_dir(), 'test-export-').'.zip';
    file_put_contents($tmpZip, $zipBytes);

    $zip = new ZipArchive;
    $zip->open($tmpZip);
    $content = $zip->getFromName('customer.json');
    $zip->close();
    unlink($tmpZip);

    expect($content)->toContain('Testna cesta 1');
    expect($content)->toContain($order->status);
});

test('customer erasure clears billing/address fields and deletes local message attachments', function () {
    Storage::fake('local');

    [$workspace, $owner] = createWorkspaceWithUser();
    [$customerA, $conversationA, $messageA] = makeCustomerWithConversation($workspace->id, 'Customer A');

    $customerA->update([
        'address_line' => 'Testna cesta 1',
        'postal_code' => '1000',
        'city' => 'Ljubljana',
        'country' => 'Slovenija',
        'tax_number' => 'SI12345678',
    ]);

    $attachmentPath = "attachments/{$workspace->id}/".Str::random(20).'.jpg';
    Storage::disk('local')->put($attachmentPath, 'fake-bytes');
    $messageA->update(['metadata' => ['attachments' => [
        ['source' => 'local', 'path' => $attachmentPath, 'type' => 'image'],
    ]]]);

    customerPrivacyActingAsConfirmed($this, $owner)
        ->post(route('customers.privacy.erase', $customerA->id), ['confirm' => true])
        ->assertRedirect();

    $customerA->refresh();
    expect($customerA->address_line)->toBeNull();
    expect($customerA->postal_code)->toBeNull();
    expect($customerA->city)->toBeNull();
    expect($customerA->country)->toBeNull();
    expect($customerA->tax_number)->toBeNull();
    expect($messageA->fresh()->metadata)->toBeNull();

    Storage::disk('local')->assertMissing($attachmentPath);
});

test('erasure requires explicit confirmation', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    [$customer] = makeCustomerWithConversation($workspace->id);

    customerPrivacyActingAsConfirmed($this, $owner)
        ->post(route('customers.privacy.erase', $customer->id), [])
        ->assertSessionHasErrors('confirm');

    expect($customer->fresh()->full_name)->not->toBe('Izbrisana stranka');
});

test('a member of a different workspace cannot export or erase another workspace customer', function () {
    [$workspace] = createWorkspaceWithUser();
    [$customer] = makeCustomerWithConversation($workspace->id);

    [, $otherOwner] = createWorkspaceWithUser();

    customerPrivacyActingAsConfirmed($this, $otherOwner)->post(route('customers.privacy.export', $customer->id))->assertNotFound();
    customerPrivacyActingAsConfirmed($this, $otherOwner)->post(route('customers.privacy.erase', $customer->id), ['confirm' => true])->assertNotFound();
});

test('customer export/erase requires a recently confirmed password', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    [$customer] = makeCustomerWithConversation($workspace->id);

    $this->actingAs($owner)->post(route('customers.privacy.export', $customer->id))
        ->assertRedirect(route('password.confirm.app'));

    $this->actingAs($owner)->post(route('customers.privacy.erase', $customer->id), ['confirm' => true])
        ->assertRedirect(route('password.confirm.app'));

    expect($customer->fresh()->full_name)->not->toBe('Izbrisana stranka');
});

test('an unauthenticated request cannot export or erase a customer', function () {
    [$workspace] = createWorkspaceWithUser();
    [$customer] = makeCustomerWithConversation($workspace->id);

    $this->post(route('customers.privacy.export', $customer->id))->assertRedirect(route('login'));
    $this->post(route('customers.privacy.erase', $customer->id), ['confirm' => true])->assertRedirect(route('login'));
});
