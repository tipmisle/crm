<?php

namespace App\Http\Controllers\Inbox;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Support\AttachmentResolver;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function show(Request $request, int $message, int $index, AttachmentResolver $resolver): StreamedResponse
    {
        // Message has no workspace_id of its own — authorize via its
        // conversation, the same boundary the rest of the Inbox uses.
        // Conversation's global BelongsToWorkspace scope defaults to
        // matching nothing for a user with no current_workspace_id, which
        // would make the relation silently unusable in some contexts — load
        // it explicitly unscoped and do the workspace check ourselves
        // instead of depending on that scope's exact behavior.
        $message = Message::with(['conversation' => fn ($q) => $q->withoutGlobalScopes()])->findOrFail($message);

        abort_unless(
            $message->conversation && $message->conversation->workspace_id === $request->user()->current_workspace_id,
            404
        );

        return $resolver->respond($message, $index);
    }
}
