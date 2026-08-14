<?php

use App\Models\Conversation;
use App\Models\CustomerIdentity;
use App\Services\Messaging\DTOs\NormalizedIncomingMessage;
use App\Services\Messaging\MessageIngestionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

function normalizedMessage(string $channelExternalId, string $senderExternalId, string $messageId, ?string $text = 'Hi'): NormalizedIncomingMessage
{
    return new NormalizedIncomingMessage(
        provider: 'meta',
        channelExternalId: $channelExternalId,
        conversationExternalId: $senderExternalId,
        senderExternalId: $senderExternalId,
        senderName: null,
        senderUsername: null,
        messageExternalId: $messageId,
        text: $text,
        attachments: [],
        sentAt: Carbon::now(),
        rawMetadata: [],
    );
}

test('two messages from the same external sender resolve to the same identity and conversation', function () {
    Http::fake(['*' => Http::response([], 200)]);

    [$workspace] = createWorkspaceWithUser();
    createMetaChannel($workspace, 'instagram', 'ig_123');

    $ingestion = app(MessageIngestionService::class);

    $first = $ingestion->ingest(normalizedMessage('ig_123', 'sender_ext_1', 'mid_1', 'First message'));
    $second = $ingestion->ingest(normalizedMessage('ig_123', 'sender_ext_1', 'mid_2', 'Second message'));

    expect($first->conversation_id)->toBe($second->conversation_id);
    expect(CustomerIdentity::where('external_id', 'sender_ext_1')->count())->toBe(1);
});

test('creating a customer from a conversation attaches the existing identity instead of duplicating it', function () {
    Http::fake(['*' => Http::response([], 200)]);

    [$workspace, $user] = createWorkspaceWithUser();
    createMetaChannel($workspace, 'instagram', 'ig_123');

    $ingestion = app(MessageIngestionService::class);
    $ingestion->ingest(normalizedMessage('ig_123', 'sender_ext_1', 'mid_1'));

    $conversation = Conversation::where('external_conversation_id', 'sender_ext_1')->firstOrFail();
    expect($conversation->customer_id)->toBeNull();

    $this->actingAs($user)->post(route('inbox.create-customer', $conversation->id))->assertRedirect();

    expect(CustomerIdentity::where('external_id', 'sender_ext_1')->count())->toBe(1);

    $identity = CustomerIdentity::where('external_id', 'sender_ext_1')->first();
    expect($identity->customer_id)->not->toBeNull();

    // A follow-up message from the same external identity must resolve to
    // the customer that was just created, not create a new conversation.
    $second = $ingestion->ingest(normalizedMessage('ig_123', 'sender_ext_1', 'mid_2', 'Follow-up'));
    $second->conversation->refresh();

    expect($second->conversation->customer_id)->toBe($identity->customer_id);
});
