<?php

use App\Jobs\ProcessMetaWebhook;

test('a queued meta webhook job never serializes the raw message text in plaintext', function () {
    $marker = 'TOP_SECRET_CUSTOMER_MESSAGE_'.uniqid();

    $payload = [
        'object' => 'instagram',
        'entry' => [[
            'id' => 'ig_123',
            'messaging' => [[
                'sender' => ['id' => 'sender_ext_1'],
                'message' => ['mid' => 'mid_1', 'text' => $marker],
            ]],
        ]],
    ];

    $job = new ProcessMetaWebhook($payload);

    // This mirrors what Laravel's queue worker actually persists to the
    // `jobs` / `failed_jobs` tables: the job object, PHP-serialized.
    $serialized = serialize($job);

    expect($serialized)->not->toContain($marker);
});
