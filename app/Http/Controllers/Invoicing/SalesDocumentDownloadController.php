<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\SalesDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Auth-gated PDF download — the same pattern as Inbox\AttachmentController
 * (explicit workspace check + private 'local' disk stream), inlined here
 * since only this one controller needs it.
 */
class SalesDocumentDownloadController extends Controller
{
    public function show(Request $request, SalesDocument $document): StreamedResponse
    {
        abort_unless($document->workspace_id === $request->user()->current_workspace_id, 404);
        abort_unless($document->pdf_path && Storage::disk('local')->exists($document->pdf_path), 404);

        return Storage::disk('local')->response($document->pdf_path, headers: [
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
