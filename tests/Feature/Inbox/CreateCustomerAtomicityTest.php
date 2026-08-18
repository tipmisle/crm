<?php

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\CustomerIdentity;

afterEach(function () {
    // See tests/Feature/Orders/AtomicCreateUpdateTest.php — a model event
    // listener registered via a static closure is bound to the class and
    // must not leak into later tests.
    CustomerIdentity::flushEventListeners();
});

test('creating a customer from a conversation rolls back if the identity link fails', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $channel = createMetaChannel($workspace, 'instagram', 'ig_atomicity');

    $conversation = Conversation::create([
        'workspace_id' => $workspace->id,
        'channel_id' => $channel->id,
        'external_conversation_id' => 'sender_atomicity',
        'customer_display_name' => 'Ana Novak',
        'status' => 'new_enquiry',
    ]);

    $customersBefore = Customer::count();

    CustomerIdentity::creating(function () {
        throw new RuntimeException('Simulated identity failure');
    });

    $this->actingAs($user)->post(route('inbox.create-customer', $conversation))->assertStatus(500);

    expect(Customer::count())->toBe($customersBefore);
    expect($conversation->fresh()->customer_id)->toBeNull();
});
