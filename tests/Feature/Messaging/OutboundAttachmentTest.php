<?php

use App\Enums\MessageStatus;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

test('an image attachment is stored, sent, and recorded on the message', function () {
    Storage::fake('local');
    Http::fake(['*/messages*' => Http::response(['message_id' => 'mid_attachment_1'], 200)]);

    [$workspace, $user] = createWorkspaceWithUser();
    $channel = createMetaChannel($workspace, 'instagram', 'ig_123');
    $conversation = Conversation::create([
        'workspace_id' => $workspace->id,
        'channel_id' => $channel->id,
        'external_conversation_id' => 'sender_ext_1',
        'status' => 'new_enquiry',
    ]);

    $file = UploadedFile::fake()->image('cake.jpg', 800, 600);

    $this->actingAs($user)->post(route('inbox.messages.store', $conversation->id), [
        'attachment' => $file,
    ])->assertSessionMissing('error');

    $message = Message::where('conversation_id', $conversation->id)->latest()->first();

    expect($message->status)->toBe(MessageStatus::Sent);
    expect($message->external_message_id)->toBe('mid_attachment_1');
    expect($message->metadata['attachments'][0]['type'])->toBe('image');
    expect($message->metadata['attachments'][0]['source'])->toBe('local');
    expect($message->metadata['attachments'][0]['path'])->toContain('inbox-attachments/');
    expect($message->metadata['attachments'][0])->not->toHaveKey('url');

    Storage::disk('local')->assertExists($message->metadata['attachments'][0]['path']);

    Http::assertSent(function ($request) {
        return str_contains($request->body(), 'filedata') && str_contains($request->body(), '"attachment"');
    });
});

test('sending requires either a body or an attachment', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $channel = createMetaChannel($workspace, 'instagram', 'ig_123');
    $conversation = Conversation::create([
        'workspace_id' => $workspace->id,
        'channel_id' => $channel->id,
        'external_conversation_id' => 'sender_ext_1',
        'status' => 'new_enquiry',
    ]);

    $this->actingAs($user)->post(route('inbox.messages.store', $conversation->id), [])
        ->assertSessionHas('error');

    expect(Message::where('conversation_id', $conversation->id)->count())->toBe(0);
});
