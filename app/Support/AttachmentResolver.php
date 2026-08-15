<?php

namespace App\Support;

use App\Models\Message;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Resolves and streams a single attachment referenced by
 * Message.metadata['attachments'][$index] from the private 'local' disk.
 * Callers are responsible for authorization BEFORE calling respond() — see
 * App\Http\Controllers\Inbox\AttachmentController and
 * App\Http\Controllers\Admin\SupportContentController::attachment().
 */
class AttachmentResolver
{
    public function respond(Message $message, int $index): StreamedResponse
    {
        $attachment = $message->metadata['attachments'][$index] ?? null;

        abort_if(! $attachment, 404);
        // Provider-hosted (Meta CDN) attachments aren't ours to serve —
        // the frontend uses that URL directly and never reaches this route.
        abort_if(($attachment['source'] ?? null) !== 'local', 404);

        $path = $attachment['path'] ?? null;
        abort_if(! $path || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, headers: [
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
